<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$primaryId = (int)($input['primary_id'] ?? 0);
$secondaryId = (int)($input['secondary_id'] ?? 0);
if ($primaryId <= 0 || $secondaryId <= 0 || $primaryId === $secondaryId) {
    Response::error('primary_id + secondary_id (unterschiedlich) erforderlich');
}

$db = Database::getInstance();
$primary = $db->queryOne("SELECT * FROM crm_kontakte WHERE id = ? AND geloescht_am IS NULL", [$primaryId]);
$secondary = $db->queryOne("SELECT * FROM crm_kontakte WHERE id = ? AND geloescht_am IS NULL", [$secondaryId]);
if (!$primary || !$secondary) Response::error('Kontakt nicht gefunden', 404);

// Wenn Frontend explizit Felder vorgibt (Feld-für-Feld-Dialog), die übernehmen,
// sonst Default-Logik: Felder aus secondary nehmen, falls primary leer.
$feldwahl = is_array($input['feldwahl'] ?? null) ? $input['feldwahl'] : null;

$update = [];
if ($feldwahl) {
    $mergebareFelder = ['anrede','titel','vorname','nachname','funktion','abteilung','geburtsdatum',
        'email_primaer','email_zweit','telefon','telefon_alt','mobil','fax','website',
        'firma_id','bevorzugtes_thema','interessen','merkmale','beschreibung',
        'kontakt_status','lead_quelle','opt_in_status','thx_score',
        'asana_task_gid','deal_wert','deal_stufe','foto_path'];
    foreach ($mergebareFelder as $f) {
        if (!array_key_exists($f, $feldwahl)) continue;
        $quelle = $feldwahl[$f]; // 'primary' | 'secondary' | 'leer'
        if ($quelle === 'secondary') {
            if (($primary[$f] ?? null) != ($secondary[$f] ?? null)) {
                $update[$f] = $secondary[$f] ?? null;
            }
        } elseif ($quelle === 'leer') {
            if (!empty($primary[$f])) $update[$f] = null;
        }
        // 'primary' = keine Änderung
    }
} else {
    foreach (['vorname','funktion','abteilung','telefon','mobil','website','firma_id','beschreibung','thx_score'] as $f) {
        if (empty($primary[$f]) && !empty($secondary[$f])) $update[$f] = $secondary[$f];
    }
}
if (!empty($update)) {
    $db->update('crm_kontakte', $update, 'id = ?', [$primaryId]);
}

// Tags vereinigen
$db->execute("INSERT IGNORE INTO crm_kontakt_tags (kontakt_id, tag_id, vergeben_am) 
              SELECT ?, tag_id, vergeben_am FROM crm_kontakt_tags WHERE kontakt_id = ?",
             [$primaryId, $secondaryId]);

// Listen-Mitgliedschaften
$db->execute("INSERT IGNORE INTO crm_kontakt_listen (kontakt_id, listen_id, status, beigetreten_am)
              SELECT ?, listen_id, status, beigetreten_am FROM crm_kontakt_listen WHERE kontakt_id = ?",
             [$primaryId, $secondaryId]);

// Adressen, Aktivitäten, Brevo-Events übertragen
$db->execute("UPDATE crm_adressen SET kontakt_id = ? WHERE kontakt_id = ?", [$primaryId, $secondaryId]);
$db->execute("UPDATE crm_aktivitaeten SET kontakt_id = ? WHERE kontakt_id = ?", [$primaryId, $secondaryId]);
$db->execute("UPDATE crm_brevo_events SET kontakt_id = ? WHERE kontakt_id = ?", [$primaryId, $secondaryId]);

// Secondary soft-deleten + Tombstone
$db->update('crm_kontakte', ['geloescht_am' => date('Y-m-d H:i:s'), 'geloescht_durch' => Auth::id()], 'id = ?', [$secondaryId]);
$db->insert('crm_loesch_events', [
    'entity_typ' => 'kontakt',
    'entity_id' => $secondaryId,
    'geloescht_durch' => Auth::id(),
    'art' => 'soft',
    'grund' => 'merge in ' . $primaryId,
]);

// Aktivität im Primary
$db->insert('crm_aktivitaeten', [
    'kontakt_id' => $primaryId,
    'typ' => 'kontakt_geaendert',
    'titel' => 'Merge: Kontakt #' . $secondaryId . ' zusammengeführt',
    'quelle' => 'manuell',
    'actor_user_id' => Auth::id(),
]);

Response::success(['primary_id' => $primaryId, 'secondary_id' => $secondaryId], 'Kontakte zusammengeführt');
