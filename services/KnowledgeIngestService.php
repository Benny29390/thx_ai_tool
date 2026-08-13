<?php
/**
 * Knowledge Ingest Service — Prepare-before-Write Pipeline
 *
 * Phase 1 PREPARE: Chunking + Embedding + LLM-Extraktion (kein DB-Write)
 * Phase 2 COMMIT:  Transaktionaler Write in alle 3 logischen DBs
 *
 * Prepared-Daten werden in Session/Cache zwischengespeichert (nicht in DB).
 */

namespace Services;

use Core\Database;

class KnowledgeIngestService
{
    private Database $db;
    /** @deprecated wird nicht mehr genutzt — Embeddings erzeugt der Worker via bge-m3 (Qdrant V2) */
    private ?KnowledgeEmbeddingService $embeddingService;
    private KnowledgeExtractionService $extractionService;

    // Chunking-Config
    private const CHUNK_MAX_CHARS = 1200;     // max Zeichen pro Chunk
    private const CHUNK_MIN_CHARS = 100;      // min Zeichen pro Chunk
    private const CHUNK_OVERLAP_CHARS = 150;  // Overlap zwischen Chunks

    /**
     * @param Database $db
     * @param KnowledgeEmbeddingService|null $embeddingService DEPRECATED — Parameter bleibt nur fuer Backwards-
     *        Compatibility; Embeddings macht jetzt der qdrant_sync-Worker mit bge-m3 lokal.
     * @param KnowledgeExtractionService $extractionService
     */
    public function __construct(
        Database $db,
        ?KnowledgeEmbeddingService $embeddingService,
        KnowledgeExtractionService $extractionService
    ) {
        $this->db = $db;
        $this->embeddingService = $embeddingService; // unused
        $this->extractionService = $extractionService;
    }

    /**
     * Phase 1: PREPARE — extrahiert Chunks, Embeddings, Metadata (kein DB-Write)
     *
     * @param string $text Voller Dokument-Text
     * @param array $context Optional: ['customer_name' => ...]
     * @return array {chunks, embeddings, metadata, content_hash}
     */
    public function prepare(string $text, array $context = []): array
    {
        // Original-Text aufbewahren
        $originalText = trim($text);
        // Content-Hash fuer Dedup
        $contentHash = hash('sha256', $originalText);

        // Chunking
        $chunks = $this->chunkText($text);
        if (empty($chunks)) {
            throw new \Exception('Kein Inhalt zum Verarbeiten nach Chunking');
        }

        // Embeddings erzeugt der qdrant_sync-Worker spaeter mit bge-m3 lokal —
        // hier nichts mehr embedden (kein OpenAI-Call).
        $metadata = $this->extractionService->extract($text, $context);
        $entitiesPerChunk = $this->extractionService->mapEntitiesToChunks($chunks, $metadata['entities']);

        return [
            'content_hash' => $contentHash,
            'original_content' => $originalText,
            'chunks' => $chunks,
            'embeddings' => [],
            'metadata' => $metadata,
            'entities_per_chunk' => $entitiesPerChunk,
        ];
    }

    /**
     * Variante von prepare() fuer Quellen, die schon eigene Chunks mitbringen.
     * Z.B. Projektplaene, wo jedes Item ein semantisch zusammenhaengender Chunk ist.
     *
     * $chunks: array of strings (Chunk-Inhalte).
     * $fullText: Volltext fuer Hash + LLM-Extraktion (Entities werden ueber den
     * Gesamttext gefunden und dann den Chunks zugeordnet).
     */
    /**
     * Kanonischer content_hash fuer Chunk-basierte Quellen (Projektplan, Reporting).
     * Bildet Volltext UND Chunk-Grenzen ab, damit auch eine reine Chunk-Strategie-Aenderung
     * erkannt wird. Erwartet bereits getrimmte, gefilterte Chunks. Ohne LLM-Aufruf, damit die
     * Skip-Pruefung im Sync billig bleibt.
     */
    public static function chunkContentHash(string $fullText, array $chunks): string
    {
        return hash('sha256', trim($fullText) . "\n\x1eCHUNKSTRAT\x1e\n" . implode("\n\x1e\n", $chunks));
    }

