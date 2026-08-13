<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM_VOKABULAR)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmListenService.php';
$svc = new \Services\CrmListenService($db);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::error('id fehlt', 400);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $l = $svc->detail($id);
    if (!$l) Response::error('Liste nicht gefunden', 404);
    Response::success($l);
}
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $svc->aktualisieren($id, $input, Auth::id());
    Response::success(['ok' => true]);
}
Response::error('Methode nicht erlaubt', 405);
