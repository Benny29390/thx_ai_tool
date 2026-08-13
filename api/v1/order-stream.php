<?php
/**
 * Order Streaming API - SSE Streaming fuer Briefing/Artikel-Generierung
 * Streamt AI-Antworten Token fuer Token zum Browser
 */

use Core\Auth;
use Core\Response;
use Services\AIService;
use Services\ContextService;
use Services\UsageTracker;

global $db, $method, $input, $uri;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Route parsen
if (!preg_match('#^/orders/(\d+)/stream/(briefing|briefing-interview|briefing-chat|article|article-chat|inline-edit)$#', $uri, $matches)) {
    Response::notFound('Endpunkt nicht gefunden');
}

$orderId = (int) $matches[1];
$action = $matches[2];

// Order laden und Zugriff pruefen
$order = $db->queryOne(
    "SELECT o.*, ctx.name as context_name FROM orders o
     LEFT JOIN contexts ctx ON o.context_id = ctx.id
     WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    Response::notFound('Auftrag nicht gefunden');
}

if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
    Response::forbidden();
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// Modell bestimmen
$model = $order['model'] ?? $input['model'] ?? null;
if (!$model) {
    $model = $settings['default_model'] ?? 'gpt-4';
}

// API-Key pruefen
$provider = (strpos($model, 'claude') !== false) ? 'anthropic' : 'openai';
$apiKey = $provider === 'anthropic' ? ($settings['anthropic_api_key'] ?? '') : ($settings['openai_api_key'] ?? '');

if (empty($apiKey)) {
    Response::error('API-Key fuer ' . $provider . ' nicht konfiguriert. Bitte unter Einstellungen hinterlegen.');
}

// Services initialisieren
require_once SERVICES_PATH . '/ContextService.php';
require_once SERVICES_PATH . '/UsageTracker.php';

$ai = new AIService($apiKey, $provider);
$ai->setModel($model);
$contextService = new ContextService($db);
$tracker = new UsageTracker($db);

// ==========================================
// SSE Setup - Ab hier wird gestreamt
// ==========================================

// Alle Output Buffer leeren
while (ob_get_level()) {
    ob_end_clean();
}

// SSE Headers setzen
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Apache mod_deflate deaktivieren (wuerde SSE puffern)
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
header('Content-Encoding: identity');

// Ignore user abort damit wir den Content noch speichern koennen
ignore_user_abort(true);
set_time_limit(300);

/**
 * SSE Event senden
 */
