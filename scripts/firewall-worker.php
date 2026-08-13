<?php
/**
 * Cron-Worker: Firewall / IP-Sperren.
 *
 * Laeuft als www-data (im CLI ist shell_exec erlaubt, anders als im Web).
 * Aufgaben pro Lauf:
 *   1) aktuellen fail2ban-Stand als Snapshot schreiben (storage/firewall-state.json)
 *      — die Web-Anzeige liest nur diesen Snapshot.
 *   2) offene Entsperr-Auftraege aus firewall_unban_queue abarbeiten
 *      (ueber die sudo-Bruecke /usr/local/bin/ki-fail2ban) und im Audit-Log
 *      protokollieren.
 *
 * Aufruf (Cron, jede Minute): php /var/www/scripts/firewall-worker.php
 */

define('BASE_PATH', __DIR__ . '/..');

require BASE_PATH . '/config/constants.php'; // definiert u.a. CONFIG_PATH
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/AuditLog.php';
require BASE_PATH . '/services/FirewallService.php';

$verbose = in_array('--verbose', $argv ?? [], true) || in_array('-v', $argv ?? [], true);

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);

$svc = new \Services\FirewallService();

// 1) Snapshot schreiben
$ok = $svc->writeSnapshot(time());
if ($verbose) echo $ok ? "Snapshot geschrieben\n" : "Snapshot FEHLGESCHLAGEN (fail2ban erreichbar?)\n";

// 2) Entsperr-Queue abarbeiten
$processed = $svc->processQueue();
foreach ($processed as $p) {
    // Audit-Log: wer hat das Entsperren beauftragt steht in der Queue, hier
    // protokollieren wir die tatsaechliche Ausfuehrung.
    try {
        \Core\AuditLog::record('firewall', $p['ip'], $p['ok'] ? 'unban' : 'unban_failed', [
            'ip'      => $p['ip'],
            'message' => $p['message'],
        ], $p['requested_by']); // actorId explizit — im CLI-Worker ist Core\Auth nicht geladen
    } catch (\Throwable $e) { /* Audit darf nie blockieren */ }
    if ($verbose) echo "Unban {$p['ip']}: " . ($p['ok'] ? 'OK' : 'FEHLER') . " — {$p['message']}\n";
}

if ($verbose) echo "Fertig. " . count($processed) . " Auftrag/Auftraege abgearbeitet.\n";
