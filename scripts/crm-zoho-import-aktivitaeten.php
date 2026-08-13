<?php
/**
 * Importiert Notes, Calls, Tasks und Verkaufschancen aus Zoho-Backup als
 * Aktivitäten am jeweiligen Kontakt (oder Firma).
 *
 *  - Notes_001.csv          → crm_aktivitaeten typ=notiz
 *  - Calls_001.csv          → crm_aktivitaeten typ=telefonat
 *  - Tasks_001.csv          → crm_aktivitaeten typ=sonstiges (Aufgaben)
 *  - Verkaufschancen_001.csv → Update am Kontakt (deal_wert, deal_stufe)
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Database.php';

$opts = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

echo "─── Zoho-Aktivitäten-Import ───\n";
echo "Modus: " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n\n";

function feld($v) { $v = trim((string)$v); return $v === '' || $v === '-' || $v === 'NULL' ? null : $v; }
function datum($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $v) ? $v : null;
}

// ─── Kontakt-Index aufbauen: zoho-id → unsere kontakt-id ─────────────
echo "Baue Kontakt-Index …\n";
$kontaktIdx = [];
foreach ($db->query("SELECT id, legacy_zoho_id FROM crm_kontakte WHERE legacy_zoho_id IS NOT NULL") as $r) {
    $kontaktIdx[$r['legacy_zoho_id']] = (int)$r['id'];
}
echo "  → " . count($kontaktIdx) . " Kontakte indiziert\n\n";

// ─── 1. NOTES ────────────────────────────────────────────────────────
echo "─── 1. Notes ───\n";
$csv = BASE_PATH . '/docs/zoho-export/Data_001/Data/Notes_001.csv';
$stats = ['gelesen' => 0, 'eingefuegt' => 0, 'kein_kontakt' => 0, 'fehler' => 0];
if (file_exists($csv)) {
    $f = fopen($csv, 'r'); $header = fgetcsv($f);
    while (($row = fgetcsv($f)) !== false) {
        $stats['gelesen']++;
        $a = array_combine($header, $row);
        $parentId = feld($a['Übergeordnete.id'] ?? '');
        $parentModul = feld($a['Parent Id.Module'] ?? '');
        if ($parentModul && stripos($parentModul, 'contact') === false) continue;
        $kontaktId = $kontaktIdx[$parentId] ?? null;
        if (!$kontaktId) { $stats['kein_kontakt']++; continue; }

        $titel = feld($a['Titel'] ?? '');
        $inhalt = feld($a['Inhalt der Notiz'] ?? '');
        $erstellt = datum($a['Zeitpunkt der Erstellung'] ?? '') ?: date('Y-m-d H:i:s');
        if (!$dryRun) {
            try {
                $db->insert('crm_aktivitaeten', [
                    'kontakt_id' => $kontaktId,
                    'typ' => 'notiz',
                    'titel' => $titel,
                    'inhalt' => $inhalt,
                    'quelle' => 'zoho_import',
                    'erstellt_am' => $erstellt,
                ]);
                $stats['eingefuegt']++;
            } catch (\Throwable $e) { $stats['fehler']++; }
        }
    }
    fclose($f);
}
printf("  gelesen=%d eingefuegt=%d kein_kontakt=%d fehler=%d\n", $stats['gelesen'], $stats['eingefuegt'], $stats['kein_kontakt'], $stats['fehler']);

// ─── 2. CALLS ────────────────────────────────────────────────────────
echo "\n─── 2. Calls ───\n";
$csv = BASE_PATH . '/docs/zoho-export/Data_001/Data/Calls_001.csv';
$stats = ['gelesen' => 0, 'eingefuegt' => 0, 'kein_kontakt' => 0, 'fehler' => 0];
if (file_exists($csv)) {
    $f = fopen($csv, 'r'); $header = fgetcsv($f);
    while (($row = fgetcsv($f)) !== false) {
        $stats['gelesen']++;
        $a = array_combine($header, $row);
        $kontaktZohoId = feld($a['Kontaktname.id'] ?? '');
        if (!$kontaktZohoId) { $stats['kein_kontakt']++; continue; }
        $kontaktId = $kontaktIdx[$kontaktZohoId] ?? null;
        if (!$kontaktId) { $stats['kein_kontakt']++; continue; }

        $betreff = feld($a['Betreff'] ?? '');
        $beschreibung = feld($a['Beschreibung'] ?? '');
        $dauer = feld($a['Anrufdauer'] ?? '');
        $ergebnis = feld($a['Anrufergebnis'] ?? '');
        $art = feld($a['Art des Anrufs'] ?? '');
        $beginn = datum($a['Anruf Beginnzeit'] ?? '');
        $erstellt = datum($a['Zeitpunkt der Erstellung'] ?? '') ?: date('Y-m-d H:i:s');

        $titelTeile = array_filter([$art, $betreff, $dauer ? "($dauer)" : null]);
        $titel = implode(' · ', $titelTeile);
        $inhaltTeile = array_filter([$beschreibung, $ergebnis ? "Ergebnis: $ergebnis" : null]);

        if (!$dryRun) {
            try {
                $db->insert('crm_aktivitaeten', [
                    'kontakt_id' => $kontaktId,
                    'typ' => 'telefonat',
                    'titel' => mb_substr($titel, 0, 255),
                    'inhalt' => implode("\n\n", $inhaltTeile),
                    'quelle' => 'zoho_import',
                    'erstellt_am' => $beginn ?: $erstellt,
                ]);
                $stats['eingefuegt']++;
            } catch (\Throwable $e) { $stats['fehler']++; }
        }
    }
    fclose($f);
}
printf("  gelesen=%d eingefuegt=%d kein_kontakt=%d fehler=%d\n", $stats['gelesen'], $stats['eingefuegt'], $stats['kein_kontakt'], $stats['fehler']);

// ─── 3. TASKS ────────────────────────────────────────────────────────
echo "\n─── 3. Tasks ───\n";
$csv = BASE_PATH . '/docs/zoho-export/Data_001/Data/Tasks_001.csv';
$stats = ['gelesen' => 0, 'eingefuegt' => 0, 'kein_kontakt' => 0, 'fehler' => 0];
if (file_exists($csv)) {
    $f = fopen($csv, 'r'); $header = fgetcsv($f);
    while (($row = fgetcsv($f)) !== false) {
        $stats['gelesen']++;
        $a = array_combine($header, $row);
        $kontaktZohoId = feld($a['Kontaktname.id'] ?? '');
        if (!$kontaktZohoId) { $stats['kein_kontakt']++; continue; }
        $kontaktId = $kontaktIdx[$kontaktZohoId] ?? null;
        if (!$kontaktId) { $stats['kein_kontakt']++; continue; }

        $betreff = feld($a['Betreff'] ?? '');
        $beschreibung = feld($a['Beschreibung'] ?? '');
        $status = feld($a['Status'] ?? '');
        $faellig = feld($a['Fälligkeitsdatum'] ?? '');
        $prio = feld($a['Priorität'] ?? '');
        $erstellt = datum($a['Zeitpunkt der Erstellung'] ?? '') ?: date('Y-m-d H:i:s');

        $titel = trim("Aufgabe" . ($betreff ? ": $betreff" : '') . ($status ? " [$status]" : ''));
        $inhaltTeile = array_filter([
            $beschreibung,
            $faellig ? "Fällig: $faellig" : null,
            $prio ? "Priorität: $prio" : null,
        ]);

        if (!$dryRun) {
            try {
                $db->insert('crm_aktivitaeten', [
                    'kontakt_id' => $kontaktId,
                    'typ' => 'sonstiges',
                    'titel' => mb_substr($titel, 0, 255),
                    'inhalt' => implode("\n", $inhaltTeile),
                    'quelle' => 'zoho_import',
                    'erstellt_am' => $erstellt,
                ]);
                $stats['eingefuegt']++;
            } catch (\Throwable $e) { $stats['fehler']++; }
        }
    }
    fclose($f);
}
printf("  gelesen=%d eingefuegt=%d kein_kontakt=%d fehler=%d\n", $stats['gelesen'], $stats['eingefuegt'], $stats['kein_kontakt'], $stats['fehler']);

// ─── 4. VERKAUFSCHANCEN ─────────────────────────────────────────────
echo "\n─── 4. Verkaufschancen ───\n";
$csv = BASE_PATH . '/docs/zoho-export/Data_001/Data/Verkaufschancen_001.csv';
$stats = ['gelesen' => 0, 'aktualisiert' => 0, 'kein_kontakt' => 0, 'fehler' => 0];
if (file_exists($csv)) {
    $f = fopen($csv, 'r'); $header = fgetcsv($f);
    while (($row = fgetcsv($f)) !== false) {
        $stats['gelesen']++;
        $a = array_combine($header, $row);
        $kontaktZohoId = feld($a['Kontakt-Name.id'] ?? '');
        if (!$kontaktZohoId) { $stats['kein_kontakt']++; continue; }
        $kontaktId = $kontaktIdx[$kontaktZohoId] ?? null;
        if (!$kontaktId) { $stats['kein_kontakt']++; continue; }

        $betrag = is_numeric($a['Betrag'] ?? '') ? (float)$a['Betrag'] : null;
        $stufe  = feld($a['Stufe'] ?? '');
        $name   = feld($a['Verkaufschance-Name'] ?? '');
        $abschluss = datum($a['Abschlussdatum'] ?? '');
        $update = [];
        if ($betrag !== null) $update['deal_wert'] = $betrag;
        if ($stufe)            $update['deal_stufe'] = mb_substr($stufe, 0, 80);
        if (!$update) continue;

        if (!$dryRun) {
            try {
                $db->update('crm_kontakte', $update, 'id = ?', [$kontaktId]);
                // Auch als Aktivität dokumentieren
                $db->insert('crm_aktivitaeten', [
                    'kontakt_id' => $kontaktId,
                    'typ' => 'sonstiges',
                    'titel' => mb_substr("Verkaufschance: " . ($name ?: '(unbenannt)') . ($stufe ? " · $stufe" : ''), 0, 255),
                    'inhalt' => ($betrag !== null ? "Wert: " . number_format($betrag, 2, ',', '.') . " €\n" : '') .
                                ($abschluss ? "Abschluss: $abschluss" : ''),
                    'quelle' => 'zoho_import',
                    'erstellt_am' => datum($a['Zeitpunkt der Erstellung'] ?? '') ?: date('Y-m-d H:i:s'),
                ]);
                $stats['aktualisiert']++;
            } catch (\Throwable $e) { $stats['fehler']++; }
        }
    }
    fclose($f);
}
printf("  gelesen=%d aktualisiert=%d kein_kontakt=%d fehler=%d\n", $stats['gelesen'], $stats['aktualisiert'], $stats['kein_kontakt'], $stats['fehler']);

echo "\n─── Fertig ───\n";