function sendSSE(string $type, array $data = []): void
{
    $data['type'] = $type;
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

try {
    switch ($action) {
        case 'briefing':
            streamBriefing($order, $db, $ai, $contextService, $tracker, $model, $provider, $orderId);
            break;

        case 'briefing-interview':
            $message = trim($input['message'] ?? '');
            streamBriefingInterview($order, $db, $ai, $contextService, $tracker, $model, $provider, $orderId, $message);
            break;

        case 'briefing-chat':
            $message = trim($input['message'] ?? '');
            if (empty($message)) {
                sendSSE('error', ['message' => 'Nachricht erforderlich']);
                exit;
            }
            streamBriefingChat($order, $db, $ai, $contextService, $tracker, $model, $provider, $orderId, $message);
            break;

        case 'article':
            streamArticle($order, $db, $ai, $contextService, $tracker, $model, $provider, $orderId);
            break;

        case 'article-chat':
            $message = trim($input['message'] ?? '');
            if (empty($message)) {
                sendSSE('error', ['message' => 'Nachricht erforderlich']);
                exit;
            }
            $currentArticle = $input['current_article'] ?? $order['article_content'] ?? '';
            streamArticleChat($order, $db, $ai, $contextService, $tracker, $model, $provider, $orderId, $message, $currentArticle);
            break;

        case 'inline-edit':
            $selectedText = trim($input['selected_text'] ?? '');
            if (empty($selectedText)) {
                sendSSE('error', ['message' => 'Kein Text ausgewählt']);
                exit;
            }
            $instruction = trim($input['instruction'] ?? 'Schreibe den Text um');
            $contextBefore = $input['context_before'] ?? '';
            $contextAfter = $input['context_after'] ?? '';
            streamInlineEdit($order, $db, $ai, $contextService, $tracker, $model, $provider, $orderId, $selectedText, $instruction, $contextBefore, $contextAfter);
            break;
    }
} catch (\Exception $e) {
    sendSSE('error', ['message' => $e->getMessage()]);
}

exit;

// ==========================================
// Streaming-Funktionen
// ==========================================

/**
 * Briefing-Interview: KI stellt gezielte Fragen basierend auf Kontext
 * Wenn $userMessage leer ist, startet das Interview (erste Frage).
 * Sonst antwortet die KI auf die Antwort und stellt ggf. weitere Fragen.
 */
function streamBriefingInterview(array $order, $db, AIService $ai, ContextService $contextService, UsageTracker $tracker, string $model, string $provider, int $orderId, string $userMessage): void
{
    $ai->setMaxTokens(2048);

    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein erfahrener Content-Stratege und führst ein kurzes Interview, um ein Artikel-Briefing vorzubereiten.\n\n";
    $systemPrompt .= "Der Artikel-Titel lautet: \"{$order['title']}\"\n\n";

    if ($artifactsText) {
        $systemPrompt .= "=== AKTIVE ARTEFAKTE (bereits hinterlegte Vorgaben) ===\n";
        $systemPrompt .= $artifactsText . "\n\n";
        $systemPrompt .= "UMGANG MIT ARTEFAKTEN:\n";
        $systemPrompt .= "- Die obigen Artefakte enthalten bereits festgelegte Vorgaben (Regeln, Wissen, Autorenstil etc.).\n";
        $systemPrompt .= "- Informationen die durch Artefakte bereits abgedeckt sind (z.B. Tonalität, Ansprache, Stilregeln), musst du NICHT mehr erfragen.\n";
        $systemPrompt .= "- Beginne deine ERSTE Nachricht mit einer kurzen Zusammenfassung (2-3 Sätze), was du aus den Artefakten bereits weisst und als gegeben betrachtest.\n";
        $systemPrompt .= "- Stelle danach nur Fragen zu Themen, die die Artefakte NICHT abdecken (z.B. spezifischer Artikelfokus, Zielgruppe für diesen konkreten Artikel, gewünschte Länge).\n";
        $systemPrompt .= "- Wenn ein Artefakt eine Vorgabe enthält (z.B. 'Du-Ansprache'), erwähne das als bereits festgelegt, nicht als offene Frage.\n\n";
    }

    $systemPrompt .= "DEINE AUFGABE:\n";
    $systemPrompt .= "- Stelle gezielte Fragen, um alle nötigen Infos für ein gutes Briefing zu sammeln.\n";
    $systemPrompt .= "- Überspringe Themen die bereits durch Artefakte beantwortet sind.\n";
    $systemPrompt .= "- WICHTIG: Stelle pro Nachricht NUR EINE Frage. Nicht mehrere auf einmal!\n";
    $systemPrompt .= "- Die Frage darf kurz erläutert werden, aber es bleibt EINE Frage pro Nachricht.\n";
    $systemPrompt .= "- Mögliche offene Themen (nur wenn nicht bereits durch Artefakte abgedeckt): Zielgruppe, Kernbotschaft, gewünschte Länge, besondere Schwerpunkte, wo der Artikel erscheinen soll, Call-to-Action.\n";
    $systemPrompt .= "- Wenn aus Artefakten z.B. Produkte, Zielgruppen oder Stilregeln bekannt sind, fasse das kurz zusammen und frage nur nach Ergänzungen.\n";
    $systemPrompt .= "- Antworte immer als normaler Chat-Text (KEIN HTML, keine Überschriften). Kurz und freundlich.\n";
    $systemPrompt .= "- Wenn der Nutzer genug Infos gegeben hat oder sagt 'fertig'/'reicht', bestätige kurz dass du alle Infos hast.\n";
    $systemPrompt .= "\n";
    $systemPrompt .= "ANTWORT-VORSCHLÄGE:\n";
    $systemPrompt .= "- Biete am Ende jeder Frage 2-4 konkrete Antwort-Vorschläge an.\n";
    $systemPrompt .= "- Formatiere die Vorschläge als <<Vorschlag>> (in doppelten spitzen Klammern).\n";
    $systemPrompt .= "- Die Vorschläge sollen kurz und klickbar sein (max. 5-6 Wörter pro Vorschlag).\n";
    $systemPrompt .= "- Setze die Vorschläge ans Ende deiner Nachricht, jeweils auf einer eigenen Zeile.\n";
    $systemPrompt .= "- Beispiel:\n";
    $systemPrompt .= "  Wo soll der Artikel erscheinen?\n";
    $systemPrompt .= "  <<Unternehmensblog>>\n";
    $systemPrompt .= "  <<LinkedIn-Artikel>>\n";
    $systemPrompt .= "  <<Newsletter>>\n";

    // Chat-History laden
    $chatHistory = $db->query(
        "SELECT role, content FROM order_chat_messages
         WHERE order_id = ? AND phase = 'briefing'
         ORDER BY created_at ASC",
        [$orderId]
    );

    $messages = [];
    foreach ($chatHistory as $msg) {
        if ($msg['role'] !== 'system') {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }

    // Erste Nachricht oder Folgeantwort
    if (empty($userMessage)) {
        $messages[] = ['role' => 'user', 'content' => "Ich möchte einen Artikel erstellen mit dem Titel: \"{$order['title']}\". Bitte stelle mir gezielte Fragen, damit du ein gutes Briefing erstellen kannst."];
        $userContentForDB = "Briefing-Interview gestartet für: \"{$order['title']}\"";
    } else {
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        $userContentForDB = $userMessage;
    }

    // Streaming starten
    $result = $ai->chatStream($messages, $systemPrompt, function($token) {
        sendSSE('token', ['content' => $token]);
    });

    $responseText = $result['content'];
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // Chat-Nachrichten speichern
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'briefing',
        'role' => 'user',
        'content' => $userContentForDB,
        'tokens_used' => 0,
        'model_used' => $model
    ]);
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'briefing',
        'role' => 'assistant',
        'content' => $responseText,
        'tokens_used' => ($tokensInput + $tokensOutput),
        'model_used' => $model
    ]);

    // Usage tracken
    $tracker->log(
        $order['customer_id'],
        Auth::id(),
        'order_briefing_interview',
        $model,
        $provider,
        $tokensInput,
        $tokensOutput,
        0,
        ['order_id' => $orderId]
    );

    sendSSE('done', [
        'response' => $responseText,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput
    ]);
}

