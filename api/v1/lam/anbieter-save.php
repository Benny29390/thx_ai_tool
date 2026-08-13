<?php
/**
 * LAM Anbieter anlegen oder aktualisieren
 * POST /lam/anbieter-save
 * Body: { id?, name, firma?, rolle, beziehungsstatus, notizen? }
 * Response: { id, neu: bool }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id = trim((string)($input['id'] ?? '')) ?: null;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $resultId = $svc->speichereAnbieter($id, $input);
    Response::success(['id' => $resultId, 'neu' => $id === null]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Exception $e) {
    Response::error('Speichern fehlgeschlagen', 500);
}
