<?php
/**
 * POST /lam/domain-wissen-anwenden
 * Body: { domain }
 * Rollt die in lam_domain_wissen gespeicherte linkart + empfehlung_default
 * auf alle Verlinkungen dieser Domain aus.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben fuer Parallel-Arbeit.
Session::release();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$domain = trim((string)($input['domain'] ?? ''));
if ($domain === '') Response::error('domain erforderlich', 400);
$force = !empty($input['force']);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->wendeDomainWissenAn($domain, $force));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 404);
}
