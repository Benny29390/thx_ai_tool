<?php
/**
 * KnowledgeRetrievalHybridTrait — die embedding-modell-UNABHÄNGIGEN Teile des
 * Hybrid-Retrievals: Orchestrierung (retrieve), Sparse (FULLTEXT), Graph (Entitäten),
 * RRF-Fusion und das Laden der Chunk-Details aus MariaDB.
 *
 * Geteilt von KnowledgeRetrievalService (V1, Dense via OpenAI/MariaDB) und
 * KnowledgeRetrievalServiceV2 (Dense via bge-m3/Qdrant). Beide Klassen implementieren
 * nur `denseSearch()` selbst und deklarieren dieselben Konstanten + `$this->db`.
 *
 * Hinweis: Konstanten liegen bewusst NICHT im Trait (Trait-Konstanten erst ab PHP 8.2);
 * `self::` in den Trait-Methoden löst zur jeweils nutzenden Klasse auf.
 */

namespace Services;

trait KnowledgeRetrievalHybridTrait
{
    /** Wer fragt? Bestimmt, ob private Dokumente ausgeliefert werden duerfen. */
    private ?int $viewerId = null;

    /**
     * Fragenden Nutzer setzen. Ohne Angabe wird auf die aktive Session zurueckgegriffen;
     * ist auch die nicht da (CLI/Cron), gilt "kein Nutzer" — private Dokumente bleiben
     * dann aussen vor (fail-closed).
     */
    public function setViewer(?int $userId): void
    {
        $this->viewerId = $userId !== null ? (int) $userId : null;
    }

    /** Effektiver Betrachter: explizit gesetzt > Session > keiner. */
    private function viewer(): ?int
    {
        if ($this->viewerId !== null) return $this->viewerId;
        if (class_exists('\Core\Auth')) {
            $uid = \Core\Auth::id();
            if (!empty($uid)) return (int) $uid;
        }
        return null;
    }

    /**
     * Sichtbarkeits-Bedingung: private Dokumente nur an ihren Besitzer.
     *
     * BEWUSST OHNE ADMIN-AUSNAHME. Sonst waere die Zusage "auch ein Administrator sieht
     * meine Mails nicht" wertlos — Admin::isAdmin() haette den Filter ausgehebelt.
     *
     * @return array{0:string,1:array} [SQL-Fragment, Parameter]
     */
    private function visibilityClause(string $docAlias = 'd'): array
    {
        $viewer = $this->viewer();
        if ($viewer === null) {
            return [" AND {$docAlias}.visibility <> 'privat'", []];
        }
        return [" AND ({$docAlias}.visibility <> 'privat' OR {$docAlias}.owner_user_id = ?)", [$viewer]];
    }

    /**
     * Hybrid-Retrieval: Dense + Sparse + Graph + RRF
     * (identisch zu V1; ruft das klassenspezifische denseSearch() auf)
     */
    public function retrieve(
        string $query,
        ?int $customerId = null,
        int $finalK = self::FINAL_K,
        ?array $allowedCustomerIds = null
    ): array {
        if (mb_strlen(trim($query)) < 2) return [];

        $denseResults  = $this->denseSearch($query, $customerId);
        $sparseResults = $this->sparseSearch($query, $customerId);
        $graphResults  = $this->graphSearch($denseResults, $customerId);

        $fused = $this->rrfFusion($denseResults, $sparseResults, $graphResults);

        // Wenn allowedCustomerIds gesetzt: mehr Kandidaten laden und hart filtern.
        $loadK = $allowedCustomerIds !== null ? max($finalK * 3, 30) : $finalK;
        $chunks = $this->loadChunkDetails(array_slice($fused, 0, $loadK));

        if ($allowedCustomerIds !== null) {
            $allowedMap = array_flip(array_map('intval', $allowedCustomerIds));
            $chunks = array_values(array_filter($chunks, function ($c) use ($allowedMap) {
                $cid = $c['customer_id'] ?? null;
                if ($cid === null) return true; // globales Wissen ist immer erlaubt
                return isset($allowedMap[(int)$cid]);
            }));
            $chunks = array_slice($chunks, 0, $finalK);
        }
        return $chunks;
    }

