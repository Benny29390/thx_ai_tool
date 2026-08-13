<?php
/**
 * RerankService — Cross-Encoder-Reranking fuer die RAG-Trefferliste.
 *
 * Breit retrieven (viele Kandidaten) -> Reranker sortiert nach echter Relevanz zur Frage neu
 * -> nur die Top-N gehen ans LLM. Reduziert Rauschen und hebt die wirklich passenden Stellen
 * nach oben (siehe docs/rag-optimierung.md).
 *
 * Einheitliche Cohere-/Jina-/Voyage-kompatible API:
 *   POST { model, query, documents: [strings], top_n }
 *   -> { results|data: [ { index, relevance_score|score } ] }
 * Damit funktionieren lokal (z.B. Infinity/TEI mit Cohere-kompatiblem /rerank, bge-reranker-v2-m3),
 * Cohere, Jina und Voyage ueber denselben Code-Pfad — nur Base-URL/Key/Modell unterscheiden sich.
 */

namespace Services;

class RerankService
{
    private string $provider;
    private string $url;
    private string $model;
    private string $apiKey;

    public function __construct(string $provider, string $url, string $model, string $apiKey)
    {
        $this->provider = $provider;
        $this->url = $url;
        $this->model = $model;
        $this->apiKey = $apiKey;
    }

    /** Voreingestellte Endpoint-URL je Cloud-Anbieter (lokal wird frei konfiguriert). */
    public static function defaultUrlFor(string $provider): string
    {
        return match ($provider) {
            'cohere' => 'https://api.cohere.com/v2/rerank',
            'jina'   => 'https://api.jina.ai/v1/rerank',
            'voyage' => 'https://api.voyageai.com/v1/rerank',
            default  => '', // local/custom: URL muss in den Einstellungen gesetzt werden
        };
    }

    /** Empfohlenes Standard-Modell je Anbieter. */
    public static function defaultModelFor(string $provider): string
    {
        return match ($provider) {
            'cohere' => 'rerank-v3.5',
            'jina'   => 'jina-reranker-v2-base-multilingual',
            'voyage' => 'rerank-2.5',
            default  => 'bge-reranker-v2-m3',
        };
    }

    /**
     * Dokumente nach Relevanz zur Frage neu sortieren.
     *
     * @param string[] $documents Chunk-Texte in aktueller Reihenfolge
     * @return array<int,array{index:int,score:float}> Top-N, beste zuerst. Leeres Array = nicht angewandt.
     */
    public function rerank(string $query, array $documents, int $topN): array
    {
        $documents = array_values($documents);
        if (trim($query) === '' || count($documents) < 2) return [];
        if ($this->url === '') {
            throw new \RuntimeException('Rerank-URL nicht konfiguriert');
        }

        $payload = [
            'model'     => $this->model,
            'query'     => $query,
            'documents' => $documents,
            'top_n'     => max(1, $topN),
        ];

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array_values(array_filter([
                'Content-Type: application/json',
                'Accept: application/json',
                $this->apiKey !== '' ? 'Authorization: Bearer ' . $this->apiKey : null,
            ])),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err !== '') {
            throw new \RuntimeException('Rerank cURL-Fehler: ' . $err);
        }
        if ($code !== 200) {
            throw new \RuntimeException("Rerank API Fehler ({$code}): " . mb_substr((string)$resp, 0, 300));
        }

        $data = json_decode((string)$resp, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Rerank-Antwort unparsbar');
        }
        return self::parseResults($data, $topN);
    }

    /**
     * Antwort-Body in [{index, score}] (beste zuerst, max topN) ueberfuehren.
     * Deckt Cohere/Jina ('results') und Voyage ('data') sowie 'relevance_score'/'score' ab.
     */
    public static function parseResults(array $data, int $topN): array
    {
        $items = $data['results'] ?? $data['data'] ?? null;
        if (!is_array($items)) {
            throw new \RuntimeException('Rerank-Antwort ohne results/data');
        }

        $out = [];
        foreach ($items as $it) {
            if (!is_array($it) || !isset($it['index'])) continue;
            $score = $it['relevance_score'] ?? $it['score'] ?? 0;
            $out[] = ['index' => (int)$it['index'], 'score' => (float)$score];
        }
        // API liefert i.d.R. bereits sortiert; zur Sicherheit nach Score desc.
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, max(1, $topN));
    }
}
