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
foreach (['suche', 'typ', 'anbieter_id'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}

Response::success($svc->listeKorrespondenz($filter));
