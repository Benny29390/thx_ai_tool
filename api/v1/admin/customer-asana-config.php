<?php
/**
 * Asana-Config eines Kunden setzen.
 *
 * GET  /admin/customers/{id}/asana-config        → aktuelle Config
 * POST /admin/customers/{id}/asana-config        → Body: {project_gids[], sync_enabled, sync_interval_hours}
 *
 * Aktualisiert customer.settings.asana und triggert optional einen Sync.
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if (!Auth::isAdmin()) Response::forbidden();

$customerId = (int) ($_GET['customer_id'] ?? 0);
if (!$customerId) Response::error('Customer-ID erforderlich');

$customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::notFound('Kunde nicht gefunden');

$settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
$asanaCfg = $settings['asana'] ?? [];

if ($method === 'GET') {
    Response::success([
        'project_gids' => $asanaCfg['project_gids'] ?? [],
        'sync_enabled' => !empty($asanaCfg['sync_enabled']),
        'sync_interval_hours' => (int) ($asanaCfg['sync_interval_hours'] ?? 72),
        'last_sync_at' => $asanaCfg['last_sync_at'] ?? null,
    ]);
}

if ($method === 'POST') {
    $projectGids = $input['project_gids'] ?? [];
    if (!is_array($projectGids)) Response::error('project_gids muss Array sein');
    $projectGids = array_values(array_filter(array_map('strval', $projectGids), fn($g) => preg_match('/^[a-zA-Z0-9]+$/', $g)));

    $syncEnabled = !empty($input['sync_enabled']);
    $intervalHours = max(1, min(720, (int) ($input['sync_interval_hours'] ?? 72)));
    $triggerSync = !empty($input['trigger_sync']);

    $settings['asana'] = array_merge($asanaCfg, [
        'project_gids' => $projectGids,
        'sync_enabled' => $syncEnabled,
        'sync_interval_hours' => $intervalHours,
    ]);

    $db->update('customers', ['settings' => json_encode($settings)], 'id = ?', [$customerId]);

    $jobId = null;
    if ($triggerSync && !empty($projectGids)) {
        try {
            require_once SERVICES_PATH . '/JobQueue.php';
            $queue = new \Services\JobQueue($db);
            $jobId = $queue->createJob([
                'customer_id' => $customerId,
                'user_id' => Auth::id(),
                'job_type' => 'asana_sync',
                'topic' => 'Asana-Sync nach Config-Aenderung fuer ' . ($customer['name'] ?? ''),
                'priority' => 7,
                'max_attempts' => 2,
            ]);
        } catch (\Exception $e) {
            error_log('asana-config sync trigger: ' . $e->getMessage());
        }
    }

    Response::success([
        'project_gids' => $projectGids,
        'sync_enabled' => $syncEnabled,
        'sync_interval_hours' => $intervalHours,
        'sync_job_id' => $jobId,
    ], 'Asana-Konfiguration gespeichert');
}

Response::error('Method not allowed', 405);
