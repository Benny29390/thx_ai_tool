<?php
/**
 * Importiert Fotos aus dem Zoho-Bulk-Backup.
 *
 * Quelle:
 *   /var/www/docs/zoho-export/Data_001/Data/Contacts_001.csv (Spalte "Kontakt-Bild")
 *   /var/www/docs/zoho-export/Data_001/RecordImages/{hash}.png
 *
 * Logik:
 *   1. CSV lesen → pro Zeile (Eintrag-ID, E-Mail, Kontakt-Bild)
 *   2. Match in unserer DB: zuerst legacy_zoho_id, sonst email_primaer
 *   3. Bild aus RecordImages/{Kontakt-Bild} nach /var/www/uploads/crm/avatar/{id}.{ext} kopieren
 *   4. foto_path im Kontakt setzen
 *
 * Usage:
 *   php scripts/crm-zoho-import-fotos.php [--dry-run] [--csv=pfad] [--images=verz]
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Database.php';

$opts = getopt('', ['dry-run', 'csv::', 'images::']);
$dryRun = isset($opts['dry-run']);
$csv    = $opts['csv']    ?? BASE_PATH . '/docs/zoho-export/Data_001/Data/Contacts_001.csv';
$images = $opts['images'] ?? BASE_PATH . '/docs/zoho-export/Data_001/RecordImages';

if (!file_exists($csv))      { fwrite(STDERR, "CSV nicht gefunden: $csv\n"); exit(1); }
if (!is_dir($images))        { fwrite(STDERR, "Bild-Verzeichnis nicht gefunden: $images\n"); exit(1); }

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

echo "─── Zoho-Foto-Import ───\n";
echo "CSV       : $csv\n";
echo "Bilder    : $images\n";
echo "Modus     : " . ($dryRun ? "DRY-RUN" : "LIVE") . "\n\n";

$verz = '/var/www/uploads/crm/avatar';
if (!is_dir($verz)) @mkdir($verz, 0775, true);

// ─── Header analysieren ──────────────────────────────────────────────
$f = fopen($csv, 'r');
$header = fgetcsv($f);
$idxId   = array_search('Eintrag-ID', $header);
$idxMail = array_search('E-Mail', $header);
$idxBild = array_search('Kontakt-Bild', $header);
if ($idxId === false || $idxBild === false) {
    fwrite(STDERR, "Spalten 'Eintrag-ID' und/oder 'Kontakt-Bild' nicht gefunden.\n");
    exit(1);
}

$stats = [
    'zeilen' => 0, 'mit_bild' => 0, 'datei_fehlt' => 0,
    'kontakt_match_id' => 0, 'kontakt_match_mail' => 0, 'kontakt_kein_match' => 0,
    'gespeichert' => 0, 'fehler' => 0,
];

$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);

while (($row = fgetcsv($f)) !== false) {
    $stats['zeilen']++;
    $zohoId = trim((string)($row[$idxId] ?? ''));
    $email  = strtolower(trim((string)($row[$idxMail] ?? '')));
    $bild   = trim((string)($row[$idxBild] ?? ''));
    if (!$bild) continue;
    $stats['mit_bild']++;

    $bildPfad = $images . '/' . $bild;
    if (!file_exists($bildPfad)) {
        $stats['datei_fehlt']++;
        continue;
    }

    // Match in unserer DB
    $kontaktId = null;
    if ($zohoId) {
        $kontaktId = $db->queryValue("SELECT id FROM crm_kontakte WHERE legacy_zoho_id = ?", [$zohoId]);
        if ($kontaktId) $stats['kontakt_match_id']++;
    }
    if (!$kontaktId && $email) {
        $kontaktId = $db->queryValue("SELECT id FROM crm_kontakte WHERE email_primaer = ?", [$email]);
        if ($kontaktId) $stats['kontakt_match_mail']++;
    }
    if (!$kontaktId) {
        $stats['kontakt_kein_match']++;
        continue;
    }

    if ($dryRun) {
        $stats['gespeichert']++;
        continue;
    }

    try {
        // MIME erkennen
        $mime = $finfo->file($bildPfad);
        if (!isset($extMap[$mime])) {
            // Fallback: Extension aus Dateinamen
            $ext = strtolower(pathinfo($bild, PATHINFO_EXTENSION));
            $ext = $ext === 'jpeg' ? 'jpg' : $ext;
            if (!in_array($ext, ['jpg','png','gif','webp'], true)) {
                throw new \RuntimeException("Unbekannter Bildtyp: $mime / .$ext");
            }
        } else {
            $ext = $extMap[$mime];
        }

        // Alte Dateien (andere Endung) für diesen Kontakt entfernen
        foreach (['jpg','png','gif','webp'] as $alt) {
            $altPfad = "$verz/{$kontaktId}.$alt";
            if (file_exists($altPfad)) @unlink($altPfad);
        }
        $ziel = "$verz/{$kontaktId}.$ext";
        copy($bildPfad, $ziel);
        @chmod($ziel, 0664);

        $db->update('crm_kontakte', ['foto_path' => "/uploads/crm/avatar/{$kontaktId}.{$ext}"], 'id = ?', [(int)$kontaktId]);
        $stats['gespeichert']++;

        if ($stats['gespeichert'] % 100 === 0) {
            echo "  · {$stats['gespeichert']} Fotos zugeordnet …\n";
        }
    } catch (\Throwable $e) {
        $stats['fehler']++;
        fwrite(STDERR, "Fehler bei $bild (Kontakt $kontaktId): " . $e->getMessage() . "\n");
    }
}
fclose($f);

echo "\n─── Statistik ───\n";
foreach ($stats as $k => $v) printf("  %-25s : %d\n", $k, $v);
echo "\n" . ($dryRun ? "DRY-RUN — keine Dateien geschrieben." : "Fertig. Fotos liegen unter $verz") . "\n";
