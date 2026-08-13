<?php
/**
 * POST /lam/domain-wissen-save
 * Body: { domain, linkart?, reduktionsstrategie?, notiz?, empfehlung_default?,
 *         branche?, thema?, tonalitaet?, risikofaktoren? }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->speichereDomainWissen($input));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Speichern fehlgeschlagen', 500);
}
