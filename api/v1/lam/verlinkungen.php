<?php
/**
 * LAM Verlinkungs-Liste API (pro Kunde)
 * GET /lam/verlinkungen?customer_id=&suche=&linkart=&empfehlung=&status=&...
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

$customerId = (int) ($_GET['customer_id'] ?? 0);
if ($customerId <= 0) {
    Response::error('customer_id fehlt oder ungueltig', 400);
}

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$filter = [];
foreach (['suche', 'follow'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}
// Multi-Select-Filter (Arrays)
foreach (['linkart', 'empfehlung', 'importquelle'] as $k) {
    if (!empty($_GET[$k])) {
        $filter[$k] = is_array($_GET[$k]) ? $_GET[$k] : [$_GET[$k]];
    }
}
foreach (['nur_neu', 'nur_topp', 'nur_ohne_linkart', 'ohne_empfehlung', 'ohne_linktext', 'ohne_ziel_url',
          'ohne_bemerkung', 'nur_link_verloren', 'ohne_si', 'ohne_dp', 'nicht_erreichbar'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = true;
}
if (isset($_GET['limit'])) $filter['limit'] = (int) $_GET['limit'];
if (isset($_GET['offset'])) $filter['offset'] = (int) $_GET['offset'];
if (!empty($_GET['sort']))  $filter['sort']  = (string) $_GET['sort'];
if (!empty($_GET['order'])) $filter['order'] = (string) $_GET['order'];

Response::success($svc->listeVerlinkungen($customerId, $filter));
