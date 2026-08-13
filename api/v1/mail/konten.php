<?php
/** GET /mail/konten — Liste aller Konten (ohne Passwörter) */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailKontoService;

if (!Auth::isAdmin()) Response::forbidden();

require_once SERVICES_PATH . '/MailKontoService.php';
$svc = new MailKontoService(Database::getInstance());
Response::success($svc->listeKonten());
