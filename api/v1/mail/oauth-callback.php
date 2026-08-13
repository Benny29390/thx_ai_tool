<?php
/**
 * Rueckleitung von Microsoft nach der Anmeldung.
 * Prueft den `state`, tauscht den Code gegen Tokens und schickt den Nutzer
 * zurueck in die Einstellungen — mit klarer Rueckmeldung.
 */

global $db;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$ziel = '/admin/settings?tab=smtp';

/** Zurueck zu den Einstellungen, mit Meldung. */
$zurueck = function (string $status, string $text) use ($ziel) {
    header('Location: ' . $ziel . '&oauth=' . urlencode($status) . '&meldung=' . urlencode($text));
    exit;
};

// Microsoft meldet Fehler ueber die Adresszeile (z.B. abgebrochene Zustimmung)
if (!empty($_GET['error'])) {
    $zurueck('fehler', (string) ($_GET['error_description'] ?? $_GET['error']));
}

$code  = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');
$erwartet = $_SESSION['mail_oauth_state'] ?? null;
unset($_SESSION['mail_oauth_state']);   // nur einmal gueltig

if ($code === '' || $state === '' || !is_array($erwartet)) {
    $zurueck('fehler', 'Unvollständige Rückmeldung von Microsoft.');
}
// Zeitliche Begrenzung: ein alter State darf nicht ewig gelten.
if (!hash_equals((string) $erwartet['state'], $state) || (time() - (int) $erwartet['ts']) > 900) {
    $zurueck('fehler', 'Sicherheitsprüfung fehlgeschlagen (state). Bitte erneut versuchen.');
}

require_once SERVICES_PATH . '/MailOAuthService.php';

try {
    $oauth = new \Services\MailOAuthService($db);
    $oauth->tauscheCode((int) $erwartet['konto_id'], $code);
    $zurueck('ok', 'Postfach erfolgreich mit Microsoft verbunden.');
} catch (\Throwable $e) {
    $zurueck('fehler', $e->getMessage());
}
