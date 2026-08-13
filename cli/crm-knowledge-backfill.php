<?php
/**
 * crm-knowledge-backfill.php — Initialer Backfill aller CRM-Kontakte + Firmen
 * in die Wissensdatenbank V2 (Qdrant). Idempotent: bereits gleiche Hash-Dokumente
 * werden uebersprungen (kein Re-Embedding).
 *
 * Aufruf:
 *   php cli/crm-knowledge-backfill.php [--kontakte] [--firmen] [--limit=N] [--from=ID] [--force]
 *
 *   --kontakte      Nur Kontakte syncen (default: beides)
 *   --firmen        Nur Firmen syncen
 *   --limit=N       Max. N Entities pro Lauf (Default: alles)
 *   --from=ID       Start-ID (zum Resumen)
 *   --force         Auch unveraenderte Docs neu schreiben (Hash-Check umgehen)
 *   --dry-run       Nur zaehlen, nichts schreiben
 */

#!/usr/bin/env php
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);
ini_set('memory_limit', '512M');

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

// Args
$opts = [];
foreach ($argv as $a) {
    if (preg_match('/^--(\w+)(?:=(.*))?$/', $a, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}
$doKontakte = !isset($opts['firmen']) || isset($opts['kontakte']);
$doFirmen = !isset($opts['kontakte']) || isset($opts['firmen']);
$limit = isset($opts['limit']) ? (int) $opts['limit'] : 0;
$fromId = isset($opts['from']) ? (int) $opts['from'] : 0;
$force = !empty($opts['force']);
$dryRun = !empty($opts['dry-run']);

$config = require CONFIG_PATH . '/config.php';
$db = Database::getInstance($config['db']);
$svc = new CrmKnowledgeSyncService($db, new CrmKontaktService($db), new CrmFirmaService($db));

function nowStr(): string { return date('H:i:s'); }
function fmtNum(int $n): string { return number_format($n, 0, ',', '.'); }

echo "[" . nowStr() . "] CRM-Backfill-Start\n";
echo "  Kontakte: " . ($doKontakte ? 'JA' : 'nein') . " | Firmen: " . ($doFirmen ? 'JA' : 'nein') . "\n";
echo "  Limit: " . ($limit ?: 'kein') . " | From-ID: " . ($fromId ?: '0') . "\n";
echo "  Force: " . ($force ? 'JA' : 'nein') . " | Dry-Run: " . ($dryRun ? 'JA' : 'nein') . "\n\n";

$totalProcessed = 0;
$totalSkipped = 0;
$totalErrors = 0;
$startTime = time();

// ─────────────── FIRMEN ZUERST (Kontakte referenzieren Firmen) ───────────────
if ($doFirmen) {
    echo "[" . nowStr() . "] === FIRMEN ===\n";
    $whereLimit = $limit > 0 ? "LIMIT $limit" : '';
    $firmen = $db->query(
        "SELECT id FROM crm_firmen WHERE geloescht_am IS NULL AND id >= ? ORDER BY id ASC $whereLimit",
        [$fromId]
    );
    $count = count($firmen);
    echo "  $count Firmen zu verarbeiten\n";
    foreach ($firmen as $i => $f) {
        $id = (int) $f['id'];
        try {
            if ($dryRun) {
                $totalProcessed++;
            } else {
                if ($force) {
                    // Cache-Bust: alten Hash invalidieren damit upsert garantiert neu schreibt
                    $db->execute(
                        "UPDATE knowledge_documents SET content_hash = '' WHERE source_type = 'crm_firma' AND external_id = ?",
                        ['firma:' . $id]
                    );
                }
                $docId = $svc->syncFirma($id);
                if ($docId !== null) $totalProcessed++; else $totalSkipped++;
            }
        } catch (\Throwable $e) {
            $totalErrors++;
            echo "  ✗ Firma $id: " . $e->getMessage() . "\n";
        }
        if (($i + 1) % 100 === 0 || $i === $count - 1) {
            $pct = round(($i + 1) / max($count, 1) * 100, 1);
            $elapsed = time() - $startTime;
            $rate = $elapsed > 0 ? round(($i + 1) / $elapsed, 1) : 0;
            echo "  [" . nowStr() . "] Firma " . ($i + 1) . "/$count ($pct%) — $rate/s — letzte ID: $id\n";
        }
    }
}

// ─────────────── KONTAKTE ───────────────
if ($doKontakte) {
    echo "\n[" . nowStr() . "] === KONTAKTE ===\n";
    $whereLimit = $limit > 0 ? "LIMIT $limit" : '';
    $kontakte = $db->query(
        "SELECT id FROM crm_kontakte WHERE geloescht_am IS NULL AND id >= ? ORDER BY id ASC $whereLimit",
        [$fromId]
    );
    $count = count($kontakte);
    echo "  $count Kontakte zu verarbeiten\n";
    foreach ($kontakte as $i => $k) {
        $id = (int) $k['id'];
        try {
            if ($dryRun) {
                $totalProcessed++;
            } else {
                if ($force) {
                    $db->execute(
                        "UPDATE knowledge_documents SET content_hash = '' WHERE source_type = 'crm_kontakt' AND external_id = ?",
                        ['kontakt:' . $id]
                    );
                }
                $docId = $svc->syncKontakt($id);
                if ($docId !== null) $totalProcessed++; else $totalSkipped++;
            }
        } catch (\Throwable $e) {
            $totalErrors++;
            echo "  ✗ Kontakt $id: " . $e->getMessage() . "\n";
        }
        if (($i + 1) % 100 === 0 || $i === $count - 1) {
            $pct = round(($i + 1) / max($count, 1) * 100, 1);
            $elapsed = time() - $startTime;
            $rate = $elapsed > 0 ? round(($i + 1) / $elapsed, 1) : 0;
            echo "  [" . nowStr() . "] Kontakt " . ($i + 1) . "/$count ($pct%) — $rate/s — letzte ID: $id\n";
        }
    }
}

$elapsed = time() - $startTime;
echo "\n[" . nowStr() . "] === FERTIG ===\n";
echo "  Processed: " . fmtNum($totalProcessed) . "\n";
echo "  Skipped:   " . fmtNum($totalSkipped) . "\n";
echo "  Errors:    " . fmtNum($totalErrors) . "\n";
echo "  Elapsed:   {$elapsed}s\n";
echo "\nQdrant-Embeddings werden vom qdrant_sync-Worker im Hintergrund nachgezogen.\n";
echo "Status: SELECT status, COUNT(*) FROM generation_jobs WHERE job_type='qdrant_sync' GROUP BY status;\n";
