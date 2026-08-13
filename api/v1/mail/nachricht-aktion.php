<?php
/** POST /mail/nachricht-aktion Body: { id, aktion: 'gelesen'|'ungelesen'|'markiert_toggle'|'status_setzen'|'loeschen', wert? } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
$aktion = (string)($input['aktion'] ?? '');
$wert = $input['wert'] ?? null;
if ($id <= 0 || $aktion === '') Response::error('id + aktion erforderlich', 400);

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());

try {
    switch ($aktion) {
        case 'gelesen':
            $svc->setzeGelesen($id, true);
            Response::success(['ok' => true]);
        case 'ungelesen':
            $svc->setzeGelesen($id, false);
            Response::success(['ok' => true]);
        case 'markiert_toggle':
            $neu = $svc->toggleMarkiert($id);
            Response::success(['markiert' => $neu]);
        case 'status_setzen':
            $svc->setzeStatus($id, (string)$wert);
            Response::success(['ok' => true]);
        case 'loeschen':
            $svc->loescheMail($id);
            Response::success(['ok' => true]);
        case 'als_spam':
            $svc->markiereAlsSpam($id);
            Response::success(['ok' => true]);
        case 'kein_spam':
            $svc->markiereAlsKeinSpam($id);
            Response::success(['ok' => true]);
        default:
            Response::error('Unbekannte Aktion', 400);
    }
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Aktion fehlgeschlagen: ' . $e->getMessage(), 500);
}
