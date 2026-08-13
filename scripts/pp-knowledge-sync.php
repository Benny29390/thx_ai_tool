<?php
/**
 * Cron-Worker: Projektplaene -> Wissensdatenbank
 *
 * Laeuft jede Minute. Holt alle Plaene, deren knowledge_dirty=1 und deren
 * knowledge_dirty_since > DEBOUNCE_SECONDS alt ist, und syncronisiert sie
 * mit der Wissensdatenbank (knowledge_documents + knowledge_chunks +
 * knowledge_embeddings + knowledge_entities + knowledge_relations).
 *
 * Plaene ohne customer_id werden geskippt. Plaene mit plan_status='entwurf'
 * werden geskippt (bzw. ihr Doc entfernt wenn vorhanden).
 *
 * Aufruf:
 *   php scripts/pp-knowledge-sync.php
 *   php scripts/pp-knowledge-sync.php --limit=20
 *   php scripts/pp-knowledge-sync.php --plan=76     # gezielt ein Plan
 *   php scripts/pp-knowledge-sync.php --force-all   # alle Plaene markieren
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(function ($class) {
    $ns = ['Core\\' => 'core/', 'Services\\' => 'services/', 'Models\\' => 'models/'];
    foreach ($ns as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

// CLI-Args parsen
$opts = ['limit' => 50, 'plan' => null, 'force_all' => false, 'verbose' => false];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) $opts['limit'] = (int) $m[1];
    elseif (preg_match('/^--plan=(\d+)$/', $a, $m)) $opts['plan'] = (int) $m[1];
    elseif ($a === '--force-all') $opts['force_all'] = true;
    elseif ($a === '-v' || $a === '--verbose') $opts['verbose'] = true;
}

$svc = \Services\PpKnowledgeSyncService::build($db);

// Mode 1: --force-all -> alle Plaene markieren und Cron das natuerlich abarbeiten lassen
if ($opts['force_all']) {
    $cnt = $db->execute('UPDATE pp_plans SET knowledge_dirty = 1, knowledge_dirty_since = NOW() WHERE state = 1');
    echo "[force-all] $cnt Plaene als dirty markiert.\n";
    exit(0);
}

// Mode 2: einzelner Plan
if ($opts['plan']) {
    $r = $svc->syncPlan($opts['plan']);
    echo "[plan {$opts['plan']}] action={$r['action']} doc_id=" . ($r['doc_id'] ?? '-') . " reason=" . ($r['reason'] ?: '-') . "\n";
    exit(0);
}

// Mode 3 (default): alle Dirty-Plaene
$planIds = $svc->findDirtyPlans($opts['limit']);
if (empty($planIds)) {
    if ($opts['verbose']) echo date('Y-m-d H:i:s') . " — nichts zu tun.\n";
    exit(0);
}

$start = microtime(true);
$ok = 0; $skip = 0; $err = 0;
foreach ($planIds as $pid) {
    try {
        $r = $svc->syncPlan((int) $pid);
        if (in_array($r['action'], ['created', 'updated', 'removed'], true)) {
            $ok++;
            echo date('H:i:s') . " plan $pid -> {$r['action']}" . ($r['doc_id'] ? " (doc {$r['doc_id']})" : '') . "\n";
        } else {
            $skip++;
            if ($opts['verbose']) echo date('H:i:s') . " plan $pid -> skip ({$r['reason']})\n";
        }
    } catch (\Throwable $e) {
        $err++;
        echo date('H:i:s') . " plan $pid -> ERROR " . $e->getMessage() . "\n";
        // dirty-Flag belassen, damit der naechste Lauf es nochmal versucht
    }
}
$dur = round(microtime(true) - $start, 1);
echo date('Y-m-d H:i:s') . " — {$ok} sync, {$skip} skip, {$err} err, " . count($planIds) . " gesamt ({$dur}s)\n";
