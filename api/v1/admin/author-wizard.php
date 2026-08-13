<?php
/**
 * Author Wizard API - KI-gesteuerte Profil-Erstellung
 *
 * POST: Nimmt Dokumente und URLs entgegen, extrahiert Text,
 *       analysiert Schreibstil via KI, gibt Autorenprofil als JSON zurück.
 */

use Core\Response;

global $db, $input, $method;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Prüfe ob Request-Body zu groß war (PHP verwirft Input bei Überschreitung von post_max_size)
if (empty($input) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $maxSize = ini_get('post_max_size');
    Response::error("Request zu groß (max. {$maxSize}). Bitte weniger/kleinere Dateien hochladen.");
}

// Execution-Time erhöhen für KI-Analyse
set_time_limit(120);

require_once SERVICES_PATH . '/DocumentProcessor.php';
require_once SERVICES_PATH . '/AIService.php';

$documents = $input['documents'] ?? [];
$urls = $input['urls'] ?? [];
$authorName = trim($input['author_name'] ?? '');
$requestedModel = trim($input['model'] ?? '');

// Mindestens eine Quelle erforderlich
if (empty($documents) && empty($urls)) {
    Response::error('Mindestens ein Dokument oder eine URL erforderlich');
}

$allTexts = [];
$processor = new \Services\DocumentProcessor();

// 1. Dokumente verarbeiten
foreach ($documents as $doc) {
    $filename = $doc['filename'] ?? 'unknown.txt';
    $data = $doc['data'] ?? '';

    if (empty($data)) continue;

    // Base64 Data-URI prefix entfernen falls vorhanden
    if (preg_match('/^data:[^;]+;base64,/', $data)) {
        $data = preg_replace('/^data:[^;]+;base64,/', '', $data);
    }

    $decoded = base64_decode($data);
    if ($decoded === false) {
        continue; // Ungültiges Base64 überspringen
    }

    // Temp-Datei erstellen
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $tmpFile = tempnam(sys_get_temp_dir(), 'wizard_') . '.' . $ext;
    file_put_contents($tmpFile, $decoded);

    try {
        $result = $processor->processFile($tmpFile, '', $filename);
        if (!empty($result['text'])) {
            $allTexts[] = "=== Dokument: $filename ===\n" . $result['text'];
        }
    } catch (\Throwable $e) {
        error_log("Author Wizard: Fehler bei Dokument '$filename': " . $e->getMessage());
    } finally {
        @unlink($tmpFile);
    }
}

// 2. URLs verarbeiten
foreach ($urls as $url) {
    $url = trim($url);
    if (empty($url)) continue;

    // URL validieren
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        continue;
    }

    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'KI-Text-Tool/1.0',
                'follow_location' => true,
                'max_redirects' => 3
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $html = @file_get_contents($url, false, $context);
        if (!$html) continue;

        // Script/Style entfernen
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $html);

        // HTML zu Text
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (!empty($text)) {
            // Auf 5000 Zeichen pro URL begrenzen
            if (mb_strlen($text) > 5000) {
                $text = mb_substr($text, 0, 5000) . '...';
            }
            $allTexts[] = "=== URL: $url ===\n" . $text;
        }
    } catch (\Throwable $e) {
        error_log("Author Wizard: Fehler bei URL '$url': " . $e->getMessage());
    }
}

if (empty($allTexts)) {
    Response::error('Konnte keinen Text aus den Quellen extrahieren');
}

// 3. Texte zusammenführen (max 20.000 Zeichen für KI)
$combinedText = implode("\n\n", $allTexts);
if (mb_strlen($combinedText) > 20000) {
    $combinedText = mb_substr($combinedText, 0, 20000) . "\n\n[... Text gekürzt ...]";
}

// 4. KI-Analyse
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// Modell: Vom User gewählt oder Fallback auf Default
if (!empty($requestedModel)) {
    // Validieren dass das Modell existiert
    $modelRow = $db->queryOne("SELECT model_id, provider FROM ai_models WHERE model_id = ? AND is_active = 1", [$requestedModel]);
    if ($modelRow) {
        $model = $modelRow['model_id'];
        $provider = $modelRow['provider'];
    } else {
        $model = $requestedModel;
        $provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
    }
} else {
    $model = $settings['default_model'] ?? 'gpt-4';
    $provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
}

