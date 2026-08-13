<?php
/**
 * Asana-Integration für den Projektplanner.
 *
 * GET  /admin/projektplanner/asana/projects                    Asana-Projekte (Default-Workspace)
 * GET  /admin/projektplanner/asana/sections?project_gid=X      Sektionen eines Projekts
 * GET  /admin/projektplanner/asana/search?project_gid=X&q=Y    Tasks suchen
 * POST /admin/projektplanner/asana/create-task
 *      Body: { project_gid, section_gid?, name, notes?, plan_row_id? }
 *      → erstellt Task; wenn plan_row_id gegeben, wird die Plan-Zeile direkt verknüpft
 * GET  /admin/projektplanner/asana/task/{gid}                   Detail
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\AsanaService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$db = Database::getInstance();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$pat = \Core\Settings::get('asana_pat');
if (empty($pat)) Response::error('Asana PAT nicht konfiguriert. Unter /admin/settings einrichten.');

require_once SERVICES_PATH . '/AsanaService.php';
$asana = new AsanaService($pat);

try {
    if ($action === 'projects' && $method === 'GET') {
        $workspaceGid = $_GET['workspace_gid'] ?? null;
        if (!$workspaceGid) {
            $workspaceGid = \Core\Settings::get('asana_workspace_gid');
        }
        if (!$workspaceGid) {
            $workspaces = $asana->listWorkspaces();
            if (empty($workspaces)) Response::error('Keine Asana-Workspaces gefunden');
            $workspaceGid = $workspaces[0]['gid'];
        }
        $projects = $asana->listProjects((string) $workspaceGid);
        Response::success(['projects' => $projects, 'workspace_gid' => $workspaceGid]);
    }

    if ($action === 'sections' && $method === 'GET') {
        $projectGid = $_GET['project_gid'] ?? '';
        if (!$projectGid) Response::error('project_gid fehlt');
        Response::success(['sections' => $asana->listSections($projectGid)]);
    }

    if ($action === 'search' && $method === 'GET') {
        $projectGid = $_GET['project_gid'] ?? '';
        $query = trim((string) ($_GET['q'] ?? ''));
        if (!$projectGid) Response::error('project_gid fehlt');
        $wsGid = \Core\Settings::get('asana_workspace_gid') ?: null;
        Response::success(['tasks' => $asana->searchTasks($projectGid, $query, 30, $wsGid)]);
    }

    if ($action === 'task' && $method === 'GET') {
        $taskGid = $_GET['task_gid'] ?? '';
        if (!$taskGid) Response::error('task_gid fehlt');
        $task = $asana->getTask($taskGid);
        if (!$task) Response::error('Task nicht gefunden');
        $stories = [];
        try { $stories = $asana->getTaskStories($taskGid, true); } catch (\Throwable $_) {}
        Response::success(['task' => $task, 'stories' => $stories]);
    }

    if ($action === 'templates' && $method === 'GET') {
        // Tasks aus einem speziellen Templates-Project (configurable via Settings)
        // Caching 1h pro Project-GID
        $projectGid = \Core\Settings::get('asana_templates_project_gid');
        if (empty($projectGid)) Response::success(['templates' => [], 'configured' => false]);
        $cacheKey = '/tmp/pp_asana_tmpl_' . preg_replace('/[^a-z0-9]/i', '', $projectGid) . '.json';
        if (!empty($_GET['refresh']) || !file_exists($cacheKey) || (time() - filemtime($cacheKey) > 3600)) {
            $tasks = $asana->getTasks($projectGid);
            $templates = array_map(function ($t) {
                return [
                    'gid' => $t['gid'] ?? '',
                    'name' => $t['name'] ?? '',
                    'notes' => $t['notes'] ?? '',
                ];
            }, $tasks);
            file_put_contents($cacheKey, json_encode($templates));
        } else {
            $templates = json_decode(file_get_contents($cacheKey), true) ?: [];
        }
        Response::success(['templates' => $templates, 'configured' => true, 'cached' => file_exists($cacheKey)]);
    }

    if ($action === 'sync-status' && $method === 'POST') {
        // DEAKTIVIERT (Stand 2026-05-27): Projektplanner und Asana sind nur per Link
        // verbunden, nicht per Status. Frueher hat dieser Endpoint is_done aus Asana
        // ueberschrieben — das war nicht gewuenscht und hat manuell gesetzte Erledigt-
        // Flags zurueckgesetzt.
        Response::success(
            ['checked' => 0, 'changed' => 0],
            'Status-Sync zwischen Projektplanner und Asana ist deaktiviert.'
        );
    }

    if ($action === 'refresh-cache' && $method === 'POST') {
        // Lösche alle Asana-Caches
        foreach (glob('/tmp/pp_asana_*') as $f) @unlink($f);
        Response::success(['ok' => true], 'Asana-Cache geleert');
    }

    if ($action === 'orphans' && $method === 'GET') {
        // Plan-Zeilen mit asana_gid suchen + prüfen ob Asana-Task noch existiert
        $planId = (int) ($_GET['plan_id'] ?? 0);
        $sql = "SELECT r.id, r.asana_gid, r.asana_task_name, r.description, r.plan_id,
                       p.title AS plan_title
                FROM pp_plan_rows r
                JOIN pp_plans p ON p.id = r.plan_id AND p.state = 1
                WHERE r.asana_gid IS NOT NULL AND r.asana_gid != ''";
        $params = [];
        if ($planId) { $sql .= " AND r.plan_id = ?"; $params[] = $planId; }
        $sql .= " ORDER BY p.id, r.position LIMIT 500";
        $rows = $db->query($sql, $params) ?: [];
        $orphans = [];
        foreach ($rows as $row) {
            try {
                $task = $asana->getTask((string) $row['asana_gid']);
                if (!$task || empty($task['gid'])) {
                    $orphans[] = $row;
                }
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'not found') !== false || stripos($e->getMessage(), '404') !== false) {
                    $orphans[] = $row;
                }
            }
        }
        Response::success(['orphans' => $orphans, 'checked' => count($rows)]);
    }

    if ($action === 'unlink-orphans' && $method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $rowIds = $payload['row_ids'] ?? [];
        if (!is_array($rowIds) || empty($rowIds)) Response::error('row_ids fehlt');
        $rowIds = array_map('intval', $rowIds);
        // Permission-Check pro Row's Plan
        $planIds = array_unique(array_filter(array_map(
            fn($id) => (int) $db->queryValue("SELECT plan_id FROM pp_plan_rows WHERE id = ?", [$id]),
            $rowIds
        )));
        require __DIR__ . '/_pp_perm.php';
        foreach ($planIds as $pid) pp_require($pid, 'write');
        // Unlink
        $count = 0;
        foreach ($rowIds as $id) {
            $db->update('pp_plan_rows', [
                'asana_gid' => null, 'asana_url' => null, 'asana_task_name' => null,
            ], 'id = ?', [$id]);
            $count++;
        }
        Response::success(['unlinked' => $count], "$count Asana-Refs entfernt");
    }

    if ($action === 'create' && $method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $projectGid = (string) ($payload['project_gid'] ?? '');
        $name = trim((string) ($payload['name'] ?? ''));
        $sectionGid = $payload['section_gid'] ?? null;
        $notes = $payload['notes'] ?? null;
        $rowId = !empty($payload['plan_row_id']) ? (int) $payload['plan_row_id'] : null;
        if (!$projectGid || !$name) Response::error('project_gid und name erforderlich');
        // Permission-Check über plan_id der Row
        if ($rowId) {
            $rowPlanId = (int) $db->queryValue("SELECT plan_id FROM pp_plan_rows WHERE id = ?", [$rowId]);
            if ($rowPlanId) { require __DIR__ . '/_pp_perm.php'; pp_require($rowPlanId, 'write'); }
        }
        $task = $asana->createTask($projectGid, $name, $sectionGid ?: null, $notes);
        // Direkt an Plan-Zeile binden falls angegeben
        if ($rowId && !empty($task['gid'])) {
            $url = $task['permalink_url'] ?? ("https://app.asana.com/0/0/" . $task['gid']);
            $db->update('pp_plan_rows',
                ['asana_gid' => $task['gid'], 'asana_url' => $url, 'asana_task_name' => $name],
                'id = ?', [$rowId]
            );
        }
        Response::success(['task' => $task]);
    }

    if ($action === 'unlink' && $method === 'POST') {
        // Einzelne Plan-Zeile von ihrem Asana-Task lösen (Datensatz bleibt, nur Verknüpfung weg)
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $rowId = (int) ($payload['plan_row_id'] ?? 0);
        if (!$rowId) Response::error('plan_row_id erforderlich');
        $rowPlanId = (int) $db->queryValue("SELECT plan_id FROM pp_plan_rows WHERE id = ?", [$rowId]);
        if ($rowPlanId) { require __DIR__ . '/_pp_perm.php'; pp_require($rowPlanId, 'write'); }
        $db->update('pp_plan_rows', [
            'asana_gid' => null, 'asana_url' => null, 'asana_task_name' => null,
        ], 'id = ?', [$rowId]);
        Response::success(['unlinked' => 1]);
    }

    if ($action === 'subtasks' && $method === 'GET') {
        // Unteraufgaben eines Asana-Tasks auflisten und markieren, welche schon als
        // Board-Zeile existieren (Abgleich ueber asana_gid im selben Plan).
        $taskGid = trim((string) ($_GET['task_gid'] ?? ''));
        $planId  = (int) ($_GET['plan_id'] ?? 0);
        if (!$taskGid) Response::error('task_gid fehlt');
        $subs = $asana->getSubtasks($taskGid);
        $existing = [];
        if ($planId) {
            foreach ($db->query(
                "SELECT asana_gid FROM pp_plan_rows WHERE plan_id = ? AND asana_gid IS NOT NULL AND asana_gid <> ''",
                [$planId]
            ) ?: [] as $row) {
                $existing[(string) $row['asana_gid']] = true;
            }
        }
        $out = [];
        foreach ($subs as $s) {
            $gid = (string) ($s['gid'] ?? '');
            if ($gid === '') continue;
            $out[] = [
                'gid'       => $gid,
                'name'      => $s['name'] ?? '',
                'url'       => $s['permalink_url'] ?? ('https://app.asana.com/0/0/' . $gid),
                'completed' => !empty($s['completed']),
                'in_board'  => isset($existing[$gid]),
            ];
        }
        $parent = $asana->getTask($taskGid);
        Response::success([
            'parent_name' => $parent['name'] ?? '',
            'subtasks'    => $out,
            'total'       => count($out),
            'missing'     => count(array_filter($out, fn($x) => !$x['in_board'])),
        ]);
    }

    if ($action === 'import-subtasks' && $method === 'POST') {
        $payload  = json_decode(file_get_contents('php://input'), true) ?: [];
        $planId   = (int) ($payload['plan_id'] ?? 0);
        $parentRowId = (int) ($payload['parent_row_id'] ?? 0);
        $subtasks = $payload['subtasks'] ?? [];
        if (!$planId) Response::error('plan_id fehlt');
        if (!is_array($subtasks) || empty($subtasks)) Response::error('Keine Unteraufgaben ausgewaehlt');
        require __DIR__ . '/_pp_perm.php';
        pp_require($planId, 'write');
        require_once SERVICES_PATH . '/ProjektplannerService.php';
        $svc = new \Services\ProjektplannerService($db);
        $ids = $svc->importAsanaSubtasks($planId, $parentRowId, $subtasks);
        Response::success(['created' => count($ids), 'row_ids' => $ids], count($ids) . ' Unteraufgaben importiert');
    }

    if ($action === 'link' && $method === 'POST') {
        // Bestehenden Task an Plan-Zeile binden
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $rowId = (int) ($payload['plan_row_id'] ?? 0);
        $taskGid = (string) ($payload['task_gid'] ?? '');
        if (!$rowId || !$taskGid) Response::error('plan_row_id und task_gid erforderlich');
        $rowPlanId = (int) $db->queryValue("SELECT plan_id FROM pp_plan_rows WHERE id = ?", [$rowId]);
        if ($rowPlanId) { require __DIR__ . '/_pp_perm.php'; pp_require($rowPlanId, 'write'); }
        $task = $asana->getTask($taskGid);
        if (!$task) Response::error('Task nicht gefunden');
        $url = $task['permalink_url'] ?? ("https://app.asana.com/0/0/$taskGid");
        $db->update('pp_plan_rows', [
            'asana_gid' => $taskGid,
            'asana_url' => $url,
            'asana_task_name' => $task['name'] ?? '',
        ], 'id = ?', [$rowId]);
        Response::success(['task' => $task]);
    }
} catch (\Throwable $e) {
    Response::error('Asana-Fehler: ' . $e->getMessage());
}

Response::error('Methode nicht unterstützt', 405);
