<?php
/**
 * POST /api/v1/lam/domain-anbieter-verschieben
 * Body: { junction_id, richtung: -1|1 }
 * Verschiebt die Position eines Anbieter-Eintrags an einer Domain um +/-1.
 * Tauscht mit dem Nachbarn (swap positions).
 */
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$jid = trim((string) ($input['junction_id'] ?? ''));
$richtung = (int) ($input['richtung'] ?? 0);
if ($jid === '' || !in_array($richtung, [-1, 1], true)) Response::error('Ungültige Parameter', 400);

$db = Database::getInstance();
$row = $db->queryOne("SELECT domain_id, position FROM lam_domain_anbieter WHERE id = ?", [$jid]);
if (!$row) Response::error('Eintrag nicht gefunden', 404);

// Nachbarn finden: nächster nach oben (kleinste Position > $row['position'] - 1) oder unten
$nachbar = $db->queryOne(
    $richtung === -1
        ? "SELECT id, position FROM lam_domain_anbieter WHERE domain_id = ? AND position < ? ORDER BY position DESC LIMIT 1"
        : "SELECT id, position FROM lam_domain_anbieter WHERE domain_id = ? AND position > ? ORDER BY position ASC LIMIT 1",
    [$row['domain_id'], $row['position']]
);
if (!$nachbar) Response::success(['ok' => true, 'noop' => true]);

// Swap positions
$db->execute("UPDATE lam_domain_anbieter SET position = ? WHERE id = ?", [$nachbar['position'], $jid]);
$db->execute("UPDATE lam_domain_anbieter SET position = ? WHERE id = ?", [$row['position'], $nachbar['id']]);

Response::success(['ok' => true]);
