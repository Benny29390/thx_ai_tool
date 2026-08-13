<?php
/**
 * /api/v1/crm/kontakte
 *   GET:  Liste mit Filter+Pagination
 *   POST: Anlegen
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmKontaktService.php';
$svc = new \Services\CrmKontaktService($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Multi-Select-Felder akzeptieren BEIDES: Single (status=lead) und Array (status[]=lead&status[]=kunde)
    $filter = [
        'suche'           => $_GET['suche'] ?? null,
        'kontakt_status'  => $_GET['kontakt_status'] ?? null,
        'opt_in_status'   => $_GET['opt_in_status'] ?? null,
        'firma_id'        => $_GET['firma_id'] ?? null,
        'tag_ids'         => $_GET['tag_ids'] ?? null,
        'tag_modus'       => $_GET['tag_modus'] ?? 'oder',
        'ohne_tag_ids'    => $_GET['ohne_tag_ids'] ?? null,
        'listen_ids'      => $_GET['listen_ids'] ?? null,
        'tag_id'          => $_GET['tag_id'] ?? null,
        'ohne_tag_id'     => $_GET['ohne_tag_id'] ?? null,
        'listen_id'       => $_GET['listen_id'] ?? null,
        'ohne_firma'      => !empty($_GET['ohne_firma']),
        'mit_zoho_legacy' => !empty($_GET['mit_zoho_legacy']),
        'sort'            => $_GET['sort'] ?? null,
        'order'           => $_GET['order'] ?? null,
        'limit'           => $_GET['limit'] ?? 50,
        'offset'          => $_GET['offset'] ?? 0,
    ];
    Response::success($svc->liste(array_filter($filter, fn($v) => $v !== null && $v !== '' && $v !== [])));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $id = $svc->anlegen($input, Auth::id());
        Response::success(['id' => $id], 'Kontakt angelegt');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

Response::error('Methode nicht erlaubt', 405);
