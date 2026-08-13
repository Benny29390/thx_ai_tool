<?php
/** GET /mail/ordner?konto_id= — Liste System- + manueller Ordner mit Counts */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$kontoId = !empty($_GET['konto_id']) ? (int)$_GET['konto_id'] : null;

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());
Response::success($svc->ordnerBaum($kontoId));
