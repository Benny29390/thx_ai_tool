<?php
/**
 * /api/v1/crm/pflege — Datenpflege-Center Endpoints.
 *
 *  GET    ?action=issues[&typ=X&status=Y&limit=N]   → Issue-Liste
 *  GET    ?action=stats                              → Counts pro Typ+Schwere
 *  POST   ?action=scan                               → Detektor erneut laufen lassen
 *  POST   ?action=merge_preview                      Body: {typ, ids[]}    → Side-by-side-Daten
 *  POST   ?action=merge                              Body: {typ, master_id, loser_ids[], field_values} → Merge ausführen
 *  POST   ?action=issue_status                       Body: {issue_id, neuer_status, notiz?} → ignorieren/erledigt
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\CrmPflegeDetectorService;
use Services\CrmMergeService;

if (!Auth::can('crm')) Response::error('Kein Zugriff', 403);

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$detector = new CrmPflegeDetectorService($db);
$merger = new CrmMergeService($db);

if ($method === 'GET' && $action === 'issues') {
    $filter = [
        'typ' => $_GET['typ'] ?? null,
        'status' => $_GET['status'] ?? null,
        'schwere' => $_GET['schwere'] ?? null,
        'interaktion_tage' => isset($_GET['interaktion_tage']) ? (int) $_GET['interaktion_tage'] : null,
        'fehlt_min' => isset($_GET['fehlt_min']) ? (int) $_GET['fehlt_min'] : null,
        'fehlt_max' => isset($_GET['fehlt_max']) ? (int) $_GET['fehlt_max'] : null,
        'limit' => $_GET['limit'] ?? 100,
        'offset' => $_GET['offset'] ?? 0,
    ];
    Response::success(['issues' => $detector->listIssues($filter)]);
}

if ($method === 'GET' && $action === 'stats') {
    Response::success(['stats' => $detector->getStatsByTyp()]);
}

if ($method === 'POST' && $action === 'scan') {
    $stats = $detector->runAll();
    Response::success(['stats' => $stats], 'Scan abgeschlossen');
}

/* linkedin_kandidaten: liefert mehrere LinkedIn-Profil-Vorschläge mit Vorschau-Bild
   und Beschreibung, sodass der User das richtige Profil per Modal auswählen kann. */
if ($method === 'POST' && $action === 'linkedin_kandidaten') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $kontaktId = (int) ($input['kontakt_id'] ?? 0);
    if (!$kontaktId) Response::error('kontakt_id nötig', 400);
    $svc = new \Services\CrmAnreicherungService($db);
    $r = $svc->sucheLinkedinKandidaten($kontaktId);
    Response::success($r);
}

/* image_search: Bilder-Suche für Foto-Vorschläge (Brave Images). Optional mit linkedin_only-Filter. */
if ($method === 'POST' && $action === 'image_search') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $kontaktId = (int) ($input['kontakt_id'] ?? 0);
    $linkedinOnly = !empty($input['linkedin_only']);
    if (!$kontaktId) Response::error('kontakt_id nötig', 400);
    $k = $db->queryOne(
        "SELECT k.vorname, k.nachname, k.funktion, f.firmenname
         FROM crm_kontakte k LEFT JOIN crm_firmen f ON f.id = k.firma_id
         WHERE k.id = ? AND k.geloescht_am IS NULL",
        [$kontaktId]
    );
    if (!$k) Response::error('Kontakt nicht gefunden', 404);
    $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
    if ($name === '') Response::error('Kontakt hat keinen Namen', 400);
    $braveKey = (string) \Core\Settings::get('brave_search_api_key');
    if ($braveKey === '') Response::error('Brave-Search-API-Key fehlt', 500);
    $query = $name;
    if ($k['firmenname']) $query .= ' ' . $k['firmenname'];
    if ($linkedinOnly) $query .= ' site:linkedin.com';
    try {
        $svc = new \Services\WebSearchService($braveKey);
        $sr = $svc->searchImages($query, 16);
        // Filter: ignoriere offensichtlich unbrauchbare Treffer (zu klein)
        $bilder = array_values(array_filter($sr['results'], function ($b) {
            $w = (int) ($b['width'] ?? 0);
            $h = (int) ($b['height'] ?? 0);
            return $w >= 100 && $h >= 100;
        }));
        Response::success(['query' => $query, 'bilder' => $bilder]);
    } catch (\Throwable $e) {
        Response::error('Bildersuche fehlgeschlagen: ' . $e->getMessage(), 500);
    }
}

