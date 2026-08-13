<?php
use Core\Database; use Core\Settings;
$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{32,128}$/', $token)) {
    http_response_code(400); echo 'Token ungueltig'; exit;
}
require_once SERVICES_PATH . '/CrmBrevoService.php';
require_once SERVICES_PATH . '/CrmKontaktService.php';
require_once SERVICES_PATH . '/CrmDoiService.php';
$db = \Core\Database::getInstance();
$apiKey = (string)Settings::get('brevo_api_key', '');
$svc = new \Services\CrmDoiService($db, new \Services\CrmKontaktService($db), new \Services\CrmBrevoService($apiKey));
try {
    $svc->widerruf($token);
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body style="font-family:sans-serif;text-align:center;padding:60px;"><h1>Abgemeldet</h1><p>Du erhältst keine weiteren Mails von uns. Schade, dass Du gehst!</p></body></html>';
} catch (\Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(400);
    echo '<html><body style="font-family:sans-serif;text-align:center;padding:60px;"><h1>Fehler</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
}
