<?php
namespace Services;

use Core\Database;

/**
 * Tagesplaner-Gamification (Baustein A-D).
 *
 *  A — Tages-Score: pro abgehakter Task Punkte, gestaffelt nach Wert.
 *        Quick 1 · Normal 5 · Heute-Hero (#1-Karte) 10 · Ueberfaellig 15 · Bonus "alle Heute fertig" +20.
 *        Genau EIN Score-Event pro Task (Dedup ueber user_id+task_id), Bonus 1x pro Tag.
 *  B — Streaks: aufeinanderfolgende Tage mit Aktivitaet bzw. mit "alle Heute-Tasks erledigt".
 *        Quelle: planner_daily_stats (taeglicher Roll-up).
 *  C — Achievements: feiernde Badges, pro Tag je Schluessel einmal verdienbar.
 *        sprint · karteileiche · quickwins_hunter · punktlandung · delegation_master.
 *        (ki_versteher fehlt bewusst — braucht KI-Annahme-Tracking, das es noch nicht gibt.)
 *  D — Wochenrueckblick: 7-Tage-Aggregat (Tasks, Aufwand, Top-Kunde, beste Stunde, Punkte).
 *
 * Tonalitaet: Inhaber, kein Praktikant. Mittlere Sichtbarkeit, kein Konfetti-Overkill.
 */
class PlannerGamificationService
{
    private Database $db;

    /** Punkte pro Event-Typ. */
    private const POINTS = [
        'quick'            => 1,
        'normal'          => 5,
        'today_hero'      => 10,
        'overdue'         => 15,
        'all_today_bonus' => 20,
        'toad'            => 25,  // Kröte des Tages — die bewusst angegangene schlimmste Aufgabe
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ============================ Hooks ============================

    /**
     * Wird gerufen, wenn eine Task lokal abgehakt wird. Vergibt Punkte (genau einmal je Task),
     * den Tages-Bonus und faellige Achievements. Liefert ein Ergebnis fuer Toast + Header.
     */
    public function onTaskCompleted(int $userId, int $taskId): array
    {
        $today = date('Y-m-d');
        $t = $this->db->queryOne(
            "SELECT id, name, due_on, daily_slot, planned_for_date, is_quick_task, postpone_count, customer_id,
                    effort_minutes, ai_effort_estimate, score, is_toad
             FROM planner_tasks WHERE id = ? AND user_id = ?",
            [$taskId, $userId]
        );
        if (!$t) return ['ok' => false];

        $result = ['ok' => true, 'gained' => 0, 'event' => null, 'bonus' => 0, 'new_achievements' => []];

        // 1) Score-Event — genau einmal pro Task (egal welcher Typ schon existiert).
        $already = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_score_events WHERE user_id = ? AND task_id = ?",
            [$userId, $taskId]
        );
        if ($already === 0) {
            [$type, $points] = $this->classify($userId, $t);
            $this->db->insert('planner_score_events', [
                'user_id'    => $userId,
                'task_id'    => $taskId,
                'event_type' => $type,
                'points'     => $points,
                'customer_id'=> $t['customer_id'] ?: null,
                'event_date' => $today,
            ]);
            $result['gained'] = $points;
            $result['event']  = $type;
        }

        // 2) Tagesstatistik neu berechnen (vor Bonus, damit all_today_done aktuell ist).
        $stats = $this->recomputeDay($userId, $today);

        // 3) Bonus: alle Heute-Tasks erledigt → einmal pro Tag +20.
        if (!empty($stats['all_today_done'])) {
            $hasBonus = (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM planner_score_events
                 WHERE user_id = ? AND event_type = 'all_today_bonus' AND event_date = ?",
                [$userId, $today]
            );
            if ($hasBonus === 0) {
                $this->db->insert('planner_score_events', [
                    'user_id'    => $userId,
                    'task_id'    => null,
                    'event_type' => 'all_today_bonus',
                    'points'     => self::POINTS['all_today_bonus'],
                    'customer_id'=> null,
                    'event_date' => $today,
                ]);
                $result['bonus'] = self::POINTS['all_today_bonus'];
                $stats = $this->recomputeDay($userId, $today);
            }
        }

        // 4) Achievements rund um die Erledigung pruefen.
        $result['new_achievements'] = $this->evaluateOnComplete($userId, $t, $stats);

        $result['day_points'] = (int) ($stats['points'] ?? 0);
        $result['day_tasks']  = (int) ($stats['tasks_completed'] ?? 0);
        return $result;
    }

