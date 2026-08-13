<?php
/**
 * transkription-worker.php — Cron-Dispatcher fuer Transkriptions-Jobs.
 *
 * Aufruf (Cron, alle 1–2 Minuten):
 *     /usr/bin/php /var/www/scripts/transkription-worker.php
 *
 * Verhalten pro Lauf:
 *   1) Stale-Locks aufraeumen: running-Jobs ohne Lebenszeichen (>30 Min ohne
 *      finished_at) bekommen den Lock zurueckgegeben (status=failed).
 *   2) Aktuelle running-Jobs zaehlen. Wenn < MAX_PARALLEL, freie Slots fuellen.
 *   3) Pro queued Job: per UPDATE atomar auf 'running' lockieren und
 *      transkription-process-job.php als Hintergrundprozess starten
 *      (nohup, getrennter Output-Log pro Job).
 *
 * Concurrency ist absichtlich konservativ (3): jeder faster-whisper-Lauf
 * zieht je nach Modell 2–8 GB RAM und mehrere CPU-Kerne.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Database.php';

$config = require __DIR__ . '/../config/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

const MAX_PARALLEL = 3;
const STALE_AFTER_MIN = 30;

$log = function(string $msg) {
    $line = '[' . date('Y-m-d H:i:s') . "] [worker] $msg\n";
    @file_put_contents(STORAGE_PATH . '/logs/transkription.log', $line, FILE_APPEND);
    fwrite(STDOUT, $line);
};

// --- 1) Stale-Locks aufraeumen ---
$stale = $db->execute(
    "UPDATE tr_jobs
     SET status='failed', finished_at=NOW(),
         error_message=CONCAT('Worker-Timeout nach ', ?, ' Min')
     WHERE status='running'
       AND started_at IS NOT NULL
       AND started_at < (NOW() - INTERVAL ? MINUTE)",
    [STALE_AFTER_MIN, STALE_AFTER_MIN]
);
if ($stale > 0) {
    $log("Stale-Locks gefreed: $stale");
}

// --- 2) Freie Slots ermitteln ---
$running = (int)$db->queryValue("SELECT COUNT(*) FROM tr_jobs WHERE status='running'");
$free = MAX_PARALLEL - $running;
if ($free <= 0) {
    $log("Volle Slots ($running/" . MAX_PARALLEL . "), nichts zu tun");
    exit(0);
}

// --- 3) Queued Jobs holen + locken ---
//     next_retry_at NULL = sofort verarbeiten, sonst nur wenn faellig
$queued = $db->query(
    "SELECT id FROM tr_jobs
     WHERE status='queued' AND (next_retry_at IS NULL OR next_retry_at <= NOW())
     ORDER BY created_at ASC LIMIT ?",
    [$free]
);
if (!$queued) {
    exit(0);
}

$started = 0;
$logsDir = STORAGE_PATH . '/logs';
if (!is_dir($logsDir)) @mkdir($logsDir, 0775, true);

foreach ($queued as $row) {
    $jobId = (int)$row['id'];

    // Atomic lock: nur wenn noch 'queued'
    $locked = $db->execute(
        "UPDATE tr_jobs SET status='running', started_at=NOW(), progress_pct=5
         WHERE id=? AND status='queued'",
        [$jobId]
    );
    if ($locked !== 1) {
        $log("Job $jobId — Lock verloren (race), skip");
        continue;
    }

    // Hintergrundprozess starten
    $logFile = $logsDir . '/transkription-job-' . $jobId . '.log';
    $cmd = sprintf(
        'nohup /usr/bin/php %s %d > %s 2>&1 &',
        escapeshellarg(__DIR__ . '/transkription-process-job.php'),
        $jobId,
        escapeshellarg($logFile)
    );
    exec($cmd);
    $started++;
    $log("Job $jobId gestartet (Log: $logFile)");
}

$log("Fertig: $started gestartet, $running liefen bereits");
