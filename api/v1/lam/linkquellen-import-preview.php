<?php
/**
 * POST /api/v1/lam/linkquellen-import-preview  (multipart/form-data)
 * Field: file (XLSX/CSV)
 * Liest die hochgeladene Datei und liefert die erkannten Linkquellen-Kandidaten
 * (mit Spalten-Detektion, Themengebiet, Notiz, SI/DP) inkl. Dubletten-Markierung.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
    Response::error('Keine Datei hochgeladen', 400);
}

$name = $_FILES['file']['name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xlsm', 'csv'], true)) {
    Response::error('Nur XLSX/CSV erlaubt', 400);
}
if (($_FILES['file']['size'] ?? 0) > 10 * 1024 * 1024) {
    Response::error('Max. 10 MB', 400);
}

// In Session-Verzeichnis ablegen, damit Commit-Schritt dieselbe Datei nutzen kann
$dir = sys_get_temp_dir() . '/lam_lq_import';
@mkdir($dir, 0775, true);
$token = bin2hex(random_bytes(8));
$pfad = $dir . '/' . $token . '.' . $ext;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $pfad)) {
    Response::error('Datei konnte nicht gespeichert werden', 500);
}

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $r = $svc->leseLinkquellenKandidaten($pfad);
    $stats = [
        'gesamt' => count($r['kandidaten']),
        'neu'    => count(array_filter($r['kandidaten'], fn($k) => empty($k['existiert']))),
        'dubletten' => count(array_filter($r['kandidaten'], fn($k) => !empty($k['existiert']))),
    ];
    Response::success([
        'token'      => $token,
        'datei'      => $name,
        'spalten'    => $r['spalten'],
        'kandidaten' => $r['kandidaten'],
        'stats'      => $stats,
        'fehler'     => $r['fehler'] ?? null,
    ]);
} catch (\Throwable $e) {
    @unlink($pfad);
    Response::error('Verarbeitung fehlgeschlagen: ' . $e->getMessage(), 500);
}
