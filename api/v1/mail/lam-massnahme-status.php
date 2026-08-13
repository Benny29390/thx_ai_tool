<?php
/**
 * POST /mail/lam-massnahme-status
 * Body: { mail_id, neuer_status }
 *
 * Setzt den Status der mit dieser Mail verknüpften Maßnahme.
 * Sicherheits-Check: Maßnahme muss tatsächlich mit der Mail verknüpft sein.
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$mailId = (int)($input['mail_id'] ?? 0);
$status = trim((string)($input['neuer_status'] ?? ''));
if ($mailId <= 0 || $status === '') Response::error('mail_id + neuer_status erforderlich', 400);

$db = Database::getInstance();
$massnahmeId = $db->queryValue(
    "SELECT ziel_id FROM mail_lam_verknuepfung WHERE mail_id = ? AND typ = 'massnahme' LIMIT 1",
    [$mailId]
);
if (!$massnahmeId) Response::error('Keine Maßnahme mit dieser Mail verknüpft.', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new \Services\LamService($db);

try {
    $svc->aktualisiereMassnahmeFeld($massnahmeId, 'status', $status);
    Response::success(['massnahme_id' => $massnahmeId, 'neuer_status' => $status]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Status-Update fehlgeschlagen: ' . $e->getMessage(), 500);
}