/* save_kontakt_foto_url: lädt das gewählte Bild und setzt es als Avatar. */
if ($method === 'POST' && $action === 'save_kontakt_foto_url') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $kontaktId = (int) ($input['kontakt_id'] ?? 0);
    $url = trim((string) ($input['url'] ?? ''));
    if (!$kontaktId || $url === '') Response::error('kontakt_id + url nötig', 400);
    try {
        $svc = new \Services\CrmKontaktService($db);
        $pfad = $svc->speichereFotoVonUrl($kontaktId, $url, Auth::id());
        Response::success(['foto_path' => $pfad], 'Foto gespeichert');
    } catch (\Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
}

/* refresh_entity_issues: nach einer Action im Wizard prüfen, ob andere offene Issues für dieselbe
   Entity (Kontakt/Firma) jetzt obsolet sind. Bündelt verwandte Felder-Issues automatisch. */
if ($method === 'POST' && $action === 'refresh_entity_issues') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $typ = (string) ($input['typ'] ?? '');
    $id  = (int) ($input['id'] ?? 0);
    if (!$id || !in_array($typ, ['firma','kontakt'], true)) Response::error('typ + id nötig', 400);
    $detector = new \Services\CrmPflegeDetectorService($db);
    $closed = 0;
    // Alle offenen Issues, die diese Entity referenzieren
    $rows = $db->query(
        "SELECT id, typ, entities_json FROM crm_pflege_issues WHERE status IN ('offen','in_bearbeitung')"
    );
    foreach ($rows as $r) {
        $ents = json_decode($r['entities_json'] ?? '[]', true) ?: [];
        $matches = false;
        foreach ($ents as $e) {
            if (($e['typ'] ?? '') === $typ && (int)($e['id'] ?? 0) === $id) { $matches = true; break; }
        }
        if (!$matches) continue;
        // Issue-typ-spezifischer Re-Check
        $erledigt = false;
        if ($r['typ'] === 'fehlt_linkedin') {
            $hat = (int) $db->queryValue("SELECT COUNT(*) FROM crm_social_links WHERE kontakt_id = ? AND plattform = 'linkedin'", [$id]);
            $erledigt = ($hat > 0);
        } elseif ($r['typ'] === 'fehlt_email') {
            $mail = $db->queryValue("SELECT email_primaer FROM crm_kontakte WHERE id = ?", [$id]);
            $erledigt = ($mail && filter_var($mail, FILTER_VALIDATE_EMAIL));
        } elseif ($r['typ'] === 'fehlt_branche') {
            $br = $db->queryValue("SELECT branche FROM crm_firmen WHERE id = ?", [$id]);
            $erledigt = !empty($br);
        } elseif ($r['typ'] === 'fehlt_firma') {
            $fid = $db->queryValue("SELECT firma_id FROM crm_kontakte WHERE id = ?", [$id]);
            $erledigt = !empty($fid);
        } elseif ($r['typ'] === 'firma_ohne_kontakte') {
            $cnt = (int) $db->queryValue("SELECT COUNT(*) FROM crm_kontakte WHERE firma_id = ? AND geloescht_am IS NULL", [$id]);
            $erledigt = ($cnt > 0);
        } elseif ($r['typ'] === 'format_website') {
            $col = ($typ === 'firma') ? 'website' : 'website';
            $tbl = ($typ === 'firma') ? 'crm_firmen' : 'crm_kontakte';
            $w = $db->queryValue("SELECT $col FROM $tbl WHERE id = ?", [$id]);
            $erledigt = $w && preg_match('#^https?://#i', $w);
        }
        if ($erledigt) {
            $db->execute(
                "UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion='bundle_resolved', erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
                [Auth::id(), $r['id']]
            );
            $closed++;
        }
    }
    Response::success(['closed' => $closed], $closed . ' verwandte Issues miterledigt');
}

