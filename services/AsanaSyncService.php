<?php
/**
 * Asana Sync Service — Liest Asana-Projekte und legt pro Task ein Wissen-Dokument an.
 *
 * Dokumente bekommen:
 *  source_type = 'asana'
 *  external_id = 'task:<GID>'  oder  'project:<GID>'
 *
 * Beim wiederholten Sync wird ein bestehendes Dokument via reprocess() aktualisiert.
 */

namespace Services;

use Core\Database;

class AsanaSyncService
{
    private Database $db;
    private AsanaService $asana;
    private KnowledgeService $knowledgeService;
    private KnowledgeIngestService $ingestService;
    private DocumentProcessor $docProcessor;
    private int $systemUserId;

    public function __construct(
        Database $db,
        AsanaService $asana,
        KnowledgeService $knowledgeService,
        KnowledgeIngestService $ingestService,
        ?DocumentProcessor $docProcessor = null,
        int $systemUserId = 1
    ) {
        $this->db = $db;
        $this->asana = $asana;
        $this->knowledgeService = $knowledgeService;
        $this->ingestService = $ingestService;
        $this->docProcessor = $docProcessor ?? new DocumentProcessor();
        $this->systemUserId = $systemUserId;
    }

    /**
     * Synchronisiert alle Asana-Projekte eines Kunden.
     *
     * @return array Stats: ['projects' => N, 'tasks_synced' => N, 'attachments' => N, 'errors' => [...]]
     */
    public function syncCustomer(int $customerId, bool $forceFull = false): array
    {
        $customer = $this->db->queryOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
        if (!$customer) throw new \RuntimeException('Kunde nicht gefunden');

        $settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
        $asanaCfg = $settings['asana'] ?? [];
        $projectGids = $asanaCfg['project_gids'] ?? [];

        if (empty($projectGids)) {
            return ['projects' => 0, 'tasks_synced' => 0, 'attachments' => 0, 'errors' => []];
        }

        $modifiedSince = null;
        if (!$forceFull && !empty($asanaCfg['last_sync_at'])) {
            try { $modifiedSince = new \DateTime($asanaCfg['last_sync_at']); } catch (\Exception $e) {}
        }

        $stats = ['projects' => 0, 'tasks_synced' => 0, 'attachments' => 0, 'errors' => []];

        foreach ($projectGids as $projectGid) {
            try {
                $r = $this->syncProject($customerId, (string) $projectGid, $modifiedSince);
                $stats['projects']++;
                $stats['tasks_synced'] += $r['tasks_synced'];
                $stats['attachments'] += $r['attachments'];
            } catch (\Exception $e) {
                $stats['errors'][] = ['project_gid' => $projectGid, 'error' => $e->getMessage()];
                error_log("Asana sync project {$projectGid}: " . $e->getMessage());
            }
        }

        // last_sync_at NUR updaten wenn der Sync ohne Projekt-Fehler durchlief — sonst bleiben
        // verpasste Tasks bei der naechsten Iteration wieder ausserhalb des modified_since-Fensters.
        if (empty($stats['errors'])) {
            $settings['asana'] = $asanaCfg;
            $settings['asana']['last_sync_at'] = (new \DateTime())->format(\DateTime::ATOM);
            $this->db->update('customers', ['settings' => json_encode($settings)], 'id = ?', [$customerId]);
        }

        return $stats;
    }

    /**
     * Synchronisiert EIN Projekt: Project-Overview-Doc + alle Tasks (inkrementell).
     */
    public function syncProject(int $customerId, string $projectGid, ?\DateTime $modifiedSince = null): array
    {
        // 1. Project-Overview als eigenes Dokument
        $project = $this->asana->getProject($projectGid);
        if (!empty($project)) {
            $this->upsertProjectOverview($customerId, $project);
        }

        // 2. Tasks holen
        $tasks = $this->asana->getTasks($projectGid, $modifiedSince);
        $tasksSynced = 0;
        $attachmentCount = 0;

        foreach ($tasks as $task) {
            try {
                $r = $this->upsertTask($customerId, $project, $task);
                $tasksSynced++;
                $attachmentCount += $r['attachments'];
            } catch (\Exception $e) {
                error_log("Asana sync task {$task['gid']}: " . $e->getMessage());
            }
        }

        return ['tasks_synced' => $tasksSynced, 'attachments' => $attachmentCount];
    }

