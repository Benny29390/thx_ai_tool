<?php
/**
 * POST /lam/aufgabe-aktualisieren
 * Body: { id, titel?, beschreibung?, status?, faellig_am?, zustaendig_user_id? }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id = trim((string)($input['id'] ?? ''));
if ($id === '') Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $svc->aktualisiereAufgabe($id, $input);
    Response::success(['ok' => true]);
} catch (\Throwable $e) {
    Response::error('Aktualisieren fehlgeschlagen', 500);
}
