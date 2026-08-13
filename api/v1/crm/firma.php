<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmFirmaService.php';
$svc = new \Services\CrmFirmaService($db);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::error('id fehlt', 400);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $f = $svc->detail($id);
    if (!$f) Response::error('Firma nicht gefunden', 404);
    Response::success($f);
}
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $svc->aktualisieren($id, $input, Auth::id());
    Response::success(['ok' => true]);
}
if ($method === 'DELETE') { $svc->softDelete($id, Auth::id()); Response::success(['ok' => true]); }
Response::error('Methode nicht erlaubt', 405);
