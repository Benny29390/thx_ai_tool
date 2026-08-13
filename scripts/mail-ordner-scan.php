<?php
/**
 * Ordner-Katalog einlesen: Struktur + Mail-Anzahl je Ordner in mail_ordner_cache.
 *
 * Warum ueberhaupt ein Katalog?
 * Thomas' Postfach hat ueber 2000 Ordner. Sie bei jedem Oeffnen der Ordner-Auswahl vom
 * Server zu holen dauert Minuten. Und ohne die Mail-Anzahl sieht man nicht, dass ~80 %
 * davon LEER sind (Outlook-Altlasten, alte Kategorien). Einmal einlesen, danach ist die
 * Auswahl sofort da und zeigt nur, was wirklich zaehlt.
 *
 * Aufruf: php scripts/mail-ordner-scan.php --konto=3
 */

require_once __DIR__ . '/../config/constants.php';
spl_autoload_register(function ($class) {
    foreach (['Core\\' => 'core/', 'Models\\' => 'models/', 'Services\\' => 'services/'] as $ns => $dir) {
        if (strpos($class, $ns) === 0) {
            $f = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($f)) { require_once $f; return; }
        }
    }
});
require_once ROOT_PATH . '/vendor/autoload.php';

ini_set('memory_limit', '1G');
set_time_limit(0);

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$kontoId = 0;
$alle = in_array('--alle', $argv ?? [], true);
foreach ($argv as $a) {
    if (preg_match('/^--konto=(\d+)$/', $a, $m)) $kontoId = (int) $m[1];
}

