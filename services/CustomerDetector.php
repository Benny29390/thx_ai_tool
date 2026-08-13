<?php

namespace Services;

use Core\Database;

/**
 * Erraet anhand eines Textes den passenden Kunden. Genutzt im Wissens-Ingest
 * wenn der User „KI waehlt Kunden" wählt, statt einen festen Kunden vorzugeben.
 *
 * Strategie: Wort-Match gegen Customer-Name, -Slug, -Kürzel, -Domain.
 * Pro Match Punkte vergeben; hoechster Score (mind. 3) gewinnt.
 */
class CustomerDetector
{
    private Database $db;
    private ?array $customers = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Findet den passenden Kunden. Liefert customer_id oder null.
     * $context kann zusaetzliche Hinweise enthalten (z.B. source_ref bei URLs).
     */
    public function detectFromText(string $text, array $context = []): ?int
    {
        $haystack = ' ' . mb_strtolower($text . ' ' . ($context['source_ref'] ?? '') . ' ' . ($context['title'] ?? '')) . ' ';
        $haystack = $this->normalize($haystack);

        $scores = [];
        foreach ($this->loadCustomers() as $c) {
            $score = 0;
            // Name (1 Punkt pro Vorkommen, max 5)
            if (!empty($c['name'])) {
                $needle = $this->normalize(' ' . mb_strtolower($c['name']) . ' ');
                $n = mb_substr_count($haystack, $needle);
                if ($n > 0) $score += min(5, $n) * 3;
            }
            // Slug (3 Punkte, da eindeutiger)
            if (!empty($c['slug'])) {
                $needle = ' ' . mb_strtolower($c['slug']) . ' ';
                if (str_contains($haystack, $needle)) $score += 3;
                // Auch in URLs gefunden (z.B. fryka.de)
                if (str_contains($haystack, '/' . mb_strtolower($c['slug']) . '/')) $score += 4;
            }
            // Kürzel (2 Punkte, kann aber zu generisch sein - nur als ganzes Wort)
            if (!empty($c['abbreviation']) && mb_strlen($c['abbreviation']) >= 3) {
                $needle = ' ' . mb_strtolower($c['abbreviation']) . ' ';
                if (str_contains($haystack, $needle)) $score += 2;
            }
            // Website-Domain
            $domains = $this->extractDomains($c);
            foreach ($domains as $d) {
                $needle = mb_strtolower($d);
                if (str_contains($haystack, $needle)) $score += 5;
            }
            if ($score > 0) $scores[(int) $c['id']] = $score;
        }

        if (empty($scores)) return null;
        arsort($scores);
        $top = array_key_first($scores);
        $topScore = $scores[$top];
        // Schwelle: mindestens 3 Punkte, sonst zu unsicher
        if ($topScore < 3) return null;
        // Wenn der zweitbeste Score sehr nah dran ist (>= 80%), ist es zu mehrdeutig
        $values = array_values($scores);
        if (count($values) > 1 && $values[1] >= $values[0] * 0.8) {
            return null;
        }
        return (int) $top;
    }

    private function loadCustomers(): array
    {
        if ($this->customers !== null) return $this->customers;
        $this->customers = $this->db->query(
            "SELECT id, name, slug, abbreviation, website, settings FROM customers WHERE is_active = 1"
        ) ?: [];
        return $this->customers;
    }

    private function extractDomains(array $c): array
    {
        $out = [];
        if (!empty($c['website'])) {
            $out[] = $this->hostOf($c['website']);
        }
        $s = json_decode($c['settings'] ?? '{}', true) ?: [];
        foreach ($s['domains'] ?? [] as $d) {
            if (!empty($d['url'])) $out[] = $this->hostOf($d['url']);
        }
        return array_values(array_filter(array_unique($out)));
    }

    private function hostOf(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) $url = 'http://' . $url;
        $h = parse_url($url, PHP_URL_HOST) ?: '';
        return preg_replace('/^www\./', '', mb_strtolower($h));
    }

    private function normalize(string $s): string
    {
        $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
        return preg_replace('/\s+/', ' ', $s);
    }
}
