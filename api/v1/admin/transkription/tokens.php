<?php
/**
 * Transkription — API-Token-Verwaltung pro User.
 *
 * GET    /admin/transkription/tokens             Eigene Tokens (ohne Klartext, nur Metadaten)
 * POST   /admin/transkription/tokens             Body: { label } → erzeugt + zeigt Klartext EINMAL
 * DELETE /admin/transkription/tokens/{id}        Token loeschen
 *
 * Token-Format: thx_tr_<48 hex>. Wird per sha256-Hash in tr_api_tokens gespeichert,
 * Klartext nur einmalig in der POST-Response sichtbar.
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

$db = Database::getInstance();
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$method = $_SERVER['REQUEST_METHOD'];
$id = (int)($_GET['token_id'] ?? 0);

if ($method === 'GET') {
    $rows = $db->query(
        'SELECT id, label, created_at, last_used_at FROM tr_api_tokens WHERE user_id=? ORDER BY created_at DESC',
        [$userId]
    );
    Response::success(['tokens' => $rows]);
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    $label = trim((string)($json['label'] ?? 'Bookmarklet'));
    if ($label === '') $label = 'Token';

    // Token: thx_tr_<48 hex>
    $secret = bin2hex(random_bytes(24));
    $clear  = 'thx_tr_' . $secret;
    $hash   = hash('sha256', $clear);

    $id = (int)$db->insert('tr_api_tokens', [
        'user_id'    => $userId,
        'token_hash' => $hash,
        'label'      => substr($label, 0, 120),
    ]);
    Response::success([
        'id'    => $id,
        'label' => $label,
        'token' => $clear,    // einmalig
    ], 'Token erzeugt — bitte JETZT kopieren, er wird nicht erneut angezeigt');
}

if ($method === 'DELETE') {
    if (!$id) Response::error('token_id fehlt');
    $rows = $db->execute('DELETE FROM tr_api_tokens WHERE id=? AND user_id=?', [$id, $userId]);
    if ($rows === 0) Response::notFound('Token nicht gefunden oder nicht Dir');
    Response::success(['deleted' => $id], 'Token geloescht');
}

Response::error('Methode nicht unterstuetzt', 405);
