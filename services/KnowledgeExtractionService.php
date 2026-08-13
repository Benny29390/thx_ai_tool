<?php
/**
 * Knowledge Extraction Service — LLM-basierte Metadaten + Entity/Relation Extraktion
 *
 * Ein LLM-Call liefert:
 *  - Titel, Beschreibung, Kunde, Kategorie, Tags
 *  - Entities (pro Typ und mit chunk-Verweis)
 *  - Relations zwischen Entities
 */

namespace Services;

class KnowledgeExtractionService
{
    private AIService $ai;

    // Erlaubte Relation-Typen (Kratz-konform)
    public const ALLOWED_RELATIONS = [
        'gehoert_zu', 'produziert', 'befindet_sich_in', 'verwendet',
        'entwickelt', 'ist_teil_von', 'hat_eigenschaft', 'beeinflusst',
        'entstand_am', 'relates_to'
    ];

    public const ENTITY_TYPES = ['PER', 'ORG', 'LOC', 'PRODUCT', 'CONCEPT', 'EVENT', 'MISC'];

    /** @var int Akkumulierte Extraction-Input-Tokens (für Sync-Jobs) */
    public int $tokensIn = 0;
    /** @var int Akkumulierte Extraction-Output-Tokens */
    public int $tokensOut = 0;

