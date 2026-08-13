<?php
/**
 * QdrantSyncService — synchronisiert knowledge_chunks ins parallele Wissen-V2-System
 * (bge-m3 + Qdrant). Wird vom Worker (job_type=qdrant_sync) und vom Backfill-CLI genutzt.
 *
 * Konvention: Qdrant-Point-ID = chunk_id. Payload customer_id = <int> oder 0 (global).
 * Fortschritt/Status in kb_qdrant_sync.
 */

namespace Services;

use Core\Database;
use Core\Settings;

class QdrantSyncService
{
    private Database $db;
    private QdrantClient $qdrant;
    private LocalEmbeddingService $embedder;
    private string $model;

    public function __construct(Database $db, QdrantClient $qdrant, LocalEmbeddingService $embedder)
    {
        $this->db = $db;
        $this->qdrant = $qdrant;
        $this->embedder = $embedder;
        $this->model = $embedder->getModel();
    }

    /** Baut den Service aus den Settings. */
    public static function fromSettings(Database $db): self
    {
        $dim     = (int) (Settings::get('embedding_local_dim', '1024') ?: 1024);
        $qdrant  = new QdrantClient(
            (string) Settings::get('qdrant_url', 'http://localhost:6333'),
            (string) Settings::get('qdrant_api_key', ''),
            'knowledge_bge_m3',
            $dim
        );
        $embedder = new LocalEmbeddingService(
            (string) Settings::get('embedding_local_url', 'https://ki.thoxan.com/embeddings/embeddings'),
            (string) Settings::get('local_api_key', ''),
            (string) Settings::get('embedding_local_model', 'bge-m3'),
            $dim
        );
        return new self($db, $qdrant, $embedder);
    }

    public function qdrant(): QdrantClient { return $this->qdrant; }
    public function tokensUsed(): int { return $this->embedder->tokensUsed; }

    public function ensure(): void { $this->qdrant->ensureCollection(); }

    /** Alle Punkte eines Dokuments aus Qdrant + Tracking entfernen. */
    public function deleteDocument(int $docId): void
    {
        $this->qdrant->deleteByDocument($docId);
        $this->db->execute("DELETE FROM kb_qdrant_sync WHERE document_id = ?", [$docId]);
    }

    /**
     * Ein Dokument (alle aktuellen Chunks) neu synchronisieren.
     * delete-then-upsert deckt reprocess ab (geänderte chunk_ids).
     * @return int Anzahl synchronisierter Chunks
     */
    public function syncDocument(int $docId): int
    {
        $this->ensure();
        $this->qdrant->deleteByDocument($docId);
        $this->db->execute("DELETE FROM kb_qdrant_sync WHERE document_id = ?", [$docId]);

        $rows = $this->db->query(
            "SELECT id, document_id, customer_id, content FROM knowledge_chunks WHERE document_id = ? ORDER BY chunk_index",
            [$docId]
        );
        return $this->syncChunkRows($rows);
    }

    /**
     * Eine Menge Chunk-Zeilen einbetten + nach Qdrant upserten + Tracking aktualisieren.
     * @param array $rows je ['id','document_id','customer_id','content']
     * @return int Anzahl synchronisierter Chunks
     */
    public function syncChunkRows(array $rows): int
    {
        if (empty($rows)) return 0;

        // Dokument-Meta fuer Payload (Titel/Kategorie/Quelle) gebuendelt laden
        $docIds = array_values(array_unique(array_map(fn($r) => (int)$r['document_id'], $rows)));
        $ph = implode(',', array_fill(0, count($docIds), '?'));
        $meta = [];
        foreach ($this->db->query("SELECT id, title, category, source_type FROM knowledge_documents WHERE id IN ($ph)", $docIds) as $m) {
            $meta[(int)$m['id']] = $m;
        }

        $texts = array_map(fn($r) => (string)$r['content'], array_values($rows));
        $embs  = $this->embedder->embedBatch($texts); // index-aligned zur Eingabe

        $points = [];
        $rowsList = array_values($rows);
        foreach ($rowsList as $i => $r) {
            $vec = $embs[$i]['vector'] ?? null;
            if (!is_array($vec)) continue;
            $cid   = (int)$r['id'];
            $docId = (int)$r['document_id'];
            $m = $meta[$docId] ?? [];
            $points[] = [
                'id'      => $cid,
                'vector'  => $vec,
                'payload' => [
                    'chunk_id'    => $cid,
                    'document_id' => $docId,
                    'customer_id' => $r['customer_id'] === null ? 0 : (int)$r['customer_id'],
                    'title'       => (string)($m['title'] ?? ''),
                    'category'    => (string)($m['category'] ?? ''),
                    'source_type' => (string)($m['source_type'] ?? ''),
                ],
            ];
        }

        $this->qdrant->upsertPoints($points);

        // Tracking: alle Zeilen als synced markieren
        $now = date('Y-m-d H:i:s');
        $stmt = "INSERT INTO kb_qdrant_sync (chunk_id, document_id, customer_id, status, model, error, synced_at)
                 VALUES (?, ?, ?, 'synced', ?, NULL, ?)
                 ON DUPLICATE KEY UPDATE status='synced', model=VALUES(model), error=NULL, synced_at=VALUES(synced_at), document_id=VALUES(document_id), customer_id=VALUES(customer_id)";
        foreach ($rowsList as $r) {
            $this->db->execute($stmt, [
                (int)$r['id'], (int)$r['document_id'],
                $r['customer_id'] === null ? null : (int)$r['customer_id'],
                $this->model, $now,
            ]);
        }

        return count($points);
    }

    /** Tracking-Zeilen einer Chunk-Menge auf 'error' setzen (bei Batch-Fehler). */
    public function markError(array $chunkIds, string $message): void
    {
        if (empty($chunkIds)) return;
        $ph = implode(',', array_fill(0, count($chunkIds), '?'));
        try {
            $this->db->execute(
                "UPDATE kb_qdrant_sync SET status='error', error=? WHERE chunk_id IN ($ph)",
                array_merge([mb_substr($message, 0, 1000)], array_map('intval', $chunkIds))
            );
        } catch (\Throwable $e) { error_log('kb_qdrant_sync markError: ' . $e->getMessage()); }
    }
}
