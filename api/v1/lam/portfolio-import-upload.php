<?php
/**
 * POST /api/v1/lam/portfolio-import/upload  (multipart/form-data)
 *   Fields: files[] (1..N Dateien, EML/XLSX/CSV)
 * Liefert: { batch_id }
 *
 * Speichert die Dateien physisch ab und legt einen lam_import_batches-Eintrag an.
 * Analyse passiert per separatem GET /portfolio-import/{batch_id}/analyse.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PortfolioImportService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

if (empty($_FILES['files'])) Response::error('Keine Dateien hochgeladen');

// Normalize: multi-file Upload kommt als parallele Arrays
$raw = $_FILES['files'];
$files = [];
if (is_array($raw['name'])) {
    for ($i = 0; $i < count($raw['name']); $i++) {
        $files[] = [
            'name' => $raw['name'][$i],
            'tmp_name' => $raw['tmp_name'][$i],
            'error' => $raw['error'][$i],
            'size' => $raw['size'][$i],
            'type' => $raw['type'][$i] ?? '',
        ];
    }
} else {
    $files[] = $raw;
}

require_once SERVICES_PATH . '/PortfolioImportService.php';
$svc = new PortfolioImportService(Database::getInstance());
$user = Auth::user();

try {
    $batchId = $svc->speichereDateien($files, (int)($user['id'] ?? 0));
    Response::success(['batch_id' => $batchId], count($files) . ' Datei(en) hochgeladen');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 400);
}
