<?php
/**
 * Transkription — Korrektur-Dictionary API
 *
 * GET    /admin/transkription/corrections             Liste (global + user)
 * POST   /admin/transkription/corrections             Body: { original, correction, scope: 'user'|'global' }
 * DELETE /admin/transkription/corrections/{id}
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\TranskriptionEditorService;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

require_once SERVICES_PATH . '/TranskriptionEditorService.php';
$svc = new TranskriptionEditorService(Database::getInstance());
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$method = $_SERVER['REQUEST_METHOD'];
$id = (int)($_GET['correction_id'] ?? 0);

if ($method === 'GET') {
    Response::success(['corrections' => $svc->listCorrections($userId)]);
}

$raw = file_get_contents('php://input');
$json = $raw ? json_decode($raw, true) : null;
if (!is_array($json)) $json = [];

if ($method === 'POST') {
    $original = trim((string)($json['original'] ?? ''));
    $correction = trim((string)($json['correction'] ?? ''));
    $scope = $json['scope'] ?? 'user';
    if ($original === '' || $correction === '') Response::error('original und correction muessen gefuellt sein');
    if (!in_array($scope, ['user', 'global'], true)) $scope = 'user';
    if ($scope === 'global' && !Auth::isAdmin()) Response::forbidden('Globale Korrekturen darf nur Admin anlegen');
    try {
        $id = $svc->createCorrection($original, $correction, $scope, $userId);
        Response::success(['id' => $id], 'Korrektur gespeichert');
    } catch (\Throwable $e) {
        Response::error('Speichern fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($method === 'DELETE') {
    if (!$id) Response::error('correction_id fehlt');
    $ok = $svc->deleteCorrection($id, $userId, Auth::isAdmin());
    if (!$ok) Response::notFound('Korrektur nicht gefunden oder kein Zugriff');
    Response::success(['deleted' => $id], 'Korrektur geloescht');
}

Response::error('Methode nicht unterstuetzt', 405);
