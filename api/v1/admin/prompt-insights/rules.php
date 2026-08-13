<?php
/**
 * Prompt-Insights — Rules API + Layer-4-Derivation + Export
 *
 * GET    /admin/prompt-insights/rules[?status=&cluster_id=]   Liste
 * POST   /admin/prompt-insights/rules                          Neue manuelle Regel
 * PUT    /admin/prompt-insights/rules/{id}                     Edit/Status ändern
 * DELETE /admin/prompt-insights/rules/{id}                     Löschen
 * POST   /admin/prompt-insights/rules/derive/{cluster_id}     Layer-4: LLM-basiert ableiten
 * GET    /admin/prompt-insights/rules/export?format=markdown|json
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

$method    = $_SERVER['REQUEST_METHOD'];
$action    = $_GET['action'] ?? '';
$ruleId    = (int)($_GET['rule_id'] ?? 0);
$clusterId = (int)($_GET['cluster_id'] ?? 0);

if ($action === 'derive' && $method === 'POST' && $clusterId) {
    try {
        $res = $svc->deriveRulesForCluster($clusterId, $userId);
        Response::success($res, "Cluster {$clusterId}: {$res['created']} Regelvorschläge erstellt");
    } catch (\Throwable $e) {
        Response::error('Regelableitung fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($action === 'export' && $method === 'GET') {
    $format = ($_GET['format'] ?? 'markdown') === 'json' ? 'json' : 'markdown';
    $content = $svc->exportRules($userId, $format);
    header('Content-Type: ' . ($format === 'json' ? 'application/json; charset=UTF-8' : 'text/markdown; charset=UTF-8'));
    header('Content-Disposition: attachment; filename="prompt-spielregeln-' . date('Y-m-d') . '.' . ($format === 'json' ? 'json' : 'md') . '"');
    echo $content;
    exit;
}

if ($method === 'GET') {
    $status    = $_GET['status']    ?? null;
    $clusterId = !empty($_GET['cluster_id']) ? (int)$_GET['cluster_id'] : null;
    Response::success(['rules' => $svc->listRules($userId, $status, $clusterId)]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $text = trim((string)($body['text'] ?? ''));
    if (mb_strlen($text) < 5) Response::error('Text zu kurz');
    $clusterId = !empty($body['cluster_id']) ? (int)$body['cluster_id'] : null;
    $id = $svc->createRule($userId, $text, $clusterId);
    Response::success(['id' => $id], 'Regel erstellt');
}

if ($method === 'PUT' && $ruleId) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $ok = $svc->updateRule($ruleId, $userId, $body);
    if (!$ok) Response::error('Update fehlgeschlagen oder Regel nicht gefunden');
    Response::success(['id' => $ruleId]);
}

if ($method === 'DELETE' && $ruleId) {
    $ok = $svc->deleteRule($ruleId, $userId);
    if (!$ok) Response::error('Löschen fehlgeschlagen');
    Response::success(['id' => $ruleId]);
}

Response::error('Unbekannte Aktion oder Methode', 405);
