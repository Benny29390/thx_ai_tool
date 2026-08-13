<?php
/**
 * Admin-Code-Tasks API
 *
 * POST   /admin/tasks                → Task anlegen + SSE-Stream der Ausführung
 * GET    /admin/tasks                → Liste
 * GET    /admin/tasks/{id}           → Detail mit Snapshots
 * POST   /admin/tasks/{id}/rollback  → Snapshots rückwärts anwenden
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\AdminTaskService;

$db = Database::getInstance();
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

if (!Auth::isAdmin()) Response::forbidden();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$taskId = (int) ($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

require_once SERVICES_PATH . '/AdminTaskService.php';
$service = new AdminTaskService($db);

// ===== Reply auf eine Rückfrage (awaiting_user) =====
if ($taskId > 0 && $action === 'reply' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $reply = trim((string) ($payload['reply'] ?? ''));
    if ($reply === '') Response::error('Antwort-Text erforderlich');

    $task = $db->queryOne("SELECT * FROM admin_tasks WHERE id = ?", [$taskId]);
    if (!$task) Response::notFound('Task nicht gefunden');
    if ($task['status'] !== 'awaiting_user') Response::error('Task wartet nicht auf eine Antwort');

    // SSE-Header
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    @ini_set('output_buffering', '0');
    while (ob_get_level()) ob_end_flush();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no');
    @set_time_limit(600);

    $sseOut = function (string $event, array $data) {
        echo "event: $event\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush(); @flush();
    };

    $sseOut('resume', ['task_id' => $taskId]);
    try {
        $service->reply($taskId, $reply, $sseOut);
    } catch (\Throwable $e) {
        $sseOut('error', ['message' => $e->getMessage(), 'rolled_back' => false]);
    }
    exit;
}

// ===== Module-Liste =====
if ($taskId === 0 && $action === 'modules' && $method === 'GET') {
    $modules = [];
    foreach (\Services\AdminTaskService::MODULES as $key => $m) {
        $modules[] = [
            'key' => $key,
            'label' => $m['label'],
            'description' => $m['description'],
            'paths_count' => count($m['paths']),
        ];
    }
    Response::success(['modules' => $modules]);
}

// ===== Rollback =====
if ($taskId > 0 && $action === 'rollback' && $method === 'POST') {
    $task = $db->queryOne("SELECT * FROM admin_tasks WHERE id = ?", [$taskId]);
    if (!$task) Response::notFound('Task nicht gefunden');
    if ($task['status'] === 'rolled_back') Response::error('Task wurde bereits zurückgerollt');
    // running-Tasks dürfen rückgerollt werden (Notfall — z.B. Task hängt)
    $result = $service->rollback($taskId, 'Manueller Rollback durch ' . ($user['name'] ?? 'Admin'));
    Response::success($result, 'Task zurückgerollt');
}

// ===== Single Task mit Snapshots + Messages =====
if ($taskId > 0 && $method === 'GET') {
    $task = $db->queryOne(
        "SELECT t.*, u.name AS created_by_name FROM admin_tasks t LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ?",
        [$taskId]
    );
    if (!$task) Response::notFound('Task nicht gefunden');
    $snapshots = $db->query(
        "SELECT id, file_path, operation, original_content, new_content, file_existed, applied_at, rolled_back_at
         FROM admin_task_snapshots WHERE task_id = ? ORDER BY id ASC",
        [$taskId]
    ) ?: [];
    foreach ($snapshots as &$s) {
        $s['relative_path'] = str_replace('/var/www/', '', $s['file_path']);
    }
    unset($s);
    $task['snapshots'] = $snapshots;

    // Messages für Chat-View (in vereinfachter Form: user-text, assistant-text, tool_call, tool_result)
    $rawMessages = $db->query(
        "SELECT id, role, content, created_at FROM admin_task_messages WHERE task_id = ? ORDER BY id ASC",
        [$taskId]
    ) ?: [];
    $messages = [];
    foreach ($rawMessages as $rm) {
        $content = $rm['content'];
        $decoded = json_decode((string) $content, true);
        if ($rm['role'] === 'user' && !is_array($decoded)) {
            // Klassische User-Nachricht
            $messages[] = ['kind' => 'user', 'text' => $content, 'created_at' => $rm['created_at']];
        } elseif ($rm['role'] === 'assistant' && is_array($decoded)) {
            // Assistant: kann Text-Blöcke + Tool-Use-Blöcke enthalten
            foreach ($decoded as $block) {
                $type = $block['type'] ?? '';
                if ($type === 'text' && !empty(trim($block['text'] ?? ''))) {
                    $messages[] = ['kind' => 'assistant', 'text' => $block['text'], 'created_at' => $rm['created_at']];
                } elseif ($type === 'tool_use') {
                    $messages[] = [
                        'kind' => 'tool_call', 'tool' => $block['name'] ?? '',
                        'args' => $block['input'] ?? [], 'tool_use_id' => $block['id'] ?? '',
                        'created_at' => $rm['created_at'],
                    ];
                }
            }
        } elseif ($rm['role'] === 'tool_result' && is_array($decoded)) {
            foreach ($decoded as $block) {
                if (($block['type'] ?? '') === 'tool_result') {
                    $messages[] = [
                        'kind' => 'tool_result', 'tool_use_id' => $block['tool_use_id'] ?? '',
                        'text' => (string) ($block['content'] ?? ''),
                        'is_error' => !empty($block['is_error']),
                        'created_at' => $rm['created_at'],
                    ];
                }
            }
        }
    }
    $task['messages'] = $messages;
    $task['cost_usd'] = adminTaskCost((int) $task['tokens_input'], (int) $task['tokens_output']);
    Response::success($task);
}

// ===== Liste =====
if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    $sql = "SELECT t.*, u.name AS created_by_name, u.abbreviation AS created_by_abbr,
                (SELECT COUNT(*) FROM admin_task_snapshots WHERE task_id = t.id AND rolled_back_at IS NULL) AS files_changed,
                (SELECT COUNT(*) FROM admin_task_snapshots WHERE task_id = t.id) AS total_snapshots
            FROM admin_tasks t
            LEFT JOIN users u ON u.id = t.created_by
            WHERE 1=1";
    $params = [];
    if ($status) { $sql .= " AND t.status = ?"; $params[] = $status; }
    $sql .= " ORDER BY t.id DESC LIMIT 100";
    $tasks = $db->query($sql, $params) ?: [];
    foreach ($tasks as &$t) {
        $t['cost_usd'] = adminTaskCost((int) $t['tokens_input'], (int) $t['tokens_output']);
    }
    unset($t);
    Response::success(['tasks' => $tasks]);
}

/**
 * Kostenberechnung für Anthropic Claude Sonnet 4.6:
 * Input $3 / Mio Tokens, Output $15 / Mio Tokens.
 */
