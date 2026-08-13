<?php
/** GET /mail/personen-sicht?konto_id= */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$kontoId = !empty($_GET['konto_id']) ? (int)$_GET['konto_id'] : null;

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());
Response::success($svc->personenSicht($kontoId));
