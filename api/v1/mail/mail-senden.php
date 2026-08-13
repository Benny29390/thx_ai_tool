<?php
/**
 * POST /mail/mail-senden
 * Body: { konto_id, empfaenger, betreff, text,
 *         cc?: [], bcc?: [],
 *         anbieter_id?, kontakt_id?, massnahme_id?, vorschlagsliste_eintrag_id?,
 *         anhaenge?: [{pfad, name, mime}] }
 *
 * Versendet eine komplett neue Mail aus dem LAM heraus (Compose-Modal).
 * Legt automatisch lam_kommunikation-Eintrag (typ mail_ausgang) an, wenn anbieter_id gesetzt.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailAntwortService;
use Services\MailKontoService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (empty($input['konto_id']))      Response::error('konto_id erforderlich', 400);
if (empty($input['empfaenger']))    Response::error('Empfänger erforderlich', 400);
if (empty($input['betreff']))       Response::error('Betreff erforderlich', 400);
if (empty($input['text']))          Response::error('Mail-Text erforderlich', 400);

require_once SERVICES_PATH . '/MailKontoService.php';
require_once SERVICES_PATH . '/MailAntwortService.php';
$db = Database::getInstance();
$konten = new MailKontoService($db);
$svc = new MailAntwortService($db, $konten);

try {
    $input['user_id'] = Auth::user()['id'] ?? null;
    $r = $svc->sendeNeueMail($input);
    Response::success($r, 'Mail gesendet');
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Versand fehlgeschlagen: ' . $e->getMessage(), 500);
}
