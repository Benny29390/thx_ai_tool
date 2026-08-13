<?php
/**
 * Muster-Verwaltung.
 *  GET    /api/v1/lam/aufraeum-muster?customer_id=X[&nur_bestaetigt=1|0]
 *  POST   /api/v1/lam/aufraeum-muster   Body: { customer_id, muster_typ, muster_value,
 *                                                aktion_linkart, aktion_strategie?,
 *                                                aktion_empfehlung?, beschreibung?,
 *                                                ursprungs_domain?, ursprungs_notiz?,
 *                                                herkunft? }
 *  DELETE /api/v1/lam/aufraeum-muster?id=X&customer_id=Y
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
    $nur = null;
    if (isset($_GET['nur_bestaetigt'])) {
        $nur = $_GET['nur_bestaetigt'] === '1';
    }
    Response::success(['muster' => $svc->listeMuster($customerId, $nur)]);
}

if ($method === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);
    $customerId = (int)($json['customer_id'] ?? 0);
    $typ        = (string)($json['muster_typ'] ?? '');
    $wert       = trim((string)($json['muster_value'] ?? ''));
    $linkart    = $json['aktion_linkart'] ?? null;
    if ($customerId <= 0 || $typ === '' || $wert === '' || !$linkart) {
        Response::error('customer_id + muster_typ + muster_value + aktion_linkart erforderlich', 400);
    }
    try {
        $id = $svc->speichereMuster(
            $customerId,
            [
                'muster_typ'        => $typ,
                'muster_value'      => $wert,
                'aktion_linkart'    => $linkart,
                'aktion_strategie'  => $json['aktion_strategie']  ?? null,
                'aktion_empfehlung' => $json['aktion_empfehlung'] ?? null,
                'beschreibung'      => $json['beschreibung']      ?? null,
            ],
            Auth::id(),
            (string)($json['herkunft']         ?? 'manuell'),
            isset($json['ursprungs_domain'])   ? (string)$json['ursprungs_domain']   : null,
            isset($json['ursprungs_notiz'])    ? (string)$json['ursprungs_notiz']    : null
        );
        Response::success(['id' => $id]);
    } catch (\Throwable $e) {
        Response::error('Speichern fehlgeschlagen: ' . $e->getMessage(), 500);
    }
}

if ($method === 'DELETE') {
    $id         = (int)($_GET['id'] ?? 0);
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($id <= 0 || $customerId <= 0) Response::error('id + customer_id erforderlich', 400);
    $ok = $svc->loescheMuster($id, $customerId);
    Response::success(['geloescht' => $ok]);
}

Response::error('Nur GET, POST oder DELETE', 405);
