<?php
/** POST /mail/konto-loeschen Body: { id } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailKontoService;

if (!Auth::isAdmin()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
if ($id <= 0) Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/MailKontoService.php';
$svc = new MailKontoService(Database::getInstance());
$svc->loescheKonto($id);
Response::success(['ok' => true]);
