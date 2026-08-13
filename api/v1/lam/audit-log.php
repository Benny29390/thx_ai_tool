<?php
/** GET /lam/audit-log?entity_typ=&aktion=&user_id=&ab_datum=&nur_bulk= */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$filter = [];
foreach (['entity_typ', 'aktion', 'user_id', 'ab_datum', 'nur_bulk'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->listeAuditEintraege($filter));
