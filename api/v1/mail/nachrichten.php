<?php
/** GET /mail/nachrichten?konto_id=&suche=&status=&nur_ungelesen=&nur_markiert= */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$filter = [];
foreach (['konto_id', 'richtung', 'status', 'suche', 'lam_anbieter_id', 'lam_massnahme_id', 'absender', 'system_ordner', 'ordner_id'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}
if (!empty($_GET['nur_ungelesen'])) $filter['nur_ungelesen'] = true;
if (!empty($_GET['nur_markiert'])) $filter['nur_markiert'] = true;

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());
Response::success($svc->listeMails($filter));
