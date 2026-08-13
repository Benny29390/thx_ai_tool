<?php
/**
 * LAM Linkquellen-Liste API
 * GET /lam/linkquellen?suche=&verifikation_status[]=&limit=&offset=&sort=&order=
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

$filter = [];
foreach (['suche', 'anbieter_id', 'customer_id', 'sort', 'order'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}
if (!empty($_GET['verifikation_status'])) {
    $filter['verifikation_status'] = (array) $_GET['verifikation_status'];
}
foreach (['nur_disqualifiziert', 'ohne_anbieter', 'ohne_kunde', 'ohne_si', 'ohne_dp', 'nur_nicht_erreichbar', 'nur_ungeprueft', 'nur_in_wartezeit', 'nur_verfuegbar'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = true;
}
if (!empty($_GET['linkart'])) $filter['linkart'] = (array)$_GET['linkart'];
if (!empty($_GET['tag_ids'])) $filter['tag_ids'] = (array)$_GET['tag_ids'];
foreach (['si_min', 'si_max', 'preis_min', 'preis_max'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $filter[$k] = $_GET[$k];
}
if (isset($_GET['limit'])) $filter['limit'] = (int) $_GET['limit'];
if (isset($_GET['offset'])) $filter['offset'] = (int) $_GET['offset'];

Response::success($svc->listeLinkquellen($filter));
