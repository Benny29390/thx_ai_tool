<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * Lernschleife des Tagesplaners.
 *
 *   1. PROTOKOLL  — recordCorrection(): jede Stelle, an der der Inhaber die KI ueberstimmt
 *                   (Aufwand, Quick-ja/nein, Wichtigkeit), wird festgehalten.
 *   2. ANALYSE    — deriveRules(): ein LLM destilliert aus den Korrekturen wenige, konkrete,
 *                   verallgemeinerbare Regeln (Kandidaten).
 *   3. AKTIVIEREN — der Inhaber schaltet Kandidaten scharf (setRuleStatus 'active').
 *   4. ANWENDEN   — activeRulesBlock(): aktive Regeln gehen in den Analyse-Prompt kuenftiger Laeufe.
 *
 * Bewusst getrennt von der reinen Pre-Analyse (PlannerEffortAiService) — die ruft hier nur
 * activeRulesBlock() ab.
 */
class PlannerLearningService
{
    private Database $db;

    /** Lesbare Namen fuer die Wichtigkeits-Stufen (sowohl KI- als auch User-Seite). */
    private const WHEN_LABEL = [
        'asap'          => 'sofort',
        'this_week'     => 'diese Woche',
        'when_possible' => 'wenn möglich',
    ];

    /** Lesbare Namen fuer die 8 Zeitraum-Buckets. */
    private const SLOT_LABEL = [
        'today'      => 'Heute',
        'tomorrow'   => 'Morgen',
        'day_after'  => 'Übermorgen',
        'rest_week'  => 'Rest der Woche',
        'next_week'  => 'Nächste Woche',
        'this_month' => 'Noch diesen Monat',
        'later'      => 'Später',
        'occasion'   => 'Bei Gelegenheit',
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ============================ 1. Protokoll ============================

    /**
     * Vergleicht die vom User gesetzten Felder mit der KI-Vorhersage und protokolliert Abweichungen.
     * $before = Task-Zeile VOR dem Update (mit ai_effort_estimate, ai_is_quick, ai_recommended_when, name, …).
     * $updates = die validierten Feld-Updates aus dem set-field-Endpunkt.
     * Liefert die Anzahl protokollierter Korrekturen.
     */
    public function recordCorrection(int $userId, int $taskId, array $before, array $updates): int
    {
        $name    = mb_substr((string)($before['name'] ?? ''), 0, 500);
        $project = isset($before['asana_project_name']) ? mb_substr((string)$before['asana_project_name'], 0, 255) : null;
        $notes   = isset($before['notes']) ? mb_substr(trim((string)$before['notes']), 0, 300) : null;
        $logged  = 0;

        // --- Aufwand ---
        if (array_key_exists('effort_minutes', $updates) && $updates['effort_minutes'] !== null) {
            $ai   = (int)($before['ai_effort_estimate'] ?? 0);
            $user = (int)$updates['effort_minutes'];
            if ($ai > 0 && $user > 0 && abs($ai - $user) >= 15) {
                $this->store($userId, $taskId, $name, $project, $notes, 'effort', (string)$ai, (string)$user);
                $logged++;
            }
        }

        // --- Quick-ja/nein --- (nur wenn die KI ueberhaupt eine Vorhersage hatte)
        if (array_key_exists('is_quick_task', $updates) && isset($before['ai_is_quick']) && $before['ai_is_quick'] !== null) {
            $ai   = (int)$before['ai_is_quick'];
            $user = !empty($updates['is_quick_task']) ? 1 : 0;
            if ($ai !== $user) {
                $this->store($userId, $taskId, $name, $project, $notes, 'quick', $ai ? 'ja' : 'nein', $user ? 'ja' : 'nein');
                $logged++;
            }
        }

        // --- Wichtigkeit / wann --- (User-Prio ueberstimmt KI-Empfehlung)
        if (array_key_exists('manual_priority', $updates) && $updates['manual_priority'] !== null && $updates['manual_priority'] !== '') {
            $ai   = (string)($before['ai_recommended_when'] ?? '');
            $user = (string)$updates['manual_priority'];
            if ($ai !== '' && $ai !== $user) {
                $this->store(
                    $userId, $taskId, $name, $project, $notes, 'priority',
                    self::WHEN_LABEL[$ai] ?? $ai,
                    self::WHEN_LABEL[$user] ?? $user
                );
                $logged++;
            }
        }

        // --- Slot --- (User schiebt die Task in einen anderen Tagesplan-Slot)
        if (array_key_exists('daily_slot', $updates) && $updates['daily_slot'] !== null) {
            $ai   = (string)($before['daily_slot'] ?? '');
            $user = (string)$updates['daily_slot'];
            if ($ai !== '' && $ai !== $user) {
                $this->store(
                    $userId, $taskId, $name, $project, $notes, 'slot',
                    self::SLOT_LABEL[$ai] ?? $ai,
                    self::SLOT_LABEL[$user] ?? $user
                );
                $logged++;
            }
        }

        // --- Kunde --- (User korrigiert die Kunden-Zuordnung)
        if (array_key_exists('customer_id', $updates)) {
            $oldId = $before['customer_id'] ?? null;
            $newId = $updates['customer_id'];
            if ((string)$oldId !== (string)$newId) {
                $oldName = $before['customer_name'] ?? ($oldId ? "Kunde #{$oldId}" : 'kein Kunde');
                $newName = $newId ? ($this->db->queryValue("SELECT name FROM customers WHERE id = ?", [(int)$newId]) ?: "Kunde #{$newId}") : 'kein Kunde';
                $this->store($userId, $taskId, $name, $project, $notes, 'customer', (string)$oldName, (string)$newName);
                $logged++;
            }
        }

        return $logged;
    }

    private function store(int $userId, int $taskId, string $name, ?string $project, ?string $notes, string $field, string $aiVal, string $userVal): void
    {
        // Upsert: die jeweils LETZTE Korrektur pro Task+Feld zaehlt (kein Hin-und-Her-Rauschen).
        $this->db->execute(
            "INSERT INTO planner_ai_corrections
                (user_id, task_id, task_name, task_project, task_notes_snippet, field, ai_value, user_value)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                task_name = VALUES(task_name), task_project = VALUES(task_project),
                task_notes_snippet = VALUES(task_notes_snippet),
                ai_value = VALUES(ai_value), user_value = VALUES(user_value)",
            [$userId, $taskId, $name, $project, $notes, $field, $aiVal, $userVal]
        );
    }

    /** Wieviele Korrekturen sind protokolliert (fuer "X Korrekturen gesammelt"-Hinweis)? */
    public function correctionCount(int $userId): int
    {
        return (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM planner_ai_corrections WHERE user_id = ?",
            [$userId]
        );
    }

    // ============================ 2. Analyse → Regelkandidaten ============================

    /**
     * Destilliert aus den protokollierten Korrekturen Regelkandidaten via LLM.
     * Legt neue Kandidaten an (Dedup gegen bestehende, nicht verworfene Regeln).
     */
    public function deriveRules(int $userId, int $maxCorrections = 60): array
    {
        $rows = $this->db->query(
            "SELECT field, task_name, task_project, task_notes_snippet, ai_value, user_value
             FROM planner_ai_corrections
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$userId, $maxCorrections]
        ) ?: [];

        if (count($rows) < 3) {
            return ['ok' => true, 'candidates' => 0, 'message' => 'Noch zu wenige Korrekturen — gehe ein paar Tasks durch, dann erkenne ich Muster.'];
        }

        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            return ['ok' => false, 'candidates' => 0, 'message' => 'Anthropic-API-Key nicht konfiguriert'];
        }

