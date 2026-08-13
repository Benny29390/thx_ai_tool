<?php
/**
 * Person-Sharelinks (Admin) — generieren + auflisten
 *
 * GET    /admin/projektplanner/person-shares       Liste aller Sharelinks
 * POST   /admin/projektplanner/person-shares       Neuen Sharelink anlegen
 * DELETE /admin/projektplanner/person-shares/{id}  Sharelink löschen
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$db = Database::getInstance();
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
$shareId = (int) ($_GET['share_id'] ?? 0);

if ($shareId > 0 && $method === 'DELETE') {
    $db->execute("DELETE FROM pp_person_shares WHERE id = ?", [$shareId]);
    Response::success(['id' => $shareId]);
}

if ($method === 'GET') {
    $rows = $db->query(
        "SELECT s.id, s.person_name, s.share_hash, s.created_at,
                u.name AS created_by_name
         FROM pp_person_shares s
         LEFT JOIN users u ON u.id = s.created_by
         ORDER BY s.id DESC"
    ) ?: [];
    Response::success(['shares' => $rows]);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string) ($payload['person_name'] ?? ''));
    if ($name === '') Response::error('person_name fehlt');
    // Wenn schon vorhanden: bestehenden zurückgeben
    $existing = $db->queryOne("SELECT id, share_hash FROM pp_person_shares WHERE person_name = ?", [$name]);
    if ($existing) {
        Response::success(['id' => $existing['id'], 'share_hash' => $existing['share_hash']], 'Bestehender Sharelink');
    }
    $hash = bin2hex(random_bytes(16));
    $newId = $db->insert('pp_person_shares', [
        'person_name' => $name,
        'share_hash' => $hash,
        'created_by' => $userId,
    ]);
    Response::success(['id' => $newId, 'share_hash' => $hash], 'Sharelink erzeugt');
}

Response::error('Methode nicht unterstützt', 405);
