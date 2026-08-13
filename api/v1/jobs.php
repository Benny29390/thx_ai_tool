<?php
/**
 * Jobs API - Status und Verwaltung von Generierungs-Jobs
 */

use Core\Auth;
use Core\Response;

global $db, $input;

require_once SERVICES_PATH . '/JobQueue.php';

$jobQueue = new \Services\JobQueue($db);
$method = $_SERVER['REQUEST_METHOD'];
$jobId = $_GET['id'] ?? null;

// ===== Einzelner Job =====
if ($jobId) {
    $job = $jobQueue->getJob((int) $jobId);

    if (!$job) {
        Response::notFound('Job nicht gefunden');
    }

    // Zugriffsprüfung: User muss Job-Eigentümer oder Admin sein
    if ($job['user_id'] !== Auth::id() && !Auth::isAdmin()) {
        Response::forbidden();
    }

    // GET - Job-Status abrufen
    if ($method === 'GET') {
        // Fortschritts-Felder aus DB holen (falls nicht im Job-Objekt)
        $progressData = $db->queryOne(
            "SELECT current_step, total_steps, completed_steps FROM generation_jobs WHERE id = ?",
            [$job['id']]
        );

        $response = [
            'id' => $job['id'],
            'project_id' => $job['project_id'],
            'order_id' => $job['order_id'] ?? null,
            'status' => $job['status'],
            'job_type' => $job['job_type'],
            'topic' => $job['topic'],
            'model' => $job['model'],
            'attempts' => $job['attempts'],
            'max_attempts' => $job['max_attempts'],
            'created_at' => $job['created_at'],
            'started_at' => $job['started_at'],
            'completed_at' => $job['completed_at'],
            'processing_time_ms' => $job['processing_time_ms'],
            'tokens_input' => $job['tokens_input'],
            'tokens_output' => $job['tokens_output'],
            // Reliable Generation Progress
            'current_step' => $progressData['current_step'] ?? null,
            'total_steps' => (int) ($progressData['total_steps'] ?? 0),
            'completed_steps' => (int) ($progressData['completed_steps'] ?? 0),
            'progress_percent' => ($progressData['total_steps'] ?? 0) > 0
                ? round((($progressData['completed_steps'] ?? 0) / $progressData['total_steps']) * 100)
                : 0
        ];

        // Zusätzliche Infos je nach Status
        if ($job['status'] === 'pending') {
            $response['queue_position'] = $jobQueue->getQueuePosition($job['id']);
            $response['pending_jobs'] = $jobQueue->getPendingJobsCount();
        }

        // Outline und bereits generierte Sections zurückgeben (für Live-Preview)
        // Auch bei pending/completed, damit die UI den aktuellen Stand anzeigen kann
        $sectionsConfig = $job['sections_config'] ?? [];

        // Outline (Gliederung) zurückgeben
        if (!empty($sectionsConfig['outline'])) {
            $response['outline'] = $sectionsConfig['outline'];
        }

        // Bereits generierte Sections aus DB laden (nur für Projekt-Jobs)
        if ($job['project_id'] && in_array($job['status'], ['processing', 'completed'])) {
            $projectId = $job['project_id'];
            $currentVersion = $db->queryOne(
                "SELECT id FROM article_versions WHERE project_id = ? ORDER BY version_number DESC LIMIT 1",
                [$projectId]
            );

            if ($currentVersion) {
                $generatedSections = $db->query(
                    "SELECT section_order, heading_level, heading_text, content, word_count
                     FROM article_sections
                     WHERE article_version_id = ?
                     ORDER BY section_order",
                    [$currentVersion['id']]
                );

                $response['generated_sections'] = [];
                foreach ($generatedSections as $section) {
                    $response['generated_sections'][$section['section_order']] = [
                        'heading' => $section['heading_text'],
                        'level' => $section['heading_level'],
                        'content' => $section['content'],
                        'word_count' => $section['word_count']
                    ];
                }
            }
        }

        if ($job['status'] === 'completed' && $job['result']) {
            $response['result'] = $job['result'];
            // Wörter zählen (nur für Projekt-Jobs mit sections-Format)
            if ($job['project_id'] && is_array($job['result'])) {
                $response['total_words'] = 0;
                foreach ($job['result'] as $section) {
                    if (is_array($section) && isset($section['content'])) {
                        $response['total_words'] += \Services\AIService::countWords($section['content'] ?? '');
                    }
                }
            }
        }

        if ($job['status'] === 'failed') {
            $response['error_message'] = $job['error_message'];
        }

        // Prompt hinzufügen wenn vorhanden
        if (!empty($job['prompt_used'])) {
            $prompt = json_decode($job['prompt_used'], true);
            $response['prompt'] = $prompt;
            // Modus erkennen: reliable hat outline+sections, fast hat system+user
            $response['generation_mode'] = isset($prompt['outline']) ? 'reliable' : 'fast';
        }

        Response::success($response);
    }

    // POST - Job abbrechen
    if ($method === 'POST') {
        $action = $input['action'] ?? '';

        if ($action === 'cancel') {
            if ($jobQueue->cancelJob($job['id'])) {
                Response::success(null, 'Job abgebrochen');
            } else {
                Response::error('Job kann nicht abgebrochen werden (bereits abgeschlossen oder fehlgeschlagen)');
            }
        }

        if ($action === 'retry') {
            // Nur fehlgeschlagene Jobs können erneut versucht werden
            if ($job['status'] !== 'failed') {
                Response::error('Nur fehlgeschlagene Jobs können wiederholt werden');
            }

            // Job zurücksetzen
            $db->update('generation_jobs', [
                'status' => 'pending',
                'attempts' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null
            ], 'id = ?', [$job['id']]);

            // Projekt-Status aktualisieren (nur für Projekt-Jobs)
            if ($job['project_id']) {
                $db->update('projects', [
                    'generation_status' => 'generating',
                    'current_job_id' => $job['id'],
                    'last_generation_error' => null
                ], 'id = ?', [$job['project_id']]);
            }

            Response::success(['job_id' => $job['id']], 'Job wird wiederholt');
        }

        Response::error('Unbekannte Aktion');
    }

    Response::error('Method not allowed', 405);
}

// ===== Job-Liste =====
if ($method === 'GET') {
    // Jobs des aktuellen Users
    $jobs = $jobQueue->getUserJobs(Auth::id(), 20);

    // Für Admins: Queue-Statistiken
    $stats = null;
    if (Auth::isAdmin()) {
        $stats = $jobQueue->getStats();
    }

    Response::success([
        'jobs' => $jobs,
        'stats' => $stats
    ]);
}

Response::error('Method not allowed', 405);
