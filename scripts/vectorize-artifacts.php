<?php
/**
 * Batch-Vektorisierung fuer Artefakte
 *
 * Aufruf: php scripts/vectorize-artifacts.php [--force]
 *
 * Optionen:
 *   --force   Auch bereits vektorisierte Artefakte neu vektorisieren
 */

require_once dirname(__DIR__) . '/config/constants.php';

// Autoloader
spl_autoload_register(function ($class) {
    $namespaces = [
        'Core\\' => 'core/',
        'Models\\' => 'models/',
        'Services\\' => 'services/'
    ];

    foreach ($namespaces as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $relativeClass = substr($class, strlen($namespace));
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

use Core\Database;

// Config laden
$configFile = CONFIG_PATH . '/config.php';
if (!file_exists($configFile)) {
    die("Fehler: config.php nicht gefunden\n");
}

$config = require $configFile;
$db = Database::getInstance($config['db']);

// CLI-Argumente
$force = in_array('--force', $argv ?? []);

// OpenAI API-Key
$apiKey = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
$apiKey = $apiKey['setting_value'] ?? '';
if (empty($apiKey)) {
    die("Fehler: OpenAI API-Key nicht konfiguriert\n");
}

// Services
$ai = new \Services\AIService($apiKey, 'openai');
$embeddings = new \Services\EmbeddingService($db);
$embeddings->setAIService($ai);
$entityService = new \Services\EntityService($db);
$artifactService = new \Services\ArtifactService($db, $entityService);

// Artefakte laden
$where = $force ? '1=1' : 'embedding_id IS NULL';
$artifacts = $db->query(
    "SELECT id, slug FROM artifacts WHERE {$where}
     AND (JSON_EXTRACT(meta, '$.is_active') IS NULL OR JSON_EXTRACT(meta, '$.is_active') != false)
     ORDER BY id"
);

$total = count($artifacts);
echo "Artefakte zu vektorisieren: {$total}" . ($force ? " (force)" : "") . "\n";

if ($total === 0) {
    echo "Nichts zu tun.\n";
    exit(0);
}

$success = 0;
$errors = 0;

foreach ($artifacts as $i => $art) {
    $num = $i + 1;
    echo "[{$num}/{$total}] {$art['slug']} ... ";

    try {
        $embId = $artifactService->vectorize((int) $art['id'], $embeddings);
        if ($embId) {
            echo "OK ({$embId})\n";
            $success++;
        } else {
            echo "SKIP (zu kurz)\n";
        }
    } catch (\Exception $e) {
        echo "FEHLER: " . $e->getMessage() . "\n";
        $errors++;
    }

    usleep(100000); // Rate-Limiting: 100ms
}

echo "\nFertig: {$success} vektorisiert, {$errors} Fehler\n";