    public function prepareFromChunks(array $chunks, string $fullText, array $context = []): array
    {
        $originalText = trim($fullText);

        $chunks = array_values(array_filter(array_map('trim', $chunks)));
        if (empty($chunks)) {
            throw new \Exception('Kein Chunk-Inhalt zum Verarbeiten');
        }

        // Hash ueber die TATSAECHLICHEN Chunks (Inhalt + Grenzen), nicht nur den Volltext.
        // So loest jede Aenderung der Chunk-Strategie (z.B. fixe Bloecke -> 1 Chunk pro
        // Aufgabe) automatisch einen Re-Sync aus, auch wenn der Volltext identisch bleibt.
        $contentHash = self::chunkContentHash($originalText, $chunks);

        // In das interne Format konvertieren (wie chunkText)
        $structured = [];
        foreach ($chunks as $i => $content) {
            $structured[] = [
                'index' => $i,
                'content' => $content,
                'word_count' => str_word_count($content),
            ];
        }

        // Kein OpenAI-Embed-Call mehr — qdrant_sync-Worker zieht das bge-m3-Embedding spaeter nach.
        $metadata = $this->extractionService->extract($fullText, $context);
        $entitiesPerChunk = $this->extractionService->mapEntitiesToChunks($structured, $metadata['entities']);

        return [
            'content_hash' => $contentHash,
            'original_content' => $originalText,
            'chunks' => $structured,
            'embeddings' => [],
            'metadata' => $metadata,
            'entities_per_chunk' => $entitiesPerChunk,
        ];
    }

