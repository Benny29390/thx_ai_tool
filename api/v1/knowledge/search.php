<?php
/**
 * Knowledge Search API — Hybrid Retrieval V2 (bge-m3 + Qdrant + MariaDB Sparse/Graph + RRF).
 * Kein V1-Fallback: wenn lokaler Embedding-Server oder Qdrant down ist, kommt ein klarer Fehler.
 */

use Core\Auth;
use Core\Response;
use Core\Settings;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once SERVICES_PATH . '/QdrantClient.php';
require_once SERVICES_PATH . '/LocalEmbeddingService.php';
require_once SERVICES_PATH . '/KnowledgeRetrievalServiceV2.php';

$query = trim($input['query'] ?? '');
if (mb_strlen($query) < 2) Response::error('Query zu kurz');

$customerId = isset($input['customer_id']) && $input['customer_id'] !== '' ? (int) $input['customer_id'] : null;
$topK = (int) ($input['top_k'] ?? \Services\KnowledgeRetrievalServiceV2::FINAL_K);
$topK = max(1, min(50, $topK));

// Sicherheits-Filter: Non-Admin sieht nur Wissen aus seinen effektiven Kunden + Global.
$allowedCustomerIds = null;
if (!Auth::isAdmin()) {
    $allowedCustomerIds = array_map(fn($c) => (int)$c['id'], Auth::customers());
    if ($customerId !== null && !in_array($customerId, $allowedCustomerIds, true)) {
        $customerId = null;
    }
}

// V2-Services aus Settings bauen (lokaler Embedding-Server + Qdrant).
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = Settings::decryptMap($settings);

$localKey = $settings['local_api_key'] ?? '';
if (empty($localKey)) {
    Response::error('Lokaler Embedding-API-Key (local_api_key) ist nicht konfiguriert', 503);
}

$dim = (int)($settings['embedding_local_dim'] ?? 1024) ?: 1024;

try {
    $qdrant = new \Services\QdrantClient(
        $settings['qdrant_url'] ?? 'http://localhost:6333',
        $settings['qdrant_api_key'] ?? '',
        'knowledge_bge_m3',
        $dim
    );
    $embedder = new \Services\LocalEmbeddingService(
        $settings['embedding_local_url'] ?? 'https://ki.thoxan.com/embeddings/embeddings',
        $localKey,
        $settings['embedding_local_model'] ?? 'bge-m3',
        $dim
    );
    $retrieval = new \Services\KnowledgeRetrievalServiceV2($db, $qdrant, $embedder);
} catch (\Throwable $e) {
    Response::error('Wissensdatenbank-Initialisierung fehlgeschlagen: ' . $e->getMessage(), 503);
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

try {
    $chunks = $retrieval->retrieve($query, $customerId, $topK, $allowedCustomerIds);

    $result = array_map(fn($c) => [
        'chunk_id' => (int) $c['id'],
        'document_id' => (int) $c['document_id'],
        'title' => $c['title'],
        'category' => $c['category'],
        'customer_name' => $c['customer_name'] ?? null,
        'content_preview' => mb_substr($c['content'], 0, 300) . (mb_strlen($c['content']) > 300 ? '...' : ''),
        'score' => round($c['score'], 4),
        'sources' => $c['sources'],
    ], $chunks);

    Response::success([
        'query' => $query,
        'count' => count($result),
        'chunks' => $result,
    ]);
} catch (\Throwable $e) {
    Response::error('Suche fehlgeschlagen: ' . $e->getMessage(), 503);
}
