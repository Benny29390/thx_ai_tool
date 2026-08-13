<?php
/**
 * Manueller Asana-Sync fuer einen Kunden + Status-Endpoint.
 *
 * POST /admin/customers/{id}/asana-sync   → Sync-Job in Queue
 * GET  /admin/customers/{id}/asana-status → Sync-Status, last_sync_at, Anzahl Asana-Docs
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if (!Auth::isAdmin()) Response::forbidden();

$customerId = (int) ($_GET['customer_id'] ?? 0);
if (!$customerId) Response::error('Customer-ID erforderlich');

$customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::notFound('Kunde nicht gefunden');

$action = $_GET['action'] ?? null;

if ($method === 'GET' || $action === 'status') {
    $settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
    $asanaCfg = $settings['asana'] ?? [];

    $docsCount = (int) ($db->queryValue(
        "SELECT COUNT(*) FROM knowledge_documents WHERE customer_id = ? AND source_type = 'asana'",
        [$customerId]
    ) ?: 0);

    $taskCount = (int) ($db->queryValue(
        "SELECT COUNT(*) FROM knowledge_documents
         WHERE customer_id = ? AND source_type = 'asana' AND external_id LIKE 'task:%'",
        [$customerId]
    ) ?: 0);

    $projectCount = (int) ($db->queryValue(
        "SELECT COUNT(*) FROM knowledge_documents
         WHERE customer_id = ? AND source_type = 'asana' AND external_id LIKE 'project:%'",
        [$customerId]
    ) ?: 0);

    // Letzten Sync-Job aus Queue
    $lastJob = $db->queryOne(
        "SELECT id, status, created_at, started_at, finished_at, error_message
         FROM generation_jobs
         WHERE customer_id = ? AND job_type = 'asana_sync'
         ORDER BY id DESC LIMIT 1",
        [$customerId]
    );

    Response::success([
        'asana' => $asanaCfg,
        'docs_count' => $docsCount,
        'task_count' => $taskCount,
        'project_count' => $projectCount,
        'last_job' => $lastJob,
    ]);
}

if ($method === 'POST') {
    require_once SERVICES_PATH . '/JobQueue.php';
    $queue = new \Services\JobQueue($db);
    try {
        $jobId = $queue->createJob([
            'customer_id' => $customerId,
            'user_id' => Auth::id(),
            'job_type' => 'asana_sync',
            'topic' => 'Manueller Asana-Sync fuer ' . ($customer['name'] ?? ''),
            'priority' => 8,
            'max_attempts' => 2,
        ]);
        Response::success(['job_id' => $jobId], 'Sync-Job in Warteschlange');
    } catch (\Exception $e) {
        Response::error('Job konnte nicht erstellt werden: ' . $e->getMessage());
    }
}

Response::error('Method not allowed', 405);
