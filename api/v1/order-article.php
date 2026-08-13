<?php
/**
 * Order Article API - Artikel generieren, speichern, Chat-to-Editor
 * Async-Version: generate und chat erstellen Jobs, content bleibt synchron
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input, $uri;

// Order-ID und Aktion aus URI extrahieren
if (preg_match('#^/orders/(\d+)/article/(generate|content|chat)$#', $uri, $matches)) {
    $orderId = (int) $matches[1];
    $action = $matches[2];
} else {
    Response::notFound('Endpunkt nicht gefunden');
}

// Auftrag laden und Zugriff prüfen
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

// Services laden
require_once SERVICES_PATH . '/JobQueue.php';

$jobQueue = new \Services\JobQueue($db);

// Settings laden (API-Keys, Default-Model)
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

/**
 * Hilfsfunktion: Neue Version erstellen (für synchrone content-Speicherung)
 */
function createVersion($db, int $orderId, string $articleContent, ?string $briefingContent, string $changeDescription, string $changeSource, ?int $createdBy): int
{
    // Naechste Versionsnummer
    $maxVersion = $db->queryValue(
        "SELECT MAX(version_number) FROM order_versions WHERE order_id = ?",
        [$orderId]
    );
    $versionNumber = ($maxVersion ?? 0) + 1;

    // Wörter zählen
    $plainText = strip_tags($articleContent);
    $wordCount = count(preg_split('/\s+/', trim($plainText)));

    $db->insert('order_versions', [
        'order_id' => $orderId,
        'version_number' => $versionNumber,
        'article_content' => $articleContent,
        'briefing_content' => $briefingContent,
        'change_description' => substr($changeDescription, 0, 500),
        'change_source' => $changeSource,
        'word_count' => $wordCount,
        'created_by' => $createdBy
    ]);

    // Aktuelle Version im Auftrag aktualisieren
    $db->update('orders', [
        'current_version' => $versionNumber,
        'article_content' => $articleContent
    ], 'id = ?', [$orderId]);

    return $versionNumber;
}

switch ($action) {
    case 'generate':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }

        if (empty($order['briefing_content'])) {
            Response::error('Kein Briefing vorhanden. Bitte zuerst ein Briefing erstellen.');
        }

        // API-Key Prüfung (damit Fehler sofort angezeigt wird)
        $provider = (strpos($model, 'claude') !== false) ? 'anthropic' : 'openai';
        $apiKey = $provider === 'anthropic' ? ($settings['anthropic_api_key'] ?? '') : ($settings['openai_api_key'] ?? '');

        if (empty($apiKey)) {
            Response::error('API-Key für ' . $provider . ' nicht konfiguriert. Bitte unter Einstellungen hinterlegen.');
        }

        // Job erstellen statt direkt AI aufrufen
        $jobId = $jobQueue->createOrderJob([
            'order_id' => $orderId,
            'customer_id' => $order['customer_id'],
            'user_id' => Auth::id(),
            'job_type' => 'order_article',
            'topic' => $order['title'],
            'model' => $model
        ]);

        // Status auf generating setzen
        $db->update('orders', ['status' => 'generating'], 'id = ?', [$orderId]);

        Response::success([
            'job_id' => $jobId
        ], 'Artikel-Generierung gestartet');
        break;

    case 'content':
        if ($method !== 'PUT') {
            Response::error('Method not allowed', 405);
        }

        $articleContent = $input['article_content'] ?? null;
        if ($articleContent === null) {
            Response::error('article_content erforderlich');
        }

        $changeDescription = $input['change_description'] ?? 'Manuelle Bearbeitung';

        $versionNumber = createVersion(
            $db,
            $orderId,
            $articleContent,
            $order['briefing_content'],
            $changeDescription,
            'manual',
            Auth::id()
        );

        Response::success([
            'version_number' => $versionNumber
        ], 'Artikel gespeichert');
        break;

    case 'chat':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }

        $message = trim($input['message'] ?? '');
        $currentArticle = $input['current_article'] ?? $order['article_content'] ?? '';

        if (empty($message)) {
            Response::error('Nachricht erforderlich');
        }

        // API-Key Prüfung
        $provider = (strpos($model, 'claude') !== false) ? 'anthropic' : 'openai';
        $apiKey = $provider === 'anthropic' ? ($settings['anthropic_api_key'] ?? '') : ($settings['openai_api_key'] ?? '');

        if (empty($apiKey)) {
            Response::error('API-Key für ' . $provider . ' nicht konfiguriert. Bitte unter Einstellungen hinterlegen.');
        }

        // Job erstellen statt direkt AI aufrufen
        $jobId = $jobQueue->createOrderJob([
            'order_id' => $orderId,
            'customer_id' => $order['customer_id'],
            'user_id' => Auth::id(),
            'job_type' => 'order_article_chat',
            'topic' => $order['title'],
            'model' => $model,
            'chat_message' => $message,
            'sections_config' => ['current_article' => $currentArticle]
        ]);

        Response::success([
            'job_id' => $jobId
        ], 'Artikel-Chat gestartet');
        break;

    default:
        Response::notFound('Aktion nicht gefunden');
}
