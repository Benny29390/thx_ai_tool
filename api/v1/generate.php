<?php
/**
 * Article Generation API - Asynchrone Artikelgenerierung via Job-Queue
 *
 * Erstellt einen Job in der Queue und gibt sofort die Job-ID zurück.
 * Die eigentliche Generierung erfolgt durch den Worker (cli/worker.php).
 */

use Core\Auth;
use Core\Response;

global $db, $input;

require_once SERVICES_PATH . '/JobQueue.php';

// Eingaben validieren
$topic = trim($input['topic'] ?? '');
$targetWords = (int) ($input['target_words'] ?? 1000);
$sections = (int) ($input['sections'] ?? 5);
$styleSlug = $input['style'] ?? 'informativ';
$customerId = $input['customer_id'] ?? Auth::customerId();
$projectId = $input['project_id'] ?? null;

if (empty($topic)) {
    Response::error('Thema erforderlich');
}

// Für Admins ohne customer_id: ersten verfügbaren Kunden nutzen
if (!$customerId && Auth::isAdmin()) {
    $defaultCustomer = $db->queryOne("SELECT id FROM customers WHERE is_active = 1 ORDER BY id LIMIT 1");
    $customerId = $defaultCustomer['id'] ?? null;
}

if (!$customerId) {
    Response::error('Kunde erforderlich');
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// Model und Provider
$model = $input['model'] ?? $settings['default_model'] ?? 'gpt-4';
$provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
$apiKey = $provider === 'anthropic' ? $settings['anthropic_api_key'] : $settings['openai_api_key'];

if (empty($apiKey)) {
    Response::error('API-Key für ' . $provider . ' nicht konfiguriert');
}

// Regeln und Wissen IDs
$ruleIds = $input['rule_ids'] ?? [];
$knowledgeIds = $input['knowledge_ids'] ?? [];

// Projekt erstellen oder existierendes verwenden
if (!$projectId) {
    // Neues Projekt anlegen
    $projectId = $db->insert('projects', [
        'customer_id' => $customerId,
        'created_by' => Auth::id(),
        'title' => $topic,
        'target_word_count' => $targetWords,
        'status' => 'draft',
        'generation_status' => 'generating'
    ]);

    // Erste Version anlegen
    $versionId = $db->insert('article_versions', [
        'project_id' => $projectId,
        'version_number' => 1,
        'content' => '',
        'word_count' => 0
    ]);

    // Regeln dem Projekt zuweisen
    if (!empty($ruleIds)) {
        foreach ($ruleIds as $ruleId) {
            $db->insert('project_rules', [
                'project_id' => $projectId,
                'rule_id' => (int) $ruleId
            ]);
        }
    }

    // Wissen dem Projekt zuweisen
    if (!empty($knowledgeIds)) {
        foreach ($knowledgeIds as $knowledgeId) {
            $db->insert('project_knowledge', [
                'project_id' => $projectId,
                'knowledge_entry_id' => (int) $knowledgeId
            ]);
        }
    }
} else {
    // Existierendes Projekt prüfen
    $project = $db->queryOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if (!$project) {
        Response::error('Projekt nicht gefunden');
    }

    // Zugriff prüfen
    if (!Auth::isAdmin() && $project['customer_id'] !== Auth::customerId()) {
        Response::forbidden();
    }

    // Prüfen ob bereits ein Job läuft
    if ($project['generation_status'] === 'generating') {
        $activeJob = $db->queryOne(
            "SELECT id FROM generation_jobs WHERE project_id = ? AND status IN ('pending', 'processing')",
            [$projectId]
        );
        if ($activeJob) {
            Response::error('Generierung läuft bereits', 409, ['job_id' => $activeJob['id']]);
        }
    }
}

// Job in Queue erstellen
$jobQueue = new \Services\JobQueue($db);

try {
    $jobId = $jobQueue->createJob([
        'project_id' => $projectId,
        'customer_id' => $customerId,
        'user_id' => Auth::id(),
        'job_type' => 'full_article',
        'topic' => $topic,
        'target_words' => $targetWords,
        'style_slug' => $styleSlug,
        'model' => $model,
        'sections_config' => [
            'count' => $sections,
            'words_per_section' => (int) ($targetWords / $sections)
        ],
        'rule_ids' => $ruleIds,
        'knowledge_ids' => $knowledgeIds,
        'priority' => 0
    ]);

    Response::success([
        'job_id' => $jobId,
        'project_id' => $projectId,
        'status' => 'pending',
        'message' => 'Job in Warteschlange',
        'queue_position' => $jobQueue->getQueuePosition($jobId),
        'pending_jobs' => $jobQueue->getPendingJobsCount()
    ]);

} catch (\Exception $e) {
    // Bei Fehler Projekt-Status zurücksetzen
    $db->update('projects', [
        'generation_status' => 'failed',
        'last_generation_error' => $e->getMessage()
    ], 'id = ?', [$projectId]);

    Response::error('Fehler beim Erstellen des Jobs: ' . $e->getMessage());
}
