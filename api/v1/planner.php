<?php
/**
 * /api/v1/planner — Tagesplaner-API
 *
 * GET    /planner/tasks                — alle offenen Tasks des aktuellen Users, sortiert nach Score
 * POST   /planner/sync                 — Asana-Sync triggern (zieht Tasks aus Asana)
 * POST   /planner/pat                  — Asana-PAT speichern { pat } oder löschen { pat: "" }
 * GET    /planner/pat-status           — { hasPat: bool, gid, email, name, workspaces }
 * POST   /planner/tasks/{id}/effort    — Aufwand setzen { minutes: int }
 * POST   /planner/tasks/{id}/complete  — lokal abhaken { completed: bool }
 * POST   /planner/tasks/{id}/postpone  — verschieben { date: 'YYYY-MM-DD' | null }
 * POST   /planner/estimate-efforts     — KI-Aufwand-Schätzung für Tasks ohne Schätzung (Batch)
 * POST   /planner/plan-day             — KI-Tagesplan { minutes: int }
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::check()) Response::forbidden();
$userId = Auth::id();
if (!$userId) Response::forbidden();

$db = Database::getInstance();
require_once SERVICES_PATH . '/PlannerSyncService.php';
require_once SERVICES_PATH . '/PlannerScoreService.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

if ($action === 'pat-status' && $method === 'GET') {
    $sync = new \Services\PlannerSyncService($db);
    $row = $db->queryOne(
        "SELECT asana_user_gid, asana_user_email, asana_user_name FROM users WHERE id = ?",
        [$userId]
    );
    Response::success([
        'has_pat'  => $sync->hasUserPat($userId),
        'gid'      => $row['asana_user_gid'] ?? null,
        'email'    => $row['asana_user_email'] ?? null,
        'name'     => $row['asana_user_name'] ?? null,
    ]);
}

if ($action === 'pat' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $pat = trim((string)($payload['pat'] ?? ''));
    $sync = new \Services\PlannerSyncService($db);
    try {
        $result = $sync->setUserPat($userId, $pat);
        Response::success($result, $pat === '' ? 'PAT entfernt' : 'PAT gespeichert');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'sync' && $method === 'POST') {
    $sync = new \Services\PlannerSyncService($db);
    try {
        $stats = $sync->syncForUser($userId);
        // Zeitraum-Buckets aus den (ggf. neuen) Fristen materialisieren
        $sync->recomputeBuckets($userId);
        // Score neu berechnen
        (new \Services\PlannerScoreService($db))->scoreAll($userId);
        Response::success($stats, 'Sync abgeschlossen');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'resolve-customers' && $method === 'POST') {
    $sync = new \Services\PlannerSyncService($db);
    try {
        $stats = $sync->resolveCustomersForUser($userId);
        // Nebenher die Zeitraum-Buckets aus den Fristen neu materialisieren
        $sync->recomputeBuckets($userId);
        Response::success($stats, 'Kundenzuordnung + Zeitraum neu berechnet');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'tasks' && $method === 'GET') {
    $includeCompleted = !empty($_GET['include_completed']);
    $includeIgnored = !empty($_GET['include_ignored']);
    $where = "pt.user_id = ?";
    $params = [$userId];
    if ($includeCompleted) {
        // Mit Completed: offene Tasks + Erledigte aus den letzten 90 Tagen (deckt das Archiv-Maximum).
        // Verhindert, dass mehrere tausend Asana-Historie-Tasks über den Draht gehen.
        $cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
        $where .= " AND ( (pt.completed_at_asana IS NULL AND pt.completed_at_local IS NULL)
                       OR pt.completed_at_asana >= ?
                       OR pt.completed_at_local >= ? )";
        $params[] = $cutoff;
        $params[] = $cutoff;
    } else {
        // Ohne Completed: nur offene Tasks.
        $where .= " AND pt.completed_at_asana IS NULL AND pt.completed_at_local IS NULL";
    }
    if (!$includeIgnored) {
        $where .= " AND pt.planner_ignored = 0";
    }
    $rows = $db->query(
        "SELECT pt.*, c.name AS customer_name, c.abbreviation AS customer_abbr, c.hex_color AS customer_color, c.is_hot AS customer_is_hot,
                a.account_label AS asana_account_label, a.color_hex AS asana_account_color, a.is_default AS asana_account_is_default,
                a.default_customer_id AS asana_account_default_customer_id
         FROM planner_tasks pt
         LEFT JOIN customers c ON c.id = pt.customer_id
         LEFT JOIN user_asana_accounts a ON a.id = pt.asana_account_id
         WHERE $where
         ORDER BY pt.score DESC, pt.due_on ASC, pt.name ASC",
        $params
    ) ?: [];

    // 'Brennende Kunden' = Kunden mit aktivem ESKALIERTEM Plan im Projektplanner (risiko_modus='eskaliert').
    // Plus optionaler Budget-Status (over/risk) und manuelle is_hot-Markierung.
    try {
        $burning = [];
        foreach ($db->query("SELECT DISTINCT customer_id FROM pp_plans WHERE state = 1 AND risiko_modus = 'eskaliert' AND customer_id IS NOT NULL") ?: [] as $bp) {
            $burning[(int)$bp['customer_id']] = true;
        }
        $custStatus = [];
        require_once SERVICES_PATH . '/PpBudgetService.php';
        foreach (((new \Services\PpBudgetService($db))->getCustomersOverview((int)date('Y')) ?? []) as $cc) {
            if (is_array($cc) && isset($cc['customer_id'])) $custStatus[(int)$cc['customer_id']] = $cc['status'] ?? 'ok';
        }
        foreach ($rows as &$r) {
            $cid = (int)($r['customer_id'] ?? 0);
            $r['customer_budget_status'] = ($cid && isset($custStatus[$cid])) ? $custStatus[$cid] : null;
            // is_hot kombiniert: Projektplanner-Eskalation ODER manuelle Markierung.
            $r['customer_is_hot'] = (($cid && isset($burning[$cid])) || !empty($r['customer_is_hot'])) ? 1 : 0;
        }
        unset($r);
    } catch (\Throwable $e) { /* optional */ }

    // Anzahl neuer Tasks seit letztem Tagesplan-Besuch (kommen vom Cron-Sync)
    $lastSeen = $db->queryValue("SELECT planner_last_seen_at FROM users WHERE id = ?", [$userId]);
    $newCount = 0;
    $autoPushedToday = 0;
    if ($lastSeen) {
        $newCount = (int)$db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks WHERE user_id = ? AND created_at > ?
              AND completed_at_asana IS NULL AND planner_ignored = 0",
            [$userId, $lastSeen]
        );
        $autoPushedToday = (int)$db->queryValue(
            "SELECT COUNT(*) FROM planner_tasks WHERE user_id = ? AND auto_pushed_to_today_at > ?
              AND completed_at_local IS NULL AND completed_at_asana IS NULL AND planner_ignored = 0",
            [$userId, $lastSeen]
        );
    }
    // "Special-Tile-Customers" = Kunden, die als default_customer_id auf einem
    // Asana-Account des Users gesetzt sind. Bekommen ihre eigene Kachel in Phase 1
    // (z.B. Hills & Valleys-Account → H&V-Kachel).
    $specialCustomers = $db->query(
        "SELECT DISTINCT c.id, c.name, c.abbreviation, c.hex_color
         FROM user_asana_accounts a
         JOIN customers c ON c.id = a.default_customer_id
         WHERE a.user_id = ? AND a.is_active = 1 AND a.default_customer_id IS NOT NULL",
        [$userId]
    ) ?: [];

    Response::success([
        'tasks' => $rows,
        'new_count' => $newCount,
        'auto_pushed_today' => $autoPushedToday,
        'last_seen' => $lastSeen,
        'special_customers' => $specialCustomers,
    ]);
}

