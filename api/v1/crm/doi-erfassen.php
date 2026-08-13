<?php
use Core\Auth; use Core\Database; use Core\Response; use Core\Settings;
if (!Auth::can(CAP_CRM)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim((string)($input['email'] ?? ''));
$quelle = trim((string)($input['quelle'] ?? 'manuell'));
$listen = is_array($input['listen_ids'] ?? null) ? array_map('intval', $input['listen_ids']) : [];
if ($email === '') Response::error('E-Mail Pflicht');

$apiKey = (string)Settings::get('brevo_api_key', '');
if ($apiKey === '') Response::error('Brevo-API-Key fehlt');

require_once SERVICES_PATH . '/CrmBrevoService.php';
require_once SERVICES_PATH . '/CrmKontaktService.php';
require_once SERVICES_PATH . '/CrmDoiService.php';

$db = Database::getInstance();
$svc = new \Services\CrmDoiService($db, new \Services\CrmKontaktService($db), new \Services\CrmBrevoService($apiKey));

try {
    $res = $svc->erfassen($email, $quelle, $listen, $input['text'] ?? null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
    Response::success($res, 'DOI-Erfassung gestartet — Bestätigungsmail wurde gesendet');
} catch (\Throwable $e) { Response::error($e->getMessage()); }
