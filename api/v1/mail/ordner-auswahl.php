<?php
/**
 * Ordner-Auswahl je Konto — liest aus dem Ordner-Katalog (mail_ordner_cache).
 *
 * GET  ?konto_id=..            -> Katalog + Scan-Status + gespeicherte Auswahl
 * POST { konto_id, ordner:[] } -> Auswahl speichern
 * POST { konto_id, aktion:'scan' } -> Katalog neu einlesen (laeuft im Hintergrund)
 *
 * Vier Schalter je Ordner:
 *   abholen     = Ordner erscheint in /mail (lesen + beantworten)
 *   ins_wissen  = Inhalt wandert zusaetzlich in die Wissensdatenbank
 *   stil_lernen = aus den EIGENEN Mails darin wird der Schreibstil gelernt
 *   rekursiv    = inkl. aller Unterordner
 */

use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/MailKontoService.php';
$svc = new \Services\MailKontoService($db);

if ($method === 'GET') {
    $kontoId = (int) ($_GET['konto_id'] ?? 0);
    if (!$kontoId) Response::error('konto_id fehlt');
    Response::success($svc->ordnerBaumAusKatalog($kontoId));
}

if ($method === 'POST') {
    $kontoId = (int) ($input['konto_id'] ?? 0);
    if (!$kontoId) Response::error('konto_id fehlt');

    // --- Katalog neu einlesen ---
    if (($input['aktion'] ?? '') === 'scan') {
        $laeuft = $db->queryValue("SELECT scan_status FROM mail_konten WHERE id = ?", [$kontoId]);
        if ($laeuft === 'laeuft') {
            Response::success(['status' => 'laeuft'], 'Der Katalog wird bereits eingelesen.');
        }
        // Bewusst als eigener Prozess: 2000+ Ordner beim Server abzufragen dauert Minuten
        // und wuerde jede Web-Anfrage in den Timeout laufen lassen.
        $db->execute("UPDATE mail_konten SET scan_status='laeuft', scan_fortschritt=0, scan_meldung=NULL WHERE id=?", [$kontoId]);
        $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(ROOT_PATH . '/scripts/mail-ordner-scan.php')
             . ' --konto=' . (int) $kontoId . ' > /dev/null 2>&1 &';
        exec($cmd);
        Response::success(['status' => 'laeuft'], 'Ordner werden eingelesen — das dauert ein bis zwei Minuten.');
    }

    // --- Auswahl speichern ---
    $ordner = $input['ordner'] ?? [];
    if (!is_array($ordner)) Response::error('ordner muss eine Liste sein');

    try {
        $n = $svc->speichereOrdnerAuswahl($kontoId, $ordner);
        Response::success(['gespeichert' => $n], $n . ' Ordner ausgewählt.');
    } catch (\Throwable $e) {
        Response::error($e->getMessage());
    }
}

Response::error('Method not allowed', 405);
