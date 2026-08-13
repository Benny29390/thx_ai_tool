<?php
/**
 * GET /lam/aufgaben?status=&typ=&bezug_typ=&bezug_id=
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$filter = [];
foreach (['status', 'typ', 'bezug_typ', 'bezug_id'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->listeAufgaben($filter));
