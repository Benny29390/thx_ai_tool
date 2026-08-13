<?php
/**
 * Admin API: Maßnahmen (feedback_measures)
 *
 * Routen (in api/handler.php verdrahtet):
 *   GET    /admin/measures                 Liste (?status=offen|in_arbeit|erledigt|verworfen|all)
 *   POST   /admin/measures                 Maßnahme anlegen (manuell)
 *   GET    /admin/measures/{id}            Einzeln inkl. verknuepfter Feedbacks
 *   PUT    /admin/measures/{id}            aktualisieren (status, priority, title, ...)
 *   DELETE /admin/measures/{id}            loeschen
 *   POST   /admin/measures/from-feedback   Body {feedback_id} -> Maßnahme aus Feedback
 *   POST   /admin/measures/analyze         KI-Analyse: offene Feedbacks -> Maßnahmen-Vorschlaege
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/FeedbackMeasureService.php';
$svc = new \Services\FeedbackMeasureService($db);

$action = $_GET['action'] ?? null;
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ----- Sonderaktionen ------------------------------------------------------

if ($action === 'analyze') {
    if ($method !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $settings = \Core\Settings::decryptMap($settings);
    try {
        $res = $svc->analyze($settings, Auth::id());
        $msg = $res['created'] > 0
            ? ($res['created'] . ' Maßnahme(n) aus ' . $res['analyzed'] . ' Feedback(s) vorgeschlagen')
            : ($res['message'] ?? 'Keine neuen Maßnahmen.');
        Response::success($res, $msg);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'from-feedback') {
    if ($method !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    $fid = (int) ($input['feedback_id'] ?? 0);
    if (!$fid) {
        Response::error('feedback_id erforderlich');
    }
    try {
        $mid = $svc->fromFeedback($fid, Auth::id());
        $m = $svc->getMeasure($mid);
        Response::success([
            'id'     => $mid,
            'title'  => $m['title'] ?? '',
            'status' => $m['status'] ?? 'offen',
        ], 'Maßnahme angelegt');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

// ----- Standard-CRUD -------------------------------------------------------

switch ($method) {
    case 'GET':
        if ($id) {
            $m = $svc->getMeasure($id);
            $m ? Response::success($m) : Response::notFound('Maßnahme nicht gefunden');
        } else {
            Response::success($svc->listMeasures($_GET['status'] ?? 'all'));
        }
        break;

    case 'POST':
        try {
            $mid = $svc->create(array_merge((array) $input, [
                'created_by' => Auth::id(),
                'source'     => 'manuell',
            ]));
            Response::success(['id' => $mid], 'Maßnahme angelegt');
        } catch (\Throwable $e) {
            Response::error($e->getMessage());
        }
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }
        try {
            $svc->update($id, (array) $input);
            Response::success(null, 'Maßnahme aktualisiert');
        } catch (\Throwable $e) {
            Response::error($e->getMessage());
        }
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }
        $svc->delete($id);
        Response::success(null, 'Maßnahme gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
