<?php
/**
 * POST /api/v1/lam/domain-wissen-delete
 *   Body JSON: { id: int }
 *   Loescht einen Domain-Wissens-Eintrag (ohne Verlinkungen anzufassen).
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::can(CAP_LAM)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$raw = file_get_contents('php://input');
$json = $raw ? json_decode($raw, true) : null;
$id = (int)($json['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) Response::error('id fehlt', 400);

$ok = $svc->loescheDomainWissen($id);
if (!$ok) Response::notFound('Eintrag nicht gefunden');
Response::success(['deleted' => $id]);
