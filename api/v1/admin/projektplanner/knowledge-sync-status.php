<?php
/**
 * GET  /admin/projektplanner/knowledge-sync-status
 *   Status-Uebersicht des Plan-Knowledge-Syncs fuer das Wissen-Dashboard.
 *
 * POST /admin/projektplanner/knowledge-sync-status?action=mark-all-dirty
 *   Alle aktiven Plaene als sync-pflichtig markieren — Cron arbeitet ab.
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'POST' && $action === 'mark-all-dirty') {
    $cnt = $db->execute('UPDATE pp_plans SET knowledge_dirty = 1, knowledge_dirty_since = NOW() WHERE state = 1');
    Response::success(['marked' => (int) $cnt]);
}

// ===== GET =====
// Counts: synced (state=1, knowledge_doc_id IS NOT NULL),
//         dirty (state=1, knowledge_dirty=1),
//         skipped (state=1, customer_id IS NULL OR plan_status='entwurf')
$synced = (int) $db->queryValue(
    "SELECT COUNT(*) FROM pp_plans WHERE state = 1 AND knowledge_doc_id IS NOT NULL"
);
$dirty = (int) $db->queryValue(
    "SELECT COUNT(*) FROM pp_plans WHERE state = 1 AND knowledge_dirty = 1"
);
$skipped = (int) $db->queryValue(
    "SELECT COUNT(*) FROM pp_plans WHERE state = 1 AND (customer_id IS NULL OR plan_status = 'entwurf')"
);

$lastSync = $db->queryValue(
    "SELECT MAX(knowledge_synced_at) FROM pp_plans WHERE knowledge_synced_at IS NOT NULL"
);

// Pläne-Liste (nur state=1)
$plans = $db->query(
    "SELECT p.id AS plan_id, p.title, p.plan_status, p.customer_id, p.knowledge_doc_id AS doc_id,
            p.knowledge_dirty AS dirty, p.knowledge_synced_at AS synced_at,
            c.abbreviation AS customer_abbr, c.name AS customer_name,
            (SELECT COUNT(*) FROM knowledge_chunks WHERE document_id = p.knowledge_doc_id) AS chunks_count
     FROM pp_plans p
     LEFT JOIN customers c ON c.id = p.customer_id
     WHERE p.state = 1
     ORDER BY p.knowledge_synced_at IS NULL, p.knowledge_synced_at DESC, p.id DESC
     LIMIT 100"
) ?: [];

// Mit Status-Label anreichern
foreach ($plans as &$p) {
    if (empty($p['customer_id']) || $p['plan_status'] === 'entwurf') {
        $p['status'] = 'skipped';
    } elseif ((int) $p['dirty'] === 1) {
        $p['status'] = 'dirty';
    } elseif ($p['doc_id']) {
        $p['status'] = 'synced';
    } else {
        $p['status'] = 'never';
    }
    $p['synced_rel'] = $p['synced_at'] ? relTime($p['synced_at']) : null;
    $p['chunks_count'] = (int) $p['chunks_count'];
}
unset($p);

Response::success([
    'counts' => [
        'synced' => $synced,
        'dirty' => $dirty,
        'skipped' => $skipped,
    ],
    'last_sync_at' => $lastSync,
    'last_sync_rel' => $lastSync ? relTime($lastSync) : null,
    'plans' => $plans,
]);

function relTime(string $t): string {
    $sec = max(1, time() - strtotime($t));
    if ($sec < 60) return 'gerade eben';
    if ($sec < 3600) return 'vor ' . (int) round($sec / 60) . ' Min';
    if ($sec < 86400) return 'vor ' . (int) round($sec / 3600) . ' Std';
    if ($sec < 2592000) return 'vor ' . (int) round($sec / 86400) . ' Tagen';
    return 'vor ' . (int) round($sec / 2592000) . ' Mon';
}
