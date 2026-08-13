<?php
/**
 * POST /lam/ki-linkart
 * Body: { ids: [domain_id, ...], ueberschreiben?: bool }
 *
 * Ordnet Domains per KI eine Linkart aus dem BESTEHENDEN Vokabular zu (17 Werte).
 * Legt bewusst keine neuen Kategorien an — damit bleibt die Linkart-Filterleiste
 * projektuebergreifend nutzbar. Standardmaessig werden nur Domains ohne Linkart gefuellt.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben — der KI-Call dauert, soll aber nicht die ganze UI blockieren.
Session::release();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = $input['ids'] ?? [];
if (!is_array($ids) || empty($ids)) Response::error('ids erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $r = $svc->kiKlassifiziereLinkart($ids, !empty($input['ueberschreiben']));
    Response::success($r, "{$r['gesetzt']} Linkarten gesetzt"
        . ($r['uebersprungen'] > 0 ? ", {$r['uebersprungen']} übersprungen (bereits gesetzt)" : ''));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('KI-Linkart fehlgeschlagen: ' . $e->getMessage(), 500);
}
