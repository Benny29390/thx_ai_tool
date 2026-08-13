#!/usr/bin/env php
<?php
/**
 * qdrant-backfill.php — Initialer/resumabler Backfill aller knowledge_chunks ins
 * parallele Wissen-V2 (bge-m3 + Qdrant). Idempotent: bereits 'synced' Chunks werden
 * uebersprungen. Fehlerhafte Batches werden als 'error' markiert (mit --retry erneut).
 *
 * Aufruf:
 *   php /var/www/cli/qdrant-backfill.php [--batch=100] [--limit=0] [--retry]
 *     --batch   Chunks pro Embedding/Upsert-Batch (Default 100)
 *     --limit   max. Chunks in diesem Lauf (0 = alle)
 *     --retry   vorab alle 'error'-Zeilen auf 'pending' zuruecksetzen
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/config/constants.php';
spl_autoload_register(function ($class) {
    foreach (['Core\\' => 'core/', 'Services\\' => 'services/'] as $n => $d) {
        if (strpos($class, $n) === 0) {
            $f = ROOT_PATH . '/' . $d . str_replace('\\', '/', substr($class, strlen($n))) . '.php';
            if (file_exists($f)) { require_once $f; return; }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
$db = \Core\Database::getInstance($config['db']);

$opts  = getopt('', ['batch::', 'limit::', 'retry']);
$batch = max(1, (int)($opts['batch'] ?? 100));
$limit = (int)($opts['limit'] ?? 0);

function out(string $m): void { fwrite(STDOUT, '[' . date('H:i:s') . '] ' . $m . "\n"); }

$sync = \Services\QdrantSyncService::fromSettings($db);
if (!$sync->qdrant()->health()) {
    out('FEHLER: Qdrant nicht erreichbar (qdrant_url pruefen / Container laeuft?). Abbruch.');
    exit(1);
}
$sync->ensure();
out('Collection bereit: ' . $sync->qdrant()->getCollection());

// Worklist seeden (idempotent — nur neue Chunks kommen hinzu)
$db->execute("INSERT IGNORE INTO kb_qdrant_sync (chunk_id, document_id, customer_id, status)
              SELECT id, document_id, customer_id, 'pending' FROM knowledge_chunks");

if (isset($opts['retry'])) {
    $db->execute("UPDATE kb_qdrant_sync SET status='pending' WHERE status='error'");
    out("'error'-Zeilen auf 'pending' zurueckgesetzt");
}

$total = (int)$db->queryValue("SELECT COUNT(*) FROM kb_qdrant_sync");
$done0 = (int)$db->queryValue("SELECT COUNT(*) FROM kb_qdrant_sync WHERE status='synced'");
out("Chunks gesamt: $total, bereits synced: $done0");

$processed = 0;
while (true) {
    if ($limit > 0 && $processed >= $limit) break;

    $rows = $db->query(
        "SELECT s.chunk_id AS id, c.document_id, c.customer_id, c.content
         FROM kb_qdrant_sync s
         JOIN knowledge_chunks c ON c.id = s.chunk_id
         WHERE s.status = 'pending'
         ORDER BY s.chunk_id
         LIMIT ?",
        [$batch]
    );
    if (empty($rows)) break;

    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    try {
        $n = $sync->syncChunkRows($rows);
        $processed += count($rows);
        $synced = (int)$db->queryValue("SELECT COUNT(*) FROM kb_qdrant_sync WHERE status='synced'");
        out("Batch ok (+$n) — synced $synced / $total");
    } catch (\Throwable $e) {
        // Batch als 'error' markieren (faellt aus dem 'pending'-Pool -> Lauf terminiert).
        $sync->markError($ids, $e->getMessage());
        out('Batch FEHLER (' . count($ids) . ' Chunks -> error): ' . $e->getMessage());
    }
}

$syncedF = (int)$db->queryValue("SELECT COUNT(*) FROM kb_qdrant_sync WHERE status='synced'");
$errF    = (int)$db->queryValue("SELECT COUNT(*) FROM kb_qdrant_sync WHERE status='error'");
out("FERTIG. synced=$syncedF error=$errF total=$total");
out('Qdrant Punkte: ' . $sync->qdrant()->pointsCount() . ' · Embedding-Tokens: ' . $sync->tokensUsed());
if ($errF > 0) out("Hinweis: $errF Fehler — erneut mit --retry laufen lassen.");
