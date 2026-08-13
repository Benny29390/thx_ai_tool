<?php
/**
 * Sistrix-Kennzahlen abrufen für eine Domain.
 * POST /lam/sistrix-abruf
 * Body: { domain_id, teile?: ['si','alter','dp'], erzwingen?: bool }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\SistrixService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben fuer Parallel-Arbeit waehrend Sistrix-Abruf.
Session::release();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$domainId = trim((string)($input['domain_id'] ?? ''));
$teile = $input['teile'] ?? null;
$erzwingen = !empty($input['erzwingen']);

if ($domainId === '') Response::error('domain_id erforderlich', 400);

require_once SERVICES_PATH . '/SistrixService.php';
$svc = new SistrixService(Database::getInstance());

try {
    $ergebnis = $svc->holeKennzahlen($domainId, $teile, $erzwingen);
    Response::success($ergebnis);
} catch (\RuntimeException $e) {
    Response::error($e->getMessage(), 503);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Exception $e) {
    Response::error('Sistrix-Aufruf fehlgeschlagen: ' . $e->getMessage(), 500);
}