/**
 * Briefing generieren mit Streaming - nutzt Chat-History aus dem Interview
 */
function streamBriefing(array $order, $db, AIService $ai, ContextService $contextService, UsageTracker $tracker, string $model, string $provider, int $orderId): void
{
    $ai->setMaxTokens(4096);

    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    // Chat-History laden (Interview-Verlauf)
    $chatHistory = $db->query(
        "SELECT role, content FROM order_chat_messages
         WHERE order_id = ? AND phase = 'briefing'
         ORDER BY created_at ASC",
        [$orderId]
    );

    $interviewText = "";
    foreach ($chatHistory as $msg) {
        $label = $msg['role'] === 'user' ? 'Nutzer' : 'Berater';
        $interviewText .= $label . ": " . $msg['content'] . "\n\n";
    }

    $systemPrompt = "Du bist ein erfahrener Content-Stratege. Erstelle ein detailliertes Briefing für einen Artikel.\n\n";
    $systemPrompt .= "Das Briefing soll enthalten:\n";
    $systemPrompt .= "1. Arbeitstitel\n";
    $systemPrompt .= "2. Kernbotschaft (1-2 Sätze)\n";
    $systemPrompt .= "3. Zielgruppe\n";
    $systemPrompt .= "4. Gliederung mit Überschriften (H2/H3) und kurzer Beschreibung je Abschnitt\n";
    $systemPrompt .= "5. Empfohlene Wortanzahl\n";
    $systemPrompt .= "6. Tonalität und Stil\n";
    $systemPrompt .= "7. Keywords (falls relevant)\n\n";
    $systemPrompt .= "Formatiere das Briefing als sauberes HTML mit Überschriften, Listen und Absätzen.\n";
    $systemPrompt .= "WICHTIG: Gib NUR das HTML aus, KEINE Markdown-Code-Fences (kein ```html oder ```). Direkt mit dem HTML beginnen.\n\n";

    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    if (!empty($interviewText)) {
        $systemPrompt .= "=== INTERVIEW MIT DEM NUTZER ===\n" . $interviewText . "\n\n";
        $systemPrompt .= "Nutze die Informationen aus dem Interview, um ein maßgeschneidertes Briefing zu erstellen.\n";
    }

    $userMessage = "Erstelle jetzt das Briefing für den Artikel: \"{$order['title']}\"";
    $messages = [['role' => 'user', 'content' => $userMessage]];

    // Streaming starten
    $result = $ai->chatStream($messages, $systemPrompt, function($token) {
        sendSSE('token', ['content' => $token]);
    });

    $briefingContent = $result['content'];
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // Code-Fences entfernen falls vorhanden
    $briefingContent = preg_replace('/^```(?:html)?\s*\n?/', '', $briefingContent);
    $briefingContent = preg_replace('/\n?```\s*$/', '', $briefingContent);
    $briefingContent = trim($briefingContent);

    // Briefing in Order speichern
    $db->update('orders', [
        'briefing_content' => $briefingContent,
        'model' => $model
    ], 'id = ?', [$orderId]);

    // Chat-Nachrichten speichern
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'briefing',
        'role' => 'user',
        'content' => 'Briefing erstellen',
        'tokens_used' => 0,
        'model_used' => $model
    ]);
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'briefing',
        'role' => 'assistant',
        'content' => 'Briefing wurde erstellt.',
        'tokens_used' => ($tokensInput + $tokensOutput),
        'model_used' => $model
    ]);

    // Usage tracken
    $tracker->log(
        $order['customer_id'],
        Auth::id(),
        'order_briefing',
        $model,
        $provider,
        $tokensInput,
        $tokensOutput,
        0,
        ['order_id' => $orderId]
    );

    // Done-Event mit finalem Content senden
    sendSSE('done', [
        'briefing_content' => $briefingContent,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput
    ]);
}

