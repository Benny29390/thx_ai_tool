#!/usr/bin/env php
<?php
/**
 * Website-Sync Cron-Entrypoint.
 *
 * Fällige Website-Sync-Jobs in die JobQueue einreihen. Der eigentliche Worker
 * (cli/worker.php) verarbeitet sie dann.
 *
 * Cron-Empfehlung: einmal täglich
 *   0 3 * * * php /var/www/cli/website-sync.php >> /var/log/website-sync.log 2>&1
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
echo "[{$nowStr}] Website-Sync-Cron gestartet\n";

// OpenAI-Key Pflicht (für Embeddings)
$keyRow = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
if (empty($keyRow['setting_value'])) {
    echo "[{$nowStr}] OpenAI API-Key nicht konfiguriert — abgebrochen\n";
    exit(0);
}

require_once SERVICES_PATH . '/JobQueue.php';
$queue = new \Services\JobQueue($db);

$customers = $db->query("SELECT id, name, settings FROM customers WHERE is_active = 1") ?: [];

$queued = 0;
foreach ($customers as $customer) {
    $settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
    $cfg = $settings['website_crawl'] ?? [];

    if (empty($cfg['sync_enabled']) || empty($cfg['start_url'])) {
        continue;
    }

    $intervalDays = max(1, (int) ($cfg['sync_interval_days'] ?? 60));
    $lastSync = $cfg['last_sync_at'] ?? null;

    $isDue = true;
    if ($lastSync) {
        try {
            $lastDt = new DateTime($lastSync);
            $diffDays = ($now->getTimestamp() - $lastDt->getTimestamp()) / 86400;
            $isDue = $diffDays >= $intervalDays;
        } catch (\Exception $e) {}
    }
    if (!$isDue) continue;

    // Bereits ein Job in der Queue?
    $existing = $db->queryOne(
        "SELECT id FROM generation_jobs
         WHERE customer_id = ? AND job_type = 'website_sync' AND status IN ('pending','processing')
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
            'job_type' => 'website_sync',
            'topic' => 'Website-Sync (cron) für ' . $customer['name'],
            'priority' => 3,
            'max_attempts' => 2,
        ]);
        echo "[{$nowStr}] Customer {$customer['id']} ({$customer['name']}): Job #{$jobId} erstellt\n";
        $queued++;
    } catch (\Exception $e) {
        echo "[{$nowStr}] Customer {$customer['id']}: Fehler — " . $e->getMessage() . "\n";
    }
}

echo "[{$nowStr}] Website-Sync-Cron fertig — {$queued} Jobs eingereiht\n";
