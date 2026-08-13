<?php
/** POST /api/v1/lam/domain-anbieter-entfernen  Body: { junction_id } */
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$jid = trim((string) ($input['junction_id'] ?? ''));
if ($jid === '') Response::error('junction_id erforderlich', 400);
$db = Database::getInstance();
// Wenn das der direkte Anbieter auf der Domain war: lam_domains.anbieter_id auch leeren
$row = $db->queryOne("SELECT domain_id, anbieter_id FROM lam_domain_anbieter WHERE id = ?", [$jid]);
if ($row) {
    $direkt = $db->queryValue("SELECT anbieter_id FROM lam_domains WHERE id = ?", [$row['domain_id']]);
    if ($direkt === $row['anbieter_id']) {
        $db->execute("UPDATE lam_domains SET anbieter_id = NULL WHERE id = ?", [$row['domain_id']]);
    }
}
$db->execute("DELETE FROM lam_domain_anbieter WHERE id = ?", [$jid]);
Response::success(['ok' => true]);
