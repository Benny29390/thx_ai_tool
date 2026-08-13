<?php
/**
 * Projektplanner — Revisionen / Snapshots
 *
 * GET    /admin/projektplanner/plans/{id}/revisions               Liste
 * POST   /admin/projektplanner/plans/{id}/revisions               Snapshot anlegen (Body: { label? })
 * POST   /admin/projektplanner/plans/{id}/revisions/{rid}/restore Restore auf Snapshot
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\ProjektplannerService;

require_once SERVICES_PATH . '/ProjektplannerService.php';
require __DIR__ . '/_pp_perm.php';
$svc = new ProjektplannerService(Database::getInstance());
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];
$planId = (int) ($_GET['plan_id'] ?? 0);
$revId = (int) ($_GET['revision_id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$planId) Response::error('plan_id fehlt');

// Lesen: read; Snapshot/Restore: write
$needPerm = ($method === 'GET') ? 'read' : 'write';
pp_require($planId, $needPerm);

if ($method === 'GET') {
    Response::success(['revisions' => $svc->listRevisions($planId)]);
}

if ($method === 'POST' && $revId === 0) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $label = trim((string) ($payload['label'] ?? '')) ?: null;
    try {
        $id = $svc->createRevision($planId, $userId, $label);
        Response::success(['id' => $id], 'Snapshot erstellt');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($method === 'POST' && $revId > 0 && $action === 'restore') {
    try {
        $svc->restoreRevision($revId, $userId);
        Response::success(['ok' => true], 'Plan wiederhergestellt');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

Response::error('Methode nicht unterstützt', 405);
