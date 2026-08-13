<?php
/**
 * Dual Compare API — Vergleicht zwei Dual-Mode-Antworten mit einem 3. Modell (SSE)
 */

use Core\Auth;
use Core\Response;
use Services\AIService;
use Services\UsageTracker;

global $db, $method, $input, $uri;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$conversationId = (int) ($_GET['id'] ?? 0);
$userId = Auth::id();

// Conversation laden + Zugriffspruefung
$conv = $db->queryOne("SELECT * FROM chat_conversations WHERE id = ?", [$conversationId]);
if (!$conv) {
    Response::notFound('Konversation nicht gefunden');
}
if ($conv['user_id'] !== $userId) {
    Response::error('Kein Zugriff', 403);
}

$dualGroupId = $input['dual_group_id'] ?? '';
$compModel = $input['comparison_model'] ?? '';

if (empty($dualGroupId) || empty($compModel)) {
    Response::error('dual_group_id und comparison_model erforderlich');
}

// Nachrichten der Dual-Gruppe laden
$messages = $db->query(
    "SELECT role, content, model_used, dual_slot FROM chat_conversation_messages
     WHERE conversation_id = ? AND dual_group_id = ?
     ORDER BY created_at ASC",
    [$conversationId, $dualGroupId]
);

$userContent = '';
$answerA = '';
$answerB = '';
$modelNameA = '';
$modelNameB = '';

foreach ($messages as $m) {
    if ($m['role'] === 'user') {
        $userContent = $m['content'];
    } elseif ($m['dual_slot'] === 'a') {
        $answerA = $m['content'];
        $modelNameA = $m['model_used'];
    } elseif ($m['dual_slot'] === 'b') {
        $answerB = $m['content'];
        $modelNameB = $m['model_used'];
    }
}

if (empty($answerA) || empty($answerB)) {
    Response::error('Beide Antworten muessen vorhanden sein');
}

// Settings + API-Key
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

if (strpos($compModel, 'claude') !== false) {
    $provider = 'anthropic';
} elseif (strpos($compModel, 'gemini') !== false) {
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

if (empty($apiKey)) {
    Response::error('API-Key fuer ' . $provider . ' nicht konfiguriert');
}

// Display-Names holen
$nameA = $db->queryOne("SELECT display_name FROM ai_models WHERE model_id = ?", [$modelNameA]);
$nameB = $db->queryOne("SELECT display_name FROM ai_models WHERE model_id = ?", [$modelNameB]);
$displayA = $nameA['display_name'] ?? $modelNameA;
$displayB = $nameB['display_name'] ?? $modelNameB;

// AI-Service
require_once SERVICES_PATH . '/UsageTracker.php';
$ai = new AIService($apiKey, $provider);
$ai->setModel($compModel);
$tracker = new UsageTracker($db);

// SSE Setup
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
set_time_limit(120);

function sendSSE(string $type, array $data = []): void
{
    $data['type'] = $type;
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// Vergleichs-Prompt aus der zentralen Prompt-Verwaltung (versioniert/pflegbar unter
// /admin/settings?tab=prompts → dual_compare).
$systemPrompt = \Services\SystemPromptService::get('dual_compare');

$userPrompt = "Der User fragte:\n\n" . $userContent . "\n\n---\n\n";
$userPrompt .= "**Antwort A** ({$displayA}):\n\n" . $answerA . "\n\n---\n\n";
$userPrompt .= "**Antwort B** ({$displayB}):\n\n" . $answerB;

$reqStart = microtime(true);
$ttftMs = null;
try {
    $fullContent = '';

    $result = $ai->chatStream(
        [['role' => 'user', 'content' => $userPrompt]],
        $systemPrompt,
        function ($token) use (&$fullContent, &$ttftMs, $reqStart) {
            if ($ttftMs === null) {
                $ttftMs = (int) round((microtime(true) - $reqStart) * 1000);
            }
            $fullContent .= $token;
            sendSSE('token', ['content' => $token]);
        }
    );

    $totalMs = (int) round((microtime(true) - $reqStart) * 1000);
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // Vergleich als Message speichern
    $msgId = $db->insert('chat_conversation_messages', [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => $fullContent,
        'model_used' => $compModel,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput,
        'dual_group_id' => $dualGroupId,
        'dual_slot' => 'comparison',
    ]);

    // Usage tracken
    try {
        $tracker->log(
            $conv['customer_id'],
            $userId,
            'chat_conversation',
            $compModel,
            $provider,
            $tokensInput,
            $tokensOutput,
            str_word_count($fullContent),
            ['conversation_id' => $conversationId, 'dual_comparison' => true]
        );
    } catch (\Exception $e) {}

    // Performance-/Detail-Log — genau wie eine normale Chat-Antwort, damit der Vergleich
    // nachvollziehbar ist (Info-Panel, Gold-Standard-Optimierung).
    \Services\LlmRequestLog::record([
        'provider' => $provider,
        'model' => $compModel,
        'use_case' => 'dual_compare',
        'user_id' => $userId,
        'customer_id' => $conv['customer_id'] ?? null,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput,
        'ttft_ms' => $ttftMs,
        'total_ms' => $totalMs,
        'success' => true,
        'detail' => [
            'conversation_id' => $conversationId,
            'message_id' => $msgId,
            'system_prompt' => $systemPrompt,
            'user_message' => $userPrompt,
            'response_text' => $fullContent,
            'rag_chunks' => [],
        ],
    ]);

    sendSSE('done', [
        'message_id' => $msgId,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput,
        'model' => $compModel,
    ]);

} catch (\Exception $e) {
    \Services\LlmRequestLog::record([
        'provider' => $provider,
        'model' => $compModel,
        'use_case' => 'dual_compare',
        'user_id' => $userId,
        'customer_id' => $conv['customer_id'] ?? null,
        'ttft_ms' => $ttftMs,
        'total_ms' => (int) round((microtime(true) - $reqStart) * 1000),
        'success' => false,
        'error_message' => mb_substr($e->getMessage(), 0, 1000),
    ]);
    sendSSE('error', ['message' => $e->getMessage()]);
}
