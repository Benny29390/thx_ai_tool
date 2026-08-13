<?php
/**
 * Stil-Lauf: Aus Thomas' eigenen Mails lernen, wie er schreibt — und daraus Regeln ableiten.
 *
 * Läuft im Hintergrund, weil er Minuten dauert (IMAP-Suche über viele Ordner + zwei
 * LLM-Aufrufe). Der Fortschritt steht in mail_konten.stil_status.
 *
 * Was passiert:
 *   1. ERNTE    — nur Mails, deren Absender Thomas ist, aus den zum Stil-Lernen
 *                 freigegebenen Ordnern. Zitate und Signaturen werden abgeschnitten.
 *   2. PROFIL   — ein LLM beschreibt seinen Schreibstil (mail_stilprofil).
 *   3. REGELN   — ein LLM leitet konkrete, überprüfbare Regeln ab (mail_gelernte_regeln,
 *                 Status "vorschlag" — sie wirken erst nach Thomas' Freigabe).
 *
 * Das Postfach wird NICHT verändert: nur gelesen, nichts verschoben, nichts als gelesen
 * markiert. Die Mailtexte werden nach der Auswertung verworfen, nur das Profil bleibt.
 *
 * Aufruf: php scripts/mail-stil-lernen.php --konto=3
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
foreach ($argv as $a) {
    if (preg_match('/^--konto=(\d+)$/', $a, $m)) $kontoId = (int) $m[1];
}
if (!$kontoId) {
    fwrite(STDERR, "Aufruf: php scripts/mail-stil-lernen.php --konto=<id>\n");
    exit(1);
}

function status(int $kontoId, string $status, ?string $meldung = null): void
{
    $db = \Core\Database::getInstance();
    $db->execute(
        "UPDATE mail_konten SET stil_status=?, stil_meldung=?, stil_am=NOW() WHERE id=?",
        [$status, $meldung, $kontoId]
    );
}

status($kontoId, 'laeuft', 'Eigene Mails werden gesucht …');
echo "=== Stil-Lauf für Konto #$kontoId ===\n";

try {
    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    $settings = \Core\Settings::decryptMap($settings);

    $stil = new \Services\MailStilService($db);
    $lern = new \Services\MailLernService($db);

    // --- 1. Ernte ---
    $t0 = microtime(true);
    $funde = $stil->ernte($kontoId);
    printf("Ernte: %d eigene Mails in %.0f s\n", count($funde), microtime(true) - $t0);

    if (count($funde) < 5) {
        status($kontoId, 'fehler', 'Nur ' . count($funde) . ' eigene Mails gefunden — zu wenig. Mehr Ordner zum Stil-Lernen freigeben.');
        exit(1);
    }
    status($kontoId, 'laeuft', count($funde) . ' eigene Mails gefunden, Stilprofil wird erstellt …');

    // --- 2. Stilprofil ---
    $t0 = microtime(true);
    $profil = $stil->erzeugeProfil($kontoId, $settings, $funde);
    printf("Stilprofil: aus %d Mails (%d als Probe), %.0f s, Modell %s\n",
        $profil['gefunden'], $profil['genutzt'], microtime(true) - $t0, $profil['modell']);

    status($kontoId, 'laeuft', 'Stilprofil fertig, Regeln werden abgeleitet …');

    // --- 3. Regeln ---
    $t0 = microtime(true);
    $regeln = $lern->regelnAusStil($kontoId, $funde, $settings);
    printf("Regeln: %d Vorschläge in %.0f s\n", count($regeln), microtime(true) - $t0);

    $meldung = sprintf(
        '%d eigene Mails ausgewertet · Stilprofil erstellt · %d Regel-Vorschläge (warten auf Freigabe)',
        count($funde), count($regeln)
    );
    status($kontoId, 'fertig', $meldung);
    echo "\nFertig: $meldung\n";
} catch (\Throwable $e) {
    status($kontoId, 'fehler', mb_substr($e->getMessage(), 0, 240));
    fwrite(STDERR, 'FEHLER: ' . $e->getMessage() . "\n");
    exit(1);
}
