<?php
/**
 * POST /api/v1/lam/domain-anbieter-flags
 * Body: { junction_id, flag: 'betreiber'|'vermittler', wert: 0|1 }
 * Setzt eine der beiden Rollen-Flags an einer Junction. Mehrfach-Belegung möglich.
 */
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$jid = trim((string) ($input['junction_id'] ?? ''));
$flag = $input['flag'] ?? '';
$wert = (int) ($input['wert'] ?? 0) ? 1 : 0;
if ($jid === '') Response::error('junction_id erforderlich', 400);
if (!in_array($flag, ['betreiber', 'vermittler'], true)) Response::error('Ungültiges Flag', 400);

$db = Database::getInstance();
$spalte = $flag === 'betreiber' ? 'ist_betreiber' : 'ist_vermittler';
$db->execute("UPDATE lam_domain_anbieter SET $spalte = ? WHERE id = ?", [$wert, $jid]);

// Rolle-Spalte (Legacy) konsistent halten
$db->execute("UPDATE lam_domain_anbieter SET rolle = CASE
    WHEN ist_betreiber = 1 AND ist_vermittler = 1 THEN 'betreiber'
    WHEN ist_vermittler = 1 THEN 'vermittler'
    ELSE 'betreiber'
END WHERE id = ?", [$jid]);

// Globalen Anbieter-Flag mitziehen (OR-Logik: einmal gesetzt, immer dabei)
if ($wert === 1) {
    $anbieterId = $db->queryValue("SELECT anbieter_id FROM lam_domain_anbieter WHERE id = ?", [$jid]);
    if ($anbieterId) {
        $db->execute("UPDATE lam_anbieter SET $spalte = 1 WHERE id = ?", [$anbieterId]);
    }
}

Response::success(['ok' => true]);
