<?php
/** POST /mail/weiterleiten { mail_id, empfaenger, betreff?, begleittext, cc?, bcc?, anhang_ids? } */
use Core\Auth;
use Core\Response;
global $db, $input;
$mailId = (int)($input['mail_id'] ?? 0);
$empf   = $input['empfaenger'] ?? '';
if (!$mailId) Response::error('mail_id fehlt');
if (empty($empf)) Response::error('Empfänger erforderlich');
require_once SERVICES_PATH . '/MailKontoService.php';
require_once SERVICES_PATH . '/MailAntwortService.php';
try {
    $svc = new \Services\MailAntwortService($db, new \Services\MailKontoService($db));
    $r = $svc->sendeWeiterleitung($mailId, [
        'konto_id'    => (int)($input['konto_id'] ?? 0) ?: null,
        'empfaenger'  => $empf,
        'betreff'     => trim((string)($input['betreff'] ?? '')),
        'begleittext' => (string)($input['begleittext'] ?? ''),
        'cc'          => is_array($input['cc'] ?? null) ? $input['cc'] : [],
        'bcc'         => is_array($input['bcc'] ?? null) ? $input['bcc'] : [],
        'anhang_ids'  => is_array($input['anhang_ids'] ?? null) ? $input['anhang_ids'] : null,
        'user_id'     => Auth::id(),
    ]);
    Response::success($r, 'Weitergeleitet.');
} catch (\Throwable $e) { Response::error($e->getMessage()); }