/**
 * Briefing Chat mit Streaming
 */
function streamBriefingChat(array $order, $db, AIService $ai, ContextService $contextService, UsageTracker $tracker, string $model, string $provider, int $orderId, string $userMessage): void
{
    $ai->setMaxTokens(4096);

    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein erfahrener Content-Stratege. Du hilfst beim Ueberarbeiten eines Briefings.\n\n";
    $systemPrompt .= "Aktuelles Briefing:\n" . ($order['briefing_content'] ?? 'Noch kein Briefing vorhanden.') . "\n\n";

    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    $systemPrompt .= "Wenn der Benutzer Aenderungen wuenscht, gib das KOMPLETTE ueberarbeitete Briefing als HTML zurueck.\n";
    $systemPrompt .= "Antworte NUR mit dem ueberarbeiteten Briefing-HTML, ohne zusaetzliche Erklaerungen.\n";

    // Chat-History laden
    $chatHistory = $db->query(
        "SELECT role, content FROM order_chat_messages
         WHERE order_id = ? AND phase = 'briefing'
         ORDER BY created_at ASC",
        [$orderId]
    );

    $messages = [];
    foreach ($chatHistory as $msg) {
        if ($msg['role'] !== 'system') {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    // Streaming starten
    $result = $ai->chatStream($messages, $systemPrompt, function($token) {
        sendSSE('token', ['content' => $token]);
    });

    $briefingContent = $result['content'];
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // Code-Fences entfernen falls vorhanden
    $briefingContent = preg_replace('/^```(?:html)?\s*\n?/', '', $briefingContent);
    $briefingContent = preg_replace('/\n?```\s*$/', '', $briefingContent);
    $briefingContent = trim($briefingContent);

    // Briefing aktualisieren
    $db->update('orders', [
        'briefing_content' => $briefingContent
    ], 'id = ?', [$orderId]);

    // Chat-Nachrichten speichern
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'briefing',
        'role' => 'user',
        'content' => $userMessage,
        'tokens_used' => 0,
        'model_used' => $model
    ]);
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'briefing',
        'role' => 'assistant',
        'content' => 'Briefing wurde ueberarbeitet.',
        'tokens_used' => ($tokensInput + $tokensOutput),
        'model_used' => $model
    ]);

    // Usage tracken
    $tracker->log(
        $order['customer_id'],
        Auth::id(),
        'order_briefing_chat',
        $model,
        $provider,
        $tokensInput,
        $tokensOutput,
        0,
        ['order_id' => $orderId]
    );

    sendSSE('done', [
        'briefing_content' => $briefingContent,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput
    ]);
}

