<?php
/**
 * Einmaliger Import der versendeten Reportings (2023-2025) in die Wissensdatenbank.
 *
 * Quelle:  docs/reportings/{JAHR}/{JAHR-MONAT}/{KUERZEL}-Reporting_{YYYY-MM}.xlsx
 * Ergebnis: pro Reporting EIN knowledge_document (source_type=reporting) mit
 *           1 Chunk pro Aufgabe (gleiches Format wie Projektplaene) + Volltext
 *           als original_content. Nur Kunden, die aktuell aktiv sind (is_active=1),
 *           werden verarbeitet; ehemalige Kunden werden uebersprungen.
 *
 * Aufruf:
 *   php scripts/reportings-import.php --customer=PHS          # nur ein Kunde (Test)
 *   php scripts/reportings-import.php --all                   # alle aktiven Kunden
 *   php scripts/reportings-import.php --all --dry-run         # nur anzeigen, nichts schreiben
 *
 * Idempotent: bestehende Reporting-Docs werden per external_id vorab entfernt und
 * frisch angelegt (delete-first), damit kein fixes Re-Chunking greift.
 */

require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(function ($c) {
    $map = ["Core\\" => "core/", "Services\\" => "services/"];
    foreach ($map as $p => $d) {
        if (strpos($c, $p) === 0) {
            $f = __DIR__ . '/../' . $d . str_replace("\\", "/", substr($c, strlen($p))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});

use Core\Database;
use Core\Settings;
use Services\AIService;
use Services\KnowledgeExtractionService;
use Services\KnowledgeIngestService;
use Services\QdrantSyncService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$cfg = require CONFIG_PATH . '/config.php';
Database::getInstance($cfg['db']);
$db = Database::getInstance();

// ---- Args ----
$only = null; $all = false; $dry = false;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--customer=(.+)$/', $a, $m)) $only = mb_strtoupper(trim($m[1]));
    elseif ($a === '--all') $all = true;
    elseif ($a === '--dry-run') $dry = true;
}
if (!$only && !$all) { fwrite(STDERR, "Bitte --customer=XXX oder --all angeben.\n"); exit(1); }

// ---- Aktive Kunden (Kuerzel -> id/name) ----
$active = [];
foreach ($db->query("SELECT id, name, abbreviation FROM customers WHERE is_active = 1 AND abbreviation IS NOT NULL AND abbreviation <> ''") as $c) {
    $active[mb_strtoupper($c['abbreviation'])] = ['id' => (int) $c['id'], 'name' => $c['name']];
}

// ---- Dateien einsammeln ----
$base = realpath(__DIR__ . '/../docs/reportings');
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'xlsx') continue;          // nur xlsx
    if (strpos($f->getFilename(), '~$') === 0) continue;             // Excel-Lock-Tempdatei
    $files[] = $f->getPathname();
}
sort($files);

$openaiKey = (string) Settings::get('openai_api_key');
if ($openaiKey === '') { fwrite(STDERR, "OpenAI-API-Key fehlt — LLM-Extraktion nicht moeglich.\n"); exit(1); }
$ingest = new KnowledgeIngestService($db, null, new KnowledgeExtractionService(new AIService($openaiKey, 'openai')));
$qd = $dry ? null : QdrantSyncService::fromSettings($db);

// Kuerzel-Aliase: Vorlaeufer-/Alt-Kuerzel auf den heutigen aktiven Kunden mappen.
// SRE ist der Vorlaeufer von PWP (PlayWork.pro) -> als PWP erfassen.
$aliases = ['SRE' => 'PWP'];

$stats = ['files' => 0, 'imported' => 0, 'skipped_inactive' => 0, 'skipped_unparsable' => 0, 'chunks' => 0, 'embedded' => 0, 'summaries' => 0];
$skippedKuerzel = [];
$digestByCustomer = [];   // Hebel 2: pro Kunde eine Historien-Uebersicht aus Kompakt-Digests

