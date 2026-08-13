<?php
/**
 * Cron-Script: Site-Monitor Checks.
 *
 * Wird vom System-Cron alle 2 Minuten aufgerufen. Prüft alle aktiven Monitore,
 * schreibt Logs, verwaltet Incidents + Alert/Recovery-Mails, räumt alte Logs auf,
 * versendet wöchentliche/monatliche Reports.
 *
 * Usage: php /var/www/scripts/pm-check.php [--verbose|-v]
 */

define('BASE_PATH', __DIR__ . '/..');
define('CONFIG_PATH', BASE_PATH . '/config');
define('SERVICES_PATH', BASE_PATH . '/services');
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Crypto.php';
require BASE_PATH . '/core/Settings.php';
require BASE_PATH . '/services/PageMonitorService.php';

$verbose = in_array('--verbose', $argv ?? [], true) || in_array('-v', $argv ?? [], true);

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();
$svc = new \Services\PageMonitorService($db);

$start = microtime(true);
$ids = array_column($db->query("SELECT id FROM pm_monitors WHERE status != 'paused'") ?: [], 'id');
$checks = 0; $down = 0; $alerts = 0; $recoveries = 0;
foreach ($ids as $id) {
    try {
        $r = $svc->runCheck((int) $id);
        $checks += $r['checked'];
        if ($r['is_down']) $down++;
        if ($r['mail_sent'] === 'down') $alerts++;
        if ($r['mail_sent'] === 'recovery') $recoveries++;
        if ($verbose && $r['mail_sent']) echo "Monitor $id: mail={$r['mail_sent']}\n";
    } catch (\Throwable $e) {
        if ($verbose) echo "Monitor $id ERROR: " . $e->getMessage() . "\n";
    }
}

// Reports (täglich 1x intern dedupliziert über Settings-Key)
$report = $svc->sendReports(false);

// Cleanup alle paar Stunden
$lastCleanup = (int) \Core\Settings::get('site_monitor_last_cleanup_ts');
if (time() - $lastCleanup > 6 * 3600) {
    $deletedLogs = $svc->cleanupOldLogs(90);
    \Core\Settings::set('site_monitor_last_cleanup_ts', (string) time());
    if ($verbose) echo "Cleanup: $deletedLogs alte Log-Einträge\n";
}

$duration = round(microtime(true) - $start, 1);
echo sprintf(
    "[%s] PM-Check: %d Monitore, %d Checks, %d down, %d Alerts, %d Recoveries, Reports: %d in %ss\n",
    date('Y-m-d H:i:s'), count($ids), $checks, $down, $alerts, $recoveries, $report['sent'] ?? 0, $duration
);
