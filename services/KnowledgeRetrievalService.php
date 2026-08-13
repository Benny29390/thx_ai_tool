<?php
/**
 * Knowledge Retrieval Service — Hybrid Search (Dense + Sparse + Graph + RRF)
 *
 * Nach Karl Kratz: Drei Suchstrategien werden per Reciprocal Rank Fusion kombiniert.
 *
 * V1 (führend): Dense via OpenAI text-embedding-3-small (1536) + MariaDB VECTOR.
 * Die embedding-unabhängigen Teile (retrieve/sparse/graph/rrf/loadChunkDetails)
 * liegen im KnowledgeRetrievalHybridTrait und werden mit V2 geteilt.
 */

namespace Services;

use Core\Database;

class KnowledgeRetrievalService
{
    use KnowledgeRetrievalHybridTrait;

    private Database $db;
    private KnowledgeEmbeddingService $embeddingService;

    // Konfigurable Parameter
    public const RRF_K = 60;                  // RRF Damping
    public const TOP_K_PER_STRATEGY = 20;     // Kandidaten pro Search-Strategie
    public const FINAL_K = 10;                // Finale Anzahl Chunks
    public const COSINE_THRESHOLD = 0.55;     // Minimum Dense-Score
    public const GRAPH_SEED_COUNT = 3;        // Wieviele Top-Dense-Results als Graph-Seeds
    public const GRAPH_DEFAULT_SCORE = 0.5;   // Fixer Score fuer Graph-Results

    public function __construct(Database $db, KnowledgeEmbeddingService $embeddingService)
    {
        $this->db = $db;
        $this->embeddingService = $embeddingService;
    }

    /**
     * Dense (Vector) Search — V1: OpenAI-Embedding + MariaDB VECTOR (HNSW).
     */
    public function denseSearch(string $query, ?int $customerId): array
    {
        try {
            $queryVec = $this->embeddingService->embed($query);
            if (empty($queryVec['vector'])) return [];

            return $this->embeddingService->denseSearch(
                $this->db,
                $queryVec['vector'],
                $customerId,
                self::TOP_K_PER_STRATEGY,
                self::COSINE_THRESHOLD
            );
        } catch (\Exception $e) {
            error_log('Knowledge denseSearch Fehler: ' . $e->getMessage());
            return [];
        }
    }
}
