<?php
/**
 * POST /lam/domain-tags-set
 * Body: { domain_id, tag_ids: [int] }
 * Setzt die Tags einer Domain als Replace.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$domainId = trim((string)($input['domain_id'] ?? ''));
$tagIds = $input['tag_ids'] ?? [];
if ($domainId === '') Response::error('domain_id erforderlich', 400);
if (!is_array($tagIds)) $tagIds = [];

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->setzeDomainTags($domainId, $tagIds));
} catch (\Throwable $e) {
    Response::error('Speichern fehlgeschlagen', 500);
}
