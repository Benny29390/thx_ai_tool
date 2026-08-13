<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * Generiert einen KI-Tagesplan: nimmt die Top-N Tasks nach Score + die verfügbare Zeit
 * und liefert eine geordnete Sequenz mit Begründung pro Task.
 *
 * Output:
 *   {
 *     available_minutes: int,
 *     planned_minutes: int,
 *     sequence: [{ task_id, name, customer, due_on, effort, reason }],
 *     skipped: [{ task_id, name, reason_skipped }],
 *     summary: "Kurzer Überblick / Tipp für heute"
 *   }
 */
class PlannerDayPlanService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function generate(int $userId, int $availableMinutes, ?string $focusNote = null, string $slot = 'today'): array
    {
        // Tasks holen aus dem gewünschten Slot — Standard 'today' (vom User in Step 5 dahin gezogen).
        // Fallback: wenn Slot leer, fallen wir auf score-getriebene Auswahl über alle offenen Tasks zurück.
        $today = date('Y-m-d');
        // 'today' = der in den Tagesplan committete Satz (planned_for_date), sonst der Zeitraum-Bucket.
        $slotWhere = ($slot === 'today') ? "pt.planned_for_date = ?" : "pt.daily_slot = ?";
        $slotParam = ($slot === 'today') ? $today : $slot;
        $slotTasks = $this->db->query(
            "SELECT pt.id, pt.name, pt.notes, pt.due_on, pt.effort_minutes, pt.ai_effort_estimate,
                    pt.ai_summary, pt.ai_significance, pt.ai_recommended_when, pt.manual_priority, pt.ai_complexity,
                    pt.score, pt.customer_id, pt.postponed_to, pt.postpone_count, pt.daily_slot,
                    c.name AS customer_name, c.abbreviation AS customer_abbr
             FROM planner_tasks pt
             LEFT JOIN customers c ON c.id = pt.customer_id
             WHERE pt.user_id = ?
               AND pt.completed_at_asana IS NULL
               AND pt.completed_at_local IS NULL
               AND pt.planner_ignored = 0
               AND {$slotWhere}
             ORDER BY pt.score DESC, pt.due_on ASC",
            [$userId, $slotParam]
        ) ?: [];

        // Fallback wenn der Slot leer ist: Top-Score-Tasks aus dem Pool
        $tasks = $slotTasks;
        if (empty($tasks)) {
            $tasks = $this->db->query(
                "SELECT pt.id, pt.name, pt.notes, pt.due_on, pt.effort_minutes, pt.ai_effort_estimate,
                        pt.ai_summary, pt.ai_significance, pt.ai_recommended_when, pt.manual_priority, pt.ai_complexity,
                        pt.score, pt.customer_id, pt.postponed_to, pt.postpone_count, pt.daily_slot,
                        c.name AS customer_name, c.abbreviation AS customer_abbr
                 FROM planner_tasks pt
                 LEFT JOIN customers c ON c.id = pt.customer_id
                 WHERE pt.user_id = ?
                   AND pt.completed_at_asana IS NULL
                   AND pt.completed_at_local IS NULL
                   AND pt.planner_ignored = 0
                   AND (pt.postponed_to IS NULL OR pt.postponed_to <= ?)
                 ORDER BY pt.score DESC, pt.due_on ASC
                 LIMIT 40",
                [$userId, $today]
            ) ?: [];
        }

        if (empty($tasks)) {
            return [
                'available_minutes' => $availableMinutes,
                'planned_minutes'   => 0,
                'sequence'          => [],
                'skipped'           => [],
                'summary'           => 'Aktuell sind keine offenen Tasks im System. Synchronisiere mit Asana oder geniesse den freien Kopf.',
            ];
        }

        // Effective effort pro Task (manuell > KI > 60min Default)
        foreach ($tasks as &$t) {
            $t['effective_effort'] = (int)($t['effort_minutes'] ?? 0) ?: (int)($t['ai_effort_estimate'] ?? 0) ?: 60;
        }
        unset($t);

        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic-API-Key nicht konfiguriert');
        }

        $today = date('Y-m-d');
        $taskBlock = json_encode(array_map(function ($t) use ($today) {
            $overdueDays = 0;
            if (!empty($t['due_on'])) {
                $overdueDays = (int)((strtotime($today) - strtotime($t['due_on'])) / 86400);
                $overdueDays = max(0, $overdueDays);
            }
            return [
                'id'             => (int)$t['id'],
                'name'           => $t['name'],
                'customer'       => $t['customer_name'] ?? '(ohne Kunde)',
                'due_on'         => $t['due_on'],
                'overdue_days'   => $overdueDays,
                'effort_min'     => $t['effective_effort'],
                'score'          => (float)$t['score'],
                'postpone_count' => (int)($t['postpone_count'] ?? 0),
                'ai_summary'     => $t['ai_summary'] ?? '',
                'significance'   => $t['ai_significance'] ? (int)$t['ai_significance'] : null,
                'when_advice'    => $t['ai_recommended_when'] ?? null,
                'complexity'     => $t['ai_complexity'] ?? null,
            ];
        }, $tasks), JSON_UNESCAPED_UNICODE);

        $sys = <<<SYS
