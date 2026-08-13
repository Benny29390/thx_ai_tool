<?php
/**
 * Kontakt anlegen oder aktualisieren
 * POST /lam/kontakt-save
 * Body: { id?, anbieter_id, vorname?, nachname, email?, telefon?, rolle? }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id         = trim((string)($input['id'] ?? '')) ?: null;
$anbieterId = trim((string)($input['anbieter_id'] ?? ''));

if (!$id && $anbieterId === '') Response::error('anbieter_id ist beim Anlegen erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $resultId = $svc->speichereKontakt($id, $anbieterId, $input);
    Response::success(['id' => $resultId, 'neu' => $id === null]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Exception $e) {
    Response::error('Speichern fehlgeschlagen', 500);
}