    /** Task wieder geoeffnet → Score-Event(s) der Task zuruecknehmen, Tag neu rechnen. */
    public function onTaskUncompleted(int $userId, int $taskId): void
    {
        $today = date('Y-m-d');
        $this->db->execute(
            "DELETE FROM planner_score_events WHERE user_id = ? AND task_id = ?",
            [$userId, $taskId]
        );
        $stats = $this->recomputeDay($userId, $today);
        // Bonus zuruecknehmen, falls jetzt nicht mehr alle Heute-Tasks erledigt sind.
        if (empty($stats['all_today_done'])) {
            $this->db->execute(
                "DELETE FROM planner_score_events
                 WHERE user_id = ? AND event_type = 'all_today_bonus' AND event_date = ?",
                [$userId, $today]
            );
            $this->recomputeDay($userId, $today);
        }
    }

    /** Wird beim Setzen einer Task auf 'Warten' gerufen → Achievement 'delegation_master'. */
    public function onDelegation(int $userId): array
    {
        $today = date('Y-m-d');
        $count = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks
             WHERE user_id = ? AND is_waiting = 1 AND DATE(waiting_since) = ?",
            [$userId, $today]
        );
        $new = [];
        if ($count >= 5 && $this->award($userId, 'delegation_master', ['count' => $count])) {
            $new[] = $this->badge('delegation_master', ['count' => $count]);
        }
        return $new;
    }

    /** Tages-Kapazitaet (aus dem KI-Tagesplan) merken — speist 'punktlandung'. */
    public function setCapacity(int $userId, int $minutes): void
    {
        $today = date('Y-m-d');
        $this->db->execute(
            "INSERT INTO planner_daily_stats (user_id, stat_date, today_capacity_minutes)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE today_capacity_minutes = VALUES(today_capacity_minutes)",
            [$userId, $today, $minutes]
        );
    }

    // ============================ Lesen (fuer API/UI) ============================

    /** Heutiger Score + Streaks + heute verdiente Badges — fuer Header-Badge und Score-Panel. */
    public function getScore(int $userId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $stats = $this->db->queryOne(
            "SELECT * FROM planner_daily_stats WHERE user_id = ? AND stat_date = ?",
            [$userId, $date]
        ) ?: [];
        $breakdown = $this->db->query(
            "SELECT event_type, COUNT(*) AS cnt, SUM(points) AS pts
             FROM planner_score_events WHERE user_id = ? AND event_date = ?
             GROUP BY event_type",
            [$userId, $date]
        ) ?: [];
        $badges = $this->db->query(
            "SELECT achievement_key, meta, earned_at FROM planner_achievements
             WHERE user_id = ? AND earned_on = ? ORDER BY earned_at",
            [$userId, $date]
        ) ?: [];
        return [
            'date'             => $date,
            'points'           => (int) ($stats['points'] ?? 0),
            'tasks_completed'  => (int) ($stats['tasks_completed'] ?? 0),
            'effort_minutes'   => (int) ($stats['effort_minutes'] ?? 0),
            'today_planned'    => (int) ($stats['today_planned'] ?? 0),
            'today_done'       => (int) ($stats['today_done'] ?? 0),
            'all_today_done'   => (bool) ($stats['all_today_done'] ?? false),
            'streak_active'    => $this->streak($userId, 'tasks_completed'),
            'streak_all_today' => $this->streak($userId, 'all_today_done'),
            'breakdown'        => array_map(fn($b) => [
                'type' => $b['event_type'], 'count' => (int) $b['cnt'], 'points' => (int) $b['pts'],
            ], $breakdown),
            'badges'           => array_map(fn($b) => $this->badge($b['achievement_key'], $this->decodeMeta($b['meta'])), $badges),
            'best_weekday'     => $this->bestWeekday($userId),
        ];
    }

    /** Wochenrueckblick (Baustein D) — die letzten 7 Tage (inkl. heute). */
    public function getWeekReview(int $userId): array
    {
        $from = date('Y-m-d', strtotime('-6 days'));
        $to   = date('Y-m-d');

        $days = $this->db->query(
            "SELECT stat_date, tasks_completed, points, effort_minutes, all_today_done
             FROM planner_daily_stats
             WHERE user_id = ? AND stat_date BETWEEN ? AND ?
             ORDER BY stat_date",
            [$userId, $from, $to]
        ) ?: [];

        $totals = ['tasks' => 0, 'points' => 0, 'effort_minutes' => 0];
        foreach ($days as $d) {
            $totals['tasks']          += (int) $d['tasks_completed'];
            $totals['points']         += (int) $d['points'];
            $totals['effort_minutes'] += (int) $d['effort_minutes'];
        }

        $topCust = $this->db->queryOne(
            "SELECT se.customer_id, c.name AS customer_name, COUNT(*) AS cnt
             FROM planner_score_events se
             LEFT JOIN customers c ON c.id = se.customer_id
             WHERE se.user_id = ? AND se.event_date BETWEEN ? AND ? AND se.customer_id IS NOT NULL
             GROUP BY se.customer_id ORDER BY cnt DESC LIMIT 1",
            [$userId, $from, $to]
        );

        $bestHour = $this->db->queryOne(
            "SELECT HOUR(created_at) AS h, COUNT(*) AS cnt
             FROM planner_score_events
             WHERE user_id = ? AND event_date BETWEEN ? AND ? AND task_id IS NOT NULL
             GROUP BY h ORDER BY cnt DESC, h ASC LIMIT 1",
            [$userId, $from, $to]
        );

        return [
            'from'         => $from,
            'to'           => $to,
            'totals'       => $totals,
            'days'         => array_map(fn($d) => [
                'date'           => $d['stat_date'],
                'tasks'          => (int) $d['tasks_completed'],
                'points'         => (int) $d['points'],
                'effort_minutes' => (int) $d['effort_minutes'],
                'all_today_done' => (bool) $d['all_today_done'],
            ], $days),
            'top_customer' => $topCust ? [
                'id' => (int) $topCust['customer_id'],
                'name' => $topCust['customer_name'] ?: 'Ohne Kunde',
                'tasks' => (int) $topCust['cnt'],
            ] : null,
            'best_hour'    => $bestHour ? (int) $bestHour['h'] : null,
        ];
    }

    // ============================ Intern ============================

    /** Bestimmt Event-Typ + Punkte fuer eine gerade erledigte Task (hoechster zutreffender Wert). */
    private function classify(int $userId, array $t): array
    {
        $today = date('Y-m-d');
        // Kröte des Tages schlägt alles — bewusst angegangene schlimmste Aufgabe.
        if (!empty($t['is_toad'])) {
            return ['toad', self::POINTS['toad']];
        }
        if (!empty($t['due_on']) && $t['due_on'] < $today) {
            return ['overdue', self::POINTS['overdue']];
        }
        if (!empty($t['planned_for_date']) && $t['planned_for_date'] === $today && $this->isTodayHero($userId, (int) $t['id'])) {
            return ['today_hero', self::POINTS['today_hero']];
        }
        if (!empty($t['is_quick_task'])) {
            return ['quick', self::POINTS['quick']];
        }
        return ['normal', self::POINTS['normal']];
    }

    /** #1-Karte im Heute-Slot = hoechster Score. */
    private function isTodayHero(int $userId, int $taskId): bool
    {
        $heroId = (int) $this->db->queryValue(
            "SELECT id FROM planner_tasks
             WHERE user_id = ? AND planned_for_date = CURDATE() AND planner_ignored = 0
             ORDER BY score DESC, id ASC LIMIT 1",
            [$userId]
        );
        return $heroId === $taskId;
    }

    /** Tages-Roll-up aus score_events + planner_tasks neu berechnen (idempotent). Kapazitaet bleibt erhalten. */
    private function recomputeDay(int $userId, string $date): array
    {
        $points = (int) $this->db->queryValue(
            "SELECT COALESCE(SUM(points),0) FROM planner_score_events WHERE user_id = ? AND event_date = ?",
            [$userId, $date]
        );
        $tasksCompleted = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks
             WHERE user_id = ? AND planner_ignored = 0 AND DATE(completed_at_local) = ?",
            [$userId, $date]
        );
        $effort = (int) $this->db->queryValue(
            "SELECT COALESCE(SUM(COALESCE(NULLIF(effort_minutes,0), ai_effort_estimate, 60)),0)
             FROM planner_tasks
             WHERE user_id = ? AND planner_ignored = 0 AND DATE(completed_at_local) = ?",
            [$userId, $date]
        );
        $todayPlanned = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks
             WHERE user_id = ? AND planned_for_date = CURDATE() AND planner_ignored = 0 AND is_waiting = 0",
            [$userId]
        );
        $todayDone = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks
             WHERE user_id = ? AND planned_for_date = CURDATE() AND planner_ignored = 0 AND is_waiting = 0
               AND completed_at_local IS NOT NULL",
            [$userId]
        );
        $allTodayDone = ($todayPlanned > 0 && $todayDone >= $todayPlanned) ? 1 : 0;

        $topCustomer = $this->db->queryValue(
            "SELECT customer_id FROM planner_score_events
             WHERE user_id = ? AND event_date = ? AND customer_id IS NOT NULL
             GROUP BY customer_id ORDER BY COUNT(*) DESC LIMIT 1",
            [$userId, $date]
        );

        $this->db->execute(
            "INSERT INTO planner_daily_stats
                (user_id, stat_date, tasks_completed, points, effort_minutes, today_planned, today_done, all_today_done, top_customer_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                tasks_completed = VALUES(tasks_completed),
                points          = VALUES(points),
                effort_minutes  = VALUES(effort_minutes),
                today_planned   = VALUES(today_planned),
                today_done      = VALUES(today_done),
                all_today_done  = VALUES(all_today_done),
                top_customer_id = VALUES(top_customer_id)",
            [$userId, $date, $tasksCompleted, $points, $effort, $todayPlanned, $todayDone, $allTodayDone, $topCustomer ?: null]
        );

        return $this->db->queryOne(
            "SELECT * FROM planner_daily_stats WHERE user_id = ? AND stat_date = ?",
            [$userId, $date]
        ) ?: [];
    }

    /** Achievements pruefen, die an die Erledigung gekoppelt sind. Liefert neu verdiente Badges. */
    private function evaluateOnComplete(int $userId, array $t, array $stats): array
    {
        $new = [];

        // Sprint: 5 Tasks in 60 Minuten.
        $lastHour = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks
             WHERE user_id = ? AND planner_ignored = 0
               AND completed_at_local >= (NOW() - INTERVAL 60 MINUTE)",
            [$userId]
        );
        if ($lastHour >= 5 && $this->award($userId, 'sprint', ['count' => $lastHour])) {
            $new[] = $this->badge('sprint', ['count' => $lastHour]);
        }

        // Karteileiche: eine 3+ mal verschobene Task endlich erledigt.
        if ((int) ($t['postpone_count'] ?? 0) >= 3 && $this->award($userId, 'karteileiche', ['task' => $t['name']])) {
            $new[] = $this->badge('karteileiche', ['task' => $t['name']]);
        }

        // Quick Wins Hunter: 10 Quick-Tasks an einem Tag.
        $today = date('Y-m-d');
        $quickToday = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks
             WHERE user_id = ? AND planner_ignored = 0 AND is_quick_task = 1 AND DATE(completed_at_local) = ?",
            [$userId, $today]
        );
        if ($quickToday >= 10 && $this->award($userId, 'quickwins_hunter', ['count' => $quickToday])) {
            $new[] = $this->badge('quickwins_hunter', ['count' => $quickToday]);
        }

        // Punktlandung: alle Heute-Tasks erledigt UND Aufwand binnen +/-15 Min der gemerkten Kapazitaet.
        $cap = $stats['today_capacity_minutes'] ?? null;
        if (!empty($stats['all_today_done']) && $cap !== null && (int) $cap > 0) {
            $diff = abs((int) $stats['effort_minutes'] - (int) $cap);
            if ($diff <= 15 && $this->award($userId, 'punktlandung', ['capacity' => (int) $cap, 'spent' => (int) $stats['effort_minutes']])) {
                $new[] = $this->badge('punktlandung', ['capacity' => (int) $cap, 'spent' => (int) $stats['effort_minutes']]);
            }
        }

        return $new;
    }

    /** Badge eintragen, falls heute noch nicht vorhanden. true = neu verdient. */
    private function award(int $userId, string $key, array $meta = []): bool
    {
        $today = date('Y-m-d');
        $has = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM planner_achievements WHERE user_id = ? AND achievement_key = ? AND earned_on = ?",
            [$userId, $key, $today]
        );
        if ($has > 0) return false;
        $this->db->insert('planner_achievements', [
            'user_id'         => $userId,
            'achievement_key' => $key,
            'earned_on'       => $today,
            'meta'            => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
        return true;
    }

    /** Streak = aufeinanderfolgende Tage (bis heute) mit erfuelltem Feld. Heute zaehlt als Schonfrist. */
    private function streak(int $userId, string $field): int
    {
        $rows = $this->db->query(
            "SELECT stat_date, tasks_completed, all_today_done FROM planner_daily_stats
             WHERE user_id = ? AND stat_date >= ? ORDER BY stat_date DESC",
            [$userId, date('Y-m-d', strtotime('-400 days'))]
        ) ?: [];
        $byDate = [];
        foreach ($rows as $r) $byDate[$r['stat_date']] = $r;

        $streak = 0;
        $today = date('Y-m-d');
        for ($i = 0; $i < 400; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $row = $byDate[$date] ?? null;
            $met = $row && ($field === 'all_today_done'
                ? (int) $row['all_today_done'] === 1
                : (int) $row['tasks_completed'] > 0);
            if ($met) { $streak++; continue; }
            if ($date === $today) continue; // heute noch offen bricht die Serie nicht
            break;
        }
        return $streak;
    }

    /** Bester Wochentag der letzten ~8 Wochen (fuer "dein bester Tag war Mi mit 87P"). */
    private function bestWeekday(int $userId): ?array
    {
        $row = $this->db->queryOne(
            "SELECT DAYOFWEEK(stat_date) AS dow, AVG(points) AS avg_pts, MAX(points) AS max_pts
             FROM planner_daily_stats
             WHERE user_id = ? AND stat_date >= ? AND points > 0
             GROUP BY dow ORDER BY avg_pts DESC LIMIT 1",
            [$userId, date('Y-m-d', strtotime('-56 days'))]
        );
        if (!$row) return null;
        // MySQL DAYOFWEEK: 1=Sonntag .. 7=Samstag
        $names = [1 => 'So', 2 => 'Mo', 3 => 'Di', 4 => 'Mi', 5 => 'Do', 6 => 'Fr', 7 => 'Sa'];
        return [
            'weekday'    => $names[(int) $row['dow']] ?? '?',
            'avg_points' => (int) round($row['avg_pts']),
            'max_points' => (int) $row['max_pts'],
        ];
    }

    /** Badge-Metadaten fuer die UI (Icon, Label, Beschreibung) + Laufzeit-Meta. */
    private function badge(string $key, array $meta = []): array
    {
        static $defs = [
            'sprint'            => ['icon' => '🏃', 'label' => 'Sprint',            'desc' => '5 Tasks in einer Stunde'],
            'karteileiche'      => ['icon' => '🧹', 'label' => 'Karteileiche',      'desc' => 'Eine lange verschobene Task endlich erledigt'],
            'quickwins_hunter'  => ['icon' => '⚡', 'label' => 'Quick Wins Hunter',  'desc' => '10 Quick-Tasks an einem Tag'],
            'punktlandung'      => ['icon' => '🎯', 'label' => 'Punktlandung',       'desc' => 'Heute genau die geplante Kapazität getroffen'],
            'delegation_master' => ['icon' => '🤝', 'label' => 'Delegations-Master', 'desc' => '5+ Tasks sauber abgegeben'],
        ];
        $d = $defs[$key] ?? ['icon' => '🏅', 'label' => $key, 'desc' => ''];
        return ['key' => $key, 'icon' => $d['icon'], 'label' => $d['label'], 'desc' => $d['desc'], 'meta' => $meta];
    }

    private function decodeMeta($raw): array
    {
        if (!$raw) return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
