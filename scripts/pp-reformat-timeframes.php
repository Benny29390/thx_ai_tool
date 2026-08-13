<?php
/**
 * Einmalskript: alle pp_plan_rows.timeframe-Werte in aktiven Plaenen ins
 * Schema DD.MM. bzw. DD.-DD.MM. bzw. DD.MM.-DD.MM. ueberfuehren.
 *
 * Idempotent: Werte, die schon korrekt formatiert sind, bleiben unveraendert.
 * Werte, die nicht ins Schema passen (z.B. "KW12"), bleiben ebenfalls unangetastet.
 *
 * Aufruf:
 *   php scripts/pp-reformat-timeframes.php
 *   php scripts/pp-reformat-timeframes.php --dry-run
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

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$dryRun = in_array('--dry-run', $argv ?? [], true);

function pad2(int $n): string { return str_pad((string) $n, 2, '0', STR_PAD_LEFT); }

function parsePart(string $p): ?array {
    $t = rtrim($p, '.');
    if (!preg_match('/^(\d{1,2})(?:\.(\d{1,2}))?$/', $t, $m)) return null;
    $day   = (int) $m[1];
    $month = isset($m[2]) ? (int) $m[2] : null;
    if ($day < 1 || $day > 31) return null;
    if ($month !== null && ($month < 1 || $month > 12)) return null;
    return ['day' => $day, 'month' => $month];
}

function formatSingleSpan(string $s): string {
    $parts = explode('-', $s);
    if (count($parts) === 1) {
        $p = parsePart($parts[0]);
        if (!$p || $p['month'] === null) return $s;
        return pad2($p['day']) . '.' . pad2($p['month']) . '.';
    }
    if (count($parts) === 2) {
        $a = parsePart($parts[0]);
        $b = parsePart($parts[1]);
        if (!$a || !$b || $b['month'] === null) return $s;
        if ($a['month'] === null) {
            return pad2($a['day']) . '.-' . pad2($b['day']) . '.' . pad2($b['month']) . '.';
        }
        return pad2($a['day']) . '.' . pad2($a['month']) . '.-' . pad2($b['day']) . '.' . pad2($b['month']) . '.';
    }
    return $s;
}

function formatTimeframe(string $raw): string {
    if ($raw === '') return '';
    $s = preg_replace('/\s+/u', '', $raw);
    $s = strtr($s, ['—' => '-', '–' => '-']);
    if (!preg_match('/\d/', $s)) return $raw;
    // Mehrere Zeitspannen mit Komma trennen, jede einzeln normalisieren.
    $fragments = array_values(array_filter(array_map('trim', explode(',', $s)), fn($f) => $f !== ''));
    if (count($fragments) > 1) {
        return implode(', ', array_map('formatSingleSpan', $fragments));
    }
    return formatSingleSpan($fragments[0] ?? $s);
}

// Alle Item-Rows aus aktiven Plaenen mit nicht-leerem timeframe ziehen
$rows = $db->query("
    SELECT r.id, r.plan_id, r.timeframe, p.title AS plan_title
    FROM pp_plan_rows r
    JOIN pp_plans p ON p.id = r.plan_id
    WHERE p.state = 1
      AND r.row_type = 'item'
      AND r.timeframe IS NOT NULL
      AND TRIM(r.timeframe) <> ''
");

$total = count($rows);
$changed = 0;
$skipped = 0;
$kept = 0;

echo "Gefundene Zeilen mit timeframe (aktive Plaene): $total\n";
echo str_repeat('-', 70) . "\n";

foreach ($rows as $r) {
    $old = (string) $r['timeframe'];
    $new = formatTimeframe($old);
    if ($new === $old) { $kept++; continue; }
    // Falls die Format-Funktion einen unveraenderbaren Wert zurueckliefert (gleicher String),
    // wuerde der oben gefangen — hier ist $new garantiert anders als $old.
    printf("Plan %-30s  Row #%-5d  '%s' -> '%s'\n",
        mb_strimwidth($r['plan_title'], 0, 30, '..'),
        $r['id'],
        $old,
        $new
    );
    if ($dryRun) { $skipped++; continue; }
    $db->update('pp_plan_rows', ['timeframe' => $new], 'id = ?', [$r['id']]);
    $changed++;
}

echo str_repeat('-', 70) . "\n";
echo "Geaendert:   $changed\n";
echo "Unveraendert: $kept (schon korrekt oder Sonderformat)\n";
if ($dryRun) {
    echo "Dry-Run aktiv ($skipped haetten geaendert werden sollen). Ohne --dry-run noch einmal starten.\n";
} else {
    echo "Fertig.\n";
}
