<?php
/**
 * Projektplanner — Import API
 *
 * POST /admin/projektplanner/import          JSON-Upload + Import durchführen
 * POST /admin/projektplanner/import/preview  Nur Preview, kein DB-Write
 *
 * Erwartet entweder:
 *  - multipart/form-data mit Feld "json_file" (Datei-Upload), ODER
 *  - application/json mit kompletten JSON-Daten im Body
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PpImportService;

if (!Auth::isAdmin()) Response::forbidden();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if ($method !== 'POST') Response::error('Nur POST', 405);

require_once SERVICES_PATH . '/PpImportService.php';
$svc = new PpImportService(Database::getInstance());
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

// Reset: alle Plan-Daten löschen (Kunden, Team, Settings bleiben!)
if ($action === 'reset') {
    $db = Database::getInstance();
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $also_budgets = !empty($payload['also_customer_budgets']);
    $confirm = trim((string) ($payload['confirm'] ?? ''));
    if ($confirm !== 'PROJEKTPLANNER LEEREN') {
        Response::error('Confirm-Text falsch — bitte exakt „PROJEKTPLANNER LEEREN" eingeben');
    }
    $db->beginTransaction();
    try {
        $stats = [];
        // Reihenfolge wegen Foreign Keys
        $stats['pp_plan_feedback']   = $db->execute("DELETE FROM pp_plan_feedback");
        $stats['pp_plan_revisions']  = $db->execute("DELETE FROM pp_plan_revisions");
        $stats['pp_plan_shares']     = $db->execute("DELETE FROM pp_plan_shares");
        $stats['pp_plan_budget']     = $db->execute("DELETE FROM pp_plan_budget");
        $stats['pp_plan_rows']       = $db->execute("DELETE FROM pp_plan_rows");
        $stats['pp_plans']           = $db->execute("DELETE FROM pp_plans");
        $stats['pp_person_shares']   = $db->execute("DELETE FROM pp_person_shares");
        if ($also_budgets) {
            $stats['pp_customer_budget'] = $db->execute("DELETE FROM pp_customer_budget");
        }
        $db->commit();
        Response::success(['deleted' => $stats], 'Projektplanner-Daten geleert');
    } catch (\Throwable $e) {
        $db->rollBack();
        Response::error('Reset fehlgeschlagen: ' . $e->getMessage());
    }
}

// Daten holen — Datei-Upload bevorzugt, sonst JSON-Body
$data = null;
if (!empty($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['json_file']['size'] > 100 * 1024 * 1024) Response::error('Datei zu groß (max 100 MB)');
    $raw = @file_get_contents($_FILES['json_file']['tmp_name']);
    $data = $raw ? json_decode($raw, true) : null;
} else {
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : null;
}

if (!is_array($data)) Response::error('Konnte JSON nicht parsen');

try {
    if ($action === 'preview') {
        $diff = $svc->preview($data);
        Response::success(['preview' => $diff]);
    }
    $options = [
        'skip_unmapped' => !empty($_GET['skip_unmapped']) || !empty($data['_skip_unmapped']),
        'customer_mapping' => is_array($data['_customer_mapping'] ?? null) ? $data['_customer_mapping'] : [],
    ];
    unset($data['_skip_unmapped'], $data['_customer_mapping']);
    $result = $svc->importFromJson($data, $userId, $options);
    Response::success($result, 'Import abgeschlossen');
} catch (\Throwable $e) {
    Response::error($e->getMessage());
}
