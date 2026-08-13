<?php
/**
 * POST /mail/lam-kondition-anlegen
 * Body: { mail_id, domain_id, buchungstyp?, preis?, link_typ?, notiz? }
 *
 * Legt eine Kondition zu einer Domain an, basierend auf den extrahierten
 * Feldern der Mail-KI-Klassifikation. Mensch bestätigt den Drawer.
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$mailId = (int)($input['mail_id'] ?? 0);
$domainId = trim((string)($input['domain_id'] ?? ''));
$buchungstyp = trim((string)($input['buchungstyp'] ?? 'gastartikel'));
$preis = isset($input['preis']) && $input['preis'] !== '' ? (float)$input['preis'] : null;
$linkTyp = trim((string)($input['link_typ'] ?? 'follow'));
$notiz = trim((string)($input['notiz'] ?? ''));

if ($mailId <= 0 || $domainId === '') Response::error('mail_id + domain_id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$db = Database::getInstance();
$lamSvc = new \Services\LamService($db);

// Anbieter über mail_lam_verknuepfung suchen (falls dort verknüpft)
$anbieterId = $db->queryValue(
    "SELECT ziel_id FROM mail_lam_verknuepfung WHERE mail_id = ? AND typ = 'anbieter' LIMIT 1",
    [$mailId]
);

// Kondition anlegen
$konditionId = $lamSvc->ulid();
$db->execute(
    "INSERT INTO lam_konditionen
        (id, domain_id, via_anbieter_id, buchungstyp, preis, link_typ, notiz, verifikation_status, erstellt_am)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'neu', NOW())",
    [$konditionId, $domainId, $anbieterId, $buchungstyp, $preis, $linkTyp, $notiz ?: ('Aus Mail #' . $mailId)]
);

// Verknüpfung in mail_lam_verknuepfung
$db->execute(
    "INSERT INTO mail_lam_verknuepfung (mail_id, typ, ziel_id, automatisch) VALUES (?, 'kondition', ?, 0)",
    [$mailId, $konditionId]
);

Response::success([
    'kondition_id' => $konditionId,
    'anbieter_id' => $anbieterId,
    'meldung' => 'Kondition angelegt für Domain ' . substr($domainId, 0, 8) . '…',
]);
