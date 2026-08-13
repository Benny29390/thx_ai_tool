<?php
/**
 * Importiert Firmen aus Zoho-Backup + verknüpft Kontakte zu Firmen.
 *
 * Schritte:
 *   1. Firmen aus Firmen_001.csv anlegen / anreichern (per legacy_zoho_id)
 *   2. Kontakte mit Firmen verknüpfen: legacy_zoho_json enthält 'Firmen-Name.id'
 *      → wir mappen das auf unsere crm_firmen.id
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Database.php';

$opts = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);
$csvFirmen = BASE_PATH . '/docs/zoho-export/Data_001/Data/Firmen_001.csv';

if (!file_exists($csvFirmen)) { fwrite(STDERR, "$csvFirmen fehlt\n"); exit(1); }

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

echo "─── Firmen-Import ───\n";
echo "Modus: " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n\n";

function feld($v) { $v = trim((string)$v); return $v === '' || $v === '-' || $v === 'NULL' ? null : $v; }
function datum($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $v) ? $v : null;
}

$f = fopen($csvFirmen, 'r');
$header = fgetcsv($f);
$stats = ['gelesen' => 0, 'angelegt' => 0, 'angereichert' => 0, 'fehler' => 0];

while (($row = fgetcsv($f)) !== false) {
    $stats['gelesen']++;
    $assoc = array_combine($header, $row);
    $zohoId = feld($assoc['Eintrag-ID'] ?? '');
    $name = feld($assoc['Firmen-Name'] ?? '');
    if (!$name) continue;

    try {
        $vorh = $zohoId ? $db->queryValue("SELECT id FROM crm_firmen WHERE legacy_zoho_id = ?", [$zohoId]) : null;
        if (!$vorh) $vorh = $db->queryValue("SELECT id FROM crm_firmen WHERE firmenname = ?", [$name]);

        $felder = [
            'firmenname'      => $name,
            'website'         => feld($assoc['Webseite'] ?? ''),
            'branche'         => feld($assoc['Branche'] ?? ''),
            'firmen_typ'      => feld($assoc['Firmen Typ'] ?? ''),
            'bewertung'       => is_numeric($assoc['Bewertung'] ?? '') ? (int)$assoc['Bewertung'] : null,
            'beschaeftigte'   => is_numeric($assoc['Beschäftigte'] ?? '') ? (int)$assoc['Beschäftigte'] : null,
            'jahreseinnahmen' => is_numeric($assoc['Jahreseinnahmen'] ?? '') ? (float)$assoc['Jahreseinnahmen'] : null,
            'telefon'         => feld($assoc['Tel.'] ?? ''),
            'fax'             => feld($assoc['Fax'] ?? ''),
            'legacy_zoho_id'  => $zohoId,
            'legacy_zoho_json' => json_encode($assoc, JSON_UNESCAPED_UNICODE),
        ];
        $erstellt = datum($assoc['Zeitpunkt der Erstellung'] ?? '');
        $geaendert = datum($assoc['Zeitpunkt der Änderung'] ?? '');
        if ($erstellt) $felder['erstellt_am'] = $erstellt;
        if ($geaendert) $felder['geaendert_am'] = $geaendert;

        if ($vorh) {
            // Nur leere Felder anreichern
            $best = $db->queryOne("SELECT * FROM crm_firmen WHERE id = ?", [$vorh]);
            $update = [];
            foreach ($felder as $sp => $w) {
                if ($w === null) continue;
                if (empty($best[$sp])) $update[$sp] = $w;
            }
            if ($zohoId) $update['legacy_zoho_id'] = $zohoId;
            $update['legacy_zoho_json'] = $felder['legacy_zoho_json'];
            if ($update && !$dryRun) $db->update('crm_firmen', $update, 'id = ?', [$vorh]);
            $stats['angereichert']++;
        } else {
            $felder = array_filter($felder, fn($v) => $v !== null);
            if (!$dryRun) $db->insert('crm_firmen', $felder);
            $stats['angelegt']++;
        }

        if ($stats['gelesen'] % 200 === 0) echo "  · {$stats['gelesen']} verarbeitet\n";
    } catch (\Throwable $e) {
        $stats['fehler']++;
        if ($stats['fehler'] <= 5) fwrite(STDERR, "Fehler bei \"$name\": " . $e->getMessage() . "\n");
    }
}
fclose($f);

echo "\n─── Firmen-Statistik ───\n";
foreach ($stats as $k => $v) printf("  %-20s : %d\n", $k, $v);

// ─── Schritt 2: Kontakte verknüpfen ──────────────────────────────────
echo "\n─── Verknüpfe Kontakte mit Firmen ───\n";
// Index: zoho-firma-id → unsere crm_firmen.id
$firmenIndex = [];
foreach ($db->query("SELECT id, legacy_zoho_id FROM crm_firmen WHERE legacy_zoho_id IS NOT NULL") as $r) {
    $firmenIndex[$r['legacy_zoho_id']] = (int)$r['id'];
}
echo "  Firmen-Index: " . count($firmenIndex) . " Zoho-IDs\n";

$verknuepft = 0; $kein_match = 0; $kontakte_geprueft = 0;
$kontakte = $db->query("SELECT id, legacy_zoho_json FROM crm_kontakte WHERE legacy_zoho_json IS NOT NULL AND firma_id IS NULL");
foreach ($kontakte as $k) {
    $kontakte_geprueft++;
    $json = json_decode($k['legacy_zoho_json'], true);
    if (!is_array($json)) continue;
    $zohoFirmaId = trim((string)($json['Firmen-Name.id'] ?? ''));
    if (!$zohoFirmaId) continue;
    $firmaId = $firmenIndex[$zohoFirmaId] ?? null;
    if (!$firmaId) { $kein_match++; continue; }
    if (!$dryRun) $db->update('crm_kontakte', ['firma_id' => $firmaId], 'id = ?', [(int)$k['id']]);
    $verknuepft++;
}
printf("  Geprüft       : %d\n", $kontakte_geprueft);
printf("  Verknüpft     : %d\n", $verknuepft);
printf("  Kein Match    : %d (Firma im Zoho-Backup nicht enthalten)\n", $kein_match);

echo "\n" . ($dryRun ? "DRY-RUN — keine Änderungen." : "Fertig.") . "\n";
