<?php
/** Nächtlicher Reconciliation-Cron: holt Brevo-Kontakte der letzten 24h (Fallback wenn Webhook was verpasst hat). */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Session.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Crypto.php';
require BASE_PATH . '/core/Settings.php';
require BASE_PATH . '/core/Auth.php';
require BASE_PATH . '/core/AuditLog.php';
require BASE_PATH . '/services/CrmBrevoService.php';
require BASE_PATH . '/services/CrmKontaktService.php';
require BASE_PATH . '/services/CrmMigrationService.php';
$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();
$key = (string)\Core\Settings::get('brevo_api_key', '');
if ($key === '') { echo "Kein Brevo-Key\n"; exit(0); }
$brevo = new \Services\CrmBrevoService($key);
$svc = new \Services\CrmMigrationService($db, $brevo, new \Services\CrmKontaktService($db));
$runId = $svc->starteLauf('delta');
echo "[crm-reconciliation] Run $runId · Delta\n";
try {
    $counter = $svc->importiereKontakte($runId, 'delta');
    $svc->beendeLauf($runId, 'ok', $counter);
    echo "OK · " . json_encode($counter) . "\n";
} catch (\Throwable $e) {
    $svc->beendeLauf($runId, 'error', [], $e->getMessage());
    fwrite(STDERR, "Fehler: " . $e->getMessage() . "\n");
}
