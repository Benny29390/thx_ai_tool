<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * PpKnowledgeSyncService — Projektplaene als Quelle in die Wissensdatenbank.
 *
 * Granularitaet: 1 knowledge_document pro Plan. N Chunks (je Item ein Chunk).
 * Damit ist jedes Item einzeln per RAG findbar, der Plan als Klammer bleibt.
 *
 * Lifecycle:
 * - syncPlan(int) wird vom Cron getriggert wenn pp_plans.knowledge_dirty=1
 *   UND knowledge_dirty_since aelter als DEBOUNCE_SECONDS ist
 * - removePlan(int) wird beim Hartloeschen eines Plans aufgerufen
 *   (loescht das Knowledge-Doc + alle Chunks + Embeddings + Relations)
 */
class PpKnowledgeSyncService
{
    public const DEBOUNCE_SECONDS = 30;
    public const SOURCE_TYPE      = 'projektplan';
    /** Plan-Status, die in die Wissensdatenbank fliessen. 'entwurf' bewusst raus. */
    public const SYNCABLE_STATUS  = ['aktiv','einzelprojekt','reporting','abgeschlossen','archiviert'];

    private Database $db;
    private KnowledgeIngestService $ingest;

    public function __construct(Database $db, KnowledgeIngestService $ingest)
    {
        $this->db = $db;
        $this->ingest = $ingest;
    }

    /**
     * Erzeugt eine Instance mit Default-Dependencies. Embeddings werden NICHT mehr
     * von OpenAI gemacht — der qdrant_sync-Worker zieht bge-m3 (ki.thoxan.com) nach.
     * Der OpenAI-Key wird nur noch fuer die LLM-Metadaten-Extraktion (Entities/Relations) benoetigt.
     */
    public static function build(Database $db): self
    {
        require_once SERVICES_PATH . '/AIService.php';
        require_once SERVICES_PATH . '/KnowledgeExtractionService.php';
        require_once SERVICES_PATH . '/KnowledgeIngestService.php';

        $key = (string) Settings::get('openai_api_key');
        if ($key === '') {
            throw new \RuntimeException('OpenAI-API-Key nicht konfiguriert — kein Knowledge-Sync moeglich (LLM-Extraktion).');
        }
        $ai  = new AIService($key, 'openai');
        $extract = new KnowledgeExtractionService($ai);
        $ingest  = new KnowledgeIngestService($db, null, $extract);
        return new self($db, $ingest);
    }

    // ============================================================
    //  PUBLIC
    // ============================================================

    /**
     * Synchronisiert einen einzelnen Plan in die Wissensdatenbank.
     *
     * Return: ['action' => 'created'|'updated'|'skipped'|'removed', 'doc_id' => int|null, 'reason' => string]
     */
    public function syncPlan(int $planId, int $userId = 0): array
    {
        try {
            $r = $this->doSyncPlan($planId, $userId);
            // Auf Erfolg: Error-Feld zuruecksetzen falls vorher gesetzt.
            $this->db->execute('UPDATE pp_plans SET knowledge_sync_error = NULL WHERE id = ?', [$planId]);
            return $r;
        } catch (\Throwable $e) {
            // Fehler in pp_plans persistieren, damit das UI ihn anzeigen kann.
            $this->db->execute(
                'UPDATE pp_plans SET knowledge_sync_error = ? WHERE id = ?',
                [mb_substr($e->getMessage(), 0, 500), $planId]
            );
            throw $e;
        }
    }

