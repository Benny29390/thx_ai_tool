<?php
/**
 * Order Briefing API - Briefing generieren, iterieren, freigeben
 * Async-Version: generate und chat erstellen Jobs, approve/reopen bleiben synchron
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input, $uri;

if (!in_array($method, ['POST', 'PUT'])) {
    Response::error('Method not allowed', 405);
}

// Order-ID aus URI extrahieren
if (!preg_match('#^/orders/(\d+)/briefing/(generate|chat|approve|reopen|save)$#', $uri, $matches)) {
    Response::notFound('Endpunkt nicht gefunden');
}

$orderId = (int) $matches[1];
$action = $matches[2];

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

// Settings laden (für Modell-Bestimmung)
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

// API-Key Prüfung (damit Fehler sofort angezeigt wird, nicht erst im Worker)
$provider = (strpos($model, 'claude') !== false) ? 'anthropic' : 'openai';
$apiKey = $provider === 'anthropic' ? ($settings['anthropic_api_key'] ?? '') : ($settings['openai_api_key'] ?? '');

if (empty($apiKey)) {
    Response::error('API-Key für ' . $provider . ' nicht konfiguriert. Bitte unter Einstellungen hinterlegen.');
}

switch ($action) {
    case 'generate':
        // Job erstellen statt direkt AI aufrufen
        $jobId = $jobQueue->createOrderJob([
            'order_id' => $orderId,
            'customer_id' => $order['customer_id'],
            'user_id' => Auth::id(),
            'job_type' => 'order_briefing',
            'topic' => $order['title'],
            'model' => $model
        ]);

        Response::success([
            'job_id' => $jobId
        ], 'Briefing-Generierung gestartet');
        break;

    case 'chat':
        $message = trim($input['message'] ?? '');
        if (empty($message)) {
            Response::error('Nachricht erforderlich');
        }

        // Job erstellen statt direkt AI aufrufen
        $jobId = $jobQueue->createOrderJob([
            'order_id' => $orderId,
            'customer_id' => $order['customer_id'],
            'user_id' => Auth::id(),
            'job_type' => 'order_briefing_chat',
            'topic' => $order['title'],
            'model' => $model,
            'chat_message' => $message
        ]);

        Response::success([
            'job_id' => $jobId
        ], 'Briefing-Chat gestartet');
        break;

    case 'approve':
        if (empty($order['briefing_content'])) {
            Response::error('Kein Briefing vorhanden');
        }

        // Status auf briefing_approved setzen (synchron)
        $db->update('orders', [
            'status' => 'briefing_approved'
        ], 'id = ?', [$orderId]);

        Response::success(null, 'Briefing freigegeben');
        break;

    case 'reopen':
        // Nur aus editing/completed Status möglich
        if (!in_array($order['status'], ['editing', 'completed'])) {
            Response::error('Briefing kann nur aus der Bearbeitungs-Phase wiedereröffnet werden');
        }

        // Aktuellen Artikel als Version speichern (falls vorhanden und noch nicht gespeichert)
        if (!empty($order['article_content'])) {
            $maxVersion = $db->queryValue(
                "SELECT MAX(version_number) FROM order_versions WHERE order_id = ?",
                [$orderId]
            );
            $versionNumber = ($maxVersion ?? 0) + 1;

            $plainText = strip_tags($order['article_content']);
            $wordCount = count(preg_split('/\s+/', trim($plainText)));

            $db->insert('order_versions', [
                'order_id' => $orderId,
                'version_number' => $versionNumber,
                'article_content' => $order['article_content'],
                'briefing_content' => $order['briefing_content'],
                'change_description' => 'Automatische Sicherung vor Briefing-Rückkehr',
                'change_source' => 'manual',
                'word_count' => $wordCount,
                'created_by' => Auth::id()
            ]);
        }

        // Status zurück auf briefing setzen
        $db->update('orders', [
            'status' => 'briefing'
        ], 'id = ?', [$orderId]);

        Response::success(null, 'Briefing-Phase wiedereröffnet');
        break;

    case 'save':
        $briefingContent = trim($input['briefing_content'] ?? '');
        if (empty($briefingContent)) {
            Response::error('Briefing-Inhalt erforderlich');
        }

        $db->update('orders', [
            'briefing_content' => $briefingContent
        ], 'id = ?', [$orderId]);

        Response::success(null, 'Briefing gespeichert');
        break;

    default:
        Response::notFound('Aktion nicht gefunden');
}
