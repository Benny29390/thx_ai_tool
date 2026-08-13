<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * Verteilt offene Tasks intelligent auf die Tagesplan-Slots
 * (today / tomorrow / day_after / rest_week / occasion) — Eingabe für die KI:
 *   - Tasks mit Score, KI-Significance, Empfehlung, Aufwand, Postpone-Count, Deadline
 *   - Kunden-Budget-Status (PpBudgetService.getCustomersOverview → over/risk/under/ok)
 *   - Projektplanner-Risiko-Status pro Plan (pp_plans.risiko_modus + risiko_notiz)
 *
 * Outcome: pro Task ein Slot mit knapper Begründung, plus eine Tages-Capacity-Bilanz.
 * Default-Kapazitäten (in Minuten): today=240, tomorrow=240, this_week=1200.
 */
class PlannerSlotPlanService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function planSlots(int $userId, array $capacity = []): array
    {
        $capacity = array_merge([
            'today'     => 240,   // 4h
            'tomorrow'  => 240,
            'day_after' => 240,
            'rest_week' => 1200,  // Rest der Woche
        ], $capacity);

        // Tasks (offen, nicht ignoriert) — alle, denn der User will eine vollständige Vorsortierung
        $tasks = $this->db->query(
            "SELECT pt.id, pt.name, pt.due_on, pt.effort_minutes, pt.ai_effort_estimate,
                    pt.ai_significance, pt.ai_recommended_when, pt.manual_priority,
                    pt.ai_complexity, pt.is_quick_task, pt.score, pt.customer_id, pt.postpone_count,
                    pt.asana_project_gid, c.name AS customer_name, c.abbreviation AS customer_abbr
             FROM planner_tasks pt
             LEFT JOIN customers c ON c.id = pt.customer_id
             WHERE pt.user_id = ?
               AND pt.completed_at_local IS NULL AND pt.completed_at_asana IS NULL
               AND pt.planner_ignored = 0
             ORDER BY pt.score DESC, pt.due_on ASC
             LIMIT 60",
            [$userId]
        ) ?: [];

        if (empty($tasks)) {
            return ['applied' => 0, 'slots' => [], 'reasoning' => [], 'note' => 'Keine offenen Tasks'];
        }

        // Kunden-Status (PpBudgetService): pro Kunde over/risk/under/ok
        $customerStatus = [];
        try {
            require_once SERVICES_PATH . '/PpBudgetService.php';
            $svc = new PpBudgetService($this->db);
            foreach (($svc->getCustomersOverview((int)date('Y')) ?? []) as $c) {
                if (is_array($c) && isset($c['customer_id'])) {
                    $customerStatus[(int)$c['customer_id']] = $c['status'] ?? 'ok';
                }
            }
        } catch (\Throwable $e) { /* leise */ }

        // Projektplanner-Risiko pro Asana-Projekt
        $planRisk = [];
        try {
            $planRows = $this->db->query(
                "SELECT p.asana_project_gid, p.risiko_modus, p.risiko_notiz
                 FROM pp_plans p
                 WHERE p.asana_project_gid IS NOT NULL AND p.asana_project_gid <> ''
                   AND p.state = 1
                   AND (p.risiko_modus = 'eskaliert' OR p.risiko_notiz IS NOT NULL)"
            ) ?: [];
            foreach ($planRows as $r) {
                $planRisk[$r['asana_project_gid']] = [
                    'modus' => $r['risiko_modus'],
                    'note'  => mb_substr(trim((string)$r['risiko_notiz']), 0, 80),
                ];
            }
        } catch (\Throwable $e) { /* PP optional */ }

        // Payload für KI
        $today = date('Y-m-d');
        $payload = array_map(function ($t) use ($today, $customerStatus, $planRisk) {
            $overdueDays = 0;
            if (!empty($t['due_on'])) {
                $overdueDays = (int)((strtotime($today) - strtotime($t['due_on'])) / 86400);
                $overdueDays = max(0, $overdueDays);
            }
            $eff = (int)($t['effort_minutes'] ?? 0) ?: (int)($t['ai_effort_estimate'] ?? 0) ?: 60;
            $custStat = $t['customer_id'] ? ($customerStatus[(int)$t['customer_id']] ?? 'ok') : null;
            $plan = $t['asana_project_gid'] ? ($planRisk[$t['asana_project_gid']] ?? null) : null;
            return [
                'id'              => (int)$t['id'],
                'name'            => $t['name'],
                'customer'        => $t['customer_name'] ?? '(ohne Kunde)',
                'customer_status' => $custStat,
                'plan_risk'       => $plan ? $plan['modus'] : null,
                'plan_risk_note'  => $plan ? $plan['note'] : null,
                'due_on'          => $t['due_on'],
                'overdue_days'    => $overdueDays,
                'effort_min'      => $eff,
                'quick_task'      => (int)($t['is_quick_task'] ?? 0) === 1,
                'score'           => (float)($t['score'] ?? 0),
                'significance'    => $t['ai_significance'] ? (int)$t['ai_significance'] : null,
                'when_advice'     => $t['manual_priority'] ?? $t['ai_recommended_when'] ?? null,
                'complexity'      => $t['ai_complexity'] ?? null,
                'postpone_count'  => (int)$t['postpone_count'],
            ];
        }, $tasks);

        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key nicht konfiguriert');

        $sys = <<<SYS
Du bist der Tagesplaner eines Marketing-/Agentur-Inhabers. Du verteilst seine offenen Asana-Tasks auf diese Slots:
  - today      (Kapazität ~{$capacity['today']} Minuten Arbeit)
  - tomorrow   (Kapazität ~{$capacity['tomorrow']} Minuten)
  - day_after  (Kapazität ~{$capacity['day_after']} Minuten — übermorgen)
  - rest_week  (Kapazität ~{$capacity['rest_week']} Minuten — verteilt auf den Rest der Woche)
  - occasion   (alles weitere — kein Stress, kommt bei Gelegenheit dran)

Eingabe pro Task: id, customer, customer_status (over=brennt/risk=Forecast über Soll/under=zu wenig/ok), plan_risk (Projektplanner-Risiko falls gesetzt), overdue_days, effort_min, significance, when_advice, complexity, postpone_count.

Sortier-Heuristik:
1) BRENNENDE KUNDEN ZUERST: Tasks mit customer_status='over' oder plan_risk='hoch'/'kritisch' gehören in Heute oder Morgen.
2) QUICK-TASKS (quick_task=true) gehören in den Tag, in dem die Hauptarbeit für den Kunden passiert — sie passen oft zu einer halben Stunde gestapelt. Wenn 4-8 Quick-Tasks vorhanden sind, mindestens 3-4 davon ins Heute (auch wenn significance nur mittel ist) — schnelle Wins, blockieren andere.
3) ASAP + significance>=8: ebenfalls Heute.
4) Überfällig + significance>=6: Heute oder Morgen.
5) Karteileichen-Skepsis: postpone_count>=2 UND overdue_days>14 → eher 'occasion'.
6) KAPAZITätEN EINHALTEN: Summe effort_min pro Slot ≤ Slot-Kapazität.
7) HOHE-KOMPLEXITät nicht stapeln: max 1-2 'high'-Komplexität pro Tag.