/**
 * Artikel generieren mit Streaming
 */
function streamArticle(array $order, $db, AIService $ai, ContextService $contextService, UsageTracker $tracker, string $model, string $provider, int $orderId): void
{
    $ai->setMaxTokens(8192);

    // Status auf generating setzen
    $db->update('orders', ['status' => 'generating'], 'id = ?', [$orderId]);

    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein professioneller Content-Autor. Schreibe einen hochwertigen Fliesstext-Artikel basierend auf dem Briefing.\n\n";
    $systemPrompt .= "WICHTIGE REGELN:\n";
    $systemPrompt .= "- Schreibe ausschliesslich den Artikel als sauberes HTML\n";
    $systemPrompt .= "- Verwende <h2>, <h3> fuer Ueberschriften\n";
    $systemPrompt .= "- Verwende <p> fuer Absaetze\n";
    $systemPrompt .= "- Verwende <ul>/<ol> fuer Listen wenn passend\n";
    $systemPrompt .= "- Verwende <strong> und <em> fuer Hervorhebungen\n";
    $systemPrompt .= "- Schreibe auf Deutsch\n";
    $systemPrompt .= "- KEINE Meta-Kommentare, keine Erklaerungen - NUR der Artikel-HTML\n\n";

    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    $userMessage = "Schreibe den Artikel basierend auf diesem Briefing:\n\n" . $order['briefing_content'];
    $messages = [['role' => 'user', 'content' => $userMessage]];

    // Streaming starten
    $result = $ai->chatStream($messages, $systemPrompt, function($token) {
        sendSSE('token', ['content' => $token]);
    });

    $articleContent = $result['content'];
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // Version erstellen
    $maxVersion = $db->queryValue(
        "SELECT MAX(version_number) FROM order_versions WHERE order_id = ?",
        [$orderId]
    );
    $versionNumber = ($maxVersion ?? 0) + 1;
    $plainText = strip_tags($articleContent);
    $wordCount = count(preg_split('/\s+/', trim($plainText)));

    $db->insert('order_versions', [
        'order_id' => $orderId,
        'version_number' => $versionNumber,
        'article_content' => $articleContent,
        'briefing_content' => $order['briefing_content'],
        'change_description' => 'Initiale Artikel-Generierung',
        'change_source' => 'generation',
        'word_count' => $wordCount,
        'created_by' => Auth::id()
    ]);

    $db->update('orders', [
        'current_version' => $versionNumber,
        'article_content' => $articleContent,
        'status' => 'editing'
    ], 'id = ?', [$orderId]);

    // Usage tracken
    $tracker->log(
        $order['customer_id'],
        Auth::id(),
        'order_article_generate',
        $model,
        $provider,
        $tokensInput,
        $tokensOutput,
        $wordCount,
        ['order_id' => $orderId, 'version' => $versionNumber]
    );

    sendSSE('done', [
        'article_content' => $articleContent,
        'version_number' => $versionNumber,
        'word_count' => $wordCount,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput
    ]);
}

