<?php
/**
 * Abnahmetest für das Mail-Lernsystem.
 *
 * Prüft die Zusagen, die dem Nutzer gemacht wurden — nicht bloß, dass der Code läuft:
 *   1. Ohne Freigabe wirkt KEINE Regel.
 *   2. Nach Freigabe wirkt sie.
 *   3. Nach Verwerfen wirkt sie wieder nicht.
 *   4. Das Gelernte landet tatsächlich im Antwort-Prompt.
 *   5. Das Exchange-Postfach kann NICHT beschrieben werden.
 *
 * Der Test verändert nichts dauerhaft: Er legt eine eigene Testregel an und räumt sie weg.
 * Frühere Fassung dieses Tests meldete "wirkt", obwohl es gar keine Regel gab — ein Test,
 * der bei leerer Datenlage grün wird, ist wertlos. Deshalb prüft er jetzt seine Voraussetzungen.
 *
 * Aufruf: php scripts/test-mail-lernsystem.php --konto=3
 */

require_once __DIR__ . '/../config/constants.php';
spl_autoload_register(function ($class) {
    foreach (['Core\\' => 'core/', 'Services\\' => 'services/'] as $ns => $dir) {
        if (strpos($class, $ns) === 0) {
            $f = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($f)) { require_once $f; return; }
        }
    }
});
$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$kontoId = 3;
foreach ($argv as $a) {
    if (preg_match('/^--konto=(\d+)$/', $a, $m)) $kontoId = (int) $m[1];
}

$fehler = 0;
function pruefe(string $was, bool $ok, string $detail = ''): void
{
    global $fehler;
    echo ($ok ? '  OK   ' : '  FEHL ') . $was . ($detail ? "  ($detail)" : '') . "\n";
    if (!$ok) $fehler++;
}

$lern = new \Services\MailLernService($db);

echo "=== Abnahmetest Mail-Lernsystem (Konto #$kontoId) ===\n\n";

// --- Voraussetzungen ---
echo "0) Voraussetzungen\n";
$stil = (new \Services\MailStilService($db))->aktivesProfil($kontoId);
pruefe('Stilprofil vorhanden', $stil !== null && trim((string) $stil['profil_text']) !== '',
    $stil ? $stil['basis_anzahl'] . ' Mails' : 'keins');

$anzVorschlaege = count($lern->vorschlaege($kontoId));
pruefe('Regel-Vorschläge vorhanden', $anzVorschlaege > 0, $anzVorschlaege . ' Stück');

// --- Eigene Testregel: der Test darf sich nicht auf echte Daten stützen ---
$testText = 'TESTREGEL ' . bin2hex(random_bytes(4)) . ' — bitte ignorieren';
$testId = $db->insert('mail_gelernte_regeln', [
    'konto_id'   => $kontoId,
    'regel_text' => $testText,
    'kategorie'  => 'Tabu',
    'quelle'     => 'manuell',
    'status'     => 'vorschlag',
]);

echo "\n1) Ohne Freigabe wirkt nichts\n";
$block = $lern->promptBlock($kontoId);
pruefe('Testregel steht NICHT im Prompt', !str_contains($block, $testText));

echo "\n2) Nach Freigabe wirkt sie\n";
$lern->freigeben($testId, null);
$block = $lern->promptBlock($kontoId);
pruefe('Testregel steht im Prompt', str_contains($block, $testText));

echo "\n3) Nach Verwerfen wirkt sie nicht mehr\n";
$lern->verwerfen($testId, null);
$block = $lern->promptBlock($kontoId);
pruefe('Testregel wieder raus', !str_contains($block, $testText));

echo "\n4) Stilprofil im Prompt\n";
pruefe('Stilprofil steht im Prompt', str_contains($lern->promptBlock($kontoId), 'SO SCHREIBT THOMAS'));

echo "\n5) Anbindung an die Antwort-Erzeugung\n";
$src = file_get_contents(SERVICES_PATH . '/MailKlassifikationService.php');
pruefe('promptBlock() wird aufgerufen', str_contains($src, 'promptBlock'));
pruefe('wird an den System-Prompt gehängt', (bool) preg_match('/\$system\s*\.=/', $src));

echo "\n6) Exchange-Postfach ist schreibgeschützt\n";
$imap = new \Services\MailImapService($db);
$m = new ReflectionMethod($imap, 'darfSchreiben');
$m->setAccessible(true);
pruefe('OAuth2 + nur_lesen=1  => gesperrt', !$m->invoke($imap, ['auth_typ' => 'oauth2', 'nur_lesen' => 1]));
pruefe('OAuth2 + nur_lesen=0  => TROTZDEM gesperrt', !$m->invoke($imap, ['auth_typ' => 'oauth2', 'nur_lesen' => 0]));
pruefe('Passwort + nur_lesen=0 => erlaubt (Altkonten)', $m->invoke($imap, ['auth_typ' => 'passwort', 'nur_lesen' => 0]));
$nl = (int) $db->queryValue("SELECT nur_lesen FROM mail_konten WHERE id = ?", [$kontoId]);
pruefe('Konto steht in der DB auf nur_lesen', $nl === 1);

// Aufräumen
$db->execute("DELETE FROM mail_gelernte_regeln WHERE id = ?", [$testId]);
echo "\nTestregel entfernt.\n";

echo $fehler === 0
    ? "\n==> ALLE TESTS BESTANDEN.\n"
    : "\n==> $fehler FEHLER!\n";
exit($fehler === 0 ? 0 : 1);
