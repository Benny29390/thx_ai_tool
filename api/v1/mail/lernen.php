<?php
/**
 * Lernsystem: Stilprofil + gelernte Regeln ansehen, anstoßen, freigeben.
 *
 * GET  ?konto_id=..                        -> Status, Stilprofil, Regel-Vorschläge, aktive Regeln
 * POST { konto_id, aktion:'stil_lernen' }  -> Stil-Lauf starten (Hintergrund, dauert Minuten)
 * POST { konto_id, aktion:'korrekturen' }  -> aus editierten Antworten Regeln ableiten
 * POST { regel_id, aktion:'freigeben'|'verwerfen' }
 * POST { regel_id, aktion:'bearbeiten', text }
 *
 * Grundregel: Eine abgeleitete Regel ist zuerst nur ein VORSCHLAG. Erst die Freigabe
 * durch den Menschen macht sie wirksam.
 */

use Core\Auth;
use Core\Response;
use Core\Settings;

global $db, $method, $input;

require_once SERVICES_PATH . '/MailLernService.php';
require_once SERVICES_PATH . '/MailStilService.php';

$lern = new \Services\MailLernService($db);
$stil = new \Services\MailStilService($db);

if ($method === 'GET') {
    $kontoId = (int) ($_GET['konto_id'] ?? 0);
    if (!$kontoId) Response::error('konto_id fehlt');

    $k = $db->queryOne(
        "SELECT stil_status, stil_meldung, stil_am FROM mail_konten WHERE id = ?",
        [$kontoId]
    ) ?: [];

    // Wie viel Lernmaterial liegt bereit? (Korrekturen, die noch nicht ausgewertet sind)
    $offeneKorrekturen = (int) $db->queryValue(
        "SELECT COUNT(*) FROM mail_antworten a
         JOIN mail_nachrichten m ON m.id = a.eingang_mail_id
         WHERE m.konto_id = ? AND a.wurde_editiert = 1 AND a.gelernt_am IS NULL",
        [$kontoId]
    );

    Response::success([
        'status'             => $k['stil_status'] ?? 'leer',
        'meldung'            => $k['stil_meldung'] ?? null,
        'stil_am'            => $k['stil_am'] ?? null,
        'profil'             => $stil->aktivesProfil($kontoId),
        'vorschlaege'        => $lern->vorschlaege($kontoId),
        'aktive'             => $lern->aktiveRegeln($kontoId),
        'offene_korrekturen' => $offeneKorrekturen,
    ]);
}

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$aktion = (string) ($input['aktion'] ?? '');

// ---- Entscheidungen über einzelne Regeln ----
if (in_array($aktion, ['freigeben', 'verwerfen', 'bearbeiten'], true)) {
    $regelId = (int) ($input['regel_id'] ?? 0);
    if (!$regelId) Response::error('regel_id fehlt');
    try {
        if ($aktion === 'freigeben') {
            $lern->freigeben($regelId, Auth::id());
            Response::success(null, 'Regel freigegeben — sie wirkt ab dem nächsten Antwortentwurf.');
        }
        if ($aktion === 'verwerfen') {
            $lern->verwerfen($regelId, Auth::id());
            Response::success(null, 'Regel verworfen.');
        }
        $lern->bearbeiten($regelId, (string) ($input['text'] ?? ''));
        Response::success(null, 'Regel geändert.');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

$kontoId = (int) ($input['konto_id'] ?? 0);
if (!$kontoId) Response::error('konto_id fehlt');

// ---- Stil-Lauf starten (Hintergrund) ----
if ($aktion === 'stil_lernen') {
    $laeuft = $db->queryValue("SELECT stil_status FROM mail_konten WHERE id = ?", [$kontoId]);
    if ($laeuft === 'laeuft') {
        Response::success(['status' => 'laeuft'], 'Der Stil-Lauf läuft bereits.');
    }
    $frei = (int) $db->queryValue(
        "SELECT COUNT(*) FROM mail_konten_ordner WHERE konto_id = ? AND stil_lernen = 1", [$kontoId]
    );
    if (!$frei) {
        Response::error('Kein Ordner zum Stil-Lernen freigegeben. Bitte in der Ordner-Auswahl „Stil lernen" ankreuzen.');
    }

    // Eigener Prozess: IMAP-Suche über viele Ordner + zwei LLM-Aufrufe dauern Minuten
    // und würden jede Web-Anfrage in den Timeout laufen lassen.
    $db->execute("UPDATE mail_konten SET stil_status='laeuft', stil_meldung=NULL WHERE id=?", [$kontoId]);
    exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/scripts/mail-stil-lernen.php')
        . ' --konto=' . (int) $kontoId . ' > /dev/null 2>&1 &');
    Response::success(['status' => 'laeuft'], 'Stil-Lauf gestartet — das dauert einige Minuten.');
}

// ---- Aus Korrekturen lernen ----
if ($aktion === 'korrekturen') {
    set_time_limit(300);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    $settings = Settings::decryptMap($settings);

    try {
        $r = $lern->lerneAusKorrekturen($kontoId, $settings);
        Response::success($r, $r['meldung']);
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

Response::error('Unbekannte Aktion');
