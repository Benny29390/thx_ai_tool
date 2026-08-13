<?php
/** GET /lam/linkprofil-excel?customer_id=X */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$tmpFile = tempnam(sys_get_temp_dir(), 'lam_lp_') . '.xlsx';
try {
    $r = $svc->exportiereLinkprofilExcel($customerId, $tmpFile);
    $dateiName = 'linkprofil_' . preg_replace('/[^a-z0-9]+/i', '_', $r['kunde'] ?: 'kunde_' . $customerId) . '_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $dateiName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
} catch (\Throwable $e) {
    if (file_exists($tmpFile)) unlink($tmpFile);
    Response::error('Export fehlgeschlagen: ' . $e->getMessage(), 500);
}
