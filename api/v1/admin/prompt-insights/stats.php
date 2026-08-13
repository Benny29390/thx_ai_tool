<?php
/**
 * Prompt-Insights — Statistik + Browser API
 *
 * GET /admin/prompt-insights/stats[?import_id=]                Layer-2-Aggregat
 * GET /admin/prompt-insights/browse[?source=|import_id=|role=|initial_only=1|search=|cluster_id=|limit=|offset=]
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PromptInsightsService;

if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();

require_once SERVICES_PATH . '/PromptInsightsService.php';
$svc = new PromptInsightsService(Database::getInstance());
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$action = $_GET['action'] ?? 'stats';

if ($action === 'stats') {
    $importId = !empty($_GET['import_id']) ? (int)$_GET['import_id'] : null;
    Response::success($svc->getStats($userId, $importId));
}

if ($action === 'browse') {
    $filter = [
        'source'        => $_GET['source'] ?? null,
        'import_id'     => $_GET['import_id'] ?? null,
        'role'          => $_GET['role'] ?? null,
        'initial_only'  => !empty($_GET['initial_only']),
        'search'        => $_GET['search'] ?? null,
        'cluster_id'    => $_GET['cluster_id'] ?? null,
    ];
    $limit  = min(500, max(10, (int)($_GET['limit']  ?? 100)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    Response::success($svc->browseMessages($userId, $filter, $limit, $offset));
}

Response::error('Unbekannte Aktion', 405);
