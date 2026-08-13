<?php
/**
 * Customer Wizard API — Step-by-Step Kundenanlage mit KI-Hilfe.
 *
 * State wird in storage/wizards/{userId}_{wizardId}.json gespeichert (24h TTL).
 *
 * Endpoints:
 *  POST /admin/customer-wizard/start                        → {wizard_id, state}
 *  GET  /admin/customer-wizard/{wizard_id}                  → state
 *  POST /admin/customer-wizard/{wizard_id}/step/{stepName}  → updated state
 *  POST /admin/customer-wizard/{wizard_id}/commit           → {customer_id}
 *  DELETE /admin/customer-wizard/{wizard_id}                → cleanup
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$userId = Auth::id();
if (!Auth::isAdmin()) Response::forbidden();

$wizardDir = ROOT_PATH . '/storage/wizards';
if (!is_dir($wizardDir)) {
    @mkdir($wizardDir, 0750, true);
}

$action = $_GET['action'] ?? null;     // 'start' | 'step' | 'commit'
$wizardId = $_GET['wizard_id'] ?? null;
$stepName = $_GET['step'] ?? null;

function wizardPath(string $dir, int $userId, string $wizardId): string {
    return $dir . '/' . $userId . '_' . preg_replace('/[^a-f0-9]/', '', $wizardId) . '.json';
}

function wizardLoad(string $dir, int $userId, string $wizardId): ?array {
    $path = wizardPath($dir, $userId, $wizardId);
    if (!is_file($path)) return null;
    if (filemtime($path) < time() - 86400) {
        @unlink($path);
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function wizardSave(string $dir, int $userId, string $wizardId, array $data): void {
    file_put_contents(wizardPath($dir, $userId, $wizardId), json_encode($data, JSON_UNESCAPED_UNICODE));
}

function wizardDelete(string $dir, int $userId, string $wizardId): void {
    @unlink(wizardPath($dir, $userId, $wizardId));
}

// ===== START =====
if ($action === 'start' && $method === 'POST') {
    $newId = bin2hex(random_bytes(16));
    $state = [
        'wizard_id' => $newId,
        'user_id' => $userId,
        'created_at' => time(),
        'current_step' => 'basics',
        'completed_steps' => [],
        'data' => [
            'name' => '',
            'slug' => '',
            'abbreviation' => '',
            'industry' => '',
            'website' => '',
            'description' => '',
            'target_audience' => '',
            'products_services' => '',
            'unique_selling_points' => '',
            'tone_of_voice' => '',
            'brand_values' => '',
            'asana_project_gids' => [],
            'asana_sync_enabled' => true,
            'asana_sync_interval_hours' => 72,
        ],
    ];
    wizardSave($wizardDir, $userId, $newId, $state);
    Response::success($state, 'Wizard gestartet');
}

// ===== Alle anderen Aktionen brauchen wizard_id =====
if (!$wizardId) Response::error('wizard_id erforderlich');

$state = wizardLoad($wizardDir, $userId, $wizardId);
if (!$state) Response::notFound('Wizard nicht gefunden oder abgelaufen');

// ===== GET State =====
if ($method === 'GET') {
    Response::success($state);
}

// ===== DELETE =====
if ($method === 'DELETE') {
    wizardDelete($wizardDir, $userId, $wizardId);
    Response::success(null, 'Wizard verworfen');
}

// ===== STEP =====
if ($action === 'step' && $method === 'POST') {
    if (!$stepName) Response::error('step erforderlich');

    $validSteps = ['basics', 'profile_source', 'profile_review', 'tone_values', 'asana_projects', 'summary',
                   'ki_marken', 'asana_done']; // neue 3-Step-Variante
    if (!in_array($stepName, $validSteps, true)) Response::error('Unbekannter Step');

    // Daten mergen
    $newData = $input['data'] ?? [];
    if (!is_array($newData)) Response::error('data muss ein Object sein');

    foreach ($newData as $key => $val) {
        if (array_key_exists($key, $state['data'])) {
            $state['data'][$key] = $val;
        }
    }

    if (!in_array($stepName, $state['completed_steps'], true)) {
        $state['completed_steps'][] = $stepName;
    }
    $state['current_step'] = $stepName;
    $state['updated_at'] = time();

    wizardSave($wizardDir, $userId, $wizardId, $state);
    Response::success($state, 'Schritt gespeichert');
}

// ===== COMMIT =====
if ($action === 'commit' && $method === 'POST') {
    $d = $state['data'];

    if (empty($d['name']) || empty($d['slug'])) {
        Response::error('Name und Slug sind erforderlich');
    }
    if (!preg_match('/^[a-z0-9-]+$/', $d['slug'])) {
        Response::error('Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten');
    }

    // Slug eindeutig?
    $existing = $db->queryOne("SELECT id FROM customers WHERE slug = ?", [$d['slug']]);
    if ($existing) Response::error('Slug bereits vergeben');

    // settings JSON aufbauen
    $settings = [
        'asana' => [
            'project_gids' => array_values(array_filter($d['asana_project_gids'] ?? [])),
            'sync_enabled' => !empty($d['asana_sync_enabled']),
            'sync_interval_hours' => (int) ($d['asana_sync_interval_hours'] ?? 72),
            'last_sync_at' => null,
        ],
    ];

    $customerData = [
        'name' => $d['name'],
        'slug' => $d['slug'],
        'abbreviation' => mb_strtoupper(trim($d['abbreviation'] ?? '')) ?: null,
        'industry' => $d['industry'] ?: null,
        'website' => $d['website'] ?: null,
        'description' => $d['description'] ?: null,
        'target_audience' => $d['target_audience'] ?: null,
        'products_services' => $d['products_services'] ?: null,
        'unique_selling_points' => $d['unique_selling_points'] ?: null,
        'tone_of_voice' => $d['tone_of_voice'] ?: null,
        'brand_values' => $d['brand_values'] ?: null,
        'settings' => json_encode($settings),
        'is_active' => 1,
    ];

    $customerId = $db->insert('customers', $customerData);

    // Falls Asana konfiguriert + Projekte ausgewaehlt: Sync-Job in Queue
    $asanaJobQueued = false;
    if (!empty($settings['asana']['project_gids']) && $settings['asana']['sync_enabled']) {
        try {
            require_once SERVICES_PATH . '/JobQueue.php';
            $queue = new \Services\JobQueue($db);
            $queue->createJob([
                'customer_id' => $customerId,
                'user_id' => $userId,
                'job_type' => 'asana_sync',
                'topic' => 'Asana-Initial-Sync fuer Kunde ' . $d['name'],
                'priority' => 5,
                'max_attempts' => 2,
            ]);
            $asanaJobQueued = true;
        } catch (\Exception $e) {
            error_log('Asana-Job queueing failed: ' . $e->getMessage());
        }
    }

    wizardDelete($wizardDir, $userId, $wizardId);

    Response::success([
        'customer_id' => $customerId,
        'asana_sync_queued' => $asanaJobQueued,
    ], 'Kunde angelegt');
}

Response::error('Unbekannte Aktion');
