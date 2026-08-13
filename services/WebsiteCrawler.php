<?php
/**
 * Website Crawler — folgt internen Links einer Domain bis zu einem Limit.
 *
 * Nur interne Links (gleiche Host). Respektiert robots.txt nicht (nur eigene Seiten crawlen!).
 * Einfache BFS mit Max-Pages + Max-Depth.
 */

namespace Services;

class WebsiteCrawler
{
    private WebSearchService $web;
    private int $maxPages;
    private int $maxDepth;
    private int $perPageTimeout;
    private array $skipPatterns;

    public function __construct(WebSearchService $web, int $maxPages = 20, int $maxDepth = 2)
    {
        $this->web = $web;
        $this->maxPages = max(1, min(500, $maxPages));
        $this->maxDepth = max(1, min(5, $maxDepth));
        $this->perPageTimeout = 10;

        // Dateiendungen / Muster die ignoriert werden
        $this->skipPatterns = [
            '\\.(jpg|jpeg|png|gif|webp|svg|ico|pdf|zip|doc|docx|xls|xlsx|mp4|mp3|avi|mov)(\\?.*)?$',
            '^mailto:', '^tel:', '^javascript:',
            '#',
        ];
    }

    /** Host normalisieren — www. entfernen + lowercase, damit Redirects (mit/ohne www) toleriert werden */
    private function normalizeHost(string $host): string
    {
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) $host = substr($host, 4);
        return $host;
    }

    /**
     * Crawlt ausgehend von $startUrl und liefert pro Seite
     *   ['url' => ..., 'title' => ..., 'text' => ..., 'depth' => ...]
     * zurueck.
     */
    public function crawl(string $startUrl, ?callable $progress = null): array
    {
        $startUrl = $this->normalizeUrl($startUrl);
        if (!$startUrl) return [];

        $baseHost = parse_url($startUrl, PHP_URL_HOST);
        if (!$baseHost) return [];
        $host = $this->normalizeHost($baseHost);

        $visited = [];
        $queue = [['url' => $startUrl, 'depth' => 0]];
        $results = [];
        $hostAdopted = false;

        while (!empty($queue) && count($results) < $this->maxPages) {
            $current = array_shift($queue);
            $url = $current['url'];
            $depth = $current['depth'];

            if (isset($visited[$url])) continue;
            $visited[$url] = true;

            if ($progress) $progress(['type' => 'fetching', 'url' => $url, 'done' => count($results), 'total' => $this->maxPages]);

            // Rohtext + Links holen
            $pageData = $this->fetchPage($url);
            if (!$pageData) continue;

            // Nach erstem Fetch: effektive URL (nach Redirects) übernehmen, damit
            // wir z.B. nach Redirect von "domain.de" → "www.domain.de" weitercrawlen
            if (!$hostAdopted && !empty($pageData['final_url'])) {
                $effectiveHost = parse_url($pageData['final_url'], PHP_URL_HOST);
                if ($effectiveHost) $host = $this->normalizeHost($effectiveHost);
                $hostAdopted = true;
            }

            $text = $pageData['text'];
            if (mb_strlen(trim($text)) >= 100) {
                $results[] = [
                    'url' => $pageData['final_url'] ?? $url,
                    'title' => $pageData['title'],
                    'text' => $text,
                    'depth' => $depth,
                ];
                if ($progress) $progress(['type' => 'fetched', 'url' => $url, 'chars' => mb_strlen($text), 'done' => count($results), 'total' => $this->maxPages]);
            } else {
                if ($progress) $progress(['type' => 'skipped', 'url' => $url, 'reason' => 'zu wenig text']);
            }

            // Links extrahieren und zur Queue
            if ($depth < $this->maxDepth) {
                foreach ($pageData['links'] as $link) {
                    $norm = $this->normalizeUrl($link, $pageData['final_url'] ?? $url);
                    if (!$norm || isset($visited[$norm])) continue;
                    $linkHost = parse_url($norm, PHP_URL_HOST);
                    if (!$linkHost || $this->normalizeHost($linkHost) !== $host) continue;
                    if ($this->shouldSkip($norm)) continue;

                    $queue[] = ['url' => $norm, 'depth' => $depth + 1];
                }
            }
        }

        return $results;
    }

    private function fetchPage(string $url): ?array
    {
        try {
            if (!preg_match('#^https?://#i', $url)) return null;

            $host = parse_url($url, PHP_URL_HOST);
            if (!$host) return null;
            $ip = gethostbyname($host);
            $filterFlags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            if (defined('FILTER_FLAG_NO_LOOPBACK')) $filterFlags |= FILTER_FLAG_NO_LOOPBACK;
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, $filterFlags) === false) return null;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->perPageTimeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; KI-TextTool/1.0)',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: de,en;q=0.5'],
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_MAXFILESIZE => 3 * 1024 * 1024,
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
            curl_close($ch);

            if ($httpCode !== 200 || empty($html)) return null;
            if (stripos($contentType, 'text/html') === false && stripos($contentType, 'application/xhtml') === false) return null;

            // Title
            $title = '';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
                $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            // Links
            $links = [];
            if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
                $links = array_unique($m[1]);
            }

            // Text (via reflectedtodo service)
            $text = $this->htmlToText($html);

            return [
                'title' => $title,
                'text' => $text,
                'links' => $links,
                'final_url' => $finalUrl,
            ];
        } catch (\Exception $e) {
            error_log('WebsiteCrawler::fetchPage ' . $url . ': ' . $e->getMessage());
            return null;
        }
    }

    private function htmlToText(string $html): string
    {
        // Script/Style/Nav/Header/Footer raus
        $html = preg_replace('/<(script|style|nav|header|footer|aside)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?: $html;
        // Kommentare raus
        $html = preg_replace('/<!--.*?-->/s', ' ', $html) ?: $html;
        // Block-Elemente als Absaetze
        $html = preg_replace('/<(p|h[1-6]|li|tr|br|div)[^>]*>/i', "\n", $html) ?: $html;
        // Tags raus
        $text = strip_tags($html);
        // Entities decoden
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Whitespace normalisieren
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n\n", $text);
        return trim($text);
    }

    private function normalizeUrl(string $url, ?string $base = null): ?string
    {
        $url = trim($url);
        if ($url === '') return null;

        // Relative URL aufloesen
        if ($base && !preg_match('#^https?://#i', $url)) {
            if (str_starts_with($url, '//')) {
                $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
                $url = $scheme . ':' . $url;
            } elseif (str_starts_with($url, '/')) {
                $parsed = parse_url($base);
                $url = $parsed['scheme'] . '://' . $parsed['host'] . ($parsed['port'] ?? '' ? ':' . $parsed['port'] : '') . $url;
            } else {
                $basePath = rtrim(dirname(parse_url($base, PHP_URL_PATH) ?: '/'), '/') . '/';
                $parsed = parse_url($base);
                $url = $parsed['scheme'] . '://' . $parsed['host'] . $basePath . $url;
            }
        }

        if (!preg_match('#^https?://#i', $url)) return null;

        // Fragment entfernen, trailing slash normalisieren
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return null;
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $parts['scheme'] . '://' . strtolower($parts['host']) . $path . $query;
    }

    private function shouldSkip(string $url): bool
    {
        foreach ($this->skipPatterns as $pattern) {
            if (preg_match('#' . $pattern . '#i', $url)) return true;
        }
        return false;
    }
}
