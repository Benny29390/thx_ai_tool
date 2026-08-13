<?php
/**
 * KI-Coworker (Chat-Modus) API
 *
 * POST   /admin/coworker/sessions                       → Session anlegen (JSON)
 * GET    /admin/coworker/sessions                       → Liste
 * GET    /admin/coworker/sessions/{id}                  → Detail mit Messages + Snapshots + Bash-Log
 * POST   /admin/coworker/sessions/{id}/message          → User-Msg + SSE-Stream
 * POST   /admin/coworker/sessions/{id}/reply            → Antwort auf ask_user (SSE)
 * POST   /admin/coworker/sessions/{id}/stop             → idle setzen (kappt Loop)
 * POST   /admin/coworker/sessions/{id}/rollback         → komplette Session zurückrollen
 * POST   /admin/coworker/sessions/{id}/messages/{mid}/rollback → ab Message zurück
 * PATCH  /admin/coworker/sessions/{id}                  → title / cost_cap_usd ändern
 * DELETE /admin/coworker/sessions/{id}                  → closed (soft)
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\CoworkerService;

$db = Database::getInstance();
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

if (!Auth::isAdmin()) Response::forbidden();

require_once SERVICES_PATH . '/CoworkerService.php';
$service = new CoworkerService($db);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$sessionId = (int) ($_GET['session_id'] ?? 0);
$messageId = (int) ($_GET['message_id'] ?? 0);
$action = $_GET['action'] ?? '';

// ====== SSE-Helper ======
$prepareSse = function () {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level()) ob_end_flush();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no');
    @set_time_limit(0);
};
$sseOut = function (string $event, array $data) {
    echo "event: $event\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush(); @flush();
};

// ====== Stuck-Cleanup vor jedem Request mit Session-Touch ======
if ($sessionId > 0) {
    $stuck = $db->query(
        "SELECT id FROM coworker_sessions WHERE status = 'active'
         AND last_activity_at IS NOT NULL AND last_activity_at < NOW() - INTERVAL 10 MINUTE"
    ) ?: [];
    foreach ($stuck as $s) {
        $db->update('coworker_sessions', ['status' => 'idle'], 'id = ?', [(int) $s['id']]);
    }
}

// ====== /messages/{mid}/rollback ======
if ($sessionId > 0 && $messageId > 0 && $action === 'rollback_message' && $method === 'POST') {
    $session = $db->queryOne("SELECT * FROM coworker_sessions WHERE id = ?", [$sessionId]);
    if (!$session) Response::notFound('Session nicht gefunden');
    $result = $service->rollbackMessage($sessionId, $messageId);
    Response::success($result, 'Snapshots zurückgerollt');
}

// ====== /sessions/{id}/rollback ======
if ($sessionId > 0 && $action === 'rollback' && $method === 'POST') {
    $session = $db->queryOne("SELECT * FROM coworker_sessions WHERE id = ?", [$sessionId]);
    if (!$session) Response::notFound('Session nicht gefunden');
    $result = $service->rollbackSession($sessionId);
    Response::success($result, 'Session zurückgerollt');
}

// ====== /sessions/{id}/stop ======
if ($sessionId > 0 && $action === 'stop' && $method === 'POST') {
    $service->stop($sessionId);
    Response::success(['ok' => true], 'Session gestoppt');
}

// ====== /sessions/{id}/message  (SSE) ======
if ($sessionId > 0 && $action === 'message' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $text = trim((string) ($payload['text'] ?? ''));
    if ($text === '') Response::error('Nachricht erforderlich');

    $session = $db->queryOne("SELECT * FROM coworker_sessions WHERE id = ?", [$sessionId]);
    if (!$session) Response::notFound('Session nicht gefunden');
    if ($session['status'] === 'closed') Response::error('Session ist geschlossen');
    if ($session['status'] === 'active') Response::error('Session läuft bereits (eine Nachricht zur Zeit)', 409);

    $prepareSse();
    try {
        $service->sendMessage($sessionId, $text, $sseOut);
    } catch (\Throwable $e) {
        $sseOut('error', ['message' => $e->getMessage()]);
    }
    exit;
}

// ====== /sessions/{id}/reply  (SSE) ======
if ($sessionId > 0 && $action === 'reply' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $text = trim((string) ($payload['text'] ?? ''));
    if ($text === '') Response::error('Antwort erforderlich');

    $session = $db->queryOne("SELECT * FROM coworker_sessions WHERE id = ?", [$sessionId]);
    if (!$session) Response::notFound('Session nicht gefunden');
    if ($session['status'] !== 'awaiting_user') Response::error('Session wartet nicht auf Antwort');

    $prepareSse();
    try {
        $service->reply($sessionId, $text, $sseOut);
    } catch (\Throwable $e) {
        $sseOut('error', ['message' => $e->getMessage()]);
    }
    exit;
}

// ====== /sessions/{id}/bash-log ======
if ($sessionId > 0 && $action === 'bash_log' && $method === 'GET') {
    $rows = $db->query(
        "SELECT id, message_id, command, exit_code, duration_ms, timed_out, blocked_by_blacklist, executed_at,
                CHAR_LENGTH(stdout) AS stdout_len, CHAR_LENGTH(stderr) AS stderr_len
         FROM coworker_bash_log WHERE session_id = ? ORDER BY id ASC",
        [$sessionId]
    ) ?: [];
    Response::success(['log' => $rows]);
}

// ====== /sessions/{id}  (PATCH: Title/Cost-Cap) ======
if ($sessionId > 0 && $method === 'PATCH') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $update = [];
    if (isset($payload['title'])) $update['title'] = mb_substr(trim((string) $payload['title']), 0, 255);
    if (array_key_exists('cost_cap_usd', $payload)) {
        $update['cost_cap_usd'] = $payload['cost_cap_usd'] === null ? null : round((float) $payload['cost_cap_usd'], 4);
    }
    if (empty($update)) Response::error('Keine Änderungen');
    $db->update('coworker_sessions', $update, 'id = ?', [$sessionId]);
    Response::success(['ok' => true]);
}

// ====== /sessions/{id}  (DELETE: soft-close) ======
if ($sessionId > 0 && $method === 'DELETE') {
    $service->closeSession($sessionId);
    Response::success(['ok' => true], 'Session geschlossen');
}

// ====== /sessions/{id}  (GET: Detail mit allen Messages) ======
if ($sessionId > 0 && $method === 'GET') {
    $session = $db->queryOne(
        "SELECT s.*, u.name AS created_by_name, u.abbreviation AS created_by_abbr
         FROM coworker_sessions s LEFT JOIN users u ON u.id = s.created_by WHERE s.id = ?",
        [$sessionId]
    );
    if (!$session) Response::notFound('Session nicht gefunden');

    $rawMessages = $db->query(
        "SELECT id, role, content, created_at FROM coworker_messages WHERE session_id = ? ORDER BY id ASC",
        [$sessionId]
    ) ?: [];
    $messages = [];
    foreach ($rawMessages as $rm) {
        $content = $rm['content'];
        $decoded = json_decode((string) $content, true);
        $createdAt = $rm['created_at'];
        $msgId = (int) $rm['id'];
        if ($rm['role'] === 'user' && !is_array($decoded)) {
            $messages[] = ['kind' => 'user', 'message_id' => $msgId, 'text' => $content, 'created_at' => $createdAt];
        } elseif ($rm['role'] === 'assistant' && is_array($decoded)) {
            foreach ($decoded as $block) {
                $type = $block['type'] ?? '';
                if ($type === 'text' && !empty(trim($block['text'] ?? ''))) {
                    $messages[] = ['kind' => 'assistant', 'message_id' => $msgId, 'text' => $block['text'], 'created_at' => $createdAt];
                } elseif ($type === 'tool_use') {
                    $messages[] = [
                        'kind' => 'tool_call',
                        'message_id' => $msgId,
                        'tool' => $block['name'] ?? '',
                        'args' => $block['input'] ?? [],
                        'tool_use_id' => $block['id'] ?? '',
                        'created_at' => $createdAt,
                    ];
                }
            }
        } elseif ($rm['role'] === 'tool_result' && is_array($decoded)) {
            foreach ($decoded as $block) {
                if (($block['type'] ?? '') === 'tool_result') {
                    $messages[] = [
                        'kind' => 'tool_result',
                        'message_id' => $msgId,
                        'tool_use_id' => $block['tool_use_id'] ?? '',
                        'text' => (string) ($block['content'] ?? ''),
                        'is_error' => !empty($block['is_error']),
                        'created_at' => $createdAt,
                    ];
                }
            }
        }
    }
    $session['messages'] = $messages;

    $snapshots = $db->query(
        "SELECT id, message_id, file_path, operation, source, file_existed, applied_at, rolled_back_at,
                CHAR_LENGTH(original_content) AS orig_len, CHAR_LENGTH(new_content) AS new_len
         FROM coworker_snapshots WHERE session_id = ? ORDER BY id ASC",
        [$sessionId]
    ) ?: [];
    foreach ($snapshots as &$s) {
        $s['relative_path'] = str_replace('/var/www/', '', $s['file_path']);
    }
    unset($s);
    $session['snapshots'] = $snapshots;

    Response::success($session);
}

// ====== /sessions  (GET: Liste) ======
if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    $mine = !empty($_GET['mine']);
    $sql = "SELECT s.id, s.title, s.status, s.tokens_input, s.tokens_output, s.cost_usd, s.cost_cap_usd,
                   s.created_at, s.last_activity_at, s.created_by,
                   u.name AS created_by_name, u.abbreviation AS created_by_abbr,
                   (SELECT COUNT(*) FROM coworker_messages m WHERE m.session_id = s.id) AS messages_count,
                   (SELECT COUNT(*) FROM coworker_snapshots sn WHERE sn.session_id = s.id AND sn.rolled_back_at IS NULL) AS files_changed
            FROM coworker_sessions s
            LEFT JOIN users u ON u.id = s.created_by
            WHERE s.status != 'closed'";
    $params = [];
    if ($status) { $sql .= " AND s.status = ?"; $params[] = $status; }
    if ($mine)   { $sql .= " AND s.created_by = ?"; $params[] = $userId; }
    $sql .= " ORDER BY s.last_activity_at DESC, s.id DESC LIMIT 100";
    $sessions = $db->query($sql, $params) ?: [];
    Response::success(['sessions' => $sessions]);
}

// ====== /sessions  (POST: Anlegen) ======
if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $costCap = isset($payload['cost_cap_usd']) ? (float) $payload['cost_cap_usd'] : null;
    $newId = $service->createSession($userId, $costCap);
    Response::success(['id' => $newId, 'status' => 'idle'], 'Session erstellt');
}

Response::error('Methode nicht unterstützt', 405);
