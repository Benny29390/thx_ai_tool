<?php
/**
 * Customer Profile Suggest — KI-Assistent
 *
 * POST /admin/customer-profile-suggest
 *  - source: 'url' | 'file' | 'text'
 *  - url: string (wenn source=url)
 *  - file: multipart (wenn source=file)
 *  - text: string (wenn source=text)
 *
 * Liefert Vorschlag: description, target_audience, products_services,
 * unique_selling_points, tone_of_voice, brand_values, industry
 */

use Core\Response;
use Services\AIService;
use Services\DocumentProcessor;
use Services\WebSearchService;

global $db, $method, $input;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Bei Multipart-Upload wird $input nicht gesetzt — aus $_POST lesen
$source = $input['source'] ?? $_POST['source'] ?? '';
$text = '';
$sourceRef = '';

if ($source === 'url') {
    $url = trim($input['url'] ?? $_POST['url'] ?? '');
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        Response::error('Gueltige URL erforderlich');
    }

    // Settings laden fuer WebSearch
    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $settings = \Core\Settings::decryptMap($settings);

    require_once SERVICES_PATH . '/WebSearchService.php';
    $webSearch = new WebSearchService($settings['brave_search_api_key'] ?? '');

    $text = $webSearch->fetchUrlContent($url, 15, 15000);
    if (empty($text) || mb_strlen(trim($text)) < 100) {
        Response::error('Website konnte nicht gelesen werden oder enthaelt zu wenig Text');
    }
    $sourceRef = $url;
} elseif ($source === 'file') {
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        Response::error('Upload fehlgeschlagen (Code ' . $err . ')');
    }

    $file = $_FILES['file'];
    if ($file['size'] > 25 * 1024 * 1024) {
        Response::error('Datei zu gross (max 25 MB)');
    }

    require_once SERVICES_PATH . '/DocumentProcessor.php';
    $processor = new DocumentProcessor();
    try {
        $result = $processor->processFile($file['tmp_name'], $file['type'], $file['name']);
        $text = $result['text'];
    } catch (\Exception $e) {
        Response::error('Text-Extraktion fehlgeschlagen: ' . $e->getMessage());
    }

    if (mb_strlen(trim($text)) < 50) {
        Response::error('Dokument enthaelt zu wenig Text');
    }
    $sourceRef = $file['name'];
} elseif ($source === 'text') {
    $text = trim($input['text'] ?? $_POST['text'] ?? '');
    if (mb_strlen($text) < 50) {
        Response::error('Bitte mindestens 50 Zeichen Text angeben');
    }
    $sourceRef = 'manual';
} else {
    Response::error('Quelle unbekannt (source: url|file|text)');
}

// Text kuerzen (LLM-Context)
$maxLen = 20000;
if (mb_strlen($text) > $maxLen) {
    $half = intval($maxLen / 2);
    $text = mb_substr($text, 0, $half) . "\n\n[...]\n\n" . mb_substr($text, -$half);
}

// AI-Service holen
$settings = $settings ?? [];
if (empty($settings)) {
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $settings = \Core\Settings::decryptMap($settings);
}
$openaiKey = $settings['openai_api_key'] ?? '';
if (empty($openaiKey)) {
    Response::error('OpenAI API-Key nicht konfiguriert');
}

require_once SERVICES_PATH . '/AIService.php';
$ai = new AIService($openaiKey, 'openai');
$ai->setModel('gpt-4o-mini');
$ai->setTimeout(60);

// Session freigeben
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$systemPrompt = 'Du bist ein praeziser Extraktor fuer Kundenprofile. Du antwortest IMMER mit validem JSON, ohne Markdown, ohne Kommentare.';

$userPrompt = <<<PROMPT
Analysiere den folgenden Text (Website, Dokument oder Beschreibung eines Unternehmens) und extrahiere ein Kundenprofil fuer eine KI-Text-Plattform.

Extrahiere folgende Felder. Wenn etwas nicht klar hervorgeht, gib einen sinnvollen Vorschlag oder lasse das Feld leer ("").

1. name: Firmenname (falls erkennbar, sonst leer)
2. abbreviation: Kuerzel — 2 bis 4 Großbuchstaben (z.B. "Wittekind GmbH" → "WTK", "Müller & Partner" → "MUE", "Thoxan Communications" → "TXN"). Echte Umlaute erlaubt (Ä/Ö/Ü/ẞ).
3. industry: Branche (ein kurzer Begriff, z.B. "IT-Dienstleistung", "Einzelhandel", "Maschinenbau")
4. description: Firmenbeschreibung (2-3 Saetze, was macht das Unternehmen, fuer wen)
5. target_audience: Zielgruppe (praezise und fachlich, z.B. "B2B-Entscheider im Maschinenbau, 35-55 Jahre" oder "Privatkunden mit hoher Affinitaet zu nachhaltigen Produkten")
6. products_services: Produkte und Dienstleistungen (Aufzaehlung der wichtigsten Angebote)
7. unique_selling_points: USPs (was das Unternehmen unterscheidet, 2-4 konkrete Punkte)
8. tone_of_voice: Tonalitaet — EINER von: formal, informal, professional, friendly, technical, casual
9. brand_values: Markenwerte (3-5 Begriffe komma-separiert, z.B. "Innovation, Nachhaltigkeit, Qualitaet")

WICHTIG: Antworte NUR mit gueltigem JSON nach diesem Schema:

{
  "name": "...",
  "abbreviation": "...",
  "industry": "...",
  "description": "...",
  "target_audience": "...",
  "products_services": "...",
  "unique_selling_points": "...",
  "tone_of_voice": "professional",
  "brand_values": "..."
}

TEXT:
---
{$text}
---
PROMPT;

try {
    $response = $ai->chat(
        [['role' => 'user', 'content' => $userPrompt]],
        $systemPrompt
    );
} catch (\Exception $e) {
    Response::error('KI-Analyse fehlgeschlagen: ' . $e->getMessage());
}

$content = $response['content'] ?? '';

// Robust JSON parsen
$json = null;
if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
    $json = json_decode($m[0], true);
}
if (!is_array($json)) {
    Response::error('KI-Antwort konnte nicht interpretiert werden');
}

$validTones = ['formal', 'informal', 'professional', 'friendly', 'technical', 'casual'];
$tone = $json['tone_of_voice'] ?? '';
if (!in_array($tone, $validTones, true)) {
    $tone = 'professional';
}

$suggestion = [
    'name' => trim($json['name'] ?? ''),
    'abbreviation' => trim($json['abbreviation'] ?? ''),
    'industry' => trim($json['industry'] ?? ''),
    'description' => trim($json['description'] ?? ''),
    'target_audience' => trim($json['target_audience'] ?? ''),
    'products_services' => trim($json['products_services'] ?? ''),
    'unique_selling_points' => trim($json['unique_selling_points'] ?? ''),
    'tone_of_voice' => $tone,
    'brand_values' => trim($json['brand_values'] ?? ''),
];

Response::success([
    'suggestion' => $suggestion,
    'source' => $source,
    'source_ref' => $sourceRef,
    'text_length' => mb_strlen($text),
], 'Profil-Vorschlag erstellt');
