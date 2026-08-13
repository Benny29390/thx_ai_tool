<?php

namespace Services;

use Core\Database;

require_once SERVICES_PATH . '/AIService.php';
require_once SERVICES_PATH . '/KnowledgeRetrievalHybridTrait.php';
require_once SERVICES_PATH . '/KnowledgeRetrievalServiceV2.php';
require_once SERVICES_PATH . '/QdrantClient.php';
require_once SERVICES_PATH . '/LocalEmbeddingService.php';
require_once SERVICES_PATH . '/UserDirectoryMatcher.php';

/**
 * Stueck-fuer-Stueck-Befuellung von Steckbrief-Karten aus verteiltem KB-Material.
 *
 * Idee: jeder Karten-Typ hat einen Fragen-Katalog ("Slot-Fragen"). Pro Frage
 * holen wir Top-N Chunks aus der Wissensbasis des Kunden und bitten das LLM
 * um eine strukturierte Antwort, die direkt in die Karte passt. Antworten
 * landen als persistente Vorschlaege in `customer_card_suggestions`, der User
 * kann sie annehmen, ablehnen oder bearbeiten.
 */
class SteckbriefSuggestionService
{
    private Database $db;
    private string $openaiKey;
    /** @var array Settings für lokales bge-m3 + Qdrant (V2-Retrieval) */
    private array $settings;

    /**
     * @param Database $db
     * @param string $openaiKey OpenAI-API-Key — wird nur noch für den LLM-Call (Vorschlags-Generierung) genutzt
     * @param array $settings Entschlüsselte Settings (qdrant_url, qdrant_api_key, embedding_local_url, embedding_local_model, embedding_local_dim, local_api_key)
     */
    public function __construct(Database $db, string $openaiKey, array $settings = [])
    {
        $this->db = $db;
        $this->openaiKey = $openaiKey;
        $this->settings = $settings;
    }

    /**
     * Fragen-Katalog je Karten-Typ. JSON-Schema haengt am Typ.
     */
    private function slotQuestions(string $type): array
    {
        return match ($type) {
            'links' => [
                'Welche Tool-URLs werden für diesen Kunden genannt? (Looker Studio, Asana, Google Ads, Meta Business Manager, Google Analytics, Search Console, CMS-Backend, Hoster, Newsletter-Tool, CRM, Cloud-Speicher)',
                'Welche internen Dashboards, Reportings oder geteilten Dokumente sind verlinkt?',
            ],
            'kpi' => [
                'Welche monatlichen oder jaehrlichen Budgets / Werbespendings werden genannt? Welche Kanaele bekommen welches Geld?',
                'Welche KPIs werden genannt (CPL, CPA, CPM, ROAS, Conversion-Rate)? Welche Zielwerte gibt es?',
                'Welche operativen Ziele werden genannt (Leads pro Monat, Katalogbestellungen, Termine, Reichweite)?',
            ],
            'tracking_status' => [
                'Welche Tracking-Komponenten sind installiert oder fehlen? (CMP/Usercentrics, Google Tag Manager, GA4-Property-ID, Google Ads Conversions, Enhanced Conversions, Server-Side Tagging, Meta CAPI, Pixel, Microsoft Clarity, Hotjar)',
                'Werden Tracking-Luecken erwaehnt (was fehlt noch, was funktioniert nicht)?',
            ],
            'contacts' => [
                'Welche Personen werden als Ansprechpartner genannt? Mit welcher Rolle / Aufgabe / E-Mail / Telefon?',
                'Welche internen Teammitglieder (Thoxan-Seite) arbeiten an diesem Kunden? Welche Aufgaben haben sie?',
            ],
            'brand' => [
                'Welche Markenfarben werden genannt (Hex-Codes, Farb-Namen)?',
                'Welche Schriftarten / Fonts werden eingesetzt?',
                'Gibt es Vorgaben zu Bildsprache oder visuellen Leitplanken?',
            ],
            'richtext' => [
                'Welche strategischen Leitplanken, Tonalitaets-Vorgaben oder kommunikativen Regeln werden genannt?',
                'Gibt es Hinweise zu Zielgruppen, USPs, Produkt-Differenzierung, Marktposition?',
                'Welche wichtigen Hintergrund-Infos zu Geschaeftsmodell, Historie, Vertragsstatus werden erwaehnt?',
            ],
            default => [],
        };
    }

