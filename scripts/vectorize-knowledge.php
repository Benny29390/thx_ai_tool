<?php
/**
 * Batch-Vektorisierung - Vektorisiert alle Wissenseintraege ohne Embedding
 *
 * Aufruf: php scripts/vectorize-knowledge.php [--customer=ID] [--force]
 *
 * Optionen:
 *   --customer=ID  Nur Eintraege eines bestimmten Kunden vektorisieren
 *   --force        Auch bereits vektorisierte Eintraege neu vektorisieren
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

// CLI-Argumente parsen
$customerId = null;
$force = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--customer=') === 0) {
        $customerId = (int) substr($arg, 11);
    }
    if ($arg === '--force') {
        $force = true;
    }
}

// API Key pruefen
$apiKey = $db->queryValue("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
if (empty($apiKey)) {
    die("Fehler: OpenAI API Key nicht konfiguriert\n");
}

echo "=== Batch-Vektorisierung ===\n\n";

// Services initialisieren
require_once SERVICES_PATH . '/AIService.php';
require_once SERVICES_PATH . '/EmbeddingService.php';
require_once SERVICES_PATH . '/RAGService.php';

$ai = new \Services\AIService($apiKey, 'openai');
$embedding = new \Services\EmbeddingService($db);
$embedding->setAIService($ai);
$rag = new \Services\RAGService($db, $embedding);
$rag->setAIService($ai);

// Eintraege ohne Embedding laden
$where = $force ? '' : 'WHERE ke.embedding_id IS NULL';
$params = [];

if ($customerId) {
    $where = $force
        ? 'WHERE kb.customer_id = ?'
        : 'WHERE ke.embedding_id IS NULL AND kb.customer_id = ?';
    $params[] = $customerId;
}

$entries = $db->query(
    "SELECT ke.*, kb.customer_id, c.slug as customer_slug, c.name as customer_name,
            kc.name as category_name, kc.usage_hint as category_hint
     FROM knowledge_entries ke
     JOIN knowledge_bases kb ON ke.knowledge_base_id = kb.id
     JOIN customers c ON kb.customer_id = c.id
     LEFT JOIN knowledge_categories kc ON ke.category_id = kc.id
     $where
     ORDER BY ke.id",
    $params
);

$total = count($entries);
echo "Gefundene Eintraege: $total\n\n";

if ($total === 0) {
    echo "Alle Eintraege sind bereits vektorisiert.\n";
    exit(0);
}

$success = 0;
$errors = 0;

foreach ($entries as $i => $entry) {
    $num = $i + 1;
    echo "[$num/$total] Verarbeite: " . ($entry['title'] ?: '(Ohne Titel)') . " (Kunde: {$entry['customer_name']})...";

    try {
        // Reicheren Text fuer Embedding erstellen
        $textParts = [];

        if ($entry['title']) {
            $textParts[] = "Titel: " . $entry['title'];
        }

        // Kategorie-Info
        if ($entry['category_name']) {
            $categoryInfo = "Kategorie: " . $entry['category_name'];
            if ($entry['category_hint']) {
                $categoryInfo .= "\nVerwendung: " . $entry['category_hint'];
            }
            $textParts[] = $categoryInfo;
        }

        // Verwendungskontext
        if ($entry['usage_context']) {
            $textParts[] = "Kontext: " . $entry['usage_context'];
        }

        // Inhalt
        $textParts[] = "\nInhalt:\n" . $entry['content'];

        $textToEmbed = implode("\n", $textParts);

        // Bestehendes Embedding loeschen falls force
        if ($force && $entry['embedding_id']) {
            $embedding->deleteEmbedding($entry['embedding_id'], $entry['customer_slug']);
        }

        // Neues Embedding erstellen
        $embeddingId = $embedding->createEmbedding($entry['id'], $textToEmbed, $entry['customer_slug']);
        $db->update('knowledge_entries', ['embedding_id' => $embeddingId], 'id = ?', [$entry['id']]);

        echo " OK\n";
        $success++;

        // Rate-Limit beachten (max 60 Requests/Minute bei OpenAI)
        usleep(100000); // 100ms Pause

    } catch (\Exception $e) {
        echo " FEHLER: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Fertig ===\n";
echo "Erfolgreich: $success\n";
echo "Fehler: $errors\n";
