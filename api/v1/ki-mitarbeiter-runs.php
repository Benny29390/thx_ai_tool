<?php
/**
 * KI-Mitarbeiter — Läufe (Test-Chat) + Feedback.
 * Eingehängt aus api/v1/ki-mitarbeiter.php:
 *   POST /ki-mitarbeiter/{id}/test-runs   (mit $employeeId,$runSub)
 *   POST /ki-mitarbeiter/{id}/runs
 *   GET  /ai-runs/{id}                     (direkt, ueber $uri)
 *   POST /ai-runs/{id}/feedback
 */

use Core\Auth;
use Core\Response;
use Core\AuditLog;

global $db, $method, $input, $uri;

require_once SERVICES_PATH . '/KiRunnerService.php';
$runner = new \Services\KiRunnerService($db);
$actor = (int) Auth::id();

// --- Test-Chat / Lauf starten ---
if (isset($employeeId) && (($runSub ?? '') === '/test-runs' || ($runSub ?? '') === '/runs') && $method === 'POST') {
    $msg = trim((string) ($input['message'] ?? ''));
    if ($msg === '') Response::error('Eingabe fehlt.');
    set_time_limit(120);
    try {
        $res = $runner->testReply((int) $employeeId, $msg, $actor);
    } catch (\Throwable $ex) {
        Response::error($ex->getMessage());
    }
    Response::success($res);
}

// --- Lauf abrufen: /ai-runs/{id} ---
if (preg_match('#^/ai-runs/(\d+)$#', $uri, $m) && $method === 'GET') {
    $runId = (int) $m[1];
    $run = $db->queryOne("SELECT * FROM ai_runs WHERE id = ?", [$runId]);
    if (!$run) Response::error('Lauf nicht gefunden', 404);
    $e = $db->queryOne("SELECT customer_id FROM ai_employees WHERE id = ?", [$run['ai_employee_id']]);
    if ($e && !empty($e['customer_id']) && !Auth::canAccessCustomer((int) $e['customer_id'])) Response::forbidden();
    $run['messages'] = $db->query("SELECT role, content, created_at FROM ai_run_messages WHERE run_id = ? ORDER BY id ASC", [$runId]);
    Response::success($run);
}

// --- Feedback pro Lauf: /ai-runs/{id}/feedback ---
if (preg_match('#^/ai-runs/(\d+)/feedback$#', $uri, $m) && $method === 'POST') {
    $runId = (int) $m[1];
    $run = $db->queryOne("SELECT ai_employee_id FROM ai_runs WHERE id = ?", [$runId]);
    if (!$run) Response::error('Lauf nicht gefunden', 404);
    $fbId = $db->insert('ai_feedback', [
        'ai_employee_id' => (int) $run['ai_employee_id'],
        'run_id'         => $runId,
        'user_id'        => $actor,
        'rating'         => isset($input['rating']) ? (int) $input['rating'] : null,
        'feedback_type'  => (string) ($input['feedback_type'] ?? 'sonstiges'),
        'comment'        => (string) ($input['comment'] ?? ''),
        'status'         => 'open',
    ]);
    AuditLog::record('ai_employee', (string) $run['ai_employee_id'], 'feedback_given', ['run' => $runId, 'type' => $input['feedback_type'] ?? ''], $actor);
    Response::success(['id' => $fbId], 'Danke für das Feedback');
}

Response::error('Unbekannte Lauf-Route', 404);
