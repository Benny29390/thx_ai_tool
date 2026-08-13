<?php
/**
 * Korrektur-Lernschleife (Cron, nächtlich).
 *
 * Jedes Mal, wenn Thomas einen KI-Antwortentwurf vor dem Versand ÄNDERT, ist das ein
 * Lehrstück: Der Unterschied zwischen `ki_vorschlag` und `finaler_text` sagt wörtlich,
 * was die KI falsch gemacht hat. Dieses Skript wertet solche Korrekturen aus und leitet
 * daraus Regel-VORSCHLÄGE ab.
 *
 * Es aktiviert nichts. Jede Regel wartet auf Thomas' Freigabe unter
 * /admin/settings?tab=mail → „Stil & gelernte Regeln".
 *
 * Aufruf: php scripts/mail-lernschleife.php [--konto=3]
 */

require_once __DIR__ . '/../config/constants.php';
spl_autoload_register(function ($class) {
    foreach (['Core\\' => 'core/', 'Models\\' => 'models/', 'Services\\' => 'services/'] as $ns => $dir) {
        if (strpos($class, $ns) === 0) {
            $f = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($f)) { require_once $f; return; }
        }
    }
});
require_once ROOT_PATH . '/vendor/autoload.php';

set_time_limit(0);

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$nurKonto = 0;
foreach ($argv as $a) {
    if (preg_match('/^--konto=(\d+)$/', $a, $m)) $nurKonto = (int) $m[1];
}

$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

$lern = new \Services\MailLernService($db);

$where = $nurKonto ? 'WHERE id = ' . (int) $nurKonto : '';
$konten = $db->query("SELECT id, name FROM mail_konten $where");

echo '=== Korrektur-Lernschleife (' . date('Y-m-d H:i:s') . ") ===\n";

foreach ($konten as $k) {
    try {
        $r = $lern->lerneAusKorrekturen((int) $k['id'], $settings);
        printf("Konto „%s\": %s\n", $k['name'], $r['meldung']);
    } catch (\Throwable $e) {
        printf("Konto „%s\": FEHLER — %s\n", $k['name'], $e->getMessage());
    }
}

echo "Fertig.\n";