foreach ($files as $path) {
    $stats['files']++;
    $stem = pathinfo($path, PATHINFO_FILENAME);           // z.B. PHS-Reporting_2025-06
    // Kuerzel = fuehrende Buchstaben (inkl. Umlaute/&) vor - oder Leerzeichen
    if (!preg_match('/^([\p{L}&]+)/u', $stem, $mk)) { $stats['skipped_unparsable']++; continue; }
    $origKuerzel = mb_strtoupper($mk[1]);
    $kuerzel = $aliases[$origKuerzel] ?? $origKuerzel;   // Vorlaeufer-Kuerzel auf aktiven Kunden mappen

    if ($only && $kuerzel !== $only) continue;
    if (!isset($active[$kuerzel])) { $stats['skipped_inactive']++; $skippedKuerzel[$origKuerzel] = ($skippedKuerzel[$origKuerzel] ?? 0) + 1; continue; }

    // YYYY-MM aus Dateiname (sonst aus Ordner)
    $ym = null;
    if (preg_match('/(\d{4})-(\d{2})/', $stem, $mm)) $ym = $mm[1] . '-' . $mm[2];
    if (!$ym && preg_match('#/(\d{4})-(\d{2})/#', $path, $mm)) $ym = $mm[1] . '-' . $mm[2];
    if (!$ym && preg_match('#/(\d{4})/#', $path, $mm)) $ym = $mm[1];

    $cust = $active[$kuerzel];

    try {
        $parsed = parse_reporting_xlsx($path);
    } catch (\Throwable $e) {
        fwrite(STDERR, "  ! Parse-Fehler {$stem}: " . $e->getMessage() . "\n");
        $stats['skipped_unparsable']++;
        continue;
    }
    if (empty($parsed['chunks'])) { $stats['skipped_unparsable']++; fwrite(STDERR, "  ~ {$stem}: keine Aufgaben gefunden\n"); continue; }

    [$fullText, $chunks] = build_reporting_content($cust['name'], $kuerzel, $ym, $parsed);

    $extId = 'reporting:' . $stem;
    $title = '[Reporting] ' . $kuerzel . ' — ' . ($parsed['period'] ?: $ym) . ($ym ? " ({$ym})" : '');
    $desc  = 'Versendetes Reporting fuer ' . $cust['name'] . ($parsed['period'] ? ' (' . $parsed['period'] . ')' : '') . ' — ' . count($chunks) . ' Aufgaben.'
        . ($origKuerzel !== $kuerzel ? ' (Kuerzel ' . $origKuerzel . ', Vorlaeufer von ' . $kuerzel . ')' : '');

    printf("%-30s %-5s %-8s -> %2d Aufgaben\n", $stem, $kuerzel, $ym, count($chunks));
    $stats['chunks'] += count($chunks);

    // Hebel 2: faktentreuen Kompakt-Digest dieses Reportings fuer die Kunden-Historie sammeln.
    $digestByCustomer[$cust['id']]['name'] = $cust['name'];
    $digestByCustomer[$cust['id']]['abbr'] = $kuerzel;
    $digestByCustomer[$cust['id']]['items'][] = [
        'sort'   => ($ym ?: $stem),
        'digest' => build_structural_digest($parsed['period'] ?: $ym, $ym, $parsed, $origKuerzel !== $kuerzel ? $origKuerzel : null),
    ];

    if ($dry) { continue; }

    // delete-first: bestehendes Reporting-Doc mit gleicher external_id sauber entfernen
    remove_existing_doc($db, 'reporting', $extId);

    $context = ['customer_name' => $cust['name'], 'doc_context' => 'Reporting fuer ' . $cust['name'] . '.'];
    $prepared = $ingest->prepareFromChunks($chunks, $fullText, $context);
    $docId = $ingest->commit($prepared, [
        'title'       => $title,
        'description' => $desc,
        'customer_id' => $cust['id'],
        'category'    => 'Reporting',
        'tags'        => ['reporting', mb_strtolower($kuerzel), $ym ? substr($ym, 0, 4) : 'reporting'],
    ], [
        'source_type' => 'reporting',
        'source_ref'  => 'docs/reportings' . substr($path, strlen($base)),
        'external_id' => $extId,
        'created_by'  => 1,
    ]);
    $stats['imported']++;
    try { $stats['embedded'] += $qd->syncDocument((int) $docId); }
    catch (\Throwable $e) { fwrite(STDERR, "  ! Qdrant {$stem}: " . $e->getMessage() . "\n"); }
}

