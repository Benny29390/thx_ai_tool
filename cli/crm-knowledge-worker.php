<?php
/**
 * crm-knowledge-worker.php — Verarbeitet die crm_sync_queue.
 *
 * Wird per Cron alle 60s aufgerufen. Holt Eintraege deren letzte Aenderung
 * mindestens $debounce Sekunden zurueck liegt (Debounce-Schutz gegen Spam
 * waehrend Inline-Edit-Sessions) und synct sie via CrmKnowledgeSyncService.
 *
 * Aufruf (Cron):
 *   * * * * * www-data /usr/bin/php /var/www/cli/crm-knowledge-worker.php >> /var/log/crm-knowledge.log 2>&1
 *
 * Manueller Test:
 *   php cli/crm-knowledge-worker.php [--debounce=30] [--batch=50] [--verbose]
 */

#!/usr/bin/env php
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(120);

require_once dirname(__DIR__) . '/config/constants.php';
spl_autoload_register(function ($class) {
    foreach (['Core\\' => 'core/', 'Services\\' => 'services/'] as $n => $d) {
        if (strpos($class, $n) === 0) {
            $f = ROOT_PATH . '/' . $d . str_replace('\\', '/', substr($class, strlen($n))) . '.php';
            if (file_exists($f)) { require_once $f; return; }
        }
    }
});

use Core\Database;
use Services\CrmKnowledgeSyncService;
use Services\CrmKontaktService;
use Services\CrmFirmaService;

$opts = [];
foreach ($argv as $a) {
    if (preg_match('/^--(\w+)(?:=(.*))?$/', $a, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}
$debounce = isset($opts['debounce']) ? (int) $opts['debounce'] : 30;
$batch = isset($opts['batch']) ? (int) $opts['batch'] : 50;
$verbose = !empty($opts['verbose']);

// Verhindere Parallel-Lauf (Lock-File)
// Wichtig: 666-Perms damit sowohl root (manueller Lauf) als auch www-data (Cron)
// das gleiche File benutzen koennen — sonst staut sich die Queue.
$lockFile = '/tmp/crm-knowledge-worker.lock';
$lockExisted = file_exists($lockFile);
$fp = @fopen($lockFile, 'c');
if (!$fp) {
    error_log("crm-knowledge-worker: fopen($lockFile) fehlgeschlagen — " .
        "User=" . (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : '?') .
        " Lock-Owner=" . (file_exists($lockFile) ? (function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($lockFile))['name'] ?? '?') : '?') : '(none)'));
    exit(1);
}
if (!$lockExisted) @chmod($lockFile, 0666);
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    if ($verbose) echo "[" . date('H:i:s') . "] Worker laeuft bereits (Lock vorhanden)\n";
    exit(0);
}

try {
    $config = require CONFIG_PATH . '/config.php';
    $db = Database::getInstance($config['db']);
    $svc = new CrmKnowledgeSyncService($db, new CrmKontaktService($db), new CrmFirmaService($db));

    $queueSize = (int) $db->queryValue("SELECT COUNT(*) FROM crm_sync_queue");
    if ($queueSize === 0) {
        if ($verbose) echo "[" . date('H:i:s') . "] Queue leer\n";
        exit(0);
    }

    $result = $svc->processQueue($debounce, $batch);
    if ($verbose || $result['processed'] > 0 || $result['errors'] > 0) {
        echo "[" . date('H:i:s') . "] processed={$result['processed']} errors={$result['errors']} queueSize_before=$queueSize\n";
    }

    // Stuck-Jobs: wenn attempts >= 5, periodisch warnen
    $stuck = $db->query(
        "SELECT entity_typ, entity_id, attempts, last_error
         FROM crm_sync_queue WHERE attempts >= 5 LIMIT 10"
    );
    if ($stuck) {
        echo "[" . date('H:i:s') . "] WARN: " . count($stuck) . " stuck jobs (>=5 attempts):\n";
        foreach ($stuck as $s) {
            echo "  - {$s['entity_typ']}#{$s['entity_id']}: " . substr($s['last_error'] ?? '', 0, 120) . "\n";
        }
    }
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}
