<?php
/**
 * Site-Monitor API.
 *
 * GET    /admin/site-monitor                            Liste mit 24h-Stats
 * GET    /admin/site-monitor/categories                 Kategorien + Counts
 * POST   /admin/site-monitor                            Save (Body: id?, url, label, customer_id?, alert_email?, report_schedule?, sub_urls?, category?)
 * DELETE /admin/site-monitor/{id}                       Löschen
 * POST   /admin/site-monitor/{id}/toggle                Pause/Aktivieren
 * GET    /admin/site-monitor/{id}/stats?days=30         Stats
 * GET    /admin/site-monitor/customer/{id}/stats?days=30 Aggregierte Stats fuer alle Monitors eines Kunden
 * GET    /admin/site-monitor/{id}/log?limit=100         Letzte Log-Einträge
 * GET    /admin/site-monitor/{id}/incidents             Incidents-Liste
 * POST   /admin/site-monitor/{id}/check-now             Sofortcheck (manuell)
 * POST   /admin/site-monitor/batch-import               Body: { urls, customer_id?, category?, report_schedule? }
 * POST   /admin/site-monitor/bulk-category              Body: { ids, category }
 * GET    /admin/site-monitor/fetch-title?url=…          Title aus URL ziehen
 * POST   /admin/site-monitor/test-report                Body: { email }
 * POST   /admin/site-monitor/cleanup-logs               Logs > 90 Tage löschen (Admin-only)
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PageMonitorService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/PageMonitorService.php';
$svc = new PageMonitorService(Database::getInstance());
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];
$id = (int) ($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($action === 'categories' && $method === 'GET') {
    Response::success(['categories' => $svc->getCategories()]);
}

if ($action === 'fetch-title' && $method === 'GET') {
    $url = trim((string) ($_GET['url'] ?? ''));
    if (!filter_var($url, FILTER_VALIDATE_URL)) Response::error('URL ungültig');
    Response::success(['title' => $svc->fetchPageTitle($url)]);
}

if ($action === 'batch-import' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $urls = (string) ($payload['urls'] ?? '');
    if (trim($urls) === '') Response::error('Keine URLs angegeben');
    $res = $svc->batchImport($urls, !empty($payload['customer_id']) ? (int) $payload['customer_id'] : null,
        (string) ($payload['category'] ?? ''), (string) ($payload['report_schedule'] ?? 'both'), $userId);
    Response::success($res, "Import: {$res['imported']} importiert, " . count($res['errors']) . " Fehler");
}

if ($action === 'bulk-category' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = $payload['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) Response::error('ids fehlt');
    $count = $svc->setCategoryBulk($ids, (string) ($payload['category'] ?? ''));
    Response::success(['updated' => $count]);
}

if ($action === 'test-report' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim((string) ($payload['email'] ?? $user['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Response::error('E-Mail ungültig');
    $ok = $svc->testReport($email);
    if (!$ok) Response::error('Keine Monitore vorhanden — Test-Report nicht möglich');
    Response::success(['ok' => true, 'to' => $email], "Test-Report an $email gesendet");
}

if ($action === 'cleanup-logs' && $method === 'POST') {
    if (!Auth::isAdmin()) Response::forbidden();
    $days = (int) ($_GET['days'] ?? 90);
    $deleted = $svc->cleanupOldLogs($days);
    Response::success(['deleted' => $deleted], "$deleted alte Log-Einträge gelöscht");
}

if ($action === 'import-preview' && $method === 'POST') {
    if (!Auth::isAdmin()) Response::forbidden();
    if (!empty($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $raw = @file_get_contents($_FILES['json_file']['tmp_name']);
    } else {
        $raw = file_get_contents('php://input');
    }
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) Response::error('JSON nicht lesbar');
    try { Response::success(['preview' => $svc->importPreview($data)]); }
    catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($action === 'import' && $method === 'POST') {
    if (!Auth::isAdmin()) Response::forbidden();
    // File-Upload bevorzugt (für große JSONs > 5 MB)
    if (!empty($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $raw = @file_get_contents($_FILES['json_file']['tmp_name']);
    } else {
        $raw = file_get_contents('php://input');
    }
    $payload = $raw ? json_decode($raw, true) : null;
    if (!is_array($payload)) Response::error('JSON nicht lesbar');
    $custMap = is_array($payload['_customer_mapping'] ?? null) ? $payload['_customer_mapping'] : [];
    $monMap = is_array($payload['_monitor_mapping'] ?? null) ? $payload['_monitor_mapping'] : [];
    $importHistory = !empty($payload['_import_history']);
    unset($payload['_customer_mapping'], $payload['_monitor_mapping'], $payload['_import_history']);
    try {
        $result = $svc->importFromJson($payload, $custMap, $userId, $monMap);
        $msg = "{$result['imported']} Monitore importiert, {$result['skipped']} skipped, {$result['duplicates']} Duplikate";
        // Wenn JSON Logs/Incidents enthält → optional mit-importieren
        if ($importHistory && (!empty($payload['logs']) || !empty($payload['incidents']))) {
            $hist = $svc->importHistory($payload, true);
            $msg .= " · History: {$hist['logs_imported']} Logs, {$hist['incidents_imported']} Incidents";
            if (!empty($hist['skipped_urls'])) {
                $msg .= " (" . count($hist['skipped_urls']) . " URLs ohne Monitor übersprungen)";
            }
            $result['history'] = $hist;
        }
        Response::success($result, $msg);
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($action === 'import-history' && $method === 'POST') {
    if (!Auth::isAdmin()) Response::forbidden();
    $raw = file_get_contents('php://input');
    $payload = $raw ? json_decode($raw, true) : null;
    if (!is_array($payload)) Response::error('JSON nicht lesbar');
    try {
        $result = $svc->importHistory($payload, true);
        $msg = "{$result['logs_imported']} Logs + {$result['incidents_imported']} Incidents importiert";
        if (!empty($result['skipped_urls'])) {
            $msg .= " (" . count($result['skipped_urls']) . " URLs ohne Monitor übersprungen)";
        }
        Response::success($result, $msg);
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($action === 'test-alert' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim((string) ($payload['email'] ?? $user['email'] ?? ''));
    $type = ($payload['type'] ?? 'down') === 'recovery' ? 'recovery' : 'down';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Response::error('E-Mail ungültig');
    $ok = $svc->testAlertMail($email, $type);
    Response::success(['ok' => $ok, 'to' => $email, 'type' => $type], "Test-Mail ($type) an $email versendet");
}

if ($action === 'mail-log' && $method === 'GET') {
    // Pfad muss innerhalb von open_basedir liegen (/var/www) — sonst failt
    // file_exists() mit Warning und der Log waere unsichtbar.
    $logFile = '/var/www/storage/logs/pm-mail.log';
    $lines = [];
    if (file_exists($logFile)) {
        $raw = @file_get_contents($logFile);
        if ($raw) {
            $lines = array_reverse(array_filter(array_map('trim', explode("\n", $raw))));
            $lines = array_slice($lines, 0, 100);
        }
    }
    Response::success(['lines' => $lines]);
}

if ($id > 0 && $action === 'toggle' && $method === 'POST') {
    Response::success(['status' => $svc->togglePause($id)], 'Umgeschaltet');
}
if ($id > 0 && $action === 'check-now' && $method === 'POST') {
    Response::success($svc->runCheck($id), 'Check ausgeführt');
}
if ($id > 0 && $action === 'stats' && $method === 'GET') {
    $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
    Response::success($svc->getStats($id, $days));
}
if ($action === 'customer-stats' && $method === 'GET') {
    $cid = (int) ($_GET['customer_id'] ?? 0);
    if ($cid <= 0) Response::error('customer_id fehlt');
    if (!Auth::canAccessCustomer($cid)) Response::forbidden();
    $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
    Response::success($svc->getStatsForCustomer($cid, $days));
}
if ($id > 0 && $action === 'log' && $method === 'GET') {
    $limit = (int) ($_GET['limit'] ?? 100);
    Response::success(['log' => $svc->getRecentLogs($id, $limit)]);
}
if ($id > 0 && $action === 'incidents' && $method === 'GET') {
    $stats = $svc->getStats($id, 30);
    Response::success(['incidents' => $stats['incidents'] ?? []]);
}
if ($id > 0 && $method === 'DELETE') {
    $svc->delete($id);
    Response::success(['id' => $id], 'Monitor gelöscht');
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $newId = $svc->save($payload, $userId);
        Response::success(['id' => $newId], 'Gespeichert');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($method === 'GET') {
    $filter = [];
    if (!empty($_GET['customer_id'])) $filter['customer_id'] = (int) $_GET['customer_id'];
    if (!empty($_GET['category'])) $filter['category'] = $_GET['category'];
    if (!empty($_GET['status'])) $filter['status'] = $_GET['status'];
    Response::success(['monitors' => $svc->getAll($filter)]);
}

Response::error('Methode nicht unterstützt', 405);
