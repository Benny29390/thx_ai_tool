<?php
/** POST /mail/konto-save Body: { id?, name, email_adresse, ..., imap_password?, smtp_password? } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailKontoService;

if (!Auth::isAdmin()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = !empty($input['id']) ? (int)$input['id'] : null;

require_once SERVICES_PATH . '/MailKontoService.php';
$svc = new MailKontoService(Database::getInstance());

try {
    $newId = $svc->speichereKonto($id, $input);
    Response::success(['id' => $newId, 'neu' => $id === null]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Speichern fehlgeschlagen: ' . $e->getMessage(), 500);
}
