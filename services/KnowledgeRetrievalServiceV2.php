<?php
/**
 * KnowledgeRetrievalServiceV2 — paralleles Wissenssystem (Shadow).
 *
 * Identisch zu V1, AUSSER der Dense-Suche: hier bge-m3 (lokal, 1024 Dim) + Qdrant
 * statt OpenAI + MariaDB VECTOR. Sparse (FULLTEXT) und Graph (Entitäten) sowie
 * RRF-Fusion werden über KnowledgeRetrievalHybridTrait 1:1 aus MariaDB wiederverwendet.
 * loadChunkDetails (im Trait) liest die kanonischen Chunks aus MariaDB und ist zugleich
 * der autoritative Sicherheits-Backstop (allowedCustomerIds).
 *
 * NICHT in den Hot-Path (Chat/Wissen-Suche) eingebunden — nur über /admin/wissen-v2.
 */

namespace Services;

use Core\Database;

class KnowledgeRetrievalServiceV2
{
    use KnowledgeRetrievalHybridTrait;

    private Database $db;
    private QdrantClient $qdrant;
    private LocalEmbeddingService $embedder;

    // Gleiche Fusion-/Schwellen-Parameter wie V1 (für fairen A/B-Vergleich)
    public const RRF_K = 60;
    public const TOP_K_PER_STRATEGY = 20;
    public const FINAL_K = 10;
    public const COSINE_THRESHOLD = 0.55;
    public const GRAPH_SEED_COUNT = 3;
    public const GRAPH_DEFAULT_SCORE = 0.5;

    public function __construct(Database $db, QdrantClient $qdrant, LocalEmbeddingService $embedder)
    {
        $this->db = $db;
        $this->qdrant = $qdrant;
        $this->embedder = $embedder;
    }

    /**
     * Dense (Vector) Search — V2: bge-m3-Embedding + Qdrant.
     * Gleiche Cosine-Schwelle wie V1, damit der Vergleich fair ist.
     */
    public function denseSearch(string $query, ?int $customerId): array
    {
        try {
            $qv = $this->embedder->embed($query);
            if (empty($qv['vector'])) return [];

            $hits = $this->qdrant->search($qv['vector'], $customerId, self::TOP_K_PER_STRATEGY);

            $out = [];
            foreach ($hits as $h) {
                if (($h['score'] ?? 0) >= self::COSINE_THRESHOLD) {
                    $out[] = ['chunk_id' => (int)$h['chunk_id'], 'score' => (float)$h['score']];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Wissen-V2 denseSearch Fehler: ' . $e->getMessage());
            return [];
        }
    }
}
