<?php
/** Kundenportal-Chat: Anhang herunterladen. Tenant-geschuetzt + Path-Traversal-sicher. */
use Core\Response;

require __DIR__ . '/_resolve.php'; // $db, $svc, $customerId

$id = (int) ($_GET['id'] ?? 0);
if (!$id) Response::error('Kein Anhang');
$att = $svc->attachment($id);
if (!$att || (int)$att['customer_id'] !== (int)$customerId) Response::forbidden('Anhang nicht zugänglich');

// stored_name ist serverseitig generiert (hex + ext); basename als zusaetzlicher Schutz
$path = UPLOADS_PATH . '/portal/' . (int)$customerId . '/' . basename((string)$att['stored_name']);
if (!is_file($path)) Response::notFound('Datei nicht gefunden');

$mime = $att['mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . rawurlencode($att['original_name']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
