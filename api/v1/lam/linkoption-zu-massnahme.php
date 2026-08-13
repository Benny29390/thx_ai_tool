<?php
/**
 * Konvertiert einen Linkoptionen-Eintrag in eine Maßnahme.
 *
 * POST /api/v1/lam/linkoption-zu-massnahme
 * Body: { id }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = trim((string)($input['id'] ?? ''));
if ($id === '') Response::error('id erforderlich');

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $massnahmeId = $svc->konvertiereLinkoptionZuMassnahme($id);
    Response::success(['massnahme_id' => $massnahmeId], 'Maßnahme angelegt');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 400);
}
