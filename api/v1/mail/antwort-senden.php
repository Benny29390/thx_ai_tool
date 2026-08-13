<?php
/**
 * POST /mail/antwort-senden
 * Body: { id, finaler_text, betreff?, empfaenger?, cc?, bcc?, ki_vorschlag?, vorlage_id? }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailAntwortService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
$text = trim((string)($input['finaler_text'] ?? ''));
$betreff = trim((string)($input['betreff'] ?? ''));
if ($id <= 0 || $text === '' || $betreff === '') Response::error('id, finaler_text, betreff erforderlich', 400);

// Anhänge: erwartet Liste von [{pfad, name, mime}] aus antwort-anhang-upload
// Sicherheits-Check: nur Pfade aus dem _uploads/-Ordner des aktuellen Users akzeptieren
$anhaenge = [];
if (!empty($input['anhaenge']) && is_array($input['anhaenge'])) {
    $userId = Auth::user()['id'] ?? 0;
    $erlaubterBasis = realpath('/var/www/storage/mail/anhaenge/_uploads/' . (int)$userId);
    foreach ($input['anhaenge'] as $a) {
        $pfad = $a['pfad'] ?? '';
        $real = realpath($pfad);
        if ($real && $erlaubterBasis && strpos($real, $erlaubterBasis) === 0 && is_readable($real)) {
            $anhaenge[] = ['pfad' => $real, 'name' => $a['name'] ?? basename($real), 'mime' => $a['mime'] ?? null];
        }
    }
}

$opts = [
    'ki_vorschlag' => $input['ki_vorschlag'] ?? null,
    'vorlage_id' => $input['vorlage_id'] ?? null,
    'cc' => is_array($input['cc'] ?? null) ? $input['cc'] : [],
    'bcc' => is_array($input['bcc'] ?? null) ? $input['bcc'] : [],
    'empfaenger' => $input['empfaenger'] ?? null,
    'user_id' => Auth::user()['id'] ?? null,
    'auto_versendet' => false,
    'anhaenge' => $anhaenge,
];

require_once SERVICES_PATH . '/MailAntwortService.php';
$svc = new MailAntwortService(Database::getInstance());

try {
    $r = $svc->sendeAntwort($id, $text, $betreff, $opts);
    Response::success($r);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Versand fehlgeschlagen: ' . $e->getMessage(), 500);
}
