<?php
/**
 * KiWizardService — KI-geführter Erstellungs-Wizard (Spec §6/§9).
 *
 * Ein Chat, der nach jeder Antwort strukturierte Profilfelder aktualisiert. Das
 * Modell MUSS validierbares JSON liefern (Spec §9): {assistant_message,
 * profile_patch, next_questions, completion, risk_flags}. Der Server validiert
 * den Patch serverseitig (Whitelist in KiMitarbeiterService) — Status- und
 * Rechteänderungen aus Modell-Output werden verworfen.
 *
 * Muster wie api/v1/admin/author-wizard.php (Prompt-Schema → JSON-Extraktion →
 * Defaults/Whitelist), Modell/Provider aus ai_models/settings.
 */

namespace Services;

require_once SERVICES_PATH . '/AIService.php';

class KiWizardService
{
    private $db;
    private $svc;

    public function __construct($db = null)
    {
        $this->db = $db ?: \Core\Database::getInstance();
        require_once SERVICES_PATH . '/KiMitarbeiterService.php';
        $this->svc = new KiMitarbeiterService($this->db);
    }

    /** Bisheriger Wizard-Verlauf (für die UI). */
    public function verlauf(int $employeeId): array
    {
        return $this->db->query(
            "SELECT role, content, created_at FROM ai_wizard_messages WHERE ai_employee_id = ? ORDER BY id ASC",
            [$employeeId]
        );
    }

    /**
     * Eine Wizard-Runde: Nutzernachricht rein → strukturierte KI-Antwort.
     * @return array {assistant_message, profile_patch, next_questions, completion, risk_flags, profile}
     */
    public function antwort(int $employeeId, string $userMessage, int $actorId): array
    {
        $employee = $this->svc->get($employeeId);
        if (!$employee) throw new \RuntimeException('KI-Mitarbeiter nicht gefunden.');

        // Nutzernachricht speichern
        $this->db->insert('ai_wizard_messages', [
            'ai_employee_id' => $employeeId, 'role' => 'user', 'content' => $userMessage,
        ]);

        [$ai, $model] = $this->getAi();

        $systemPrompt = $this->systemPrompt($employee['profile'] ?? []);
        $messages = $this->buildMessages($employeeId);

        $response = $ai->chat($messages, $systemPrompt);
        $data = $this->extractJson($response['content'] ?? '');

        // Serverseitige Härtung
        $assistantMessage = trim((string) ($data['assistant_message'] ?? ''));
        if ($assistantMessage === '') {
            $assistantMessage = 'Ich habe das aufgenommen. Womit möchtest Du weitermachen?';
        }
        $patch = is_array($data['profile_patch'] ?? null) ? $data['profile_patch'] : [];
        $nextQuestions = is_array($data['next_questions'] ?? null) ? $data['next_questions'] : [];
        $riskFlags = is_array($data['risk_flags'] ?? null) ? $data['risk_flags'] : [];

        // Patch anwenden (KiMitarbeiterService verwirft nicht erlaubte Felder)
        $profile = $patch ? $this->svc->patchProfile($employeeId, $patch, $actorId) : ($employee['profile'] ?? []);
        // Name/Rolle auch in die Skalarspalten spiegeln, wenn geliefert
        $meta = [];
        if (!empty($patch['role_title'])) $meta['role_title'] = (string) $patch['role_title'];
        if (!empty($patch['name'])) {
            $meta['name'] = (string) $patch['name'];
        } elseif (($employee['name'] ?? '') === 'Neuer KI-Mitarbeiter' && !empty($patch['role_title'])) {
            // Solange noch der Default-Name steht, die Rollenbezeichnung als Name uebernehmen.
            $meta['name'] = (string) $patch['role_title'];
        }
        if ($meta) $this->svc->updateMeta($employeeId, $meta, $actorId);

        $completion = $this->svc->completeness($profile);

        // KI-Antwort speichern (mit angewandtem Patch)
        $this->db->insert('ai_wizard_messages', [
            'ai_employee_id' => $employeeId, 'role' => 'assistant',
            'content' => $assistantMessage,
            'patch' => json_encode($patch, JSON_UNESCAPED_UNICODE),
        ]);

        // Usage-Log (fehlertolerant)
        try {
            $this->db->insert('usage_logs', [
                'user_id' => $actorId,
                'action_type' => 'generation',
                'model_used' => $response['model'] ?? $model,
                'tokens_input' => $response['tokens']['input'] ?? 0,
                'tokens_output' => $response['tokens']['output'] ?? 0,
                'metadata' => json_encode(['type' => 'ki_wizard', 'employee_id' => $employeeId]),
            ]);
        } catch (\Throwable $e) { /* egal */ }

        return [
            'assistant_message' => $assistantMessage,
            'profile_patch'     => $patch,
            'next_questions'    => $nextQuestions,
            'completion'        => $completion,
            'risk_flags'        => $riskFlags,
            'profile'           => $profile,
        ];
    }

    // ------------------------------------------------------------------ intern