/**
 * Artikel Chat mit Streaming
 */
function streamArticleChat(array $order, $db, AIService $ai, ContextService $contextService, UsageTracker $tracker, string $model, string $provider, int $orderId, string $userMessage, string $currentArticle): void
{
    $ai->setMaxTokens(8192);

    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein professioneller Content-Editor. Der Benutzer moechte Aenderungen am Artikel vornehmen.\n\n";
    $systemPrompt .= "Aktueller Artikel:\n" . $currentArticle . "\n\n";

    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    $systemPrompt .= "ANTWORT-FORMAT: Antworte IMMER als valides JSON mit genau diesen Feldern:\n";
    $systemPrompt .= "{\n";
    $systemPrompt .= "  \"changed\": true/false,\n";
    $systemPrompt .= "  \"article_html\": \"...kompletter Artikel als HTML...\",\n";
    $systemPrompt .= "  \"change_description\": \"Kurze Beschreibung der Aenderung\",\n";
    $systemPrompt .= "  \"chat_response\": \"Antwort an den Benutzer\"\n";
    $systemPrompt .= "}\n\n";
    $systemPrompt .= "Wenn changed=true, muss article_html den KOMPLETTEN ueberarbeiteten Artikel enthalten.\n";
    $systemPrompt .= "Wenn der Benutzer nur eine Frage stellt (keine Aenderung), setze changed=false.\n";

    // Chat-History laden (letzte 10)
    $chatHistory = $db->query(
        "SELECT role, content FROM order_chat_messages
         WHERE order_id = ? AND phase = 'editing'
         ORDER BY created_at DESC LIMIT 10",
        [$orderId]
    );
    $chatHistory = array_reverse($chatHistory);

    $messages = [];
    foreach ($chatHistory as $msg) {
        if ($msg['role'] !== 'system') {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    // Streaming starten - fuer article-chat streamen wir den rohen Output
    $result = $ai->chatStream($messages, $systemPrompt, function($token) {
        sendSSE('token', ['content' => $token]);
    });

    $responseContent = $result['content'];
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // JSON parsen
    $cleanJson = $responseContent;
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $cleanJson, $jsonMatch)) {
        $cleanJson = $jsonMatch[1];
    }

    $parsed = json_decode($cleanJson, true);

    if (!$parsed || !isset($parsed['changed'])) {
        $parsed = [
            'changed' => false,
            'article_html' => $currentArticle,
            'change_description' => '',
            'chat_response' => $responseContent
        ];
    }

    $chatResponse = $parsed['chat_response'] ?? 'Erledigt.';
    $versionNumber = null;

    if ($parsed['changed'] && !empty($parsed['article_html'])) {
        // Neue Version erstellen
        $maxVersion = $db->queryValue(
            "SELECT MAX(version_number) FROM order_versions WHERE order_id = ?",
            [$orderId]
        );
        $versionNumber = ($maxVersion ?? 0) + 1;
        $plainText = strip_tags($parsed['article_html']);
        $wordCount = count(preg_split('/\s+/', trim($plainText)));

        $db->insert('order_versions', [
            'order_id' => $orderId,
            'version_number' => $versionNumber,
            'article_content' => $parsed['article_html'],
            'briefing_content' => $order['briefing_content'],
            'change_description' => $parsed['change_description'] ?? 'KI-Aenderung',
            'change_source' => 'ai',
            'word_count' => $wordCount,
            'created_by' => Auth::id()
        ]);

        $db->update('orders', [
            'current_version' => $versionNumber,
            'article_content' => $parsed['article_html']
        ], 'id = ?', [$orderId]);
    }

    // Chat-Nachrichten speichern
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'editing',
        'role' => 'user',
        'content' => $userMessage,
        'tokens_used' => 0,
        'model_used' => $model
    ]);
    $db->insert('order_chat_messages', [
        'order_id' => $orderId,
        'phase' => 'editing',
        'role' => 'assistant',
        'content' => $chatResponse,
        'applied_change' => $parsed['changed'] ? json_encode([
            'change_description' => $parsed['change_description'] ?? '',
            'version_number' => $versionNumber
        ]) : null,
        'tokens_used' => ($tokensInput + $tokensOutput),
        'model_used' => $model
    ]);

    // Usage tracken
    $tracker->log(
        $order['customer_id'],
        Auth::id(),
        'order_article_chat',
        $model,
        $provider,
        $tokensInput,
        $tokensOutput,
        0,
        ['order_id' => $orderId, 'changed' => $parsed['changed']]
    );

    sendSSE('done', [
        'changed' => $parsed['changed'],
        'article_html' => $parsed['article_html'] ?? $currentArticle,
        'change_description' => $parsed['change_description'] ?? '',
        'chat_response' => $chatResponse,
        'version_number' => $versionNumber,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput
    ]);
}

