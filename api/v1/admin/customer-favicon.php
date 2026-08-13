<?php
/**
 * POST /admin/customers/{id}/fetch-favicon
 *   → lädt das beste Favicon der hinterlegten Website und speichert es als Logo
 *
 * POST /admin/customers/bulk-fetch-favicons
 *   → wenn customer_id leer / 0: holt für alle Kunden mit Website ohne Logo
 *     (oder nur die, die in body[]'ids' stehen). Returnt Übersicht.
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\FaviconService;

if (!Auth::isAdmin()) Response::error('Kein Zugriff', 403);

$db = Database::getInstance();
$cid = (int) ($_GET['customer_id'] ?? 0);
$bulk = !empty($_GET['bulk']);
$svc = new FaviconService();
$destDir = ROOT_PATH . '/uploads/customers/logos';

if ($bulk) {
    $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    $onlyIds = isset($body['ids']) && is_array($body['ids']) ? array_map('intval', $body['ids']) : null;

    $where = "website IS NOT NULL AND website <> '' AND (logo_path IS NULL OR logo_path = '')";
    $params = [];
    if ($onlyIds) {
        $place = implode(',', array_fill(0, count($onlyIds), '?'));
        $where = "website IS NOT NULL AND website <> '' AND id IN ($place)";
        $params = $onlyIds;
    }
    $customers = $db->query("SELECT id, slug, name, website, logo_path FROM customers WHERE $where ORDER BY id", $params);

    $results = ['ok' => [], 'fail' => []];
    foreach ($customers as $cust) {
        try {
            $res = $svc->fetchAndSave($cust['website'], $destDir, $cust['slug']);
            if (!empty($cust['logo_path'])) {
                $old = $destDir . '/' . basename($cust['logo_path']);
                if (file_exists($old)) @unlink($old);
            }
            $db->execute("UPDATE customers SET logo_path = ? WHERE id = ?", [$res['filename'], $cust['id']]);
            $results['ok'][] = [
                'id' => (int) $cust['id'],
                'name' => $cust['name'],
                'logo_url' => '/uploads/customers/logos/' . $res['filename'],
                'width' => $res['width'],
                'height' => $res['height'],
                'source_url' => $res['source_url'],
            ];
        } catch (\Throwable $e) {
            $results['fail'][] = [
                'id' => (int) $cust['id'],
                'name' => $cust['name'],
                'error' => $e->getMessage(),
            ];
        }
    }
    Response::success($results, 'Bulk-Fetch abgeschlossen: ' . count($results['ok']) . ' erfolgreich, ' . count($results['fail']) . ' fehlgeschlagen');
}

if (!$cid) Response::error('Ungültige Kunden-ID', 400);

$customer = $db->queryOne("SELECT id, slug, name, website, logo_path FROM customers WHERE id = ?", [$cid]);
if (!$customer) Response::error('Kunde nicht gefunden', 404);
if (empty($customer['website'])) Response::error('Kein Website-URL hinterlegt', 400);

try {
    $res = $svc->fetchAndSave($customer['website'], $destDir, $customer['slug']);
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 422);
}

if (!empty($customer['logo_path'])) {
    $old = $destDir . '/' . basename($customer['logo_path']);
    if (file_exists($old)) @unlink($old);
}

$db->execute("UPDATE customers SET logo_path = ? WHERE id = ?", [$res['filename'], $cid]);

Response::success([
    'logo_url' => '/uploads/customers/logos/' . $res['filename'],
    'width' => $res['width'],
    'height' => $res['height'],
    'source_url' => $res['source_url'],
    'mime' => $res['mime'],
], 'Favicon übernommen');
