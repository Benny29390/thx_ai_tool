<?php
/** POST /mail/weiterleiten-entwurf  { mail_id, stichworte? } -> {text} */
use Core\Response;
global $db, $input;
$mailId = (int)($input['mail_id'] ?? 0);
if (!$mailId) Response::error('mail_id fehlt');
set_time_limit(120);
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
require_once SERVICES_PATH . '/MailKlassifikationService.php';
try {
    $svc = new \Services\MailKlassifikationService($db);
    Response::success($svc->entwerfeWeiterleitung($mailId, trim((string)($input['stichworte'] ?? ''))));
} catch (\Throwable $e) { Response::error($e->getMessage()); }
