<?php
/**
 * KiSuggestionService — Lernkreis mit Kontrolle (Spec §12).
 *
 * Aus Feedback zu einem Lauf erzeugt die KI einen Änderungsvorschlag (Diff auf
 * dem Profil). Der Vorschlag wird NUR gespeichert — er wirkt erst, wenn ein
 * Mensch ihn übernimmt (review-to-activate). Übernahme erzeugt eine neue
 * Profilversion. Sicherheitsrelevante Felder (Status/Rechte) werden verworfen.
 */

namespace Services;

require_once SERVICES_PATH . '/AIService.php';

class KiSuggestionService
{
    private $db;
    private $svc;

    public function __construct($db = null)
    {
        $this->db = $db ?: \Core\Database::getInstance();
        require_once SERVICES_PATH . '/KiMitarbeiterService.php';
        $this->svc = new KiMitarbeiterService($this->db);
    }

    /** Feedback-Liste eines Mitarbeiters (mit Vorschlag). */
    public function feedbackListe(int $employeeId): array
    {
        $rows = $this->db->query(
            "SELECT f.*, u.name AS user_name FROM ai_feedback f
             LEFT JOIN users u ON u.id = f.user_id
             WHERE f.ai_employee_id = ? ORDER BY f.created_at DESC", [$employeeId]
        );
        foreach ($rows as &$r) {
            $r['suggested_change'] = $r['suggested_change'] ? json_decode($r['suggested_change'], true) : null;
        }
        return $rows;
    }

    /** KI-Änderungsvorschlag aus einem Feedback erzeugen und speichern. */
    public function vorschlagErzeugen(int $feedbackId, int $actorId): array
    {
        $fb = $this->db->queryOne("SELECT * FROM ai_feedback WHERE id = ?", [$feedbackId]);
        if (!$fb) throw new \RuntimeException('Feedback nicht gefunden.');
        $e = $this->svc->get((int) $fb['ai_employee_id']);
        if (!$e) throw new \RuntimeException('KI-Mitarbeiter nicht gefunden.');

        [$ai, $model] = $this->getAi();
        $profileJson = json_encode($e['profile'] ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $system = <<<P
Du hilfst, das Profil eines KI-Mitarbeiters aus konkretem Feedback zu verbessern. Schlage eine KLEINE, gezielte Änderung vor (z.B. neue Negativregel, zusätzliches Positivbeispiel, präzisere Aufgabe/Eskalation). Ändere NIE Status oder Berechtigungen.

AKTUELLES PROFIL:
$profileJson

Antworte AUSSCHLIESSLICH mit JSON:
{"summary":"Ein Satz, was geändert werden soll und warum","profile_patch":{ nur die zu ändernden Profilfelder aus role_title, short_description, goals, tasks, non_tasks, escalation_rules, quality_rules, forbidden, positive_examples, negative_examples, knowledge_sources, personality }}
P;
        $userMsg = "Feedback-Typ: {$fb['feedback_type']}\nBewertung: " . ($fb['rating'] ?? '-') . "\nKommentar: " . ($fb['comment'] ?? '');
        $resp = $ai->chat([['role' => 'user', 'content' => $userMsg]], $system);
        $data = $this->extractJson($resp['content'] ?? '');
        $patch = is_array($data['profile_patch'] ?? null) ? $data['profile_patch'] : [];
        // Auf erlaubte Profilfelder beschraenken (gleiche Whitelist wie Service)
        $patch = array_intersect_key($patch, array_flip(KiMitarbeiterService::PROFILE_KEYS));
        $suggestion = ['summary' => (string) ($data['summary'] ?? ''), 'profile_patch' => $patch];
        $this->db->update('ai_feedback', ['suggested_change' => json_encode($suggestion, JSON_UNESCAPED_UNICODE)], 'id = ?', [$feedbackId]);
        return $suggestion;
    }

    /** Vorschlag übernehmen: Patch anwenden + neue Version. */
    public function uebernehmen(int $feedbackId, int $actorId): int
    {
        $fb = $this->db->queryOne("SELECT * FROM ai_feedback WHERE id = ?", [$feedbackId]);
        if (!$fb || empty($fb['suggested_change'])) throw new \RuntimeException('Kein Vorschlag vorhanden.');
        $sug = json_decode($fb['suggested_change'], true);
        $patch = $sug['profile_patch'] ?? [];
        if (!$patch) throw new \RuntimeException('Vorschlag enthält keine Änderung.');
        $this->svc->patchProfile((int) $fb['ai_employee_id'], $patch, $actorId);
        $v = $this->svc->publishVersion((int) $fb['ai_employee_id'], 'Aus Feedback #' . $feedbackId . ': ' . ($sug['summary'] ?? ''), $actorId, $actorId);
        $this->db->update('ai_feedback', ['status' => 'accepted', 'resolved_at' => date('Y-m-d H:i:s')], 'id = ?', [$feedbackId]);
        \Core\AuditLog::record('ai_employee', (string) $fb['ai_employee_id'], 'feedback_applied', ['feedback' => $feedbackId, 'version' => $v], $actorId);
        return $v;
    }

    /** Vorschlag ablehnen. */
    public function ablehnen(int $feedbackId, int $actorId): void
    {
        $fb = $this->db->queryOne("SELECT ai_employee_id FROM ai_feedback WHERE id = ?", [$feedbackId]);
        if (!$fb) throw new \RuntimeException('Feedback nicht gefunden.');
        $this->db->update('ai_feedback', ['status' => 'rejected', 'resolved_at' => date('Y-m-d H:i:s')], 'id = ?', [$feedbackId]);
        \Core\AuditLog::record('ai_employee', (string) $fb['ai_employee_id'], 'feedback_rejected', ['feedback' => $feedbackId], $actorId);
    }

    private function extractJson(string $content): array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $mm)) $content = trim($mm[1]);
        $d = json_decode($content, true);
        if (!is_array($d) && preg_match('/\{[\s\S]*\}/', $content, $mm)) $d = json_decode($mm[0], true);
        return is_array($d) ? $d : [];
    }

    private function getAi(): array
    {
        $settings = [];
        foreach ($this->db->query("SELECT setting_key, setting_value FROM settings") as $row) $settings[$row['setting_key']] = $row['setting_value'];
        $settings = \Core\Settings::decryptMap($settings);
        $model = $settings['default_model'] ?? 'gpt-4';
        $modelRow = $this->db->queryOne("SELECT provider FROM ai_models WHERE model_id = ? AND is_active = 1", [$model]);
        $provider = $modelRow['provider'] ?? (strpos($model, 'claude') !== false ? 'anthropic' : (strpos($model, 'gemini') !== false ? 'google' : 'openai'));
        $apiKey = $provider === 'anthropic' ? ($settings['anthropic_api_key'] ?? '') : ($provider === 'google' ? ($settings['google_api_key'] ?? '') : ($provider === 'local' ? ($settings['local_api_key'] ?? '') : ($settings['openai_api_key'] ?? '')));
        if ($apiKey === '' && $provider !== 'local') throw new \RuntimeException('Kein API-Schlüssel für ' . $provider . '.');
        $ai = new \Services\AIService($apiKey, $provider);
        if ($provider === 'local' && !empty($settings['local_base_url'])) $ai->configureLocal($settings['local_base_url']);
        $ai->setModel($model); $ai->setMaxTokens(1200); $ai->setTimeout(90);
        return [$ai, $model];
    }
}
