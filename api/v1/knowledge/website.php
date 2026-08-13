<?php
/**
 * Knowledge Website — crawlt ganze Website, legt pro Seite ein Dokument an.
 *
 * POST /knowledge/website
 * Body: { url, customer_id?, max_pages?, max_depth? }
 *
 * Streamt Progress als SSE. Legt die Dokumente direkt an (kein prepare/commit-2-step),
 * sonst wuerden 20 Einzel-Bestaetigungen noetig sein.
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once __DIR__ . '/_helpers.php';
require_once SERVICES_PATH . '/WebSearchService.php';
require_once SERVICES_PATH . '/WebsiteCrawler.php';
require_once SERVICES_PATH . '/AIService.php';

$url = trim($input['url'] ?? '');
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    Response::error('Gueltige Start-URL erforderlich');
}

$maxPages = max(1, min(50, (int) ($input['max_pages'] ?? 15)));
$maxDepth = max(1, min(3, (int) ($input['max_depth'] ?? 2)));

$customerId = isset($input['customer_id']) && $input['customer_id'] !== '' ? (int) $input['customer_id'] : null;
if ($customerId === null || $customerId <= 0) {
    Response::error('Bitte einen Kunden wählen — die Wissensdatenbank speichert nur kundenbezogene Einträge.', 400);
}
knowledgeAssertWriteAccess($customerId);
$customerName = null;
if ($customerId) {
    $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
    $customerName = $c['name'] ?? null;
}

// Services bauen (wirft Response::error wenn API-Key fehlt)
$services = knowledgeBuildServices($db);
$userId = Auth::id();

// SSE-Header
@ob_end_clean();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

function sse(string $event, array $data): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    @flush();
}

// Settings fuer Brave-Key
$settings = $services['settings'];
$braveKey = $settings['brave_search_api_key'] ?? '';
$openaiKey = $settings['openai_api_key'] ?? '';

$web = new \Services\WebSearchService($braveKey);
$crawler = new \Services\WebsiteCrawler($web, $maxPages, $maxDepth);

sse('start', ['url' => $url, 'max_pages' => $maxPages, 'max_depth' => $maxDepth]);

try {
    $pages = $crawler->crawl($url, function ($ev) {
        sse($ev['type'], $ev);
    });
} catch (\Exception $e) {
    sse('error', ['message' => 'Crawl fehlgeschlagen: ' . $e->getMessage()]);
    exit;
}

sse('crawled', ['count' => count($pages)]);

if (empty($pages)) {
    sse('done', ['created' => 0, 'skipped' => 0, 'failed' => 0, 'documents' => []]);
    exit;
}

// Pro Seite: dedup, dann prepare + commit
$created = [];
$skipped = [];
$failed = [];

foreach ($pages as $idx => $page) {
    $pageUrl = $page['url'];
    $content = $page['text'];

    sse('processing', ['index' => $idx + 1, 'total' => count($pages), 'url' => $pageUrl]);

    // Optional: LLM-Cleanup fuer groessere Seiten (spart Tokens bei kleinen Seiten)
    if (!empty($openaiKey) && mb_strlen($content) > 1500) {
        try {
            $cleanAi = new \Services\AIService($openaiKey, 'openai');
            $cleanAi->setModel('gpt-4o-mini');
            $cleanAi->setTimeout(45);

            $cleanPrompt = "Extrahiere den inhaltlichen Kern (Fliesstext, Produktinfos, Artikel) und entferne Navigation, Cookie-Banner, Footer, wiederholte Menues.

Gib NUR den bereinigten Text als Fliesstext mit Absaetzen zurueck.

Roher Text:
---
" . mb_substr($content, 0, 20000) . "
---";
            $cleanResp = $cleanAi->chat(
                [['role' => 'user', 'content' => $cleanPrompt]],
                'Du bist ein Text-Bereiniger. Gib NUR den bereinigten Fliesstext zurueck.'
            );
            $cleaned = trim($cleanResp['content'] ?? '');
            if (mb_strlen($cleaned) > 100) {
                $content = $cleaned;
            }
        } catch (\Exception $e) {
            // Fallback: raw text weiter nutzen
        }
    }

    if (mb_strlen(trim($content)) < 100) {
        $skipped[] = ['url' => $pageUrl, 'reason' => 'zu wenig text nach cleanup'];
        sse('skipped', ['url' => $pageUrl, 'reason' => 'zu wenig text']);
        continue;
    }

    // Dedup per content_hash
    $hash = hash('sha256', trim($content));
    $existing = $services['knowledgeService']->findByContentHash($hash);
    if ($existing) {
        $skipped[] = ['url' => $pageUrl, 'reason' => 'duplikat', 'existing_id' => $existing['id'], 'existing_title' => $existing['title']];
        sse('skipped', ['url' => $pageUrl, 'reason' => 'duplikat', 'existing' => $existing['title']]);
        continue;
    }

    // Prepare + Commit
    try {
        $prepared = $services['ingestService']->prepare($content, [
            'customer_name' => $customerName,
        ]);

        $overrides = [
            'title' => $prepared['metadata']['title'] ?: ($page['title'] ?: $pageUrl),
            'description' => $prepared['metadata']['description'],
            'customer_id' => $customerId,
            'category' => $prepared['metadata']['category'],
            'tags' => $prepared['metadata']['tags'],
        ];

        $meta = [
            'source_type' => 'web',
            'ingest_mode' => 'auto',
            'source_ref' => $pageUrl,
            'created_by' => $userId,
        ];

        $docId = $services['ingestService']->commit($prepared, $overrides, $meta);
        $created[] = ['id' => $docId, 'title' => $overrides['title'], 'url' => $pageUrl];
        sse('created', [
            'id' => $docId,
            'title' => $overrides['title'],
            'url' => $pageUrl,
            'chunks' => count($prepared['chunks']),
            'entities' => count($prepared['metadata']['entities']),
        ]);
    } catch (\Exception $e) {
        $failed[] = ['url' => $pageUrl, 'error' => $e->getMessage()];
        sse('failed', ['url' => $pageUrl, 'error' => $e->getMessage()]);
    }
}

sse('done', [
    'created' => count($created),
    'skipped' => count($skipped),
    'failed' => count($failed),
    'documents' => $created,
]);
exit;