    public function __construct(AIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Extrahiert Metadaten + Entities + Relations aus einem Text
     *
     * @param string $text Vollstaendiger Text (oder Zusammenfassung falls zu lang)
     * @param array $context Optional: ['customer_name' => 'FRYKA', 'existing_chunks' => 42]
     * @return array {title, description, customer_suggestion, category, tags, entities, relations}
     */
    public function extract(string $text, array $context = []): array
    {
        // Text ggf. kuerzen fuer LLM-Context
        $maxTextLen = 30000;
        if (mb_strlen($text) > $maxTextLen) {
            // Anfang + Ende nehmen (Mitte kuerzen)
            $half = intval($maxTextLen / 2);
            $text = mb_substr($text, 0, $half) . "\n\n[... gekuerzt ...]\n\n" . mb_substr($text, -$half);
        }

        $customerHint = '';
        if (!empty($context['customer_name'])) {
            $customerHint = "Hinweis: Der Upload erfolgt im Kontext des Kunden \"{$context['customer_name']}\".";
        }

        $relationsList = implode(', ', self::ALLOWED_RELATIONS);
        $entityTypesList = implode(', ', self::ENTITY_TYPES);

        $systemPrompt = 'Du bist ein praeziser Daten-Extraktor. Du antwortest IMMER mit valid JSON. Kein Markdown, kein Kommentar.';

        $userPrompt = <<<PROMPT
Analysiere den folgenden Text und extrahiere strukturiert:

1. TITEL (max 100 Zeichen, praegnant aus dem Inhalt)
2. BESCHREIBUNG (1-2 Saetze, fachliche Zusammenfassung)
3. KUNDE (Firmen-/Marken-Name falls erkennbar, sonst "Allgemein")
4. KATEGORIE (eine von: Produktinfo, Referenz, Prozess, FAQ, Notiz, Rechtlich, Technik, Marketing, Sonstige)
5. TAGS (3-7 pragnante Stichworte, lowercase)
6. ENTITIES (Personen, Firmen, Orte, Produkte, Konzepte, Events)
   - Pro Entity: name (Original-Schreibweise), type (einer von: {$entityTypesList})
7. RELATIONS (Verknuepfungen zwischen Entities)
   - Pro Relation: from (entity name), to (entity name), type (einer von: {$relationsList}), weight (0.0-1.0)

{$customerHint}

WICHTIG: Antworte NUR mit gueltigem JSON nach diesem Schema:

{
  "title": "...",
  "description": "...",
  "customer_suggestion": "...",
  "category": "Produktinfo",
  "tags": ["tag1", "tag2"],
  "entities": [
    {"name": "FRYKA GmbH", "type": "ORG"},
    {"name": "Laborkuehlung", "type": "CONCEPT"}
  ],
  "relations": [
    {"from": "FRYKA GmbH", "to": "Laborkuehlung", "type": "produziert", "weight": 0.9}
  ]
}

TEXT:
---
{$text}
---
PROMPT;

        try {
            // Billiges, schnelles Modell fuer Extraktion (AIService wird mit dedicated instance genutzt)
            $this->ai->setModel('gpt-4o-mini');

            $response = $this->ai->chat(
                [['role' => 'user', 'content' => $userPrompt]],
                $systemPrompt
            );

            $this->tokensIn += (int) ($response['tokens']['input'] ?? 0);
            $this->tokensOut += (int) ($response['tokens']['output'] ?? 0);

            $content = $response['content'] ?? '';
            $json = $this->parseJson($content);

            return $this->validateAndClean($json);

        } catch (\Exception $e) {
            error_log('KnowledgeExtraction Fehler: ' . $e->getMessage());
            // Fallback: leere Struktur
            return $this->emptyResult();
        }
    }

    /**
     * Extrahiert Entity-IDs pro Chunk (nach Commit)
     * Nutzt einfache case-insensitive Volltextsuche
     */
    public function mapEntitiesToChunks(array $chunks, array $entities): array
    {
        $result = [];
        foreach ($chunks as $i => $chunk) {
            $content = mb_strtolower($chunk['content']);
            $matched = [];
            foreach ($entities as $entity) {
                $name = mb_strtolower($entity['name']);
                if ($name !== '' && mb_strpos($content, $name) !== false) {
                    $matched[] = $entity['name'];
                }
            }
            $result[$i] = array_unique($matched);
        }
        return $result;
    }

    // ===== Helpers =====

    private function parseJson(string $content): array
    {
        $content = trim($content);

        // Versuch 1: Direkt
        $data = json_decode($content, true);
        if (is_array($data)) return $data;

        // Versuch 2: Markdown-Codeblock entfernen
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
            $data = json_decode($m[1], true);
            if (is_array($data)) return $data;
        }

        // Versuch 3: Erste {...} extrahieren
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) return $data;
        }

        throw new \Exception('LLM-Antwort kein gueltiges JSON');
    }

    private function validateAndClean(array $data): array
    {
        $clean = $this->emptyResult();

        $clean['title'] = trim((string)($data['title'] ?? 'Unbenannt'));
        $clean['description'] = trim((string)($data['description'] ?? ''));
        $clean['customer_suggestion'] = trim((string)($data['customer_suggestion'] ?? 'Allgemein'));
        $clean['category'] = trim((string)($data['category'] ?? 'Sonstige'));

        // Tags
        if (is_array($data['tags'] ?? null)) {
            $clean['tags'] = array_map(fn($t) => mb_strtolower(trim((string)$t)), array_slice($data['tags'], 0, 10));
            $clean['tags'] = array_values(array_filter($clean['tags'], fn($t) => $t !== ''));
        }

        // Entities (dedupe by normalized name)
        if (is_array($data['entities'] ?? null)) {
            $seen = [];
            foreach ($data['entities'] as $e) {
                if (!is_array($e) || empty($e['name'])) continue;
                $name = trim((string)$e['name']);
                $type = in_array($e['type'] ?? '', self::ENTITY_TYPES) ? $e['type'] : 'CONCEPT';
                $key = mb_strtolower($name) . ':' . $type;
                if (!isset($seen[$key])) {
                    $clean['entities'][] = ['name' => $name, 'type' => $type];
                    $seen[$key] = true;
                }
            }
        }

        // Relations
        if (is_array($data['relations'] ?? null)) {
            $entityNames = array_map(fn($e) => $e['name'], $clean['entities']);
            $entityNamesLower = array_map('mb_strtolower', $entityNames);
            foreach ($data['relations'] as $r) {
                if (!is_array($r)) continue;
                $from = trim((string)($r['from'] ?? ''));
                $to = trim((string)($r['to'] ?? ''));
                $type = $r['type'] ?? 'relates_to';
                if (!in_array($type, self::ALLOWED_RELATIONS)) $type = 'relates_to';
                $weight = max(0.0, min(1.0, (float)($r['weight'] ?? 0.5)));

                // Nur wenn from und to in entities existieren (case-insensitive match)
                $fromIdx = array_search(mb_strtolower($from), $entityNamesLower);
                $toIdx = array_search(mb_strtolower($to), $entityNamesLower);
                if ($fromIdx !== false && $toIdx !== false && $fromIdx !== $toIdx) {
                    $clean['relations'][] = [
                        'from' => $entityNames[$fromIdx], // Canonical-Name aus Entities
                        'to' => $entityNames[$toIdx],
                        'type' => $type,
                        'weight' => $weight,
                    ];
                }
            }
        }

        return $clean;
    }

    private function emptyResult(): array
    {
        return [
            'title' => 'Unbenannt',
            'description' => '',
            'customer_suggestion' => 'Allgemein',
            'category' => 'Sonstige',
            'tags' => [],
            'entities' => [],
            'relations' => [],
        ];
    }
}
