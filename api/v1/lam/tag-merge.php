<?php
/**
 * POST /lam/tag-merge
 * Body: { source_id, target_id }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$source = (int)($input['source_id'] ?? 0);
$target = (int)($input['target_id'] ?? 0);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->mergeTag($source, $target));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Merge fehlgeschlagen', 500);
}
