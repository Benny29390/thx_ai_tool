<?php
/**
 * CrmAnreicherungService — KI-gestützte Anreicherung von CRM-Firmen aus deren
 * Website-Impressum.
 *
 * Flow:
 *  1. Sammele alle Websites aus den übergebenen Records (Dublette = mehrere
 *     Quellen → nimm die, die einen Treffer gibt)
 *  2. Hole das Impressum (heuristisch über /impressum, Link-Scan auf Homepage)
 *  3. Strip HTML zu Plain Text (Impressum ist meist klein, <50k Zeichen)
 *  4. LLM extrahiert strukturierte Felder als JSON
 *  5. Returnt + Quell-URL für Transparenz
 *
 * Nutzt lokale LLMs wenn verfügbar (qwen/gpt-oss) — sonst OpenAI-Fallback.
 */

namespace Services;

use Core\Database;
use Core\Settings;

class CrmAnreicherungService
{
    private const HTTP_TIMEOUT = 10;
    private const MAX_HTML_SIZE = 500_000; // 500 KB
    private const MAX_TEXT_FOR_LLM = 12_000; // ~3k Tokens
    private const USER_AGENT = 'Mozilla/5.0 (compatible; ThoxanCRM/1.0; +https://thoxan.de)';

    private const IMPRESSUM_PFADE = [
        '/impressum', '/impressum/', '/impressum.html', '/impressum.htm', '/impressum.php',
        '/imprint', '/imprint/', '/imprint.html',
        '/legal-notice', '/legal/', '/rechtliches/impressum',
        '/kontakt/impressum', '/de/impressum', '/ueber-uns/impressum',
    ];

    public function __construct(private Database $db) {}

