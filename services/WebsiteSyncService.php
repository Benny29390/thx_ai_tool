<?php
namespace Services;

use Core\Database;

/**
 * WebsiteSyncService
 *
 * Crawlt eine Kunden-Website komplett, synchronisiert die Inhalte in die
 * Wissensbasis und führt einen Diff durch:
 *   - bekannte URL, Content gleich           → skip
 *   - bekannte URL, Content geändert         → reprocess
 *   - neue URL                                → commit
 *   - alte URL nicht mehr im Crawl gefunden  → delete aus Knowledge
 *
 * Jede Page wird in knowledge_documents abgelegt mit:
 *   - source_type = 'website'
 *   - source_ref  = vollständige URL
 *   - external_id = 'website-page:{md5(normalizedUrl)}'
 */
class WebsiteSyncService
{
    private Database $db;
    private WebsiteCrawler $crawler;
    private KnowledgeIngestService $ingest;

    public function __construct(
        Database $db,
        WebsiteCrawler $crawler,
        KnowledgeIngestService $ingest
    ) {
        $this->db = $db;
        $this->crawler = $crawler;
        $this->ingest = $ingest;
    }

    public static function buildExternalId(string $url): string
    {
        return 'website-page:' . md5(trim($url));
    }

    /**
     * Führt einen Sync für einen Kunden durch.
     *
     * @return array Statistik: created, updated, unchanged, deleted, failed, total_pages
     */
    public function sync(int $customerId, string $startUrl, int $userId, ?callable $progress = null): array
    {
        $customer = $this->db->queryOne("SELECT id, name, settings FROM customers WHERE id = ?", [$customerId]);
        if (!$customer) throw new \RuntimeException('Kunde nicht gefunden');

        // Zusätzliche Domains aus settings auspacken
        $settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
        $additionalDomains = $settings['domains'] ?? [];
        $allUrls = [trim($startUrl)];
        foreach ($additionalDomains as $d) {
            $u = trim((string) ($d['url'] ?? ''));
            if ($u !== '' && !in_array($u, $allUrls, true)) $allUrls[] = $u;
        }

        // 1. Bestehende Website-Knowledge-Einträge laden
        $existingRows = $this->db->query(
            "SELECT id, external_id, content_hash, title
             FROM knowledge_documents
             WHERE customer_id = ? AND source_type = 'web' AND ingest_mode = 'auto'",
            [$customerId]
        ) ?: [];
        $existing = [];
        foreach ($existingRows as $row) {
            if (!empty($row['external_id'])) $existing[$row['external_id']] = $row;
        }
        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deleted' => 0,
            'failed' => 0,
            'total_pages' => 0,
            'domains_crawled' => 0,
        ];

        // 2. Crawl — pro Domain einen Lauf, gesammelte Pages
        $pages = [];
        foreach ($allUrls as $url) {
            if ($progress) $progress(['type' => 'start', 'url' => $url]);
            $domainPages = $this->crawler->crawl($url, function ($ev) use ($progress) {
                if ($progress) $progress($ev);
            });
            foreach ($domainPages as $p) $pages[] = $p;
            $stats['domains_crawled']++;
            if ($progress) $progress(['type' => 'crawled', 'count' => count($domainPages), 'url' => $url]);
        }
        $stats['total_pages'] = count($pages);

        $seenExtIds = [];

        // 3. Pro Page: upsert
        $i = 0;
        foreach ($pages as $page) {
            $i++;
            $pageUrl = $page['url'];
            $extId = self::buildExternalId($pageUrl);
            $seenExtIds[$extId] = true;
            $newHash = hash('sha256', trim($page['text']));

            if ($progress) $progress(['type' => 'processing', 'index' => $i, 'total' => count($pages), 'url' => $pageUrl]);

            // Sehr kurze Pages skippen — Navigation-Stubs, "Coming Soon", 404
            // verwaessern die RAG-Trefferquote. Schwelle: 50 Woerter.
            $wordCount = str_word_count(trim($page['text']));
            if ($wordCount < 50) {
                $stats['failed']++;
                if ($progress) $progress(['type' => 'failed', 'url' => $pageUrl, 'error' => 'Zu kurz (' . $wordCount . ' Woerter)']);
                continue;
            }

            try {
                if (isset($existing[$extId])) {
                    $exRow = $existing[$extId];
                    if (($exRow['content_hash'] ?? '') === $newHash) {
                        $stats['unchanged']++;
                        if ($progress) $progress(['type' => 'unchanged', 'url' => $pageUrl, 'title' => $exRow['title']]);
                        continue;
                    }
                    // Inhalt geändert → reprocess
                    $this->ingest->reprocess(
                        (int) $exRow['id'],
                        $page['text'],
                        [
                            'title' => $page['title'] ?: $pageUrl,
                            'customer_id' => $customerId,
                            'category' => 'Website',
                            'tags' => ['website-sync'],
                        ],
                        ['customer_name' => $customer['name']],
                        $userId,
                        true
                    );
                    $this->db->update('knowledge_documents', [
                        'source_type' => 'web',
                        'ingest_mode' => 'auto',
                        'source_ref' => $pageUrl,
                        'external_id' => $extId,
                        'updated_by' => $userId,
                    ], 'id = ?', [(int) $exRow['id']]);
                    $stats['updated']++;
                    if ($progress) $progress(['type' => 'updated', 'url' => $pageUrl, 'title' => $page['title']]);
                } else {
                    // Neu
                    $prepared = $this->ingest->prepare($page['text'], ['customer_name' => $customer['name']]);
                    $title = $page['title'] ?: ($prepared['metadata']['title'] ?: $pageUrl);
                    $this->ingest->commit(
                        $prepared,
                        [
                            'title' => $title,
                            'description' => $prepared['metadata']['description'] ?? '',
                            'customer_id' => $customerId,
                            'category' => 'Website',
                            'tags' => ['website-sync'],
                        ],
                        [
                            'source_type' => 'web',
                        'ingest_mode' => 'auto',
                            'source_ref' => $pageUrl,
                            'external_id' => $extId,
                            'created_by' => $userId,
                        ]
                    );
                    $stats['created']++;
                    if ($progress) $progress(['type' => 'created', 'url' => $pageUrl, 'title' => $title]);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                if ($progress) $progress(['type' => 'failed', 'url' => $pageUrl, 'error' => $e->getMessage()]);
                error_log('WebsiteSync page failed: ' . $pageUrl . ' — ' . $e->getMessage());
            }
        }

        // 4. Verwaiste Einträge löschen
        foreach ($existing as $extId => $exRow) {
            if (isset($seenExtIds[$extId])) continue;
            try {
                $this->db->delete('knowledge_documents', 'id = ?', [(int) $exRow['id']]);
                $stats['deleted']++;
                if ($progress) $progress(['type' => 'deleted', 'title' => $exRow['title']]);
            } catch (\Throwable $e) {
                error_log('WebsiteSync delete failed: id=' . $exRow['id'] . ' — ' . $e->getMessage());
            }
        }

        if ($progress) $progress(['type' => 'done', 'stats' => $stats]);
        return $stats;
    }
}
