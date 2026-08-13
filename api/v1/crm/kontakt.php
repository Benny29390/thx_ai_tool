<?php
/**
 * /api/v1/crm/kontakte/{id}[/{aktion}]
 *   GET (no action):    Detail
 *   PUT (no action):    Update
 *   DELETE (no action): Soft-Delete
 *   GET /aktivitaeten:  Zeitlinie
 *   POST /inline:       Inline-Edit { feld, wert }
 *   POST /tags:         Tag setzen/entfernen { tag_id, aktion: 'setzen'|'entfernen' }
 *   POST /listen:       Listen-Mitgliedschaft { listen_id, status }
 *   POST /adressen:     Adresse anlegen/aktualisieren
 *   DELETE /adressen?adresse_id=X: Adresse loeschen
 *   POST /foto:         (multipart) Avatar-Upload
 *   POST /merge:        { andere_id } Kontakte zusammenfuehren
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmKontaktService.php';
$svc = new \Services\CrmKontaktService($db);

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
if ($id <= 0) Response::error('id fehlt', 400);

// ─── ohne Action: Detail / Update / Delete ───────────────────────────────
if ($action === null) {
    if ($method === 'GET') {
        $data = $svc->detail($id);
        if (!$data) Response::error('Kontakt nicht gefunden', 404);
        Response::success($data);
    }
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        try {
            $ok = $svc->aktualisieren($id, $input, Auth::id());
            Response::success(['ok' => $ok], 'Kontakt aktualisiert');
        } catch (\Throwable $e) {
            Response::error($e->getMessage());
        }
    }
    if ($method === 'DELETE') {
        $svc->softDelete($id, Auth::id());
        Response::success(['ok' => true], 'Kontakt gelöscht');
    }
    Response::error('Methode nicht erlaubt', 405);
}

// ─── Aktionen ────────────────────────────────────────────────────────────
if ($action === 'aktivitaeten' && $method === 'GET') {
    $limit = (int)($_GET['limit'] ?? 100);
    Response::success(['aktivitaeten' => $svc->ladeAktivitaeten($id, $limit)]);
}

if ($action === 'inline' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $feld = (string)($input['feld'] ?? '');
    $wert = $input['wert'] ?? null;
    if ($feld === '') Response::error('feld fehlt', 400);
    try {
        $svc->aktualisiereFeld($id, $feld, $wert, Auth::id());
        Response::success(['ok' => true], 'Gespeichert');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'tags' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $tagId = (int)($input['tag_id'] ?? 0);
    $aktion = $input['aktion'] ?? 'setzen';
    if ($tagId <= 0) Response::error('tag_id fehlt', 400);
    if ($aktion === 'setzen')    $svc->setzeTag($id, $tagId, Auth::id());
    elseif ($aktion === 'entfernen') $svc->entferneTag($id, $tagId, Auth::id());
    else Response::error('aktion ungueltig', 400);
    Response::success(['ok' => true]);
}

if ($action === 'listen' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $listenId = (int)($input['listen_id'] ?? 0);
    $status = $input['status'] ?? 'aktiv';
    if ($listenId <= 0) Response::error('listen_id fehlt', 400);
    $svc->setzeListenMitgliedschaft($id, $listenId, $status, Auth::id());
    Response::success(['ok' => true]);
}

if ($action === 'adressen') {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $adresseId = $svc->speichereAdresse($id, $input);
        Response::success(['id' => $adresseId]);
    }
    if ($method === 'DELETE') {
        $adresseId = (int)($_GET['adresse_id'] ?? 0);
        if ($adresseId <= 0) Response::error('adresse_id fehlt', 400);
        $svc->loescheAdresse($adresseId, $id);
        Response::success(['ok' => true]);
    }
}

if ($action === 'foto' && $method === 'POST') {
    if (empty($_FILES['foto'])) Response::error('Keine Datei (Feldname „foto") übergeben', 400);
    try {
        $pfad = $svc->speichereFoto($id, $_FILES['foto'], Auth::id());
        Response::success(['foto_path' => $pfad], 'Foto gespeichert');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

if ($action === 'aktivitaet' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $erlaubteTypen = ['notiz','telefonat','meeting','sonstiges'];
    $typ = in_array($input['typ'] ?? 'notiz', $erlaubteTypen, true) ? $input['typ'] : 'notiz';
    $inhalt = trim((string)($input['inhalt'] ?? ''));
    $titel = isset($input['titel']) ? trim((string)$input['titel']) : null;
    if ($inhalt === '') Response::error('Inhalt fehlt', 400);
    $aktId = $svc->logAktivitaet($id, $typ, $titel, $inhalt, null, Auth::id());
    Response::success(['id' => $aktId], 'Gespeichert');
}

if ($action === 'social' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $plattform = trim((string)($input['plattform'] ?? ''));
    $url = isset($input['url']) ? trim((string)$input['url']) : '';
    if ($plattform === '') Response::error('Plattform fehlt', 400);
    try {
        $svc->speichereSocial($id, $plattform, $url);
        Response::success([], 'Gespeichert');
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    }
}

if ($action === 'merge' && $method === 'POST') {
    // wird in Schritt 9 implementiert
    Response::error('Merge wird in Phase-1-Schritt 9 implementiert', 501);
}

Response::error('Aktion oder Methode unbekannt', 400);
