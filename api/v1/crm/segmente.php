<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmSegmentService.php';
$svc = new \Services\CrmSegmentService($db);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::success(['segmente' => $svc->liste(Auth::id(), Auth::isAdmin() || Auth::isManager())]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    try { Response::success(['id' => $svc->speichern($input, Auth::id())]); }
    catch (\Throwable $e) { Response::error($e->getMessage()); }
}
Response::error('Methode nicht erlaubt', 405);
