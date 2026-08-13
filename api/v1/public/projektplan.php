<?php
/**
 * Öffentliche Plan-Ansicht (Sharelink) — kein Auth.
 *
 * GET  /public/projektplan/{hash}              → Plan-Daten für Anzeige
 * POST /public/projektplan/{hash}/feedback     → Feedback (like/dislike/comment)
 * PUT  /public/projektplan/{hash}/feedback/{id} → eigenes Feedback ändern
 * DELETE /public/projektplan/{hash}/feedback/{id} → eigenes Feedback löschen
 */

use Core\Database;
use Core\Response;

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$hash = trim((string) ($_GET['hash'] ?? ''));
$feedbackId = (int) ($_GET['feedback_id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($hash === '') Response::notFound('Sharelink fehlt');

$plan = $db->queryOne(
    "SELECT p.id, p.title, p.period_from, p.period_to, p.quarter, p.plan_status,
            p.share_hash, p.share_password,
            c.id AS customer_id, c.name AS customer_name, c.slug AS customer_slug,
            c.abbreviation AS customer_abbr, c.hex_color AS customer_color, c.logo_path AS customer_logo
     FROM pp_plans p
     LEFT JOIN customers c ON c.id = p.customer_id
     WHERE p.share_hash = ? AND p.state = 1",
    [$hash]
);
if (!$plan) Response::notFound('Plan nicht gefunden');

// Optional: Passwort-Check via Session
if (!empty($plan['share_password'])) {
    $sessKey = 'pp_access_' . (int) $plan['id'];
    if (empty($_SESSION[$sessKey])) {
        if ($method === 'POST' && $action === 'auth') {
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $pass = (string) ($payload['password'] ?? '');
            if (password_verify($pass, $plan['share_password'])) {
                $_SESSION[$sessKey] = true;
                Response::success(['authorized' => true]);
            }
            Response::error('Falsches Passwort', 401);
        }
        Response::error('Passwort erforderlich', 403);
    }
}

// ===== Feedback CREATE =====
if ($action === 'feedback' && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $type = in_array($payload['feedback_type'] ?? '', ['like', 'dislike', 'comment'], true) ? $payload['feedback_type'] : 'comment';
    $name = trim((string) ($payload['author_name'] ?? 'Anonym'));
    $rowId = !empty($payload['row_id']) ? (int) $payload['row_id'] : null;
    $message = trim((string) ($payload['message'] ?? ''));

    // Bei like/dislike: nur 1× pro author_name/row_id (toggle möglich)
    if (in_array($type, ['like', 'dislike'], true)) {
        $existing = $db->queryOne(
            "SELECT id, feedback_type FROM pp_plan_feedback
             WHERE plan_id = ? AND ((row_id = ?) OR (row_id IS NULL AND ? IS NULL))
                   AND author_name = ? AND feedback_type IN ('like','dislike')",
            [(int) $plan['id'], $rowId, $rowId, $name]
        );
        if ($existing) {
            if ($existing['feedback_type'] === $type) {
                // toggle off
                $db->execute("DELETE FROM pp_plan_feedback WHERE id = ?", [(int) $existing['id']]);
                Response::success(['toggled' => 'off']);
            }
            // wechseln
            $db->update('pp_plan_feedback', ['feedback_type' => $type], 'id = ?', [(int) $existing['id']]);
            Response::success(['toggled' => 'changed']);
        }
    }

    $newId = $db->insert('pp_plan_feedback', [
        'plan_id' => (int) $plan['id'],
        'row_id' => $rowId,
        'author_name' => mb_substr($name, 0, 255),
        'feedback_type' => $type,
        'message' => $message ?: null,
    ]);
    Response::success(['id' => $newId], 'Feedback gespeichert');
}

// ===== Feedback PUT (Edit) =====
if ($feedbackId > 0 && $method === 'PUT') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $author = trim((string) ($payload['author_name'] ?? ''));
    $newMessage = trim((string) ($payload['message'] ?? ''));
    // Owner-Check: gleicher author_name
    $fb = $db->queryOne("SELECT * FROM pp_plan_feedback WHERE id = ? AND plan_id = ?", [$feedbackId, (int) $plan['id']]);
    if (!$fb) Response::notFound('Feedback nicht gefunden');
    if (mb_strtolower($fb['author_name']) !== mb_strtolower($author)) {
        Response::error('Nicht berechtigt', 403);
    }
    $db->update('pp_plan_feedback', ['message' => $newMessage ?: null], 'id = ?', [$feedbackId]);
    Response::success(['id' => $feedbackId]);
}

// ===== Feedback DELETE =====
if ($feedbackId > 0 && $method === 'DELETE') {
    $author = trim((string) ($_GET['author'] ?? ''));
    $fb = $db->queryOne("SELECT * FROM pp_plan_feedback WHERE id = ? AND plan_id = ?", [$feedbackId, (int) $plan['id']]);
    if (!$fb) Response::notFound('Feedback nicht gefunden');
    if (mb_strtolower($fb['author_name']) !== mb_strtolower($author)) {
        Response::error('Nicht berechtigt', 403);
    }
    $db->execute("DELETE FROM pp_plan_feedback WHERE id = ?", [$feedbackId]);
    Response::success(['id' => $feedbackId]);
}

// ===== Plan-Daten (GET) =====
if ($method === 'GET') {
    $rows = $db->query(
        "SELECT id, row_type, description, timeframe, ist_hours, planned_hours,
                responsible, lead_responsible, deadline, is_done, is_placeholder,
                actual_hours, notes, position, asana_url, asana_task_name
         FROM pp_plan_rows WHERE plan_id = ? ORDER BY position ASC, id ASC",
        [(int) $plan['id']]
    ) ?: [];

    $feedback = $db->query(
        "SELECT id, row_id, author_name, feedback_type, message, created_at
         FROM pp_plan_feedback WHERE plan_id = ? ORDER BY id ASC",
        [(int) $plan['id']]
    ) ?: [];

    // Team-Mitglieder als Kürzel-Map (für Personen-Suffix-Format in der View)
    $team = $db->query(
        "SELECT name, abbreviation, hex_color FROM pp_team_members WHERE is_active = 1"
    ) ?: [];

    unset($plan['share_password']); // niemals nach außen
    Response::success([
        'plan' => $plan,
        'rows' => $rows,
        'feedback' => $feedback,
        'team' => $team,
    ]);
}

Response::error('Methode nicht unterstützt', 405);
