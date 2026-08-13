<?php
/**
 * POST /lam/xlsx-parse  (multipart/form-data)
 * Field: xlsx (File, max 10 MB)
 * Returns: { spalten: [{name, beispiel}], roh: [[zelle, ...]] }
 */
use Core\Auth;
use Core\Response;
use Services\XlsxReader;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

if (empty($_FILES['xlsx']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) {
    Response::error('Datei fehlt', 400);
}
if ($_FILES['xlsx']['size'] > 10 * 1024 * 1024) Response::error('Datei zu groß (max 10 MB)', 400);

require_once SERVICES_PATH . '/XlsxReader.php';

try {
    $zeilen = XlsxReader::leseZeilen($_FILES['xlsx']['tmp_name']);
    if (empty($zeilen)) Response::error('Datei enthält keine Daten.', 400);
    $header = $zeilen[0];
    $roh = array_slice($zeilen, 1, 5000);
    $spalten = [];
    foreach ($header as $idx => $name) {
        $spalten[] = [
            'name' => trim((string)$name),
            'beispiel' => isset($roh[0][$idx]) ? trim((string)$roh[0][$idx]) : '',
        ];
    }
    Response::success(['spalten' => $spalten, 'roh' => $roh]);
} catch (\Throwable $e) {
    Response::error('XLSX-Parsing fehlgeschlagen: ' . $e->getMessage(), 500);
}
