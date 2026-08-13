<?php
/**
 * Projektplanner — Plan-Permissions API
 *
 * GET    /admin/projektplanner/plans/{id}/shares          Liste der User-Shares
 * POST   /admin/projektplanner/plans/{id}/shares          Body: { user_id, permission }
 * DELETE /admin/projektplanner/plans/{id}/shares/{uid}    User-Share entfernen
 * GET    /admin/projektplanner/users-for-share            Verfügbare User (für Dropdown)
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\ProjektplannerService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/ProjektplannerService.php';
$svc = new ProjektplannerService(Database::getInstance());
$db = Database::getInstance();
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];
$planId = (int) ($_GET['plan_id'] ?? 0);
$shareUserId = (int) ($_GET['share_user_id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($action === 'users') {
    // Aktive User, die noch keinen Share auf diesem Plan haben
    if (!$planId) {
        $rows = $db->query("SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name ASC") ?: [];
    } else {
        $rows = $db->query(
            "SELECT u.id, u.name, u.email
             FROM users u
             LEFT JOIN pp_plan_shares s ON s.user_id = u.id AND s.plan_id = ?
             WHERE u.is_active = 1 AND s.id IS NULL
             ORDER BY u.name ASC",
            [$planId]
        ) ?: [];
    }
    Response::success(['users' => $rows]);
}

if ($planId > 0 && $method === 'GET') {
    Response::success(['shares' => $svc->listShares($planId)]);
}

if ($planId > 0 && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $svc->setShare($planId, (int) $payload['user_id'], $payload['permission'] ?? 'read');
        Response::success(['ok' => true], 'Freigabe gespeichert');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($planId > 0 && $shareUserId > 0 && $method === 'DELETE') {
    $svc->removeShare($planId, $shareUserId);
    Response::success(['ok' => true], 'Freigabe entfernt');
}

Response::error('Methode nicht unterstützt', 405);
