<?php
/**
 * Canvas AI Chat — SSE Streaming + History + Completeness Check
 */

use Core\Auth;
use Core\Response;
use Services\AIService;
use Services\CanvasService;
use Services\CanvasAIService;

global $db, $method, $input, $uri;

require_once SERVICES_PATH . '/CanvasService.php';
require_once SERVICES_PATH . '/CanvasAIService.php';

$canvasService = new CanvasService($db);
$canvasAIService = new CanvasAIService($db, $canvasService);

$userId = Auth::id();
$canvasId = (int) ($_GET['canvas_id'] ?? 0);
$subAction = $_GET['sub_action'] ?? null;

if (!$canvasId) {
    Response::error('Canvas-ID erforderlich');
}
if (!$canvasService->canAccess($canvasId, $userId) && !Auth::isAdmin()) {
    Response::forbidden('Kein Zugriff');
}

// ===== GET: AI Message History (alle Messages fuer dieses Canvas) =====
if ($method === 'GET') {
    $messages = $canvasService->getAllAIMessages($canvasId);
    Response::success($messages);
}

// ===== POST: AI Check (sync) =====
if ($subAction === '/ai-check') {
    if ($method !== 'POST') Response::error('Method not allowed', 405);

    $field = $input['field'] ?? 'problem';
    $check = $canvasAIService->checkFieldCompleteness($canvasId, $field);
    Response::success(['check' => $check, 'field' => $field]);
}

// ===== POST: AI Chat Stream (SSE) =====
if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$message = trim($input['message'] ?? '');
// Field wird nur fuer DB-Speicherung genutzt, default 'problem'
$field = $input['field'] ?? 'problem';
if (!in_array($field, CanvasService::FIELDS)) {
    $field = 'problem';
}

if (empty($message)) {
    Response::error('Nachricht erforderlich');
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// KI Kompass nutzt immer Claude Opus (groesstes Modell, 200k Context)
$model = 'claude-opus-4-6';
$provider = 'anthropic';
$apiKey = $settings['anthropic_api_key'] ?? '';

if (empty($apiKey)) {
    // Fallback auf Default-Modell wenn kein Anthropic-Key
    $model = $settings['default_model'] ?? 'gpt-4';
    if (strpos($model, 'claude') !== false) {
        $provider = 'anthropic';
    } elseif (strpos($model, 'gemini') !== false) {
        $provider = 'google';
    } else {
        $provider = 'openai';
    }
    $providerKeys = [
        'openai' => $settings['openai_api_key'] ?? '',
        'anthropic' => $settings['anthropic_api_key'] ?? '',
        'google' => $settings['google_api_key'] ?? '',
    ];
    $apiKey = $providerKeys[$provider] ?? '';
}

if (empty($apiKey)) {
    Response::error('API-Key nicht konfiguriert.');
}

// User-Message in DB speichern
$canvasService->saveAIMessage($canvasId, $field, 'user', $message, $userId);

// Chat-History laden — Claude Opus hat 200k Context, wir koennen grosszuegig sein
// ACTION-Bloecke aus History entfernen damit die KI nicht verwirrt wird
$history = $canvasService->getAllAIMessages($canvasId, 50);
$aiMessages = [];
foreach ($history as $msg) {
    $content = $msg['content'];
    if ($msg['role'] === 'assistant') {
        // ACTION-Bloecke und <<Suggestion>>-Marker entfernen
        $content = preg_replace('/\[ACTION:\w+\][\s\S]*?\[\/ACTION\]/', '', $content);
        $content = preg_replace('/^<<.+?>>$/m', '', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));
        if (empty(trim($content))) {
            $content = '(Karte erstellt/aktualisiert)';
        }
    }
    if (empty(trim($content))) continue;

    // Anthropic verlangt abwechselnd user/assistant — gleiche Rollen zusammenfassen
    $lastIdx = count($aiMessages) - 1;
    if ($lastIdx >= 0 && $aiMessages[$lastIdx]['role'] === $msg['role']) {
        $aiMessages[$lastIdx]['content'] .= "\n\n" . $content;
    } else {
        $aiMessages[] = ['role' => $msg['role'], 'content' => $content];
    }
}

// Anthropic-Pflicht: erste Message = User, letzte Message = User
while (!empty($aiMessages) && $aiMessages[0]['role'] !== 'user') {
    array_shift($aiMessages);
}
while (!empty($aiMessages) && $aiMessages[count($aiMessages) - 1]['role'] !== 'user') {
    array_pop($aiMessages);
}
// Fallback: wenn History leer, nur die aktuelle Message
if (empty($aiMessages)) {
    $aiMessages = [['role' => 'user', 'content' => $message]];
}

// System-Prompt bauen (universell, nicht feld-spezifisch)
$userRole = $canvasAIService->getUserRole($canvasId, $userId);
$systemPrompt = $canvasAIService->buildSystemPrompt($canvasId, $userRole);

// Aktuelle User-Nachricht im System-Prompt hervorheben (gegen Kontext-Verlust)
$systemPrompt .= "\n\nAKTUELLE USER-NACHRICHT (reagiere DIREKT hierauf, stelle eine NEUE Frage zu einem ANDEREN Thema): " . $message;

// AI-Service
$ai = new AIService($apiKey, $provider);
$ai->setModel($model);

// ===== SSE Setup =====
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
header('Content-Encoding: identity');

ignore_user_abort(true);
set_time_limit(300);

function sendCanvasSSE(string $type, array $data = []): void
{
    $data['type'] = $type;
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// Stream starten
$fullContent = '';
$tokensIn = 0;
$tokensOut = 0;

try {
    $result = $ai->chatStream($aiMessages, $systemPrompt, function($token) use (&$fullContent) {
        $fullContent .= $token;
        sendCanvasSSE('token', ['content' => $token]);
    });

    $tokensIn = $result['tokens']['input'] ?? 0;
    $tokensOut = $result['tokens']['output'] ?? 0;

} catch (\Exception $e) {
    sendCanvasSSE('error', ['message' => $e->getMessage()]);
    $fullContent .= "\n[Fehler: " . $e->getMessage() . "]";
}

// Actions aus der Antwort parsen und ausfuehren
$parsed = $canvasAIService->parseAndExecuteActions($fullContent, $canvasId, $userId);

// Action-Events an Frontend senden
foreach ($parsed['actions'] as $actionResult) {
    sendCanvasSSE('action', [
        'action' => $actionResult['action'],
        'data' => $actionResult['data'],
    ]);
}

// Assistant-Message in DB speichern (bereinigter Text)
$canvasService->saveAIMessage(
    $canvasId,
    $field,
    'assistant',
    $parsed['text'] ?: $fullContent,
    null,
    $model,
    $tokensIn,
    $tokensOut
);

sendCanvasSSE('done', [
    'tokens_input' => $tokensIn,
    'tokens_output' => $tokensOut,
    'actions_count' => count($parsed['actions']),
]);

exit;
