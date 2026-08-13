<?php
/** POST /mail/konto-test Body: { id, typ: 'imap'|'smtp' } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailKontoService;

if (!Auth::isAdmin()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
$typ = (string)($input['typ'] ?? '');
if ($id <= 0 || !in_array($typ, ['imap', 'smtp'], true)) {
    Response::error('id + typ (imap|smtp) erforderlich', 400);
}

require_once SERVICES_PATH . '/MailKontoService.php';
$svc = new MailKontoService(Database::getInstance());

try {
    $r = $typ === 'imap' ? $svc->testIMAP($id) : $svc->testSMTP($id);
    Response::success($r);
} catch (\Throwable $e) {
    Response::error('Test fehlgeschlagen: ' . $e->getMessage(), 500);
}
