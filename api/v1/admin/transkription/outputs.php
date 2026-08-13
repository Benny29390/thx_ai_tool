<?php
/**
 * Transkription — Outputs API
 *
 * GET  /admin/transkription/jobs/{id}/outputs            Liste vorhandener Outputs
 * POST /admin/transkription/jobs/{id}/outputs            Body: { template_type } → erzeugt Output via LLM
 * GET  /admin/transkription/outputs/{id}/download        DOCX-Download
 * POST /admin/transkription/jobs/{id}/to-knowledge       Body: { output_id?: int } → ins Wissen
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\TranskriptionOutputService;
use Services\TranskriptionService;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

require_once SERVICES_PATH . '/TranskriptionService.php';
require_once SERVICES_PATH . '/TranskriptionOutputService.php';

$db = Database::getInstance();
$svc = new TranskriptionService($db);
$out = new TranskriptionOutputService($db);
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$method = $_SERVER['REQUEST_METHOD'];
$jobId = (int)($_GET['job_id'] ?? 0);
$outputId = (int)($_GET['output_id'] ?? 0);
$action = $_GET['outputs_action'] ?? 'list';

if ($action === 'delete-output' && $outputId && $method === 'DELETE') {
    $ok = $out->deleteOutput($outputId, $userId, Auth::isAdmin());
    if (!$ok) Response::notFound('Output nicht gefunden oder kein Zugriff');
    Response::success(['deleted' => $outputId], 'Output geloescht');
}

if ($action === 'download' && $outputId && $method === 'GET') {
    $info = $out->downloadDocx($outputId, $userId, Auth::isAdmin());
    if (!$info) Response::notFound('Datei nicht gefunden');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $info['filename'] . '"');
    header('Content-Length: ' . filesize($info['path']));
    readfile($info['path']);
    exit;
}

if (!$jobId) Response::error('job_id fehlt');
if (!$svc->getJob($jobId, $userId)) Response::notFound('Job nicht gefunden oder kein Zugriff');

if ($action === 'list' && $method === 'GET') {
    Response::success(['outputs' => $out->listOutputsForJob($jobId)]);
}

$raw = file_get_contents('php://input');
$json = $raw ? json_decode($raw, true) : null;
if (!is_array($json)) $json = [];

if ($action === 'list' && $method === 'POST') {
    $type = $json['template_type'] ?? '';
    if ($type === '') Response::error('template_type fehlt');
    try {
        $res = $out->generate($jobId, $type, $userId);
        Response::success($res, 'Output erzeugt (' . $type . ')');
    } catch (\Throwable $e) {
        Response::error('Erzeugung fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($action === 'to-knowledge' && $method === 'POST') {
    try {
        $docId = $out->sendToKnowledge($jobId, $json['output_id'] ?? null, $userId);
        Response::success(['knowledge_doc_id' => $docId], 'Ins Wissen eingespeist (Doc #' . $docId . ')');
    } catch (\Throwable $e) {
        Response::error('Einspeisung fehlgeschlagen: ' . $e->getMessage());
    }
}

Response::error('Methode/Aktion nicht unterstuetzt', 405);
