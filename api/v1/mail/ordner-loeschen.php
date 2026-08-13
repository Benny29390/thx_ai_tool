<?php
/** POST /mail/ordner-loeschen Body: { id } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
if ($id <= 0) Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());
$svc->loescheOrdner($id);
Response::success(['ok' => true]);
