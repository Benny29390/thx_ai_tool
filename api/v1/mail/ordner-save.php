<?php
/** POST /mail/ordner-save Body: { id?, name, parent_id?, farbe?, konto_id? } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = isset($input['id']) ? (int)$input['id'] : null;
$name = (string)($input['name'] ?? '');
$parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
$farbe = $input['farbe'] ?? null;
$kontoId = !empty($input['konto_id']) ? (int)$input['konto_id'] : null;
$userId = Auth::user()['id'] ?? null;

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());
try {
    $neuId = $svc->speichereOrdner($id ?: null, $name, $parentId, $farbe, $kontoId, $userId);
    Response::success(['id' => $neuId]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
}
