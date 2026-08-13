<?php
/**
 * QdrantClient — minimaler HTTP-Client fuer die Qdrant REST-API.
 *
 * Wird vom parallelen "Wissen V2"-System genutzt (Dense-Suche mit bge-m3, 1024 Dim).
 * Point-ID = chunk_id (Integer) → garantiert Konsistenz mit MariaDB als Join-Key.
 * Tenant-Konvention im Payload: customer_id = <int> oder 0 fuer globales Wissen
 * (in MariaDB ist globales Wissen customer_id NULL). MariaDB bleibt der autoritative
 * Sicherheits-Backstop; der Qdrant-Filter ist nur eine Optimierung.
 */

namespace Services;

class QdrantClient
{
    private string $base;
    private ?string $apiKey;
    private string $collection;
    private int $dim;

    public function __construct(string $url, ?string $apiKey, string $collection = 'knowledge_bge_m3', int $dim = 1024)
    {
        $this->base = rtrim($url, '/');
        $this->apiKey = ($apiKey !== null && $apiKey !== '') ? $apiKey : null;
        $this->collection = $collection;
        $this->dim = $dim;
    }

    public function getCollection(): string { return $this->collection; }

    private function request(string $method, string $path, ?array $body = null, int $timeout = 30): array
    {
        $ch = curl_init($this->base . $path);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->apiKey !== null) $headers[] = 'api-key: ' . $this->apiKey;
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
        ];
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err !== '') {
            throw new \Exception('Qdrant cURL-Fehler: ' . $err);
        }
        $data = json_decode((string)$resp, true);
        if ($code >= 400) {
            $msg = is_array($data)
                ? ($data['status']['error'] ?? json_encode($data['status'] ?? $data))
                : mb_substr((string)$resp, 0, 300);
            throw new \Exception("Qdrant HTTP {$code}: " . $msg, $code);
        }
        return is_array($data) ? $data : [];
    }

    public function health(): bool
    {
        try { $this->request('GET', '/healthz', null, 8); return true; }
        catch (\Throwable $e) {
            try { $this->request('GET', '/collections', null, 8); return true; }
            catch (\Throwable $e2) { return false; }
        }
    }

    public function collectionExists(): bool
    {
        try { $this->request('GET', '/collections/' . $this->collection, null, 10); return true; }
        catch (\Throwable $e) {
            if ($e->getCode() === 404) return false;
            throw $e;
        }
    }

    public function ensureCollection(): void
    {
        if ($this->collectionExists()) return;
        $this->request('PUT', '/collections/' . $this->collection, [
            'vectors' => ['size' => $this->dim, 'distance' => 'Cosine'],
        ]);
        // Payload-Indizes fuer schnellere Filter (optional, Fehler ignorieren)
        foreach (['customer_id', 'document_id'] as $field) {
            try {
                $this->request('PUT', '/collections/' . $this->collection . '/index?wait=true', [
                    'field_name'   => $field,
                    'field_schema' => 'integer',
                ]);
            } catch (\Throwable $e) { /* Index optional */ }
        }
    }

    public function collectionInfo(): array
    {
        $r = $this->request('GET', '/collections/' . $this->collection, null, 10);
        return $r['result'] ?? [];
    }

    public function pointsCount(): int
    {
        $info = $this->collectionInfo();
        return (int) ($info['points_count'] ?? 0);
    }

    /**
     * @param array $points Liste von ['id'=>int, 'vector'=>float[], 'payload'=>array]
     */
    public function upsertPoints(array $points): void
    {
        if (empty($points)) return;
        $this->request('PUT', '/collections/' . $this->collection . '/points?wait=true', ['points' => $points], 120);
    }

    /**
     * Vektor-Suche. Returnt Liste von ['chunk_id'=>int, 'score'=>float] (Cosine-Similarity).
     */
    public function search(array $vector, ?int $customerId, int $topK, ?array $allowedCustomerIds = null): array
    {
        $body = ['vector' => array_values($vector), 'limit' => $topK, 'with_payload' => true];
        $filter = $this->buildFilter($customerId, $allowedCustomerIds);
        if ($filter !== null) $body['filter'] = $filter;

        $r = $this->request('POST', '/collections/' . $this->collection . '/points/search', $body, 30);
        $out = [];
        foreach (($r['result'] ?? []) as $hit) {
            $cid = $hit['payload']['chunk_id'] ?? $hit['id'] ?? null;
            if ($cid === null) continue;
            $out[] = ['chunk_id' => (int)$cid, 'score' => (float)($hit['score'] ?? 0)];
        }
        return $out;
    }

    public function deleteByDocument(int $documentId): void
    {
        $this->request('POST', '/collections/' . $this->collection . '/points/delete?wait=true', [
            'filter' => ['must' => [['key' => 'document_id', 'match' => ['value' => $documentId]]]],
        ], 60);
    }

    /**
     * Tenant-Filter analog V1: Kunde X ODER global (customer_id 0).
     * MariaDB ist der autoritative Backstop; dies ist nur Vorfilterung.
     */
    private function buildFilter(?int $customerId, ?array $allowedCustomerIds): ?array
    {
        if ($customerId !== null) {
            // Reine should-Klauseln => Qdrant verlangt, dass mindestens eine
            // zutrifft (Kunde X ODER global=0). KEIN 'minimum_should' setzen:
            // dieses Feld kennt die aktuelle Qdrant-Version nicht und sie
            // quittierte den gesamten Dense-Search mit HTTP 400 (Bugfix 09.06.2026).
            return ['should' => [
                ['key' => 'customer_id', 'match' => ['value' => (int)$customerId]],
                ['key' => 'customer_id', 'match' => ['value' => 0]],
            ]];
        }
        if ($allowedCustomerIds !== null) {
            $vals = array_values(array_unique(array_map('intval', $allowedCustomerIds)));
            $vals[] = 0; // globales Wissen
            return ['must' => [['key' => 'customer_id', 'match' => ['any' => $vals]]]];
        }
        return null; // Admin / global: kein Filter
    }
}