    private function doSyncPlan(int $planId, int $userId = 0): array
    {
        $plan = $this->loadPlanWithRows($planId);
        if (!$plan) {
            return ['action' => 'skipped', 'doc_id' => null, 'reason' => 'Plan nicht gefunden'];
        }

        // Plan ist soft-deleted -> Doc entfernen
        if ((int) $plan['state'] !== 1) {
            return $this->removePlan($planId);
        }

        // Plan ohne Kunden -> Pflicht-Scope verletzt. dirty-Flag zuruecksetzen,
        // damit der Cron diesen Plan nicht in Endlosschleife jede Minute aufgreift.
        if (empty($plan['customer_id'])) {
            $this->clearDirtyFlag($planId);
            return ['action' => 'skipped', 'doc_id' => null, 'reason' => 'Plan ohne Kundenzuordnung — Skip.'];
        }

        // Status nicht syncbar (Entwurf) -> Doc entfernen falls vorhanden
        if (!in_array($plan['plan_status'], self::SYNCABLE_STATUS, true)) {
            // Doc existiert? -> weg, da Status nicht mehr passt
            if ($plan['knowledge_doc_id']) {
                return $this->removePlan($planId);
            }
            $this->clearDirtyFlag($planId);
            return ['action' => 'skipped', 'doc_id' => null, 'reason' => 'Status "' . $plan['plan_status'] . '" wird nicht gesynct.'];
        }

        // Item-Chunks bauen
        [$fullText, $chunks] = $this->buildPlanContent($plan);
        if (empty($chunks)) {
            // Plan ohne Items: Doc entfernen falls vorhanden
            if ($plan['knowledge_doc_id']) {
                return $this->removePlan($planId);
            }
            $this->clearDirtyFlag($planId);
            return ['action' => 'skipped', 'doc_id' => null, 'reason' => 'Plan ohne Items.'];
        }

        // Hash-Vergleich ZUERST (ohne LLM): bildet Volltext + Chunk-Grenzen ab, so triggert
        // auch eine reine Chunk-Strategie-Aenderung einen Re-Sync. Unveraenderte Plaene werden
        // billig uebersprungen, bevor der teure prepareFromChunks (LLM-Extraktion) laeuft.
        $hashChunks = array_values(array_filter(array_map('trim', $chunks)));
        $newHash = \Services\KnowledgeIngestService::chunkContentHash($fullText, $hashChunks);
        $existingHash = null;
        if ($plan['knowledge_doc_id']) {
            $existingHash = $this->db->queryValue(
                'SELECT content_hash FROM knowledge_documents WHERE id = ? AND is_active = 1',
                [(int) $plan['knowledge_doc_id']]
            );
        }
        if ($existingHash && $existingHash === $newHash) {
            // Trotzdem dirty-Flag zurueckziehen, damit der Cron nicht ewig retry'ed
            $this->markClean($planId, (int) $plan['knowledge_doc_id']);
            return ['action' => 'skipped', 'doc_id' => (int) $plan['knowledge_doc_id'], 'reason' => 'unchanged'];
        }

        // Erst jetzt (Aenderung erkannt) die teure Vorbereitung (1 Chunk pro Aufgabe + LLM-Extraktion).
        $context = [
            'customer_name' => $plan['customer_name'] ?: null,
            'doc_context'   => 'Projektplan fuer ' . ($plan['customer_name'] ?: 'Kunden') . '.',
        ];
        $prepared = $this->ingest->prepareFromChunks($chunks, $fullText, $context);

        $title       = $this->buildDocTitle($plan);
        $description = $this->buildDocDescription($plan, count($chunks));
        $tags        = $this->buildDocTags($plan);
        $category    = 'Projektplan';

        $overrides = [
            'title'       => $title,
            'description' => $description,
            'customer_id' => (int) $plan['customer_id'],
            'category'    => $category,
            'tags'        => $tags,
        ];

        if ($plan['knowledge_doc_id']) {
            // Re-Process bestehendes Dokument
            $this->ingest->reprocess(
                (int) $plan['knowledge_doc_id'],
                $fullText,
                $overrides,
                $context,
                $userId,
                true,
                $prepared   // per-Item-Chunks nutzen statt fixem Re-Chunking des Volltextes
            );
            $docId = (int) $plan['knowledge_doc_id'];
            $action = 'updated';
        } else {
            $docId = $this->ingest->commit($prepared, $overrides, [
                'source_type' => self::SOURCE_TYPE,
                'source_ref'  => '/admin/projektplanner/plan/' . $planId,
                'external_id' => 'plan:' . $planId,
                'created_by'  => $userId,
            ]);
            $action = 'created';
        }

        $this->markClean($planId, $docId);
        return ['action' => $action, 'doc_id' => $docId, 'reason' => ''];
    }

