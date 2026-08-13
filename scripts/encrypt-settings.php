<?php
/**
 * Migrations-Skript: alle Secret-Werte (api_key, _pat, password, secret, token)
 * in der settings-Tabelle einmalig verschluesseln.
 *
 * Idempotent: Werte, die bereits mit "enc:v1:" anfangen, werden uebersprungen.
 *
 * Aufruf (CLI):
 *   php scripts/encrypt-settings.php
 *   php scripts/encrypt-settings.php --dry-run   # nur anzeigen, nichts schreiben
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

$configFile = CONFIG_PATH . '/config.php';
$config = require $configFile;
\Core\Database::getInstance($config['db']);

$dryRun = in_array('--dry-run', $argv ?? [], true);

$db = \Core\Database::getInstance();
$rows = $db->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");

$total = 0;
$migriert = 0;
$bereitsVerschluesselt = 0;
$nichtSecret = 0;
$leer = 0;
$fehler = 0;

foreach ($rows as $row) {
    $total++;
    $key = $row['setting_key'];
    $val = (string)($row['setting_value'] ?? '');

    if (!\Core\Settings::isSecret($key)) {
        $nichtSecret++;
        continue;
    }
    if ($val === '') {
        $leer++;
        echo sprintf("  · %-30s leer — uebersprungen\n", $key);
        continue;
    }
    if (\Core\Crypto::isEncrypted($val)) {
        $bereitsVerschluesselt++;
        echo sprintf("  ✓ %-30s bereits verschluesselt\n", $key);
        continue;
    }

    try {
        $enc = \Core\Crypto::encrypt($val);
    } catch (\Throwable $e) {
        echo sprintf("  ✗ %-30s FEHLER: %s\n", $key, $e->getMessage());
        $fehler++;
        continue;
    }

    if ($dryRun) {
        echo sprintf("  → %-30s wuerde verschluesselt (%d → %d Zeichen)\n", $key, strlen($val), strlen($enc));
    } else {
        $db->execute(
            "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
            [$enc, $key]
        );
        echo sprintf("  → %-30s verschluesselt (%d → %d Zeichen)\n", $key, strlen($val), strlen($enc));
    }
    $migriert++;
}

echo "\n";
echo "------------------------------------------------------------\n";
echo "Settings gesamt:           $total\n";
echo "Nicht-Secret (uebersprungen): $nichtSecret\n";
echo "Bereits verschluesselt:    $bereitsVerschluesselt\n";
echo "Leerer Wert:               $leer\n";
echo ($dryRun ? "WUERDE migrieren:          " : "Migriert:                  ") . "$migriert\n";
if ($fehler > 0) echo "Fehler:                    $fehler\n";
echo "------------------------------------------------------------\n";
if ($dryRun) {
    echo "Dry-Run — nichts geschrieben. Ohne --dry-run scharf laufen lassen.\n";
}
