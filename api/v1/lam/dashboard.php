<?php
/**
 * LAM Dashboard-Stats API
 * GET /lam/dashboard
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) {
    Response::forbidden();
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Nur GET', 405);
}

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->getDashboardStats());
