<?php
/**
 * Migrations-CLI — fuehrt die inkrementellen Migrationen aus sql/migrations/ aus.
 *
 * Aufruf (CLI):
 *   php scripts/migrate.php                 # offene Migrationen anwenden
 *   php scripts/migrate.php --status        # nur anzeigen, was offen/angewendet ist
 *   php scripts/migrate.php --mark-baseline # alle vorhandenen als "angewendet"
 *                                           # markieren, OHNE sie auszufuehren
 *                                           # (Erst-Rollout auf Bestands-DB)
 *
 * DATENSICHERHEIT: Migrationen sind additiv/idempotent (siehe core/Migrator.php).
 * --mark-baseline aendert NICHTS am Schema, es setzt nur den Protokoll-Stand.
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
if (!is_file($configFile)) {
    fwrite(STDERR, "Keine config.php — bitte zuerst installieren.\n");
    exit(1);
}
$config = require $configFile;
\Core\Database::getInstance($config['db']);

use Core\Migrator;

$args = $argv ?? [];

if (in_array('--status', $args, true)) {
    $applied = Migrator::applied();
    $pending = Migrator::pending();
    echo "Angewendet (" . count($applied) . "):\n";
    foreach ($applied as $v) { echo "  [x] $v\n"; }
    echo "Offen (" . count($pending) . "):\n";
    foreach ($pending as $v) { echo "  [ ] $v\n"; }
    exit(0);
}

if (in_array('--mark-baseline', $args, true)) {
    $marked = Migrator::markBaseline();
    if ($marked) {
        echo "Als Baseline markiert (nicht ausgefuehrt):\n";
        foreach ($marked as $v) { echo "  $v\n"; }
    } else {
        echo "Nichts zu markieren — alle Migrationen bereits protokolliert.\n";
    }
    exit(0);
}

try {
    $r = Migrator::run();
    if ($r['applied']) {
        echo "Angewendet:\n";
        foreach ($r['applied'] as $v) { echo "  $v\n"; }
    } else {
        echo "Keine offenen Migrationen — Datenbank ist aktuell.\n";
    }
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "FEHLER: " . $e->getMessage() . "\n");
    exit(1);
}