    /**
     * Pro Karten-Typ: JSON-Schema fuer die LLM-Antwort (welche Felder erwartet).
     */
    private function answerSchema(string $type): string
    {
        return match ($type) {
            'links' => '{"items":[{"title":"Looker Studio","url":"https://lookerstudio.google.com/..."}]}',
            'kpi' => '{"items":[{"label":"Meta-Ads Budget","value":"3.000 EUR","target":"CPL <= 10 EUR","period":"Monat"}]}',
            'tracking_status' => '{"items":[{"label":"GA4 Property","status":"ok","note":"312872182"}]}  // status: ok|fehlt|tbd|na',
            'contacts' => '{"groups":[{"title":"Intern","people":[{"role":"Projektleitung","name":"Thomas Kilian","email":"...","phone":"","initials":"TK"}]}]}',
            'brand' => '{"colors":[{"name":"Primaer","value":"#004C9B"}],"fonts":[{"name":"Frutiger","note":"Headlines"}]}',
            'richtext' => '{"html":"<p>...</p><ul><li>...</li></ul>"}',
            default => '{}',
        };
    }

    /**
     * Erzeugt Vorschlaege fuer EINE Karte des Kunden, basierend auf KB-Material.
     * Existierende `pending` Vorschlaege fuer diese Karte werden vorher entfernt.
     */
    public function suggestForCard(int $cardId, int $userId): array
    {
        $card = $this->db->queryOne("SELECT * FROM customer_cards WHERE id = ?", [$cardId]);
        if (!$card) throw new \RuntimeException('Karte nicht gefunden');
        if (!empty($card['is_system'])) throw new \RuntimeException('System-Karten koennen nicht autobefuellt werden');

        $type = $card['type'];
        $customerId = (int) $card['customer_id'];
        $questions = $this->slotQuestions($type);
        if (empty($questions)) {
            throw new \RuntimeException('Fuer diesen Karten-Typ gibt es keinen Fragen-Katalog');
        }

        // Pending Vorschlaege fuer DIESE Karte loeschen (supersedet)
        $this->db->execute(
            "UPDATE customer_card_suggestions SET status='superseded'
             WHERE card_id = ? AND status = 'pending'",
            [$cardId]
        );

        $retrieval = $this->makeRetrieval();
        // Kunden-Steckbriefe sind ein geteiltes Artefakt: Sie werden von einem Nutzer erzeugt,
        // aber von allen gelesen. Deshalb hier bewusst OHNE Betrachter arbeiten — private
        // Dokumente (z.B. das Mail-Postfach) fliessen nie in einen Steckbrief ein, auch nicht,
        // wenn ihr Besitzer die Vorschlaege anstoesst.
        $retrieval->setViewer(null);
        $aiService = new AIService($this->openaiKey, 'openai');
        $aiService->setModel('gpt-4o-mini');
        $aiService->setMaxTokens(2500);

        $allChunks = [];
        foreach ($questions as $q) {
            $chunks = $retrieval->retrieve($q, $customerId, 6);
            foreach ($chunks as $c) {
                $key = $c['document_id'] . ':' . $c['id'];
                $allChunks[$key] = $c;
            }
        }
        if (empty($allChunks)) return ['created' => 0, 'note' => 'Keine Belege in der Wissensbasis gefunden'];

        // Sortiert nach Score absteigend, kappen auf 12
        $sorted = array_values($allChunks);
        usort($sorted, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $sorted = array_slice($sorted, 0, 12);

        $context = '';
        $docIds = [];
        foreach ($sorted as $i => $c) {
            $excerpt = mb_substr((string) ($c['content'] ?? ''), 0, 600);
            $context .= "[BELEG " . ($i + 1) . " | Quelle: " . ($c['title'] ?? 'unbenannt') . " | " . ($c['source_type'] ?? '') . "]\n" . $excerpt . "\n\n";
            $docIds[] = (int) $c['document_id'];
        }
        $docIds = array_values(array_unique($docIds));

        // Bestehender Karten-Inhalt (zum Vergleich / als Kontext)
        $existing = json_decode((string) ($card['body'] ?? ''), true) ?: [];

        $existingJson = json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $schema = $this->answerSchema($type);

        // Audience nur fuer contacts-Karten relevant: intern (Thoxan-Team) vs extern (Kundenseite)
        $matcher = new UserDirectoryMatcher($this->db);
        $audience = $type === 'contacts' ? $matcher->cardAudience((string) ($card['title'] ?? '')) : 'unknown';

        $audienceBlock = '';
        if ($type === 'contacts') {
            $roster = $matcher->rosterForPrompt();
            if ($audience === 'external') {
                $audienceBlock = "\n\nWICHTIG - DIESE KARTE IST FUER KUNDEN-ANSPRECHPARTNER:\n"
                               . "Diese Karte sammelt NUR Personen auf der KUNDENSEITE.\n"
                               . "Die folgenden Personen sind THOXAN-MITARBEITENDE und gehoeren NICHT in diese Karte:\n"
                               . $roster
                               . "\nFiltere alle Thoxan-Mitarbeitenden raus. Schlage NUR externe Personen vor (Kundenseite, Dienstleister, Partner).";
            } elseif ($audience === 'internal') {
                $audienceBlock = "\n\nWICHTIG - DIESE KARTE IST FUER DAS THOXAN-TEAM:\n"
                               . "Diese Karte sammelt NUR Thoxan-Mitarbeitende, die an dem Kunden arbeiten.\n"
                               . "Verfuegbare Thoxan-Mitarbeitende:\n"
                               . $roster
                               . "\nSchlage NUR Personen aus dieser Liste vor (mit ihrer Rolle/Aufgabe fuer diesen Kunden).";
            }
        }

        $system = "Du extrahierst strukturierte Fakten fuer einen Kunden-Steckbrief aus Belegen "
                . "der internen Wissensbasis (E-Mails, Asana-Tasks, Transkripte, Websites, Dokumente).\n\n"
                . "Karten-Typ: {$type}\n"
                . "Karten-Titel: " . ($card['title'] ?? '') . "\n"
                . "Schema fuer die Antwort: {$schema}\n"
                . "Bestehender Karten-Inhalt: {$existingJson}"
                . $audienceBlock
                . "\n\nRegeln:\n"
                . "- Nutze ausschliesslich Fakten aus den Belegen. Erfinde NICHTS.\n"
                . "- Wenn ein Fakt bereits im bestehenden Karten-Inhalt vorhanden ist, NICHT erneut vorschlagen.\n"
                . "- Schreibe alle Klartexte mit Du/Dich/Dir Gross.\n"
                . "- KEINE Gedankenstriche (em-dash), stattdessen normales ' - ' oder Komma.\n"
                . "- Anglizismen vermeiden ('Maßnahme' statt 'Campaign', 'Vorgang' statt 'Process').\n"
                . "- Schreibe jeden Vorschlag als atomare Einheit (z.B. EIN Link, EINE Kennzahl, EINE Person).\n"
                . "- Wenn KEINE neuen Fakten gefunden: leere items-Liste zurueckgeben.\n"
                . ($type === 'contacts'
                    ? "- NIEMALS Initialen, E-Mail-Adressen oder Telefonnummern halluzinieren. "
                        . "Diese Felder NUR ausfuellen, wenn sie WOERTLICH im Beleg stehen. "
                        . "Lass sie sonst leer ('') - das System fuellt sie spaeter aus den Stammdaten.\n"
                    : '')
                . "\nAntwort: NUR ein JSON-Objekt nach dem Schema oben. Keine Erklaerung, kein Markdown.";

        $user = "Belege:\n\n" . $context;

        $resp = $aiService->chat([['role' => 'user', 'content' => $user]], $system);
        $content = trim((string) ($resp['content'] ?? ''));
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content);
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('LLM-Antwort war kein gueltiges JSON');
        }

