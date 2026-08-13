<?php
/**
 * Knowledge Service — CRUD fuer Dokumente, Chunks, Entities, Relations
 *
 * Source of Truth: MySQL. Transaktionale Schreib-Operationen fuer Konsistenz.
 */

namespace Services;

use Core\Database;

class KnowledgeService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ===== Documents =====

    public function listDocuments(array $filters = []): array
    {
        [$where, $params] = $this->buildDocumentFilters($filters);

        $limit = (int) ($filters['limit'] ?? 100);
        $offset = (int) ($filters['offset'] ?? 0);

        // Sortierung — Whitelist gegen Injection
        $sortMap = [
            'updated_at' => 'd.updated_at',
            'created_at' => 'd.created_at',
            'title' => 'd.title',
            'customer' => 'c.name',
            'category' => 'd.category',
            'source_type' => 'd.source_type',
            'chunk_count' => 'chunk_count',
        ];
        $sortBy = $sortMap[$filters['sort_by'] ?? ''] ?? 'd.updated_at';
        $sortDir = (strtolower((string) ($filters['sort_dir'] ?? '')) === 'asc') ? 'ASC' : 'DESC';

        $sql = "SELECT d.*,
                    (SELECT COUNT(*) FROM knowledge_chunks WHERE document_id = d.id) AS chunk_count,
                    (SELECT COUNT(DISTINCT ce.entity_id) FROM knowledge_chunks c
                     JOIN knowledge_chunk_entities ce ON ce.chunk_id = c.id
                     WHERE c.document_id = d.id) AS entity_count,
                    (SELECT COUNT(*) FROM knowledge_usage u
                     JOIN knowledge_chunks c ON u.chunk_id = c.id
                     WHERE c.document_id = d.id) AS usage_count,
                    c.name AS customer_name,
                    u.name AS creator_name
                FROM knowledge_documents d
                LEFT JOIN customers c ON d.customer_id = c.id
                LEFT JOIN users u ON d.created_by = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $sortBy $sortDir, d.id DESC
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $docs = $this->db->query($sql, $params);
        foreach ($docs as &$d) {
            $d['tags'] = $d['tags'] ? json_decode($d['tags'], true) : [];
        }
        return $docs;
    }

    public function getDocument(int $id): ?array
    {
        // Direktaufruf per ID muss die Sichtbarkeit genauso pruefen wie die Liste —
        // sonst reicht das Erraten einer ID, um an ein privates Dokument zu kommen.
        $viewer = class_exists('\Core\Auth') ? (int) (\Core\Auth::id() ?: 0) : 0;
        $doc = $this->db->queryOne(
            "SELECT d.*, c.name AS customer_name, u.name AS creator_name
             FROM knowledge_documents d
             LEFT JOIN customers c ON d.customer_id = c.id
             LEFT JOIN users u ON d.created_by = u.id
             WHERE d.id = ?
               AND (d.visibility <> 'privat' OR d.owner_user_id = ?)",
            [$id, $viewer]
        );
        if (!$doc) return null;
        $doc['tags'] = $doc['tags'] ? json_decode($doc['tags'], true) : [];
        return $doc;
    }

    public function updateDocument(int $id, array $data): void
    {
        $allowed = ['title', 'description', 'category', 'tags', 'customer_id', 'is_active'];
        $update = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $update[$f] = $f === 'tags' ? json_encode($data[$f]) : $data[$f];
            }
        }
        if (!empty($update)) {
            $this->db->update('knowledge_documents', $update, 'id = ?', [$id]);
        }
    }

    public function deleteDocument(int $id): void
    {
        // CASCADE loescht automatisch chunks, embeddings, chunk_entities, usage
        // Relations die explizit aus diesem Dokument stammen: mit entfernen
        try {
            $this->db->execute(
                "DELETE FROM knowledge_relations WHERE source_document_id = ?",
                [$id]
            );
        } catch (\Exception $e) {
            error_log('deleteDocument: relations cleanup skipped: ' . $e->getMessage());
        }

        // created_by fuer den Sync-Job merken, BEVOR das Dokument geloescht wird
        $createdBy = (int) ($this->db->queryValue("SELECT created_by FROM knowledge_documents WHERE id = ?", [$id]) ?: 0);

        $this->db->delete('knowledge_documents', 'id = ?', [$id]);

        // Orphaned Entities + Relations aufraeumen (Graph-View)
        $this->cleanupOrphans();

        // Wissen-V2: Punkte des Dokuments aus Qdrant entfernen (Hintergrund-Job).
        // Fehler duerfen das Loeschen nie brechen.
        try {
            (new \Services\JobQueue($this->db))->createJob([
                'user_id'         => $createdBy > 0 ? $createdBy : 1,
                'job_type'        => 'qdrant_sync',
                'sections_config' => ['op' => 'delete_document', 'document_id' => $id],
                'priority'        => -5,
            ]);
        } catch (\Throwable $e) {
            error_log('qdrant_sync enqueue (delete) fehlgeschlagen (doc ' . $id . '): ' . $e->getMessage());
        }
    }

    /**
     * Loescht verwaiste Entities und Relations:
     *  - Entities, die in keinem Chunk mehr vorkommen
     *  - Relations, deren Quell- oder Ziel-Entity geloescht wurde
     */
    public function cleanupOrphans(): array
    {
        $deletedEntities = 0;
        $deletedRelations = 0;
        try {
            // Relations ohne referenzierte Entities
            $stmt = $this->db->execute(
                "DELETE r FROM knowledge_relations r
                 LEFT JOIN knowledge_entities fe ON r.from_entity_id = fe.id
                 LEFT JOIN knowledge_entities te ON r.to_entity_id = te.id
                 WHERE fe.id IS NULL OR te.id IS NULL"
            );

            // Verwaiste Entities (nicht in chunk_entities)
            $stmt = $this->db->execute(
                "DELETE e FROM knowledge_entities e
                 LEFT JOIN knowledge_chunk_entities ce ON ce.entity_id = e.id
                 WHERE ce.entity_id IS NULL"
            );

            // Nochmal Relations, die jetzt nach Entity-Loeschung verwaist sind
            $this->db->execute(
                "DELETE r FROM knowledge_relations r
                 LEFT JOIN knowledge_entities fe ON r.from_entity_id = fe.id
                 LEFT JOIN knowledge_entities te ON r.to_entity_id = te.id
                 WHERE fe.id IS NULL OR te.id IS NULL"
            );
        } catch (\Exception $e) {
            error_log('cleanupOrphans: ' . $e->getMessage());
        }
        return ['entities_deleted' => $deletedEntities, 'relations_deleted' => $deletedRelations];
    }

    // ===== Content-Hash =====

    public function computeContentHash(string $content): string
    {
        return hash('sha256', $content);
    }

    public function findByContentHash(string $hash): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM knowledge_documents WHERE content_hash = ? AND is_active = 1 LIMIT 1",
            [$hash]
        );
    }

    /**
     * Sucht ein Dokument anhand source_type + external_id.
     * Wird vor allem fuer Asana-Sync genutzt: wenn task:GID schon existiert → reprocess, sonst commit.
     */
    public function findByExternalId(string $sourceType, string $externalId): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM knowledge_documents WHERE source_type = ? AND external_id = ? LIMIT 1",
            [$sourceType, $externalId]
        );
    }

    // ===== Chunks =====

    public function getChunksByDocument(int $documentId): array
    {
        return $this->db->query(
            "SELECT * FROM knowledge_chunks WHERE document_id = ? ORDER BY chunk_index",
            [$documentId]
        );
    }

    // ===== Entities =====

    public function getEntitiesByDocument(int $documentId): array
    {
        return $this->db->query(
            "SELECT DISTINCT e.id, e.name, e.type, e.normalized_name, e.mention_count,
                    COUNT(DISTINCT ce.chunk_id) AS chunk_count
             FROM knowledge_entities e
             JOIN knowledge_chunk_entities ce ON ce.entity_id = e.id
             JOIN knowledge_chunks c ON c.id = ce.chunk_id
             WHERE c.document_id = ?
             GROUP BY e.id
             ORDER BY chunk_count DESC, e.name",
            [$documentId]
        );
    }

    public function getRelationsByDocument(int $documentId): array
    {
        return $this->db->query(
            "SELECT r.id, r.type, r.weight,
                    e1.id AS from_id, e1.name AS from_name, e1.type AS from_type,
                    e2.id AS to_id, e2.name AS to_name, e2.type AS to_type
             FROM knowledge_relations r
             JOIN knowledge_entities e1 ON r.from_entity_id = e1.id
             JOIN knowledge_entities e2 ON r.to_entity_id = e2.id
             WHERE r.source_document_id = ?
             ORDER BY r.weight DESC",
            [$documentId]
        );
    }

    // ===== Stats =====

    public function getStats(?int $customerId = null): array
    {
        $where = $customerId === null ? '' : 'WHERE customer_id = ?';
        $params = $customerId === null ? [] : [$customerId];

        $docs = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM knowledge_documents {$where} " . ($where ? 'AND is_active = 1' : 'WHERE is_active = 1'),
            $params
        );
        $chunks = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM knowledge_chunks " . ($customerId === null ? '' : 'WHERE customer_id = ?'),
            $params
        );
        $entities = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM knowledge_entities " . ($customerId === null ? '' : 'WHERE customer_id = ?'),
            $params
        );
        $relations = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM knowledge_relations " . ($customerId === null ? '' : 'WHERE customer_id = ?'),
            $params
        );

        return compact('docs', 'chunks', 'entities', 'relations');
    }

    // ===== Erweiterte Filter + Facets + Dashboard =====

    /**
     * Baut WHERE-Clause und Params fuer Document-Filter. Ueberall identisch
     * verwendet (listDocuments, countDocuments, getFacets).
     */
    private function buildDocumentFilters(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        // Private Dokumente (z.B. Mail-Postfach) nur fuer ihren Besitzer sichtbar —
        // bewusst OHNE Admin-Ausnahme. Greift fuer Liste UND Zaehler, weil beide hier
        // durchlaufen. Ohne bekannten Nutzer (CLI/Cron): private Dokumente raus (fail-closed).
        $viewer = class_exists('\Core\Auth') ? \Core\Auth::id() : null;
        if (!empty($viewer)) {
            $where[] = "(d.visibility <> 'privat' OR d.owner_user_id = ?)";
            $params[] = (int) $viewer;
        } else {
            $where[] = "d.visibility <> 'privat'";
        }

        if (isset($filters['customer_id']) && $filters['customer_id'] !== null && $filters['customer_id'] !== '') {
            if ($filters['customer_id'] === 'null') {
                $where[] = 'd.customer_id IS NULL';
            } else {
                $where[] = 'd.customer_id = ?';
                $params[] = (int) $filters['customer_id'];
            }
        }
        if (!empty($filters['category'])) {
            $where[] = 'd.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['source_type'])) {
            $where[] = 'd.source_type = ?';
            $params[] = $filters['source_type'];
        }
        if (!empty($filters['ingest_mode'])) {
            $where[] = 'd.ingest_mode = ?';
            $params[] = $filters['ingest_mode'];
        }
        if (!empty($filters['search'])) {
            // Tokenisierte Suche: jedes Wort >= 3 Zeichen wird einzeln gesucht (AND).
            // Frage-Fuell-Woerter werden ignoriert. Sonderzeichen ("?", ",") weg.
            $query = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', (string) $filters['search']);
            $words = preg_split('/\s+/u', trim($query));
            $stop = ['der','die','das','ein','eine','und','oder','ist','sind','war','hat','haben','wer','was','wie','wo','wann','warum','welche','welcher','welches','wieso','weshalb','bei','von','zu','in','auf','an','fuer','aus','dem','den','des','mit','am','um','the','and','for','that','what','who','when','where','why','how'];
            $keywords = [];
            foreach ($words as $w) {
                $wl = mb_strtolower(trim($w));
                if (mb_strlen($wl) < 3) continue;
                if (in_array($wl, $stop, true)) continue;
                $keywords[] = $w;
            }
            if (empty($keywords)) {
                // Fallback: ganzen Original-String suchen (z.B. bei sehr kurzen Suchen)
                $where[] = '(d.title LIKE ? OR d.description LIKE ?)';
                $params[] = '%' . $filters['search'] . '%';
                $params[] = '%' . $filters['search'] . '%';
            } else {
                foreach ($keywords as $kw) {
                    $where[] = '(d.title LIKE ? OR d.description LIKE ?)';
                    $params[] = '%' . $kw . '%';
                    $params[] = '%' . $kw . '%';
                }
            }
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'd.updated_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'd.updated_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['tags']) && is_array($filters['tags'])) {
            foreach ($filters['tags'] as $tag) {
                // Tags liegen als JSON-Array in d.tags
                $where[] = 'JSON_SEARCH(d.tags, "one", ?) IS NOT NULL';
                $params[] = (string) $tag;
            }
        }
        if (!empty($filters['created_by'])) {
            $where[] = 'd.created_by = ?';
            $params[] = (int) $filters['created_by'];
        }
        if (isset($filters['size_bucket']) && $filters['size_bucket'] !== '') {
            // chunk-count-basierte Buckets: 'kurz' <= 2, 'mittel' 3-10, 'lang' > 10
            $bucket = $filters['size_bucket'];
            $sub = "(SELECT COUNT(*) FROM knowledge_chunks WHERE document_id = d.id)";
            if ($bucket === 'kurz') $where[] = "$sub <= 2";
            elseif ($bucket === 'mittel') $where[] = "$sub BETWEEN 3 AND 10";
            elseif ($bucket === 'lang') $where[] = "$sub > 10";
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'inactive') $where[] = 'd.is_active = 0';
            else $where[] = 'd.is_active = 1';
        } elseif (empty($filters['include_inactive'])) {
            $where[] = 'd.is_active = 1';
        }
        // Kunden-Status (Aktiv/Inaktiv) — wirkt auf customers.is_active
        if (isset($filters['customer_status']) && $filters['customer_status'] !== '') {
            $isAct = $filters['customer_status'] === 'active' ? 1 : 0;
            $where[] = "d.customer_id IN (SELECT id FROM customers WHERE is_active = ?)";
            $params[] = $isAct;
        }
        // Kunden-Tags (Art) — settings ist JSON, tags-Liste darin
        if (!empty($filters['customer_tags']) && is_array($filters['customer_tags'])) {
            foreach ($filters['customer_tags'] as $tag) {
                $where[] = "d.customer_id IN (SELECT id FROM customers WHERE JSON_SEARCH(JSON_EXTRACT(settings, '$.tags'), 'one', ?) IS NOT NULL)";
                $params[] = (string) $tag;
            }
        }
        // Berechtigung: nicht-Admin auf erlaubte Kunden einschraenken
        if (!empty($filters['allowed_customer_ids']) && is_array($filters['allowed_customer_ids'])) {
            $ids = array_map('intval', $filters['allowed_customer_ids']);
            if (empty($ids)) {
                $where[] = '0 = 1';
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $where[] = "d.customer_id IN ($placeholders)";
                foreach ($ids as $id) $params[] = $id;
            }
        }
        return [$where, $params];
    }

    public function countDocuments(array $filters = []): int
    {
        [$where, $params] = $this->buildDocumentFilters($filters);
        return (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM knowledge_documents d WHERE " . implode(' AND ', $where),
            $params
        );
    }

    /**
     * Facets: pro Quelle/Kategorie/Tag/Kunde die Counts fuer die aktuelle
     * Filter-Auswahl. Wird in der Sidebar angezeigt.
     */
    public function getFacets(array $filters = []): array
    {
        // Wir bauen die Basis-Filter, lassen aber die einzelne Facette beim
        // Zaehlen weg, damit die Liste nicht auf ihren eigenen Wert kollabiert.
        $sources = $this->facetCounts($filters, 'source_type');
        $categories = $this->facetCounts($filters, 'category');
        $customers = $this->facetCounts($filters, 'customer_id', true);
        $tags = $this->facetTags($filters);
        $customerTags = $this->facetCustomerTags($filters);
        $customerStatus = $this->facetCustomerStatus($filters);
        $ingestModes = $this->facetCounts($filters, 'ingest_mode');
        return compact('sources', 'categories', 'customers', 'tags', 'customerTags', 'customerStatus', 'ingestModes');
    }

    private function facetCounts(array $filters, string $field, bool $joinCustomer = false): array
    {
        $local = $filters;
        unset($local[$field]);
        [$where, $params] = $this->buildDocumentFilters($local);
        $select = $field === 'customer_id'
            ? "d.customer_id AS facet_value, c.name AS facet_label, c.abbreviation AS facet_abbr, COUNT(*) AS n"
            : "d.$field AS facet_value, d.$field AS facet_label, COUNT(*) AS n";
        // ingest_mode kann NULL sein — filter raus
        $extra = $field === 'ingest_mode' ? " AND d.ingest_mode IS NOT NULL" : '';
        $join = $joinCustomer ? 'LEFT JOIN customers c ON c.id = d.customer_id' : '';
        // Sortierung: Kunden + Kategorien + Tags alphabetisch (facet_label ASC),
        // Quellen ebenfalls alphabetisch — User will keine "Wer ist am dicksten"-Reihenfolge.
        $rows = $this->db->query(
            "SELECT $select FROM knowledge_documents d $join WHERE " . implode(' AND ', $where)
            . $extra
            . " GROUP BY facet_value ORDER BY facet_label ASC LIMIT 50",
            $params
        ) ?: [];
        return $rows;
    }

    /**
     * Customer-Tags (Art): aus customers.settings JSON-Array extrahieren.
     * Counts beziehen sich auf die Anzahl Kunden mit dem Tag, nicht Docs.
     */
    private function facetCustomerTags(array $filters): array
    {
        $local = $filters;
        unset($local['customer_tags']);
        [$where, $params] = $this->buildDocumentFilters($local);
        // Distinct customer_ids unter den Filter-Bedingungen, dann Tags aus settings extrahieren
        $rows = $this->db->query(
            "SELECT DISTINCT c.id, c.settings FROM knowledge_documents d
             JOIN customers c ON c.id = d.customer_id
             WHERE " . implode(' AND ', $where) . " AND c.settings IS NOT NULL",
            $params
        ) ?: [];
        $counts = [];
        foreach ($rows as $r) {
            $s = json_decode($r['settings'] ?? '{}', true) ?: [];
            foreach (($s['tags'] ?? []) as $t) {
                $t = trim((string) $t);
                if ($t === '') continue;
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        $out = [];
        foreach ($counts as $tag => $n) {
            $out[] = ['facet_value' => $tag, 'facet_label' => $tag, 'n' => $n];
        }
        return $out;
    }

    /**
     * Customer-Status: wieviele Kunden mit Docs sind aktiv vs inaktiv.
     */
    private function facetCustomerStatus(array $filters): array
    {
        $local = $filters;
        unset($local['customer_status']);
        [$where, $params] = $this->buildDocumentFilters($local);
        $rows = $this->db->query(
            "SELECT c.is_active, COUNT(DISTINCT c.id) AS n FROM knowledge_documents d
             JOIN customers c ON c.id = d.customer_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY c.is_active",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $val = $r['is_active'] ? 'active' : 'inactive';
            $label = $r['is_active'] ? 'Aktiv' : 'Inaktiv';
            $out[] = ['facet_value' => $val, 'facet_label' => $label, 'n' => (int) $r['n']];
        }
        // Alphabetisch nach Label
        usort($out, fn($a, $b) => strcmp($a['facet_label'], $b['facet_label']));
        return $out;
    }

    private function facetTags(array $filters): array
    {
        // Tags sind JSON-Arrays - manuell aufbrechen
        $local = $filters;
        unset($local['tags']);
        [$where, $params] = $this->buildDocumentFilters($local);
        $rows = $this->db->query(
            "SELECT d.tags FROM knowledge_documents d WHERE " . implode(' AND ', $where) . " AND d.tags IS NOT NULL AND d.tags != '[]' LIMIT 5000",
            $params
        ) ?: [];
        $counts = [];
        foreach ($rows as $r) {
            $tags = json_decode($r['tags'] ?? '[]', true) ?: [];
            foreach ($tags as $t) {
                $t = trim((string) $t);
                if ($t === '') continue;
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }
        // Singletons rausfiltern — Tags die nur 1x vorkommen helfen niemandem
        // beim Filtern (3.689 von 5.959 Tags waren Singletons → Filter-Liste
        // unbenutzbar). Schwelle n>=2.
        $counts = array_filter($counts, fn($n) => $n >= 2);
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        $out = [];
        $i = 0;
        foreach ($counts as $tag => $n) {
            $out[] = ['facet_value' => $tag, 'facet_label' => $tag, 'n' => $n];
            if (++$i >= 50) break;
        }
        return $out;
    }

    /**
     * Heatmap-Daten: pro Kunde × Schluesselthema die Doc-Counts.
     * Themen sind als Combo aus Kategorie/Quelle/Tag-Mustern definiert.
     */
    public function getDashboard(?array $allowedCustomerIds = null, array $filters = []): array
    {
        $custClauses = [];
        $custParams = [];
        if ($allowedCustomerIds !== null) {
            $ids = array_map('intval', $allowedCustomerIds);
            if (empty($ids)) return ['themes' => [], 'customers' => []];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $custClauses[] = "id IN ($placeholders)";
            foreach ($ids as $id) $custParams[] = $id;
        }
        if (isset($filters['customer_status']) && $filters['customer_status'] !== '') {
            $custClauses[] = "is_active = ?";
            $custParams[] = $filters['customer_status'] === 'active' ? 1 : 0;
        }
        if (!empty($filters['customer_tags']) && is_array($filters['customer_tags'])) {
            foreach ($filters['customer_tags'] as $tag) {
                $custClauses[] = "JSON_SEARCH(JSON_EXTRACT(settings, '$.tags'), 'one', ?) IS NOT NULL";
                $custParams[] = (string) $tag;
            }
        }
        $custWhere = $custClauses ? ('WHERE ' . implode(' AND ', $custClauses)) : '';

        $customers = $this->db->query(
            "SELECT id, name FROM customers $custWhere ORDER BY name",
            $custParams
        ) ?: [];

        // Schluesselthemen + Auswahl-Kriterium
        $themes = [
            ['key' => 'steckbrief', 'label' => 'Steckbrief',
             'where' => "(d.source_type = 'kundensteckbrief' OR d.category = 'Kundensteckbrief')"],
            ['key' => 'marke',      'label' => 'Marke',
             'where' => "(d.category = 'Marketing' OR JSON_SEARCH(d.tags, 'one', 'marke') IS NOT NULL OR JSON_SEARCH(d.tags, 'one', 'brand') IS NOT NULL)"],
            ['key' => 'recht',      'label' => 'Recht',
             'where' => "d.category = 'Rechtlich'"],
            ['key' => 'technik',    'label' => 'Technik',
             'where' => "d.category = 'Technik'"],
            ['key' => 'prozess',    'label' => 'Prozess',
             'where' => "d.category = 'Prozess'"],
            ['key' => 'referenz',   'label' => 'Referenz',
             'where' => "d.category = 'Referenz'"],
            ['key' => 'asana',      'label' => 'Asana',
             'where' => "d.source_type = 'asana'"],
            ['key' => 'website',    'label' => 'Website',
             'where' => "d.source_type = 'web' AND d.ingest_mode = 'auto'"],
            ['key' => 'transkript', 'label' => 'Transkript',
             'where' => "d.source_type = 'transcript'"],
        ];

        // Eine Query: customer_id × theme_key → count
        // Wir bauen das als UNION ALL, da MySQL kein einfacheres Pivot kennt.
        $unionParts = [];
        foreach ($themes as $t) {
            $unionParts[] = "SELECT d.customer_id, '" . $t['key'] . "' AS theme_key, COUNT(*) AS n
                             FROM knowledge_documents d
                             WHERE d.is_active = 1 AND d.customer_id IS NOT NULL AND " . $t['where'] . "
                             GROUP BY d.customer_id";
        }
        $rows = $this->db->query(implode("\nUNION ALL\n", $unionParts)) ?: [];

        $byCustomer = [];
        foreach ($rows as $r) {
            $cid = (int) $r['customer_id'];
            $byCustomer[$cid][$r['theme_key']] = (int) $r['n'];
        }
        // Total + letztes Update pro Kunde
        $totals = $this->db->query(
            "SELECT customer_id, COUNT(*) AS total, MAX(updated_at) AS last_update
             FROM knowledge_documents
             WHERE is_active = 1 AND customer_id IS NOT NULL
             GROUP BY customer_id"
        ) ?: [];
        $totalsByCust = [];
        foreach ($totals as $r) {
            $totalsByCust[(int) $r['customer_id']] = ['total' => (int) $r['total'], 'last_update' => $r['last_update']];
        }

        $out = [];
        foreach ($customers as $c) {
            $cid = (int) $c['id'];
            $row = [
                'id' => $cid,
                'name' => $c['name'],
                'total' => $totalsByCust[$cid]['total'] ?? 0,
                'last_update' => $totalsByCust[$cid]['last_update'] ?? null,
                'themes' => [],
            ];
            foreach ($themes as $t) {
                $row['themes'][$t['key']] = $byCustomer[$cid][$t['key']] ?? 0;
            }
            $out[] = $row;
        }
        // Nach Total absteigend sortieren (Kunden ohne Wissen ans Ende)
        usort($out, fn($a, $b) => $b['total'] <=> $a['total']);

        return ['themes' => $themes, 'customers' => $out];
    }

    /**
     * Globale Stats: Top-Kunden, Top-Tags, Verteilungen.
     */
    public function getGlobalStats(?array $allowedCustomerIds = null): array
    {
        $custFilter = '';
        $params = [];
        if ($allowedCustomerIds !== null) {
            $ids = array_map('intval', $allowedCustomerIds);
            if (empty($ids)) return ['docs' => 0, 'chunks' => 0, 'customers' => 0, 'with_steckbrief' => 0, 'without_steckbrief' => 0, 'last_updates' => []];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $custFilter = "AND customer_id IN ($placeholders)";
            $params = $ids;
        }
        $docs = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM knowledge_documents WHERE is_active = 1 $custFilter",
            $params
        );
        $chunks = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM knowledge_chunks c
             JOIN knowledge_documents d ON d.id = c.document_id
             WHERE d.is_active = 1 " . str_replace('customer_id', 'd.customer_id', $custFilter),
            $params
        );
        $customers = (int) $this->db->queryValue(
            "SELECT COUNT(DISTINCT customer_id) FROM knowledge_documents WHERE is_active = 1 AND customer_id IS NOT NULL $custFilter",
            $params
        );
        $withSteckbrief = (int) $this->db->queryValue(
            "SELECT COUNT(DISTINCT customer_id) FROM knowledge_documents
             WHERE is_active = 1 AND customer_id IS NOT NULL
                AND (source_type = 'kundensteckbrief' OR category = 'Kundensteckbrief') $custFilter",
            $params
        );
        return [
            'docs' => $docs,
            'chunks' => $chunks,
            'customers' => $customers,
            'with_steckbrief' => $withSteckbrief,
            'without_steckbrief' => max(0, $customers - $withSteckbrief),
        ];
    }
}
