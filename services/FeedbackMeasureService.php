<?php
/**
 * FeedbackMeasureService — "Maßnahmen" (To-dos) aus internem Feedback.
 *
 * - CRUD auf feedback_measures (+ n:m-Verknuepfung feedback_measure_links)
 * - fromFeedback(): ein Feedback in eine Maßnahme umwandeln
 * - analyze(): offene, noch nicht verarbeitete Feedbacks per LLM zu Themen
 *   clustern und daraus Maßnahmen-Vorschlaege (source='ki') anlegen.
 *
 * Bewusst lese- UND schreibfaehig in einem Service, damit sowohl die API
 * (api/v1/admin/measures.php) als auch das CLI/Cron (cli/feedback-analyze.php)
 * exakt dieselbe Logik nutzen.
 */

namespace Services;

class FeedbackMeasureService
{
    private $db;

    public const STATUSES   = ['offen', 'in_arbeit', 'erledigt', 'verworfen'];
    public const PRIORITIES  = ['hoch', 'mittel', 'niedrig'];

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------ Lesen

    /** Liste der Maßnahmen (optional nach Status gefiltert) inkl. Feedback-Anzahl. */
    public function listMeasures(string $status = 'all'): array
    {
        $where = '';
        $params = [];
        if ($status !== 'all' && in_array($status, self::STATUSES, true)) {
            $where = 'WHERE m.status = ?';
            $params[] = $status;
        }
        return $this->db->query(
            "SELECT m.*,
                    (SELECT COUNT(*) FROM feedback_measure_links l WHERE l.measure_id = m.id) AS feedback_count,
                    u.name AS creator_name
             FROM feedback_measures m
             LEFT JOIN users u ON u.id = m.created_by
             $where
             ORDER BY
                CASE m.status WHEN 'offen' THEN 1 WHEN 'in_arbeit' THEN 2 WHEN 'erledigt' THEN 3 ELSE 4 END,
                FIELD(m.priority, 'hoch','mittel','niedrig'),
                m.created_at DESC",
            $params
        );
    }

    /** Eine Maßnahme inkl. verknuepfter Feedbacks. */
    public function getMeasure(int $id): ?array
    {
        $m = $this->db->queryOne("SELECT * FROM feedback_measures WHERE id = ?", [$id]);
        if (!$m) {
            return null;
        }
        $m['feedbacks'] = $this->db->query(
            "SELECT f.id, f.feedback_type, f.description, f.page_url, f.status, f.created_at,
                    u.name AS user_name
             FROM feedback_measure_links l
             JOIN internal_feedback f ON f.id = l.feedback_id
             LEFT JOIN users u ON u.id = f.user_id
             WHERE l.measure_id = ?
             ORDER BY f.created_at DESC",
            [$id]
        );
        return $m;
    }

    /** Zaehlt offene Maßnahmen (fuer Menue-Badge). */
    public function countOpen(): int
    {
        return (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM feedback_measures WHERE status IN ('offen','in_arbeit')"
        );
    }

    // --------------------------------------------------------------- Schreiben

