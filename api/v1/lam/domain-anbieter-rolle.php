<?php
/** POST /api/v1/lam/domain-anbieter-rolle  Body: { junction_id, rolle: 'betreiber'|'vermittler' } */
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$jid = trim((string) ($input['junction_id'] ?? ''));
$rolle = $input['rolle'] ?? 'betreiber';
if ($jid === '') Response::error('junction_id erforderlich', 400);
if (!in_array($rolle, ['betreiber', 'vermittler'], true)) Response::error('Ungültige Rolle', 400);
$db = Database::getInstance();
$db->execute("UPDATE lam_domain_anbieter SET rolle = ? WHERE id = ?", [$rolle, $jid]);
// Globalen Flag am Anbieter ergänzen (OR-Logik: nicht entfernen, nur hinzufügen)
$anbieterId = $db->queryValue("SELECT anbieter_id FROM lam_domain_anbieter WHERE id = ?", [$jid]);
if ($anbieterId) {
    if ($rolle === 'betreiber') $db->execute("UPDATE lam_anbieter SET ist_betreiber = 1 WHERE id = ?", [$anbieterId]);
    else $db->execute("UPDATE lam_anbieter SET ist_vermittler = 1 WHERE id = ?", [$anbieterId]);
}
Response::success(['ok' => true]);
