<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmFirmaService.php';
$svc = new \Services\CrmFirmaService($db);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['branchen'])) { Response::success(['branchen' => $svc->branchenMitAnzahl()]); }
    $filter = array_filter([
        'suche'   => $_GET['suche'] ?? null,
        'branche' => $_GET['branche'] ?? null,
        'mit_kontakten'   => !empty($_GET['mit_kontakten']),
        'ohne_kontakte'   => !empty($_GET['ohne_kontakte']),
        'mit_zoho_legacy' => !empty($_GET['mit_zoho_legacy']),
        'sort'    => $_GET['sort'] ?? null,
        'order'   => $_GET['order'] ?? null,
        'limit'   => $_GET['limit'] ?? 50,
        'offset'  => $_GET['offset'] ?? 0,
    ], fn($v) => $v !== null && $v !== '' && $v !== []);
    Response::success($svc->liste($filter));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    try { Response::success(['id' => $svc->anlegen($input, Auth::id())], 'Firma angelegt'); }
    catch (\Throwable $e) { Response::error($e->getMessage()); }
}
Response::error('Methode nicht erlaubt', 405);