/* bulk_ignore: ignoriert alle Issues eines Typs (optional gefiltert nach Score / Schwere). */
if ($method === 'POST' && $action === 'bulk_ignore') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $typ = (string) ($input['typ'] ?? '');
    if ($typ === '') Response::error('typ nötig', 400);
    $where = ["typ = ?", "status = 'offen'"];
    $params = [$typ];
    if (isset($input['match_score_max'])) {
        $where[] = "COALESCE(match_score, 0) <= ?";
        $params[] = (float) $input['match_score_max'];
    }
    if (isset($input['fehlt_max'])) {
        // grobe Heuristik: match_score / 10 ≈ Anzahl fehlender Felder (siehe aktiv_unvollstaendig-Detektor)
        $where[] = "COALESCE(match_score, 0) < ?";
        $params[] = ((int) $input['fehlt_max'] + 1) * 10;
    }
    if (!empty($input['schwere'])) {
        $where[] = "schwere = ?";
        $params[] = $input['schwere'];
    }
    $whereSql = implode(' AND ', $where);
    $affected = $db->execute(
        "UPDATE crm_pflege_issues SET status='ignoriert', erledigt_aktion='bulk_ignoriert', erledigt_durch=?, erledigt_am=NOW()
         WHERE $whereSql",
        array_merge([Auth::id()], $params)
    );
    Response::success(['affected' => $affected], $affected . ' Issues ignoriert');
}

if ($method === 'POST' && $action === 'merge_preview') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $typ = $input['typ'] ?? '';
    $ids = $input['ids'] ?? [];
    $issueId = isset($input['issue_id']) ? (int)$input['issue_id'] : null;
    if (!is_array($ids) || count($ids) < 2) Response::error('Mindestens 2 IDs nötig', 400);
    try {
        if ($typ === 'kontakt') {
            $preview = $merger->mergePreviewKontakt($ids);
        } elseif ($typ === 'firma') {
            $preview = $merger->mergePreviewFirma($ids);
        } else {
            Response::error('Unbekannter Typ: ' . $typ, 400);
        }
        // Wenn nach dem Filter (geloescht_am IS NULL) weniger als 2 Records übrig sind,
        // ist das Dubletten-Issue obsolet. Markieren + 200 zurück, statt das Frontend
        // mit unvollständigen Daten zu beliefern.
        if (count($preview['records'] ?? []) < 2) {
            if ($issueId) {
                $db->execute(
                    "UPDATE crm_pflege_issues SET status='obsolet', erledigt_aktion='entity_geloescht', erledigt_durch=?, erledigt_am=NOW() WHERE id = ? AND status IN ('offen','in_bearbeitung')",
                    [Auth::id(), $issueId]
                );
            }
            Response::success(['obsolete' => true, 'grund' => 'Mindestens ein Datensatz dieser Dublette wurde zwischenzeitlich gelöscht']);
        }
        Response::success($preview);
    } catch (\Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
}

