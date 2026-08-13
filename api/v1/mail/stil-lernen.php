<?php
/**
 * Stil-Ernte: Aus den eigenen Mails ein Stilprofil erzeugen.
 *
 * GET  ?konto_id=..  -> aktives Profil anzeigen
 * POST { konto_id }  -> neu erzeugen (IMAP-Suche nach eigenen Mails + KI-Analyse)
 */

use Core\Response;
use Core\Settings;

global $db, $method, $input;

require_once SERVICES_PATH . '/MailStilService.php';
$svc = new \Services\MailStilService($db);

if ($method === 'GET') {
    $kontoId = (int) ($_GET['konto_id'] ?? 0);
    if (!$kontoId) Response::error('konto_id fehlt');
    $p = $svc->aktivesProfil($kontoId);
    Response::success(['profil' => $p]);
}

if ($method === 'POST') {
    $kontoId = (int) ($input['konto_id'] ?? 0);
    if (!$kontoId) Response::error('konto_id fehlt');

    // Kann dauern: IMAP-Suche ueber viele Ordner + ein LLM-Aufruf.
    // Speicher hoch: webklex laedt beim Abruf komplette Mail-Inhalte; grosse HTML-Mails
    // sprengen sonst das Standard-Limit.
    set_time_limit(600);
    ini_set('memory_limit', '1G');
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    $settings = Settings::decryptMap($settings);

    try {
        $r = $svc->erzeugeProfil($kontoId, $settings);
        Response::success($r, sprintf(
            'Stilprofil erstellt aus %d eigenen Mails (%d als Textprobe genutzt).',
            $r['gefunden'], $r['genutzt']
        ));
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

Response::error('Method not allowed', 405);