    /**
     * Project-Overview als Knowledge-Dokument (upsert).
     */
    private function upsertProjectOverview(int $customerId, array $project): void
    {
        $title = '[Asana-Projekt] ' . ($project['name'] ?? 'Unbenanntes Projekt');
        $notes = strip_tags($project['html_notes'] ?? $project['notes'] ?? '');

        $text = "PROJEKT: " . ($project['name'] ?? '') . "\n\n";
        $text .= "Team: " . ($project['team']['name'] ?? '-') . "\n";
        $text .= "Owner: " . ($project['owner']['name'] ?? '-') . "\n";
        $text .= "Erstellt: " . ($project['created_at'] ?? '-') . "\n";
        $text .= "Geaendert: " . ($project['modified_at'] ?? '-') . "\n";
        if (!empty($project['archived'])) $text .= "Status: Archiviert\n";
        if (!empty($project['members'])) {
            $names = array_map(fn($m) => $m['name'] ?? '', $project['members']);
            $text .= "Mitglieder: " . implode(', ', array_filter($names)) . "\n";
        }
        $text .= "\nBESCHREIBUNG:\n" . trim($notes ?: '-') . "\n";

        $extId = 'project:' . $project['gid'];
        // Tags 'asana' und 'overview' redundant — source_type sagt das schon.
        $this->upsertDocument($customerId, $title, $text, $extId, $project['permalink_url'] ?? null, [
            'projekt',
        ]);
    }

    /**
     * Einzelnen Task als Knowledge-Dokument anlegen oder updaten.
     */
    private function upsertTask(int $customerId, array $project, array $task): array
    {
        $taskGid = $task['gid'];
        // [Asana]-Praefix entfaellt — source_type sagt das schon, und im UI hat
        // die Karte ohnehin ein orangefarbenes Asana-Symbol.
        $title = $task['name'] ?? 'Unbenannter Task';

        // Vollstaendigen Text bauen
        $lines = [];
        $lines[] = "TASK: " . ($task['name'] ?? '');
        $lines[] = "Projekt: " . ($project['name'] ?? '-');
        $lines[] = "Status: " . (!empty($task['completed']) ? 'Erledigt' : 'Offen');
        if (!empty($task['assignee'])) {
            $lines[] = "Zugewiesen an: " . ($task['assignee']['name'] ?? '') . " <" . ($task['assignee']['email'] ?? '') . ">";
        }
        if (!empty($task['due_on'])) $lines[] = "Faellig: " . $task['due_on'];
        if (!empty($task['due_at'])) $lines[] = "Faellig (Zeit): " . $task['due_at'];
        if (!empty($task['start_on'])) $lines[] = "Start: " . $task['start_on'];
        $lines[] = "Erstellt: " . ($task['created_at'] ?? '-');
        $lines[] = "Geaendert: " . ($task['modified_at'] ?? '-');
        if (!empty($task['permalink_url'])) $lines[] = "Asana-Link: " . $task['permalink_url'];

        // Custom Fields
        if (!empty($task['custom_fields'])) {
            $cfLines = [];
            foreach ($task['custom_fields'] as $cf) {
                $val = $cf['display_value'] ?? $cf['text_value'] ?? $cf['number_value'] ?? ($cf['enum_value']['name'] ?? null);
                if ($val !== null && $val !== '') $cfLines[] = "  - " . ($cf['name'] ?? '') . ": " . $val;
            }
            if ($cfLines) {
                $lines[] = "\nCUSTOM FIELDS:";
                $lines = array_merge($lines, $cfLines);
            }
        }

        // Tags
        if (!empty($task['tags'])) {
            $tagNames = array_map(fn($t) => $t['name'] ?? '', $task['tags']);
            $lines[] = "Tags: " . implode(', ', array_filter($tagNames));
        }

        // Beschreibung
        $notes = strip_tags($task['html_notes'] ?? $task['notes'] ?? '');
        if ($notes) {
            $lines[] = "\nBESCHREIBUNG:";
            $lines[] = trim($notes);
        }

        // Comments
        try {
            $stories = $this->asana->getTaskStories($taskGid, true);
            if (!empty($stories)) {
                $lines[] = "\nKOMMENTARE:";
                foreach ($stories as $s) {
                    $author = $s['created_by']['name'] ?? 'Unbekannt';
                    $when = substr($s['created_at'] ?? '', 0, 10);
                    $body = trim(strip_tags($s['html_text'] ?? $s['text'] ?? ''));
                    if ($body !== '') $lines[] = "[{$when}] {$author}: {$body}";
                }
            }
        } catch (\Exception $e) {
            // Comments-Fehler nicht fatal
        }

        // Attachments runterladen + via DocumentProcessor extrahieren
        $attachmentCount = 0;
        try {
            $attachments = $this->asana->getTaskAttachments($taskGid);
            $attachmentTexts = [];
            foreach ($attachments as $att) {
                if (empty($att['download_url'])) continue;
                $name = $att['name'] ?? 'attachment';
                $tmpFile = $this->asana->downloadAttachment($att['download_url'], $name);
                if (!$tmpFile) continue;
                try {
                    $mime = mime_content_type($tmpFile) ?: '';
                    $result = $this->docProcessor->processFile($tmpFile, $mime, $name);
                    $extracted = trim($result['text'] ?? '');
                    if ($extracted !== '') {
                        $attachmentTexts[] = "--- ANHANG: {$name} ---\n" . mb_substr($extracted, 0, 10000);
                        $attachmentCount++;
                    }
                } catch (\Exception $e) {
                    // einzelner Anhang-Fehler nicht fatal
                } finally {
                    @unlink($tmpFile);
                }
            }
            if ($attachmentTexts) {
                $lines[] = "\nANHAENGE:\n" . implode("\n\n", $attachmentTexts);
            }
        } catch (\Exception $e) {
            // Attachments-Fehler nicht fatal
        }

        $text = implode("\n", $lines);
        $extId = 'task:' . $taskGid;
        // Tags 'asana' und 'task' wuerden auf 100% bzw 99% der Asana-Docs
        // landen — als Filter wertlos. Auch 'erledigt'/'offen' kommt nur
        // dann rein wenn der Sync diese Information braucht (Status liegt
        // separat im Body-Text). → komplett raus.
        $this->upsertDocument($customerId, $title, $text, $extId, $task['permalink_url'] ?? null, []);

        return ['attachments' => $attachmentCount];
    }