function adminTaskCost(int $tokensIn, int $tokensOut): float {
    return round(($tokensIn / 1_000_000) * 3 + ($tokensOut / 1_000_000) * 15, 4);
}

// ===== Run: Task starten / fortsetzen (SSE) — funktioniert für pending UND awaiting_user (Resume) =====
if ($taskId > 0 && $action === 'run' && $method === 'POST') {
    $task = $db->queryOne("SELECT * FROM admin_tasks WHERE id = ?", [$taskId]);
    if (!$task) Response::notFound('Task nicht gefunden');
    if (!in_array($task['status'], ['pending', 'awaiting_user'], true)) {
        Response::error('Task kann nicht gestartet werden (Status: ' . $task['status'] . ')');
    }

    // Stuck-Cleanup: hat schon eine running-Task >5min keine Aktivität → rollback
    $stuck = $db->query(
        "SELECT id FROM admin_tasks WHERE status = 'running'
         AND started_at IS NOT NULL AND started_at < NOW() - INTERVAL 5 MINUTE"
    ) ?: [];
    foreach ($stuck as $s) {
        try { $service->rollback((int) $s['id'], 'Stream abgebrochen — Stuck-Cleanup'); }
        catch (\Throwable $e) { $db->update('admin_tasks', ['status'=>'failed','error_message'=>'Stuck-Cleanup','completed_at'=>date('Y-m-d H:i:s')], 'id = ?', [(int) $s['id']]); }
    }

    // Concurrency: andere running-Task → nicht starten, Task bleibt pending
    $running = $db->queryOne("SELECT id FROM admin_tasks WHERE status = 'running' AND id != ? LIMIT 1", [$taskId]);
    if ($running) {
        Response::error('Andere Task läuft (#' . $running['id'] . ') — diese bleibt in Queue.', 409);
    }

    // SSE-Header
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level()) ob_end_flush();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no');
    @set_time_limit(600);

    $sseOut = function (string $event, array $data) {
        echo "event: $event\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush(); @flush();
    };

    $sseOut('task_running', ['task_id' => $taskId]);

    try {
        $service->execute($taskId, $sseOut);
    } catch (\Throwable $e) {
        $sseOut('error', ['message' => $e->getMessage(), 'rolled_back' => false]);
        $db->update('admin_tasks', [
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$taskId]);
    }
    exit;
}

// ===== Task anlegen (JSON-Response, kein SSE) =====
if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $prompt = trim((string) ($payload['prompt'] ?? ''));
    $scope = $payload['scope'] ?? 'frontend';
    $module = $payload['module'] ?? null;
    if ($prompt === '') Response::error('Prompt erforderlich');
    if (!in_array($scope, ['frontend', 'frontend_backend', 'all'], true)) Response::error('Ungültiger Scope');
    if ($module !== null && !isset(\Services\AdminTaskService::MODULES[$module])) $module = null;

    $newId = $db->insert('admin_tasks', [
        'created_by' => $userId,
        'prompt' => $prompt,
        'scope' => $scope,
        'module' => $module,
        'status' => 'pending',
    ]);

    // Position in der Queue (alle pending vor mir + ggf. running)
    $beforeMe = (int) ($db->queryValue("SELECT COUNT(*) FROM admin_tasks WHERE status IN ('pending','running') AND id < ?", [$newId]) ?? 0);
    Response::success([
        'id' => $newId,
        'status' => 'pending',
        'queue_position' => $beforeMe,
    ], $beforeMe === 0 ? 'Task wird gestartet' : 'Task #' . $newId . ' in Queue (' . $beforeMe . ' vor)');
}

Response::error('Methode nicht unterstützt', 405);