if ($action === 'mark-seen' && $method === 'POST') {
    $db->update('users', ['planner_last_seen_at' => date('Y-m-d H:i:s')], 'id = ?', [$userId]);
    Response::success(['marked' => true]);
}

if ($action === 'effort' && $method === 'POST' && $taskId > 0) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $minutes = (int)($payload['minutes'] ?? 0);
    if ($minutes < 0 || $minutes > 60 * 24) Response::error('Ungültiger Aufwand');
    // Besitzer-Check
    $task = $db->queryOne("SELECT id FROM planner_tasks WHERE id = ? AND user_id = ?", [$taskId, $userId]);
    if (!$task) Response::notFound();
    $db->update('planner_tasks', ['effort_minutes' => $minutes ?: null], 'id = ?', [$taskId]);
    // Score nachziehen
    (new \Services\PlannerScoreService($db))->scoreAll($userId);
    Response::success(['task_id' => $taskId, 'minutes' => $minutes]);
}

if ($action === 'ack-signal' && $method === 'POST' && $taskId > 0) {
    // Quittiert das Warten-Auto-Wake-Signal — Karte verliert das 'Ball zurück'-Badge.
    // is_waiting selbst wurde vom Sync bereits auf 0 gesetzt; das war nur die UI-Markierung.
    $task = $db->queryOne("SELECT id FROM planner_tasks WHERE id = ? AND user_id = ?", [$taskId, $userId]);
    if (!$task) Response::notFound();
    $db->update('planner_tasks', ['waiting_signal' => 0], 'id = ?', [$taskId]);
    Response::success(['task_id' => $taskId]);
}

