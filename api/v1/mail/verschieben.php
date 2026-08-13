<?php
/** POST /mail/verschieben Body: { ids: [int], ziel: int|'posteingang'|'archiv'|'spam'|'papierkorb' } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$ids = $input['ids'] ?? [];
$ziel = $input['ziel'] ?? null;
if (!is_array($ids) || count($ids) === 0 || $ziel === null) Response::error('ids + ziel erforderlich', 400);

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());
$verschoben = 0;
foreach ($ids as $mid) {
    $mid = (int)$mid;
    if ($mid <= 0) continue;
    try {
        $svc->verschiebeMail($mid, is_numeric($ziel) ? (int)$ziel : (string)$ziel);
        $verschoben++;
    } catch (\Throwable $e) { /* skip */ }
}
Response::success(['verschoben' => $verschoben]);
