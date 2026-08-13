<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * PpPlanGeneratorService — KI-gestuetzte Erzeugung eines Plan-Entwurfs.
 *
 * Bekommt: customer_id, period_from, period_to, optionales Briefing.
 * Liest:
 *   - Customer-Steckbrief (customers + customer_knowledge)
 *   - Section-Taxonomy fuer den Kunden (typische Sektionen)
 *   - Letzte synced Plaene (als Beispiele)
 * Ruft Claude mit Tool-Use (Tool 'create_plan_draft' erzwingt JSON-Struktur).
 * Schreibt Ergebnis in pp_plans (state=1, plan_status='entwurf') + pp_plan_rows.
 *
 * Sicherheits-Netz: neuer Plan ist immer Entwurf — Knowledge-Sync greift erst,
 * wenn der User ihn auf 'aktiv' setzt.
 */
class PpPlanGeneratorService
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-opus-4-7';
    private const MAX_TOKENS = 8000;
    private const TIMEOUT_S = 90;

    private Database $db;

    public function __construct(Database $db) { $this->db = $db; }

    /**
     * Erzeugt einen Plan-Entwurf via Claude.
     *
     * @return array { plan_id, sections, items, tokens_in, tokens_out }
     */
    public function generateDraft(int $customerId, ?string $periodFrom, ?string $periodTo, string $briefing, int $userId): array
    {
        $key = (string) Settings::get('anthropic_api_key');
        if ($key === '') {
            throw new \RuntimeException('Anthropic API-Key nicht konfiguriert (Einstellungen → Provider).');
        }
        if ($customerId <= 0) {
            throw new \RuntimeException('Kunde ist Pflicht.');
        }

        // ===== Kontext bauen =====
        $customer = $this->db->queryOne(
            'SELECT id, name, abbreviation, slug, website FROM customers WHERE id = ?',
            [$customerId]
        );
        if (!$customer) throw new \RuntimeException('Kunde nicht gefunden.');

        $steckbrief = $this->buildCustomerProfile($customerId);
        $taxonomy = (new PpTaxonomyService($this->db))->getForCustomer($customerId);
        $examplePlans = $this->buildExamplePlans($customerId, 5);

        // ===== Tool-Definition =====
        $tool = [
            'name' => 'create_plan_draft',
            'description' => 'Erzeugt einen strukturierten Projektplan-Entwurf mit Sektionen und Items.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => 'Kurzer Plan-Titel im Format "{KUERZEL} {Zeitraum}" — z.B. "FRY 2026-Q3" oder "BKK 2026-08+09".',
                    ],
                    'sections' => [
                        'type' => 'array',
                        'description' => 'Hauptbereiche des Plans. Nutze die typischen Sektionen aus der Taxonomy als Skelett, sofern sinnvoll.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'description' => ['type' => 'string', 'description' => 'Aufgaben-Beschreibung, koennen lange Stichpunkte sein.'],
                                            'timeframe'   => ['type' => 'string', 'description' => 'Optional: Zeitraum wie "01.-15.07." oder Kalenderwoche.'],
                                            'lead'        => ['type' => 'string', 'description' => 'Hauptverantwortlich (Kuerzel oder Name aus Team).'],
                                            'team'        => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Mitwirkende.'],
                                            'planned_hours' => ['type' => 'number', 'description' => 'Geplante Stunden. Nutze historische Mittelwerte als Anhalt.'],
                                            'is_placeholder' => ['type' => 'boolean', 'description' => 'true wenn nur Platzhalter ohne konkrete Festlegung.'],
                                            'notes' => ['type' => 'string', 'description' => 'Optional: Bemerkung, URL etc.'],
                                        ],
                                        'required' => ['description'],
                                    ],
                                ],
                            ],
                            'required' => ['name', 'items'],
                        ],
                    ],
                    'rationale' => [
                        'type' => 'string',
                        'description' => 'Kurze Begruendung, welche Muster aus den Beispielen uebernommen wurden und welche Anpassungen das Briefing erforderte.',
                    ],
                ],
                'required' => ['title', 'sections'],
            ],
        ];

        $systemPrompt = $this->buildSystemPrompt($customer, $periodFrom, $periodTo, $steckbrief, $taxonomy, $examplePlans);
        $userPrompt   = $this->buildUserPrompt($customer, $periodFrom, $periodTo, $briefing);

        // ===== Claude-Call =====
        $payload = [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $systemPrompt,
            'tools'      => [$tool],
            'tool_choice'=> ['type' => 'tool', 'name' => 'create_plan_draft'],
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        $resp = $this->callAnthropic($key, $payload);
        $toolInput = $this->extractToolInput($resp);

        // ===== In DB schreiben =====
        $planId = $this->writePlan($customerId, $periodFrom, $periodTo, $toolInput, $userId, $briefing);

        return [
            'plan_id'   => $planId,
            'sections'  => count($toolInput['sections'] ?? []),
            'items'     => $this->countItems($toolInput['sections'] ?? []),
            'tokens_in' => $resp['usage']['input_tokens'] ?? 0,
            'tokens_out'=> $resp['usage']['output_tokens'] ?? 0,
            'rationale' => $toolInput['rationale'] ?? '',
        ];
    }

    // ============================================================
    //  Kontext-Bausteine
    // ============================================================

    private function buildCustomerProfile(int $customerId): string
    {
        $c = $this->db->queryOne(
            'SELECT name, abbreviation, website, industry,
                    description, target_audience, unique_selling_points,
                    tone_of_voice, products_services, brand_values
             FROM customers WHERE id = ?',
            [$customerId]
        );
        if (!$c) return '';
        $parts = [];
        $parts[] = 'Kunde: ' . trim($c['name'] ?? '') . ' (' . trim($c['abbreviation'] ?? '?') . ')';
        if (!empty($c['industry']))               $parts[] = 'Branche: ' . $c['industry'];
        if (!empty($c['website']))                $parts[] = 'Website: ' . $c['website'];
        if (!empty($c['description']))            $parts[] = 'Beschreibung: ' . $c['description'];
        if (!empty($c['target_audience']))        $parts[] = 'Zielgruppe: ' . $c['target_audience'];
        if (!empty($c['unique_selling_points']))  $parts[] = 'USPs: ' . $c['unique_selling_points'];
        if (!empty($c['products_services']))      $parts[] = 'Leistungen: ' . $c['products_services'];
        if (!empty($c['tone_of_voice']))          $parts[] = 'Tonalitaet: ' . $c['tone_of_voice'];
        if (!empty($c['brand_values']))           $parts[] = 'Markenwerte: ' . $c['brand_values'];
        return implode("\n", $parts);
    }

    private function buildExamplePlans(int $customerId, int $limit): array
    {
        $plans = $this->db->query(
            "SELECT id, title, period_from, period_to, plan_status
             FROM pp_plans
             WHERE customer_id = ? AND state = 1 AND plan_status IN ('aktiv','abgeschlossen','reporting','einzelprojekt','archiviert')
             ORDER BY period_from DESC, id DESC
             LIMIT $limit",
            [$customerId]
        ) ?: [];
        $out = [];
        foreach ($plans as $p) {
            $rows = $this->db->query(
                'SELECT row_type, description, timeframe, planned_hours, lead_responsible, responsible, is_placeholder
                 FROM pp_plan_rows
                 WHERE plan_id = ? AND row_type IN ("section","item")
                 ORDER BY position ASC',
                [$p['id']]
            ) ?: [];
            $sections = [];
            $current = null;
            foreach ($rows as $r) {
                if ($r['row_type'] === 'section') {
                    $current = ['name' => $r['description'], 'items' => []];
                    $sections[] = &$current;
                    unset($current);
                    $current = &$sections[count($sections) - 1];
                } else {
                    if (!$current) continue;
                    if ((int) $r['is_placeholder']) continue;
                    $current['items'][] = [
                        'description'   => $r['description'],
                        'timeframe'     => $r['timeframe'],
                        'planned_hours' => (float) $r['planned_hours'],
                        'lead'          => $r['lead_responsible'],
                        'team'          => $r['responsible'],
                    ];
                }
            }
            unset($current);
            $out[] = [
                'title' => $p['title'],
                'period' => ($p['period_from'] && $p['period_to'])
                    ? date('d.m.Y', strtotime($p['period_from'])) . '–' . date('d.m.Y', strtotime($p['period_to']))
                    : '',
                'sections' => $sections,
            ];
        }
        return $out;
    }

    private function buildSystemPrompt(array $customer, ?string $from, ?string $to, string $profile, array $taxonomy, array $examples): string
    {
        $taxonomyTxt = '';
        if (!empty($taxonomy)) {
            $lines = [];
            foreach (array_slice($taxonomy, 0, 25) as $t) {
                $typical = '';
                if (!empty($t['typical_items'])) {
                    $items = json_decode($t['typical_items'], true);
                    if (is_array($items)) {
                        $top = array_slice(array_keys($items), 0, 3);
                        $typical = ' · typische Items: ' . implode(' · ', array_map(fn($x) => '„' . mb_substr($x, 0, 40) . '"', $top));
                    }
                }
                $lines[] = '- ' . $t['display_name']
                    . ' (in ' . $t['plan_count'] . ' Plaenen · ⌀ ' . $t['avg_items_per_plan'] . ' Items · ⌀ ' . $t['avg_planned_hours'] . ' h)'
                    . $typical;
            }
            $taxonomyTxt = "TYPISCHE SEKTIONEN FUER DIESEN KUNDEN (Section-Taxonomy):\n" . implode("\n", $lines) . "\n\n";
        }

        $examplesTxt = '';
        if (!empty($examples)) {
            $blocks = [];
            foreach ($examples as $ex) {
                $secBlocks = [];
                foreach ($ex['sections'] as $s) {
                    $itLines = array_map(function ($it) {
                        $h = $it['planned_hours'] > 0 ? number_format((float) $it['planned_hours'], 1, ',', '') . 'h' : '';
                        $lead = $it['lead'] ? ' [' . $it['lead'] . ']' : '';
                        return '    - ' . mb_substr($it['description'], 0, 120) . ($h ? ' (' . $h . ')' : '') . $lead;
                    }, $s['items']);
                    $secBlocks[] = '  ## ' . $s['name'] . "\n" . implode("\n", $itLines);
                }
                $blocks[] = "PLAN: {$ex['title']} ({$ex['period']})\n" . implode("\n\n", $secBlocks);
            }
            $examplesTxt = "BEISPIELE — die letzten Plaene dieses Kunden:\n\n" . implode("\n\n----\n\n", $blocks) . "\n\n";
        }

        $period = ($from && $to) ? date('d.m.Y', strtotime($from)) . ' – ' . date('d.m.Y', strtotime($to)) : 'noch offen';

        return "Du erzeugst einen Projektplan-Entwurf fuer die Thoxan Communications GmbH.\n"
            . "Ziel-Kunde: {$customer['name']} ({$customer['abbreviation']}). Geplanter Zeitraum: $period.\n\n"
            . "REGELN:\n"
            . "- Nutze die typischen Sektionen aus der Taxonomy als Skelett. Lass weg, was im Briefing nicht passt.\n"
            . "- Pro Sektion: typischerweise 3-10 Items, orientiere Dich an den Beispielen.\n"
            . "- Stundenangaben: nimm den ⌀-Wert pro Sektion und verteile sinnvoll auf die Items. Briefing kann das verschieben.\n"
            . "- Item-Beschreibungen: ausfuehrliche Stichpunkte wie in den Beispielen — nicht knapp einzeilig.\n"
            . "- Lead-Kuerzel/Team-Kuerzel nur uebernehmen, wenn klar erkennbar; sonst leer lassen.\n"
            . "- KEINE Erfindungen ausserhalb der Muster. Wenn das Briefing was Neues will, schreib es klar als neue Sektion.\n"
            . "- Hoefflichkeitsformen Du/Dich/Dir gross. Keine Gedankenstriche im Plan-Text.\n\n"
            . "STECKBRIEF:\n$profile\n\n"
            . $taxonomyTxt
            . $examplesTxt
            . "Rufe das Tool 'create_plan_draft' mit dem fertigen Vorschlag auf.";
    }

    private function buildUserPrompt(array $customer, ?string $from, ?string $to, string $briefing): string
    {
        $p = ($from && $to) ? date('d.m.Y', strtotime($from)) . ' – ' . date('d.m.Y', strtotime($to)) : 'Zeitraum noch offen';
        $brief = trim($briefing);
        return "Erstelle einen Plan-Entwurf fuer {$customer['name']}, Zeitraum: $p.\n\n"
            . ($brief !== '' ? "BRIEFING:\n$brief\n\n" : "Kein spezielles Briefing — bau einen Standard-Plan nach Muster der letzten Quartale.\n\n")
            . "Rufe das Tool jetzt auf.";
    }

    // ============================================================
    //  Anthropic-Call + Tool-Result-Parsing
    // ============================================================

    private function callAnthropic(string $apiKey, array $payload): array
    {
        $ch = curl_init(self::ANTHROPIC_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT_S,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException('Anthropic curl-Fehler: ' . $err);
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Anthropic: kein gueltiges JSON: ' . substr($body, 0, 300));
        }
        if ($code !== 200 || isset($json['error'])) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('Anthropic-Fehler: ' . $msg);
        }
        return $json;
    }

    private function extractToolInput(array $response): array
    {
        $blocks = $response['content'] ?? [];
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'tool_use' && ($b['name'] ?? '') === 'create_plan_draft') {
                return $b['input'] ?? [];
            }
        }
        throw new \RuntimeException('Claude hat das Tool nicht aufgerufen. Rohantwort: ' . substr(json_encode($response), 0, 400));
    }

    private function countItems(array $sections): int
    {
        $c = 0;
        foreach ($sections as $s) $c += count($s['items'] ?? []);
        return $c;
    }

    // ============================================================
    //  Plan schreiben
    // ============================================================

    private function writePlan(int $customerId, ?string $from, ?string $to, array $draft, int $userId, string $briefing): int
    {
        $title = trim((string) ($draft['title'] ?? ''));
        if ($title === '') {
            // Fallback-Titel zusammenbauen
            $abbr = $this->db->queryValue('SELECT abbreviation FROM customers WHERE id=?', [$customerId]);
            $title = ($abbr ?: 'PLAN') . ' ' . ($from ? date('Y-m', strtotime($from)) : 'neu');
        }
        $this->db->beginTransaction();
        try {
            $planId = (int) $this->db->insert('pp_plans', [
                'customer_id' => $customerId,
                'title'       => $title,
                'period_from' => $from ?: null,
                'period_to'   => $to ?: null,
                'plan_status' => 'entwurf',
                'state'       => 1,
                'created_by'  => $userId,
            ]);

            // Briefing als initiale Notiz-Zeile (damit der User weiss, worauf der Entwurf basiert)
            $pos = 0;
            if (trim($briefing) !== '') {
                $this->db->insert('pp_plan_rows', [
                    'plan_id'  => $planId,
                    'row_type' => 'note',
                    'description' => '',
                    'notes'    => 'KI-Briefing: ' . $briefing,
                    'position' => $pos++,
                ]);
            }
            if (!empty($draft['rationale'])) {
                $this->db->insert('pp_plan_rows', [
                    'plan_id'  => $planId,
                    'row_type' => 'note',
                    'description' => '',
                    'notes'    => 'KI-Begruendung: ' . $draft['rationale'],
                    'position' => $pos++,
                ]);
            }

            foreach (($draft['sections'] ?? []) as $s) {
                $this->db->insert('pp_plan_rows', [
                    'plan_id'  => $planId,
                    'row_type' => 'section',
                    'description' => trim((string) ($s['name'] ?? '')),
                    'position' => $pos++,
                ]);
                foreach (($s['items'] ?? []) as $it) {
                    $team = $it['team'] ?? [];
                    if (is_array($team)) $team = implode(', ', array_map('trim', $team));
                    $this->db->insert('pp_plan_rows', [
                        'plan_id'  => $planId,
                        'row_type' => 'item',
                        'description'   => trim((string) ($it['description'] ?? '')),
                        'timeframe'     => trim((string) ($it['timeframe'] ?? '')) ?: null,
                        'lead_responsible' => trim((string) ($it['lead'] ?? '')) ?: null,
                        'responsible'   => trim((string) $team) ?: null,
                        'planned_hours' => round(((float) ($it['planned_hours'] ?? 0)) * 4) / 4, // Viertelstunden-Takt
                        'is_placeholder'=> !empty($it['is_placeholder']) ? 1 : 0,
                        'notes'         => trim((string) ($it['notes'] ?? '')) ?: null,
                        'position'      => $pos++,
                    ]);
                }
            }
            $this->db->commit();
            return $planId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
