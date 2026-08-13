<?php
/**
 * Projektplanner — Feedback Admin API
 *
 * GET    /admin/projektplanner/plans/{id}/feedback                     Liste pro Plan
 * POST   /admin/projektplanner/plans/{id}/feedback/{fid}/read          Als gelesen markieren
 * POST   /admin/projektplanner/plans/{id}/feedback/{fid}/unread        Wieder als ungelesen markieren
 * DELETE /admin/projektplanner/plans/{id}/feedback/{fid}               Feedback löschen
 * POST   /admin/projektplanner/plans/{id}/feedback/read-all            Alle als gelesen markieren
 */

use Core\Auth;
use Core\Database;
use Core\Response;

require __DIR__ . '/_pp_perm.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$planId = (int) ($_GET['plan_id'] ?? 0);
$fbId = (int) ($_GET['feedback_id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$planId) Response::error('plan_id fehlt');

// Read für GET reicht, Mark-Read/Delete brauchen write
$needPerm = ($method === 'GET') ? 'read' : 'write';
pp_require($planId, $needPerm);

if ($method === 'GET') {
    $rows = $db->query(
        "SELECT f.id, f.row_id, f.author_name, f.feedback_type, f.message, f.read_at, f.created_at,
                r.description AS row_description, r.row_type
         FROM pp_plan_feedback f
         LEFT JOIN pp_plan_rows r ON r.id = f.row_id
         WHERE f.plan_id = ?
         ORDER BY f.created_at DESC, f.id DESC",
        [$planId]
    ) ?: [];
    Response::success(['feedback' => $rows]);
}

if ($action === 'read-all' && $method === 'POST') {
    $now = date('Y-m-d H:i:s');
    $count = $db->execute(
        "UPDATE pp_plan_feedback SET read_at = ? WHERE plan_id = ? AND read_at IS NULL",
        [$now, $planId]
    );
    Response::success(['marked' => $count]);
}

if ($fbId > 0 && $action === 'read' && $method === 'POST') {
    $db->update('pp_plan_feedback', ['read_at' => date('Y-m-d H:i:s')], 'id = ? AND plan_id = ?', [$fbId, $planId]);
    Response::success(['ok' => true]);
}
if ($fbId > 0 && $action === 'unread' && $method === 'POST') {
    $db->update('pp_plan_feedback', ['read_at' => null], 'id = ? AND plan_id = ?', [$fbId, $planId]);
    Response::success(['ok' => true]);
}
if ($fbId > 0 && $method === 'DELETE') {
    $db->execute("DELETE FROM pp_plan_feedback WHERE id = ? AND plan_id = ?", [$fbId, $planId]);
    Response::success(['ok' => true]);
}

Response::error('Methode nicht unterstützt', 405);
