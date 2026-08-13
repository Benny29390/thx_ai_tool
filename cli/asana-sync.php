#!/usr/bin/env php
<?php
/**
 * Asana Sync Cron-Entrypoint.
 *
 * Faellige Sync-Jobs in die JobQueue einreihen. Der eigentliche Worker (cli/worker.php)
 * verarbeitet sie dann.
 *
 * Cron: 0 *\/4 * * * php /var/www/cli/asana-sync.php >> /var/log/asana-sync.log 2>&1
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config/constants.php';

spl_autoload_register(function ($class) {
    $namespaces = ['Core\\' => 'core/', 'Services\\' => 'services/'];
    foreach ($namespaces as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($namespace))) . '.php';
            if (file_exists($file)) require_once $file;
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
$db = \Core\Database::getInstance($config['db']);

$now = new DateTime();
$nowStr = $now->format('Y-m-d H:i:s');

echo "[{$nowStr}] Asana-Sync-Cron gestartet\n";

// Pruefen, ob PAT konfiguriert ist
$patRow = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = 'asana_pat'");
if (empty($patRow['setting_value'])) {
    echo "[{$nowStr}] Asana PAT nicht konfiguriert — abgebrochen\n";
    exit(0);
}

require_once SERVICES_PATH . '/JobQueue.php';
$queue = new \Services\JobQueue($db);

// Alle aktiven Kunden mit aktiviertem Asana-Sync
$customers = $db->query(
    "SELECT id, name, settings FROM customers WHERE is_active = 1"
) ?: [];

$queued = 0;
foreach ($customers as $customer) {
    $settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
    $asanaCfg = $settings['asana'] ?? [];

    if (empty($asanaCfg['sync_enabled']) || empty($asanaCfg['project_gids'])) {
        continue;
    }

    $intervalHours = max(1, (int) ($asanaCfg['sync_interval_hours'] ?? 4));
    $lastSync = $asanaCfg['last_sync_at'] ?? null;

    $isDue = true;
    if ($lastSync) {
        try {
            $lastDt = new DateTime($lastSync);
            $diffHours = ($now->getTimestamp() - $lastDt->getTimestamp()) / 3600;
            $isDue = $diffHours >= $intervalHours;
        } catch (\Exception $e) {}
    }

    if (!$isDue) continue;

    // Pruefen ob bereits ein pending/processing Job existiert
    $existing = $db->queryOne(
        "SELECT id FROM generation_jobs
         WHERE customer_id = ? AND job_type = 'asana_sync' AND status IN ('pending','processing')
         LIMIT 1",
        [$customer['id']]
    );
    if ($existing) {
        echo "[{$nowStr}] Customer {$customer['id']} ({$customer['name']}): bereits ein Job in Queue (#{$existing['id']})\n";
        continue;
    }

    try {
        $jobId = $queue->createJob([
            'customer_id' => (int) $customer['id'],
            'user_id' => 1, // System
            'job_type' => 'asana_sync',
            'topic' => 'Asana-Sync (cron) fuer ' . $customer['name'],
            'priority' => 3,
            'max_attempts' => 2,
        ]);
        echo "[{$nowStr}] Customer {$customer['id']} ({$customer['name']}): Job #{$jobId} erstellt\n";
        $queued++;
    } catch (\Exception $e) {
        echo "[{$nowStr}] Customer {$customer['id']}: Fehler — " . $e->getMessage() . "\n";
    }
}

echo "[{$nowStr}] Asana-Sync-Cron beendet — {$queued} Jobs eingereiht\n";