    /**
     * Reichert die übergebenen Firma-IDs aus deren Impressum an.
     *
     * Returns: [
     *   'fields' => ['firmenname'=>'...','branche'=>'...','telefon'=>'...', ...],
     *   'quelle_url' => 'https://...',
     *   'quelle_firma_id' => 1234,
     *   'confidence' => 0.85,
     *   'fehler' => null | 'Beschreibung was nicht klappte',
     * ]
     */
    /**
     * Reichert einen einzelnen Kontakt an: nutzt Firma + Name als Anker für
     * Web-Search (LinkedIn / Firmen-Website / öffentliche Quellen).
     */
    public function anreichereKontakt(int $kontaktId): array
    {
        $k = $this->db->queryOne(
            "SELECT k.*, f.firmenname, f.website AS firma_website
             FROM crm_kontakte k LEFT JOIN crm_firmen f ON f.id = k.firma_id
             WHERE k.id = ? AND k.geloescht_am IS NULL",
            [$kontaktId]
        );
        if (!$k) return ['fehler' => 'Kontakt nicht gefunden'];

        $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
        if ($name === '') return ['fehler' => 'Kontakt hat keinen Namen'];

        // Web-Search nach Name + Firma
        $braveKey = (string) Settings::get('brave_search_api_key');
        $openaiKey = (string) Settings::get('openai_api_key');
        if ($braveKey === '' || $openaiKey === '') return ['fehler' => 'API-Keys fehlen'];

        $query = $name . ' ' . (string) $k['firmenname'];
        $query = trim($query);
        try {
            $web = new WebSearchService($braveKey);
            $sr = $web->search($query, 5);
            $treffer = $sr['results'] ?? [];
            if (empty($treffer)) return ['fehler' => 'Keine Web-Treffer für „' . $query . '"'];

            $kontext = [];
            $quellUrls = [];
            foreach (array_slice($treffer, 0, 2) as $t) {
                $url = $t['url'] ?? '';
                if (!$url) continue;
                $html = $this->httpGet($url);
                if (!$html) continue;
                $text = $this->htmlZuText($html);
                $kontext[] = '## ' . $url . "\n\n" . mb_substr($text, 0, 3500);
                $quellUrls[] = $url;
            }
            if (empty($kontext)) return ['fehler' => 'Web-Treffer-Seiten nicht ladbar'];

            $ai = new AIService($openaiKey, 'openai');
            if (method_exists($ai, 'setModel')) $ai->setModel('gpt-4o-mini');
            $sys = 'Du extrahierst öffentlich verfügbare Daten zu einer Person aus Web-Such-Treffern. '
                . 'Gib AUSSCHLIESSLICH valides JSON zurück. Bei Unsicherheit Feld weglassen, nicht raten.';
            $userPrompt = "Person: \"$name\"" . ($k['firmenname'] ? ' bei „' . $k['firmenname'] . '"' : '') . "\n\n"
                . "Web-Treffer:\n\n" . implode("\n\n---\n\n", $kontext) . "\n\n"
                . 'Extrahiere JSON: {funktion, abteilung, telefon, mobil, email, website, linkedin, xing, beschreibung, _confidence}';
            $resp = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $sys);
            $content = trim($resp['content'] ?? '');
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) $content = $m[1];
            $data = json_decode($content, true);
            if (!is_array($data)) return ['fehler' => 'LLM-Antwort kein JSON'];
            return [
                'fields' => $data,
                'quelle_url' => $quellUrls[0] ?? null,
                'quelle_kontakt_id' => $kontaktId,
                'confidence' => $data['_confidence'] ?? 0.5,
                'modus' => 'websearch_kontakt',
                'web_treffer' => array_map(fn($t) => ['title' => $t['title'] ?? '', 'url' => $t['url'] ?? ''], array_slice($treffer, 0, 3)),
                'fehler' => null,
            ];
        } catch (\Throwable $e) {
            error_log('CrmAnreicherung Kontakt: ' . $e->getMessage());
            return ['fehler' => 'Anreicherung fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    /**
     * Speziell für „fehlt_linkedin": sucht über Brave Search nach
     * „Vorname Nachname Firma site:linkedin.com/in" und nimmt die beste URL.
     * Wir crawlen LinkedIn-Profile NICHT (Blockade + AGB-Risiko) — nur die
     * Profil-URL wird vorgeschlagen, der User bestätigt sie.
     */
    public function anreichereLinkedin(int $kontaktId): array
    {
        // Backwards-Compatibility-Wrapper — liefert ersten Kandidaten als „fields".
        $r = $this->sucheLinkedinKandidaten($kontaktId);
        if (!empty($r['fehler'])) return $r;
        $top = $r['kandidaten'][0] ?? null;
        if (!$top) return ['fehler' => $r['fehler'] ?? 'Keine Kandidaten'];
        return [
            'fields' => ['linkedin' => $top['url']],
            'quelle_url' => $top['url'],
            'quelle_kontakt_id' => $kontaktId,
            'confidence' => 0.8,
            'modus' => 'linkedin_search',
            'web_treffer' => $r['kandidaten'],
            'fehler' => null,
        ];
    }

    /**
     * Liefert mehrere LinkedIn-Profil-Kandidaten zur User-Auswahl.
     * Pro Treffer: URL, Title (oft „Name — Funktion — Firma"), Description (Snippet),
     * Profilbild-Thumbnail (Brave-CDN). Damit kann der User im Modal das richtige Profil
     * anhand Foto + Firma erkennen.
     */
    public function sucheLinkedinKandidaten(int $kontaktId): array
    {
        $k = $this->db->queryOne(
            "SELECT k.vorname, k.nachname, k.funktion, f.firmenname
             FROM crm_kontakte k LEFT JOIN crm_firmen f ON f.id = k.firma_id
             WHERE k.id = ? AND k.geloescht_am IS NULL",
            [$kontaktId]
        );
        if (!$k) return ['fehler' => 'Kontakt nicht gefunden'];
        $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
        if ($name === '') return ['fehler' => 'Kontakt hat keinen Namen'];

        $braveKey = (string) Settings::get('brave_search_api_key');
        if ($braveKey === '') return ['fehler' => 'Brave-Search-API-Key fehlt'];

        $query = $name . ' ' . (string) $k['firmenname'] . ' site:linkedin.com/in';
        try {
            $web = new WebSearchService($braveKey);
            $sr = $web->search(trim($query), 10);
            $treffer = $sr['results'] ?? [];
            $seen = []; // gegen Duplikate (de./www. + Trailing-Slash)
            $kandidaten = [];
            foreach ($treffer as $t) {
                $url = $t['url'] ?? '';
                if (!$url || !preg_match('#linkedin\.com/in/#i', $url)) continue;
                $url = preg_replace('#\?.*$#', '', $url);
                $url = rtrim($url, '/');
                $key = strtolower(preg_replace('#^https?://(?:[a-z]{2}\.|www\.)?#i', '', $url));
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $kandidaten[] = [
                    'url' => $url,
                    'title' => $t['title'] ?? '',
                    'description' => strip_tags((string) ($t['description'] ?? '')),
                    'thumbnail' => $t['thumbnail'] ?? null,
                    'favicon' => $t['favicon'] ?? null,
                ];
                if (count($kandidaten) >= 8) break;
            }
            if (empty($kandidaten)) {
                return [
                    'kandidaten' => [],
                    'query' => $query,
                    'fehler' => 'Keine LinkedIn-Profile in den Brave-Suchtreffern für „' . trim($name . ' ' . (string) $k['firmenname']) . '"',
                ];
            }
            return ['kandidaten' => $kandidaten, 'query' => $query, 'fehler' => null];
        } catch (\Throwable $e) {
            error_log('CrmAnreicherung LinkedIn-Kandidaten: ' . $e->getMessage());
            return ['fehler' => 'LinkedIn-Suche fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    public function anreichereFirma(array $firmaIds): array
    {
        $firmen = [];
        foreach ($firmaIds as $id) {
            $f = $this->db->queryOne(
                "SELECT id, firmenname, website FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL",
                [(int) $id]
            );
            if ($f) $firmen[] = $f;
        }
        if (empty($firmen)) {
            return ['fehler' => 'Keine Firma gefunden'];
        }

        // Versuche alle Websites — nimm die erste die ein verwertbares Impressum liefert
        $tried = [];
        foreach ($firmen as $firma) {
            $website = trim((string) ($firma['website'] ?? ''));
            if ($website === '') continue;
            $tried[] = $website;
            $impressum = $this->holeImpressumText($website);
            if ($impressum === null) continue;
            $felder = $this->llmExtraktion($impressum['text'], $firma['firmenname'], $impressum['url']);
            if ($felder !== null) {
                // Website-Fallback: wenn LLM keine Website extrahiert hat,
                // nimm Schema+Host der Quelle (Impressum-URL) — das IST die Website.
                if (empty($felder['website'])) {
                    $scheme = parse_url($impressum['url'], PHP_URL_SCHEME);
                    $host   = parse_url($impressum['url'], PHP_URL_HOST);
                    if ($scheme && $host) $felder['website'] = $scheme . '://' . $host;
                }
                return [
                    'fields' => $felder,
                    'quelle_url' => $impressum['url'],
                    'quelle_firma_id' => (int) $firma['id'],
                    'confidence' => $felder['_confidence'] ?? 0.7,
                    'fehler' => null,
                    'modus' => 'impressum',
                ];
            }
        }

        // Fallback: Web-Search mit Firmenname (+ ggf. Stadt aus Adresse)
        $webResult = $this->webSearchFallback($firmen);
        if ($webResult !== null) return $webResult;

        return [
            'fehler' => empty($tried)
                ? 'Keine Website hinterlegt und Web-Suche brachte keinen verwertbaren Treffer.'
                : 'Kein Impressum auf ' . implode(', ', $tried) . ' und Web-Suche ohne Treffer.',
        ];
    }

    /**
     * Wenn kein Impressum gefunden wurde, mache eine Brave-Web-Search nach
     * Firmenname (+ Stadt wenn bekannt), hole die Top-Treffer-Seite und
     * lass das LLM versuchen Daten zu extrahieren — oder erkennen dass
     * die Firma nicht mehr existiert.
     */
    private function webSearchFallback(array $firmen): ?array
    {
        $braveKey = (string) Settings::get('brave_search_api_key');
        if ($braveKey === '') return null;
        $openaiKey = (string) Settings::get('openai_api_key');
        if ($openaiKey === '') return null;

        // Suchbegriff zusammenstellen — nimm den Firmennamen + ggf. Stadt
        $firma = $firmen[0]; // Hauptkandidat
        $name = trim((string) $firma['firmenname']);
        if ($name === '') return null;
        // Stadt aus Adresse ergänzen falls verfügbar
        $stadt = (string) $this->db->queryValue(
            "SELECT stadt FROM crm_adressen WHERE firma_id = ? AND stadt IS NOT NULL LIMIT 1",
            [(int) $firma['id']]
        );
        $query = $stadt ? ($name . ' ' . $stadt . ' impressum') : ($name . ' impressum');

        try {
            $web = new WebSearchService($braveKey);
            $sr = $web->search($query, 5);
            $treffer = $sr['results'] ?? [];
            if (empty($treffer)) return ['fehler' => 'Web-Search ohne Treffer für „' . $query . '"'];

            // Wenn die Firma eine eigene Domain hat: NUR Treffer auf genau dieser
            // Domain akzeptieren. Fremd-Domains führen sonst regelmäßig zu Daten
            // einer ganz anderen Firma/Person (typisch bei Einzelunternehmen, wo
            // der erste Brave-Treffer die Inhaber-Privatseite ist).
            $masterHost = strtolower((string) parse_url($this->normalizeUrl((string) ($firma['website'] ?? '')) ?? '', PHP_URL_HOST));
            $masterHost = $masterHost === '' ? '' : preg_replace('/^www\./', '', $masterHost);
            if ($masterHost !== '') {
                $alle = $treffer;
                $treffer = array_values(array_filter($treffer, function ($t) use ($masterHost) {
                    $h = preg_replace('/^www\./', '', strtolower((string) parse_url($t['url'] ?? '', PHP_URL_HOST)));
                    return $h === $masterHost;
                }));
                if (empty($treffer)) {
                    return [
                        'fehler' => 'Kein Impressum auf der hinterlegten Website (' . $masterHost . ') gefunden — '
                            . 'die Web-Suche fand nur Treffer auf fremden Domains, die ignoriert wurden, um falsche Daten zu vermeiden.',
                        'web_treffer' => array_map(fn($t) => ['title' => $t['title'] ?? '', 'url' => $t['url'] ?? ''], array_slice($alle, 0, 3)),
                    ];
                }
            }

            // Hole die ersten 2 Seiten + sammle Text
            $kontext = [];
            $quellUrls = [];
            foreach (array_slice($treffer, 0, 2) as $t) {
                $url = $t['url'] ?? '';
                if (!$url) continue;
                $html = $this->httpGet($url);
                if (!$html) continue;
                $text = $this->htmlZuText($html);
                $kontext[] = '## Quelle: ' . $url . "\n\n" . mb_substr($text, 0, 4000);
                $quellUrls[] = $url;
            }
            if (empty($kontext)) {
                return ['fehler' => 'Treffer-Seiten konnten nicht geladen werden'];
            }

            $combined = implode("\n\n---\n\n", $kontext);

            // LLM mit speziellem Prompt: extrahieren ODER "Firma existiert nicht mehr"
            $ai = new AIService($openaiKey, 'openai');
            if (method_exists($ai, 'setModel')) $ai->setModel('gpt-4o-mini');
            $sys = 'Du analysierst Web-Such-Treffer um eine Firma zu identifizieren und Stammdaten zu extrahieren. '
                . 'Gib AUSSCHLIESSLICH ein valides JSON-Objekt zurück. '
                . 'Wenn die Firma in den Treffern nicht zweifelsfrei wiederzufinden ist (z.B. komplett andere Firmen, '
                . 'Treffer beziehen sich auf etwas anderes, „Domain steht zum Verkauf"), gib zurück: '
                . '{"_existiert_nicht_mehr": true, "_grund": "...", "_confidence": 0.0-1.0}';

            $userPrompt = "Gesuchte Firma: \"$name\"" . ($stadt ? " (Stadt: $stadt)" : '') . "\n\n"
                . "Web-Suche-Treffer:\n\n" . $combined . "\n\n"
                . 'Extrahiere folgende Felder (alle optional). Bei Unsicherheit weglassen, nicht raten:'
                . "\n{"
                . "\n  \"firmenname\": \"...\","
                . "\n  \"branche\": \"2-4 Worte\","
                . "\n  \"firmen_typ\": \"GmbH | AG | UG | KG | GbR | Einzelunternehmen | e.V. | Sonstige\","
                . "\n  \"telefon\": \"+49 ...\","
                . "\n  \"fax\": \"+49 ...\","
                . "\n  \"email\": \"info@...\","
                . "\n  \"website\": \"https://...\","
                . "\n  \"adresse_strasse\": \"...\","
                . "\n  \"adresse_plz\": \"...\","
                . "\n  \"adresse_stadt\": \"...\","
                . "\n  \"adresse_land\": \"Deutschland\","
                . "\n  \"beschreibung\": \"was die Firma macht\","
                . "\n  \"_confidence\": 0.0-1.0"
                . "\n}"
                . "\n\nALTERNATIV — wenn nicht auffindbar:"
                . "\n{\"_existiert_nicht_mehr\": true, \"_grund\": \"warum\", \"_confidence\": 0.0-1.0}";

            $response = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $sys);
            $content = trim($response['content'] ?? '');
            if ($content === '') return ['fehler' => 'LLM lieferte leere Antwort'];
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) $content = $m[1];
            $data = json_decode($content, true);
            if (!is_array($data)) return ['fehler' => 'LLM-Antwort war kein gültiges JSON'];

            return [
                'fields' => $data,
                'quelle_url' => $quellUrls[0] ?? null,
                'quelle_firma_id' => (int) $firma['id'],
                'confidence' => $data['_confidence'] ?? 0.5,
                'modus' => 'websearch',
                'web_treffer' => array_map(fn($t) => ['title' => $t['title'] ?? '', 'url' => $t['url'] ?? ''], array_slice($treffer, 0, 3)),
                'existiert_nicht_mehr' => !empty($data['_existiert_nicht_mehr']),
                'fehler' => null,
            ];
        } catch (\Throwable $e) {
            error_log('CrmAnreicherung WebSearch: ' . $e->getMessage());
            return ['fehler' => 'Web-Suche fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    /**
     * Lädt das Impressum einer Website. Versucht erst bekannte Pfade,
     * dann scannt die Homepage nach einem Impressum-Link.
     */
    private function holeImpressumText(string $website): ?array
    {
        $base = $this->normalizeUrl($website);
        if (!$base) return null;
        $baseHost = parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST);

        // 1. Direkte Impressum-Pfade probieren
        foreach (self::IMPRESSUM_PFADE as $pfad) {
            $url = $baseHost . $pfad;
            $html = $this->httpGet($url);
            if ($html === null) continue;
            $text = $this->htmlZuText($html);
            // Plausibilität: "impressum" oder typische Pflichtangaben drin?
            if ($this->istImpressum($text)) {
                return ['url' => $url, 'text' => mb_substr($text, 0, self::MAX_TEXT_FOR_LLM)];
            }
        }

        // 2. Homepage holen, nach Impressum-Link scannen
        $homepage = $this->httpGet($base);
        if ($homepage === null) return null;
        $linkUrl = $this->findeImpressumLink($homepage, $base);
        if ($linkUrl) {
            // Impressum-Link MUSS auf derselben Domain liegen — externe Impressum-Links
            // führen sonst zu Daten einer anderen rechtlichen Entität (Inhaber-Privatseite).
            $linkHost = preg_replace('/^www\./', '', strtolower((string) parse_url($linkUrl, PHP_URL_HOST)));
            $baseHostName = preg_replace('/^www\./', '', strtolower((string) parse_url($base, PHP_URL_HOST)));
            if ($linkHost !== '' && $linkHost === $baseHostName) {
                $html = $this->httpGet($linkUrl);
                if ($html !== null) {
                    $text = $this->htmlZuText($html);
                    if ($this->istImpressum($text)) {
                        return ['url' => $linkUrl, 'text' => mb_substr($text, 0, self::MAX_TEXT_FOR_LLM)];
                    }
                }
            }
        }

        // 3. Fallback: nimm Homepage selbst (für Mini-Sites ohne separates Impressum)
        $text = $this->htmlZuText($homepage);
        if ($this->istImpressum($text)) {
            return ['url' => $base, 'text' => mb_substr($text, 0, self::MAX_TEXT_FOR_LLM)];
        }

        return null;
    }

    private function findeImpressumLink(string $html, string $baseUrl): ?string
    {
        if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>([^<]*)<\/a>/i', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $href = $match[1];
                $text = strtolower(trim($match[2]));
                if (str_contains(strtolower($href), 'impressum') || $text === 'impressum' || str_contains($text, 'impressum')) {
                    return $this->absolutUrl($href, $baseUrl);
                }
            }
        }
        return null;
    }

    private function istImpressum(string $text): bool
    {
        $low = mb_strtolower($text);
        $score = 0;
        if (str_contains($low, 'impressum')) $score += 2;
        if (preg_match('/handelsregister|hrb|hra/u', $low)) $score++;
        if (str_contains($low, 'umsatzsteuer') || str_contains($low, 'ust-id')) $score++;
        if (preg_match('/geschäftsführer|geschaeftsfuehrer|vorstand|inhaber/u', $low)) $score++;
        if (preg_match('/\bvertretungsberechtigt\b/u', $low)) $score++;
        return $score >= 2;
    }

    private function llmExtraktion(string $impressumText, string $bekannterName, string $quelleUrl): ?array
    {
        $openaiKey = (string) Settings::get('openai_api_key');
        if ($openaiKey === '') return null;

        $sys = 'Du extrahierst strukturierte Firmendaten aus deutschen Impressum-Texten. '
            . 'Gib AUSSCHLIESSLICH ein valides JSON-Objekt zurück — keine Erklärungen, kein Markdown-Codeblock. '
            . 'Wenn ein Feld nicht eindeutig im Text steht: weglassen (nicht raten). '
            . 'Telefonnummern im internationalen Format (+49 ...). Adressen aus dem Impressum, nicht aus Beispielen.';

        $userPrompt = "Bekannter Firmenname: \"$bekannterName\"\nImpressum-Quelle: $quelleUrl\n\n"
            . "Impressum-Text:\n```\n$impressumText\n```\n\n"
            . 'Extrahiere folgende Felder (alle optional, JSON-Format):'
            . "\n{"
            . "\n  \"firmenname\": \"offizielle Firmierung (z.B. \\\"Müller GmbH & Co. KG\\\")\","
            . "\n  \"branche\": \"Hauptgeschäftsbereich in 2-4 Worten (z.B. \\\"Maschinenbau\\\", \\\"IT-Beratung\\\")\","
            . "\n  \"firmen_typ\": \"GmbH | AG | UG | KG | GbR | Einzelunternehmen | e.V. | Sonstige\","
            . "\n  \"telefon\": \"+49 ...\","
            . "\n  \"fax\": \"+49 ...\","
            . "\n  \"email\": \"info@...\","
            . "\n  \"website\": \"https://...\","
            . "\n  \"adresse_strasse\": \"Musterstraße 12\","
            . "\n  \"adresse_plz\": \"12345\","
            . "\n  \"adresse_stadt\": \"Berlin\","
            . "\n  \"adresse_land\": \"Deutschland\","
            . "\n  \"geschaeftsfuehrung\": [\"Vor Name\", \"Vor Name\"],"
            . "\n  \"handelsregister\": \"HRB 12345 Amtsgericht Berlin\","
            . "\n  \"ust_id\": \"DE123456789\","
            . "\n  \"beschreibung\": \"1-2 Sätze was die Firma macht\","
            . "\n  \"_confidence\": 0.0-1.0 (wie sicher dass das die richtige Firma ist)"
            . "\n}";

        try {
            $ai = new AIService($openaiKey, 'openai');
            // Schnelles, günstiges Modell — Extraktion ist deterministisch
            if (method_exists($ai, 'setModel')) $ai->setModel('gpt-4o-mini');
            $response = $ai->chat(
                [['role' => 'user', 'content' => $userPrompt]],
                $sys
            );
            $content = trim($response['content'] ?? '');
            if ($content === '') return null;

            // Robust JSON extrahieren — manchmal kommt Markdown-Codeblock trotz Verbot
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
                $content = $m[1];
            }
            $data = json_decode($content, true);
            if (!is_array($data)) {
                error_log('CrmAnreicherung: kein valides JSON vom LLM: ' . substr($content, 0, 200));
                return null;
            }
            return $data;
        } catch (\Throwable $e) {
            error_log('CrmAnreicherung LLM-Fehler: ' . $e->getMessage());
            return null;
        }
    }

    // ───────────────────────────── HTTP / HTML ─────────────────────────────

    private function httpGet(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => self::HTTP_TIMEOUT,
                'user_agent' => self::USER_AGENT,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
                'header' => "Accept: text/html,application/xhtml+xml\r\nAccept-Language: de-DE,de;q=0.9\r\n",
            ],
            'https' => [
                'timeout' => self::HTTP_TIMEOUT,
                'user_agent' => self::USER_AGENT,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
                'header' => "Accept: text/html,application/xhtml+xml\r\nAccept-Language: de-DE,de;q=0.9\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') return null;
        // Status prüfen
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/[\d.]+\s+(\d+)#', $h, $m)) $status = (int) $m[1];
        }
        if ($status >= 400) return null;
        if (mb_strlen($body) > self::MAX_HTML_SIZE) $body = mb_substr($body, 0, self::MAX_HTML_SIZE);
        return $body;
    }

    private function htmlZuText(string $html): string
    {
        // Scripts + Styles raus
        $html = preg_replace('#<script[^>]*>.*?</script>#is', ' ', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', ' ', $html);
        // Block-Elemente in Zeilenumbrüche
        $html = preg_replace('#<(br|p|div|li|tr|h[1-6])[^>]*>#i', "\n", $html);
        // Alle Tags weg
        $text = strip_tags($html);
        // HTML-Entities decodieren
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Mehrfache Whitespaces normalisieren
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        return trim($text);
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function absolutUrl(string $url, string $base): string
    {
        if (preg_match('#^https?://#i', $url)) return $url;
        $b = parse_url($base);
        $scheme = $b['scheme'] ?? 'https';
        $host = $b['host'] ?? '';
        if (str_starts_with($url, '//')) return $scheme . ':' . $url;
        if (str_starts_with($url, '/')) return $scheme . '://' . $host . $url;
        return $scheme . '://' . $host . '/' . $url;
    }
}