if ($method === 'POST' && $action === 'merge') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $typ = $input['typ'] ?? '';
    $masterId = (int)($input['master_id'] ?? 0);
    $loserIds = $input['loser_ids'] ?? [];
    $fieldValues = $input['field_values'] ?? [];
    $issueId = isset($input['issue_id']) ? (int)$input['issue_id'] : null;
    if (!$masterId || !is_array($loserIds) || empty($loserIds)) {
        Response::error('master_id + loser_ids erforderlich', 400);
    }
    try {
        if ($typ === 'kontakt') {
            $masterIdOut = $merger->mergeKontakte($masterId, $loserIds, $fieldValues, Auth::id());
        } elseif ($typ === 'firma') {
            $masterIdOut = $merger->mergeFirmen($masterId, $loserIds, $fieldValues, Auth::id());
        } else {
            Response::error('Unbekannter Typ: ' . $typ, 400);
        }
        // Issue als erledigt markieren
        if ($issueId) {
            $db->execute(
                "UPDATE crm_pflege_issues
                 SET status='erledigt', erledigt_aktion='merged', erledigt_durch=?, erledigt_am=NOW(),
                     erledigt_notiz=?
                 WHERE id = ?",
                [Auth::id(), 'Master #' . $masterIdOut . ', Loser: ' . implode(',', $loserIds), $issueId]
            );
        }
        Response::success(['master_id' => $masterIdOut], 'Merge abgeschlossen');
    } catch (\Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
}

if ($method === 'POST' && $action === 'ai_enrich') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $typ = $input['typ'] ?? '';
    $ids = $input['entity_ids'] ?? [];
    $modus = $input['modus'] ?? null; // optional: 'linkedin' für gezielte Profil-Suche
    if (!is_array($ids) || empty($ids)) Response::error('entity_ids fehlen', 400);
    $svc = new \Services\CrmAnreicherungService($db);
    if ($typ === 'firma') {
        Response::success($svc->anreichereFirma($ids));
    } elseif ($typ === 'kontakt') {
        if ($modus === 'linkedin') {
            Response::success($svc->anreichereLinkedin((int) $ids[0]));
        } else {
            Response::success($svc->anreichereKontakt((int) $ids[0]));
        }
    } else {
        Response::error('Unbekannter Typ: ' . $typ, 400);
    }
}

