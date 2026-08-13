<?php
/**
 * KI Text Tool - Einstiegspunkt
 */

// Fehler anzeigen waehrend Entwicklung
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Konstanten laden
require_once __DIR__ . '/config/constants.php';

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
