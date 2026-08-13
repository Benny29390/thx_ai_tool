<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmTagService.php';
$svc = new \Services\CrmTagService($db);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::success(['tags' => $svc->liste($_GET['suche'] ?? null)]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sofort-Anlegen aus der Detail-Combobox bewusst niedrigschwellig: jeder CRM-User darf Tags anlegen.
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $id = $svc->anlegen((string)($input['name'] ?? ''), $input['farbe'] ?? null, $input['beschreibung'] ?? null, Auth::id());
        Response::success(['id' => $id]);
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}
Response::error('Methode nicht erlaubt', 405);
