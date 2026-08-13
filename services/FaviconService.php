<?php
namespace Services;

/**
 * FaviconService — lädt das beste verfügbare Favicon einer Website
 * und speichert es als Logo-Datei im Upload-Verzeichnis.
 *
 * Strategie:
 *  1. HTML der Seite holen, alle <link rel="icon|apple-touch-icon|shortcut icon|mask-icon"> sammeln
 *  2. Plus Standard-Fallbacks /favicon.ico und /apple-touch-icon.png
 *  3. Kandidaten scoren:
 *     - SVG → 10000 (skaliert immer)
 *     - sonst: max(width, height) aus sizes-Attribut oder URL-Hint
 *     - apple-touch-icon ohne sizes → 180 (typisch)
 *     - /favicon.ico → 32 (typisch)
 *  4. Top-Kandidaten parallel laden (curl_multi), MIME validieren
 *  5. Bei Bildern (nicht SVG): tatsächliche Pixel-Dimensionen prüfen, größtes nehmen
 *  6. Als Datei speichern, return ['filename' => ..., 'mime' => ..., 'width' => ..., 'height' => ...]
 */
class FaviconService
{
    private const MAX_CANDIDATES_TO_DOWNLOAD = 6;
    private const HTML_TIMEOUT = 10;
    private const IMG_TIMEOUT = 6;
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_MIMES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon',
    ];

    /**
     * Holt das beste Favicon und speichert es als Datei.
     *
     * @param string $url       Website-URL des Kunden
     * @param string $destDir   Zielverzeichnis (mit trailing slash)
     * @param string $slug      Wird im Dateinamen verwendet
     * @return array            ['filename' => 'logo_xy_123.png', 'mime' => 'image/png', ...]
     * @throws \RuntimeException bei Fehlschlag
     */
    public function fetchAndSave(string $url, string $destDir, string $slug): array
    {
        $url = $this->normalizeUrl($url);
        if ($url === '') throw new \RuntimeException('Keine gültige URL hinterlegt');

        $html = $this->fetchHtml($url);
        $finalUrl = $this->getFinalUrl($url);
        $candidates = $this->collectCandidates($html, $finalUrl);

        if (empty($candidates)) {
            throw new \RuntimeException('Kein Favicon auf der Seite gefunden');
        }

        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES_TO_DOWNLOAD);

        $downloads = $this->downloadAll(array_column($candidates, 'url'));

        $best = null;
        foreach ($candidates as $c) {
            $d = $downloads[$c['url']] ?? null;
            if (!$d || empty($d['body'])) continue;
            $code = (int) ($d['http_code'] ?? 0);
            if ($code < 200 || $code >= 400) continue;
            $mime = $this->detectMime($d['body'], $d['content_type'] ?? '');
            if (!in_array($mime, self::ALLOWED_MIMES, true)) continue;
            if (strlen($d['body']) > self::MAX_BYTES) continue;

            $w = 0; $h = 0;
            if ($mime === 'image/svg+xml') {
                // SVG immer best-case
                $w = 10000; $h = 10000;
            } else {
                $info = @getimagesizefromstring($d['body']);
                if (!$info) continue;
                $w = (int) $info[0];
                $h = (int) $info[1];
            }
            $area = $w * $h;
            if (!$best || $area > $best['area']) {
                $best = [
                    'url'  => $c['url'],
                    'body' => $d['body'],
                    'mime' => $mime,
                    'width' => $w,
                    'height' => $h,
                    'area' => $area,
                ];
            }
        }

        if (!$best) {
            throw new \RuntimeException('Favicon konnte nicht heruntergeladen werden (kein gültiges Bild)');
        }

        $ext = $this->extensionForMime($best['mime']);
        $filename = 'logo_' . $slug . '_' . time() . '.' . $ext;
        $destPath = rtrim($destDir, '/') . '/' . $filename;

        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        if (file_put_contents($destPath, $best['body']) === false) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden');
        }

        return [
            'filename' => $filename,
            'mime' => $best['mime'],
            'width' => $best['width'] === 10000 ? null : $best['width'],
            'height' => $best['height'] === 10000 ? null : $best['height'],
            'source_url' => $best['url'],
        ];
    }

    // ============ HTTP ============

    private function fetchHtml(string $url): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => self::HTML_TIMEOUT,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ThoxanBot/1.0; FaviconFetcher)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 400 || !is_string($html) || $html === '') {
            throw new \RuntimeException("Seite konnte nicht geladen werden (HTTP $code)");
        }
        return $html;
    }

    private function getFinalUrl(string $url): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => self::HTML_TIMEOUT,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ThoxanBot/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return is_string($effective) && $effective !== '' ? $effective : $url;
    }

    /**
     * Parallel-Download mehrerer URLs via curl_multi.
     * @param string[] $urls
     * @return array<string, array{body:string,content_type:string,http_code:int}>
     */
    private function downloadAll(array $urls): array
    {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urls as $u) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $u,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => self::IMG_TIMEOUT,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ThoxanBot/1.0)',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$u] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh, 1.0);
        } while ($active && $status === CURLM_OK);

        $results = [];
        foreach ($handles as $u => $ch) {
            $body = curl_multi_getcontent($ch);
            $results[$u] = [
                'body' => is_string($body) ? $body : '',
                'content_type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
                'http_code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $results;
    }

    // ============ Parsing ============

    /**
     * Extrahiert Favicon-Kandidaten aus HTML + Standard-Fallbacks.
     * @return array<int, array{url:string,score:int,rel:string}>
     */
    private function collectCandidates(string $html, string $baseUrl): array
    {
        $candidates = [];
        $seen = [];

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        foreach ($dom->getElementsByTagName('link') as $link) {
            /** @var \DOMElement $link */
            $rel = strtolower(trim((string) $link->getAttribute('rel')));
            if ($rel === '') continue;
            if (!preg_match('/(?:^|\s)(icon|shortcut icon|apple-touch-icon|apple-touch-icon-precomposed|mask-icon)(?:\s|$)/', $rel, $m)) continue;
            $relMatched = $m[1];

            $href = trim((string) $link->getAttribute('href'));
            if ($href === '') continue;
            $abs = $this->absoluteUrl($href, $baseUrl);
            if ($abs === '' || isset($seen[$abs])) continue;
            $seen[$abs] = true;

            $type = strtolower(trim((string) $link->getAttribute('type')));
            $sizes = strtolower(trim((string) $link->getAttribute('sizes')));

            $score = $this->scoreCandidate($abs, $relMatched, $type, $sizes);
            $candidates[] = ['url' => $abs, 'score' => $score, 'rel' => $relMatched];
        }

        // Fallbacks
        $root = $this->urlRoot($baseUrl);
        foreach ([
            ['/favicon.ico', 32, 'fallback-ico'],
            ['/apple-touch-icon.png', 180, 'fallback-apple'],
            ['/apple-touch-icon-precomposed.png', 180, 'fallback-apple'],
        ] as [$path, $score, $rel]) {
            $u = $root . $path;
            if (isset($seen[$u])) continue;
            $seen[$u] = true;
            $candidates[] = ['url' => $u, 'score' => $score, 'rel' => $rel];
        }

        return $candidates;
    }

    private function scoreCandidate(string $url, string $rel, string $type, string $sizes): int
    {
        $lowerUrl = strtolower($url);
        $isSvg = ($type === 'image/svg+xml') || str_ends_with($lowerUrl, '.svg');
        if ($isSvg) return 10000;

        // sizes kann "32x32 192x192 any" sein → max Dimension nehmen
        $max = 0;
        if ($sizes !== '') {
            if (str_contains($sizes, 'any')) $max = 9000; // "any" deutet auf SVG-ähnliches / großes Bild hin
            foreach (preg_split('/\s+/', $sizes) as $token) {
                if (preg_match('/^(\d+)x(\d+)$/', $token, $m)) {
                    $max = max($max, (int) $m[1], (int) $m[2]);
                }
            }
        }
        if ($max > 0) return $max;

        // Heuristik nach rel-Typ
        if (str_starts_with($rel, 'apple-touch-icon')) return 180;
        if ($rel === 'mask-icon') return 100;
        return 32;
    }

    // ============ URL-Helpers ============

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) return '';
        return $url;
    }

    private function urlRoot(string $url): string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host'])) return '';
        $scheme = $p['scheme'] ?? 'https';
        $port = isset($p['port']) ? ':' . $p['port'] : '';
        return $scheme . '://' . $p['host'] . $port;
    }

    private function absoluteUrl(string $href, string $base): string
    {
        $href = trim($href);
        if ($href === '') return '';
        if (preg_match('#^https?://#i', $href)) return $href;
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $href;
        }
        $root = $this->urlRoot($base);
        if ($root === '') return '';
        if (str_starts_with($href, '/')) return $root . $href;
        // Relative Pfade — auf Basis-Pfad auflösen
        $basePath = parse_url($base, PHP_URL_PATH) ?: '/';
        $basePath = preg_replace('#/[^/]*$#', '/', $basePath);
        return $root . $basePath . $href;
    }

    // ============ MIME ============

    private function detectMime(string $body, string $headerType): string
    {
        $headerMime = strtolower(trim(explode(';', $headerType)[0] ?? ''));

        // Wenn Server explizit HTML/XHTML/JSON sagt → niemals als Bild akzeptieren
        // (SPAs liefern oft ihre index.html mit HTTP 200 für unbekannte Pfade)
        if (in_array($headerMime, ['text/html', 'application/xhtml+xml', 'application/json', 'text/plain'], true)) {
            return '';
        }
        if (in_array($headerMime, self::ALLOWED_MIMES, true)) return $headerMime;

        // SVG strikt: muss mit <?xml oder <svg als Root-Element beginnen,
        // optional mit DOCTYPE/Kommentaren dazwischen. Nicht "irgendwo enthält".
        $trim = ltrim($body);
        $head = substr($trim, 0, 2048);
        if (preg_match('/^(?:<\?xml[^>]*>\s*)?(?:<!DOCTYPE[^>]*>\s*)?(?:<!--.*?-->\s*)*<svg\b/is', $head)) {
            return 'image/svg+xml';
        }
        // Falsch-Positive ausschließen: wenn der Anfang HTML-Markup ist, nie SVG
        if (preg_match('/^(?:<!DOCTYPE\s+html|<html\b|<head\b|<body\b)/i', $head)) {
            return '';
        }

        // Binary via finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($body);
        if (is_string($detected) && $detected !== '') {
            $detected = strtolower($detected);
            if ($detected === 'image/vnd.microsoft.icon') return 'image/x-icon';
            if (in_array($detected, ['text/html', 'application/xhtml+xml', 'application/json', 'text/plain', 'text/xml'], true)) return '';
            return $detected;
        }
        return '';
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png'                   => 'png',
            'image/jpeg'                  => 'jpg',
            'image/gif'                   => 'gif',
            'image/webp'                  => 'webp',
            'image/svg+xml'               => 'svg',
            'image/x-icon',
            'image/vnd.microsoft.icon'    => 'ico',
            default                       => 'img',
        };
    }
}
