<?php
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$filter = [];
foreach (['suche', 'status', 'customer_id', 'domain_id', 'sonderstatus', 'vorgangstyp', 'sort'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}
if (isset($_GET['limit']))  $filter['limit']  = (int)$_GET['limit'];
if (isset($_GET['offset'])) $filter['offset'] = (int)$_GET['offset'];

Response::success($svc->listeMassnahmen($filter));