        $fieldLabel = ['effort' => 'Aufwand', 'quick' => 'Quick-Task', 'priority' => 'Wichtigkeit', 'slot' => 'Einplanung', 'customer' => 'Kunde'];
        $lines = [];
        foreach ($rows as $r) {
            $ctx = $r['task_name'];
            if (!empty($r['task_project'])) $ctx .= " [Projekt: {$r['task_project']}]";
            $lines[] = sprintf(
                '- (%s) »%s«: KI sagte "%s", Inhaber korrigierte auf "%s"',
                $fieldLabel[$r['field']] ?? $r['field'], $ctx, $r['ai_value'], $r['user_value']
            );
        }
        $corrBlock = implode("\n", $lines);

        // Bereits bekannte Regeln mitgeben, damit das LLM keine Dubletten erzeugt.
        $existing = $this->db->query(
            "SELECT rule_text FROM planner_learned_rules WHERE user_id = ? AND status != 'dismissed'",
            [$userId]
        ) ?: [];
        $existingBlock = $existing ? implode("\n", array_map(fn($e) => '- ' . $e['rule_text'], $existing)) : '(noch keine)';

        $system = <<<SYS
Du destillierst aus konkreten Korrekturen eines Agentur-Inhabers verallgemeinerbare Regeln fuer eine
Aufgaben-KI. Der Inhaber hat die KI-Einschaetzung (Aufwand, ob Quick-Task, Wichtigkeit) ueberstimmt.

Finde die WIEDERKEHRENDEN MUSTER und formuliere sie als knappe, konkrete Regeln, die die KI bei
KUENFTIGEN, AEHNLICHEN Aufgaben anwenden kann. Eine Regel nur, wenn sie durch mehrere Korrekturen
gestuetzt wird oder ein klares, benennbares Muster zeigt (z.B. ein Titel-Format, ein Projekttyp, ein Kunde).

Regeln:
- Maximal 6.
- Jede Regel: konkret und handlungsleitend, kein Geschwurbel. Auf Deutsch, echte Umlaute.
- pattern_hint: woran die KI erkennt, dass die Regel greift (Titel-Format / Projektname / Schluesselwort).
- field: "effort" | "quick" | "priority" | "slot" | "customer" | "general".
- support_count: wie viele der Korrekturen diese Regel stuetzen (ganze Zahl).
- KEINE Regel, die schon unter "Bereits bekannt" steht.

Antworte AUSSCHLIESSLICH als JSON-Array:
[{"field":"quick","rule_text":"...","pattern_hint":"...","support_count":3}]
Wenn kein belastbares Muster erkennbar ist, antworte mit [].
SYS;

