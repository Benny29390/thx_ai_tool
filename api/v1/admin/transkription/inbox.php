<?php
/**
 * Transkription — Inbox API fuer das Dashboard
 *
 * GET /admin/transkription/inbox
 *
 * Liefert alle „zu klaerenden" Punkte fuer den eingeloggten User:
 *   - speakers:     done-Jobs mit speaker_count > 1 und mind. einem unbenannten Sprecher
 *   - failed:       Jobs mit status='failed'
 *   - auto_partial: Jobs mit auto_status='partial' oder 'failed'
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

$db = Database::getInstance();
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$isAdmin = Auth::isAdmin();
$customerIds = array_map('intval', array_column(Auth::customers(), 'id'));

// Scope: alle Jobs, die der User sieht (selbst hochgeladen ODER Kunde zugewiesen)
$scopeSql = '1=1';
$scopeParams = [];
if (!$isAdmin) {
    $custIn = $customerIds ? ('u.customer_id IN (' . implode(',', array_fill(0, count($customerIds), '?')) . ')') : '0';
    $scopeSql = '(u.user_id = ? OR ' . $custIn . ')';
    $scopeParams[] = $userId;
    foreach ($customerIds as $cid) $scopeParams[] = $cid;
}

$items = [];

// (1) Failed jobs
$failed = $db->query(
    "SELECT j.id AS job_id, u.filename, j.error_message
     FROM tr_jobs j JOIN tr_uploads u ON u.id=j.upload_id
     WHERE j.status='failed' AND $scopeSql
     ORDER BY j.finished_at DESC LIMIT 10",
    $scopeParams
);
foreach ($failed as $r) {
    $items[] = [
        'kind'     => 'failed',
        'job_id'   => (int)$r['job_id'],
        'filename' => $r['filename'],
        'detail'   => $r['error_message'] ? substr((string)$r['error_message'], 0, 140) : null,
    ];
}

// (2) Auto-Pipeline partial / failed (Job selbst war ok)
$autoBroken = $db->query(
    "SELECT j.id AS job_id, u.filename, j.auto_status, j.error_message
     FROM tr_jobs j JOIN tr_uploads u ON u.id=j.upload_id
     WHERE j.status='done' AND j.auto_status IN ('partial','failed') AND $scopeSql
     ORDER BY j.finished_at DESC LIMIT 10",
    $scopeParams
);
foreach ($autoBroken as $r) {
    $items[] = [
        'kind'     => 'auto_partial',
        'job_id'   => (int)$r['job_id'],
        'filename' => $r['filename'],
        'detail'   => 'auto-status: ' . $r['auto_status'] . ($r['error_message'] ? ' — ' . substr((string)$r['error_message'], 0, 100) : ''),
    ];
}

// (3) Sprecher unbenannt: done-Jobs mit speaker_count > 1 und ≥1 leerem name_custom
$speakerOpen = $db->query(
    "SELECT j.id AS job_id, u.filename, r.speaker_count,
            SUM(CASE WHEN s.name_custom IS NULL OR s.name_custom='' THEN 1 ELSE 0 END) AS unnamed
     FROM tr_jobs j
     JOIN tr_uploads u ON u.id=j.upload_id
     JOIN tr_results r ON r.job_id=j.id
     LEFT JOIN tr_speakers s ON s.result_id=r.id
     WHERE j.status='done' AND r.speaker_count > 1 AND $scopeSql
     GROUP BY j.id, u.filename, r.speaker_count
     HAVING unnamed > 0
     ORDER BY j.finished_at DESC LIMIT 10",
    $scopeParams
);
foreach ($speakerOpen as $r) {
    $items[] = [
        'kind'     => 'speakers',
        'job_id'   => (int)$r['job_id'],
        'filename' => $r['filename'],
        'detail'   => $r['unnamed'] . ' von ' . $r['speaker_count'] . ' Sprechern noch ohne Namen',
    ];
}

Response::success(['items' => $items]);
