<?php
/**
 * Admin Feedback API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

// ----- KI-Analyse eines einzelnen Feedbacks (Maßnahmen-Erkennung + naechste Schritte) -----
if (($_GET['action'] ?? '') === 'analyze') {
    if ($method !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    if (!$id) {
        Response::error('ID erforderlich');
    }
    require_once SERVICES_PATH . '/FeedbackMeasureService.php';
    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    $settings = \Core\Settings::decryptMap($settings);
    try {
        $svc = new \Services\FeedbackMeasureService($db);
        $res = $svc->analyzeOne((int) $id, $settings);
        Response::success($res, 'Analyse fertig');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

switch ($method) {
    case 'GET':
        if ($id) {
            // Einzelnes Feedback
            $feedback = $db->queryOne(
                "SELECT f.*, u.name as user_name, u.email as user_email
                 FROM internal_feedback f
                 JOIN users u ON f.user_id = u.id
                 WHERE f.id = ?",
                [$id]
            );

            if (!$feedback) {
                Response::notFound('Feedback nicht gefunden');
            }

            Response::success($feedback);
        } else {
            // Liste mit Filter
            $status = $_GET['status'] ?? 'all';
            $where = $status !== 'all' ? "WHERE f.status = ?" : "";
            $params = $status !== 'all' ? [$status] : [];

            $feedback = $db->query(
                "SELECT f.*, u.name as user_name
                 FROM internal_feedback f
                 JOIN users u ON f.user_id = u.id
                 $where
                 ORDER BY f.created_at DESC",
                $params
            );

            Response::success($feedback);
        }
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $feedback = $db->queryOne("SELECT * FROM internal_feedback WHERE id = ?", [$id]);
        if (!$feedback) {
            Response::notFound('Feedback nicht gefunden');
        }

        $updates = [];
        if (isset($input['status'])) {
            $allowedStatuses = ['new', 'in_progress', 'resolved', 'wont_fix'];
            if (!in_array($input['status'], $allowedStatuses)) {
                Response::error('Ungültiger Status');
            }
            $updates['status'] = $input['status'];

            // Resolved Timestamp setzen
            if (in_array($input['status'], ['resolved', 'wont_fix'])) {
                $updates['resolved_at'] = date('Y-m-d H:i:s');
                $updates['resolved_by'] = Auth::id();
            } else {
                $updates['resolved_at'] = null;
                $updates['resolved_by'] = null;
            }
        }

        if (isset($input['admin_notes'])) {
            $updates['admin_notes'] = $input['admin_notes'];
        }

        if (isset($input['next_steps'])) {
            $updates['next_steps'] = $input['next_steps'];
        }

        if (isset($input['title'])) {
            $updates['title'] = mb_substr(trim((string)$input['title']), 0, 255);
        }

        if (!empty($updates)) {
            $db->update('internal_feedback', $updates, 'id = ?', [$id]);
        }

        Response::success(null, 'Feedback aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $feedback = $db->queryOne("SELECT * FROM internal_feedback WHERE id = ?", [$id]);
        if (!$feedback) {
            Response::notFound('Feedback nicht gefunden');
        }

        // Media-Datei löschen falls vorhanden
        if ($feedback['media_path']) {
            $filepath = ROOT_PATH . $feedback['media_path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        $db->delete('internal_feedback', 'id = ?', [$id]);
        Response::success(null, 'Feedback gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
