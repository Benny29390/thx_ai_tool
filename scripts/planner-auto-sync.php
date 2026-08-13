<?php
/**
 * Tagesplan Auto-Sync — läuft per Cron alle 10 Min.
 * Pro User mit gültigem Asana-PAT:
 *   1. Sync (zieht neue Tasks + erkennt modifizierte → setzt ai_summary=NULL für Re-Analyse)
 *   2. KI-Analyse für alle Tasks ohne ai_summary (inkl. Asana-Kommentare via Stories)
 *   3. Auto-Slot: konservatives Einordnen neuer Tasks
 *      - Quick + ASAP → 'today'
 *      - Significance >= 9 + Deadline heute/morgen → 'today'
 *      - mid/long-term per Deadline
 *   4. Score-Recalc
 *
 * Aufruf: php /var/www/scripts/planner-auto-sync.php
 * Optional: --user=<id> nur für diesen User
 * Optional: --verbose
 */

require_once __DIR__ . '/../config/constants.php';

spl_autoload_register(function ($class) {
    $namespaces = ['Core\\' => 'core/', 'Models\\' => 'models/', 'Services\\' => 'services/'];
    foreach ($namespaces as $ns => $dir) {
        if (strpos($class, $ns) === 0) {
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$config = require __DIR__ . '/../config/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$opts = getopt('', ['user::', 'verbose']);
$verbose = isset($opts['verbose']);
$onlyUser = isset($opts['user']) ? (int)$opts['user'] : null;

function vlog(string $msg, bool $verbose): void
{
    if ($verbose) echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

require_once __DIR__ . '/../services/PlannerSyncService.php';
require_once __DIR__ . '/../services/PlannerEffortAiService.php';
require_once __DIR__ . '/../services/PlannerScoreService.php';

$where = "asana_user_pat IS NOT NULL AND asana_user_pat <> '' AND is_active = 1";
$params = [];
if ($onlyUser) { $where .= " AND id = ?"; $params[] = $onlyUser; }
$users = $db->query("SELECT id, email FROM users WHERE $where", $params) ?: [];

$totalStats = ['users' => 0, 'new' => 0, 'changed' => 0, 'analyzed' => 0, 'pushed_today' => 0];

foreach ($users as $u) {
    $uid = (int)$u['id'];
    vlog("User #{$uid} ({$u['email']}) — start", $verbose);

    // 1. Sync
    try {
        $sync = new \Services\PlannerSyncService($db);
        $syncStats = $sync->syncForUser($uid);
        vlog(sprintf("  sync: seen=%d new=%d changed=%d", $syncStats['tasks_seen'] ?? 0, $syncStats['tasks_created'] ?? 0, $syncStats['tasks_changed_in_asana'] ?? 0), $verbose);
        $totalStats['new'] += $syncStats['tasks_created'] ?? 0;
        $totalStats['changed'] += $syncStats['tasks_changed_in_asana'] ?? 0;
    } catch (\Throwable $e) {
        vlog("  sync failed: " . $e->getMessage(), $verbose);
        continue;
    }

    // 2. KI-Analyse für offene + nicht-analysierte Tasks. Mehrere Durchläufe weil pro Lauf max 30 Tasks.
    $svc = new \Services\PlannerEffortAiService($db);
    $rounds = 0;
    while ($rounds < 5) {
        try {
            $r = $svc->estimateMissingForUser($uid);
            $est = (int)($r['estimated'] ?? 0);
            $failed = (int)($r['failed'] ?? 0);
            $totalStats['analyzed'] += $est;
            if ($est === 0 && $failed === 0) break;
            vlog("  analyze round " . ($rounds+1) . ": estimated={$est}, failed={$failed}", $verbose);
        } catch (\Throwable $e) {
            vlog("  analyze failed: " . $e->getMessage(), $verbose);
            break;
        }
        $rounds++;
    }

    // 2b. Backfill: Aktivitätstyp für schon-analysierte Tasks nachklassifizieren.
    // Schlanker als die volle Re-Analyse: nur Titel+Summary, kein Asana-Stories-Round-Trip.
    // Pro Cron-Lauf bis zu 3 Runden a 60 Tasks → ~180 Tasks/Lauf, also nach ~1-2 Stunden komplett.
    $backfillRounds = 0;
    while ($backfillRounds < 3) {
        try {
            $bc = $svc->classifyActivitiesForUser($uid, 60);
            $count = (int)($bc['classified'] ?? 0);
            $batch = (int)($bc['batch_size'] ?? 0);
            if (!isset($totalStats['activities_classified'])) $totalStats['activities_classified'] = 0;
            $totalStats['activities_classified'] += $count;
            if ($batch === 0) break;
            vlog("  classify-activity round " . ($backfillRounds+1) . ": classified={$count}/{$batch}", $verbose);
        } catch (\Throwable $e) {
            vlog("  classify-activity failed: " . $e->getMessage(), $verbose);
            break;
        }
        $backfillRounds++;
    }

    // 3. Zeitraum-Buckets materialisieren (daily_slot = fristBucket(due_on) fuer nicht-gepinnte Tasks)
    try {
        $dist = $sync->recomputeBuckets($uid);
        if (!empty($dist)) {
            $parts = [];
            foreach ($dist as $k => $v) $parts[] = "{$k}={$v}";
            vlog("  buckets: " . implode(' ', $parts), $verbose);
        }
        $totalStats['pushed_today'] += $dist['today'] ?? 0;
    } catch (\Throwable $e) {
        vlog("  recompute-buckets failed: " . $e->getMessage(), $verbose);
    }

    // 4. Score
    try {
        (new \Services\PlannerScoreService($db))->scoreAll($uid);
    } catch (\Throwable $e) {
        vlog("  score failed: " . $e->getMessage(), $verbose);
    }

    $totalStats['users']++;
}

vlog(sprintf(
    "FERTIG: %d User · %d neu · %d geändert · %d KI-analysiert · %d Aktivität-klassifiziert · %d in Heute geschoben",
    $totalStats['users'], $totalStats['new'], $totalStats['changed'], $totalStats['analyzed'],
    $totalStats['activities_classified'] ?? 0, $totalStats['pushed_today']
), $verbose || count($users) > 0);

// Cleanup-Pass: Tasks, die vor mehr als 3 Monaten erledigt wurden, aus der DB löschen.
// Sie stehen weiter in Asana zur Verfügung, brauchen aber den Tagesplaner nicht zu belasten.
// Lauft max 1x pro Stunde via /tmp/planner-cleanup.lock (touch + mtime-Check).
$lockFile = '/tmp/planner-cleanup.lock';
$shouldCleanup = !file_exists($lockFile) || (time() - filemtime($lockFile)) >= 3600;
if ($shouldCleanup) {
    $cutoff = date('Y-m-d', strtotime('-3 months'));
    try {
        $db->execute(
            "DELETE FROM planner_tasks
             WHERE completed_at_asana IS NOT NULL
               AND completed_at_asana < ?
               AND (completed_at_local IS NULL OR completed_at_local < ?)",
            [$cutoff, $cutoff]
        );
        $deleted = (int)$db->queryValue("SELECT ROW_COUNT()");
        @touch($lockFile);
        if ($deleted > 0) vlog("Cleanup: {$deleted} Tasks älter als {$cutoff} entfernt", $verbose || count($users) > 0);
    } catch (\Throwable $e) {
        vlog("Cleanup failed: " . $e->getMessage(), $verbose);
    }
}