Du planst den Arbeitstag eines Marketing-/Agentur-Profis. Eingabe: Liste offener Tasks (mit Aufwand, Deadline, Kunde, Score, KI-Vorab-Analyse) und die heute verfügbare Arbeitszeit in Minuten.

Aufgabe: Schlage eine kluge SEQUENZ vor, die in die verfügbare Zeit passt.

Priorisierungs-Heuristiken — diese sind WICHTIG, blind auf Score zu schauen reicht nicht:

1. KARTEILEICHEN-SKEPSIS: Tasks mit overdue_days > 14 UND postpone_count >= 2 sind verdächtig. Bewerte sie nicht automatisch als dringend — frage Dich, ob sie überhaupt noch relevant sind. Im Zweifel ins `skipped` mit Begründung "wirkt wie Karteileiche, überprüfen ob noch relevant".

2. SIGNIFICANCE schlägt Score: Eine Task mit significance >= 7 und when_advice='asap' oder 'this_week' sollte vor Tasks mit niedrigerer significance kommen, selbst wenn der Score numerisch höher wäre durch Überfälligkeit.

3. VAGE TASKS: Eine Task mit when_advice='when_possible' und niedriger significance gehört nur in den Tagesplan, wenn sonst nichts Besseres ansteht.

4. KOPF-FRISCHE: Tasks mit complexity='high' sollten früh am Tag oder in zusammenhängende Zeitblöcke, nicht zwischen 5 kleinen Quick-Wins.

5. VARIATION: Wenn 8 von 10 Tasks vom gleichen Kunden sind und alle höhen Score haben, ist das vermutlich ein Sammel-Projekt-Ende — variiere trotzdem mit 1-2 anderen Tasks, weil Mono-Tagging ermüdet. Es sei denn, eine Fokus-Notiz sagt explizit das Gegenteil.

Pro Task einen 'reason' (max 15 Worte, inhaltlich: warum genau jetzt diese Task — beziehe Dich auf KI-Summary, significance oder Aufwand).
Pro 'skipped' einen 'reason' (warum heute NICHT — z.B. "wirkt wie Karteileiche, 23 Tage alt + 4x verschoben" oder "low significance, kann warten").

Am Ende ein 'summary' (1-2 Sätze) mit Top-Tipp für heute — kein Marketing-Speak, knochentrocken.

Antworte AUSSCHLIESSLICH als gültiges JSON, ohne Markdown-Fences:
{"sequence":[{"task_id":<int>,"reason":"<text>"}],"skipped":[{"task_id":<int>,"reason":"<text>"}],"summary":"<text>"}
SYS;

        if ($focusNote !== null && trim($focusNote) !== '') {
            $sys .= "\n\nFokus-Notiz des Users für heute: " . trim($focusNote);
        }

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-sonnet-4-6');
        $ai->setMaxTokens(3000);
        $ai->setTimeout(60);

        $resp = $ai->chat(
            [['role' => 'user', 'content' => "Verfügbare Zeit: {$availableMinutes} Minuten.\n\nTasks:\n" . $taskBlock]],
            $sys
        );
        $text = trim($resp['content'] ?? '');
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('KI-Antwort war kein gültiges JSON');
        }

        // Anreichern mit den Task-Details (Name, Aufwand, etc.)
        $byId = [];
        foreach ($tasks as $t) $byId[(int)$t['id']] = $t;

        $hydrate = function (array $items) use ($byId) {
            $out = [];
            foreach ($items as $i) {
                $tid = (int)($i['task_id'] ?? 0);
                if (!isset($byId[$tid])) continue;
                $t = $byId[$tid];
                $out[] = [
                    'task_id'    => $tid,
                    'name'       => $t['name'],
                    'customer'   => $t['customer_name'] ?? null,
                    'customer_abbr' => $t['customer_abbr'] ?? null,
                    'due_on'     => $t['due_on'],
                    'effort_min' => $t['effective_effort'],
                    'reason'     => $i['reason'] ?? '',
                ];
            }
            return $out;
        };

        $sequence = $hydrate($parsed['sequence'] ?? []);
        $skipped = $hydrate($parsed['skipped'] ?? []);
        $planned = array_sum(array_column($sequence, 'effort_min'));

        return [
            'available_minutes' => $availableMinutes,
            'planned_minutes'   => $planned,
            'sequence'          => $sequence,
            'skipped'           => $skipped,
            'summary'           => $parsed['summary'] ?? '',
        ];
    }
}
