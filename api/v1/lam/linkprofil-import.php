<?php
/**
 * CSV-Import für Linkprofil-Verlinkungen.
 *
 * POST /api/v1/lam/linkprofil-import   (multipart/form-data)
 *   - customer_id (Pflicht)
 *   - csv (File) ODER csv_text (String)
 *   - quelle (optional, z.B. 'sistrix', 'ahrefs', 'xovi', 'gsc')
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$customerId = (int)($_POST['customer_id'] ?? $_REQUEST['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('customer_id erforderlich');

$csvContent = null;
if (!empty($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['csv']['size'] > 20 * 1024 * 1024) Response::error('Datei zu groß (max 20 MB)');
    $csvContent = file_get_contents($_FILES['csv']['tmp_name']);
} elseif (!empty($_POST['csv_text'])) {
    $csvContent = (string)$_POST['csv_text'];
}
if (!$csvContent) Response::error('Keine CSV-Datei oder csv_text mitgesendet');

$quelle = trim((string)($_POST['quelle'] ?? ''));

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $r = $svc->importiereLinkprofilCsv($customerId, $csvContent, $quelle);
    // Snapshot erzeugen + Diff berechnen (best-effort)
    try {
        $snap = $svc->erzeugeLinkprofilSnapshot((string)$customerId);
        $r['snapshot_id'] = $snap['snapshot_id'];
        $r['neu_count'] = $snap['neu_count'];
        $r['verschwunden_count'] = $snap['verschwunden_count'];
    } catch (\Throwable $eSnap) {
        $r['snapshot_error'] = $eSnap->getMessage();
    }
    Response::success($r,
        "Import OK: {$r['neu']} neu, {$r['doppelt']} doppelt, {$r['gesamt']} Zeilen (Format: {$r['format']})");
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 400);
}
