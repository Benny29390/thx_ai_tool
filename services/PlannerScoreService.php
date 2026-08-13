<?php
namespace Services;

use Core\Database;

/**
 * Score = Dringlichkeit * Bedeutsamkeit * Kunden-Gewicht * Stale-Penalty / max(Aufwand_h, 0.25)
 *
 * Komponenten:
 *  Dringlichkeit (aus Deadline):
 *    - fällig heute:    8
 *    - fällig morgen:   6
 *    - fällig diese Wo: 4
 *    - fällig nächste: 2.5
 *    - später:          1.5
 *    - keine Deadline:   1
 *    - überfällig:     8 + min(diff_days*0.3, 6)  // gedämpfter Wachstum statt 0.5
 *
 *  Bedeutsamkeit:
 *    - aus ai_significance (1..10), wenn vorhanden: linear /5.5 zentriert (also 1->0.18, 5->0.91, 10->1.82)
 *    - ai_recommended_when als Multiplikator:
 *        asap          1.4
 *        this_week     1.0
 *        when_possible 0.7
 *    - kein KI-Wert:    1.0 (neutral)
 *
 *  Kunden-Gewicht (PpBudget-Status):
 *    over 3.0, risk 2.0, under 1.5, ok 1.0, no-budget 0.8, kein Kunde 0.7
 *
 *  Stale-Penalty (postpone_count):
 *    0 verschoben:  1.0
 *    1 verschoben:  0.85
 *    2 verschoben:  0.65
 *    3+ verschoben: 0.40
 *
 *  Aufwand: minutes (effort_minutes oder ai_effort_estimate oder 60). Quick-Win-Bias bleibt.
 */
class PlannerScoreService
{
    private Database $db;
    private ?array $customerStatusCache = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function scoreAll(int $userId): int
    {
        $tasks = $this->db->query(
            "SELECT id, due_on, customer_id, effort_minutes, ai_effort_estimate,
                    ai_significance, ai_recommended_when, manual_priority, postpone_count, planner_ignored
             FROM planner_tasks
             WHERE user_id = ? AND completed_at_local IS NULL AND completed_at_asana IS NULL",
            [$userId]
        ) ?: [];
        $updated = 0;
        foreach ($tasks as $t) {
            $score = $this->scoreTask($t);
            $this->db->update('planner_tasks', ['score' => $score], 'id = ?', [(int)$t['id']]);
            $updated++;
        }
        return $updated;
    }

    public function scoreTask(array $task): float
    {
        if (!empty($task['planner_ignored'])) return 0.0;

        $urgency = $this->urgencyFromDue($task['due_on'] ?? null);
        $significance = $this->significance($task);
        $weight = $this->customerWeight((int)($task['customer_id'] ?? 0) ?: null);
        $stale = $this->stalePenalty((int)($task['postpone_count'] ?? 0));

        $eff = (int)($task['effort_minutes'] ?? 0);
        if ($eff <= 0) $eff = (int)($task['ai_effort_estimate'] ?? 0);
        if ($eff <= 0) $eff = 60;
        $eff = max(15, $eff);

        return round(($urgency * $significance * $weight * $stale) / ($eff / 60.0), 3);
    }

    private function urgencyFromDue(?string $dueOn): float
    {
        if (!$dueOn) return 1.0;
        try {
            $due = new \DateTime($dueOn);
            $due->setTime(0, 0, 0);
        } catch (\Throwable $e) {
            return 1.0;
        }
        $today = new \DateTime('today');
        $diff = (int)$today->diff($due)->format('%r%a'); // negativ = überfällig
        if ($diff < 0)  return 8.0 + min(abs($diff) * 0.3, 6.0); // gedämpfter Wachstum
        if ($diff === 0) return 8.0;
        if ($diff === 1) return 6.0;
        if ($diff <= 7)  return 4.0;
        if ($diff <= 14) return 2.5;
        return 1.5;
    }

    private function significance(array $task): float
    {
        $sig = $task['ai_significance'] ?? null;
        if ($sig === null) return 1.0; // neutral
        $base = max(1, min(10, (int)$sig)) / 5.5; // 1->0.18 .. 10->1.82

        // User-Override schlägt KI-Empfehlung
        $when = $task['manual_priority'] ?? null;
        if ($when === null) $when = $task['ai_recommended_when'] ?? null;
        $whenMult = match ($when) {
            'asap'          => 1.4,
            'this_week'     => 1.0,
            'when_possible' => 0.7,
            default         => 1.0,
        };
        return $base * $whenMult;
    }

    private function stalePenalty(int $postponeCount): float
    {
        if ($postponeCount <= 0) return 1.0;
        if ($postponeCount === 1) return 0.85;
        if ($postponeCount === 2) return 0.65;
        return 0.40;
    }

    private function customerWeight(?int $customerId): float
    {
        if (!$customerId) return 0.7;
        $status = $this->customerStatus($customerId);
        return match ($status) {
            'over'      => 3.0,
            'risk'      => 2.0,
            'under'     => 1.5,
            'ok'        => 1.0,
            'no-budget' => 0.8,
            default     => 1.0,
        };
    }

    private function customerStatus(int $customerId): string
    {
        if ($this->customerStatusCache === null) {
            $this->customerStatusCache = [];
            try {
                require_once __DIR__ . '/PpBudgetService.php';
                $svc = new PpBudgetService($this->db);
                $list = $svc->getCustomersOverview((int)date('Y')) ?? [];
                foreach ($list as $c) {
                    if (!is_array($c) || !isset($c['customer_id'])) continue;
                    $this->customerStatusCache[(int)$c['customer_id']] = $c['status'] ?? 'ok';
                }
            } catch (\Throwable $e) {
                error_log('PlannerScore: customerOverview-Fail: ' . $e->getMessage());
            }
        }
        return $this->customerStatusCache[$customerId] ?? 'ok';
    }
}
