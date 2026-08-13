<?php
/** Kunden-Websites verwalten (pm_monitors als einzige Quelle). Zugriff im Handler geprueft. */
use Core\Auth;
use Core\Database;
use Core\Response;

require_once SERVICES_PATH . '/PageMonitorService.php';

$db     = Database::getInstance();
$cid    = (int) ($_GET['customer_id'] ?? 0);
$userId = (int) Auth::id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$svc    = new \Services\PageMonitorService($db);

if (!$db->queryOne("SELECT id FROM customers WHERE id = ?", [$cid])) Response::notFound('Kunde nicht gefunden');

// Liste
if ($method === 'GET' && empty($_GET['ws_action'])) {
    Response::success($svc->websitesForCustomer($cid));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

// Aktion auf einer bestehenden Website
if (!empty($_GET['ws_action'])) {
    $mid = (int) ($_GET['monitor_id'] ?? 0);
    // Tenant: Monitor muss zu diesem Kunden gehoeren
    if (!$db->queryOne("SELECT id FROM pm_monitors WHERE id = ? AND customer_id = ?", [$mid, $cid])) Response::notFound('Website nicht gefunden');
    switch ($_GET['ws_action']) {
        case 'monitoring':
            $on = !empty($input['on']);
            $svc->setMonitoring($cid, $mid, $on);
            Response::success(['id' => $mid, 'monitoring' => $on], $on ? 'Monitoring aktiviert' : 'Monitoring deaktiviert');
        case 'primary':
            $svc->setPrimaryWebsite($cid, $mid);
            Response::success(['id' => $mid, 'is_primary' => true], 'Als Hauptseite gesetzt');
        case 'delete':
            $svc->removeWebsite($cid, $mid);
            Response::success(['id' => $mid], 'Website entfernt');
    }
    Response::error('Unbekannte Aktion');
}

// Neue Website
if ($method === 'POST') {
    try {
        $id = $svc->addWebsiteForCustomer($cid, (string)($input['url'] ?? ''), (string)($input['label'] ?? ''), !empty($input['monitor']), $userId);
        Response::success(['id' => $id], 'Website hinzugefügt');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

Response::error('Method not allowed', 405);
