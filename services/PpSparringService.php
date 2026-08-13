<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * PpSparringService — KI-Sparring für den Projektplanner.
 *
 * Dialog pro Plan (fortsetzbar), mit planner-spezifischem Kontext (Plan-Stand +
 * Kunden-Steckbrief + Taxonomie + Projektplanner-Regeln), kundenübergreifenden
 * Impulsen aus anderen Plänen (RAG) und einem Materialisier-Schritt, der die im
 * Gespräch vereinbarten Schritte als strukturierte Vorschläge liefert.
 *
 * Das eigentliche Streaming macht der Endpoint (SSE) via AIService::chatStream —
 * dieser Service liefert die Bausteine (Prompt, Impulse, Persistenz, Materialize).
 */
class PpSparringService
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-opus-4-7';
    private const MAX_TOKENS = 16000; // genug für viele Vorschläge — sonst abgeschnittener Tool-Aufruf
    private const TIMEOUT_S = 120;
    private const IMPULSE_K = 6;

    private Database $db;

    public function __construct(Database $db) { $this->db = $db; }

    // ============================================================
    //  Conversation + Messages
    // ============================================================

    /** Eine Sparring-Konversation je Plan — anlegen oder laden. */
    public function getOrCreateConversation(int $planId, int $userId): array
    {
        $conv = $this->db->queryOne("SELECT * FROM pp_sparring_conversations WHERE plan_id = ?", [$planId]);
        if ($conv) return $conv;
        $cid = $this->db->queryValue("SELECT customer_id FROM pp_plans WHERE id = ?", [$planId]);
        $id = (int) $this->db->insert('pp_sparring_conversations', [
            'plan_id' => $planId,
            'customer_id' => $cid ?: null,
            'created_by' => $userId ?: null,
        ]);
        return $this->db->queryOne("SELECT * FROM pp_sparring_conversations WHERE id = ?", [$id]);
    }

    public function listMessages(int $convId): array
    {
        return $this->db->query(
            "SELECT id, role, content, model, tokens_in, tokens_out, created_at
             FROM pp_sparring_messages WHERE conversation_id = ? ORDER BY id ASC",
            [$convId]
        ) ?: [];
    }

    public function addMessage(int $convId, string $role, string $content, ?string $model = null, ?int $tin = null, ?int $tout = null): int
    {
        $id = (int) $this->db->insert('pp_sparring_messages', [
            'conversation_id' => $convId,
            'role' => in_array($role, ['user', 'assistant'], true) ? $role : 'user',
            'content' => $content,
            'model' => $model,
            'tokens_in' => $tin,
            'tokens_out' => $tout,
        ]);
        $this->db->execute("UPDATE pp_sparring_conversations SET updated_at = NOW() WHERE id = ?", [$convId]);
        return $id;
    }

    // ============================================================
    //  System-Prompt (Plan-Stand + Kontext)
    // ============================================================

    public function buildSystemPrompt(array $plan): string
    {
        $customerId = (int) ($plan['customer_id'] ?? 0);
        $profile = $this->customerProfile($customerId);
        $planTxt = $this->planContext($plan);

        // Taxonomie (typische Sektionen/Items dieses Kunden)
        $taxTxt = '';
        try {
            $tax = (new PpTaxonomyService($this->db))->getForCustomer($customerId);
            $lines = [];
            foreach (array_slice($tax, 0, 20) as $t) {
                $typical = '';
                if (!empty($t['typical_items'])) {
                    $its = json_decode($t['typical_items'], true);
                    if (is_array($its)) $typical = ': ' . implode(' · ', array_map(fn($x) => '„' . mb_substr($x, 0, 50) . '"', array_slice(array_keys($its), 0, 3)));
                }
                $lines[] = '- ' . ($t['display_name'] ?? '') . $typical;
            }
            if ($lines) $taxTxt = "TYPISCHE SEKTIONEN/SCHRITTE DIESES KUNDEN:\n" . implode("\n", $lines) . "\n\n";
        } catch (\Throwable $_) {}

        // Projektplanner-Regeln
        $rulesTxt = '';
        try {
            require_once __DIR__ . '/PpAiRulesService.php';
            $r = (new PpAiRulesService($this->db))->activeRulesText($customerId);
            if ($r !== '') $rulesTxt = "GELERNTE REGELN FÜR DIESEN KUNDEN (unbedingt befolgen):\n" . $r . "\n\n";
        } catch (\Throwable $_) {}

        return "Du bist ein erfahrener Projekt- und Planungs-Sparringspartner der Thoxan Communications GmbH.\n"
            . "Du hilfst, den folgenden Projektplan Schritt für Schritt zu schärfen: Was steht wirklich an, was fällt weg, "
            . "wo braucht es Puffer, welche Reihenfolge macht Sinn. Du denkst mit, hinterfragst, machst konkrete Vorschläge — "
            . "aber Du entscheidest nicht allein, es ist ein Dialog.\n\n"
            . "REGELN:\n"
            . "- Beziehe Dich konkret auf den aktuellen Plan-Stand unten. Erfinde keine Fakten.\n"
            . "- Nutze die Impulse aus anderen Projekten nur als Anregung, kein Muss — und sag, woher eine Idee kommt.\n"
            . "- Kurze, konkrete Antworten. Wenn Du Schritte vorschlägst, formuliere sie so, dass sie direkt in den Plan könnten (Beschreibung, grober Aufwand, Verantwortlich).\n"
            . "- Höflichkeitsformen Du/Dich/Dir groß. Keine Gedankenstriche.\n\n"
            . "AKTUELLER PLAN-STAND:\n" . $planTxt . "\n\n"
            . "STECKBRIEF:\n" . $profile . "\n\n"
            . $taxTxt
            . $rulesTxt;
    }

    private function planContext(array $plan): string
    {
        $head = ($plan['title'] ?? 'Plan') . ' · Zeitraum ' . ($plan['period_from'] ?? '?') . ' bis ' . ($plan['period_to'] ?? '?')
            . ' · Status ' . ($plan['plan_status'] ?? '?');
        $lines = [$head];
        foreach (($plan['rows'] ?? []) as $r) {
            if (($r['row_type'] ?? '') === 'section') { $lines[] = "\n## " . trim((string) $r['description']); continue; }
            if (($r['row_type'] ?? '') !== 'item') continue;
            $meta = [];
            if (!empty($r['planned_hours'])) $meta[] = $r['planned_hours'] . ' h';
            if (!empty($r['lead_responsible'])) $meta[] = 'Lead ' . $r['lead_responsible'];
            if (!empty($r['timeframe'])) $meta[] = $r['timeframe'];
            $lines[] = '- [#' . $r['id'] . '] ' . trim(str_replace("\n", ' ', (string) $r['description'])) . ($meta ? ' (' . implode(', ', $meta) . ')' : '');
        }
        return implode("\n", $lines);
    }

    private function customerProfile(int $customerId): string
    {
        $c = $this->db->queryOne(
            'SELECT name, abbreviation, website, industry, description, target_audience,
                    unique_selling_points, tone_of_voice, products_services, brand_values
             FROM customers WHERE id = ?',
            [$customerId]
        );
        if (!$c) return '';
        $parts = ['Kunde: ' . trim($c['name'] ?? '') . ' (' . trim($c['abbreviation'] ?? '?') . ')'];
        foreach ([['industry', 'Branche'], ['description', 'Beschreibung'], ['target_audience', 'Zielgruppe'], ['products_services', 'Leistungen'], ['tone_of_voice', 'Tonalität']] as [$k, $l]) {
            if (!empty($c[$k])) $parts[] = $l . ': ' . $c[$k];
        }
        return implode("\n", $parts);
    }

    // ============================================================
    //  Impulse aus anderen Projekten (RAG, kundenübergreifend)
    // ============================================================

    /** @return array{block:string, sources:array} — leerer Block bei nicht erreichbarem RAG. */
    public function retrieveImpulses(string $query, ?int $currentDocId, ?array $allowedCustomerIds): array
    {
        $empty = ['block' => '', 'sources' => []];
        $localKey = (string) Settings::get('local_api_key');
        if ($localKey === '' || trim($query) === '') return $empty;
        try {
            $dim = (int) (Settings::get('embedding_local_dim') ?: 1024) ?: 1024;
            $qdrant = new QdrantClient(
                (string) (Settings::get('qdrant_url') ?: 'http://localhost:6333'),
                (string) Settings::get('qdrant_api_key'),
                'knowledge_bge_m3',
                $dim
            );
            $embedder = new LocalEmbeddingService(
                (string) (Settings::get('embedding_local_url') ?: 'https://ki.thoxan.com/embeddings/embeddings'),
                $localKey,
                (string) (Settings::get('embedding_local_model') ?: 'bge-m3'),
                $dim
            );
            $retr = new KnowledgeRetrievalServiceV2($this->db, $qdrant, $embedder);
            // Kundenübergreifend: customerId = null, aber innerhalb der erlaubten Kunden.
            $chunks = $retr->retrieve($query, null, 24, $allowedCustomerIds);
        } catch (\Throwable $e) {
            return $empty; // RAG-Ausfall darf das Sparring nicht sprengen
        }
        $picked = [];
        $sources = [];
        $seenDoc = [];
        foreach ($chunks as $ch) {
            if (($ch['source_type'] ?? '') !== 'projektplan') continue;          // nur Pläne
            if ($currentDocId && (int) ($ch['document_id'] ?? 0) === $currentDocId) continue; // aktuellen Plan ausschließen
            $doc = (int) ($ch['document_id'] ?? 0);
            $picked[] = "### " . ($ch['title'] ?? 'Plan') . "\n" . trim((string) ($ch['content'] ?? ''));
            if (!isset($seenDoc[$doc])) { $seenDoc[$doc] = true; $sources[] = ['title' => $ch['title'] ?? 'Plan', 'document_id' => $doc]; }
            if (count($picked) >= self::IMPULSE_K) break;
        }
        if (!$picked) return $empty;
        $block = "IMPULSE AUS ANDEREN PROJEKTEN (nur zur Anregung, kein Muss — Herkunft nennen):\n"
            . implode("\n\n", $picked) . "\n";
        return ['block' => $block, 'sources' => $sources];
    }

    // ============================================================
    //  Materialisieren: Gespräch → strukturierte Vorschläge
    // ============================================================

    public function materializeSuggestions(int $convId, array $plan): array
    {
        $key = (string) Settings::get('anthropic_api_key');
        if ($key === '') throw new \RuntimeException('Anthropic API-Key nicht konfiguriert.');
        $msgs = $this->listMessages($convId);
        if (!$msgs) return ['suggestions' => [], 'rationale' => 'Noch kein Gespräch.'];

        $messages = [];
        foreach ($msgs as $m) {
            $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']];
        }
        // Anthropic braucht abschließend eine user-Message.
        $messages[] = ['role' => 'user', 'content' =>
            "Fasse jetzt die in unserem Gespräch VEREINBARTEN konkreten Plan-Änderungen als Vorschläge zusammen — "
            . "nichts erfinden, was wir nicht besprochen haben.\n"
            . "WICHTIG: Der aktuelle Plan-Stand steht oben im Kontext (Zeilen mit [#id]). Schlage NICHTS vor, was inhaltlich schon im Plan steht — "
            . "keine Doppelungen bereits vorhandener/aktiver Schritte. Nur wirklich NEUE Schritte (add) oder gezielte Entfernungen (remove). "
            . "Gibt es nichts Neues und nichts zu entfernen, gib eine leere Liste zurück.\n"
            . "- Neue Schritte: action='add' mit Sektion + Beschreibung.\n"
            . "- Bestehende Schritte, die raus sollen: action='remove' mit der row_id aus den [#id]-Markierungen des Plan-Stands "
            . "(schreibe NICHT „ENTFERNEN“ in eine neue Zeile — nutze action='remove').\n"
            . "Rufe das Tool 'plan_vorschlaege' auf."];

        $tool = [
            'name' => 'plan_vorschlaege',
            'description' => 'Die im Sparring vereinbarten Plan-Änderungen als Liste: neue Schritte (add) oder zu entfernende bestehende Zeilen (remove).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'suggestions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'action'        => ['type' => 'string', 'enum' => ['add', 'remove'], 'description' => "'add' = neue Zeile anlegen, 'remove' = eine BESTEHENDE Zeile entfernen. Standard 'add'."],
                                'row_id'        => ['type' => 'integer', 'description' => "Bei action='remove': die exakte row_id der zu entfernenden Zeile aus den [#id]-Markierungen des Plan-Stands. Niemals erfinden."],
                                'section'       => ['type' => 'string', 'description' => "Bei action='add': Ziel-Sektion (bestehend oder neu), z.B. „Linkaufbau, Online-PR“."],
                                'description'   => ['type' => 'string', 'description' => "Bei 'add': ausführliche Schritt-Beschreibung wie eine Plan-Zeile. Bei 'remove': kurz WAS entfernt wird und WARUM."],
                                'timeframe'     => ['type' => 'string'],
                                'deadline'      => ['type' => 'string'],
                                'lead'          => ['type' => 'string'],
                                'team'          => ['type' => 'string'],
                                'planned_hours' => ['type' => 'number'],
                            ],
                            'required' => ['description'],
                        ],
                    ],
                    'rationale' => ['type' => 'string'],
                ],
                'required' => ['suggestions'],
            ],
        ];

        $payload = [
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $this->buildSystemPrompt($plan),
            'tools' => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => 'plan_vorschlaege'],
            'messages' => $messages,
        ];
        $resp = $this->callAnthropic($key, $payload);
        $input = $this->extractToolInput($resp, 'plan_vorschlaege');
        return [
            'suggestions' => array_values($input['suggestions'] ?? []),
            'rationale' => (string) ($input['rationale'] ?? ''),
        ];
    }

    // ============================================================
    //  Anthropic (aus PpPlanGeneratorService gespiegelt)
    // ============================================================

    private function callAnthropic(string $apiKey, array $payload): array
    {
        $ch = curl_init(self::ANTHROPIC_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'],
            CURLOPT_TIMEOUT => self::TIMEOUT_S,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException('Anthropic curl-Fehler: ' . $err);
        $json = json_decode($body, true);
        if (!is_array($json)) throw new \RuntimeException('Anthropic: kein gueltiges JSON: ' . substr((string) $body, 0, 300));
        if ($code !== 200 || isset($json['error'])) throw new \RuntimeException('Anthropic-Fehler: ' . ($json['error']['message'] ?? ('HTTP ' . $code)));
        return $json;
    }

    private function extractToolInput(array $response, string $toolName): array
    {
        foreach (($response['content'] ?? []) as $b) {
            if (($b['type'] ?? '') === 'tool_use' && ($b['name'] ?? '') === $toolName) return $b['input'] ?? [];
        }
        throw new \RuntimeException('Claude hat das Tool nicht aufgerufen.');
    }
}
