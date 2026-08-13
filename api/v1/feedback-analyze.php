<?php
/**
 * Feedback Analysis API - Analysiert negatives Feedback und schlägt Regeln vor
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

// Services laden
require_once SERVICES_PATH . '/AIService.php';

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$sectionId = $input['section_id'] ?? null;
$feedbackText = trim($input['feedback'] ?? '');
$sectionContent = trim($input['section_content'] ?? '');

if (!$feedbackText) {
    Response::error('Bitte beschreibe, was genau nicht gut war');
}

// Section-Inhalt holen falls nicht mitgesendet
if ($sectionId && !$sectionContent) {
    $section = $db->queryOne(
        "SELECT asec.*, p.customer_id, p.title as project_title
         FROM article_sections asec
         JOIN article_versions av ON asec.article_version_id = av.id
         JOIN projects p ON av.project_id = p.id
         WHERE asec.id = ?",
        [$sectionId]
    );

    if ($section) {
        $sectionContent = $section['content'];
    }
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// Bestes verfügbares Modell nutzen
$anthropicKey = !empty($settings['anthropic_api_key']) ? $settings['anthropic_api_key'] : null;
$openaiKey = !empty($settings['openai_api_key']) ? $settings['openai_api_key'] : null;

if ($anthropicKey) {
    $apiKey = $anthropicKey;
    $provider = 'anthropic';
    $model = 'claude-3-sonnet-20240229';
} elseif ($openaiKey) {
    $apiKey = $openaiKey;
    $provider = 'openai';
    $model = 'gpt-4';
} else {
    Response::error('Kein API-Key konfiguriert');
}

$ai = new \Services\AIService($apiKey, $provider);
$ai->setModel($model);

// System-Prompt für Regel-Generierung
$systemPrompt = "Du bist ein Experte für Content-Qualität und KI-Textgenerierung. Deine Aufgabe ist es, aus negativem Nutzer-Feedback eine konkrete, umsetzbare Regel zu formulieren.

WICHTIG:
- Die Regel muss konkret und präzise sein
- Die Regel muss für eine KI verständlich und umsetzbar sein
- Die Regel soll das spezifische Problem verhindern
- Formuliere die Regel als klare Anweisung (nicht als Verbot wenn möglich)
- Die Regel sollte 1-2 Sätze lang sein

Antworte AUSSCHLIESSLICH im folgenden JSON-Format:
{
    \"rule_name\": \"Kurzer, beschreibender Name der Regel (max 50 Zeichen)\",
    \"rule_content\": \"Die konkrete Regel als Anweisung formuliert\",
    \"rule_type\": \"style|format|content|seo|tone\",
    \"explanation\": \"Kurze Erklärung, warum diese Regel das Problem löst\"
}";

$userPrompt = "Analysiere das folgende Feedback zu einem KI-generierten Text und erstelle eine Regel, die dieses Problem in Zukunft verhindert.

NUTZER-FEEDBACK:
\"$feedbackText\"";

if ($sectionContent) {
    $userPrompt .= "\n\nBEMÄNGELTER TEXT-ABSCHNITT:
\"" . substr($sectionContent, 0, 2000) . "\"";
}

try {
    $response = $ai->chat([
        ['role' => 'user', 'content' => $userPrompt]
    ], $systemPrompt);

    $content = $response['content'];

    // JSON extrahieren
    $jsonMatch = [];
    if (preg_match('/\{[\s\S]*\}/m', $content, $jsonMatch)) {
        $suggestion = json_decode($jsonMatch[0], true);

        if (json_last_error() === JSON_ERROR_NONE && isset($suggestion['rule_content'])) {
            // Feedback in DB speichern mit der Analyse
            $feedbackData = [
                'user_id' => Auth::id(),
                'rating' => 'negative',
                'comment' => $feedbackText,
                'improvement_request' => json_encode($suggestion)
            ];

            // Nur section_id hinzufügen wenn es eine gültige numerische ID ist (keine skeleton-IDs)
            if ($sectionId && is_numeric($sectionId) && (int)$sectionId > 0) {
                $feedbackData['section_id'] = (int)$sectionId;
            }

            $feedbackId = $db->insert('section_feedback', $feedbackData);

            Response::success([
                'feedback_id' => $feedbackId,
                'suggestion' => $suggestion
            ]);
        }
    }

    // Fallback wenn JSON-Parsing fehlschlägt
    Response::success([
        'suggestion' => [
            'rule_name' => 'Aus Feedback generiert',
            'rule_content' => $feedbackText,
            'rule_type' => 'content',
            'explanation' => 'Die KI konnte keine spezifische Regel ableiten. Die Feedback-Beschreibung wird direkt als Regel verwendet.'
        ]
    ]);

} catch (\Exception $e) {
    Response::error('Fehler bei der Analyse: ' . $e->getMessage());
}