Antworte AUSSCHLIESSLICH als JSON, kein Markdown:
{
  "assignments": [
    {"id": <int>, "slot": "today"|"tomorrow"|"day_after"|"rest_week"|"occasion", "reason": "<max 15 Worte>"}
  ],
  "summary": "<1-2 Sätze: was zeichnet den heutigen Plan aus, Top-Tipp>"
}
SYS;

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-sonnet-4-6');
        $ai->setMaxTokens(6000);
        $ai->setTimeout(90);

        $resp = $ai->chat(
            [['role' => 'user', 'content' => "Tasks:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE)]],
            $sys
        );
        $text = trim($resp['content'] ?? '');
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['assignments']) || !is_array($parsed['assignments'])) {
            throw new \RuntimeException('KI-Antwort hatte kein gültiges assignments-Array');
        }

        $validIds = array_flip(array_map(fn($t) => (int)$t['id'], $tasks));
        $applied = 0;
        $reasoning = [];
        $slots = ['today' => [], 'tomorrow' => [], 'day_after' => [], 'rest_week' => [], 'occasion' => []];
        $validSlots = ['today','tomorrow','day_after','rest_week','next_week','this_month','later','occasion'];
        foreach ($parsed['assignments'] as $a) {
            $tid = (int)($a['id'] ?? 0);
            $slot = $a['slot'] ?? null;
            if (!isset($validIds[$tid])) continue;
            if (!in_array($slot, $validSlots, true)) continue;
            if (!isset($slots[$slot])) $slots[$slot] = [];
            $reason = mb_substr((string)($a['reason'] ?? ''), 0, 200);
            // Nur der 'today'-Satz wird in den Tagesplan committet (planned_for_date). Frist bleibt unangetastet.
            // tomorrow/day_after/rest_week sind beratend (Vorschau), aendern nichts an der Faelligkeit.
            if ($slot === 'today') {
                $this->db->update('planner_tasks', ['planned_for_date' => date('Y-m-d')], 'id = ?', [$tid]);
                $applied++;
            }
            $reasoning[$tid] = $reason;
            $slots[$slot][] = $tid;
        }
        return [
            'applied'   => $applied,
            'slots'     => $slots,
            'reasoning' => $reasoning,
            'summary'   => $parsed['summary'] ?? '',
            'capacity'  => $capacity,
        ];
    }
}
