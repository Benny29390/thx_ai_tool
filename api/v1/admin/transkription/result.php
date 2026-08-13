<?php
/**
 * Transkription — Result/Editor API
 *
 * GET    /admin/transkription/jobs/{id}/result            Volltext + Segmente + Sprecher
 * PUT    /admin/transkription/jobs/{id}/result            Body: { transcript_text?, segments? }
 * PUT    /admin/transkription/jobs/{id}/speakers          Body: { speakers: [{id, name_custom}] }
 * POST   /admin/transkription/jobs/{id}/apply-corrections Korrektur-Dict auf Volltext anwenden
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\TranskriptionService;
use Services\TranskriptionEditorService;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

require_once SERVICES_PATH . '/TranskriptionService.php';
require_once SERVICES_PATH . '/TranskriptionEditorService.php';

$db = Database::getInstance();
$svc = new TranskriptionService($db);
$editor = new TranskriptionEditorService($db);
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$method   = $_SERVER['REQUEST_METHOD'];
$jobId    = (int)($_GET['job_id'] ?? 0);
$action   = $_GET['result_action'] ?? 'result';

if (!$jobId) Response::error('job_id fehlt');
if (!$svc->getJob($jobId, $userId)) Response::notFound('Job nicht gefunden oder kein Zugriff');

if ($method === 'GET' && $action === 'result') {
    $data = $editor->loadResult($jobId);
    if (!$data) Response::notFound('Kein Transkript fuer diesen Job');
    Response::success($data);
}

$raw = file_get_contents('php://input');
$json = $raw ? json_decode($raw, true) : null;
if (!is_array($json)) $json = [];

if ($method === 'PUT' && $action === 'result') {
    try {
        $editor->updateResult($jobId, $json, $userId);
        Response::success(['updated' => true], 'Transkript gespeichert');
    } catch (\Throwable $e) {
        Response::error('Speichern fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($method === 'PUT' && $action === 'speakers') {
    try {
        $editor->renameSpeakers($jobId, $json['speakers'] ?? []);
        Response::success(['updated' => true], 'Sprecher aktualisiert');
    } catch (\Throwable $e) {
        Response::error('Speichern fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($method === 'POST' && $action === 'apply-corrections') {
    try {
        $res = $editor->applyCorrections($jobId, $userId);
        Response::success($res, $res['changes'] . ' Korrekturen angewendet');
    } catch (\Throwable $e) {
        Response::error('Korrekturen fehlgeschlagen: ' . $e->getMessage());
    }
}

Response::error('Methode/Aktion nicht unterstuetzt', 405);
