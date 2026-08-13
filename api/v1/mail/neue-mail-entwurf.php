<?php
/** POST /mail/neue-mail-entwurf  { konto_id, stichworte, empfaenger? } -> {betreff, text} */
use Core\Response;
global $db, $input;
$kontoId = (int)($input['konto_id'] ?? 0);
$stich   = trim((string)($input['stichworte'] ?? ''));
if (!$kontoId) Response::error('konto_id fehlt');
if ($stich === '') Response::error('Bitte ein paar Stichworte angeben.');
set_time_limit(120);
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
require_once SERVICES_PATH . '/MailKlassifikationService.php';
try {
    $svc = new \Services\MailKlassifikationService($db);
    Response::success($svc->entwerfeNeueMail($kontoId, $stich, trim((string)($input['empfaenger'] ?? ''))));
} catch (\Throwable $e) { Response::error($e->getMessage()); }
