<?php
/**
 * Projektplanner — Plan-Zeilen API
 *
 * POST   /admin/projektplanner/plans/{plan_id}/rows                Neue Zeile (write)
 * PUT    /admin/projektplanner/plans/{plan_id}/rows/{row_id}       Auto-Save einer Zeile (edit für Whitelist-Felder, write für alles)
 * DELETE /admin/projektplanner/plans/{plan_id}/rows/{row_id}       Zeile löschen (write)
 * POST   /admin/projektplanner/plans/{plan_id}/rows/reorder        Reihenfolge speichern (write)
 * POST   /admin/projektplanner/plans/{plan_id}/rows/{row_id}/move  In anderen Plan verschieben (write auf Quelle UND Ziel)
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\ProjektplannerService;

require_once SERVICES_PATH . '/ProjektplannerService.php';
require __DIR__ . '/_pp_perm.php';

$svc = new ProjektplannerService(Database::getInstance());

$method = $_SERVER['REQUEST_METHOD'];
$planId = (int) ($_GET['plan_id'] ?? 0);
$rowId  = (int) ($_GET['row_id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$planId) Response::error('plan_id fehlt');

if ($action === 'reorder' && $method === 'POST') {
    pp_require($planId, 'write');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $order = $payload['order'] ?? [];
    if (!is_array($order)) Response::error('order muss Array sein');
    $svc->reorderRows($planId, $order);
    Response::success(['count' => count($order)], 'Reihenfolge gespeichert');
}

if ($rowId > 0 && $action === 'move' && $method === 'POST') {
    pp_require($planId, 'write');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $target = (int) ($payload['target_plan_id'] ?? 0);
    $pos = (int) ($payload['position'] ?? 0);
    if (!$target) Response::error('target_plan_id fehlt');
    pp_require($target, 'write');
    $svc->moveRowToPlan($rowId, $target, $pos);
    Response::success(['id' => $rowId, 'plan_id' => $target], 'Verschoben');
}

if ($rowId > 0 && $method === 'PUT') {
    $perm = pp_require($planId, 'edit');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    // Wenn nur 'edit', auf Whitelist-Felder beschränken
    if (in_array($perm, ['edit'], true)) {
        $payload = pp_filter_edit_fields($payload);
        if (empty($payload)) Response::error('Keine erlaubten Felder im Request');
    }
    $svc->updateRow($rowId, $payload);
    Response::success(['id' => $rowId], 'Gespeichert');
}

if ($rowId > 0 && $method === 'DELETE') {
    pp_require($planId, 'write');
    $svc->deleteRow($rowId);
    Response::success(['id' => $rowId], 'Zeile gelöscht');
}

if ($method === 'POST') {
    pp_require($planId, 'write');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $newId = $svc->addRow($planId, $payload);
    Response::success(['id' => $newId], 'Zeile hinzugefügt');
}

Response::error('Methode nicht unterstützt', 405);
