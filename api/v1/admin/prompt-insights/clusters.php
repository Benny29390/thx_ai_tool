<?php
/**
 * Prompt-Insights — Clustering API
 *
 * POST  /admin/prompt-insights/embed                    Embeddings für offene Initialprompts erstellen
 * POST  /admin/prompt-insights/recluster                Clustering neu rechnen (Body: threshold?)
 * GET   /admin/prompt-insights/clusters                 Liste der Cluster
 * GET   /admin/prompt-insights/clusters/{id}/samples    Sample-Prompts des Clusters
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
$clusterId = (int)($_GET['cluster_id'] ?? 0);

if ($action === 'embed' && $method === 'POST') {
    try {
        $res = $svc->embedInitialPrompts($userId, 500);
        Response::success($res, "Embeddings: {$res['done']} erzeugt, {$res['skipped']} übersprungen");
    } catch (\Throwable $e) {
        Response::error('Embed-Lauf fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($action === 'recluster' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $threshold = max(0.5, min(0.95, (float)($body['threshold'] ?? 0.78)));
    try {
        $res = $svc->recluster($userId, $threshold);
        Response::success($res, "Cluster: {$res['clusters']} gebildet, {$res['assigned']} Prompts zugeordnet (Threshold {$threshold})");
    } catch (\Throwable $e) {
        Response::error('Re-Cluster fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($action === 'list' && $method === 'GET') {
    Response::success(['clusters' => $svc->listClusters($userId)]);
}

if ($action === 'samples' && $method === 'GET' && $clusterId) {
    Response::success(['samples' => $svc->getClusterSamples($clusterId, $userId, 30)]);
}

Response::error('Unbekannte Aktion oder Methode', 405);