    /** Nachrichten für das Modell: bisheriger Wizard-Verlauf. */
    private function buildMessages(int $employeeId): array
    {
        $rows = $this->db->query(
            "SELECT role, content FROM ai_wizard_messages WHERE ai_employee_id = ? ORDER BY id ASC",
            [$employeeId]
        );
        $messages = [];
        foreach ($rows as $r) {
            $role = $r['role'] === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) $r['content']];
        }
        if (empty($messages)) {
            $messages[] = ['role' => 'user', 'content' => 'Bitte hilf mir, diesen KI-Mitarbeiter zu entwerfen.'];
        }
        return $messages;
    }

    /** System-Prompt mit §9-Schema + Sicherheitsrahmen; überschreibbar via SystemPromptService. */
    private function systemPrompt(array $profile): string
    {
        $profileJson = json_encode($profile ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $default = <<<PROMPT
Du bist ein erfahrener Berater, der einem Teammitglied hilft, im Sparring einen spezialisierten KI-Mitarbeiter zu entwerfen. Du stellst kontextabhängige Rückfragen (nicht alle auf einmal) und baust schrittweise ein strukturiertes Rollenprofil auf.

Vorgehen (Spec-Phasen): 1) Bedarf/Problem klären. 2) Rolle, Aufgaben, ausdrückliche Nicht-Aufgaben, Eskalationsregeln. 3) Arbeitsabläufe. 4) Wissen/Fähigkeiten, Positiv-/Negativbeispiele, verbotene Inhalte. 5) benötigte Zugriffe (nur das Nötigste, jeweils begründet). 6) Persönlichkeit/Kommunikation. 7) mindestens 3 Testfälle (Standardfall, unvollständige Eingabe, kritischer Grenzfall).

Prinzipien: Bedarf vor Lösung; lieber klar abgegrenzte Spezialisten; Least Privilege; im Zweifel eskalieren statt raten. Sprich Deutsch, Du-Ansprache, echte Umlaute.

SICHERHEIT: Du darfst KEINEN Status setzen und KEINE Berechtigungen aktivieren. Schlage Zugriffe nur vor (der Mensch beantragt, ein Admin gibt frei). Setze niemals Felder wie "status" oder "permission_level".

AKTUELLES PROFIL (bereits erfasst):
$profileJson

Antworte AUSSCHLIESSLICH mit EINEM JSON-Objekt (kein Markdown, kein Text davor/danach):
{
  "assistant_message": "Deine nächste Nachricht/Frage an das Teammitglied (kurz, konkret).",
  "profile_patch": { nur geänderte/ergänzte Felder aus: name (kurzer, sprechender Name des KI-Mitarbeiters), role_title, short_description, goals[], tasks[{title,included}], non_tasks[], responsibilities[], escalation_rules[], workflows[{name,steps[]}], skills[], knowledge_sources[], positive_examples[], negative_examples[], quality_rules[], forbidden[], personality{tone,length,address,languages,on_uncertainty}, test_cases[{name,category,input,expected,must_have[],must_not_have[]}], problem_statement, expected_benefit, need_classification },
  "next_questions": [{"id":"...","question":"...","type":"text|single_choice","options":[]}],
  "completion": {"percentage": 0, "missing_sections": []},
  "risk_flags": []
}
PROMPT;

        try {
            if (class_exists('\\Services\\SystemPromptService')) {
                $override = \Services\SystemPromptService::get('ki_wizard');
                if (is_string($override) && trim($override) !== '') {
                    // Override darf den {profile}-Platzhalter nutzen
                    return str_replace('{profile}', $profileJson, $override);
                }
            }
        } catch (\Throwable $e) { /* Default nutzen */ }
        return $default;
    }

    /** JSON aus der Modellantwort ziehen (Code-Block-Strip → decode → Regex-Fallback). */
    private function extractJson(string $content): array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $mm)) {
            $content = trim($mm[1]);
        }
        $data = json_decode($content, true);
        if (!is_array($data) && preg_match('/\{[\s\S]*\}/', $content, $mm)) {
            $data = json_decode($mm[0], true);
        }
        return is_array($data) ? $data : [];
    }

    /** AIService aus Settings (Modell/Provider/Key) — Muster wie author-wizard.php. */
    private function getAi(): array
    {
        $settings = [];
        foreach ($this->db->query("SELECT setting_key, setting_value FROM settings") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $settings = \Core\Settings::decryptMap($settings);

        $model = $settings['default_model'] ?? 'gpt-4';
        $modelRow = $this->db->queryOne("SELECT model_id, provider FROM ai_models WHERE model_id = ? AND is_active = 1", [$model]);
        $provider = $modelRow['provider'] ?? (strpos($model, 'claude') !== false ? 'anthropic' : (strpos($model, 'gemini') !== false ? 'google' : 'openai'));

        $apiKey = '';
        if ($provider === 'anthropic') $apiKey = $settings['anthropic_api_key'] ?? '';
        elseif ($provider === 'google') $apiKey = $settings['google_api_key'] ?? '';
        elseif ($provider === 'local') $apiKey = $settings['local_api_key'] ?? '';
        else $apiKey = $settings['openai_api_key'] ?? '';

        if ($apiKey === '' && $provider !== 'local') {
            throw new \RuntimeException('Kein API-Schlüssel für ' . $provider . ' konfiguriert (Einstellungen → KI-Modelle).');
        }

        $ai = new \Services\AIService($apiKey, $provider);
        if ($provider === 'local' && !empty($settings['local_base_url'])) {
            $ai->configureLocal($settings['local_base_url']);
        }
        $ai->setModel($model);
        $ai->setMaxTokens(2000);
        $ai->setTimeout(90);
        return [$ai, $model];
    }
}
