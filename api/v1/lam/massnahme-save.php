<?php
/**
 * Maßnahme anlegen oder bearbeiten.
 *
 * POST /api/v1/lam/massnahme-save
 * Body: { id?, customer_id, domain_id, status, vorgangstyp, buchungstyp?, linktext?, ... }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = isset($input['id']) ? trim((string)$input['id']) : null;
if ($id === '') $id = null;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $neueId = $svc->speichereMassnahme($id, $input);
    Response::success(['id' => $neueId], $id ? 'Maßnahme aktualisiert' : 'Maßnahme angelegt');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 400);
}
