<?php
/**
 * JobQueue Service - Verwaltung der asynchronen Generierungs-Jobs
 */

namespace Services;

use Core\Database;

class JobQueue
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Neuen Job erstellen
     */
    public function createJob(array $data): int
    {
        $jobId = $this->db->insert('generation_jobs', [
            'project_id' => $data['project_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'user_id' => $data['user_id'],
            'job_type' => $data['job_type'] ?? 'full_article',
            'topic' => $data['topic'] ?? null,
            'target_words' => $data['target_words'] ?? 800,
            'style_slug' => $data['style_slug'] ?? null,
            'model' => $data['model'] ?? null,
            'sections_config' => json_encode($data['sections_config'] ?? ['count' => 5]),
            'rule_ids' => json_encode($data['rule_ids'] ?? []),
            'knowledge_ids' => json_encode($data['knowledge_ids'] ?? []),
            'section_index' => $data['section_index'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'max_attempts' => $data['max_attempts'] ?? 3
        ]);

        // Projekt-Status nur aktualisieren wenn dieser Job an ein Projekt haengt
        if (!empty($data['project_id'])) {
            $this->db->update('projects', [
                'generation_status' => 'generating',
                'current_job_id' => $jobId,
                'last_generation_error' => null
            ], 'id = ?', [$data['project_id']]);
        }

        return $jobId;
    }

    /**
     * Job anhand ID laden
     */
    public function getJob(int $id): ?array
    {
        $job = $this->db->queryOne("SELECT * FROM generation_jobs WHERE id = ?", [$id]);

        if ($job) {
            $job['sections_config'] = json_decode($job['sections_config'] ?? '[]', true);
            $job['rule_ids'] = json_decode($job['rule_ids'] ?? '[]', true);
            $job['knowledge_ids'] = json_decode($job['knowledge_ids'] ?? '[]', true);
            $job['result'] = $job['result'] ? json_decode($job['result'], true) : null;
            $job['prompt_used'] = $job['prompt_used'] ?? null;
        }

        return $job;
    }

    /**
     * Naechsten wartenden Job holen (aeltester mit hoechster Prioritaet)
     */
    public function getNextPendingJob(): ?array
    {
        $job = $this->db->queryOne(
            "SELECT * FROM generation_jobs
             WHERE status = 'pending'
             ORDER BY priority DESC, created_at ASC
             LIMIT 1
             FOR UPDATE SKIP LOCKED"
        );

        if ($job) {
            $job['sections_config'] = json_decode($job['sections_config'] ?? '[]', true);
            $job['rule_ids'] = json_decode($job['rule_ids'] ?? '[]', true);
            $job['knowledge_ids'] = json_decode($job['knowledge_ids'] ?? '[]', true);
        }

        return $job;
    }

    /**
     * Job als "in Bearbeitung" markieren
     */
    public function startJob(int $id): void
    {
        $this->db->update('generation_jobs', [
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s'),
            'attempts' => $this->db->queryValue(
                "SELECT attempts + 1 FROM generation_jobs WHERE id = ?",
                [$id]
            )
        ], 'id = ?', [$id]);
    }

    /**
     * Job erfolgreich abschliessen
     */
    public function completeJob(int $id, array $result, int $tokensInput = 0, int $tokensOutput = 0, int $processingTimeMs = 0): void
    {
        $this->db->update('generation_jobs', [
            'status' => 'completed',
            'result' => json_encode($result),
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'processing_time_ms' => $processingTimeMs,
            'completed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);

        // Projekt-Status aktualisieren
        $job = $this->db->queryOne("SELECT project_id, order_id FROM generation_jobs WHERE id = ?", [$id]);
        if ($job && $job['project_id']) {
            $this->db->update('projects', [
                'generation_status' => 'completed',
                'current_job_id' => null
            ], 'id = ?', [$job['project_id']]);
        }

        // Order-Status aktualisieren wenn order_id gesetzt
        if ($job && $job['order_id']) {
            // Status-Update wird vom Worker je nach job_type gemacht
        }
    }

    /**
     * Job als fehlgeschlagen markieren
     */
    public function failJob(int $id, string $error): void
    {
        $this->db->update('generation_jobs', [
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);

        // Projekt-Status aktualisieren
        $job = $this->db->queryOne("SELECT project_id, order_id FROM generation_jobs WHERE id = ?", [$id]);
        if ($job && $job['project_id']) {
            $this->db->update('projects', [
                'generation_status' => 'failed',
                'current_job_id' => null,
                'last_generation_error' => $error
            ], 'id = ?', [$job['project_id']]);
        }
    }

    /**
     * Job abbrechen
     */
    public function cancelJob(int $id): bool
    {
        $job = $this->db->queryOne(
            "SELECT status, project_id, order_id FROM generation_jobs WHERE id = ?",
            [$id]
        );

        if (!$job || !in_array($job['status'], ['pending', 'processing'])) {
            return false;
        }

        $this->db->update('generation_jobs', [
            'status' => 'cancelled',
            'completed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);

        // Projekt-Status zuruecksetzen (nur wenn project_id gesetzt)
        if ($job['project_id']) {
            $this->db->update('projects', [
                'generation_status' => 'idle',
                'current_job_id' => null
            ], 'id = ?', [$job['project_id']]);
        }

        return true;
    }

    /**
     * Job fuer Retry zuruecksetzen (bei leerem Response)
     */
    public function retryJob(int $id, string $reason = ''): bool
    {
        $job = $this->db->queryOne(
            "SELECT attempts, max_attempts FROM generation_jobs WHERE id = ?",
            [$id]
        );

        if (!$job || $job['attempts'] >= $job['max_attempts']) {
            return false;
        }

        $this->db->update('generation_jobs', [
            'status' => 'pending',
            'error_message' => $reason ?: 'Leere Antwort - Retry ' . $job['attempts']
        ], 'id = ?', [$id]);

        return true;
    }

    /**
     * Alle Jobs eines Users laden
     */
    public function getUserJobs(int $userId, int $limit = 10): array
    {
        $jobs = $this->db->query(
            "SELECT j.*, p.title as project_title, o.title as order_title
             FROM generation_jobs j
             LEFT JOIN projects p ON j.project_id = p.id
             LEFT JOIN orders o ON j.order_id = o.id
             WHERE j.user_id = ?
             ORDER BY j.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );

        foreach ($jobs as &$job) {
            $job['result'] = $job['result'] ? json_decode($job['result'], true) : null;
        }

        return $jobs;
    }

    /**
     * Aktive Jobs eines Projekts laden
     */
    public function getActiveJobForProject(int $projectId): ?array
    {
        $job = $this->db->queryOne(
            "SELECT * FROM generation_jobs
             WHERE project_id = ? AND status IN ('pending', 'processing')
             ORDER BY created_at DESC
             LIMIT 1",
            [$projectId]
        );

        if ($job) {
            $job['sections_config'] = json_decode($job['sections_config'] ?? '[]', true);
            $job['result'] = $job['result'] ? json_decode($job['result'], true) : null;
        }

        return $job;
    }

    /**
     * Queue-Position eines Jobs ermitteln
     */
    public function getQueuePosition(int $id): int
    {
        $job = $this->db->queryOne(
            "SELECT priority, created_at FROM generation_jobs WHERE id = ?",
            [$id]
        );

        if (!$job) {
            return 0;
        }

        return (int) $this->db->queryValue(
            "SELECT COUNT(*) + 1 FROM generation_jobs
             WHERE status = 'pending'
             AND (priority > ? OR (priority = ? AND created_at < ?))",
            [$job['priority'], $job['priority'], $job['created_at']]
        );
    }

    /**
     * Anzahl der wartenden Jobs
     */
    public function getPendingJobsCount(): int
    {
        return (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM generation_jobs WHERE status = 'pending'"
        );
    }

    /**
     * Haengende Jobs aufraumen (Jobs die zu lange im Processing stecken)
     */
    public function cleanupStuckJobs(int $maxProcessingMinutes = 10): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$maxProcessingMinutes} minutes"));

        $stuckJobs = $this->db->query(
            "SELECT id FROM generation_jobs
             WHERE status = 'processing' AND started_at < ?",
            [$cutoff]
        );

        $cleaned = 0;
        foreach ($stuckJobs as $job) {
            // Zurueck auf pending setzen wenn noch Versuche uebrig
            $this->retryJob($job['id'], 'Job hing fest - automatischer Retry');
            $cleaned++;
        }

        return $cleaned;
    }

    /**
     * Order-Job erstellen
     */
    public function createOrderJob(array $data): int
    {
        $jobId = $this->db->insert('generation_jobs', [
            'order_id' => $data['order_id'],
            'project_id' => null,
            'customer_id' => $data['customer_id'] ?? null,
            'user_id' => $data['user_id'],
            'job_type' => $data['job_type'],
            'topic' => $data['topic'] ?? null,
            'model' => $data['model'],
            'chat_message' => $data['chat_message'] ?? null,
            'sections_config' => json_encode($data['sections_config'] ?? []),
            'rule_ids' => json_encode($data['rule_ids'] ?? []),
            'knowledge_ids' => json_encode($data['knowledge_ids'] ?? []),
            'priority' => $data['priority'] ?? 0,
            'max_attempts' => $data['max_attempts'] ?? 3
        ]);

        return $jobId;
    }

    /**
     * Aktiven Job fuer Order finden
     */
    public function getActiveJobForOrder(int $orderId): ?array
    {
        $job = $this->db->queryOne(
            "SELECT * FROM generation_jobs
             WHERE order_id = ? AND status IN ('pending', 'processing')
             ORDER BY created_at DESC LIMIT 1",
            [$orderId]
        );

        if ($job) {
            $job['result'] = $job['result'] ? json_decode($job['result'], true) : null;
        }

        return $job;
    }

    /**
     * Job-Statistiken
     */
    public function getStats(): array
    {
        return [
            'pending' => (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM generation_jobs WHERE status = 'pending'"
            ),
            'processing' => (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM generation_jobs WHERE status = 'processing'"
            ),
            'completed_today' => (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM generation_jobs
                 WHERE status = 'completed' AND DATE(completed_at) = CURDATE()"
            ),
            'failed_today' => (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM generation_jobs
                 WHERE status = 'failed' AND DATE(completed_at) = CURDATE()"
            ),
            'avg_processing_time_ms' => (int) $this->db->queryValue(
                "SELECT AVG(processing_time_ms) FROM generation_jobs
                 WHERE status = 'completed' AND processing_time_ms IS NOT NULL
                 AND completed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )
        ];
    }
}