// --alle: alle aktiven Konten nacheinander (fuer den naechtlichen Cron)
if ($alle && !$kontoId) {
    $ids = array_column($db->query("SELECT id FROM mail_konten WHERE aktiv = 1"), 'id');
    foreach ($ids as $id) {
        echo "=== Konto #$id ===\n";
        passthru(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --konto=' . (int) $id);
    }
    exit(0);
}

if (!$kontoId) {
    fwrite(STDERR, "Aufruf: php scripts/mail-ordner-scan.php --konto=<id> | --alle\n");
    exit(1);
}

/**
 * Outlook-Sonderordner, die keine echten Mail-Ordner sind. Die tauchen bei Exchange
 * ueber IMAP mit auf (Kalender hat 25.000 "Mails") und wuerden die Auswahl zumuellen.
 * Bewusst nach dem OBERSTEN Pfadsegment beurteilt — Unterordner erben die Einstufung.
 */
const SYSTEM_ORDNER = [
    'kalender', 'kontakte', 'journal', 'aufgaben', 'notizen', 'postausgang',
    'junk-e-mail', 'junk e-mail', 'synchronisierungsprobleme', 'rss-feeds',
    'verlauf für unterhaltungen', 'quick step-einstellungen', 'vorgeschlagene kontakte',
    'gelöschte elemente', 'entwürfe',
];

function istSystem(string $lesbarerPfad, string $trenner): bool
{
    $oben = mb_strtolower(explode($trenner, $lesbarerPfad)[0] ?? '');
    return in_array($oben, SYSTEM_ORDNER, true);
}

$db->execute(
    "UPDATE mail_konten SET scan_status='laeuft', scan_fortschritt=0, scan_gesamt=0, scan_meldung=NULL WHERE id=?",
    [$kontoId]
);

try {
    $konten = new \Services\MailKontoService($db);
    $cfg = $konten->getZugangsdaten($kontoId);

    $mgr = new \Webklex\PHPIMAP\ClientManager();
    $client = $mgr->make($konten->imapVerbindung($cfg));
    $client->connect();

    // Das Trennzeichen kommt vom SERVER, es wird nicht geraten.
    // Exchange nutzt "/". Frueher habe ich zusaetzlich am Punkt zerlegt — das zerhackte
    // jeden Ordner mit Punkt im Namen ("… unternehmer.de" wurde zu "unternehmer" + "de")
    // und damit Namen, Einrueckung und die ganze Eltern-Kind-Struktur.
    $trenner = '/';
    $ordner = [];
    foreach ($client->getFolders(false) as $f) {
        if (!empty($f->delimiter)) $trenner = (string) $f->delimiter;
        $ordner[] = ['pfad' => (string) $f->path, 'lesbar' => (string) ($f->full_name ?: $f->path), 'obj' => $f];
    }
    $db->execute("UPDATE mail_konten SET ordner_trenner = ? WHERE id = ?", [$trenner, $kontoId]);
    echo "Trennzeichen laut Server: [$trenner]\n";

    $gesamt = count($ordner);
    $db->execute("UPDATE mail_konten SET scan_gesamt=? WHERE id=?", [$gesamt, $kontoId]);
    echo "Ordner gefunden: $gesamt\n";

    $db->execute("DELETE FROM mail_ordner_cache WHERE konto_id=?", [$kontoId]);

    $i = 0;
    $mitMails = 0;
    foreach ($ordner as $o) {
        $i++;
        $anzahl = 0;
        try {
            // examine() = "wie viele Eintraege liegen hier?" ohne sie zu laden.
            $ex = $o['obj']->examine();
            $anzahl = (int) ($ex['exists'] ?? 0);
        } catch (\Throwable $e) {
            $anzahl = 0;   // nicht selektierbare Ordner (reine Container) zaehlen als leer
        }

        /**
         * Ist das ueberhaupt ein MAIL-Ordner?
         *
         * Exchange gibt ueber IMAP auch Kontaktlisten, Kalender und Kategorien heraus —
         * mit Eintragszahl (z.B. "Akquise Potenzial: 1781"), aber es sind keine Mails.
         * `examine()` sieht bei beiden EXAKT gleich aus, es gibt kein Attribut, das sie
         * unterscheidet. Der einzige verlaessliche Test: eine Nachricht anfordern.
         * Mail-Ordner liefern sie, alle anderen antworten mit "Empty response".
         *
         * Kostet einen zusaetzlichen Abruf je gefuelltem Ordner — vertretbar, weil dieser
         * Lauf nachts stattfindet und Thomas sonst 200 Kategorien in der Auswahl haette.
         */
        $istMail = true;
        if ($anzahl > 0) {
            try {
                $probe = $o['obj']->messages()->all()
                    ->leaveUnread()            // nichts als gelesen markieren
                    ->setFetchBody(false)      // nur der Kopf, kein Inhalt
                    ->setFetchFlags(false)
                    ->limit(1)
                    ->get();
                $istMail = count($probe) > 0;
            } catch (\Throwable $e) {
                $istMail = false;              // "Empty response" => kein Mail-Ordner
            }
        }
        if ($anzahl > 0 && $istMail) $mitMails++;

        $lesbar = $o['lesbar'];

        // AUSSCHLIESSLICH am Trennzeichen des Servers zerlegen.
        $lesbarTeile = explode($trenner, $lesbar);
        $nameKurz    = (string) end($lesbarTeile);
        $tiefe       = max(0, count($lesbarTeile) - 1);

        // Eltern-Pfad im ROHEN Format (IMAP braucht roh, die Anzeige braucht lesbar)
        $eltern = null;
        $rohTeile = explode($trenner, $o['pfad']);
        if (count($rohTeile) > 1) {
            array_pop($rohTeile);
            $eltern = implode($trenner, $rohTeile);
        }

        $db->execute(
            "INSERT INTO mail_ordner_cache (konto_id, pfad, name_lesbar, name_kurz, eltern_pfad, tiefe, anzahl_mails, ist_system, ist_mailordner)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name_lesbar=VALUES(name_lesbar), name_kurz=VALUES(name_kurz),
                                     eltern_pfad=VALUES(eltern_pfad), tiefe=VALUES(tiefe),
                                     anzahl_mails=VALUES(anzahl_mails), ist_system=VALUES(ist_system),
                                     ist_mailordner=VALUES(ist_mailordner), aktualisiert_am=NOW()",
            [$kontoId, $o['pfad'], $lesbar, $nameKurz, $eltern, $tiefe, $anzahl,
             istSystem($lesbar, $trenner) ? 1 : 0, $istMail ? 1 : 0]
        );

        if ($i % 25 === 0) {
            $db->execute("UPDATE mail_konten SET scan_fortschritt=? WHERE id=?", [$i, $kontoId]);
            echo "  $i / $gesamt …\n";
        }
    }

    try { $client->disconnect(); } catch (\Throwable $e) {}

    $keineMail = (int) $db->queryValue(
        "SELECT COUNT(*) FROM mail_ordner_cache WHERE konto_id=? AND ist_mailordner=0", [$kontoId]
    );
    $meldung = "$gesamt Ordner · $mitMails echte Mail-Ordner · $keineMail Kategorien/Kontakte (ausgeblendet)";
    $db->execute(
        "UPDATE mail_konten SET scan_status='fertig', scan_fortschritt=?, scan_am=NOW(), scan_meldung=? WHERE id=?",
        [$i, $meldung, $kontoId]
    );
    echo "Fertig: $meldung\n";
} catch (\Throwable $e) {
    $db->execute(
        "UPDATE mail_konten SET scan_status='fehler', scan_meldung=? WHERE id=?",
        [mb_substr($e->getMessage(), 0, 240), $kontoId]
    );
    fwrite(STDERR, "FEHLER: " . $e->getMessage() . "\n");
    exit(1);
}