    /** Maßnahme anlegen. $data: title, description?, area?, priority?, source?, created_by?, feedback_ids? */
    public function create(array $data): int
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Titel erforderlich');
        }
        $id = (int) $this->db->insert('feedback_measures', [
            'title'       => mb_substr($title, 0, 255),
            'description' => $data['description'] ?? null,
            'area'        => isset($data['area']) ? mb_substr((string)$data['area'], 0, 100) : null,
            'status'      => in_array($data['status'] ?? '', self::STATUSES, true) ? $data['status'] : 'offen',
            'priority'    => in_array($data['priority'] ?? '', self::PRIORITIES, true) ? $data['priority'] : 'mittel',
            'source'      => ($data['source'] ?? '') === 'ki' ? 'ki' : 'manuell',
            'created_by'  => $data['created_by'] ?? null,
        ]);
        if (!empty($data['feedback_ids']) && is_array($data['feedback_ids'])) {
            $this->linkFeedbacks($id, $data['feedback_ids']);
        }
        return $id;
    }

    /** Felder/Status/Prioritaet aktualisieren. */
    public function update(int $id, array $data): void
    {
        $updates = [];
        if (isset($data['title']) && trim($data['title']) !== '') {
            $updates['title'] = mb_substr(trim($data['title']), 0, 255);
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if (array_key_exists('area', $data)) {
            $updates['area'] = $data['area'] !== null ? mb_substr((string)$data['area'], 0, 100) : null;
        }
        if (isset($data['status']) && in_array($data['status'], self::STATUSES, true)) {
            $updates['status'] = $data['status'];
        }
        if (isset($data['priority']) && in_array($data['priority'], self::PRIORITIES, true)) {
            $updates['priority'] = $data['priority'];
        }
        if ($updates) {
            $this->db->update('feedback_measures', $updates, 'id = ?', [$id]);
        }
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM feedback_measure_links WHERE measure_id = ?", [$id]);
        $this->db->execute("DELETE FROM feedback_measures WHERE id = ?", [$id]);
    }

    /** Feedbacks mit einer Maßnahme verknuepfen (idempotent). */
    public function linkFeedbacks(int $measureId, array $feedbackIds): void
    {
        foreach (array_unique(array_map('intval', $feedbackIds)) as $fid) {
            if ($fid <= 0) {
                continue;
            }
            try {
                $this->db->execute(
                    "INSERT IGNORE INTO feedback_measure_links (measure_id, feedback_id) VALUES (?, ?)",
                    [$measureId, $fid]
                );
            } catch (\Throwable $e) { /* Duplikat/FK egal */ }
        }
    }

    /**
     * Ein einzelnes Feedback in eine Maßnahme umwandeln. Gibt die neue measure_id zurueck.
     *
     * Uebernimmt die im Cockpit erarbeitete Planung automatisch mit:
     * - Titel/Bereich/Prioritaet aus der KI-Analyse (internal_feedback.ai_suggestion), falls vorhanden
     * - die "Naechsten Schritte" (internal_feedback.next_steps, auch von Hand editierbar) werden an die
     *   Beschreibung angehaengt, damit die Maßnahme den Umsetzungsplan direkt enthaelt und nicht
     *   nachtraeglich von Hand ergaenzt werden muss.
     */
    public function fromFeedback(int $feedbackId, ?int $userId = null): int
    {
        $fb = $this->db->queryOne("SELECT * FROM internal_feedback WHERE id = ?", [$feedbackId]);
        if (!$fb) {
            throw new \RuntimeException('Feedback nicht gefunden');
        }
        $desc = trim((string)($fb['description'] ?? ''));

        // KI-Vorschlag (falls das Feedback analysiert wurde) als bevorzugte Quelle fuer die Kerndaten
        $sug = [];
        if (!empty($fb['ai_suggestion'])) {
            $decoded = json_decode((string)$fb['ai_suggestion'], true);
            if (is_array($decoded) && !empty($decoded['measure']) && is_array($decoded['measure'])) {
                $sug = $decoded['measure'];
            }
        }

        // Titel: KI-Titel > Feedback-Titel > gekuerzte Beschreibung
        $title = trim((string)($sug['title'] ?? ''));
        if ($title === '') {
            $title = trim((string)($fb['title'] ?? ''));
        }
        if ($title === '') {
            $title = mb_substr($desc, 0, 80);
            if (mb_strlen($desc) > 80) {
                $title .= '…';
            }
        }

        // Beschreibung: Feedback-Text + die erarbeitete Planung ("Naechste Schritte")
        $steps = trim((string)($fb['next_steps'] ?? ''));
        $description = $desc;
        if ($steps !== '') {
            $description = ($description !== '' ? $description . "\n\n" : '') . "Nächste Schritte:\n" . $steps;
        }

        $typeArea = [
            'bug' => 'Fehler', 'feature' => 'Feature', 'improvement' => 'Verbesserung', 'other' => 'Sonstiges',
        ];
        $area = trim((string)($sug['area'] ?? '')) ?: ($typeArea[$fb['feedback_type']] ?? null);
        $priority = in_array($sug['priority'] ?? '', self::PRIORITIES, true)
            ? $sug['priority']
            : ($fb['feedback_type'] === 'bug' ? 'hoch' : 'mittel');

        $id = $this->create([
            'title'        => $title !== '' ? $title : 'Maßnahme aus Feedback #' . $feedbackId,
            'description'  => $description,
            'area'         => $area,
            'priority'     => $priority,
            'source'       => 'manuell',
            'created_by'   => $userId,
            'feedback_ids' => [$feedbackId],
        ]);
        // Feedback auf "in Bearbeitung" setzen, damit es aus "neu" rausfaellt
        $this->db->update('internal_feedback', ['status' => 'in_progress'], 'id = ?', [$feedbackId]);
        return $id;
    }

    // ----------------------------------------------------------- KI-Analyse

    /**
     * Offene, noch NICHT mit einer Maßnahme verknuepfte Feedbacks holen.
     * "verarbeitet" = im Link-Table vorhanden (kein Schema-Zusatz noetig).
     */
    public function unprocessedFeedback(int $limit = 60): array
    {
        return $this->db->query(
            "SELECT f.id, f.feedback_type, f.description, f.page_url, u.name AS user_name, f.created_at
             FROM internal_feedback f
             LEFT JOIN users u ON u.id = f.user_id
             WHERE f.status = 'new'
               AND f.id NOT IN (SELECT feedback_id FROM feedback_measure_links)
             ORDER BY f.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * KI-Analyse: clustert die offenen Feedbacks zu Themen und legt daraus
     * Maßnahmen (source='ki', status='offen') an. Gibt ['created'=>int,
     * 'measures'=>[...], 'analyzed'=>int] zurueck.
     *
     * @param array  $settings  entschluesselte settings (api keys)
     * @param int|null $userId   wer die Analyse ausloest (created_by der Maßnahmen)
     */
    public function analyze(array $settings, ?int $userId = null): array
    {
        $feedbacks = $this->unprocessedFeedback();
        if (empty($feedbacks)) {
            return ['created' => 0, 'measures' => [], 'analyzed' => 0, 'message' => 'Keine offenen, unverarbeiteten Feedbacks.'];
        }

        [$apiKey, $provider, $model] = $this->pickModel($settings);
        if (!$apiKey) {
            throw new \RuntimeException('Kein API-Key (Anthropic/OpenAI) konfiguriert.');
        }

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, $provider);
        $ai->setModel($model);

        // Feedbacks kompakt auflisten
        $list = '';
        $validIds = [];
        foreach ($feedbacks as $f) {
            $validIds[(int)$f['id']] = true;
            $list .= sprintf(
                "- ID %d | Typ: %s | Seite: %s\n  \"%s\"\n",
                (int)$f['id'],
                $f['feedback_type'],
                $f['page_url'] ?: '-',
                trim(preg_replace('/\s+/', ' ', (string)$f['description']))
            );
        }

        $systemPrompt = "Du bist Produkt-Managerin fuer ein internes KI-Text-Werkzeug. "
            . "Du bekommst eine Liste von Nutzer-Feedbacks (Bugs, Feature-Wuensche, Verbesserungen). "
            . "Buendle sie zu konkreten, umsetzbaren MASSNAHMEN (To-dos). Mehrere Feedbacks zum selben "
            . "Thema gehoeren in EINE Maßnahme. Jede Maßnahme braucht einen praegnanten Titel, einen "
            . "kurzen Umsetzungs-Vorschlag, einen Bereich und eine Prioritaet.\n"
            . "Regeln fuer den Stil: Du/Dich gross. Keine Anglizismen wo deutsche Begriffe passen. "
            . "Keine Gedankenstriche.\n\n"
            . "Antworte AUSSCHLIESSLICH mit JSON in genau dieser Form:\n"
            . "{\n  \"measures\": [\n    {\n"
            . "      \"title\": \"praegnanter Titel (max 80 Zeichen)\",\n"
            . "      \"area\": \"betroffener Bereich, z.B. Chat, Wissen, CRM, Tagesplaner, Allgemein\",\n"
            . "      \"priority\": \"hoch|mittel|niedrig\",\n"
            . "      \"description\": \"2-4 Saetze: was ist zu tun und warum\",\n"
            . "      \"feedback_ids\": [Liste der IDs, die zu dieser Maßnahme gehoeren]\n"
            . "    }\n  ]\n}";

        $userPrompt = "Hier die offenen Feedbacks. Buendle sie zu Maßnahmen:\n\n" . $list;

        $response = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $systemPrompt);
        $content = $response['content'] ?? '';

        $json = null;
        if (preg_match('/\{[\s\S]*\}/m', $content, $mm)) {
            $json = json_decode($mm[0], true);
        }
        if (!$json || empty($json['measures']) || !is_array($json['measures'])) {
            throw new \RuntimeException('KI-Antwort konnte nicht als Maßnahmen gelesen werden.');
        }

        $created = [];
        foreach ($json['measures'] as $mraw) {
            $title = trim((string)($mraw['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            // nur gueltige (in dieser Analyse enthaltene) Feedback-IDs verlinken
            $fids = [];
            foreach ((array)($mraw['feedback_ids'] ?? []) as $fid) {
                if (isset($validIds[(int)$fid])) {
                    $fids[] = (int)$fid;
                }
            }
            $mid = $this->create([
                'title'        => $title,
                'description'  => $mraw['description'] ?? null,
                'area'         => $mraw['area'] ?? null,
                'priority'     => in_array($mraw['priority'] ?? '', self::PRIORITIES, true) ? $mraw['priority'] : 'mittel',
                'source'       => 'ki',
                'created_by'   => $userId,
                'feedback_ids' => $fids,
            ]);
            // Verbuchte Feedbacks aus "neu" nehmen -> sie sind jetzt in einer Maßnahme erfasst
            foreach ($fids as $fid) {
                $this->db->update('internal_feedback', ['status' => 'in_progress'], 'id = ?', [$fid]);
            }
            $created[] = $this->getMeasure($mid);
        }

        return [
            'created'  => count($created),
            'measures' => $created,
            'analyzed' => count($feedbacks),
            'model'    => $provider . '/' . $model,
        ];
    }

    /**
     * Einzel-Analyse EINES Feedbacks: KI erkennt die Maßnahme dahinter und
     * schlaegt naechste Schritte vor. Speichert das Ergebnis in
     * internal_feedback.ai_suggestion und befuellt next_steps, falls noch leer.
     * Rueckgabe: ['summary','measure'=>['title','area','priority'],'next_steps'=>[...],'next_steps_text'=>string].
     */
    public function analyzeOne(int $feedbackId, array $settings): array
    {
        $fb = $this->db->queryOne("SELECT * FROM internal_feedback WHERE id = ?", [$feedbackId]);
        if (!$fb) {
            throw new \RuntimeException('Feedback nicht gefunden');
        }
        [$apiKey, $provider, $model] = $this->pickModel($settings);
        if (!$apiKey) {
            throw new \RuntimeException('Kein API-Key (Anthropic/OpenAI) konfiguriert.');
        }
        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, $provider);
        $ai->setModel($model);

        $systemPrompt = "Du bist Produkt-Managerin fuer ein internes KI-Text-Werkzeug. Du bekommst EIN "
            . "Nutzer-Feedback. Erkenne die dahinterliegende Maßnahme (das To-do) und schlage konkrete, "
            . "umsetzbare naechste Schritte vor.\n"
            . "Stil: Du/Dich gross. Keine Anglizismen wo deutsche Begriffe passen. Keine Gedankenstriche.\n\n"
            . "Antworte AUSSCHLIESSLICH mit JSON in genau dieser Form:\n"
            . "{\n"
            . "  \"summary\": \"1 Satz: worum geht es im Kern\",\n"
            . "  \"measure\": { \"title\": \"praegnanter Maßnahmen-Titel (max 80 Zeichen)\", \"area\": \"Bereich z.B. Chat, Wissen, CRM, Tagesplaner, Allgemein\", \"priority\": \"hoch|mittel|niedrig\" },\n"
            . "  \"next_steps\": [\"konkreter Schritt 1\", \"konkreter Schritt 2\", \"...\"]\n"
            . "}";

        $userPrompt = sprintf(
            "Feedback (Typ: %s, Seite: %s):\n\"%s\"",
            $fb['feedback_type'],
            $fb['page_url'] ?: '-',
            trim(preg_replace('/\s+/', ' ', (string)$fb['description']))
        );

        $response = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $systemPrompt);
        $content  = $response['content'] ?? '';

        $json = null;
        if (preg_match('/\{[\s\S]*\}/m', $content, $mm)) {
            $json = json_decode($mm[0], true);
        }
        if (!$json || empty($json['measure'])) {
            throw new \RuntimeException('KI-Antwort konnte nicht gelesen werden.');
        }

        // next_steps als Text aufbereiten
        $steps = $json['next_steps'] ?? [];
        $stepsText = is_array($steps)
            ? implode("\n", array_map(fn($s) => '- ' . trim((string)$s), $steps))
            : (string)$steps;
        $json['next_steps_text'] = $stepsText;
        $json['model'] = $provider . '/' . $model;

        // Vorschlag speichern; next_steps nur befuellen, wenn der User noch nichts eingetragen hat
        $update = ['ai_suggestion' => json_encode($json, JSON_UNESCAPED_UNICODE)];
        if (trim((string)($fb['next_steps'] ?? '')) === '' && $stepsText !== '') {
            $update['next_steps'] = $stepsText;
        }
        // Titel automatisch setzen, wenn das Feedback noch keinen hat
        if (trim((string)($fb['title'] ?? '')) === '' && !empty($json['measure']['title'])) {
            $update['title'] = mb_substr((string)$json['measure']['title'], 0, 255);
        }
        $this->db->update('internal_feedback', $update, 'id = ?', [$feedbackId]);

        return $json;
    }

    /** Bestes verfuegbares Modell fuer die Analyse waehlen. */
    private function pickModel(array $settings): array
    {
        if (!empty($settings['anthropic_api_key'])) {
            return [$settings['anthropic_api_key'], 'anthropic', 'claude-sonnet-4-5-20250929'];
        }
        if (!empty($settings['openai_api_key'])) {
            return [$settings['openai_api_key'], 'openai', 'gpt-5'];
        }
        return [null, null, null];
    }
}