/**
 * Inline-Edit: Markierten Text per KI umschreiben (leichtgewichtig, schnell)
 */
function streamInlineEdit(array $order, $db, AIService $ai, ContextService $contextService, UsageTracker $tracker, string $model, string $provider, int $orderId, string $selectedText, string $instruction, string $contextBefore, string $contextAfter): void
{
    $ai->setMaxTokens(2048);

    $systemPrompt = "Du bist ein professioneller Text-Editor. Schreibe den markierten Text gemäß der Anweisung um.\n";
    $systemPrompt .= "\nARTIKEL: \"{$order['title']}\"\n";

    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);
    if ($artifactsText) {
        $systemPrompt .= "\n" . $artifactsText . "\n";
    }

    $systemPrompt .= "\nREGELN:\n";
    $systemPrompt .= "- Gib NUR den Ersatztext zurück, NICHTS anderes\n";
    $systemPrompt .= "- Kein HTML, keine Erklärungen, keine Anführungszeichen drumherum\n";
    $systemPrompt .= "- Behalte den Stil und die Sprache des umgebenden Texts bei\n";
    $systemPrompt .= "- Der Ersatztext muss nahtlos in den Kontext passen\n";

    $userMessage = "";
    if ($contextBefore) {
        $userMessage .= "Text davor: ..." . mb_substr($contextBefore, -800) . "\n\n";
    }
    $userMessage .= "MARKIERTER TEXT: " . $selectedText . "\n\n";
    if ($contextAfter) {
        $userMessage .= "Text danach: " . mb_substr($contextAfter, 0, 800) . "...\n\n";
    }
    $userMessage .= "ANWEISUNG: " . $instruction;

    $messages = [['role' => 'user', 'content' => $userMessage]];

    $result = $ai->chatStream($messages, $systemPrompt, function($token) {
        sendSSE('token', ['content' => $token]);
    });

    $replacementText = trim($result['content']);
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // Usage tracken
    $tracker->log(
        $order['customer_id'],
        Auth::id(),
        'order_inline_edit',
        $model,
        $provider,
        $tokensInput,
        $tokensOutput,
        0,
        ['order_id' => $orderId]
    );

    sendSSE('done', [
        'replacement_text' => $replacementText,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput
    ]);
}
