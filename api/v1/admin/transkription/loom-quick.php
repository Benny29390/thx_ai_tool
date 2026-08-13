<?php
/**
 * Transkription — Loom-Quick-Import.
 *
 * Authentifizierung NUR per API-Token (keine Session-Cookie noetig — fuer
 * Bookmarklets, iOS-Shortcuts, Zapier, Make.com).
 *
 * Token kann an drei Stellen kommen:
 *   - Header:  Authorization: Bearer thx_tr_xxx
 *   - Header:  X-Tr-Token: thx_tr_xxx
 *   - Query:   ?token=thx_tr_xxx        (nur fuer Browser-Bookmarklet)
 *
 * Request:
 *   POST /api/v1/admin/transkription/loom-quick
 *   Body (JSON):
 *     { "url":  "https://www.loom.com/share/..." }       // einzelne URL
 *     { "urls": ["https://...", "https://..."] }         // mehrere URLs
 *     { "text": "neuer Loom von ... https://www.loom.com/share/abc ..." }
 *                                                        // beliebiger Text — URLs werden extrahiert
 *   ODER form-data: url=... | urls[]=... | text=...
 *   ODER ?url=...                                        (Query-Param, fuer Bookmarklet-GET)
 *
 * Akzeptiert text-Variante ist fuer Slack/Zapier-Webhook-Bodies gedacht.
 *
 * Antwort:  { success: true, data: { job_id, upload_id } }                    // bei einer URL
 *           { success: true, data: { created, skipped, failed, jobs: [...] }} // bei mehreren
 *
 * Defaults aus Settings (Templates etc.) gelten — wie bei normalem Upload.
 */

use Core\Database;
use Core\Response;
use Services\TranskriptionService;

// CORS-Praeflug fuer Cross-Origin-Bookmarklets
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tr-Token');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

require_once SERVICES_PATH . '/TranskriptionService.php';

// ---- Token finden ----
$token = '';
$auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with(strtolower($auth), 'bearer ')) {
    $token = trim(substr($auth, 7));
}
if ($token === '') $token = (string)($_SERVER['HTTP_X_TR_TOKEN'] ?? '');
if ($token === '') $token = (string)($_GET['token'] ?? '');

if ($token === '' || !str_starts_with($token, 'thx_tr_')) {
    Response::error('API-Token fehlt — als Bearer-Header, X-Tr-Token-Header oder ?token=… senden', 401);
}

$db = Database::getInstance();
$row = $db->queryOne(
    'SELECT t.id, t.user_id, u.is_active
     FROM tr_api_tokens t JOIN users u ON u.id = t.user_id
     WHERE t.token_hash = ? LIMIT 1',
    [hash('sha256', $token)]
);
if (!$row || (int)$row['is_active'] !== 1) {
    Response::error('Token ungueltig', 401);
}
$userId = (int)$row['user_id'];

// Token-„last_used" markieren
$db->execute('UPDATE tr_api_tokens SET last_used_at=NOW() WHERE id=?', [(int)$row['id']]);

// Auth-Singleton mit dem Token-User initialisieren, damit Settings/Cap-Checks
// in Services funktionieren (z.B. resolveCustomerId, ingestLoomUrl).
\Core\Auth::initFromUserId($userId);

if (!\Core\Auth::can(CAP_TRANSCRIPTION)) {
    Response::error('User hat die Capability „transcription" nicht', 403);
}

// ---- URL einlesen ----
$url = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        if (is_array($json)) $url = trim((string)($json['url'] ?? $json['loom_url'] ?? ''));
    } else {
        $url = trim((string)($_POST['url'] ?? $_POST['loom_url'] ?? ''));
    }
}
if ($url === '') $url = trim((string)($_GET['url'] ?? ''));

if ($url === '') {
    Response::error('Parameter „url" fehlt', 422);
}

// ---- Anstossen ----
$svc = new TranskriptionService($db);
try {
    $res = $svc->ingestLoomUrl($url, $userId, []);
    Response::success(
        ['job_id' => $res['job_id'], 'upload_id' => $res['upload_id']],
        'Loom-Aufnahme eingereiht — Job #' . $res['job_id']
    );
} catch (\Throwable $e) {
    Response::error('Import fehlgeschlagen: ' . $e->getMessage(), 422);
}