if ($action === 'waiting-candidates' && $method === 'GET' && $taskId > 0) {
    // Liefert eine Vorschlagsliste für "auf wen wartest Du?":
    //  - alle aktiven Mitarbeitenden (intern)
    //  - alle Kontakte beim verknüpften CRM-Kunden (extern)
    $task = $db->queryOne(
        "SELECT pt.id, pt.customer_id, c.crm_firma_id, c.name AS customer_name
         FROM planner_tasks pt
         LEFT JOIN customers c ON c.id = pt.customer_id
         WHERE pt.id = ? AND pt.user_id = ?",
        [$taskId, $userId]
    );
    if (!$task) Response::notFound();

    $internal = $db->query(
        "SELECT id, name, email, abbreviation FROM users WHERE is_active = 1 ORDER BY name ASC"
    ) ?: [];

    $external = [];
    if (!empty($task['crm_firma_id'])) {
        $external = $db->query(
            "SELECT id, vorname, nachname, funktion, email_primär
             FROM crm_kontakte
             WHERE firma_id = ? AND gelöscht_am IS NULL
             ORDER BY nachname ASC, vorname ASC",
            [(int)$task['crm_firma_id']]
        ) ?: [];
    }

    Response::success([
        'task_id' => $taskId,
        'customer_name' => $task['customer_name'] ?? null,
        'internal' => array_map(fn($u) => [
            'name' => $u['name'],
            'abbreviation' => $u['abbreviation'] ?? null,
            'email' => $u['email'] ?? null,
        ], $internal),
        'external' => array_map(fn($k) => [
            'name' => trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')),
            'funktion' => $k['funktion'] ?? null,
            'email' => $k['email_primär'] ?? null,
        ], $external),
    ]);
}

if ($action === 'ack-reanalyzed' && $method === 'POST' && $taskId > 0) {
    // Quittiert das Re-Analyse-Signal (Slot/Aufwand/Fortschritt nach Asana-Change).
    $task = $db->queryOne("SELECT id FROM planner_tasks WHERE id = ? AND user_id = ?", [$taskId, $userId]);
    if (!$task) Response::notFound();
    $db->update('planner_tasks', ['ai_re_analyzed_signal' => 0, 'ai_re_analyzed_summary' => null], 'id = ?', [$taskId]);
    Response::success(['task_id' => $taskId]);
}

if ($action === 'complete' && $method === 'POST' && $taskId > 0) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $task = $db->queryOne("SELECT id FROM planner_tasks WHERE id = ? AND user_id = ?", [$taskId, $userId]);
    if (!$task) Response::notFound();
    $completed = !empty($payload['completed']);
    $db->update('planner_tasks', [
        'completed_at_local' => $completed ? date('Y-m-d H:i:s') : null,
    ], 'id = ?', [$taskId]);
    // Gamification: Punkte/Bonus/Badges vergeben bzw. beim Wiederoeffnen zuruecknehmen.
    $gami = null;
    try {
        $svc = new \Services\PlannerGamificationService($db);
        if ($completed) {
            $gami = $svc->onTaskCompleted($userId, $taskId);
        } else {
            $svc->onTaskUncompleted($userId, $taskId);
        }
    } catch (\Throwable $e) {
        error_log('PlannerGamification/complete: ' . $e->getMessage());
    }
    Response::success(['task_id' => $taskId, 'completed' => $completed, 'gamification' => $gami]);
}

