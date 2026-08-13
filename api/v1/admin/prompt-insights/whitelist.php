<?php
/**
 * Prompt-Insights — Whitelist API (Eigennamen-Anonymisierung)
 *
 * GET    /admin/prompt-insights/whitelist                  Eigene Liste
 * POST   /admin/prompt-insights/whitelist                  Neuer Eintrag (Body: {original, placeholder?})
 * DELETE /admin/prompt-insights/whitelist/{id}             Eintrag löschen
 * POST   /admin/prompt-insights/whitelist/init             Aus Kunden + Team-Members initial befüllen
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PromptInsightsService;

if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();

require_once SERVICES_PATH . '/PromptInsightsService.php';
$svc = new PromptInsightsService(Database::getInstance());
$db = Database::getInstance();
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$method  = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? '';
$entryId = (int)($_GET['entry_id'] ?? 0);

if ($method === 'GET') {
    Response::success(['entries' => $svc->loadWhitelist($userId)]);
}

if ($action === 'init' && $method === 'POST') {
    $added = $svc->initWhitelistIfEmpty($userId);
    Response::success(['added' => $added], "$added Eigennamen aus Kunden + Team übernommen");
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $original = trim((string)($body['original'] ?? ''));
    $placeholder = trim((string)($body['placeholder'] ?? '<NAME>'));
    if (mb_strlen($original) < 2) Response::error('Mindestens 2 Zeichen');
    if (mb_strlen($original) > 200) Response::error('Max. 200 Zeichen');
    try {
        $id = $db->insert('pi_whitelist', [
            'user_id'     => $userId,
            'original'    => $original,
            'placeholder' => $placeholder !== '' ? mb_substr($placeholder, 0, 80) : '<NAME>',
            'source'      => 'manuell',
        ]);
        Response::success(['id' => $id], 'Eintrag hinzugefügt');
    } catch (\Throwable $e) {
        Response::error('Eintrag bereits vorhanden oder Fehler: ' . $e->getMessage());
    }
}

if ($method === 'DELETE') {
    if (!$entryId) Response::error('entry_id fehlt');
    $deleted = $db->execute("DELETE FROM pi_whitelist WHERE id = ? AND user_id = ?", [$entryId, $userId]);
    if (!$deleted) Response::notFound('Eintrag nicht gefunden');
    Response::success(['deleted' => $entryId]);
}

Response::error('Methode nicht unterstützt', 405);
