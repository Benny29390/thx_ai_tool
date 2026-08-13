<?php
/**
 * Dashboard-Stats API
 * GET /admin/projektplanner/dashboard?date_from=&date_to=&status=&customer_id=
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PpDashboardService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/PpDashboardService.php';
$svc = new PpDashboardService(Database::getInstance());

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

if (($_GET['action'] ?? '') === 'my-open-tasks') {
    $u = Auth::user();
    if (!$u) Response::error('Nicht eingeloggt');
    $tasks = $svc->getOpenTasksFor((string)($u['name'] ?? ''));
    Response::success(['tasks' => $tasks, 'user' => ['name' => $u['name']]]);
}

$filter = [];
if (!empty($_GET['date_from'])) $filter['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filter['date_to'] = $_GET['date_to'];
if (!empty($_GET['status'])) $filter['status'] = $_GET['status'];
if (!empty($_GET['customer_id'])) $filter['customer_id'] = (int) $_GET['customer_id'];

Response::success($svc->getStats($filter));