        // In atomare Vorschlaege zerlegen (Audience-Filter im contacts-Fall mitgeben)
        $atoms = $this->atomize($type, $decoded, ['audience' => $audience]);

        // Bei contacts: Atoms wegfiltern, die bereits in der Karte sind
        if ($type === 'contacts') {
            $existingGroups = $existing['groups'] ?? [];
            $atoms = array_values(array_filter($atoms, function ($atom) use ($existingGroups) {
                $p = $atom['groups'][0]['people'][0] ?? null;
                if (!$p) return false;
                [$gi, $pi, $found] = $this->findExistingPerson($existingGroups, $p);
                return $found === null;
            }));
        }

        $created = 0;
        foreach ($atoms as $atom) {
            $payload = json_encode($atom, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $snippet = $this->atomSnippet($type, $atom);
            $this->db->insert('customer_card_suggestions', [
                'customer_id' => $customerId,
                'card_id' => $cardId,
                'slot_key' => $type,
                'payload' => $payload,
                'snippet' => mb_substr($snippet, 0, 500),
                'confidence' => 0.7,
                'source_doc_ids' => implode(',', $docIds),
                'status' => 'pending',
                'created_by' => $userId,
            ]);
            $created++;
        }

        return ['created' => $created];
    }

    /**
     * Erzeugt Vorschlaege fuer alle nicht-System-Karten eines Kunden. Karten,
     * fuer deren Typ kein Fragen-Katalog existiert, werden uebersprungen.
     */
    public function suggestForAllCards(int $customerId, int $userId): array
    {
        $cards = $this->db->query(
            "SELECT id, type FROM customer_cards
             WHERE customer_id = ? AND (is_system = 0 OR is_system IS NULL)",
            [$customerId]
        ) ?: [];

        $supported = ['links','kpi','tracking_status','contacts','brand','richtext'];
        $totals = ['cards_processed' => 0, 'cards_skipped' => 0, 'suggestions_created' => 0];

        foreach ($cards as $c) {
            if (!in_array($c['type'], $supported, true)) {
                $totals['cards_skipped']++;
                continue;
            }
            try {
                $res = $this->suggestForCard((int) $c['id'], $userId);
                $totals['cards_processed']++;
                $totals['suggestions_created'] += (int) ($res['created'] ?? 0);
            } catch (\Throwable $e) {
                error_log('suggestForCard ' . $c['id'] . ' failed: ' . $e->getMessage());
                $totals['cards_skipped']++;
            }
        }
        return $totals;
    }

