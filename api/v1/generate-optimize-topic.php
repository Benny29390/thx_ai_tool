<?php
/**
 * Topic Optimization API - Verbessert ein Thema/Stichworte mit KI
 */

use Core\Response;

global $db, $input;

require_once SERVICES_PATH . '/AIService.php';

$topic = trim($input['topic'] ?? '');

if (empty($topic)) {
    Response::error('Thema erforderlich');
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// Modell aus Einstellungen laden (Fallback: gpt-4)
$model = $settings['optimize_model'] ?? $settings['default_model'] ?? 'gpt-4';

// API-Key bestimmen
$provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
$apiKey = $provider === 'anthropic'
    ? ($settings['anthropic_api_key'] ?? '')
    : ($settings['openai_api_key'] ?? '');

if (empty($apiKey)) {
    Response::error('API-Key für ' . $provider . ' nicht konfiguriert');
}

try {
    $ai = new \Services\AIService($apiKey, $provider);
    $ai->setModel($model);

    $systemPrompt = "Du bist ein SEO-Experte und Content-Stratege. Deine Aufgabe ist es, aus Stichworten oder unstrukturierten Eingaben einen klaren, prägnanten Artikel-Titel zu formulieren.

REGELN:
- Erstelle einen konkreten, aussagekraeftigen Titel
- Der Titel soll SEO-freundlich sein (wichtige Keywords enthalten)
- Maximal 70 Zeichen
- Kein Doppelpunkt am Ende
- Keine Anführungszeichen
- Direkt und aktiv formuliert
- Deutsch

Antworte NUR mit dem optimierten Titel, ohne Erklärung oder Zusatztext.";

    $response = $ai->chat(
        [['role' => 'user', 'content' => "Optimiere dieses Thema/diese Stichworte zu einem Artikel-Titel:\n\n$topic"]],
        $systemPrompt
    );

    $optimizedTopic = trim($response['content']);

    // Anführungszeichen entfernen falls vorhanden
    $optimizedTopic = trim($optimizedTopic, '"\'');

    Response::success([
        'original_topic' => $topic,
        'optimized_topic' => $optimizedTopic
    ]);

} catch (\Exception $e) {
    Response::error('Fehler bei der Optimierung: ' . $e->getMessage());
}
