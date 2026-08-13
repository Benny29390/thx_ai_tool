<?php
/**
 * LocalEmbeddingService — OpenAI-kompatibler Embeddings-Client fuer den lokalen
 * bge-m3-Server (z.B. https://ki.thoxan.com/embeddings/embeddings, 1024 Dim).
 *
 * Bewusst getrennt von KnowledgeEmbeddingService gehalten, damit der fuehrende
 * V1-Pfad (OpenAI 1536 + MariaDB VECTOR) vollstaendig unberuehrt bleibt.
 */

namespace Services;

class LocalEmbeddingService
{
    private string $url;
    private string $apiKey;
    private string $model;
    private int $dimensions;
    private const BATCH_SIZE = 64;

    /** @var int Akkumulierte Embedding-Tokens (fuer Sync-Jobs / Usage) */
    public int $tokensUsed = 0;

    public function __construct(string $url, string $apiKey, string $model = 'bge-m3', int $dimensions = 1024)
    {
        $this->url = $url;
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->dimensions = $dimensions;
    }

    public function getModel(): string { return $this->model; }
    public function getDimensions(): int { return $this->dimensions; }

    /**
     * Einzelnen Text embedden. Returnt ['vector' => float[]] oder [].
     */
    public function embed(string $text): array
    {
        $r = $this->embedBatch([$text]);
        return $r[0] ?? [];
    }

    /**
     * Batch-Embedding gegen den OpenAI-kompatiblen /embeddings-Endpoint.
     * @param string[] $texts
     * @return array Liste von ['vector' => float[]] in Eingabe-Reihenfolge
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) return [];

        $results = [];
        foreach (array_chunk($texts, self::BATCH_SIZE) as $chunk) {
            $ch = curl_init($this->url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $this->model,
                    'input' => array_values($chunk),
                ]),
                CURLOPT_HTTPHEADER => array_values(array_filter([
                    'Content-Type: application/json',
                    'Accept: application/json',
                    $this->apiKey !== '' ? 'Authorization: Bearer ' . $this->apiKey : null,
                ])),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT => 300, // erster Call kann das Modell laden
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($resp === false || $err !== '') {
                throw new \Exception('Lokaler Embedding cURL-Fehler: ' . $err);
            }
            if ($code !== 200) {
                throw new \Exception("Lokaler Embedding API Fehler ({$code}): " . mb_substr((string)$resp, 0, 300));
            }

            $data = json_decode((string)$resp, true);
            if (!is_array($data) || !isset($data['data'])) {
                throw new \Exception('Lokaler Embedding-Output unparsbar: ' . mb_substr((string)$resp, 0, 300));
            }
            $this->tokensUsed += (int) ($data['usage']['total_tokens'] ?? 0);

            // Nach 'index' sortieren, damit die Reihenfolge zur Eingabe passt
            $items = $data['data'];
            usort($items, fn($a, $b) => ((int)($a['index'] ?? 0)) <=> ((int)($b['index'] ?? 0)));
            foreach ($items as $item) {
                $vec = $item['embedding'] ?? $item['vector'] ?? null;
                if (!is_array($vec)) {
                    throw new \Exception('Embedding-Antwort ohne Vektor-Feld');
                }
                $results[] = ['vector' => $vec];
            }
        }

        return $results;
    }
}