    public function listForCard(int $cardId): array
    {
        $rows = $this->db->query(
            "SELECT s.*, GROUP_CONCAT(DISTINCT d.title SEPARATOR '|') AS doc_titles
             FROM customer_card_suggestions s
             LEFT JOIN knowledge_documents d
                ON FIND_IN_SET(d.id, s.source_doc_ids) > 0
             WHERE s.card_id = ? AND s.status = 'pending'
             GROUP BY s.id
             ORDER BY s.id DESC",
            [$cardId]
        ) ?: [];
        foreach ($rows as &$r) {
            $r['payload_decoded'] = $r['payload'] ? (json_decode($r['payload'], true) ?: []) : [];
            $r['source_docs'] = !empty($r['doc_titles']) ? array_values(array_filter(explode('|', $r['doc_titles']))) : [];
            unset($r['doc_titles']);
        }
        return $rows;
    }

    public function listForCustomer(int $customerId): array
    {
        $rows = $this->db->query(
            "SELECT s.*, c.title AS card_title, c.type AS card_type
             FROM customer_card_suggestions s
             JOIN customer_cards c ON c.id = s.card_id
             WHERE s.customer_id = ? AND s.status = 'pending'
             ORDER BY s.card_id, s.id DESC",
            [$customerId]
        ) ?: [];
        foreach ($rows as &$r) {
            $r['payload_decoded'] = $r['payload'] ? (json_decode($r['payload'], true) ?: []) : [];
        }
        return $rows;
    }

    /**
     * Vorschlag annehmen: payload wird in die Karte gemerged.
     */
    public function accept(int $suggestionId, int $userId, CustomerCardService $cardService): array
    {
        $s = $this->db->queryOne("SELECT * FROM customer_card_suggestions WHERE id = ?", [$suggestionId]);
        if (!$s) throw new \RuntimeException('Vorschlag nicht gefunden');
        if ($s['status'] !== 'pending') throw new \RuntimeException('Vorschlag bereits entschieden');

        $card = $this->db->queryOne("SELECT * FROM customer_cards WHERE id = ?", [(int) $s['card_id']]);
        if (!$card) throw new \RuntimeException('Karte nicht gefunden');

        $existing = json_decode((string) ($card['body'] ?? ''), true) ?: [];
        $atom = json_decode((string) ($s['payload'] ?? ''), true) ?: [];

        $merged = $this->mergeAtom($card['type'], $existing, $atom);

        $cardService->update((int) $card['id'], ['body' => $merged], $userId);

        $this->db->update('customer_card_suggestions', [
            'status' => 'accepted',
            'decided_by' => $userId,
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$suggestionId]);

        return ['card_id' => (int) $card['id']];
    }

    public function reject(int $suggestionId, int $userId): void
    {
        $this->db->update('customer_card_suggestions', [
            'status' => 'rejected',
            'decided_by' => $userId,
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$suggestionId]);
    }

    // ----- intern -----