/* Single-Issue-Preview: liefert {record, felder, ki_supported} fuer Issues mit 1 Entity */
if ($method === 'POST' && $action === 'single_preview') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $typ = $input['typ'] ?? ''; // 'firma' oder 'kontakt'
    $id = (int) ($input['id'] ?? 0);
    $adresseId = isset($input['adresse_id']) ? (int) $input['adresse_id'] : null;
    if (!$id || !in_array($typ, ['firma','kontakt'], true)) Response::error('typ + id nötig', 400);
    $merger = new \Services\CrmMergeService($db);
    // issue_id optional — wenn gesendet, markiert der Server stale Issues automatisch als obsolet
    $issueId = isset($input['issue_id']) ? (int)$input['issue_id'] : null;
    if ($typ === 'firma') {
        $f = $db->queryOne("SELECT * FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$id]);
        if (!$f) {
            // Stale Issue: Firma ist (soft-)gelöscht. Issue automatisch als obsolet markieren
            // damit der Wizard zum nächsten springt — KEIN 404 (würde sonst Fail2Ban triggern).
            if ($issueId) {
                $db->execute(
                    "UPDATE crm_pflege_issues SET status='obsolet', erledigt_aktion='entity_geloescht', erledigt_durch=?, erledigt_am=NOW() WHERE id = ? AND status IN ('offen','in_bearbeitung')",
                    [Auth::id(), $issueId]
                );
            }
            Response::success(['obsolete' => true, 'grund' => 'Firma wurde zwischenzeitlich gelöscht']);
        }
        // Verknüpfte Kontakte mit Namen + Funktion (damit User sieht was an der Firma hängt)
        $kontakte = $db->query(
            "SELECT id, vorname, nachname, funktion, email_primaer FROM crm_kontakte
             WHERE firma_id = ? AND geloescht_am IS NULL ORDER BY nachname ASC",
            [$id]
        );
        $kontakteListe = array_map(fn($k) => [
            'kontakt_id' => (int) $k['id'],
            'firma_id' => $id,
            'name' => trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')),
            'funktion' => $k['funktion'] ?? '',
            'email' => $k['email_primaer'] ?? '',
        ], $kontakte);
        // Optional: spezifische Adresse mit hinzunehmen (z.B. bei plz_unplausibel)
        $felder = $merger->firmaFelderFuerVergleichPublic();
        if ($adresseId) {
            $adr = $db->queryOne("SELECT * FROM crm_adressen WHERE id = ? AND firma_id = ?", [$adresseId, $id]);
            if ($adr) {
                $f['adresse_id'] = (int) $adr['id'];
                $f['adresse_strasse'] = $adr['strasse'];
                $f['adresse_plz']     = $adr['plz'];
                $f['adresse_stadt']   = $adr['stadt'];
                $f['adresse_land']    = $adr['land'];
                $felder[] = ['key' => 'adresse_strasse', 'label' => 'Straße', 'virtual' => true];
                $felder[] = ['key' => 'adresse_plz',     'label' => 'PLZ',    'virtual' => true];
                $felder[] = ['key' => 'adresse_stadt',   'label' => 'Stadt',  'virtual' => true];
                $felder[] = ['key' => 'adresse_land',    'label' => 'Land',   'virtual' => true];
            }
        }
        Response::success([
            'typ' => 'firma',
            'record' => $f,
            'felder' => $felder,
            'kontakte' => $kontakteListe,
        ]);
    } else {
        $k = $db->queryOne(
            "SELECT k.*, f.firmenname AS firma_name FROM crm_kontakte k LEFT JOIN crm_firmen f ON f.id = k.firma_id
             WHERE k.id = ? AND k.geloescht_am IS NULL", [$id]
        );
        if (!$k) {
            if ($issueId) {
                $db->execute(
                    "UPDATE crm_pflege_issues SET status='obsolet', erledigt_aktion='entity_geloescht', erledigt_durch=?, erledigt_am=NOW() WHERE id = ? AND status IN ('offen','in_bearbeitung')",
                    [Auth::id(), $issueId]
                );
            }
            Response::success(['obsolete' => true, 'grund' => 'Kontakt wurde zwischenzeitlich gelöscht']);
        }
        // Social-Links als virtuelle Felder anreichern (für Issue-Typen wie fehlt_linkedin)
        $links = $db->query("SELECT plattform, url FROM crm_social_links WHERE kontakt_id = ?", [$id]);
        $k['linkedin'] = '';
        $k['xing'] = '';
        foreach ($links as $l) {
            if (in_array($l['plattform'], ['linkedin','xing'], true)) $k[$l['plattform']] = $l['url'];
        }
        $felder = $merger->kontaktFelderFuerVergleichPublic();
        // Optional: spezifische Adresse mit hinzunehmen (z.B. bei plz_unplausibel)
        if ($adresseId) {
            $adr = $db->queryOne("SELECT * FROM crm_adressen WHERE id = ? AND kontakt_id = ?", [$adresseId, $id]);
            if ($adr) {
                $k['adresse_id'] = (int) $adr['id'];
                $k['adresse_strasse'] = $adr['strasse'];
                $k['adresse_plz']     = $adr['plz'];
                $k['adresse_stadt']   = $adr['stadt'];
                $k['adresse_land']    = $adr['land'];
                $felder[] = ['key' => 'adresse_strasse', 'label' => 'Straße', 'virtual' => true];
                $felder[] = ['key' => 'adresse_plz',     'label' => 'PLZ',    'virtual' => true];
                $felder[] = ['key' => 'adresse_stadt',   'label' => 'Stadt',  'virtual' => true];
                $felder[] = ['key' => 'adresse_land',    'label' => 'Land',   'virtual' => true];
            }
        }
        Response::success([
            'typ' => 'kontakt',
            'record' => $k,
            'felder' => $felder,
        ]);
    }
}

