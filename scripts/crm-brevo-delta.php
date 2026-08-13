<?php
/**
 * Brevo-Delta: holt für Kontakte mit Brevo-ID aber falschem erstellt_am
 * die echten Brevo-Timestamps (createdAt + modifiedAt) und alle Custom
 * Attributes per API nach.
 *
 * Default: nur Kontakte mit erstellt_am >= 2026-06-01 (also die "frischen"
 * Brevo-only Kontakte, deren Datum vom Migrationsskript stammt).
 *
 * Usage:
 *   php scripts/crm-brevo-delta.php [--dry-run] [--alle]
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Session.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Crypto.php';
require BASE_PATH . '/core/Settings.php';
require BASE_PATH . '/core/Auth.php';
require BASE_PATH . '/services/CrmBrevoService.php';

$opts = getopt('', ['dry-run', 'alle']);
$dryRun = isset($opts['dry-run']);
$alleBrevoIds = isset($opts['alle']);

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

$key = (string)\Core\Settings::get('brevo_api_key', '');
if ($key === '') { fwrite(STDERR, "Brevo-API-Key nicht gesetzt\n"); exit(1); }
$brevo = new \Services\CrmBrevoService($key);

echo "─── Brevo-Delta ───\n";
echo "Modus: " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";

$where = "brevo_id IS NOT NULL AND geloescht_am IS NULL";
if (!$alleBrevoIds) {
    $where .= " AND (legacy_zoho_id IS NULL OR erstellt_am >= '2026-06-01')";
}
$kontakte = $db->query("SELECT id, brevo_id, email_primaer, erstellt_am, geaendert_am FROM crm_kontakte WHERE $where");
echo "Kandidaten: " . count($kontakte) . "\n\n";

$stats = ['gelesen' => 0, 'aktualisiert' => 0, 'kein_brevo_ergebnis' => 0, 'fehler' => 0];

foreach ($kontakte as $k) {
    $stats['gelesen']++;
    try {
        // Brevo-API direkt aufrufen (Helper via cURL)
        $ch = curl_init('https://api.brevo.com/v3/contacts/' . urlencode($k['email_primaer']));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['api-key: ' . $key, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 429) {
            // Rate-Limit → kurz warten
            usleep(500000);
            continue;
        }
        if ($http !== 200) { $stats['kein_brevo_ergebnis']++; continue; }
        $data = json_decode($resp, true);
        if (!is_array($data)) { $stats['kein_brevo_ergebnis']++; continue; }

        $update = [];
        if (!empty($data['createdAt'])) {
            $update['erstellt_am'] = date('Y-m-d H:i:s', strtotime($data['createdAt']));
        }
        if (!empty($data['modifiedAt'])) {
            $update['geaendert_am'] = date('Y-m-d H:i:s', strtotime($data['modifiedAt']));
        }
        // Custom Attributes
        if (!empty($data['attributes']) && is_array($data['attributes'])) {
            $attr = $data['attributes'];
            if (!empty($attr['VORNAME']) || !empty($attr['FIRSTNAME'])) {
                // schon da, überspringen
            }
            // legacy_zoho_json ergänzen mit Brevo-Daten
            $update['legacy_zoho_json'] = json_encode(['_brevo' => $data], JSON_UNESCAPED_UNICODE);
        }
        if ($update && !$dryRun) {
            $db->update('crm_kontakte', $update, 'id = ?', [(int)$k['id']]);
            $stats['aktualisiert']++;
        }

        if ($stats['gelesen'] % 25 === 0) {
            echo "  · {$stats['gelesen']} verarbeitet …\n";
        }
        usleep(120000);  // Rate-Limit-Schutz (~8 req/sec)
    } catch (\Throwable $e) {
        $stats['fehler']++;
        fwrite(STDERR, "Fehler bei {$k['email_primaer']}: " . $e->getMessage() . "\n");
    }
}

echo "\n─── Statistik ───\n";
foreach ($stats as $k => $v) printf("  %-25s : %d\n", $k, $v);
echo "\n" . ($dryRun ? "DRY-RUN — nichts geschrieben." : "Fertig.") . "\n";
