<?php
/** POST /mail/klassifizieren Body: { id } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailKlassifikationService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
if ($id <= 0) Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/MailKlassifikationService.php';
$svc = new MailKlassifikationService(Database::getInstance());

try {
    Response::success($svc->klassifiziereMail($id));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Klassifikation fehlgeschlagen: ' . $e->getMessage(), 500);
}
