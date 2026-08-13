<?php
/**
 * POST /api/v1/lam/anbieter-domain-entfernen
 * Body: { domain_id, anbieter_id, rolle?: 'betreiber'|'vermittler' }
 * Entfernt die Verknüpfung Anbieter-Domain. Wenn rolle angegeben, wird nur dieses
 * Flag entfernt — der Anbieter bleibt für die andere Rolle verknüpft.
 * Wenn nach dem Entfernen beide Flags 0 sind, wird die Junction-Zeile gelöscht.
 */
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$domainId = trim((string) ($input['domain_id'] ?? ''));
$anbieterId = trim((string) ($input['anbieter_id'] ?? ''));
$rolle = $input['rolle'] ?? '';
if ($domainId === '' || $anbieterId === '') Response::error('domain_id + anbieter_id erforderlich', 400);

$db = Database::getInstance();
$junction = $db->queryOne(
    "SELECT id, ist_betreiber, ist_vermittler FROM lam_domain_anbieter WHERE domain_id = ? AND anbieter_id = ?",
    [$domainId, $anbieterId]
);
if (!$junction) Response::success(['ok' => true, 'noop' => true]);

if ($rolle === 'betreiber' && $junction['ist_vermittler']) {
    // Nur Betreiber-Flag entfernen, Vermittler bleibt
    $db->execute("UPDATE lam_domain_anbieter SET ist_betreiber = 0, rolle = 'vermittler' WHERE id = ?", [$junction['id']]);
} elseif ($rolle === 'vermittler' && $junction['ist_betreiber']) {
    $db->execute("UPDATE lam_domain_anbieter SET ist_vermittler = 0, rolle = 'betreiber' WHERE id = ?", [$junction['id']]);
} else {
    // Komplett entfernen (keine andere Rolle übrig oder keine spezifische Rolle angegeben)
    $db->execute("DELETE FROM lam_domain_anbieter WHERE id = ?", [$junction['id']]);
    // Wenn das der direkte Anbieter auf der Domain war: auch dort leeren
    $direkt = $db->queryValue("SELECT anbieter_id FROM lam_domains WHERE id = ?", [$domainId]);
    if ($direkt === $anbieterId) {
        $db->execute("UPDATE lam_domains SET anbieter_id = NULL WHERE id = ?", [$domainId]);
    }
}

Response::success(['ok' => true]);
