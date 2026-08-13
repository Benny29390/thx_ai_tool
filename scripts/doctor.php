<?php
/**
 * doctor.php — Selbsttest einer Installation (CLI).
 *
 * Prueft die haeufigsten Stolpersteine: config + encryption_key, DB-Verbindung,
 * Verschluesselung, Schreibrechte, Migrationsstand und optionale externe
 * Werkzeuge. Gibt einen Statusbericht aus und beendet mit Code 0 (alles ok)
 * bzw. 1 (mindestens ein harter Fehler).
 *
 * Aufruf:  php scripts/doctor.php
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

$fehler = 0;
$warn = 0;
function ok(string $m): void { echo "  [ok]   $m\n"; }
function bad(string $m): void { global $fehler; $fehler++; echo "  [FEHL] $m\n"; }
function warnung(string $m): void { global $warn; $warn++; echo "  [warn] $m\n"; }

echo "== KI Text Tool — Selbsttest ==\n\n";

// 1) config.php + encryption_key
echo "Konfiguration:\n";
$configFile = CONFIG_PATH . '/config.php';
if (!is_file($configFile)) {
    bad("config.php fehlt ($configFile) — Installation nicht abgeschlossen.");
    echo "\nAbbruch — ohne config.php koennen weitere Tests nicht laufen.\n";
    exit(1);
}
$config = require $configFile;
ok("config.php vorhanden");

$key = $config['app']['encryption_key'] ?? '';
if (!is_string($key) || !preg_match('/^[0-9a-fA-F]{64}$/', $key)) {
    bad("encryption_key fehlt oder ist kein 64-stelliger Hex-Wert. Secrets sind nicht verschluesselbar.");
} else {
    ok("encryption_key vorhanden (64 Hex-Zeichen)");
}

// 2) DB-Verbindung
echo "\nDatenbank:\n";
$db = null;
try {
    $db = \Core\Database::getInstance($config['db']);
    $db->queryValue("SELECT 1");
    ok("Verbindung zu '{$config['db']['name']}' auf '{$config['db']['host']}' steht");
} catch (\Throwable $e) {
    bad("Keine DB-Verbindung: " . $e->getMessage());
}

// 3) Verschluesselung (Roundtrip)
echo "\nVerschluesselung:\n";
try {
    $probe = 'doctor-' . bin2hex(random_bytes(4));
    $enc = \Core\Crypto::encrypt($probe);
    $dec = \Core\Crypto::decrypt($enc);
    if ($dec === $probe && strpos($enc, 'enc:v1:') === 0) {
        ok("AES-256-GCM Ver-/Entschluesselung funktioniert");
    } else {
        bad("Ver-/Entschluesselung liefert falsches Ergebnis.");
    }
} catch (\Throwable $e) {
    bad("Crypto-Fehler: " . $e->getMessage());
}

// 4) Schreibrechte
echo "\nSchreibrechte:\n";
foreach (['storage', 'storage/logs', 'uploads'] as $rel) {
    $path = ROOT_PATH . '/' . $rel;
    if (!is_dir($path)) {
        warnung("$rel/ existiert nicht (wird bei Bedarf angelegt).");
        continue;
    }
    if (is_writable($path)) {
        ok("$rel/ ist beschreibbar");
    } else {
        bad("$rel/ ist NICHT beschreibbar (sollte www-data gehoeren).");
    }
}

// 5) Migrationsstand
echo "\nMigrationen:\n";
if ($db) {
    try {
        $pending = \Core\Migrator::pending($db);
        if (empty($pending)) {
            ok("Datenbank ist auf aktuellem Migrationsstand");
        } else {
            warnung(count($pending) . " offene Migration(en): " . implode(', ', $pending) . " — 'php scripts/migrate.php' ausfuehren.");
        }
    } catch (\Throwable $e) {
        warnung("Migrationsstand nicht ermittelbar: " . $e->getMessage());
    }
}

// 6) Optionale externe Werkzeuge (nur Hinweis)
echo "\nExterne Werkzeuge (nur relevant fuer bestimmte Module):\n";
foreach (['ffmpeg' => 'Transkription', 'yt-dlp' => 'Loom-Import', 'python3' => 'Transkription', 'composer' => 'Updates', 'git' => 'Updates'] as $bin => $zweck) {
    $found = trim((string) @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
    if ($found !== '') {
        ok("$bin gefunden ($zweck)");
    } else {
        warnung("$bin nicht gefunden — noetig fuer: $zweck");
    }
}

echo "\n== Ergebnis: ";
if ($fehler === 0) {
    echo "OK" . ($warn ? " ($warn Hinweis" . ($warn > 1 ? 'e' : '') . ")" : "") . " ==\n";
    exit(0);
}
echo "$fehler Fehler, $warn Hinweise ==\n";
exit(1);