$apiKey = $provider === 'anthropic'
    ? ($settings['anthropic_api_key'] ?? '')
    : ($settings['openai_api_key'] ?? '');

if (empty($apiKey)) {
    Response::error('API-Key für ' . $provider . ' nicht konfiguriert');
}

$systemPrompt = 'Du bist ein erfahrener Lektor und Stilanalyst. Analysiere die folgenden Texte und erstelle daraus ein Autorenprofil.

WICHTIG:
- Konzentriere dich ausschließlich auf den eigentlichen Fließtext (Artikel, Blogposts, Absätze).
- Ignoriere technische Artefakte wie PDF-Koordinaten, Layout-Anweisungen, Menü-Texte, Navigation, Footer-Inhalte, Cookie-Hinweise oder HTML/CSS-Fragmente.
- Wenn der Text Navigations- oder Website-Elemente enthält, filtere diese heraus und analysiere nur den redaktionellen Inhalt.
- Falls kein verwertbarer Fließtext vorhanden ist, gib bei writing_style an: "Nicht genügend Textmaterial für eine Stilanalyse."

Antworte ausschließlich mit einem JSON-Objekt (kein Markdown, keine Code-Blöcke, kein erklärungstext davor/danach):
{
  "name": "Name des Autors (falls im Text erkennbar, sonst leer)",
  "writing_style": "Detaillierte Beschreibung des Schreibstils in 2-4 Sätzen: Satzlänge, Komplexität, rhetorische Mittel, Leseransprache",
  "expertise": "Erkennbare Fachgebiete und Themen, kommasepariert",
  "tone": "Einer von: formal, informal, professional, friendly, technical, casual",
  "perspective": "Einer von: Ich-Form, Wir-Form, Neutral",
  "example_text": "Ein repräsentativer Originalauszug aus dem Text (max. 500 Zeichen), der den Stil gut zeigt",
  "notes": "Besonderheiten: typische Formulierungen, Lieblingswörter, Absatzlänge, Verwendung von Aufzählungen/Fragen/Metaphern etc."
}';

$userMessage = $combinedText;
if (!empty($authorName)) {
    $userMessage = "Hinweis: Der Autor heißt wahrscheinlich \"$authorName\".\n\n" . $userMessage;
}

try {
    $ai = new \Services\AIService($apiKey, $provider);
    $ai->setModel($model);
    $ai->setMaxTokens(2000);
    $ai->setTimeout(60);

    $response = $ai->chat(
        [['role' => 'user', 'content' => $userMessage]],
        $systemPrompt
    );

    $content = trim($response['content']);

    // JSON aus Antwort extrahieren (falls in Code-Block)
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $m)) {
        $content = trim($m[1]);
    }

    $profile = json_decode($content, true);
    if (!$profile) {
        // Versuche JSON innerhalb des Texts zu finden
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $profile = json_decode($m[0], true);
        }
    }

    if (!$profile) {
        Response::error('KI-Antwort konnte nicht als JSON geparst werden');
    }

    // Autorennamen überschreiben falls vorgegeben
    if (!empty($authorName)) {
        $profile['name'] = $authorName;
    }

    // Sicherstellen dass alle Felder existieren
    $defaults = [
        'name' => '',
        'writing_style' => '',
        'expertise' => '',
        'tone' => '',
        'perspective' => '',
        'example_text' => '',
        'notes' => ''
    ];
    $profile = array_merge($defaults, $profile);

    // Usage loggen
    try {
        $db->insert('usage_logs', [
            'user_id' => \Core\Auth::id(),
            'customer_id' => \Core\Auth::customerId(),
            'action_type' => 'generation',
            'model_used' => $response['model'] ?? $model,
            'tokens_input' => $response['tokens']['input'] ?? 0,
            'tokens_output' => $response['tokens']['output'] ?? 0,
            'metadata' => json_encode(['type' => 'author_wizard'])
        ]);
    } catch (\Throwable $e) {
        error_log("Author Wizard: Usage-Log Fehler: " . $e->getMessage());
    }

    Response::success($profile, 'Autorenprofil erstellt');

} catch (\Throwable $e) {
    Response::error('KI-Analyse fehlgeschlagen: ' . $e->getMessage());
}
