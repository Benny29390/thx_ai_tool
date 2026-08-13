<?php
/** GET /lam/auslagen-excel?customer_id=X&jahr=2026&monat=5 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$customerId = (int)($_GET['customer_id'] ?? 0);
$jahr = (int)($_GET['jahr'] ?? date('Y'));
$monat = !empty($_GET['monat']) ? (int)$_GET['monat'] : null;
$quartal = !empty($_GET['quartal']) ? (int)$_GET['quartal'] : null; // legacy
if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$tmpFile = tempnam(sys_get_temp_dir(), 'lam_au_') . '.xlsx';
try {
    $r = $svc->exportiereQuartalsAuslagenExcel($customerId, $jahr, $quartal, $tmpFile);
    $zeitTag = $jahr . ($quartal ? '_Q' . $quartal : '');
    $dateiName = 'auslagen_' . preg_replace('/[^a-z0-9]+/i', '_', $r['kunde'] ?: 'kunde_' . $customerId) . '_' . $zeitTag . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dateiName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
} catch (\Throwable $e) {
    if (file_exists($tmpFile)) unlink($tmpFile);
    Response::error('Export fehlgeschlagen: ' . $e->getMessage(), 500);
}
