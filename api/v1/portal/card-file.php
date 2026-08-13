<?php
/** Kundenportal: Datei aus einer freigegebenen Dokument-/Bild-Kachel herunterladen. Tenant- + Sichtbarkeits-geschuetzt. */
use Core\Response;

require __DIR__ . '/_resolve.php'; // $db, $svc, $customerId

$id = (int) ($_GET['id'] ?? 0);
if (!$id) Response::error('Keine Datei');
$f = $svc->cardFile($id);
if (!$f || (int)$f['customer_id'] !== (int)$customerId) Response::forbidden('Datei nicht zugänglich');
if ((int)$f['customer_visible'] !== 1) Response::forbidden('Diese Kachel ist nicht freigegeben');

// Karten-Dateien liegen unter /var/www/uploads/customers/{id}/cards/...
$real = realpath($f['file_path']);
// Sicherheit: Datei muss im Upload-Verzeichnis genau DIESES Kunden liegen (Tenant-Pfad-Check)
$custRoot = realpath(ROOT_PATH . '/uploads/customers/' . (int)$customerId);
if (!$real || !$custRoot || strpos($real, $custRoot) !== 0 || !is_file($real)) Response::notFound('Datei nicht gefunden');

header('Content-Type: ' . ($f['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . rawurlencode($f['file_name']) . '"');
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
readfile($real);
exit;
