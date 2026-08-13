<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(600);
ini_set('memory_limit', '512M');

require_once dirname(__DIR__) . '/config/constants.php';
spl_autoload_register(function ($class) {
    foreach (['Core\\' => 'core/', 'Services\\' => 'services/'] as $n => $d) {
        if (strpos($class, $n) === 0) {
            $f = ROOT_PATH . '/' . $d . str_replace('\\', '/', substr($class, strlen($n))) . '.php';
            if (file_exists($f)) { require_once $f; return; }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
$db = \Core\Database::getInstance($config['db']);
$svc = new \Services\CrmPflegeDetectorService($db);

echo "[" . date('H:i:s') . "] CRM-Pflege-Scan startet …\n\n";
$t0 = microtime(true);
$stats = $svc->runAll();
$elapsed = round(microtime(true) - $t0, 2);

echo "─── Ergebnis nach {$elapsed}s ───\n";
$total = 0;
foreach ($stats as $typ => $count) {
    printf("  %-22s %5d\n", $typ, $count);
    $total += $count;
}
echo "─────────────────────────────\n";
printf("  TOTAL Issues:          %5d\n", $total);

echo "\nNach Schwere/Typ:\n";
foreach ($svc->getStatsByTyp() as $r) {
    printf("  %-22s [%s] %d\n", $r['typ'], $r['schwere'], $r['anzahl']);
}
