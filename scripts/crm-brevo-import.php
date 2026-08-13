<?php
/**
 * CRM-Brevo-Import-Worker.
 * Wird vom Webrequest gestartet (shell_exec & im Hintergrund).
 *
 * Usage: php scripts/crm-brevo-import.php --run=42 --modus=full
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';  // setzt ROOT_PATH, CONFIG_PATH, SERVICES_PATH, etc. + Caps
require BASE_PATH . '/core/Session.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Crypto.php';
require BASE_PATH . '/core/Settings.php';
require BASE_PATH . '/core/Auth.php';         // wird von AuditLog::record erwartet
require BASE_PATH . '/core/AuditLog.php';
require BASE_PATH . '/services/CrmBrevoService.php';
require BASE_PATH . '/services/CrmKontaktService.php';
require BASE_PATH . '/services/CrmMigrationService.php';

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$opts = getopt('', ['run::','modus::']);
$runId = (int)($opts['run'] ?? 0);
$modus = $opts['modus'] ?? 'full';
if ($runId <= 0) {
    fwrite(STDERR, "Run-ID fehlt\n");
    exit(1);
}

$apiKey = (string)\Core\Settings::get('brevo_api_key', '');
if ($apiKey === '') {
    $db->execute("UPDATE crm_migrations_runs SET status='error', beendet_am=NOW(), fehler_text=? WHERE id=?",
        ['Brevo-API-Key nicht gesetzt', $runId]);
    exit(1);
}

$brevo = new \Services\CrmBrevoService($apiKey);
$kontaktSvc = new \Services\CrmKontaktService($db);
$svc = new \Services\CrmMigrationService($db, $brevo, $kontaktSvc);

try {
    echo "[crm-brevo-import] Run $runId · Modus $modus\n";
    echo "[crm-brevo-import] Synchronisiere Listen …\n";
    $listResult = $svc->synchronisiereListen();
    echo "[crm-brevo-import] Listen: " . json_encode($listResult) . "\n";

    echo "[crm-brevo-import] Importiere Kontakte …\n";
    $counter = $svc->importiereKontakte($runId, $modus);
    echo "[crm-brevo-import] Counter: " . json_encode($counter) . "\n";

    $svc->beendeLauf($runId, 'ok', $counter);
    echo "[crm-brevo-import] Fertig.\n";
} catch (\Throwable $e) {
    $svc->beendeLauf($runId, 'error', [], $e->getMessage());
    fwrite(STDERR, "Fehler: " . $e->getMessage() . "\n");
    exit(1);
}
