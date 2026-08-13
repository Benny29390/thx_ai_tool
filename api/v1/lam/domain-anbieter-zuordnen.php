<?php
/**
 * POST /api/v1/lam/domain-anbieter-zuordnen
 * Body: { domain_id, anbieter_id, rolle: 'betreiber'|'vermittler' }
 * Verknüpft einen bestehenden Anbieter mit einer Domain via lam_domain_anbieter.
 * Dubletten-sicher (gleiche domain_id + anbieter_id → Rolle aktualisieren).
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$domainId = trim((string) ($input['domain_id'] ?? ''));
$anbieterId = trim((string) ($input['anbieter_id'] ?? ''));
$rolle = $input['rolle'] ?? 'betreiber';
if ($domainId === '' || $anbieterId === '') Response::error('domain_id + anbieter_id erforderlich', 400);
if (!in_array($rolle, ['betreiber', 'vermittler'], true)) $rolle = 'betreiber';

$db = Database::getInstance();
$vorhanden = $db->queryValue(
    "SELECT id FROM lam_domain_anbieter WHERE domain_id = ? AND anbieter_id = ?",
    [$domainId, $anbieterId]
);
if ($vorhanden) {
    $db->execute("UPDATE lam_domain_anbieter SET rolle = ? WHERE id = ?", [$rolle, $vorhanden]);
} else {
    // ULID inline (gleiches Schema wie LamService::ulid)
    $time = (int) (microtime(true) * 1000);
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $id = '';
    for ($i = 9; $i >= 0; $i--) { $id = $alphabet[$time % 32] . $id; $time = intdiv($time, 32); }
    for ($i = 0; $i < 16; $i++) $id .= $alphabet[random_int(0, 31)];
    $db->execute(
        "INSERT INTO lam_domain_anbieter (id, domain_id, anbieter_id, rolle) VALUES (?, ?, ?, ?)",
        [$id, $domainId, $anbieterId, $rolle]
    );
}

// Globalen Flag am Anbieter setzen
if ($rolle === 'betreiber') {
    $db->execute("UPDATE lam_anbieter SET ist_betreiber = 1 WHERE id = ?", [$anbieterId]);
} else {
    $db->execute("UPDATE lam_anbieter SET ist_vermittler = 1 WHERE id = ?", [$anbieterId]);
}

// Wenn Domain noch keinen direkten anbieter_id hat: setzen
$hatAnbieter = $db->queryValue("SELECT anbieter_id FROM lam_domains WHERE id = ?", [$domainId]);
if (!$hatAnbieter) {
    $db->execute("UPDATE lam_domains SET anbieter_id = ? WHERE id = ?", [$anbieterId, $domainId]);
}

Response::success(['ok' => true]);
