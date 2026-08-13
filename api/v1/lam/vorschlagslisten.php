<?php
/**
 * Vorschlagslisten-API.
 *
 *   GET  /lam/vorschlagslisten              Liste
 *   GET  /lam/vorschlagsliste-detail?id=X   eine Liste mit Einträgen
 *   POST /lam/vorschlagsliste-save          Body: {id?, customer_id, name, status, zielzahl, notiz}
 *   POST /lam/vorschlagsliste-loeschen      Body: {id}
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$uri = $_SERVER['REQUEST_URI'] ?? '';
$pathOnly = strtok($uri, '?');
$method = $_SERVER['REQUEST_METHOD'];
$input = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: $_POST) : [];

if ($pathOnly === '/api/v1/lam/vorschlagslisten' && $method === 'GET') {
    $filter = [
        'customer_id' => $_GET['customer_id'] ?? null,
        'status'      => $_GET['status'] ?? null,
        'suche'       => $_GET['suche'] ?? null,
    ];
    Response::success($svc->listeVorschlagslisten($filter));
}

if ($pathOnly === '/api/v1/lam/vorschlagsliste-detail' && $method === 'GET') {
    $id = $_GET['id'] ?? '';
    $detail = $svc->getVorschlagsliste($id);
    if (!$detail) Response::notFound('Liste nicht gefunden');
    Response::success($detail);
}

if ($pathOnly === '/api/v1/lam/vorschlagsliste-save' && $method === 'POST') {
    try {
        $neueId = $svc->speichereVorschlagsliste($input['id'] ?? null, $input);
        Response::success(['id' => $neueId], !empty($input['id']) ? 'Liste aktualisiert' : 'Liste angelegt');
    } catch (\Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
}

if ($pathOnly === '/api/v1/lam/vorschlagsliste-loeschen' && $method === 'POST') {
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') Response::error('id erforderlich');
    $svc->loescheVorschlagsliste($id);
    Response::success(null, 'Liste gelöscht');
}

if ($pathOnly === '/api/v1/lam/vorschlagsliste-excel' && $method === 'GET') {
    require __DIR__ . '/vorschlagsliste-excel.php';
    return;
}

if ($pathOnly === '/api/v1/lam/vorschlagsliste-eintrag-add' && $method === 'POST') {
    $listenId = trim((string)($input['vorschlagsliste_id'] ?? ''));
    $domainIds = $input['domain_ids'] ?? [];
    if ($listenId === '' || empty($domainIds) || !is_array($domainIds)) {
        Response::error('vorschlagsliste_id + domain_ids erforderlich', 400);
    }
    $standardwerte = [];
    foreach (['status','notiz','artikelthema'] as $k) {
        if (isset($input[$k])) $standardwerte[$k] = $input[$k];
    }
    try {
        $r = $svc->fuegeDomainsZuVorschlagslisteHinzu($listenId, $domainIds, $standardwerte);
        $msg = $r['added'] . ' Domain(s) hinzugefügt';
        if (!empty($r['skipped'])) $msg .= ', ' . count($r['skipped']) . ' bereits auf der Liste oder ungültig (übersprungen)';
        Response::success($r, $msg);
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\Throwable $e) {
        error_log('LAM vorschlagsliste-eintrag-add: ' . $e->getMessage());
        Response::error('Hinzufügen fehlgeschlagen: ' . $e->getMessage(), 500);
    }
}

Response::error('Unbekannter Pfad', 404);