    private function makeRetrieval(): KnowledgeRetrievalServiceV2
    {
        $localKey = (string) ($this->settings['local_api_key'] ?? '');
        if ($localKey === '') {
            throw new \RuntimeException('Lokaler Embedding-API-Key (local_api_key) ist nicht konfiguriert');
        }
        $dim = (int)($this->settings['embedding_local_dim'] ?? 1024) ?: 1024;
        $qdrant = new QdrantClient(
            $this->settings['qdrant_url'] ?? 'http://localhost:6333',
            $this->settings['qdrant_api_key'] ?? '',
            'knowledge_bge_m3',
            $dim
        );
        $emb = new LocalEmbeddingService(
            $this->settings['embedding_local_url'] ?? 'https://ki.thoxan.com/embeddings/embeddings',
            $localKey,
            $this->settings['embedding_local_model'] ?? 'bge-m3',
            $dim
        );
        return new KnowledgeRetrievalServiceV2($this->db, $qdrant, $emb);
    }

    /**
     * Sucht eine Person in den existierenden Gruppen.
     * Match-Reihenfolge: user_id, E-Mail (case-insensitive), normalisierter Name.
     * Liefert [groupIndex, personIndex, person] oder [null, null, null].
     */
    private function findExistingPerson(array $groups, array $needle): array
    {
        $needleUserId = (int) ($needle['user_id'] ?? 0);
        $needleEmail = mb_strtolower(trim((string) ($needle['email'] ?? '')));
        $needleName = $this->normalizeNameSimple((string) ($needle['name'] ?? ''));

        foreach ($groups as $gi => $g) {
            foreach (($g['people'] ?? []) as $pi => $p) {
                $pUserId = (int) ($p['user_id'] ?? 0);
                if ($needleUserId > 0 && $pUserId === $needleUserId) return [$gi, $pi, $p];
                $pEmail = mb_strtolower(trim((string) ($p['email'] ?? '')));
                if ($needleEmail !== '' && $pEmail === $needleEmail) return [$gi, $pi, $p];
                $pName = $this->normalizeNameSimple((string) ($p['name'] ?? ''));
                if ($needleName !== '' && $pName === $needleName) return [$gi, $pi, $p];
            }
        }
        return [null, null, null];
    }

    /**
     * Reichert eine bestehende Person mit Werten aus dem Vorschlag an.
     * Bestehende nicht-leere Werte werden NICHT ueberschrieben (Daten-Schutz).
     */
    private function mergePerson(array $existing, array $new): array
    {
        $fields = ['name', 'role', 'initials', 'email', 'phone', 'note', 'user_id'];
        foreach ($fields as $f) {
            $cur = trim((string) ($existing[$f] ?? ''));
            $val = trim((string) ($new[$f] ?? ''));
            if ($cur === '' && $val !== '') $existing[$f] = $new[$f];
        }
        return $existing;
    }

    private function normalizeNameSimple(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
        $s = preg_replace('/[^a-z0-9 ]+/', '', $s);
        return preg_replace('/\s+/', ' ', (string) $s);
    }

    /**
     * Zerlegt die LLM-Antwort in atomare Vorschlaege (ein Link, eine Person,
     * eine Kennzahl, ein Tracking-Punkt, eine Farbe, ein Font, ein richtext-Block).
     */
    private function atomize(string $type, array $decoded, array $opts = []): array
    {
        switch ($type) {
            case 'links':
                $atoms = [];
                foreach (($decoded['items'] ?? []) as $it) {
                    $url = trim((string) ($it['url'] ?? ''));
                    if ($url === '' || !preg_match('#^https?://#i', $url)) continue;
                    $atoms[] = ['items' => [$it]];
                }
                return $atoms;

            case 'kpi':
                $atoms = [];
                foreach (($decoded['items'] ?? []) as $it) {
                    if (empty($it['label']) && empty($it['value'])) continue;
                    $atoms[] = ['items' => [$it]];
                }
                return $atoms;

            case 'tracking_status':
                $atoms = [];
                foreach (($decoded['items'] ?? []) as $it) {
                    if (empty($it['label'])) continue;
                    $atoms[] = ['items' => [$it]];
                }
                return $atoms;

            case 'contacts':
                $atoms = [];
                $matcher = new UserDirectoryMatcher($this->db);
                $audience = (string) ($opts['audience'] ?? 'unknown');
                foreach (($decoded['groups'] ?? []) as $g) {
                    $groupTitle = $g['title'] ?? '';
                    $enriched = $matcher->enrichPeople($g['people'] ?? []);
                    // Audience-Filter: extern → keine Thoxan-User, intern → nur Thoxan-User
                    $enriched = $matcher->filterPeopleByAudience($enriched, $audience);
                    foreach ($enriched as $p) {
                        $atoms[] = ['groups' => [['title' => $groupTitle, 'people' => [$p]]]];
                    }
                }
                return $atoms;

            case 'brand':
                $atoms = [];
                foreach (($decoded['colors'] ?? []) as $c) {
                    if (empty($c['value']) && empty($c['name'])) continue;
                    $atoms[] = ['colors' => [$c]];
                }
                foreach (($decoded['fonts'] ?? []) as $f) {
                    if (empty($f['name'])) continue;
                    $atoms[] = ['fonts' => [$f]];
                }
                return $atoms;

            case 'richtext':
                $html = trim((string) ($decoded['html'] ?? ''));
                if ($html === '') return [];
                return [['html' => $html]];
        }
        return [];
    }

