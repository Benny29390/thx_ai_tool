<?php
/**
 * KI Text Tool - Einstiegspunkt
 */

// Fehler anzeigen waehrend Entwicklung
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Konstanten laden
require_once __DIR__ . '/config/constants.php';

// Wartungsmodus: waehrend eines Updates (scripts/update.php) liegt die Datei
// storage/MAINTENANCE. Dann JEDE Anfrage sauber mit 503 abweisen — dependency-frei,
// damit es auch greift, wenn gerade Code/vendor getauscht werden. Verhindert, dass
// jemand auf einem halb aktualisierten Stand landet.
$maintenanceFile = __DIR__ . '/storage/MAINTENANCE';
if (is_file($maintenanceFile)) {
    http_response_code(503);
    header('Retry-After: 120');
    header('Content-Type: text/html; charset=utf-8');
    $msg = @file_get_contents($maintenanceFile);
    $msg = ($msg !== false && trim($msg) !== '') ? trim($msg) : 'Wartungsarbeiten — bitte in wenigen Minuten erneut versuchen.';
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Wartung</title><style>body{font-family:system-ui,sans-serif;background:#f1f5f9;'
        . 'color:#334155;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
        . '.box{background:#fff;padding:32px 40px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:420px;text-align:center}'
        . 'h1{font-size:20px;margin:0 0 8px}p{margin:0;line-height:1.5;font-size:15px}</style></head>'
        . '<body><div class="box"><h1>Kurz in Wartung</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></div></body></html>';
    exit;
}

// Composer-Autoloader (vendor/) — fuer PhpSpreadsheet, Symfony Mailer, etc.
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Request-Logging (so früh wie möglich)
require_once __DIR__ . '/core/RequestLogger.php';
\Core\RequestLogger::init();

// Autoloader
spl_autoload_register(function ($class) {
    // Namespace zu Pfad
    $prefix = '';
    $baseDir = ROOT_PATH . '/';

    // Namespace-Mapping
    $namespaces = [
        'Core\\' => 'core/',
        'Models\\' => 'models/',
        'Services\\' => 'services/',
        'Modules\\' => 'modules/'
    ];

    foreach ($namespaces as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $relativeClass = substr($class, strlen($namespace));
            $file = $baseDir . $dir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// API-Anfragen separat verarbeiten
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, '/api/') === 0) {
    require_once ROOT_PATH . '/api/handler.php';
    exit;
}

// Anwendung starten
$app = \Core\App::getInstance();
$app->run();
