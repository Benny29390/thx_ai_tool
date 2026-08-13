#!/usr/bin/env php
<?php
/**
 * Migrate Vectors — fuellt knowledge_embeddings.vector_v aus den BLOB-Vektoren.
 *
 * Idempotent: bearbeitet nur Zeilen wo vector_v NULL ist.
 * Aufruf: php /var/www/cli/migrate-vectors.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');

require_once dirname(__DIR__) . '/config/constants.php';

spl_autoload_register(function ($class) {
    $namespaces = ['Core\\' => 'core/', 'Services\\' => 'services/'];
    foreach ($namespaces as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($namespace))) . '.php';
            if (file_exists($file)) require_once $file;
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
$db = \Core\Database::getInstance($config['db']);
$pdo = $db->connect();

$now = date('Y-m-d H:i:s');
echo "[{$now}] Vector-Migration gestartet\n";

// Wie viele insgesamt?
$total = (int) $db->queryValue(
    "SELECT COUNT(*) FROM knowledge_embeddings WHERE vector_v IS NULL"
);
echo "[{$now}] {$total} Embeddings ohne vector_v\n";

if ($total === 0) {
    echo "Nichts zu tun.\n";
    exit(0);
}

$batchSize = 200;
$processed = 0;
$failed = 0;
$startTs = microtime(true);

// Unbuffered Iteration ueber alle Zeilen
$pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
$selectStmt = $pdo->prepare(
    "SELECT chunk_id, `vector`, dimensions FROM knowledge_embeddings WHERE vector_v IS NULL"
);
$selectStmt->execute();

// Update-Statement vorbereiten (auf NEUER Connection, da Cursor blockiert)
$updatePdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['port'] ?? 3306,
        $config['db']['name'],
        $config['db']['charset'] ?? 'utf8mb4'),
    $config['db']['user'],
    $config['db']['pass'],
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$updateStmt = $updatePdo->prepare(
    "UPDATE knowledge_embeddings SET vector_v = VEC_FromText(?) WHERE chunk_id = ?"
);

while ($row = $selectStmt->fetch(\PDO::FETCH_ASSOC)) {
    $unpacked = unpack('f*', $row['vector']);
    if (!$unpacked) {
        $failed++;
        continue;
    }

    // VEC_FromText erwartet "[f1,f2,...,fN]"
    // Werte mit ausreichender Praezision serialisieren
    $vals = array_values($unpacked);
    $jsonText = '[' . implode(',', array_map(fn($v) => sprintf('%.7g', $v), $vals)) . ']';

    try {
        $updateStmt->execute([$jsonText, (int) $row['chunk_id']]);
        $processed++;
    } catch (\Exception $e) {
        $failed++;
        error_log("Vector migration chunk_id={$row['chunk_id']}: " . $e->getMessage());
    }
    unset($unpacked, $vals, $jsonText);

    if ($processed > 0 && $processed % $batchSize === 0) {
        $elapsed = microtime(true) - $startTs;
        $rate = $processed / max(0.001, $elapsed);
        $eta = ($total - $processed) / max(0.001, $rate);
        echo sprintf("[%s] %d/%d (%.1f%%) — %.1f/s — ETA %.0fs\n",
            date('H:i:s'), $processed, $total, ($processed / $total) * 100, $rate, $eta);
    }
}
$selectStmt->closeCursor();

$elapsed = microtime(true) - $startTs;
echo sprintf("[%s] Fertig — %d migriert, %d Fehler in %.1fs\n",
    date('Y-m-d H:i:s'), $processed, $failed, $elapsed);

// Wenn alle migriert: HNSW-Index anlegen
$remaining = (int) $db->queryValue("SELECT COUNT(*) FROM knowledge_embeddings WHERE vector_v IS NULL");
if ($remaining === 0) {
    echo "[" . date('H:i:s') . "] Lege HNSW-Index an…\n";
    try {
        $db->execute("ALTER TABLE knowledge_embeddings ADD VECTOR INDEX idx_vector_v (vector_v)");
        echo "✓ HNSW-Index angelegt.\n";
    } catch (\Exception $e) {
        if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), 'exists') !== false) {
            echo "Index existiert bereits.\n";
        } else {
            echo "Fehler beim Index: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "Es sind noch {$remaining} Zeilen offen — Index wird nicht angelegt.\n";
}
