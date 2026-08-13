<?php
/** POST /mail/pull Body: { konto_id? } — manuell IMAP-Pull triggern */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailImapService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$kontoId = !empty($input['konto_id']) ? (int)$input['konto_id'] : null;

@set_time_limit(180);

require_once SERVICES_PATH . '/MailImapService.php';
$svc = new MailImapService(Database::getInstance());
$r = $svc->pullAlle('manuell', $kontoId);
Response::success($r);