if ($action === 'postpone' && $method === 'POST' && $taskId > 0) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $date = trim((string)($payload['date'] ?? ''));
    $task = $db->queryOne("SELECT id, postpone_count FROM planner_tasks WHERE id = ? AND user_id = ?", [$taskId, $userId]);
    if (!$task) Response::notFound();
    // postpone_count zählt jedes Verschieben — wird vom Score als Stale-Penalty verwendet
    $db->update('planner_tasks', [
        'postponed_to' => $date !== '' ? $date : null,
        'postpone_count' => (int)$task['postpone_count'] + 1,
    ], 'id = ?', [$taskId]);
    (new \Services\PlannerScoreService($db))->scoreAll($userId);
    Response::success(['task_id' => $taskId, 'postponed_to' => $date ?: null, 'postpone_count' => (int)$task['postpone_count'] + 1]);
}

if ($action === 'bulk-set' && $method === 'POST') {
    // Bulk-Update für mehrere Tasks auf einmal.
    // Body: { task_ids: [int,...], action: 'private'|'ignore'|'customer'|'effort'|'slot'|'priority'|'complete',
    //         customer_id?: int, minutes?: int, slot?: <8 Zeitraum-Buckets>,
    //         priority?: 'asap'|'this_week'|'when_possible' }
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = array_values(array_filter(array_map('intval', $payload['task_ids'] ?? []), fn($i) => $i > 0));
    $bulkAction = $payload['action'] ?? '';
    if (empty($ids)) Response::error('Keine Task-IDs');
    if (!in_array($bulkAction, ['private','ignore','customer','effort','slot','priority','complete'], true)) Response::error('Ungültige Aktion');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $ownTasks = $db->query(
        "SELECT id FROM planner_tasks WHERE user_id = ? AND id IN ($placeholders)",
        array_merge([$userId], $ids)
    ) ?: [];
    $ownIds = array_map(fn($r) => (int)$r['id'], $ownTasks);
    if (empty($ownIds)) Response::error('Keine eigenen Tasks gefunden');

    $idPh = implode(',', array_fill(0, count($ownIds), '?'));
    $updated = 0;

    if ($bulkAction === 'private') {
        $db->execute("UPDATE planner_tasks SET customer_id = NULL, category_hint = 'private' WHERE id IN ($idPh)", $ownIds);
        $updated = count($ownIds);
    } elseif ($bulkAction === 'ignore') {
        $db->execute("UPDATE planner_tasks SET planner_ignored = 1 WHERE id IN ($idPh)", $ownIds);
        $updated = count($ownIds);
    } elseif ($bulkAction === 'customer') {
        $cid = (int)($payload['customer_id'] ?? 0);
        if ($cid <= 0) Response::error('customer_id fehlt');
        if (!$db->queryValue("SELECT id FROM customers WHERE id = ?", [$cid])) Response::error('Kunde nicht gefunden');
        $db->execute("UPDATE planner_tasks SET customer_id = ?, category_hint = NULL WHERE id IN ($idPh)", array_merge([$cid], $ownIds));
        $updated = count($ownIds);
    } elseif ($bulkAction === 'effort') {
        $m = (int)($payload['minutes'] ?? 0);
        if ($m < 1 || $m > 1440) Response::error('Minuten ausserhalb 1-1440');
        $db->execute("UPDATE planner_tasks SET effort_minutes = ? WHERE id IN ($idPh)", array_merge([$m], $ownIds));
        $updated = count($ownIds);
    } elseif ($bulkAction === 'slot') {
        // Bulk-Re-Planen: setzt die Faelligkeit auf ein repraesentatives Datum des Buckets (Frist ist die Wahrheit).
        $slot = $payload['slot'] ?? '';
        if (!in_array($slot, ['today','tomorrow','day_after','rest_week','next_week','this_month','later','occasion'], true)) Response::error('Ungültiger Slot');
        $newDue = \Services\PlannerSyncService::bucketToDate($slot);
        $db->execute("UPDATE planner_tasks SET due_on = ?, due_locally_set = 1, daily_slot = ? WHERE id IN ($idPh)", array_merge([$newDue, $slot], $ownIds));
        $updated = count($ownIds);
    } elseif ($bulkAction === 'priority') {
        $p = $payload['priority'] ?? '';
        if (!in_array($p, ['asap','this_week','when_possible'], true)) Response::error('Ungültige Prio');
        $db->execute("UPDATE planner_tasks SET manual_priority = ? WHERE id IN ($idPh)", array_merge([$p], $ownIds));
        $updated = count($ownIds);
    } elseif ($bulkAction === 'complete') {
        $now = date('Y-m-d H:i:s');
        $db->execute("UPDATE planner_tasks SET completed_at_local = ? WHERE id IN ($idPh)", array_merge([$now], $ownIds));
        $updated = count($ownIds);
    }
    (new \Services\PlannerScoreService($db))->scoreAll($userId);
    Response::success(['updated' => $updated, 'task_ids' => $ownIds]);
}

