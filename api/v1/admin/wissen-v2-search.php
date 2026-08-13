<?php
/**
 * GET /api/v1/admin/wissen-v2-search?q=...&customer_id=... — Diagnostische Suche
 * gegen die Wissensdatenbank (bge-m3 + Qdrant). Admin-only.
 *
 * Antwort-Schema bleibt aus Rueckwaerts-Kompatibilitaet ('v2' als Key), V1 ist tot.
 */

use Core\Response;
use Core\Settings;

$db = \Core\Database::getInstance();

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    Response::error('Query zu kurz (mindestens 2 Zeichen).');
}
$customerId = (isset($_GET['customer_id']) && $_GET['customer_id'] !== '') ? (int) $_GET['customer_id'] : null;
$topK = 10;

$fmt = function (array $chunks): array {
    return array_map(function ($c) {
        return [
            'chunk_id'    => (int) ($c['id'] ?? 0),
            'document_id' => (int) ($c['document_id'] ?? 0),
            'title'       => (string) ($c['title'] ?? ''),
            'category'    => (string) ($c['category'] ?? ''),
            'customer'    => $c['customer_name'] ?? null,
            'score'       => round((float) ($c['score'] ?? 0), 4),
            'sources'     => $c['sources'] ?? [],
            'preview'     => mb_substr(trim((string) ($c['content'] ?? '')), 0, 220),
        ];
    }, $chunks);
};

$v2 = []; $v2err = null; $v2ms = 0;
try {
    $t = microtime(true);
    $dim = (int) (Settings::get('embedding_local_dim', '1024') ?: 1024);
    $qd = new \Services\QdrantClient(
        (string) Settings::get('qdrant_url', 'http://localhost:6333'),
        (string) Settings::get('qdrant_api_key', ''),
        'knowledge_bge_m3',
        $dim
    );
    $le = new \Services\LocalEmbeddingService(
        (string) Settings::get('embedding_local_url', 'https://ki.thoxan.com/embeddings/embeddings'),
        (string) Settings::get('local_api_key', ''),
        (string) Settings::get('embedding_local_model', 'bge-m3'),
        $dim
    );
    $r2 = new \Services\KnowledgeRetrievalServiceV2($db, $qd, $le);
    $v2 = $r2->retrieve($q, $customerId, $topK, null);
    $v2ms = (int) round((microtime(true) - $t) * 1000);
} catch (\Throwable $e) { $v2err = $e->getMessage(); }

Response::success([
    'query'       => $q,
    'customer_id' => $customerId,
    'v2' => ['label' => 'Wissensdatenbank', 'ms' => $v2ms, 'results' => $fmt($v2), 'error' => $v2err],
]);