/* assign_kontakt: weist einen bestehenden Kontakt einer Firma zu (für firma_ohne_kontakte). */
if ($method === 'POST' && $action === 'assign_kontakt') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $kontaktId = (int) ($input['kontakt_id'] ?? 0);
    $firmaId   = (int) ($input['firma_id'] ?? 0);
    $issueId   = isset($input['issue_id']) ? (int) $input['issue_id'] : null;
    if (!$kontaktId || !$firmaId) Response::error('kontakt_id + firma_id nötig', 400);
    $ok1 = $db->queryValue("SELECT id FROM crm_kontakte WHERE id = ? AND geloescht_am IS NULL", [$kontaktId]);
    $ok2 = $db->queryValue("SELECT id FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$firmaId]);
    if (!$ok1 || !$ok2) Response::error('Kontakt oder Firma nicht gefunden', 404);
    $db->execute("UPDATE crm_kontakte SET firma_id = ?, firma_status = 'verknuepft', geaendert_am = NOW(), geaendert_durch = ? WHERE id = ?",
        [$firmaId, Auth::id(), $kontaktId]);
    if ($issueId) {
        $db->execute("UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion='kontakt_zugewiesen', erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
            [Auth::id(), $issueId]);
    }
    Response::success([], 'Kontakt zugewiesen');
}

/* create_kontakt_fuer_firma: legt einen neuen Kontakt mit firma_id an (für firma_ohne_kontakte). */
if ($method === 'POST' && $action === 'create_kontakt_fuer_firma') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $firmaId  = (int) ($input['firma_id'] ?? 0);
    $vorname  = trim((string) ($input['vorname']  ?? ''));
    $nachname = trim((string) ($input['nachname'] ?? ''));
    $funktion = trim((string) ($input['funktion'] ?? ''));
    $email    = trim((string) ($input['email']    ?? ''));
    $telefon  = trim((string) ($input['telefon']  ?? ''));
    $mobil    = trim((string) ($input['mobil']    ?? ''));
    $issueId  = isset($input['issue_id']) ? (int) $input['issue_id'] : null;
    if (!$firmaId || ($vorname === '' && $nachname === '')) {
        Response::error('firma_id + mind. Vor- oder Nachname nötig', 400);
    }
    if (!$db->queryValue("SELECT id FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$firmaId])) {
        Response::error('Firma nicht gefunden', 404);
    }
    // E-Mail-Konflikt: wenn Mail bereits existiert, abbrechen mit klarer Meldung
    if ($email !== '') {
        $dup = $db->queryValue("SELECT id FROM crm_kontakte WHERE email_primaer = ?", [$email]);
        if ($dup) Response::error('Ein Kontakt mit dieser E-Mail existiert bereits (#' . $dup . ')', 409);
    } else {
        // crm_kontakte.email_primaer ist NOT NULL — Platzhalter setzen
        $email = 'unbekannt-' . uniqid() . '@invalid.local';
    }
    $neueId = $db->insert('crm_kontakte', [
        'firma_id' => $firmaId,
        'firma_status' => 'verknuepft',
        'vorname' => $vorname,
        'nachname' => $nachname,
        'funktion' => $funktion ?: null,
        'email_primaer' => $email,
        'telefon' => $telefon ?: null,
        'mobil' => $mobil ?: null,
        'erstellt_durch' => Auth::id(),
    ]);
    if ($issueId) {
        $db->execute("UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion='kontakt_angelegt', erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
            [Auth::id(), $issueId]);
    }
    Response::success(['kontakt_id' => $neueId], 'Kontakt angelegt');
}

/* set_adresse_feld: aktualisiert ein einzelnes Feld einer Adresse (für virtuelle Felder im Pflege-Wizard). */
if ($method === 'POST' && $action === 'set_adresse_feld') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $adresseId = (int) ($input['adresse_id'] ?? 0);
    $feld = (string) ($input['feld'] ?? '');
    $wert = $input['wert'] ?? null;
    $erlaubt = ['strasse','plz','stadt','bundesland','land'];
    if (!$adresseId || !in_array($feld, $erlaubt, true)) {
        Response::error('adresse_id + feld nötig', 400);
    }
    $exists = $db->queryValue("SELECT id FROM crm_adressen WHERE id = ?", [$adresseId]);
    if (!$exists) Response::error('Adresse nicht gefunden', 404);
    $db->execute("UPDATE crm_adressen SET {$feld} = ? WHERE id = ?", [($wert === '' ? null : $wert), $adresseId]);
    Response::success([], 'Adress-Feld aktualisiert');
}

