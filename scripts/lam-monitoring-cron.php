<?php
/**
 * LAM Auto-Monitoring-Cron.
 *
 * Läuft täglich. Iteriert über alle live-Maßnahmen, die NICHT muted sind,
 * eine veroeffentlichungs_url haben und nicht gelöscht sind. Führt
 * fuehreMonitoringCheckAus() pro Maßnahme aus.
 *
 * Optionen:
 *   --limit=N      max N Maßnahmen pro Lauf (Default: alle)
 *   --dry-run      nur ausgeben, was geprüft würde
 *
 * Aufruf:
 *   /usr/bin/php /var/www/scripts/lam-monitoring-cron.php
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Settings.php';
require_once __DIR__ . '/../core/Crypto.php';
require_once __DIR__ . '/../services/LamService.php';

define('SERVICES_PATH', __DIR__ . '/../services');

$config = require __DIR__ . '/../config/config.php';
$db = \Core\Database::getInstance($config['db']);

// CLI-Optionen
$opts = getopt('', ['limit::', 'dry-run']);
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 0;
$dryRun = isset($opts['dry-run']);

$startZeit = microtime(true);
$start = date('Y-m-d H:i:s');
echo "[$start] LAM-Monitoring-Cron gestartet" . ($dryRun ? ' (dry-run)' : '') . "\n";

// Vor dem Lauf: Mute mit abgelaufenem Datum automatisch deaktivieren
$db->execute(
    "UPDATE lam_massnahmen
     SET monitoring_muted = 0, monitoring_stumm_bis = NULL
     WHERE monitoring_muted = 1
       AND monitoring_stumm_bis IS NOT NULL
       AND monitoring_stumm_bis < CURDATE()"
);

// Intervall aus Settings, Whitelist
$intervall = (int)\Core\Settings::get('lam_monitoring_intervall_minuten', 1440);
$erlaubt = [15, 30, 60, 120, 360, 720, 1440];
if (!in_array($intervall, $erlaubt, true)) $intervall = 1440;
echo "Konfiguriertes Intervall: {$intervall} Minuten\n";

// Nur Maßnahmen prüfen, deren letzter Check älter als das Intervall ist
$sql = "SELECT m.id, m.veroeffentlichungs_url, d.url AS domain_url, c.abbreviation AS kuerzel
        FROM lam_massnahmen m
        JOIN lam_domains d ON d.id = m.domain_id
        LEFT JOIN customers c ON c.id = m.customer_id
        LEFT JOIN (
            SELECT massnahme_id, MAX(zeitpunkt) AS letzter
            FROM lam_monitoring_checks
            GROUP BY massnahme_id
        ) lc ON lc.massnahme_id = m.id
        WHERE m.status = 'live'
          AND m.monitoring_muted = 0
          AND m.geloescht_am IS NULL
          AND m.veroeffentlichungs_url IS NOT NULL
          AND m.veroeffentlichungs_url <> ''
          AND (lc.letzter IS NULL OR lc.letzter < (NOW() - INTERVAL {$intervall} MINUTE))
        ORDER BY lc.letzter IS NULL DESC, lc.letzter ASC
        LIMIT 200";
// $limit überschreibt die Default-Limit-200 nur, wenn CLI-Option gesetzt ist
if ($limit > 0) $sql = preg_replace('/LIMIT \d+$/', 'LIMIT ' . $limit, $sql);

$massnahmen = $db->query($sql);
$gesamt = count($massnahmen);
echo "Gefundene Maßnahmen: $gesamt\n";

if ($dryRun) {
    foreach ($massnahmen as $m) {
        echo " - {$m['kuerzel']} | {$m['domain_url']} → {$m['veroeffentlichungs_url']}\n";
    }
    echo "[Dry-Run] Beendet.\n";
    exit(0);
}

$svc = new \Services\LamService($db);

$ok = 0; $fehler = 0; $alerts = 0;
foreach ($massnahmen as $m) {
    try {
        $r = $svc->fuehreMonitoringCheckAus($m['id']);
        $ok++;
        if (!empty($r['alert'])) $alerts++;
    } catch (\Throwable $e) {
        $fehler++;
        echo "  FEHLER bei {$m['id']} ({$m['domain_url']}): " . $e->getMessage() . "\n";
    }
    // 200ms Pause, um nicht alle Server gleichzeitig zu hämmern
    usleep(200_000);
}

$dauer = round(microtime(true) - $startZeit, 1);
$ende = date('Y-m-d H:i:s');
echo "[$ende] Fertig: ok=$ok, fehler=$fehler, neue_alerts=$alerts, dauer={$dauer}s\n";