    /**
     * Phase 2: COMMIT — schreibt alle vorbereiteten Daten transaktional
     *
     * @param array $prepared Result von prepare()
     * @param array $overrides User-Aenderungen: ['title', 'description', 'customer_id', 'category', 'tags']
     * @param array $meta ['source_type', 'source_ref', 'created_by']
     * @return int Document-ID
     */
    public function commit(array $prepared, array $overrides, array $meta): int
    {
        $customerId = $overrides['customer_id'] ?? null;
        if ($customerId !== null) $customerId = (int) $customerId;

        // Hartes Scope-Constraint: Wissensdatenbank-Eintraege MUESSEN einen Kunden
        // haben. Ohne Kundenbezug landet kein Wissen in der DB — verhindert dass
        // unzuordenbare Inhalte sich ansammeln und die RAG-Trefferquote verwaessern.
        if ($customerId === null || $customerId <= 0) {
            $title = (string) ($overrides['title'] ?? '(ohne Titel)');
            $src = (string) ($meta['source_type'] ?? '?');
            throw new \RuntimeException(
                'Knowledge-Ingest abgelehnt: customer_id fehlt fuer „' . $title
                . '" (source_type=' . $src . '). Bitte beim Upload/Sync einen Kunden zuweisen.'
            );
        }

        $this->db->beginTransaction();

        try {

            // Defensiv: wenn external_id gesetzt ist, identifiziert sie das Ursprungs-
            // Objekt eindeutig (Asana-Task-GID, Website-URL-Hash, Steckbrief-Card-ID,
            // Projektplan-ID). Ein zweites Doc mit derselben Kombination waere immer
            // ein Bug — bestehendes Doc wird stattdessen aktualisiert.
            $extId = $meta['external_id'] ?? null;
            $srcType = $meta['source_type'] ?? null;
            if ($extId && $srcType) {
                $existing = $this->db->queryOne(
                    "SELECT id FROM knowledge_documents WHERE source_type = ? AND external_id = ? AND " .
                    ($customerId !== null ? 'customer_id = ?' : 'customer_id IS NULL') .
                    " LIMIT 1",
                    $customerId !== null ? [$srcType, $extId, $customerId] : [$srcType, $extId]
                );
                if ($existing) {
                    $this->db->rollback();
                    error_log("KnowledgeIngestService::commit: dispatching to reprocess for ($srcType, $extId, customer=$customerId) — existing id={$existing['id']}");
                    // Bestehendes Doc updaten statt neues einfuegen.
                    $this->reprocess(
                        (int) $existing['id'],
                        $prepared['original_content'] ?? '',
                        $overrides,
                        ['customer_name' => null],
                        (int) ($meta['created_by'] ?? 0),
                        true
                    );
                    return (int) $existing['id'];
                }
            }

            // 1. Document
            // ingest_mode: explizit aus meta, sonst Default je nach source_type
            $autoSources = ['asana', 'transcript', 'projektplan', 'reporting', 'kundensteckbrief'];
            $defaultMode = in_array($meta['source_type'] ?? '', $autoSources, true) ? 'auto' : 'manuell';
            $docId = $this->db->insert('knowledge_documents', [
                'customer_id' => $customerId,
                'title' => $overrides['title'] ?? $prepared['metadata']['title'],
                'description' => $overrides['description'] ?? $prepared['metadata']['description'],
                'source_type' => $meta['source_type'],
                'ingest_mode' => $meta['ingest_mode'] ?? $defaultMode,
                'source_ref' => $meta['source_ref'] ?? null,
                'external_id' => $meta['external_id'] ?? null,
                'category' => $overrides['category'] ?? $prepared['metadata']['category'],
                'tags' => json_encode($this->normalizeTags($overrides['tags'] ?? $prepared['metadata']['tags'])),
                'original_content' => $prepared['original_content'] ?? null,
                'content_hash' => $prepared['content_hash'],
                'created_by' => (int) $meta['created_by'],
            ]);

            // 2. Chunks
            $chunkIds = [];
            foreach ($prepared['chunks'] as $i => $chunk) {
                $chunkIds[$i] = $this->db->insert('knowledge_chunks', [
                    'document_id' => $docId,
                    'customer_id' => $customerId,
                    'chunk_index' => $i,
                    'content' => $chunk['content'],
                    'word_count' => $chunk['word_count'],
                ]);
            }

            // 3. Embeddings — werden NICHT mehr in knowledge_embeddings geschrieben.
            //    Stattdessen erzeugt der qdrant_sync-Worker spaeter pro Chunk ein bge-m3-
            //    Embedding und speichert es in Qdrant (Collection knowledge_bge_m3).
            //    Die V1-Tabelle knowledge_embeddings bleibt unangetastet (Backup).

            // 4. Entities (mit Deduplication via ON DUPLICATE KEY)
            $entityIdMap = [];
            foreach ($prepared['metadata']['entities'] as $ent) {
                $normalizedName = mb_strtolower(trim($ent['name']));
                if ($normalizedName === '') continue;

                $this->db->execute(
                    "INSERT INTO knowledge_entities (customer_id, name, normalized_name, type, mention_count)
                     VALUES (?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE mention_count = mention_count + 1, id = LAST_INSERT_ID(id)",
                    [$customerId, $ent['name'], $normalizedName, $ent['type']]
                );
                $entityId = (int) $this->db->queryValue("SELECT LAST_INSERT_ID()");
                $entityIdMap[$ent['name']] = $entityId;
            }

            // 5. Chunk-Entity Junction
            foreach ($prepared['entities_per_chunk'] ?? [] as $i => $entNames) {
                $chunkId = $chunkIds[$i] ?? null;
                if (!$chunkId) continue;
                foreach ($entNames as $entName) {
                    if (isset($entityIdMap[$entName])) {
                        try {
                            $this->db->execute(
                                "INSERT IGNORE INTO knowledge_chunk_entities (chunk_id, entity_id) VALUES (?, ?)",
                                [$chunkId, $entityIdMap[$entName]]
                            );
                        } catch (\Exception $e) {
                            // Ignore (already exists)
                        }
                    }
                }
            }

            // 6. Relations
            foreach ($prepared['metadata']['relations'] as $rel) {
                if (!isset($entityIdMap[$rel['from']]) || !isset($entityIdMap[$rel['to']])) continue;
                $this->db->insert('knowledge_relations', [
                    'customer_id' => $customerId,
                    'from_entity_id' => $entityIdMap[$rel['from']],
                    'to_entity_id' => $entityIdMap[$rel['to']],
                    'type' => $rel['type'],
                    'weight' => $rel['weight'],
                    'source_document_id' => $docId,
                ]);
            }

            $this->db->commit();
            $this->enqueueQdrantSync($docId, $customerId, (int)($meta['created_by'] ?? 0));
            return $docId;

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Enqueued einen Hintergrund-Job, der das Dokument ins parallele Wissen-V2
     * (bge-m3 + Qdrant) synchronisiert. Fehler hier duerfen den Haupt-Flow NIE brechen.
     */
    private function enqueueQdrantSync(int $docId, ?int $customerId, int $userId, string $op = 'upsert_document'): void
    {
        try {
            (new \Services\JobQueue($this->db))->createJob([
                'user_id'         => $userId > 0 ? $userId : 1,
                'customer_id'     => $customerId,
                'job_type'        => 'qdrant_sync',
                'sections_config' => ['op' => $op, 'document_id' => $docId],
                'priority'        => -5, // niedrige Prioritaet: blockiert echte Jobs nicht
            ]);
        } catch (\Throwable $e) {
            error_log('qdrant_sync enqueue fehlgeschlagen (doc ' . $docId . '): ' . $e->getMessage());
        }
    }

    /**
     * REPROCESS — ersetzt Inhalt eines bestehenden Dokuments komplett
     * Loescht alte Chunks/Embeddings/Relations, baut sie neu auf.
     * Document-ID bleibt erhalten (wichtig fuer Usage-Tracking, Referenzen)
     *
     * @param int $docId
     * @param string $newContent Neuer Text (oder gleicher Text wenn nur Metadata aendert)
     * @param array $overrides title, description, customer_id, category, tags
     * @param array $context ['customer_name' => ...]
     * @param int $userId
     * @param bool $reanalyze Wenn false: nur Metadata updaten, Chunks behalten
     */
    public function reprocess(int $docId, string $newContent, array $overrides, array $context, int $userId, bool $reanalyze = true, ?array $prepared = null): void
    {
        if ($reanalyze) {
            // Wenn bereits vorbereitete Chunks uebergeben wurden (z.B. 1-pro-Aufgabe aus
            // dem Projektplan-/Reporting-Sync), diese nutzen — sonst den Volltext mit der
            // Standard-Strategie (chunkText, fixe Bloecke) zerlegen.
            if ($prepared === null) {
                $prepared = $this->prepare($newContent, $context);
            }
        }

        $this->db->beginTransaction();
        try {
            // Metadata-Update
            $update = ['updated_by' => $userId];
            if (isset($overrides['title'])) $update['title'] = $overrides['title'];
            if (isset($overrides['description'])) $update['description'] = $overrides['description'];
            if (isset($overrides['customer_id'])) $update['customer_id'] = $overrides['customer_id'] ?: null;
            if (isset($overrides['category'])) $update['category'] = $overrides['category'];
            if (isset($overrides['tags'])) $update['tags'] = json_encode($this->normalizeTags($overrides['tags']));
            if ($reanalyze) {
                $update['content_hash'] = $prepared['content_hash'];
                $update['original_content'] = $prepared['original_content'] ?? null;
            }
            $this->db->update('knowledge_documents', $update, 'id = ?', [$docId]);

            if ($reanalyze) {
                $customerId = $overrides['customer_id'] ?? null;
                if ($customerId !== null) $customerId = (int) $customerId;

                // Alte Relations die dieses Dokument als Quelle haben loeschen
                $this->db->delete('knowledge_relations', 'source_document_id = ?', [$docId]);

                // Alte Chunks loeschen (CASCADE loescht knowledge_embeddings + knowledge_chunk_entities)
                $this->db->delete('knowledge_chunks', 'document_id = ?', [$docId]);

                // Neue Chunks
                $chunkIds = [];
                foreach ($prepared['chunks'] as $i => $chunk) {
                    $chunkIds[$i] = $this->db->insert('knowledge_chunks', [
                        'document_id' => $docId,
                        'customer_id' => $customerId,
                        'chunk_index' => $i,
                        'content' => $chunk['content'],
                        'word_count' => $chunk['word_count'],
                    ]);
                }

                // Embeddings werden NICHT mehr in knowledge_embeddings geschrieben —
                // qdrant_sync-Worker zieht das bge-m3-Embedding spaeter nach (siehe enqueueQdrantSync unten).

                // Entities dedupliziert hinzufuegen
                $entityIdMap = [];
                foreach ($prepared['metadata']['entities'] as $ent) {
                    $normalizedName = mb_strtolower(trim($ent['name']));
                    if ($normalizedName === '') continue;
                    $this->db->execute(
                        "INSERT INTO knowledge_entities (customer_id, name, normalized_name, type, mention_count)
                         VALUES (?, ?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE mention_count = mention_count + 1, id = LAST_INSERT_ID(id)",
                        [$customerId, $ent['name'], $normalizedName, $ent['type']]
                    );
                    $entityIdMap[$ent['name']] = (int) $this->db->queryValue("SELECT LAST_INSERT_ID()");
                }

                // Chunk-Entity Junction
                foreach ($prepared['entities_per_chunk'] ?? [] as $i => $entNames) {
                    $chunkId = $chunkIds[$i] ?? null;
                    if (!$chunkId) continue;
                    foreach ($entNames as $entName) {
                        if (isset($entityIdMap[$entName])) {
                            try {
                                $this->db->execute(
                                    "INSERT IGNORE INTO knowledge_chunk_entities (chunk_id, entity_id) VALUES (?, ?)",
                                    [$chunkId, $entityIdMap[$entName]]
                                );
                            } catch (\Exception $e) {}
                        }
                    }
                }

                // Neue Relations
                foreach ($prepared['metadata']['relations'] as $rel) {
                    if (!isset($entityIdMap[$rel['from']]) || !isset($entityIdMap[$rel['to']])) continue;
                    $this->db->insert('knowledge_relations', [
                        'customer_id' => $customerId,
                        'from_entity_id' => $entityIdMap[$rel['from']],
                        'to_entity_id' => $entityIdMap[$rel['to']],
                        'type' => $rel['type'],
                        'weight' => $rel['weight'],
                        'source_document_id' => $docId,
                    ]);
                }
            }

            $this->db->commit();

            // Nach reanalyze: verwaiste Entities/Relations aufraeumen
            if ($reanalyze) {
                try {
                    $this->db->execute(
                        "DELETE r FROM knowledge_relations r
                         LEFT JOIN knowledge_entities fe ON r.from_entity_id = fe.id
                         LEFT JOIN knowledge_entities te ON r.to_entity_id = te.id
                         WHERE fe.id IS NULL OR te.id IS NULL"
                    );
                    $this->db->execute(
                        "DELETE e FROM knowledge_entities e
                         LEFT JOIN knowledge_chunk_entities ce ON ce.entity_id = e.id
                         WHERE ce.entity_id IS NULL"
                    );
                    $this->db->execute(
                        "DELETE r FROM knowledge_relations r
                         LEFT JOIN knowledge_entities fe ON r.from_entity_id = fe.id
                         LEFT JOIN knowledge_entities te ON r.to_entity_id = te.id
                         WHERE fe.id IS NULL OR te.id IS NULL"
                    );
                } catch (\Exception $e) {
                    error_log('Reprocess-Cleanup: ' . $e->getMessage());
                }
            }

            // Wissen-V2 nachziehen (delete-then-upsert deckt geänderte chunk_ids ab)
            $this->enqueueQdrantSync($docId, $overrides['customer_id'] ?? null, $userId);
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Chunking — zerlegt Text in semantische Bloecke (absatz-basiert mit Overlap)
     *
     * @param string $text
     * @return array [{index, content, word_count}, ...]
     */
    public function chunkText(string $text): array
    {
        $text = trim(preg_replace('/\r\n?/', "\n", $text));
        if (empty($text)) return [];

        // 1. In Absaetze zerlegen (doppelter Zeilenumbruch)
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

        // 2. Absaetze zu Chunks bis max CHUNK_MAX_CHARS kombinieren
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $para) {
            // Wenn Paragraph allein schon zu gross, harte Trennung
            if (mb_strlen($para) > self::CHUNK_MAX_CHARS) {
                // Current flushen
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                // Paragraph in Teile schneiden (an Satzgrenzen)
                foreach ($this->splitLongParagraph($para) as $part) {
                    $chunks[] = $part;
                }
                continue;
            }

            // Passt zu current?
            $combined = $current === '' ? $para : $current . "\n\n" . $para;
            if (mb_strlen($combined) <= self::CHUNK_MAX_CHARS) {
                $current = $combined;
            } else {
                // Current flushen und neu mit Paragraph starten
                $chunks[] = $current;
                $current = $para;
            }
        }
        if ($current !== '') $chunks[] = $current;

        // 3. Overlap hinzufuegen — nur den LETZTEN SATZ des vorherigen Chunks vornheran
        // (kein Mid-Word-Cut mehr)
        $withOverlap = [];
        foreach ($chunks as $i => $chunk) {
            if ($i > 0) {
                $prev = $chunks[$i - 1];
                $overlap = '';
                // Versuche letzten vollstaendigen Satz zu finden
                if (preg_match('/(?:^|[.!?]\s+)([^.!?]{20,' . self::CHUNK_OVERLAP_CHARS . '}[.!?])\s*$/s', $prev, $m)) {
                    $overlap = trim($m[1]);
                } elseif (mb_strlen($prev) > self::CHUNK_OVERLAP_CHARS) {
                    // Fallback: ab letztem Satzende schneiden
                    $tail = mb_substr($prev, -self::CHUNK_OVERLAP_CHARS);
                    if (preg_match('/[.!?]\s+(.+)$/s', $tail, $m)) {
                        $overlap = trim($m[1]);
                    }
                }
                if ($overlap !== '') {
                    $chunk = $overlap . "\n\n" . $chunk;
                }
            }
            $withOverlap[] = $chunk;
        }

        // 4. Zu Struktur konvertieren
        $result = [];
        foreach ($withOverlap as $i => $content) {
            $content = trim($content);
            if (mb_strlen($content) < self::CHUNK_MIN_CHARS && $i > 0) continue;

            $result[] = [
                'index' => count($result),
                'content' => $content,
                'word_count' => str_word_count($content),
            ];
        }

        return $result;
    }

    private function splitLongParagraph(string $text): array
    {
        // An Satzgrenzen splitten, dann zu Chunks kombinieren
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $parts = [];
        $current = '';

        foreach ($sentences as $s) {
            if (mb_strlen($current) + mb_strlen($s) + 1 > self::CHUNK_MAX_CHARS) {
                if ($current !== '') $parts[] = $current;
                // Falls einzelner Satz zu lang, hart abschneiden
                if (mb_strlen($s) > self::CHUNK_MAX_CHARS) {
                    while (mb_strlen($s) > self::CHUNK_MAX_CHARS) {
                        $parts[] = mb_substr($s, 0, self::CHUNK_MAX_CHARS);
                        $s = mb_substr($s, self::CHUNK_MAX_CHARS);
                    }
                }
                $current = $s;
            } else {
                $current = $current === '' ? $s : $current . ' ' . $s;
            }
        }
        if ($current !== '') $parts[] = $current;
        return $parts;
    }

    /**
     * Normalisiert Tag-Listen: lowercase, getrimmt, dedupliziert, leere weg,
     * Muell-Tags ('asana','task','overview') gefiltert. Wird beim Insert
     * und Update in commit()/reprocess() angewendet — verhindert Tag-Inflation
     * durch Schreibweise-Varianten und redundante Source-Marker.
     */
    private function normalizeTags($tags): array
    {
        if (!is_array($tags)) return [];
        $blocklist = ['asana', 'task', 'overview']; // source-redundant
        $out = [];
        foreach ($tags as $t) {
            $t = mb_strtolower(trim((string) $t));
            if ($t === '') continue;
            if (in_array($t, $blocklist, true)) continue;
            $out[$t] = true;
        }
        return array_keys($out);
    }
}