/* set_social_link: legt einen Social-Link (LinkedIn, XING, …) an oder aktualisiert ihn. */
if ($method === 'POST' && $action === 'set_social_link') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $kontaktId = (int) ($input['kontakt_id'] ?? 0);
    $plattform = (string) ($input['plattform'] ?? '');
    $url = trim((string) ($input['url'] ?? ''));
    $issueId = isset($input['issue_id']) ? (int) $input['issue_id'] : null;
    $erlaubt = ['linkedin','xing','facebook','instagram','twitter_x','youtube','tiktok','website','sonstiges'];
    if (!$kontaktId || !in_array($plattform, $erlaubt, true) || $url === '') {
        Response::error('kontakt_id, plattform, url nötig', 400);
    }
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');
    $existing = $db->queryValue("SELECT id FROM crm_social_links WHERE kontakt_id = ? AND plattform = ?", [$kontaktId, $plattform]);
    if ($existing) {
        $db->execute("UPDATE crm_social_links SET url = ? WHERE id = ?", [$url, (int) $existing]);
    } else {
        $db->insert('crm_social_links', ['kontakt_id' => $kontaktId, 'plattform' => $plattform, 'url' => $url]);
    }
    if ($issueId) {
        $db->execute(
            "UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion='social_link_gesetzt', erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
            [Auth::id(), $issueId]
        );
    }
    Response::success([], 'Link gespeichert');
}

/* mark_shared_email: markiert mehrere Kontakte als "Mehrpersonen-Adresse" —
   sie werden vom E-Mail-Dubletten-Detektor künftig ignoriert. */
if ($method === 'POST' && $action === 'mark_shared_email') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $kontaktIds = $input['kontakt_ids'] ?? [];
    $issueId = isset($input['issue_id']) ? (int)$input['issue_id'] : null;
    if (!is_array($kontaktIds) || empty($kontaktIds)) Response::error('kontakt_ids fehlen', 400);
    try {
        $svc = new \Services\CrmKontaktService($db);
        foreach ($kontaktIds as $kid) {
            $svc->aktualisiereFeld((int) $kid, 'shared_email', 1, Auth::id());
        }
        if ($issueId) {
            $db->execute("UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion='shared_email_akzeptiert', erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
                [Auth::id(), $issueId]);
        }
        Response::success([], count($kontaktIds) . ' Kontakte als Mehrpersonen-Adresse markiert');
    } catch (\Throwable $e) { Response::error($e->getMessage(), 400); }
}

/* personalize_emails: setzt individuelle email_primaer pro Kontakt. */
if ($method === 'POST' && $action === 'personalize_emails') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $mapping = $input['mapping'] ?? []; // {kontakt_id: 'neue@email.de', ...}
    $issueId = isset($input['issue_id']) ? (int)$input['issue_id'] : null;
    if (!is_array($mapping) || empty($mapping)) Response::error('mapping fehlt', 400);
    try {
        $svc = new \Services\CrmKontaktService($db);
        $changed = 0;
        foreach ($mapping as $kid => $email) {
            $email = trim((string) $email);
            if ($email === '' || !str_contains($email, '@')) {
                Response::error('Ungültige E-Mail für Kontakt ' . $kid . ': „' . $email . '"', 400);
            }
            $svc->aktualisiereFeld((int) $kid, 'email_primaer', $email, Auth::id());
            $changed++;
        }
        if ($issueId) {
            $db->execute("UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion='emails_personalisiert', erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
                [Auth::id(), $issueId]);
        }
        Response::success([], $changed . ' E-Mails personalisiert');
    } catch (\Throwable $e) { Response::error($e->getMessage(), 400); }
}

