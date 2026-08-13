<?php
/**
 * „Mit Claude verfeinern" — Hybrid-Knopf im Antwort-Editor.
 *
 * Nimmt den aktuellen (lokal erzeugten oder editierten) Antwortentwurf und laesst ihn
 * bewusst von Claude in der Cloud verbessern. Der Nutzer entscheidet pro Mail.
 *
 * POST { mail_id, entwurf, auftrag? }
 */

use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

$mailId  = (int) ($input['mail_id'] ?? 0);
$entwurf = trim((string) ($input['entwurf'] ?? ''));
$auftrag = trim((string) ($input['auftrag'] ?? ''));
$kontoId = (int) ($input['konto_id'] ?? 0);   // fuer freien Text (neue Mail, mail_id=0)

// mail_id=0 ist erlaubt (neue Mail); dann muss der Entwurf da sein.
if ($entwurf === '')   Response::error('Kein Entwurf zum Verfeinern übergeben.');

set_time_limit(120);
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

require_once SERVICES_PATH . '/MailKlassifikationService.php';

try {
    $svc = new \Services\MailKlassifikationService($db);
    $r = $svc->verfeinereAntwort($mailId, $entwurf, $auftrag, $kontoId ?: null);
    Response::success($r, 'Mit Claude verfeinert.');
} catch (\Throwable $e) {
    Response::error($e->getMessage());
}