if ($action === 'set-field' && $method === 'POST' && $taskId > 0) {
    // Generisches Update für Kanban-Drops + Inline-Edits.
    // Erlaubte Felder: daily_slot, manual_priority, effort_minutes, due_on
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    // Vergleichsfelder mitladen, um Korrekturen gegen die KI-Vorhersage zu protokollieren (Lernschleife).
    $task = $db->queryOne(
        "SELECT pt.id, pt.name, pt.asana_project_name, pt.notes, pt.ai_effort_estimate, pt.ai_is_quick,
                pt.ai_recommended_when, pt.daily_slot, pt.slot_pinned, pt.customer_id, c.name AS customer_name
         FROM planner_tasks pt
         LEFT JOIN customers c ON c.id = pt.customer_id
         WHERE pt.id = ? AND pt.user_id = ?",
        [$taskId, $userId]
    );
    if (!$task) Response::notFound();

    $allowed = ['plan_today', 'manual_priority', 'effort_minutes', 'due_on', 'customer_id', 'category_hint', 'is_quick_task', 'is_waiting', 'waiting_on', 'is_recurring', 'recurring_pattern', 'recurring_interval_days', 'quick_win_user_excluded', 'is_toad'];
    $updates = [];
    foreach ($allowed as $f) {
        if (!array_key_exists($f, $payload)) continue;
        $v = $payload[$f];
        if ($f === 'plan_today') {
            // Tagesplan-Commit (Phase 7 „Heute"): heute einplanen / aus dem Tagesplan nehmen.
            $updates['planned_for_date'] = !empty($v) ? date('Y-m-d') : null;
        } elseif ($f === 'manual_priority') {
            if ($v === null || $v === '') $updates[$f] = null;
            elseif (in_array($v, ['asap','this_week','when_possible'], true)) $updates[$f] = $v;
        } elseif ($f === 'effort_minutes') {
            $m = (int)$v;
            $updates[$f] = ($m > 0 && $m <= 1440) ? $m : null;
        } elseif ($f === 'due_on') {
            $updates[$f] = (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null;
            // Frist im Tool gesetzt = lokale Re-Planung; Asana-Sync überschreibt sie nicht mehr (bis Asana angeglichen).
            $updates['due_locally_set'] = 1;
        } elseif ($f === 'customer_id') {
            if ($v === null || $v === '' || $v === 0) $updates[$f] = null;
            else {
                $cid = (int)$v;
                $exists = $db->queryValue("SELECT id FROM customers WHERE id = ?", [$cid]);
                if ($exists) $updates[$f] = $cid;
            }
        } elseif ($f === 'category_hint') {
            $updates[$f] = in_array($v, ['private','unclear'], true) ? $v : null;
        } elseif ($f === 'is_quick_task') {
            $updates[$f] = !empty($v) ? 1 : 0;
        } elseif ($f === 'is_waiting') {
            // Beim Setzen auf 'Warten' auch waiting_since stempeln; beim Lösen Signal mit-quittieren.
            $newVal = !empty($v) ? 1 : 0;
            $updates[$f] = $newVal;
            if ($newVal) {
                $updates['waiting_since'] = date('Y-m-d H:i:s');
            } else {
                $updates['waiting_signal'] = 0;
            }
        } elseif ($f === 'waiting_on') {
            $updates[$f] = is_string($v) ? mb_substr(trim($v), 0, 100) : null;
        } elseif ($f === 'is_recurring') {
            $updates[$f] = !empty($v) ? 1 : 0;
        } elseif ($f === 'recurring_pattern') {
            $updates[$f] = is_string($v) ? mb_substr(trim($v), 0, 60) : null;
        } elseif ($f === 'recurring_interval_days') {
            $i = (int)$v;
            $updates[$f] = ($i >= 1 && $i <= 730) ? $i : null;
        } elseif ($f === 'quick_win_user_excluded') {
            $updates[$f] = !empty($v) ? 1 : 0;
        } elseif ($f === 'is_toad') {
            $updates[$f] = !empty($v) ? 1 : 0;
        }
    }
    if (empty($updates)) Response::error('Kein gültiges Feld geliefert');
    // Kröte des Tages: immer nur EINE aktiv — beim Setzen alle anderen des Users zurücksetzen.
    if (!empty($updates['is_toad'])) {
        $db->execute("UPDATE planner_tasks SET is_toad = 0 WHERE user_id = ? AND id <> ?", [$userId, $taskId]);
    }
    $db->update('planner_tasks', $updates, 'id = ?', [$taskId]);
    // Frist geändert → Zeitraum-Bucket immer neu aus der Frist ableiten (Frist ist die eine Wahrheit).
    if (array_key_exists('due_on', $updates)) {
        $db->update('planner_tasks', ['daily_slot' => \Services\PlannerSyncService::fristBucket($updates['due_on'])], 'id = ?', [$taskId]);
    }
    (new \Services\PlannerScoreService($db))->scoreAll($userId);
    // Lernschleife: Korrekturen gegen die KI-Vorhersage protokollieren (Aufwand/Quick/Wichtigkeit/Slot/Kunde).
    // Automatische Calls (z.B. Auto-Refill beim Abhaken) tragen _auto=1 und werden NICHT als Korrektur gewertet.
    if (empty($payload['_auto'])) {
        try {
            (new \Services\PlannerLearningService($db))->recordCorrection($userId, $taskId, $task, $updates);
        } catch (\Throwable $e) {
            error_log('PlannerLearning/record: ' . $e->getMessage());
        }
    }
    // Gamification: 'Delegations-Master' pruefen, wenn gerade auf 'Warten' gesetzt wurde.
    $gami = null;
    if (!empty($updates['is_waiting'])) {
        try {
            $newBadges = (new \Services\PlannerGamificationService($db))->onDelegation($userId);
            if ($newBadges) $gami = ['new_achievements' => $newBadges];
        } catch (\Throwable $e) {
            error_log('PlannerGamification/delegation: ' . $e->getMessage());
        }
    }
    Response::success(['task_id' => $taskId, 'updated' => $updates, 'gamification' => $gami]);
}

if ($action === 'ignore' && $method === 'POST' && $taskId > 0) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $task = $db->queryOne("SELECT id FROM planner_tasks WHERE id = ? AND user_id = ?", [$taskId, $userId]);
    if (!$task) Response::notFound();
    $ignored = !empty($payload['ignored']) ? 1 : 0;
    $db->update('planner_tasks', ['planner_ignored' => $ignored], 'id = ?', [$taskId]);
    (new \Services\PlannerScoreService($db))->scoreAll($userId);
    Response::success(['task_id' => $taskId, 'ignored' => (bool)$ignored]);
}

