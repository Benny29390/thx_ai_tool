<?php
/**
 * Manuelle Risiko-Steuerung pro Plan.
 *
 * POST /api/v1/projektplanner/plan-risiko
 * Body: { plan_id: int, modus: 'auto'|'eskaliert'|'gruen'|'nicht_relevant', notiz?: string }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\AuditLog;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$json = json_decode(file_get_contents('php://input'), true);
$planId = (int)($json['plan_id'] ?? 0);
$modus  = (string)($json['modus'] ?? 'auto');
$notiz  = trim((string)($json['notiz'] ?? ''));

$erlaubt = ['auto', 'eskaliert', 'gruen', 'nicht_relevant'];
if ($planId <= 0) Response::error('plan_id erforderlich', 400);
if (!in_array($modus, $erlaubt, true)) Response::error('Ungueltiger modus', 400);
if (mb_strlen($notiz) > 1000) Response::error('Notiz zu lang (max 1000 Zeichen)', 400);

$db = Database::getInstance();
$exist = $db->queryValue("SELECT id FROM pp_plans WHERE id = ?", [$planId]);
if (!$exist) Response::error('Plan nicht gefunden', 404);

$db->execute(
    "UPDATE pp_plans
     SET risiko_modus = ?, risiko_notiz = ?,
         risiko_set_am = " . ($modus === 'auto' ? 'NULL' : 'NOW()') . "
     WHERE id = ?",
    [$modus, $notiz !== '' ? $notiz : null, $planId]
);
AuditLog::record('pp_plan', (string)$planId, 'risiko.gesetzt', [
    'modus' => $modus,
    'notiz' => mb_substr($notiz, 0, 200),
]);

Response::success(['plan_id' => $planId, 'modus' => $modus, 'notiz' => $notiz]);