// ============================================================
// Hebel 2: pro Kunde EIN Historien-Uebersichts-Dokument aus den Kompakt-Digests.
// Bei einer breiten Frage ("was haben wir fuer X gemacht?") laedt der Chat dieses
// Dokument komplett -> lueckenlose Abdeckung ueber die ganze Historie (Digest-Tiefe).
// ============================================================
echo "\n--- Historien-Uebersichten (Hebel 2) ---\n";
foreach ($digestByCustomer as $custId => $data) {
    usort($data['items'], fn($a, $b) => strcmp($a['sort'], $b['sort']));
    $digests = array_map(fn($it) => $it['digest'], $data['items']);
    $head = "Kunde: {$data['name']}\nReporting-Historie (Kurzfassung) — " . count($digests) . " Reportings, chronologisch.\n"
        . "Fuer Details zu einem Quartal das jeweilige Voll-Reporting heranziehen.";
    $fullText = $head . "\n\n" . implode("\n\n", $digests);
    $extId = 'reporting_summary:' . $custId;
    $title = '[Reporting-Historie] ' . $data['abbr'] . ' — ' . $data['name'];

    printf("  %-5s %-32s %d Digests\n", $data['abbr'], $data['name'], count($digests));
    if ($dry) continue;

    remove_existing_doc($db, 'reporting_summary', $extId);
    $context = ['customer_name' => $data['name'], 'doc_context' => 'Reporting-Historie fuer ' . $data['name'] . '.'];
    $prepared = $ingest->prepareFromChunks($digests, $fullText, $context);
    $docId = $ingest->commit($prepared, [
        'title'       => $title,
        'description' => 'Chronologische Kurzfassung aller ' . count($digests) . ' Reportings fuer ' . $data['name'] . ' (fuer Historien-/Sammelfragen).',
        'customer_id' => $custId,
        'category'    => 'Reporting-Historie',
        'tags'        => ['reporting', 'historie', mb_strtolower($data['abbr'])],
    ], [
        'source_type' => 'reporting_summary',
        'source_ref'  => '/admin/wissen',
        'external_id' => $extId,
        'created_by'  => 1,
    ]);
    $stats['summaries']++;
    try { $stats['embedded'] += $qd->syncDocument((int) $docId); }
    catch (\Throwable $e) { fwrite(STDERR, "  ! Qdrant Uebersicht {$data['abbr']}: " . $e->getMessage() . "\n"); }
}

echo "\n=== Zusammenfassung ===\n";
echo "Dateien gescannt:        {$stats['files']}\n";
echo "Importiert:              {$stats['imported']}\n";
echo "Historien-Uebersichten:  {$stats['summaries']}\n";
echo "Aufgaben-Chunks:         {$stats['chunks']}\n";
echo "Qdrant embedded:         {$stats['embedded']}\n";
echo "Uebersprungen (inaktiv): {$stats['skipped_inactive']}" . ($skippedKuerzel ? ' [' . implode(', ', array_map(fn($k, $v) => "$k:$v", array_keys($skippedKuerzel), $skippedKuerzel)) . ']' : '') . "\n";
echo "Uebersprungen (leer):    {$stats['skipped_unparsable']}\n";
if ($dry) echo "\n(DRY-RUN — nichts geschrieben)\n";

