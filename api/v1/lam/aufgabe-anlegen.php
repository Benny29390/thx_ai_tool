<?php
/**
 * POST /lam/aufgabe-anlegen
 * Body: { typ, bezug_typ, bezug_id, titel, beschreibung?, faellig_am?, zustaendig_user_id? }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$typ = trim((string)($input['typ'] ?? ''));
$bezugTyp = trim((string)($input['bezug_typ'] ?? ''));
$bezugId = trim((string)($input['bezug_id'] ?? ''));
$titel = trim((string)($input['titel'] ?? ''));
if ($typ === '' || $bezugTyp === '' || $bezugId === '' || $titel === '') {
    Response::error('typ, bezug_typ, bezug_id, titel erforderlich', 400);
}

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $userInfo = Auth::user();
    $id = $svc->legeAufgabeAn(
        $typ, $bezugTyp, $bezugId, $titel,
        $input['beschreibung'] ?? null,
        $input['faellig_am'] ?? null,
        !empty($input['zustaendig_user_id']) ? (int)$input['zustaendig_user_id'] : null,
        $userInfo['id'] ?? null
    );
    Response::success(['id' => $id]);
} catch (\Throwable $e) {
    Response::error('Anlegen fehlgeschlagen: ' . $e->getMessage(), 500);
}
