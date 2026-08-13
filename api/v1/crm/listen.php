<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmListenService.php';
$svc = new \Services\CrmListenService($db);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::success(['listen' => $svc->liste(!empty($_GET['inkl_archiviert']))]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::can(CAP_CRM_VOKABULAR)) Response::forbidden();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $id = $svc->anlegen((string)($input['name'] ?? ''), $input['brevo_list_id'] ?? null, $input['beschreibung'] ?? null, Auth::id());
        Response::success(['id' => $id]);
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}
Response::error('Methode nicht erlaubt', 405);
