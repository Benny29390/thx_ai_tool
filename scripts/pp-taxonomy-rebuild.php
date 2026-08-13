<?php
/**
 * Cron-Skript: Section-Taxonomy neu aufbauen.
 * Laeuft stuendlich.
 *
 *   php scripts/pp-taxonomy-rebuild.php
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(function ($class) {
    $ns = ['Core\\' => 'core/', 'Services\\' => 'services/'];
    foreach ($ns as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);

$svc = new \Services\PpTaxonomyService(\Core\Database::getInstance());
$start = microtime(true);
$r = $svc->rebuild();
$dur = round(microtime(true) - $start, 1);
echo date('Y-m-d H:i:s') . " — Taxonomy rebuilt: {$r['customers']} Kunden, {$r['taxonomy_rows']} Sektionen ({$dur}s)\n";
