<?php
use Core\Auth; use Core\Database; use Core\Response; use Core\Settings;
if (!Auth::can(CAP_CRM_MIGRATION)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$modus = in_array($input['modus'] ?? 'full', ['full','delta'], true) ? $input['modus'] : 'full';

$apiKey = (string)Settings::get('brevo_api_key', '');
if ($apiKey === '') Response::error('Brevo-API-Key nicht gesetzt — in den Einstellungen unter Brevo eintragen.');

require_once SERVICES_PATH . '/CrmBrevoService.php';
require_once SERVICES_PATH . '/CrmKontaktService.php';
require_once SERVICES_PATH . '/CrmMigrationService.php';

$db = Database::getInstance();
$svc = new \Services\CrmMigrationService($db, new \Services\CrmBrevoService($apiKey), new \Services\CrmKontaktService($db));

$runId = $svc->starteLauf($modus, Auth::id());

// Kein Long-Running im Webrequest — Worker triggern via Background-Prozess
// nohup + setsid sorgen dafuer, dass der Prozess auch nach dem Webrequest-Ende weiterlaeuft.
$logFile = '/var/www/storage/logs/crm-import-run' . $runId . '.log';
$cmd = sprintf(
    'nohup /usr/bin/php %s/scripts/crm-brevo-import.php --run=%d --modus=%s > %s 2>&1 &',
    escapeshellarg(ROOT_PATH), $runId, escapeshellarg($modus), escapeshellarg($logFile)
);
shell_exec($cmd);

Response::success(['run_id' => $runId, 'status' => 'gestartet'], 'Migration läuft im Hintergrund');
