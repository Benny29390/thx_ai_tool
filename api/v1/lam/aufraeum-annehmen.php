<?php
/**
 * Nimmt einen Aufraeum-Vorschlag fuer eine Domain an.
 *
 * POST /api/v1/lam/aufraeum-annehmen
 * Body: {
 *   customer_id: int,
 *   domain: string,
 *   linkart: string,
 *   strategie: 'alle_behalten'|'reduktion_auf_1'|'reduktion_auf_2',
 *   behalten_ids: int[],
 *   manuell_geaendert: bool  // true wenn User die KI-Vorauswahl angefasst hat
 * }
 *
 * Bei manuell_geaendert=true wird die Wissensbasis mit confidence='manuell'
 * gepflegt — kuenftige Imports dieser Domain bekommen den Vorschlag automatisch.
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);

$customerId = (int)($json['customer_id'] ?? 0);
$domain     = trim((string)($json['domain'] ?? ''));
$linkart    = (string)($json['linkart'] ?? '');
$strategie  = (string)($json['strategie'] ?? 'alle_behalten');
$behalten   = $json['behalten_ids'] ?? [];
$manuell    = !empty($json['manuell_geaendert']);
$urlStrat   = $json['url_strategie'] ?? null;
$mergeLt    = !isset($json['merge_linktext']) || !empty($json['merge_linktext']);
$empfehlung     = isset($json['empfehlung']) && $json['empfehlung'] !== '' ? (string)$json['empfehlung'] : null;
$zielUrl        = isset($json['ziel_url'])   && $json['ziel_url']   !== '' ? (string)$json['ziel_url']   : null;
$auchBestaetigt = !empty($json['auch_bestaetigt']);

if ($customerId <= 0 || $domain === '' || $linkart === '') {
    Response::error('customer_id, domain und linkart erforderlich', 400);
}
if (!is_array($behalten)) Response::error('behalten_ids muss Array sein', 400);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try {
    Response::success($svc->nehmeVorschlagAn(
        $customerId, $domain, $linkart, $strategie, $behalten, $manuell, Auth::id(),
        $urlStrat, $mergeLt, $empfehlung, $zielUrl, $auchBestaetigt
    ));
} catch (\Throwable $e) {
    Response::error('Annehmen fehlgeschlagen: ' . $e->getMessage(), 500);
}