        $user = "Bereits bekannte Regeln:\n{$existingBlock}\n\nKorrekturen des Inhabers:\n{$corrBlock}";

        require_once SERVICES_PATH . '/AIService.php';
        try {
            $ai = new AIService($apiKey, 'anthropic');
            $ai->setModel('claude-haiku-4-5-20251001');
            $ai->setMaxTokens(2000);
            $ai->setTimeout(90);
            $resp = $ai->chat([['role' => 'user', 'content' => $user]], $system);
            $raw = trim($resp['content'] ?? '');
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw);
            $parsed = json_decode($clean, true);
        } catch (\Throwable $e) {
            return ['ok' => false, 'candidates' => 0, 'message' => 'KI-Analyse fehlgeschlagen: ' . $e->getMessage()];
        }
        if (!is_array($parsed)) $parsed = [];

        // Bestehende Texte fuer Dedup (case-insensitiv, grob).
        $known = [];
        foreach ($existing as $e) $known[$this->norm($e['rule_text'])] = true;

        $created = 0;
        $allowedFields = ['effort', 'quick', 'priority', 'slot', 'customer', 'general'];
        foreach ($parsed as $p) {
            $text = trim((string)($p['rule_text'] ?? ''));
            if ($text === '') continue;
            if (isset($known[$this->norm($text)])) continue;
            $field = in_array($p['field'] ?? '', $allowedFields, true) ? $p['field'] : 'general';
            $hint = isset($p['pattern_hint']) ? mb_substr((string)$p['pattern_hint'], 0, 200) : null;
            $support = max(1, (int)($p['support_count'] ?? 1));
            $this->db->insert('planner_learned_rules', [
                'user_id'       => $userId,
                'field'         => $field,
                'rule_text'     => mb_substr($text, 0, 400),
                'pattern_hint'  => $hint,
                'support_count' => $support,
                'status'        => 'candidate',
            ]);
            $known[$this->norm($text)] = true;
            $created++;
        }

        return [
            'ok' => true,
            'candidates' => $created,
            'message' => $created > 0
                ? "{$created} neue Regel(n) vorgeschlagen, bitte prüfen und freischalten."
                : 'Keine neuen Muster gefunden (oder schon alle als Regel erfasst).',
        ];
    }

    private function norm(string $s): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
    }

    // ============================ 3. Regeln verwalten ============================

    public function listRules(int $userId): array
    {
        $rows = $this->db->query(
            "SELECT id, field, rule_text, pattern_hint, support_count, status, created_at, activated_at
             FROM planner_learned_rules
             WHERE user_id = ? AND status != 'dismissed'
             ORDER BY (status='active') DESC, created_at DESC",
            [$userId]
        ) ?: [];
        return array_map(fn($r) => [
            'id'            => (int)$r['id'],
            'field'         => $r['field'],
            'rule_text'     => $r['rule_text'],
            'pattern_hint'  => $r['pattern_hint'],
            'support_count' => (int)$r['support_count'],
            'status'        => $r['status'],
        ], $rows);
    }

    public function setRuleStatus(int $userId, int $ruleId, string $status): bool
    {
        if (!in_array($status, ['candidate', 'active', 'dismissed'], true)) return false;
        $rule = $this->db->queryOne(
            "SELECT id FROM planner_learned_rules WHERE id = ? AND user_id = ?",
            [$ruleId, $userId]
        );
        if (!$rule) return false;
        $this->db->execute(
            "UPDATE planner_learned_rules
             SET status = ?, activated_at = CASE WHEN ? = 'active' THEN NOW() ELSE activated_at END
             WHERE id = ? AND user_id = ?",
            [$status, $status, $ruleId, $userId]
        );
        return true;
    }

    // ============================ 4. Anwenden ============================

    /** Aktive Regeln als Prompt-Block fuer den Analyse-Lauf. Leerer String, wenn keine aktiv sind. */
    public function activeRulesBlock(int $userId): string
    {
        $rows = $this->db->query(
            "SELECT field, rule_text, pattern_hint FROM planner_learned_rules
             WHERE user_id = ? AND status = 'active'
             ORDER BY field, id",
            [$userId]
        ) ?: [];
        if (empty($rows)) return '';

        $label = ['effort' => 'Aufwand', 'quick' => 'Quick', 'priority' => 'Wichtigkeit', 'slot' => 'Einplanung', 'customer' => 'Kunde', 'general' => 'Allgemein'];
        $lines = [];
        foreach ($rows as $r) {
            $hint = !empty($r['pattern_hint']) ? " (greift bei: {$r['pattern_hint']})" : '';
            $lines[] = '- [' . ($label[$r['field']] ?? $r['field']) . '] ' . $r['rule_text'] . $hint;
        }
        return "GELERNTE REGELN DES INHABERS — er hat sie aus frueheren KI-Fehlern bestaetigt und scharf "
            . "geschaltet. BEFOLGE sie strikt, sie schlagen die allgemeinen Faustregeln oben:\n"
            . implode("\n", $lines);
    }
}
