<?php
/**
 * Customer Website-Crawl — Config + Sync-Trigger
 *
 * GET  /admin/customers/{id}/website-crawl       → Config + Status
 * PUT  /admin/customers/{id}/website-crawl       → Config setzen
 * POST /admin/customers/{id}/website-crawl/sync  → Manueller Sync (queued)
 */

use Core\Auth;
use Core\Database;
use Core\Response;

$db = Database::getInstance();
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$customerId = (int) ($_GET['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('Customer-ID erforderlich');
$customer = $db->queryOne("SELECT id, name, website, settings FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::notFound('Kunde nicht gefunden');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
$cfg = $settings['website_crawl'] ?? [];

// Fallback: wenn keine Start-URL konfiguriert ist, aber im Profil eine Website steht → übernehmen
$profileWebsite = trim((string) ($customer['website'] ?? ''));
$startUrlInherited = false;
if (empty($cfg['start_url']) && $profileWebsite !== '') {
    $cfg['start_url'] = $profileWebsite;
    $startUrlInherited = true;
}

// Alle Domains die beim Sync gecrawlt werden: primäre + zusätzliche aus settings.domains
$additionalDomains = $settings['domains'] ?? [];
$allDomains = [];
if (!empty($cfg['start_url'])) $allDomains[] = ['label' => 'Hauptseite', 'url' => $cfg['start_url']];
foreach ($additionalDomains as $d) {
    $u = trim((string) ($d['url'] ?? ''));
    if ($u === '') continue;
    $exists = false;
    foreach ($allDomains as $existing) {
        if ($existing['url'] === $u) { $exists = true; break; }
    }
    if (!$exists) $allDomains[] = ['label' => trim((string) ($d['label'] ?? '')), 'url' => $u];
}

// Anzahl Website-Wissens-Einträge zählen + letzter Job
$docsCount = (int) ($db->queryValue(
    "SELECT COUNT(*) FROM knowledge_documents WHERE customer_id = ? AND source_type = 'website'",
    [$customerId]
) ?: 0);
$lastJob = $db->queryOne(
    "SELECT id, status, created_at, started_at, completed_at, error_message, result
     FROM generation_jobs
     WHERE customer_id = ? AND job_type = 'website_sync'
     ORDER BY id DESC LIMIT 1",
    [$customerId]
);

// ===== Sync triggern =====
if ($action === 'sync' && $method === 'POST') {
    $startUrl = trim((string) ($cfg['start_url'] ?? ''));
    if ($startUrl === '') Response::error('Start-URL ist nicht konfiguriert (auch nicht im Profil hinterlegt)');

    // Falls die Start-URL aus dem Profil übernommen wurde: persistieren
    if ($startUrlInherited) {
        $cfg['start_url'] = $startUrl;
        $settings['website_crawl'] = $cfg;
        $db->update('customers', ['settings' => json_encode($settings)], 'id = ?', [$customerId]);
    }

    // Schon ein Job am Laufen?
    $existing = $db->queryOne(
        "SELECT id FROM generation_jobs
         WHERE customer_id = ? AND job_type = 'website_sync' AND status IN ('pending','processing')
         LIMIT 1",
        [$customerId]
    );
    if ($existing) Response::error('Es läuft bereits ein Sync-Job (#' . $existing['id'] . ')');

    require_once SERVICES_PATH . '/JobQueue.php';
    $queue = new \Services\JobQueue($db);
    try {
        $jobId = $queue->createJob([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'job_type' => 'website_sync',
            'topic' => 'Website-Sync (manuell) für ' . $customer['name'],
            'priority' => 5,
            'max_attempts' => 2,
        ]);
        Response::success(['job_id' => $jobId], 'Sync-Job eingereiht (#' . $jobId . ')');
    } catch (\Exception $e) {
        Response::error('Job konnte nicht erstellt werden: ' . $e->getMessage());
    }
}

// ===== Sitemap: alle gecrawlten Seiten des Kunden =====
if ($action === 'sitemap' && $method === 'GET') {
    $rows = $db->query(
        "SELECT id, title, description, source_ref, external_id, created_at, updated_at
         FROM knowledge_documents
         WHERE customer_id = ? AND source_type = 'website'
         ORDER BY source_ref ASC",
        [$customerId]
    ) ?: [];

    // Gruppieren nach Host für übersichtliche Anzeige
    $byHost = [];
    foreach ($rows as $r) {
        $host = parse_url($r['source_ref'] ?? '', PHP_URL_HOST) ?: 'unbekannt';
        $byHost[$host] = $byHost[$host] ?? [];
        $byHost[$host][] = $r;
    }
    Response::success([
        'total' => count($rows),
        'hosts' => $byHost,
        'pages' => $rows,
    ]);
}

// ===== GET =====
if ($method === 'GET') {
    Response::success([
        'start_url' => $cfg['start_url'] ?? '',
        'start_url_inherited' => $startUrlInherited,
        'profile_website' => $profileWebsite,
        'all_domains' => $allDomains,
        'max_pages' => (int) ($cfg['max_pages'] ?? 120),
        'max_depth' => (int) ($cfg['max_depth'] ?? 4),
        'sync_enabled' => !empty($cfg['sync_enabled']),
        'sync_interval_days' => (int) ($cfg['sync_interval_days'] ?? 60),
        'last_sync_at' => $cfg['last_sync_at'] ?? null,
        'last_stats' => $cfg['last_stats'] ?? null,
        'docs_count' => $docsCount,
        'last_job' => $lastJob,
    ]);
}

// ===== PUT =====
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $startUrl = trim((string) ($input['start_url'] ?? ''));
    if ($startUrl !== '' && !filter_var($startUrl, FILTER_VALIDATE_URL)) {
        Response::error('Ungültige URL');
    }
    $maxPages = max(1, min(200, (int) ($input['max_pages'] ?? 120)));
    $maxDepth = max(1, min(5, (int) ($input['max_depth'] ?? 4)));
    $syncEnabled = !empty($input['sync_enabled']);
    $intervalDays = max(1, min(365, (int) ($input['sync_interval_days'] ?? 60)));
    $triggerSync = !empty($input['trigger_sync']);

    $newCfg = array_merge($cfg, [
        'start_url' => $startUrl,
        'max_pages' => $maxPages,
        'max_depth' => $maxDepth,
        'sync_enabled' => $syncEnabled,
        'sync_interval_days' => $intervalDays,
    ]);
    $settings['website_crawl'] = $newCfg;
    $db->update('customers', ['settings' => json_encode($settings)], 'id = ?', [$customerId]);

    $resp = ['saved' => true];

    // Optional: gleich einen Sync starten
    if ($triggerSync && $startUrl !== '') {
        $existing = $db->queryOne(
            "SELECT id FROM generation_jobs
             WHERE customer_id = ? AND job_type = 'website_sync' AND status IN ('pending','processing')
             LIMIT 1",
            [$customerId]
        );
        if (!$existing) {
            require_once SERVICES_PATH . '/JobQueue.php';
            $queue = new \Services\JobQueue($db);
            try {
                $resp['sync_job_id'] = $queue->createJob([
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'job_type' => 'website_sync',
                    'topic' => 'Website-Sync (Setup) für ' . $customer['name'],
                    'priority' => 5,
                    'max_attempts' => 2,
                ]);
            } catch (\Exception $e) {
                $resp['sync_error'] = $e->getMessage();
            }
        } else {
            $resp['sync_job_id'] = (int) $existing['id'];
            $resp['sync_note'] = 'bereits ein Job in Queue';
        }
    }
    Response::success($resp, 'Konfiguration gespeichert');
}

Response::error('Methode nicht unterstützt');
