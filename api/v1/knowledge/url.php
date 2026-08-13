<?php
/**
 * Knowledge URL — URL crawlen, Text extrahieren, Vorschlag generieren
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once __DIR__ . '/_helpers.php';

$url = trim($input['url'] ?? '');
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    Response::error('Gueltige URL erforderlich');
}

// Crawl ueber WebSearchService (gibt bereinigten Text zurueck)
require_once SERVICES_PATH . '/WebSearchService.php';
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'brave%' OR setting_key = 'app_url'") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);
$braveKey = $settings['brave_search_api_key'] ?? '';
$ws = new \Services\WebSearchService($braveKey);

try {
    $content = $ws->fetchUrlContent($url, 10, 50000);
} catch (\Exception $e) {
    Response::error('URL konnte nicht geladen werden: ' . $e->getMessage());
}

if (!$content || mb_strlen(trim($content)) < 50) {
    Response::error('URL enthaelt zu wenig Text');
}

// Dedup
$services = knowledgeBuildServices($db);

// LLM-Bereinigung: Noise entfernen (Navigation, Cookies, Wiederholungen)
$openaiKey = $services['settings']['openai_api_key'] ?? '';
if (!empty($openaiKey) && mb_strlen($content) > 500) {
    try {
        require_once SERVICES_PATH . '/AIService.php';
        $cleanAi = new \Services\AIService($openaiKey, 'openai');
        $cleanAi->setModel('gpt-4o-mini');

        $cleanPrompt = "Du erhaeltst den rohen Text einer Webseite inklusive Navigation, Cookie-Banner, Menues, Wiederholungen und anderem UI-Rauschen.

Deine Aufgabe: Extrahiere NUR den eigentlichen inhaltlichen Text (Fliesstext, Kerninhalte, Produktinfos, Artikel). Entferne:
- Navigation und Menue-Eintraege
- Cookie-Banner / Datenschutz-Hinweise
- Wiederholte Produktlisten/Kategorie-Namen
- Footer-Inhalte (Impressum, Links, Social Media)
- UI-Labels wie 'Menue oeffnen', 'Zurueck zum Anfang'
- Redundanzen

Gib den bereinigten Text als Fliesstext zurueck, strukturiert mit Absaetzen. Nichts hinzufuegen, keine Kommentare. Nur der gereinigte Content.

Roher Text:
---
" . mb_substr($content, 0, 30000) . "
---";

        $cleanResp = $cleanAi->chat([['role' => 'user', 'content' => $cleanPrompt]], 'Du bist ein Text-Bereiniger. Gib NUR den bereinigten Fliesstext zurueck.');
        $cleaned = trim($cleanResp['content'] ?? '');
        if (mb_strlen($cleaned) > 100) {
            $content = $cleaned;
        }
    } catch (\Exception $e) {
        error_log('Knowledge URL cleaning failed: ' . $e->getMessage());
        // Fallback: Rohtext weiter nutzen
    }
}
$contentHash = hash('sha256', trim($content));
$existing = $services['knowledgeService']->findByContentHash($contentHash);
if ($existing) {
    Response::error('Inhalt existiert bereits: "' . $existing['title'] . '" (ID ' . $existing['id'] . ')');
}

$customerId = isset($input['customer_id']) && $input['customer_id'] !== '' ? (int) $input['customer_id'] : null;
knowledgeAssertWriteAccess($customerId);
$customerName = null;
if ($customerId) {
    $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
    $customerName = $c['name'] ?? null;
}

try {
    $prepared = $services['ingestService']->prepare($content, [
        'customer_name' => $customerName,
    ]);
} catch (\Exception $e) {
    Response::error('Analyse fehlgeschlagen: ' . $e->getMessage());
}

$extra = [
    'source_type' => 'web',
    'ingest_mode' => 'manuell',
    'source_ref' => $url,
    'customer_id_suggestion' => $customerId,
];

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$preparedId = knowledgeSavePrepared($prepared, $extra);
knowledgePreparedResponse($preparedId, $prepared, $extra);
