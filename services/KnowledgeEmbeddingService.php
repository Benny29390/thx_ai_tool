<?php
/**
 * Knowledge Embedding Service — OpenAI Embeddings + BLOB-Packing + Cosine-Similarity
 *
 * Nutzt OpenAI text-embedding-3-small (1536 dim).
 * Vektoren werden als binary-packed float32 in MEDIUMBLOB gespeichert (6144 bytes).
 */

namespace Services;

use Core\Database;

class KnowledgeEmbeddingService
{
    private string $apiKey;
    private string $model;
    private int $dimensions;
    private const API_URL = 'https://api.openai.com/v1/embeddings';
    private const BATCH_SIZE = 100; // OpenAI erlaubt bis 2048, aber 100 ist praktischer

    public function __construct(string $apiKey, string $model = 'text-embedding-3-small')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->dimensions = $model === 'text-embedding-3-large' ? 3072 : 1536;
    }

    public function getModel(): string { return $this->model; }
    public function getDimensions(): int { return $this->dimensions; }

    /**
     * Einzelnen Text embedden
     */
    public function embed(string $text): array
    {
        $result = $this->embedBatch([$text]);
        return $result[0] ?? [];
    }

    /**
     * Batch-Embedding — mehrere Texte in einem API-Call
     * @param array $texts
     * @return array Liste von ['vector' => float[], 'norm' => float]
     */
    /** @var int Akkumulierte Embedding-Tokens (für Sync-Jobs) */
    public int $tokensUsed = 0;

    public function embedBatch(array $texts): array
    {
        if (empty($texts)) return [];

        $results = [];
        $chunks = array_chunk($texts, self::BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $this->model,
                    'input' => $chunk,
                ]),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception("OpenAI Embedding API Fehler ({$httpCode}): " . mb_substr($response, 0, 500));
            }

            $data = json_decode($response, true);
            $this->tokensUsed += (int) ($data['usage']['total_tokens'] ?? 0);
            foreach ($data['data'] ?? [] as $item) {
                $vec = $item['embedding'];
                $norm = $this->calcNorm($vec);
                $results[] = ['vector' => $vec, 'norm' => $norm];
            }
        }

        return $results;
    }

    /**
     * Berechnet L2-Norm eines Vektors (fuer vorgerechnete Cosine-Aehnlichkeit)
     */
    public function calcNorm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $v) {
            $sum += $v * $v;
        }
        return sqrt($sum);
    }

    /**
     * Cosine-Similarity zwischen zwei Vektoren
     */
    public function cosineSimilarity(array $a, array $b, ?float $normA = null, ?float $normB = null): float
    {
        if ($normA === null) $normA = $this->calcNorm($a);
        if ($normB === null) $normB = $this->calcNorm($b);
        if ($normA == 0.0 || $normB == 0.0) return 0.0;

        $dot = 0.0;
        $len = count($a);
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
        }
        return $dot / ($normA * $normB);
    }

    /**
     * Vektor als BLOB packen (float32 little-endian) — Legacy-Format
     */
    public function packVector(array $vector): string
    {
        return pack('f*', ...$vector);
    }

    /**
     * Vektor als JSON-Text fuer MariaDB VEC_FromText("[f1,f2,...]")
     */
    public function vectorToText(array $vector): string
    {
        return '[' . implode(',', array_map(fn($v) => sprintf('%.7g', (float) $v), $vector)) . ']';
    }

    /**
     * BLOB zu Vektor entpacken
     */
    public function unpackVector(string $blob): array
    {
        $arr = unpack('f*', $blob);
        return array_values($arr);
    }

    /**
     * Dense-Search ueber alle Embeddings (mit Customer-Filter)
     *
     * @param Database $db
     * @param array $queryVec
     * @param int|null $customerId
     * @param int $topK
     * @param float $threshold Minimum Cosine-Score (0..1)
     * @return array Liste von ['chunk_id' => int, 'score' => float]
     */
    public function denseSearch(Database $db, array $queryVec, ?int $customerId, int $topK = 20, float $threshold = 0.55): array
    {
        if (empty($queryVec)) return [];

        // Native MariaDB-VECTOR-Suche mit HNSW-Index (EUCLIDEAN-distance).
        // Da OpenAI text-embedding-3-small normalisierte Vektoren liefert (norm = 1.0),
        // gilt: euclidean^2(a,b) = 2 - 2*cosine(a,b)  =>  cosine = 1 - d^2/2
        // Ranking ist identisch (kleinere euclidean-distance = hoehere cosine-similarity).
        // similarity-threshold 0.55  =>  d^2 <= 2*(1-0.55) = 0.9  =>  d <= sqrt(0.9) ~= 0.9487
        $maxDistance = sqrt(max(0.0, 2.0 * (1.0 - $threshold)));

        // Vector als Text "[f1,f2,...]" fuer VEC_FromText
        $queryText = '[' . implode(',', array_map(fn($v) => sprintf('%.7g', $v), $queryVec)) . ']';

        // Hinweis zur HNSW-Limitation:
        // ORDER BY VEC_DISTANCE_EUCLIDEAN(vector_v, ?) LIMIT N nutzt den Index voll.
        // Customer-Filter via WHERE-Klausel erlaubt — der Index liefert Kandidaten,
        // WHERE filtert nach. Bei sehr vielen Customern und kleiner Treffer-Quote evtl
        // ef_search anpassen.
        if ($customerId !== null) {
            $sql = "SELECT chunk_id,
                           VEC_DISTANCE_EUCLIDEAN(vector_v, VEC_FromText(?)) AS distance
                    FROM knowledge_embeddings
                    WHERE (customer_id = ? OR customer_id IS NULL)
                    ORDER BY VEC_DISTANCE_EUCLIDEAN(vector_v, VEC_FromText(?))
                    LIMIT ?";
            $params = [$queryText, $customerId, $queryText, $topK];
        } else {
            $sql = "SELECT chunk_id,
                           VEC_DISTANCE_EUCLIDEAN(vector_v, VEC_FromText(?)) AS distance
                    FROM knowledge_embeddings
                    ORDER BY VEC_DISTANCE_EUCLIDEAN(vector_v, VEC_FromText(?))
                    LIMIT ?";
            $params = [$queryText, $queryText, $topK];
        }

        $rows = $db->query($sql, $params);

        $scores = [];
        foreach ($rows as $r) {
            $distance = (float) $r['distance'];
            if ($distance > $maxDistance) continue;
            // Cosine-Similarity zurueckrechnen fuer einheitliches Score-Format
            $cosine = 1.0 - ($distance * $distance) / 2.0;
            if ($cosine < $threshold) continue;
            $scores[] = ['chunk_id' => (int) $r['chunk_id'], 'score' => $cosine];
        }
        return $scores;
    }
}
