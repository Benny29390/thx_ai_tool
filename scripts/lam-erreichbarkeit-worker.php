<?php
/**
 * lam-erreichbarkeit-worker.php
 *
 * Cron-Worker (alle 5 Min): prueft die naechsten 250 noch ungeprueften Verlinkungen
 * (letzter_http_erreichbar IS NULL) auf HTTP-Erreichbarkeit.
 *
 * Aufruf:
 *   /usr/bin/php /var/www/scripts/lam-erreichbarkeit-worker.php
 *
 * Lockfile verhindert paralleles Laufen (lange Batches ueberschneiden sich sonst).
 * Logs landen in storage/logs/lam-erreichbarkeit.log
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Database.php';

$config = require __DIR__ . '/../config/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

require_once __DIR__ . '/../services/LamService.php';

const BATCH_SIZE  = 250;
const LOCK_FILE   = '/tmp/lam-erreichbarkeit-worker.lock';
const LOCK_MAX_AGE_MIN = 30; // Stale-Lock nach 30 Min wegraeumen

$logFile = STORAGE_PATH . '/logs/lam-erreichbarkeit.log';
$log = function (string $msg) use ($logFile) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    fwrite(STDOUT, $line);
};

// --- Lockfile ---
if (file_exists(LOCK_FILE)) {
    $age = (time() - filemtime(LOCK_FILE)) / 60;
    if ($age < LOCK_MAX_AGE_MIN) {
        $log("Worker laeuft schon (Lock ist " . round($age, 1) . " Min alt) — skip");
        exit(0);
    }
    $log("Stale-Lock " . round($age, 1) . " Min alt — entferne");
    @unlink(LOCK_FILE);
}
@file_put_contents(LOCK_FILE, (string)getmypid());

try {
    $offen = (int)$db->queryValue('SELECT COUNT(*) FROM lam_verlinkungen WHERE letzter_http_erreichbar IS NULL AND geloescht_am IS NULL');
    if ($offen === 0) {
        $log('Keine ungeprueften Verlinkungen — done');
        exit(0);
    }

    $svc = new \Services\LamService($db);
    $log("Starte Batch (max " . BATCH_SIZE . " von $offen offenen)");
    $start = microtime(true);
    $r = $svc->pruefeUngepruefteVerlinkungen(BATCH_SIZE);
    $dauer = round(microtime(true) - $start, 1);
    $log("Fertig in {$dauer}s — geprueft={$r['geprueft']} erreichbar={$r['erreichbar']} tot={$r['tot']} fehler={$r['fehler']}  (rest in queue: " . ($offen - $r['geprueft']) . ')');
} catch (\Throwable $e) {
    $log('ERROR: ' . $e->getMessage());
    exit(1);
} finally {
    @unlink(LOCK_FILE);
}