    private function atomSnippet(string $type, array $atom): string
    {
        switch ($type) {
            case 'links':
                $it = $atom['items'][0] ?? [];
                return trim(($it['title'] ?? '') . ' — ' . ($it['url'] ?? ''));
            case 'kpi':
                $it = $atom['items'][0] ?? [];
                return trim(($it['label'] ?? '') . ': ' . ($it['value'] ?? ''));
            case 'tracking_status':
                $it = $atom['items'][0] ?? [];
                return '[' . ($it['status'] ?? '') . '] ' . ($it['label'] ?? '');
            case 'contacts':
                $p = $atom['groups'][0]['people'][0] ?? [];
                return trim(($p['role'] ?? '') . ': ' . ($p['name'] ?? ''));
            case 'brand':
                if (!empty($atom['colors'])) return 'Farbe: ' . ($atom['colors'][0]['name'] ?? '') . ' (' . ($atom['colors'][0]['value'] ?? '') . ')';
                if (!empty($atom['fonts'])) return 'Schrift: ' . ($atom['fonts'][0]['name'] ?? '');
                return '';
            case 'richtext':
                return mb_substr(strip_tags($atom['html'] ?? ''), 0, 200);
        }
        return '';
    }

    /**
     * Merged einen Vorschlag-Atom in den bestehenden Karten-Body (additiv).
     */
    private function mergeAtom(string $type, array $existing, array $atom): array
    {
        switch ($type) {
            case 'links':
            case 'kpi':
            case 'tracking_status':
                $existing['items'] = $existing['items'] ?? [];
                foreach (($atom['items'] ?? []) as $it) {
                    $existing['items'][] = $it;
                }
                return $existing;

            case 'contacts':
                $existing['groups'] = $existing['groups'] ?? [];
                foreach (($atom['groups'] ?? []) as $newG) {
                    $newTitle = $newG['title'] ?? '';
                    foreach (($newG['people'] ?? []) as $newP) {
                        // Bestehende Person finden: erst user_id, dann E-Mail, dann normalisierter Name
                        [$gi, $pi, $existingP] = $this->findExistingPerson($existing['groups'], $newP);
                        if ($existingP !== null) {
                            // Person ist da -> Felder ergaenzen (nur leere/schwaechere Felder ersetzen)
                            $merged = $this->mergePerson($existingP, $newP);
                            $existing['groups'][$gi]['people'][$pi] = $merged;
                            continue;
                        }
                        // Neu: in die richtige Gruppe einsortieren, ggf. Gruppe anlegen
                        $targetGi = null;
                        foreach ($existing['groups'] as $idx => $g) {
                            if (($g['title'] ?? '') === $newTitle) { $targetGi = $idx; break; }
                        }
                        if ($targetGi === null) {
                            $existing['groups'][] = ['title' => $newTitle, 'people' => []];
                            $targetGi = count($existing['groups']) - 1;
                        }
                        $existing['groups'][$targetGi]['people'][] = $newP;
                    }
                }
                return $existing;

            case 'brand':
                $existing['colors'] = $existing['colors'] ?? [];
                $existing['fonts'] = $existing['fonts'] ?? [];
                foreach (($atom['colors'] ?? []) as $c) $existing['colors'][] = $c;
                foreach (($atom['fonts'] ?? []) as $f) $existing['fonts'][] = $f;
                return $existing;

            case 'richtext':
                $existing['html'] = trim(($existing['html'] ?? '') . ($atom['html'] ? "\n" . $atom['html'] : ''));
                return $existing;
        }
        return $existing;
    }
}
