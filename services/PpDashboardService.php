<?php
namespace Services;

use Core\Database;

/**
 * PpDashboardService — Aggregierte Statistiken für das Projektplanner-Dashboard.
 *
 * Stunden-Attribution pro Zeile:
 *   - lead_responsible bekommt die VOLLEN Stunden
 *   - Wenn KEIN Lead: gleichmäßig auf responsible-Liste verteilt
 *   - Wenn KEIN Lead UND KEIN responsible: gar nichts attribuiert
 *   - no_ticket=1 Zeilen werden komplett ignoriert
 */
class PpDashboardService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * @param array $filter ['date_from'?, 'date_to'?, 'status'?, 'customer_id'?]
     */
    /**
     * Liefert alle offenen Item-Zeilen für eine Person (per Name, Kürzel oder Spitzname).
     * Person wird über alle drei Identifikatoren in users + pp_team_members aufgelöst.
     */
    public function getOpenTasksFor(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') return [];
        // Personen-Namen aus den Identifikatoren ableiten
        $names = [];
        $person = $this->db->queryOne(
            "SELECT u.name, u.abbreviation, u.nickname, t.name AS team_name, t.abbreviation AS team_abbr
             FROM users u LEFT JOIN pp_team_members t ON t.user_id = u.id
             WHERE LOWER(u.name) = LOWER(?) OR LOWER(u.abbreviation) = LOWER(?) OR LOWER(u.nickname) = LOWER(?)
                OR LOWER(t.name) = LOWER(?) OR LOWER(t.abbreviation) = LOWER(?)
             LIMIT 1",
            [$identifier, $identifier, $identifier, $identifier, $identifier]
        );
        if ($person) {
            foreach (['name', 'abbreviation', 'nickname', 'team_name', 'team_abbr'] as $k) {
                $v = trim((string)($person[$k] ?? ''));
                if ($v !== '') $names[$v] = true;
            }
        } else {
            $names[$identifier] = true;
        }

        $namesList = array_keys($names);
        $placeholders = implode(',', array_fill(0, count($namesList), '?'));
        $likeConds = array_fill(0, count($namesList), "r.responsible LIKE ?");

        $sql = "SELECT r.id AS row_id, r.description, r.planned_hours, r.ist_hours, r.deadline,
                       r.lead_responsible, r.responsible,
                       p.id AS plan_id, p.title AS plan_title, p.customer_id,
                       c.name AS customer_name, c.hex_color AS customer_color
                FROM pp_plan_rows r
                JOIN pp_plans p ON p.id = r.plan_id
                LEFT JOIN customers c ON c.id = p.customer_id
                WHERE r.row_type = 'item' AND r.no_ticket = 0 AND r.is_done = 0
                  AND p.state = 1 AND p.plan_status IN ('aktiv','einzelprojekt','reporting')
                  AND (
                        LOWER(r.lead_responsible) IN (" . $placeholders . ")
                     OR (" . implode(' OR ', $likeConds) . ")
                  )
                ORDER BY p.title, COALESCE(r.deadline, '9999-12-31'), r.position";

        // Genau 2N Parameter: N für IN (lowercase) + N für LIKE
        $leadParams = array_map('mb_strtolower', $namesList);
        $likeParams = array_map(fn($n) => '%' . $n . '%', $namesList);
        $rows = $this->db->query($sql, array_merge($leadParams, $likeParams)) ?: [];

        // is_lead / is_responsible je Zeile markieren
        $namesLower = array_map('mb_strtolower', $namesList);
        $out = [];
        foreach ($rows as $r) {
            $leadL = mb_strtolower((string)$r['lead_responsible']);
            $respList = array_map('trim', explode(',', (string)$r['responsible']));
            $respListLower = array_map('mb_strtolower', $respList);
            $isLead = in_array($leadL, $namesLower, true);
            $isResp = count(array_intersect($respListLower, $namesLower)) > 0;
            if (!$isLead && !$isResp) continue;  // safety
            $out[] = [
                'row_id'         => (int)$r['row_id'],
                'plan_id'        => (int)$r['plan_id'],
                'plan_title'     => $r['plan_title'],
                'customer_name'  => $r['customer_name'],
                'customer_color' => $r['customer_color'] ?: '#94a3b8',
                'description'    => $r['description'],
                'planned_hours'  => (float)$r['planned_hours'],
                'ist_hours'      => (float)$r['ist_hours'],
                'deadline'       => $r['deadline'],
                'is_lead'        => $isLead,
                'is_responsible' => $isResp,
            ];
        }
        return $out;
    }

    public function getStats(array $filter = []): array
    {
        $plans = $this->loadPlans($filter);
        if (empty($plans)) {
            return $this->emptyStats();
        }
        $rows = $this->loadRowsForPlans(array_column($plans, 'id'));

        // Aggregate
        $totals = ['soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0, 'total' => 0];
        $byPerson = [];       // name => {soll, ist, done, open}
        $byPlan = [];          // plan_id => {title, color, soll, ist, done, open, total}
        $forecast = [];        // YYYY-MM => name => {soll, ist}
        $doneTasks = [];
        $personTasks = [];     // name => [{description, soll, ist, role, plan_title, is_done}]
        $capacity = [];        // name => hours/month

        // Plan-Map für Lookup
        $planById = [];
        foreach ($plans as $p) {
            $planById[$p['id']] = $p;
            $byPlan[$p['id']] = [
                'title' => $p['title'],
                'color' => $p['customer_color'] ?: '#94a3b8',
                'customer_name' => $p['customer_name'],
                'soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0, 'total' => 0,
            ];
        }

        foreach ($rows as $r) {
            if ($r['row_type'] !== 'item') continue;
            if ((int) $r['no_ticket']) continue;

            $sollH = (float) $r['planned_hours'];
            $istH = (float) $r['ist_hours'];
            $isDone = (int) $r['is_done'];
            $planId = (int) $r['plan_id'];
            $plan = $planById[$planId] ?? null;
            if (!$plan) continue;

            // Attribution
            $attributions = $this->attributeHours($r, $sollH, $istH);

            // Totals
            $totals['soll'] += $sollH;
            $totals['ist'] += $istH;
            $totals['total']++;
            if ($isDone) $totals['done']++;
            else $totals['open']++;

            // by_plan
            $byPlan[$planId]['soll'] += $sollH;
            $byPlan[$planId]['ist'] += $istH;
            $byPlan[$planId]['total']++;
            if ($isDone) $byPlan[$planId]['done']++;
            else $byPlan[$planId]['open']++;

            // by_person + person_tasks + done_tasks
            foreach ($attributions as $person => $share) {
                $personSoll = $sollH * $share;
                $personIst = $istH * $share;
                if (!isset($byPerson[$person])) {
                    $byPerson[$person] = ['soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0];
                }
                $byPerson[$person]['soll'] += $personSoll;
                $byPerson[$person]['ist'] += $personIst;
                if ($isDone) $byPerson[$person]['done']++;
                else $byPerson[$person]['open']++;

                if (!isset($personTasks[$person])) $personTasks[$person] = [];
                $personTasks[$person][] = [
                    'description' => $r['description'],
                    'soll' => round($personSoll, 1),
                    'ist' => round($personIst, 1),
                    'role' => mb_strtolower((string) $r['lead_responsible']) === mb_strtolower($person) ? 'lead' : 'resp',
                    'plan_title' => $plan['title'],
                    'plan_color' => $plan['customer_color'] ?: '#94a3b8',
                    'is_done' => $isDone,
                    'deadline' => $r['deadline'] ?? '',
                ];

                // Forecast: verteile auf Monate des Plans
                $months = $this->planMonths($plan);
                if (!empty($months)) {
                    $perMonth = $personSoll / count($months);
                    $perMonthIst = $personIst / count($months);
                    foreach ($months as $ym) {
                        if (!isset($forecast[$ym])) $forecast[$ym] = [];
                        if (!isset($forecast[$ym][$person])) $forecast[$ym][$person] = ['soll' => 0, 'ist' => 0];
                        $forecast[$ym][$person]['soll'] += $perMonth;
                        $forecast[$ym][$person]['ist'] += $perMonthIst;
                    }
                }
            }

            if ($isDone) {
                $doneTasks[] = [
                    'description' => $r['description'],
                    'responsible' => $r['lead_responsible'] ?: $r['responsible'],
                    'soll' => $sollH,
                    'ist' => $istH,
                    'plan' => $plan['title'],
                    'color' => $plan['customer_color'] ?: '#94a3b8',
                    'deadline' => $r['deadline'],
                ];
            }
        }

        // Capacity aus pp_team_members
        $teamRows = $this->db->query("SELECT name, capacity_hours FROM pp_team_members WHERE is_active = 1") ?: [];
        foreach ($teamRows as $t) {
            $capacity[$t['name']] = (int) $t['capacity_hours'];
        }

        // Sortiere Forecast
        ksort($forecast);
        return [
            'totals' => [
                'soll' => round($totals['soll'], 1),
                'ist' => round($totals['ist'], 1),
                'done' => $totals['done'],
                'open' => $totals['open'],
                'total' => $totals['total'],
            ],
            'by_person' => array_map(fn($v) => [
                'soll' => round($v['soll'], 1),
                'ist' => round($v['ist'], 1),
                'done' => $v['done'],
                'open' => $v['open'],
            ], $byPerson),
            'by_plan' => array_map(fn($v) => [
                'title' => $v['title'],
                'color' => $v['color'],
                'customer_name' => $v['customer_name'],
                'soll' => round($v['soll'], 1),
                'ist' => round($v['ist'], 1),
                'done' => $v['done'],
                'open' => $v['open'],
                'total' => $v['total'],
            ], $byPlan),
            'forecast' => array_map(function($personMap) {
                return array_map(fn($v) => ['soll' => round($v['soll'], 1), 'ist' => round($v['ist'], 1)], $personMap);
            }, $forecast),
            'done_tasks' => $doneTasks,
            'person_tasks' => $personTasks,
            'capacity' => $capacity,
        ];
    }

    /**
     * Teilt die Stunden einer Zeile auf Personen auf.
     * Returns: ['Max' => 1.0, ...] (Anteil je Person, summe = 1.0 falls Personen vorhanden)
     */
    private function attributeHours(array $r, float $sollH, float $istH): array
    {
        $lead = trim((string) $r['lead_responsible']);
        if ($lead !== '') {
            return [$lead => 1.0];
        }
        $names = array_filter(array_map('trim', explode(',', (string) $r['responsible'])));
        if (empty($names)) return [];
        $share = 1.0 / count($names);
        $out = [];
        foreach ($names as $n) $out[$n] = $share;
        return $out;
    }

    private function planMonths(array $plan): array
    {
        if (empty($plan['period_from']) || empty($plan['period_to'])) return [];
        $from = new \DateTime($plan['period_from']);
        $to = new \DateTime($plan['period_to']);
        $months = [];
        $cursor = clone $from;
        $cursor->modify('first day of this month');
        while ($cursor <= $to) {
            $months[] = $cursor->format('Y-m');
            $cursor->modify('+1 month');
        }
        return $months;
    }

    private function loadPlans(array $filter): array
    {
        $sql = "SELECT p.id, p.title, p.period_from, p.period_to, p.plan_status,
                       p.customer_id, c.name AS customer_name, c.hex_color AS customer_color
                FROM pp_plans p
                LEFT JOIN customers c ON c.id = p.customer_id
                WHERE p.state = 1";
        $params = [];
        if (!empty($filter['status'])) { $sql .= " AND p.plan_status = ?"; $params[] = $filter['status']; }
        if (!empty($filter['customer_id'])) { $sql .= " AND p.customer_id = ?"; $params[] = (int) $filter['customer_id']; }
        if (!empty($filter['date_from'])) {
            $sql .= " AND (p.period_to IS NULL OR p.period_to >= ?)";
            $params[] = $filter['date_from'];
        }
        if (!empty($filter['date_to'])) {
            $sql .= " AND (p.period_from IS NULL OR p.period_from <= ?)";
            $params[] = $filter['date_to'];
        }
        return $this->db->query($sql, $params) ?: [];
    }

    private function loadRowsForPlans(array $planIds): array
    {
        if (empty($planIds)) return [];
        $in = implode(',', array_map('intval', $planIds));
        return $this->db->query(
            "SELECT id, plan_id, row_type, description, planned_hours, ist_hours,
                    responsible, lead_responsible, deadline, is_done, is_placeholder, no_ticket
             FROM pp_plan_rows
             WHERE plan_id IN ($in)
             ORDER BY plan_id, position ASC"
        ) ?: [];
    }

    private function emptyStats(): array
    {
        return [
            'totals' => ['soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0, 'total' => 0],
            'by_person' => [],
            'by_plan' => [],
            'forecast' => [],
            'done_tasks' => [],
            'person_tasks' => [],
            'capacity' => [],
        ];
    }
}