// ============================================================
// Parser: xlsx -> Sektionen mit Aufgaben (Beschreibung, Aufwand, Notizen)
// ============================================================
function parse_reporting_xlsx(string $path): array
{
    $rd = IOFactory::createReaderForFile($path);
    $rd->setReadDataOnly(true);
    $ss = $rd->load($path);
    $sh = $ss->getSheet(0);
    $rows = $sh->toArray(null, true, false, false);

    $period = '';
    // Zeile 0/1: Kundenname | Zeitraum ; Website | "Monatliche Optimierung"
    if (isset($rows[0][1]) && trim((string) $rows[0][1]) !== '') $period = trim((string) $rows[0][1]);

    $sections = [];
    $curSec = null;      // ['name'=>, 'tasks'=>[]]
    $lastTask = null;    // Referenz auf letzte Aufgabe fuer Notiz-Anhang

    foreach ($rows as $i => $r) {
        if ($i < 2) continue; // Kopf
        $a = trim((string) ($r[0] ?? ''));
        $b = trim((string) ($r[1] ?? ''));
        if ($a === '' && $b === '') continue;

        // Sektion: "1. Linkaufbau, Online-PR"
        if (preg_match('/^\d+\.\s+(.+)$/u', $a, $ms)) {
            if ($curSec && $curSec['tasks']) $sections[] = $curSec;
            $curSec = ['name' => trim($ms[1]), 'tasks' => []];
            $lastTask = null;
            continue;
        }
        // Zwischensumme / Kopf-Wiederholung
        if (mb_stripos($a, 'Aufwand ca. in Std') === 0) continue;
        if ($a === '---' || $a === '—') continue;

        if ($curSec === null) $curSec = ['name' => 'Allgemein', 'tasks' => []];

        $isHours = ($b !== '' && is_numeric(str_replace(',', '.', $b)));
        if ($a !== '' && $isHours) {
            // Aufgabe mit Aufwand
            $curSec['tasks'][] = ['desc' => $a, 'hours' => (float) str_replace(',', '.', $b), 'notes' => []];
            $lastTask = count($curSec['tasks']) - 1;
        } elseif ($a !== '') {
            // Notiz / URL / Status-Zeile -> an letzte Aufgabe haengen, sonst als eigene Notiz
            $note = $a . ($b !== '' ? '  (' . $b . ')' : '');
            if ($lastTask !== null) $curSec['tasks'][$lastTask]['notes'][] = $note;
            else $curSec['tasks'][] = ['desc' => $note, 'hours' => null, 'notes' => [], 'noteonly' => true];
        }
    }
    if ($curSec && $curSec['tasks']) $sections[] = $curSec;

    // Chunks zaehlen (nur echte Aufgaben, keine reinen Notiz-Zeilen)
    $count = 0;
    foreach ($sections as $s) foreach ($s['tasks'] as $t) if (empty($t['noteonly'])) $count++;

    return ['period' => $period, 'sections' => $sections, 'chunks' => array_fill(0, $count, true)];
}

// ============================================================
// Chunk-Builder: gleiches Format wie Projektplan-Chunks
// ============================================================
function build_reporting_content(string $customer, string $kuerzel, ?string $ym, array $parsed): array
{
    $period = $parsed['period'] ?: ($ym ?: '');
    $header = "Kunde: $customer\nReporting: " . ($period !== '' ? $period : $kuerzel)
        . ($ym ? " ($ym)" : '')
        . "\nArt: durchgefuehrte Arbeiten (Reporting)";

    $chunks = [];
    $fullParts = [$header, ''];

    foreach ($parsed['sections'] as $sec) {
        $fullParts[] = "## Sektion: " . $sec['name'];
        $fullParts[] = '';
        foreach ($sec['tasks'] as $t) {
            if (!empty($t['noteonly'])) {
                $fullParts[] = "Notiz (Sektion {$sec['name']}): " . $t['desc'];
                $fullParts[] = '';
                continue;
            }
            $lines = [];
            $lines[] = $header;
            $lines[] = "Sektion: " . $sec['name'];
            $lines[] = '';
            $lines[] = 'Aufgabe: ' . $t['desc'];
            if ($t['hours'] !== null && $t['hours'] > 0) {
                $lines[] = 'Aufwand: ' . rtrim(rtrim(number_format($t['hours'], 2, ',', ''), '0'), ',') . ' h';
            }
            foreach ($t['notes'] as $n) $lines[] = 'Notiz: ' . $n;
            $block = implode("\n", $lines);
            $chunks[] = $block;
            $fullParts[] = $block;
            $fullParts[] = '';
        }
    }
    return [implode("\n", $fullParts), $chunks];
}