    /**
     * Entfernt das Knowledge-Doc eines Plans samt Chunks/Embeddings/Relations.
     * Wird beim Hartloeschen eines Plans aufgerufen.
     */
    public function removePlan(int $planId): array
    {
        $docId = (int) $this->db->queryValue(
            "SELECT id FROM knowledge_documents WHERE source_type = ? AND external_id = ?",
            [self::SOURCE_TYPE, 'plan:' . $planId]
        );
        if (!$docId) {
            // Kein Doc da, das wir loeschen muessten — aber dirty-Flag trotzdem
            // zuruecksetzen, sonst loopt der Cron jede Minute uselessly.
            $this->clearDirtyFlag($planId);
            return ['action' => 'skipped', 'doc_id' => null, 'reason' => 'Kein Knowledge-Doc vorhanden.'];
        }

        $this->db->beginTransaction();
        try {
            // Chunk-IDs sammeln
            $chunkIds = array_column(
                $this->db->query('SELECT id FROM knowledge_chunks WHERE document_id = ?', [$docId]) ?: [],
                'id'
            );
            if (!empty($chunkIds)) {
                $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
                $this->db->execute("DELETE FROM knowledge_chunk_entities WHERE chunk_id IN ($placeholders)", $chunkIds);
                $this->db->execute("DELETE FROM knowledge_embeddings    WHERE chunk_id IN ($placeholders)", $chunkIds);
                $this->db->execute("DELETE FROM knowledge_chunks        WHERE id       IN ($placeholders)", $chunkIds);
            }
            $this->db->execute('DELETE FROM knowledge_relations WHERE source_document_id = ?', [$docId]);
            $this->db->execute('DELETE FROM knowledge_documents WHERE id = ?', [$docId]);

            // pp_plans Felder zuruecksetzen falls Plan noch existiert
            $this->db->execute(
                'UPDATE pp_plans SET knowledge_doc_id = NULL, knowledge_synced_at = NULL,
                                     knowledge_dirty = 0, knowledge_dirty_since = NULL
                 WHERE id = ?',
                [$planId]
            );

            $this->db->commit();

            // Qdrant-Sync (Best-Effort, Fehler darf nicht den Hauptpfad brechen)
            try {
                (new JobQueue($this->db))->createJob([
                    'user_id'         => 1,
                    'job_type'        => 'qdrant_sync',
                    'sections_config' => ['op' => 'delete_document', 'document_id' => $docId],
                    'priority'        => -5,
                ]);
            } catch (\Throwable $e) {
                error_log('pp_knowledge qdrant delete enqueue fail: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return ['action' => 'removed', 'doc_id' => $docId, 'reason' => ''];
    }

    /** Markiert einen Plan als sync-bedürftig (debounced verarbeitet vom Cron). */
    public function markDirty(int $planId): void
    {
        $this->db->execute(
            'UPDATE pp_plans SET knowledge_dirty = 1, knowledge_dirty_since = NOW() WHERE id = ?',
            [$planId]
        );
    }

    /** Setzt nur das dirty-Flag zurueck — fuer terminale Skip-Pfade, wo kein Doc
     *  geschrieben wird, der Cron den Plan aber sonst jede Minute wieder aufgreifen
     *  wuerde (z.B. Plan ohne Kunde, Plan ohne Items, geloeschter Plan ohne Doc). */
    private function clearDirtyFlag(int $planId): void
    {
        try {
            $this->db->execute(
                'UPDATE pp_plans SET knowledge_dirty = 0, knowledge_dirty_since = NULL WHERE id = ?',
                [$planId]
            );
        } catch (\Throwable $_) { /* Spalte fehlt evtl. — ignorieren */ }
    }

    /** Liefert IDs aller Plaene, die debounced sync-bereit sind. */
    public function findDirtyPlans(int $limit = 50): array
    {
        $rows = $this->db->query(
            "SELECT id FROM pp_plans
             WHERE knowledge_dirty = 1
               AND (knowledge_dirty_since IS NULL OR knowledge_dirty_since <= DATE_SUB(NOW(), INTERVAL ? SECOND))
             ORDER BY knowledge_dirty_since ASC
             LIMIT $limit",
            [self::DEBOUNCE_SECONDS]
        ) ?: [];
        return array_column($rows, 'id');
    }

    // ============================================================
    //  PRIVATE — Plan-Inhalt aufbereiten
    // ============================================================

    private function loadPlanWithRows(int $planId): ?array
    {
        $plan = $this->db->queryOne(
            "SELECT p.*, c.name AS customer_name, c.abbreviation AS customer_abbr
             FROM pp_plans p LEFT JOIN customers c ON c.id = p.customer_id
             WHERE p.id = ?",
            [$planId]
        );
        if (!$plan) return null;
        $plan['rows'] = $this->db->query(
            'SELECT * FROM pp_plan_rows WHERE plan_id = ? ORDER BY position ASC, id ASC',
            [$planId]
        ) ?: [];
        return $plan;
    }

    /**
     * Baut Volltext + Item-Chunks fuer einen Plan.
     * Volltext = alles aneinandergehaengt (fuer Hash + LLM-Extraktion).
     * Chunks = ein String pro Item, mit Plan/Sektion-Kontext als Praefix.
     */
    private function buildPlanContent(array $plan): array
    {
        $customer = $plan['customer_name'] ?: '— ohne Kunde —';
        $period = '';
        if ($plan['period_from'] && $plan['period_to']) {
            $period = date('d.m.Y', strtotime($plan['period_from'])) . ' – ' . date('d.m.Y', strtotime($plan['period_to']));
        }
        $planTitle = $plan['title'] ?: '(unbenannter Plan)';
        $statusLabel = $this->statusLabel($plan['plan_status']);

        $headerCtx = "Kunde: $customer\nPlan: $planTitle"
            . ($period !== '' ? "\nZeitraum: $period" : '')
            . "\nStatus: $statusLabel";

        $chunks = [];
        $fullParts = [$headerCtx, ''];

        $currentSection = '— ohne Sektion —';
        foreach ($plan['rows'] as $row) {
            if ($row['row_type'] === 'section') {
                $currentSection = trim((string) $row['description']) ?: '— ohne Sektion —';
                $fullParts[] = "## Sektion: $currentSection";
                $fullParts[] = '';
                continue;
            }
            if ($row['row_type'] === 'note') {
                $note = trim((string) ($row['notes'] ?: $row['description']));
                if ($note !== '') {
                    $fullParts[] = "Notiz (Sektion $currentSection): $note";
                    $fullParts[] = '';
                }
                continue;
            }
            if ($row['row_type'] !== 'item') continue;
            if ((int) $row['is_placeholder']) continue;
            $desc = trim((string) ($row['description'] ?? ''));
            if ($desc === '') continue;

            $itemBlock = $this->renderItemChunk($row, $headerCtx, $currentSection);
            $chunks[] = $itemBlock;
            $fullParts[] = $itemBlock;
            $fullParts[] = '';
        }

        $fullText = implode("\n", $fullParts);
        return [$fullText, $chunks];
    }

    private function renderItemChunk(array $row, string $headerCtx, string $section): string
    {
        $lines = [];
        $lines[] = $headerCtx;
        $lines[] = "Sektion: $section";
        $lines[] = '';
        $lines[] = 'Aufgabe: ' . trim((string) $row['description']);
        $bits = [];
        if (!empty($row['timeframe'])) $bits[] = 'Zeitraum: ' . $row['timeframe'];
        if (!empty($row['deadline']))  $bits[] = 'Termin: ' . $row['deadline'];
        if ($bits) $lines[] = implode('   ', $bits);

        $personBits = [];
        if (!empty($row['lead_responsible'])) $personBits[] = 'Lead: ' . trim((string) $row['lead_responsible']);
        if (!empty($row['responsible'])) {
            $team = trim((string) $row['responsible']);
            if ($team !== '') $personBits[] = 'Team: ' . $team;
        }
        if ($personBits) $lines[] = implode('   ', $personBits);

        $hourBits = [];
        $ist = (float) ($row['ist_hours'] ?? 0);
        $soll = (float) ($row['planned_hours'] ?? 0);
        if ($ist > 0)  $hourBits[] = 'Ist: ' . number_format($ist, 2, ',', '') . ' h';
        if ($soll > 0) $hourBits[] = 'Soll: ' . number_format($soll, 2, ',', '') . ' h';
        $hourBits[] = 'Status: ' . ((int) $row['is_done'] ? 'erledigt' : 'offen');
        if ((int) $row['is_focus']) $hourBits[] = 'Fokus';
        if ((int) $row['no_ticket']) $hourBits[] = 'kein Asana-Ticket';
        $lines[] = implode('   ', $hourBits);

        if (!empty($row['notes'])) {
            $notes = trim((string) $row['notes']);
            if ($notes !== '') $lines[] = 'Bemerkung: ' . $notes;
        }
        if (!empty($row['asana_url'])) {
            $lines[] = 'Asana-Task: ' . $row['asana_url'];
        }

        return implode("\n", $lines);
    }

    private function statusLabel(string $s): string
    {
        return [
            'entwurf' => 'Entwurf',
            'aktiv' => 'Aktiv',
            'einzelprojekt' => 'Einzelprojekt',
            'reporting' => 'Reporting',
            'abgeschlossen' => 'Abgeschlossen',
            'archiviert' => 'Archiviert',
        ][$s] ?? $s;
    }

    private function buildDocTitle(array $plan): string
    {
        $abbr = $plan['customer_abbr'] ?: $plan['customer_name'] ?: '?';
        $t = '[Projektplan] ' . $abbr . ' — ' . ($plan['title'] ?: '(unbenannt)');
        if ($plan['period_from'] && $plan['period_to']) {
            $t .= ' (' . date('m/Y', strtotime($plan['period_from']));
            $endMonth = date('m/Y', strtotime($plan['period_to']));
            if ($endMonth !== date('m/Y', strtotime($plan['period_from']))) $t .= '–' . $endMonth;
            $t .= ')';
        }
        return mb_substr($t, 0, 500);
    }

    private function buildDocDescription(array $plan, int $itemCount): string
    {
        $period = ($plan['period_from'] && $plan['period_to'])
            ? ' (' . date('d.m.Y', strtotime($plan['period_from'])) . '–' . date('d.m.Y', strtotime($plan['period_to'])) . ')'
            : '';
        return 'Projektplan fuer ' . ($plan['customer_name'] ?: '?') . $period
            . ' — Status: ' . $this->statusLabel($plan['plan_status'])
            . ', ' . $itemCount . ' Aufgaben.';
    }

    private function buildDocTags(array $plan): array
    {
        $tags = ['projektplan', $plan['plan_status']];
        if (!empty($plan['customer_abbr'])) $tags[] = strtolower($plan['customer_abbr']);
        if ($plan['period_from']) $tags[] = (string) date('Y', strtotime($plan['period_from']));
        return array_values(array_unique($tags));
    }

    private function markClean(int $planId, int $docId): void
    {
        $this->db->execute(
            'UPDATE pp_plans
             SET knowledge_doc_id = ?, knowledge_synced_at = NOW(),
                 knowledge_dirty = 0, knowledge_dirty_since = NULL
             WHERE id = ?',
            [$docId, $planId]
        );
    }
}
