<?php
/**
 * POST /crm/firmen/{id}/fetch-favicon
 *   → lädt das beste Favicon der hinterlegten Website und speichert es als Logo
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\FaviconService;
use Services\CrmKnowledgeSyncQueue;

if (!Auth::can('crm')) Response::error('Kein Zugriff', 403);

$db = Database::getInstance();
$fid = (int) ($_GET['firma_id'] ?? 0);
if (!$fid) Response::error('Ungültige Firma-ID', 400);

$firma = $db->queryOne("SELECT id, firmenname, website, logo_path FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$fid]);
if (!$firma) Response::error('Firma nicht gefunden', 404);
if (empty($firma['website'])) Response::error('Keine Website hinterlegt', 400);

$destDir = ROOT_PATH . '/uploads/crm/firmenlogos';
if (!is_dir($destDir)) {
    @mkdir($destDir, 0755, true);
    @chown($destDir, 'www-data');
}

$svc = new FaviconService();
try {
    // FaviconService nutzt einen Slug — bei der Firma als Dateiprefix: firma_<id>
    $res = $svc->fetchAndSave($firma['website'], $destDir, 'firma_' . $fid);
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 422);
}

if (!empty($firma['logo_path'])) {
    $old = $destDir . '/' . basename($firma['logo_path']);
    if (file_exists($old)) @unlink($old);
}

$db->execute("UPDATE crm_firmen SET logo_path = ? WHERE id = ?", [$res['filename'], $fid]);
CrmKnowledgeSyncQueue::enqueueFirma($fid);

Response::success([
    'logo_url' => '/uploads/crm/firmenlogos/' . $res['filename'],
    'width' => $res['width'] ?? null,
    'height' => $res['height'] ?? null,
    'source_url' => $res['source_url'] ?? null,
], 'Favicon übernommen');
