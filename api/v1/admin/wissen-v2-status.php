<?php
/**
 * GET /api/v1/admin/wissen-v2-status — Status des parallelen Wissen-V2-Systems
 * (Qdrant-Erreichbarkeit, Collection-Punkte, Backfill-Fortschritt, offene Sync-Jobs).
 * Admin-only (in handler.php erzwungen).
 */

use Core\Response;
use Core\Settings;

$db = \Core\Database::getInstance();

// Backfill-Fortschritt aus kb_qdrant_sync
$progress = ['pending' => 0, 'synced' => 0, 'error' => 0];
try {
    foreach ($db->query("SELECT status, COUNT(*) AS n FROM kb_qdrant_sync GROUP BY status") as $r) {
        $progress[$r['status']] = (int) $r['n'];
    }
} catch (\Throwable $e) { /* Tabelle evtl. noch nicht migriert */ }

$totalChunks = (int) $db->queryValue("SELECT COUNT(*) FROM knowledge_chunks");
$openJobs = (int) $db->queryValue(
    "SELECT COUNT(*) FROM generation_jobs WHERE job_type = 'qdrant_sync' AND status IN ('pending','processing')"
);

// Qdrant-Status
$qdrantReachable = false;
$points = null;
$collStatus = null;
try {
    require_once SERVICES_PATH . '/QdrantClient.php';
    require_once SERVICES_PATH . '/LocalEmbeddingService.php';
    require_once SERVICES_PATH . '/QdrantSyncService.php';
    $sync = \Services\QdrantSyncService::fromSettings($db);
    $q = $sync->qdrant();
    $qdrantReachable = $q->health();
    if ($qdrantReachable) {
        try {
            $info = $q->collectionInfo();
            $points = (int) ($info['points_count'] ?? 0);
            $collStatus = $info['status'] ?? null;
        } catch (\Throwable $e) { $points = null; }
    }
} catch (\Throwable $e) {
    $qdrantReachable = false;
}

Response::success([
    'qdrant' => [
        'url'        => Settings::get('qdrant_url', 'http://localhost:6333'),
        'reachable'  => $qdrantReachable,
        'collection' => 'knowledge_bge_m3',
        'points'     => $points,
        'status'     => $collStatus,
    ],
    'embedding' => [
        'url'     => Settings::get('embedding_local_url', ''),
        'model'   => Settings::get('embedding_local_model', 'bge-m3'),
        'dim'     => (int) (Settings::get('embedding_local_dim', '1024') ?: 1024),
        'key_set' => (Settings::get('local_api_key', '') ?? '') !== '',
    ],
    'backfill' => [
        'total_chunks' => $totalChunks,
        'tracked'      => $progress['pending'] + $progress['synced'] + $progress['error'],
        'synced'       => $progress['synced'],
        'pending'      => $progress['pending'],
        'error'        => $progress['error'],
    ],
    'sync_jobs_open' => $openJobs,
]);