// ============================================================
// Hebel 2: faktentreuer, deterministischer Kompakt-Digest eines Reportings.
// Kein LLM: fuer eine faktische Historie zaehlt Vollstaendigkeit/Treue, nicht Prosa —
// die eigentliche Formulierung uebernimmt spaeter die KI beim Beantworten.
// ============================================================
function build_structural_digest(string $period, ?string $ym, array $parsed, ?string $origKuerzel = null): string
{
    $total = 0.0;
    foreach ($parsed['sections'] as $s) foreach ($s['tasks'] as $t) if (!empty($t['hours'])) $total += (float) $t['hours'];

    $lines = [];
    $lines[] = '### ' . ($period !== '' ? $period : ($ym ?: 'Reporting')) . ($ym ? " ($ym)" : '')
        . ' — ca. ' . fmt_h($total) . ' h' . ($origKuerzel ? " · vormals $origKuerzel" : '');
    foreach ($parsed['sections'] as $s) {
        $sh = 0.0; $titles = [];
        foreach ($s['tasks'] as $t) {
            if (!empty($t['hours'])) $sh += (float) $t['hours'];
            if (empty($t['noteonly'])) $titles[] = short_task((string) $t['desc']);
        }
        if (!$titles) continue;
        // Nur die ersten 5 Aufgaben-Titel je Sektion — Details stehen im Voll-Reporting.
        // So bleibt die Historien-Uebersicht kompakt genug fuers Ganzdokument-Laden.
        $shown = array_slice($titles, 0, 5);
        $more  = count($titles) - count($shown);
        $lines[] = '- ' . $s['name'] . ' (~' . fmt_h($sh) . ' h): ' . implode('; ', $shown)
            . ($more > 0 ? " (+$more weitere)" : '');
    }
    return implode("\n", $lines);
}

function fmt_h(float $h): string { return rtrim(rtrim(number_format($h, 2, ',', ''), '0'), ','); }

/** Aufgaben-Beschreibung auf eine kompakte Bezeichnung kuerzen. */
function short_task(string $desc): string
{
    $d = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $desc));
    // Steht eine kompakte Bezeichnung vor einem Doppelpunkt ("Textarbeiten Gastartikel: ..."),
    // nur diese nehmen — das ist der aussagekraeftige Aufgaben-Titel.
    $cp = mb_strpos($d, ':');
    if ($cp !== false && $cp >= 4 && $cp <= 60) return trim(mb_substr($d, 0, $cp));
    if (mb_strlen($d) > 70) {
        $d = mb_substr($d, 0, 70);
        $sp = mb_strrpos($d, ' ');
        if ($sp !== false && $sp > 40) $d = mb_substr($d, 0, $sp);
        $d .= '…';
    }
    return $d;
}

// ============================================================
// Bestehendes Doc + Chunks/Embeddings/Relations entfernen (delete-first)
// ============================================================
function remove_existing_doc(Database $db, string $sourceType, string $extId): void
{
    $doc = $db->queryOne("SELECT id FROM knowledge_documents WHERE source_type = ? AND external_id = ? LIMIT 1", [$sourceType, $extId]);
    if (!$doc) return;
    $docId = (int) $doc['id'];
    $chunkIds = array_column($db->query('SELECT id FROM knowledge_chunks WHERE document_id = ?', [$docId]) ?: [], 'id');
    if ($chunkIds) {
        $ph = implode(',', array_fill(0, count($chunkIds), '?'));
        $db->execute("DELETE FROM knowledge_chunk_entities WHERE chunk_id IN ($ph)", $chunkIds);
        $db->execute("DELETE FROM knowledge_embeddings    WHERE chunk_id IN ($ph)", $chunkIds);
        $db->execute("DELETE FROM knowledge_chunks        WHERE id       IN ($ph)", $chunkIds);
    }
    $db->execute('DELETE FROM knowledge_relations WHERE source_document_id = ?', [$docId]);
    $db->execute('DELETE FROM knowledge_documents WHERE id = ?', [$docId]);
}