/* set_firma_status: markiert Kontakt als ohne_firmenbezug oder pflege_offen
   (statt eine Firma zuzuweisen). Issue wird erledigt. */
if ($method === 'POST' && $action === 'set_firma_status') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $kontaktId = (int)($input['kontakt_id'] ?? 0);
    $status = $input['status'] ?? '';
    $issueId = isset($input['issue_id']) ? (int)$input['issue_id'] : null;
    if (!$kontaktId || !in_array($status, ['verknuepft','ohne_firmenbezug','pflege_offen'], true)) {
        Response::error('kontakt_id + gültiger status nötig', 400);
    }
    try {
        $svc = new \Services\CrmKontaktService($db);
        // Bei ohne_firmenbezug/pflege_offen: firma_id auf NULL
        if ($status !== 'verknuepft') {
            $svc->aktualisieren($kontaktId, ['firma_id' => null, 'firma_status' => $status], Auth::id());
        } else {
            $svc->aktualisiereFeld($kontaktId, 'firma_status', $status, Auth::id());
        }
        if ($issueId) {
            $db->execute("UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion=?, erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
                ['firma_status_' . $status, Auth::id(), $issueId]);
        }
        Response::success([], 'Status gesetzt');
    } catch (\Throwable $e) { Response::error($e->getMessage(), 400); }
}

/* quick_firma: legt schnell eine neue Firma/Organisation an (Name + Typ),
   verknüpft den übergebenen Kontakt damit, markiert Issue als erledigt. */
if ($method === 'POST' && $action === 'quick_firma') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($input['firmenname'] ?? ''));
    $typ = trim((string)($input['firmen_typ'] ?? '')); // 'GmbH','Verein','Schule','Kirchengemeinde',...
    $kontaktId = (int)($input['kontakt_id'] ?? 0);
    $issueId = isset($input['issue_id']) ? (int)$input['issue_id'] : null;
    if ($name === '') Response::error('Firmenname fehlt', 400);
    try {
        $firmaSvc = new \Services\CrmFirmaService($db);
        $firmaId = $firmaSvc->anlegen(['firmenname' => $name, 'firmen_typ' => $typ ?: null], Auth::id());
        if ($kontaktId) {
            $kontaktSvc = new \Services\CrmKontaktService($db);
            $kontaktSvc->aktualisieren($kontaktId, ['firma_id' => $firmaId, 'firma_status' => 'verknuepft'], Auth::id());
        }
        if ($issueId) {
            $db->execute("UPDATE crm_pflege_issues SET status='erledigt', erledigt_aktion='quick_firma_angelegt', erledigt_durch=?, erledigt_am=NOW() WHERE id = ?",
                [Auth::id(), $issueId]);
        }
        Response::success(['firma_id' => $firmaId], 'Firma „' . $name . '" angelegt');
    } catch (\Throwable $e) { Response::error($e->getMessage(), 400); }
}

if ($method === 'POST' && $action === 'issue_status') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $issueId = (int)($input['issue_id'] ?? 0);
    $neuerStatus = $input['neuer_status'] ?? '';
    $notiz = $input['notiz'] ?? null;
    if (!$issueId || !in_array($neuerStatus, ['offen','ignoriert','erledigt'], true)) {
        Response::error('issue_id + gültiger Status nötig', 400);
    }
    $update = ['status' => $neuerStatus];
    if ($neuerStatus !== 'offen') {
        $update['erledigt_durch'] = Auth::id();
        $update['erledigt_am'] = date('Y-m-d H:i:s');
        $update['erledigt_aktion'] = $neuerStatus === 'ignoriert' ? 'manuell_ignoriert' : 'manuell_erledigt';
        if ($notiz) $update['erledigt_notiz'] = $notiz;
    }
    $db->update('crm_pflege_issues', $update, 'id = ?', [$issueId]);
    Response::success([], 'Status aktualisiert');
}

Response::error('Aktion unbekannt: ' . $action, 400);
