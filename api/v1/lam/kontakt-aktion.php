<?php
/**
 * Kontakt-Aktionen: loeschen, primaer setzen
 * POST /lam/kontakt-aktion
 * Body: { id, aktion: 'loeschen' | 'primaer_setzen' }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id     = trim((string)($input['id'] ?? ''));
$aktion = trim((string)($input['aktion'] ?? ''));

if ($id === '' || $aktion === '') Response::error('id und aktion erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    if ($aktion === 'loeschen') {
        $svc->loescheKontakt($id);
    } elseif ($aktion === 'primaer_setzen') {
        $svc->setzePrimaerKontakt($id);
    } else {
        Response::error('Unbekannte Aktion', 400);
    }
    Response::success(['ok' => true]);
} catch (\Exception $e) {
    Response::error('Aktion fehlgeschlagen', 500);
}
