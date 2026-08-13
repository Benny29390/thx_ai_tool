<?php

namespace Services;

/**
 * Web-Suche via Brave Search API
 *
 * Brave Search bietet 2.000 kostenlose Suchen/Monat.
 * API-Key konfigurierbar unter Einstellungen.
 */
class WebSearchService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.search.brave.com/res/v1/web/search';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Web-Suche ausfuehren
     *
     * @param string $query Suchbegriff
     * @param int $maxResults Max Ergebnisse (1-20)
     * @param string $country Laendercode fuer Ergebnisse
     * @return array{results: array, query: string}
     */
    public function search(string $query, int $maxResults = 5, string $country = 'DE'): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Brave Search API Key nicht konfiguriert');
        }

        $params = http_build_query([
            'q' => $query,
            'count' => min($maxResults, 20),
            'country' => $country,
            'search_lang' => 'de',
            'text_decorations' => 'false',
        ]);

        $ch = curl_init($this->baseUrl . '?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Encoding: gzip',
                'X-Subscription-Token: ' . $this->apiKey,
            ],
            CURLOPT_ENCODING => 'gzip',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Brave Search Fehler: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Brave Search HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new \RuntimeException('Brave Search: Ungueltige Antwort');
        }

        $results = [];
        $webResults = $data['web']['results'] ?? [];

        foreach (array_slice($webResults, 0, $maxResults) as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'description' => $item['description'] ?? '',
                'age' => $item['age'] ?? null,
                // Brave-CDN-Vorschau (z.B. LinkedIn-Profilbild, OG-Image der Treffer-Seite)
                'thumbnail' => $item['thumbnail']['src'] ?? null,
                'favicon' => $item['meta_url']['favicon'] ?? null,
            ];
        }

        return [
            'query' => $query,
            'results' => $results,
        ];
    }

    /**
     * Bildersuche über die Brave Images-API.
     * Liefert ein Array mit ['url', 'source', 'thumbnail', 'width', 'height', 'title'].
     */
    public function searchImages(string $query, int $maxResults = 12, string $country = 'DE'): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Brave Search API Key nicht konfiguriert');
        }
        $params = http_build_query([
            'q' => $query,
            'count' => min($maxResults, 100),
            'country' => $country,
            'safesearch' => 'strict',
        ]);
        $ch = curl_init('https://api.search.brave.com/res/v1/images/search?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Encoding: gzip',
                'X-Subscription-Token: ' . $this->apiKey,
            ],
            CURLOPT_ENCODING => 'gzip',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) throw new \RuntimeException('Brave Images Fehler: ' . $error);
        if ($httpCode !== 200) throw new \RuntimeException('Brave Images HTTP ' . $httpCode);
        $data = json_decode($response, true);
        $images = [];
        foreach ($data['results'] ?? [] as $item) {
            $fullUrl  = $item['properties']['url'] ?? ($item['image'] ?? '');
            $thumb    = $item['thumbnail']['src']  ?? ($item['thumbnail']['original'] ?? '');
            // Wenn weder full noch thumb → Eintrag überspringen (Brave-Layout-Drift)
            if ($fullUrl === '' && $thumb === '') continue;
            $images[] = [
                'url'       => $fullUrl !== '' ? $fullUrl : $thumb,
                'thumbnail' => $thumb !== '' ? $thumb : $fullUrl,
                'source'    => $item['source'] ?? (parse_url($item['url'] ?? '', PHP_URL_HOST) ?: ''),
                'title'     => $item['title'] ?? '',
                'page_url'  => $item['url'] ?? '',
                'width'     => $item['properties']['width']  ?? null,
                'height'    => $item['properties']['height'] ?? null,
            ];
        }
        return ['query' => $query, 'results' => $images];
    }

    /**
     * Suchbegriffe aus einer User-Nachricht extrahieren
     * Nutzt das LLM um 1-2 praezise Suchbegriffe zu generieren.
     *
     * @param string $message User-Nachricht
     * @param \Services\AIService $ai AI-Service fuer Extraktion
     * @return array Liste von Suchbegriffen
     */
    public function extractSearchQueries(string $message, AIService $ai): array
    {
        $prompt = <<<'PROMPT'
Extrahiere aus der folgenden Nachricht 1-2 praezise Suchbegriffe fuer eine Web-Suche.
Antworte NUR mit den Suchbegriffen, einer pro Zeile, ohne Nummerierung oder Erklaerung.
Wenn die Nachricht keine Web-Suche benoetigt (z.B. einfache Begruessung), antworte mit KEINE_SUCHE.
PROMPT;

        try {
            $result = $ai->chat([
                ['role' => 'user', 'content' => $prompt . "\n\nNachricht: " . $message]
            ], '');

            $content = trim($result['content'] ?? '');

            if (empty($content) || $content === 'KEINE_SUCHE') {
                return [];
            }

            $queries = array_filter(
                array_map('trim', explode("\n", $content)),
                fn($q) => !empty($q) && $q !== 'KEINE_SUCHE'
            );

            return array_slice($queries, 0, 2);
        } catch (\Exception $e) {
            // Fallback: Nachricht direkt als Suchbegriff nutzen
            $short = mb_substr($message, 0, 200);
            return [$short];
        }
    }

    /**
     * URL-Inhalt abrufen und als lesbaren Text extrahieren
     *
     * @param string $url Die abzurufende URL
     * @param int $timeout Timeout in Sekunden
     * @param int $maxChars Max Zeichen im Ergebnis
     * @return string|null Text-Inhalt oder null bei Fehler
     */
    public function fetchUrlContent(string $url, int $timeout = 10, int $maxChars = 8000): ?string
    {
        try {
            // Nur HTTP(S) erlauben
            if (!preg_match('#^https?://#i', $url)) {
                return null;
            }

            // SSRF-Schutz: Private/interne IPs blockieren
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host) return null;
            $ip = gethostbyname($host);
            $filterFlags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            if (defined('FILTER_FLAG_NO_LOOPBACK')) $filterFlags |= FILTER_FLAG_NO_LOOPBACK;
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, $filterFlags) === false) {
                error_log("fetchUrlContent SSRF blocked: {$url} resolves to {$ip}");
                return null;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; KI-TextTool/1.0; +https://thoxan.com)',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,text/plain',
                    'Accept-Language: de,en;q=0.5',
                ],
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_MAXFILESIZE => 5 * 1024 * 1024, // 5 MB max
            ]);

            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200 || empty($html)) {
                return null;
            }

            // Nur text/html und text/plain verarbeiten
            if (stripos($contentType, 'text/html') !== false || stripos($contentType, 'application/xhtml') !== false) {
                return $this->htmlToText($html, $maxChars);
            }
            if (stripos($contentType, 'text/plain') !== false) {
                return mb_substr($html, 0, $maxChars);
            }

            return null;
        } catch (\Exception $e) {
            error_log("fetchUrlContent error for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * HTML zu lesbarem Text konvertieren
     */
    private function htmlToText(string $html, int $maxChars = 8000): string
    {
        // Encoding erkennen
        if (preg_match('/charset=["\']?([^"\'\s;>]+)/i', $html, $m)) {
            $charset = strtolower(trim($m[1]));
            if ($charset !== 'utf-8' && $charset !== 'utf8') {
                $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
                if ($converted) $html = $converted;
            }
        }

        // Nicht-sichtbare Bloecke entfernen (script, style, nav, footer, header, aside, iframe, etc.)
        $html = preg_replace('/<(script|style|nav|footer|header|aside|iframe|noscript|svg|form|button|select|template)[^>]*>.*?<\/\1>/si', '', $html);
        // Elemente mit bekannten Noise-Klassen/IDs entfernen (Menu, Cookie-Banner, Navigation)
        $html = preg_replace('/<(div|section)[^>]*(?:id|class)=["\'][^"\']*(menu|nav|navigation|sidebar|cookie|breadcrumb|social|share|footer|header|topbar|banner|gdpr|consent|skip-link|accessibility)[^"\']*["\'][^>]*>.*?<\/\1>/si', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Titel extrahieren (fuer Kontext)
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $tm)) {
            $title = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        // <main> oder <article> bevorzugen (Hauptinhalt)
        $mainContent = '';
        if (preg_match('/<(main|article)[^>]*>(.*?)<\/\1>/si', $html, $mainMatch)) {
            $mainContent = $mainMatch[2];
        }
        $source = !empty($mainContent) ? $mainContent : $html;

        // Block-Elemente in Zeilenumbrueche
        $source = preg_replace('/<\/(p|div|h[1-6]|li|tr|blockquote|section|article|dt|dd)>/i', "\n", $source);
        $source = preg_replace('/<br[^>]*\/?>/i', "\n", $source);
        $source = preg_replace('/<\/?(ul|ol)>/i', "\n", $source);

        // Tags entfernen
        $text = strip_tags($source);

        // HTML-Entities dekodieren
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Whitespace bereinigen
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n[ \t]+/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        // Zu kurz = vermutlich kein nuetzlicher Inhalt
        if (mb_strlen($text) < 50) {
            return '';
        }

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars) . "\n[... gekuerzt ...]";
        }

        return $text;
    }

    /**
     * Such-Ergebnisse als Kontext-Text formatieren (mit optionalem gecrawlten Inhalt)
     */
    public function formatResultsAsContext(array $searchResults): string
    {
        if (empty($searchResults)) return '';

        $context = "--- WEB-SUCHERGEBNISSE ---\n";
        $context .= "Die folgenden Informationen stammen aus einer aktuellen Web-Suche. ";
        $context .= "Nutze sie um die Frage zu beantworten. Nenne die Quellen wenn du daraus zitierst.\n\n";

        foreach ($searchResults as $idx => $group) {
            $context .= "Suche: \"{$group['query']}\"\n";
            foreach ($group['results'] as $i => $r) {
                $num = $i + 1;
                $context .= "\n[{$num}] {$r['title']}\n";
                $context .= "URL: {$r['url']}\n";
                if (!empty($r['description'])) {
                    $context .= "{$r['description']}\n";
                }
                if (!empty($r['content'])) {
                    $context .= "\nSeiteninhalt:\n{$r['content']}\n";
                }
            }
            $context .= "\n";
        }

        $context .= "--- ENDE WEB-SUCHERGEBNISSE ---\n";
        return $context;
    }
}