if ($action === 'reset-analysis' && $method === 'POST') {
    // Setzt ai_summary + last_activity auf NULL für alle offenen Tasks, damit der nächste
    // KI-Analyse-Lauf sie nochmal aufnimmt (z.B. für neue Quick-Task-Erkennung).
    $db->execute(
        "UPDATE planner_tasks
         SET ai_summary = NULL, last_activity = NULL
         WHERE user_id = ? AND completed_at_asana IS NULL AND completed_at_local IS NULL AND planner_ignored = 0",
        [$userId]
    );
    $count = (int)$db->queryValue(
        "SELECT COUNT(*) FROM planner_tasks WHERE user_id = ? AND completed_at_asana IS NULL AND completed_at_local IS NULL AND planner_ignored = 0",
        [$userId]
    );
    Response::success(['reset' => $count], $count . ' Tasks für Re-Analyse markiert');
}

if ($action === 'estimate-efforts' && $method === 'POST') {
    require_once SERVICES_PATH . '/PlannerEffortAiService.php';
    $svc = new \Services\PlannerEffortAiService($db);
    try {
        $stats = $svc->estimateMissingForUser($userId);
        (new \Services\PlannerScoreService($db))->scoreAll($userId);
        Response::success($stats);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'sort-slots' && $method === 'POST') {
    require_once SERVICES_PATH . '/PlannerSlotPlanService.php';
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $capacity = is_array($payload['capacity'] ?? null) ? $payload['capacity'] : [];
    $svc = new \Services\PlannerSlotPlanService($db);
    try {
        $result = $svc->planSlots($userId, $capacity);
        (new \Services\PlannerScoreService($db))->scoreAll($userId);
        Response::success($result);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'plan-day' && $method === 'POST') {
    require_once SERVICES_PATH . '/PlannerDayPlanService.php';
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $minutes = (int)($payload['minutes'] ?? 0);
    $slot = in_array($payload['slot'] ?? '', ['today','tomorrow','day_after','rest_week'], true) ? $payload['slot'] : 'today';
    if ($minutes < 15 || $minutes > 60 * 16) Response::error('Verfügbare Zeit: 15-960 Minuten');
    $svc = new \Services\PlannerDayPlanService($db);
    try {
        $plan = $svc->generate($userId, $minutes, $payload['focus_note'] ?? null, $slot);
        // Tages-Kapazitaet merken (nur fuer den Heute-Plan) — speist Achievement 'Punktlandung'.
        if ($slot === 'today') {
            try { (new \Services\PlannerGamificationService($db))->setCapacity($userId, $minutes); }
            catch (\Throwable $e) { error_log('PlannerGamification/capacity: ' . $e->getMessage()); }
        }
        Response::success($plan);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

// ---- Gamification: Score/Streaks (Header + Panel) ----
if ($action === 'score' && $method === 'GET') {
    try {
        $data = (new \Services\PlannerGamificationService($db))->getScore($userId);
        Response::success($data);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

// ---- Gamification: Wochenrueckblick ----
if ($action === 'week-review' && $method === 'GET') {
    try {
        $data = (new \Services\PlannerGamificationService($db))->getWeekReview($userId);
        Response::success($data);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

// ---- Lernschleife: Regeln auflisten (inkl. Korrektur-Zaehler) ----
if ($action === 'learn-rules' && $method === 'GET') {
    try {
        $svc = new \Services\PlannerLearningService($db);
        Response::success([
            'rules'             => $svc->listRules($userId),
            'correction_count'  => $svc->correctionCount($userId),
        ]);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

// ---- Lernschleife: Korrekturen analysieren -> Regelkandidaten ----
if ($action === 'learn-analyze' && $method === 'POST') {
    try {
        $result = (new \Services\PlannerLearningService($db))->deriveRules($userId);
        Response::success($result, $result['message'] ?? 'Analyse abgeschlossen');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

// ---- Lernschleife: Regel-Status setzen (active|dismissed|candidate) ----
if ($action === 'learn-rule-status' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $ruleId = (int)($payload['rule_id'] ?? 0);
    $status = (string)($payload['status'] ?? '');
    if ($ruleId <= 0) Response::error('rule_id fehlt');
    $ok = (new \Services\PlannerLearningService($db))->setRuleStatus($userId, $ruleId, $status);
    if (!$ok) Response::error('Ungültiger Status oder Regel nicht gefunden');
    Response::success(['rule_id' => $ruleId, 'status' => $status]);
}

if ($action === 'customer-hot' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $cid = (int)($payload['customer_id'] ?? 0);
    $hot = !empty($payload['hot']) ? 1 : 0;
    if ($cid <= 0) Response::error('customer_id fehlt');
    $db->update('customers', ['is_hot' => $hot], 'id = ?', [$cid]);
    Response::success(['customer_id' => $cid, 'hot' => $hot]);
}

Response::error('Unbekannte Action', 404);