    /**
     * Sparse (FULLTEXT) Search — embedding-unabhängig, liest knowledge_chunks/-documents.
     */
    public function sparseSearch(string $query, ?int $customerId): array
    {
        $words = preg_split('/\s+/u', trim($query));
        $stopwords = ['der','die','das','ein','eine','und','oder','ist','sind','war','hat','haben','wird','werden','mit','von','zu','in','auf','an','fuer','aus','bei','nach','ueber','unter','vor','was','wer','wie','wo','wann','warum','nicht','auch','noch','nur','kann','soll','muss','darf','schon','aber','wenn','dann','als','ich','du','er','sie','wir','ihr','mein','dein','sein','kein','alle','the','and','for','that','with'];
        $keywords = array_filter($words, fn($w) => mb_strlen($w) >= 3 && !in_array(mb_strtolower($w), $stopwords));
        if (empty($keywords)) return [];

        $searchQuery = implode(' ', $keywords);

        [$visSql, $visParams] = $this->visibilityClause('d');

        try {
            if ($customerId !== null) {
                $rows = $this->db->query(
                    "SELECT c.id AS chunk_id,
                            MATCH(c.content) AGAINST(? IN NATURAL LANGUAGE MODE) AS score
                     FROM knowledge_chunks c
                     JOIN knowledge_documents d ON c.document_id = d.id
                     WHERE (c.customer_id = ? OR c.customer_id IS NULL)
                       AND d.is_active = 1
                       AND MATCH(c.content) AGAINST(? IN NATURAL LANGUAGE MODE) > 0
                       {$visSql}
                     ORDER BY score DESC
                     LIMIT ?",
                    array_merge([$searchQuery, $customerId, $searchQuery], $visParams, [self::TOP_K_PER_STRATEGY])
                );
            } else {
                $rows = $this->db->query(
                    "SELECT c.id AS chunk_id,
                            MATCH(c.content) AGAINST(? IN NATURAL LANGUAGE MODE) AS score
                     FROM knowledge_chunks c
                     JOIN knowledge_documents d ON c.document_id = d.id
                     WHERE d.is_active = 1
                       AND MATCH(c.content) AGAINST(? IN NATURAL LANGUAGE MODE) > 0
                       {$visSql}
                     ORDER BY score DESC
                     LIMIT ?",
                    array_merge([$searchQuery, $searchQuery], $visParams, [self::TOP_K_PER_STRATEGY])
                );
            }

            $results = [];
            foreach ($rows as $r) {
                $results[] = ['chunk_id' => (int)$r['chunk_id'], 'score' => (float)$r['score']];
            }
            return $results;
        } catch (\Exception $e) {
            error_log('Knowledge sparseSearch Fehler: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Graph Search — 1-Hop Entity-Traversal (embedding-unabhängig).
     */
    public function graphSearch(array $denseResults, ?int $customerId): array
    {
        if (empty($denseResults)) return [];

        $seedChunkIds = array_slice(array_column($denseResults, 'chunk_id'), 0, self::GRAPH_SEED_COUNT);
        if (empty($seedChunkIds)) return [];

        $seedPlaceholders = implode(',', array_fill(0, count($seedChunkIds), '?'));

        $seedEntityRows = $this->db->query(
            "SELECT DISTINCT entity_id FROM knowledge_chunk_entities
             WHERE chunk_id IN ({$seedPlaceholders})",
            $seedChunkIds
        );
        $seedEntityIds = array_column($seedEntityRows, 'entity_id');
        if (empty($seedEntityIds)) return [];

        $entPlaceholders = implode(',', array_fill(0, count($seedEntityIds), '?'));
        $connectedEntities = $this->db->query(
            "SELECT DISTINCT CASE
                WHEN from_entity_id IN ({$entPlaceholders}) THEN to_entity_id
                ELSE from_entity_id
             END AS connected_id
             FROM knowledge_relations
             WHERE from_entity_id IN ({$entPlaceholders}) OR to_entity_id IN ({$entPlaceholders})",
            array_merge($seedEntityIds, $seedEntityIds, $seedEntityIds)
        );
        $connectedIds = array_unique(array_merge($seedEntityIds, array_column($connectedEntities, 'connected_id')));

        if (empty($connectedIds)) return [];

        $connPlaceholders = implode(',', array_fill(0, count($connectedIds), '?'));
        $customerClause = '';
        $params = array_merge($connectedIds, $seedChunkIds);
        if ($customerId !== null) {
            $customerClause = ' AND (c.customer_id = ? OR c.customer_id IS NULL)';
            $params[] = $customerId;
        }
        [$visSql, $visParams] = $this->visibilityClause('d');
        $params = array_merge($params, $visParams);
        $params[] = self::TOP_K_PER_STRATEGY;

        $rows = $this->db->query(
            "SELECT DISTINCT ce.chunk_id
             FROM knowledge_chunk_entities ce
             JOIN knowledge_chunks c ON ce.chunk_id = c.id
             JOIN knowledge_documents d ON c.document_id = d.id
             WHERE ce.entity_id IN ({$connPlaceholders})
               AND ce.chunk_id NOT IN ({$seedPlaceholders})
               AND d.is_active = 1
               {$customerClause}
               {$visSql}
             LIMIT ?",
            $params
        );

        $results = [];
        foreach ($rows as $r) {
            $results[] = ['chunk_id' => (int)$r['chunk_id'], 'score' => self::GRAPH_DEFAULT_SCORE];
        }
        return $results;
    }

    /**
     * Reciprocal Rank Fusion — store-agnostisch (fusioniert rein über chunk_id + Rang).
     */
    public function rrfFusion(array $dense, array $sparse, array $graph): array
    {
        $scores = [];
        $sources = [];

        foreach (['dense' => $dense, 'sparse' => $sparse, 'graph' => $graph] as $source => $results) {
            foreach ($results as $rank => $r) {
                $cid = $r['chunk_id'];
                $rrfScore = 1.0 / (self::RRF_K + $rank + 1);
                $scores[$cid] = ($scores[$cid] ?? 0) + $rrfScore;
                $sources[$cid] = $sources[$cid] ?? [];
                $sources[$cid][] = $source;
            }
        }

        arsort($scores);
        $fused = [];
        foreach ($scores as $cid => $score) {
            $fused[] = [
                'chunk_id' => $cid,
                'score' => $score,
                'sources' => $sources[$cid] ?? [],
            ];
        }
        return $fused;
    }

    /**
     * Lädt Chunk-Details + Dokument-Meta aus MariaDB (autoritative Quelle + Security-Backstop).
     *
     * Hier laufen ALLE Such-Beine zusammen (dense/Qdrant, sparse, graph): egal woher eine
     * chunk_id stammt, der Inhalt wird ausschliesslich hier nachgeladen. Der Sichtbarkeits-
     * Filter an dieser Stelle ist deshalb die eigentliche Garantie — er greift auch fuer
     * Qdrant-Treffer, ohne dass der Vektor-Index davon wissen muss.
     */
    private function loadChunkDetails(array $fused): array
    {
        if (empty($fused)) return [];

        $chunkIds = array_column($fused, 'chunk_id');
        $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
        [$visSql, $visParams] = $this->visibilityClause('d');

        $chunks = $this->db->query(
            "SELECT c.id, c.content, c.document_id, c.chunk_index, c.word_count,
                    d.title, d.category, d.source_type, d.source_ref, d.customer_id,
                    cust.name AS customer_name
             FROM knowledge_chunks c
             JOIN knowledge_documents d ON c.document_id = d.id
             LEFT JOIN customers cust ON d.customer_id = cust.id
             WHERE c.id IN ({$placeholders})
               {$visSql}",
            array_merge($chunkIds, $visParams)
        );

        $byId = [];
        foreach ($chunks as $c) {
            $byId[$c['id']] = $c;
        }

        $result = [];
        foreach ($fused as $f) {
            if (isset($byId[$f['chunk_id']])) {
                $chunk = $byId[$f['chunk_id']];
                $chunk['score'] = $f['score'];
                $chunk['sources'] = $f['sources'];
                $result[] = $chunk;
            }
        }
        return $result;
    }
}
