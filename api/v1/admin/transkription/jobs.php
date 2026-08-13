<?php
/**
 * Transkription — Jobs API
 *
 * GET    /admin/transkription/jobs                 Eigene + erlaubte Jobs (Filter optional)
 * POST   /admin/transkription/jobs                 Upload (multipart: file) ODER Loom-URL (json)
 * DELETE /admin/transkription/jobs/{id}            Job + Quelldatei loeschen
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\TranskriptionService;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

require_once SERVICES_PATH . '/TranskriptionService.php';
$svc = new TranskriptionService(Database::getInstance());
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$method = $_SERVER['REQUEST_METHOD'];
$jobId  = (int)($_GET['job_id'] ?? 0);

if ($method === 'GET') {
    $filter = [
        'status'      => $_GET['status']      ?? null,
        'customer_id' => isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null,
    ];
    Response::success(['jobs' => $svc->listJobs($userId, $filter)]);
}

if ($method === 'POST') {
    // Variante A: multipart-Upload
    if (!empty($_FILES['file'])) {
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $codes = [
                UPLOAD_ERR_INI_SIZE   => 'Datei > upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
                UPLOAD_ERR_FORM_SIZE  => 'Datei > MAX_FILE_SIZE im Form',
                UPLOAD_ERR_PARTIAL    => 'Upload abgebrochen — nur teilweise empfangen',
                UPLOAD_ERR_NO_FILE    => 'Keine Datei ausgewaehlt',
                UPLOAD_ERR_NO_TMP_DIR => 'Server-Temp-Verzeichnis fehlt',
                UPLOAD_ERR_CANT_WRITE => 'Server konnte nicht auf Disk schreiben',
                UPLOAD_ERR_EXTENSION  => 'PHP-Extension blockiert Upload',
            ];
            $msg = $codes[$file['error']] ?? ('Unbekannter Upload-Fehler (Code ' . $file['error'] . ')');
            Response::error('Upload-Fehler: ' . $msg);
        }
        $cid = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
        if ($cid <= 0) Response::error('Bitte einen Kunden auswaehlen — Transkripte landen ohne Zuordnung nicht im Wissen.');
        $opts = [
            'customer_id'             => $cid,
            'model'                   => $_POST['model']                   ?? 'medium',
            'transcription_backend'   => $_POST['transcription_backend']   ?? 'local',
            'language'                => $_POST['language']                ?? null,
            'speaker_mode'            => $_POST['speaker_mode']            ?? 'multi',
            'template_type'           => $_POST['template_type']           ?? null,
            'consent_ref'             => $_POST['consent_ref']             ?? null,
            'auto_templates'          => $_POST['auto_templates']          ?? null,
            'auto_knowledge_template' => $_POST['auto_knowledge_template'] ?? null,
        ];
        try {
            $res = $svc->ingestUpload($file['tmp_name'], $file['name'], $userId, $opts);
            Response::success($res, 'Upload angenommen — Job #' . $res['job_id'] . ' eingereiht');
        } catch (\Throwable $e) {
            Response::error('Upload fehlgeschlagen: ' . $e->getMessage());
        }
    }

    // Variante B: Loom-URL via JSON
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    if (is_array($json) && !empty($json['loom_url'])) {
        $cid = isset($json['customer_id']) ? (int)$json['customer_id'] : 0;
        if ($cid <= 0) Response::error('Bitte einen Kunden auswaehlen — Transkripte landen ohne Zuordnung nicht im Wissen.');
        try {
            $res = $svc->ingestLoomUrl($json['loom_url'], $userId, [
                'customer_id'             => $cid,
                'model'                   => $json['model']                   ?? 'medium',
                'transcription_backend'   => $json['transcription_backend']   ?? 'local',
                'language'                => $json['language']                ?? null,
                'speaker_mode'            => $json['speaker_mode']            ?? 'multi',
                'template_type'           => $json['template_type']           ?? null,
                'consent_ref'             => $json['consent_ref']             ?? null,
                'auto_templates'          => $json['auto_templates']          ?? null,
                'auto_knowledge_template' => $json['auto_knowledge_template'] ?? null,
            ]);
            Response::success($res, 'Loom-Import angenommen — Job #' . $res['job_id'] . ' eingereiht');
        } catch (\Throwable $e) {
            Response::error('Loom-Import fehlgeschlagen: ' . $e->getMessage());
        }
    }

    // Sonst: vermutlich Upload > post_max_size
    $serverPost = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    Response::error('Keine Datei und keine Loom-URL erhalten. '
        . 'CONTENT_LENGTH=' . round($serverPost / 1024 / 1024, 1) . ' MB · '
        . 'post_max_size=' . ini_get('post_max_size') . ' · '
        . 'upload_max_filesize=' . ini_get('upload_max_filesize'));
}

// POST /jobs/{id}/retry
if ($method === 'POST' && !empty($_GET['job_action']) && $_GET['job_action'] === 'retry') {
    if (!$jobId) Response::error('job_id fehlt');
    $ok = $svc->retryJob($jobId, $userId);
    if (!$ok) Response::notFound('Job nicht gefunden oder kein Zugriff');
    Response::success(['queued' => $jobId], 'Job wieder in die Warteschlange');
}

if ($method === 'PATCH') {
    if (!$jobId) Response::error('job_id fehlt');
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    if (!is_array($json)) Response::error('Body muss JSON sein');
    if (isset($json['title'])) {
        $ok = $svc->updateTitle($jobId, $userId, trim((string)$json['title']));
        if (!$ok) Response::notFound('Job nicht gefunden oder kein Zugriff');
    }
    Response::success(['updated' => $jobId], 'Job aktualisiert');
}

if ($method === 'DELETE') {
    if (!$jobId) Response::error('job_id fehlt');
    $ok = $svc->deleteJob($jobId, $userId);
    if (!$ok) Response::notFound('Job nicht gefunden oder kein Zugriff');
    Response::success(['deleted' => $jobId], 'Job geloescht');
}

Response::error('Methode nicht unterstuetzt', 405);