    /**
     * Generic Upsert: existiert ein Doc mit dieser external_id → reprocess(); sonst commit().
     */
    private function upsertDocument(int $customerId, string $title, string $text, string $externalId, ?string $sourceRef, array $tags): void
    {
        $existing = $this->knowledgeService->findByExternalId('asana', $externalId);

        $newHash = hash('sha256', trim($text));
        if ($existing && $existing['content_hash'] === $newHash) {
            // unveraendert — nichts tun
            return;
        }

        if ($existing) {
            // Reprocess (volle Re-Verarbeitung mit LLM-Extraktion)
            $overrides = [
                'title' => $title,
                'customer_id' => $customerId,
                'tags' => $tags,
            ];
            $this->ingestService->reprocess(
                (int) $existing['id'],
                $text,
                $overrides,
                ['customer_name' => null],
                $this->systemUserId,
                true
            );
            // external_id und source_ref bleiben — falls bei reprocess() nicht gesetzt, nachpatchen
            $this->db->update('knowledge_documents', [
                'external_id' => $externalId,
                'source_type' => 'asana',
                'source_ref' => $sourceRef,
            ], 'id = ?', [(int) $existing['id']]);
            return;
        }

        // Neues Dokument
        $prepared = $this->ingestService->prepare($text, ['customer_name' => null]);

        $overrides = [
            'title' => $title,
            'description' => $prepared['metadata']['description'],
            'customer_id' => $customerId,
            // Asana-Docs immer als 'Asana-Vorgang' — Auto-Extractor liefert
            // sonst meist 'Sonstige', das wuerde alle Asana-Inhalte in einen
            // Sammeltopf wuerfeln.
            'category' => 'Asana-Vorgang',
            'tags' => array_unique(array_merge($tags, $prepared['metadata']['tags'] ?? [])),
        ];
        $meta = [
            'source_type' => 'asana',
            'source_ref' => $sourceRef,
            'external_id' => $externalId,
            'created_by' => $this->systemUserId,
        ];
        $this->ingestService->commit($prepared, $overrides, $meta);
    }
}
