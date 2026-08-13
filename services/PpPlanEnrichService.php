<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * PpPlanEnrichService — KI-Anreicherung eines bereits DUPLIZIERTEN Plan-Entwurfs.
 *
 * Anders als PpPlanGeneratorService (der einen Plan NEU erzeugt) arbeitet dieser
 * Service auf den bestehenden Zeilen eines Entwurfs und passt sie an:
 *   - passende OFFENE Asana-Tickets finden und verknuepfen (asana_gid/url/name)
 *   - Ticket-Infos/URLs in die Beschreibungen einarbeiten
 *   - Formulierungen an die Kunden-Standard-Beschreibungen (Taxonomy) angleichen
 *   - Zeitraeume/Deadlines auf die neue Plan-Periode legen
 *   - unpassende uebernommene Schritte als „Zu klaeren" markieren (nicht loeschen)
 *
 * Grundstruktur (Sektionen, Reihenfolge, Stunden) bleibt erhalten — es ist ein
 * DUPLIKAT, kein Neu-Entwurf. Ergebnis bleibt IMMER Entwurf; jede beruehrte Zeile
 * wird mit review_flag=1 markiert und taucht so im „Pruefen"-Filter auf.
 *
 * Anthropic-Mechanik (callAnthropic/extractToolInput/buildCustomerProfile) ist
 * bewusst aus PpPlanGeneratorService gespiegelt — der Generator ist tragend und
 * geteilt, daher nicht umgebaut.
 */
class PpPlanEnrichService
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-opus-4-7';
    private const MAX_TOKENS = 16000; // genug für viele Zeilen — sonst wird der Tool-Aufruf abgeschnitten (leeres rows-Array)
    private const TIMEOUT_S = 120;
    private const MAX_TICKETS = 40;

    private Database $db;

    public function __construct(Database $db) { $this->db = $db; }

    /**
     * Reichert einen bereits duplizierten Entwurf an.
     *
     * @param array $opts { briefing?: string, link_asana?: bool, user_id?: int }
     * @return array { plan_id, rows_updated, rows_linked, rows_unclear, tickets_considered, tokens_in, tokens_out, rationale }
     */
    public function enrichDuplicatedPlan(int $planId, array $opts = []): array
    {
        $key = (string) Settings::get('anthropic_api_key');
        if ($key === '') {
            throw new \RuntimeException('Anthropic API-Key nicht konfiguriert (Einstellungen → Provider).');
        }

        require_once __DIR__ . '/ProjektplannerService.php';
        $ppSvc = new ProjektplannerService($this->db);
        $plan = $ppSvc->getPlanWithRows($planId);
        if (!$plan) throw new \RuntimeException('Plan nicht gefunden.');
        // Sicherheits-Netz: nur Entwuerfe anreichern — niemals einen aktiven/reporteten Plan anfassen.
        if (($plan['plan_status'] ?? '') !== 'entwurf') {
            throw new \RuntimeException('KI-Anreicherung ist nur fuer Entwuerfe moeglich (Plan-Status: ' . ($plan['plan_status'] ?? '?') . ').');
        }

        $customerId = (int) ($plan['customer_id'] ?? 0);
        $briefing   = trim((string) ($opts['briefing'] ?? ''));
        $linkAsana  = array_key_exists('link_asana', $opts) ? !empty($opts['link_asana']) : true;

        // ===== Item-Zeilen einsammeln (Anker = DB-id, stabil gegen Positions-Chaos) =====
        $rows = $plan['rows'] ?? [];
        $items = [];      // row_id => row
        $sectionOf = [];  // row_id => aktuelle Sektionsueberschrift
        $currentSection = '';
        $maxPos = -1;
        foreach ($rows as $r) {
            $maxPos = max($maxPos, (int) ($r['position'] ?? 0));
            if (($r['row_type'] ?? '') === 'section') { $currentSection = (string) $r['description']; continue; }
            if (($r['row_type'] ?? '') !== 'item') continue;
            if ((int) ($r['is_placeholder'] ?? 0)) continue;
            $rid = (int) $r['id'];
            $items[$rid] = $r;
            $sectionOf[$rid] = $currentSection;
        }
        if (empty($items)) {
            return ['plan_id' => $planId, 'rows_updated' => 0, 'rows_linked' => 0, 'rows_unclear' => 0,
                    'tickets_considered' => 0, 'tokens_in' => 0, 'tokens_out' => 0, 'rationale' => 'Keine Item-Zeilen zum Anreichern.'];
        }

        // ===== Offene Asana-Tickets (so weit moeglich) =====
        $tickets = [];
        if ($linkAsana) $tickets = $this->fetchOpenTickets((string) ($plan['asana_project_gid'] ?? ''));
        $ticketGids = array_column($tickets, 'gid');

        // ===== Kontext =====
        $profile  = $this->buildCustomerProfile($customerId);
        $taxonomy = (new PpTaxonomyService($this->db))->getForCustomer($customerId);
        require_once __DIR__ . '/PpAiRulesService.php';
        $rulesText = (new PpAiRulesService($this->db))->activeRulesText($customerId);

        // ===== Tool + Prompts =====
        $tool = $this->buildTool();
        $systemPrompt = $this->buildSystemPrompt($plan, $profile, $taxonomy, $rulesText);
        $userPrompt   = $this->buildUserPrompt($plan, $items, $sectionOf, $tickets, $briefing, $linkAsana);

        $payload = [
            'model'       => self::MODEL,
            'max_tokens'  => self::MAX_TOKENS,
            'system'      => $systemPrompt,
            'tools'       => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => 'enrich_plan_rows'],
            'messages'    => [['role' => 'user', 'content' => $userPrompt]],
        ];

        $resp = $this->callAnthropic($key, $payload);
        $toolInput = $this->extractToolInput($resp);

        // ===== Anwenden (transaktional) =====
        [$updated, $linked, $unclear] = $this->applyChanges($planId, $toolInput, $items, $ticketGids, $tickets, $linkAsana, $maxPos, (string) ($toolInput['rationale'] ?? ''));

        return [
            'plan_id'            => $planId,
            'rows_updated'       => $updated,
            'rows_linked'        => $linked,
            'rows_unclear'       => $unclear,
            'tickets_considered' => count($tickets),
            'tokens_in'          => $resp['usage']['input_tokens'] ?? 0,
            'tokens_out'         => $resp['usage']['output_tokens'] ?? 0,
            'rationale'          => (string) ($toolInput['rationale'] ?? ''),
        ];
    }

    // ============================================================
    //  Asana
    // ============================================================

    /** Offene Tickets des Projekts holen; leer bei fehlender GID/PAT (kein harter Fehler). */
    private function fetchOpenTickets(string $projectGid): array
    {
        $projectGid = trim($projectGid);
        if ($projectGid === '') return [];
        $pat = (string) Settings::get('asana_pat');
        if ($pat === '') return [];

        require_once __DIR__ . '/AsanaService.php';
        try {
            $asana = new AsanaService($pat, 20);
            $tasks = $asana->getTasks($projectGid);
        } catch (\Throwable $e) {
            return []; // Asana-Ausfall darf die Anreicherung nicht sprengen
        }
        $open = array_values(array_filter($tasks, fn($t) => empty($t['completed'])));
        // Nach Faelligkeit sortieren (frueh zuerst; ohne due_on ans Ende), dann deckeln.
        usort($open, function ($a, $b) {
            $da = $a['due_on'] ?? '9999-99-99';
            $db = $b['due_on'] ?? '9999-99-99';
            return strcmp((string) $da, (string) $db);
        });
        $open = array_slice($open, 0, self::MAX_TICKETS);
        $out = [];
        foreach ($open as $t) {
            $out[] = [
                'gid'   => (string) ($t['gid'] ?? ''),
                'name'  => (string) ($t['name'] ?? ''),
                'url'   => (string) ($t['permalink_url'] ?? ''),
                'due_on'=> (string) ($t['due_on'] ?? ''),
                'notes' => mb_substr(trim((string) ($t['notes'] ?? '')), 0, 300),
            ];
        }
        return $out;
    }

    // ============================================================
    //  Tool-Schema
    // ============================================================

    private function buildTool(): array
    {
        return [
            'name' => 'enrich_plan_rows',
            'description' => 'Reichert kopierte Plan-Zeilen an: aktualisierte Beschreibung, Zeitraum, Deadline und optionale Asana-Verknuepfung. Nur Zeilen auffuehren, die tatsaechlich geaendert werden.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'rows' => [
                        'type' => 'array',
                        'description' => 'Nur die Item-Zeilen, die Du aenderst. Unveraenderte Zeilen NICHT auffuehren.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'row_id'      => ['type' => 'integer', 'description' => 'Die exakte row_id aus der Eingabe. Niemals erfinden.'],
                                'description' => ['type' => 'string', 'description' => 'Ueberarbeitete Beschreibung: an Kunden-Standard angeglichen, Ticket-Infos/URLs eingearbeitet. Zeilenspezifische Details erhalten.'],
                                'timeframe'   => ['type' => 'string', 'description' => 'An die neue Periode angepasster Zeitraum, z.B. "01.-15.07.".'],
                                'deadline'    => ['type' => 'string', 'description' => 'Deadline im Stil des Plans, z.B. "Juli 2026" oder "Juli-August 2026". KEIN ISO-Datum.'],
                                'asana_gid'   => ['type' => 'string', 'description' => 'GID eines OFFENEN Tickets aus der bereitgestellten Liste. Nur wenn eindeutig passend, sonst weglassen.'],
                                'asana_url'   => ['type' => 'string', 'description' => 'permalink_url des verknuepften Tickets (aus der Liste).'],
                                'asana_task_name' => ['type' => 'string', 'description' => 'name des verknuepften Tickets (aus der Liste).'],
                                'unclear'     => ['type' => 'boolean', 'description' => 'true, wenn dieser uebernommene Schritt in der neuen Periode vermutlich nicht mehr passt und geprueft/geloescht werden sollte. NICHT loeschen, nur markieren.'],
                            ],
                            'required' => ['row_id'],
                        ],
                    ],
                    'rationale' => ['type' => 'string', 'description' => 'Kurze Begruendung der Anpassungen (2-4 Saetze).'],
                ],
                'required' => ['rows'],
            ],
        ];
    }

    // ============================================================
    //  Prompts
    // ============================================================

    private function buildSystemPrompt(array $plan, string $profile, array $taxonomy, string $rulesText = ''): string
    {
        $from = $plan['period_from'] ?? null;
        $to   = $plan['period_to'] ?? null;
        $period = ($from && $to) ? date('d.m.Y', strtotime($from)) . ' – ' . date('d.m.Y', strtotime($to)) : 'noch offen';

        // Standard-Beschreibungen aus der Taxonomy (typical_items je Sektion).
        $stdTxt = '';
        if (!empty($taxonomy)) {
            $lines = [];
            foreach (array_slice($taxonomy, 0, 25) as $t) {
                $typical = '';
                if (!empty($t['typical_items'])) {
                    $its = json_decode($t['typical_items'], true);
                    if (is_array($its)) {
                        $top = array_slice(array_keys($its), 0, 4);
                        $typical = ': ' . implode(' · ', array_map(fn($x) => '„' . mb_substr($x, 0, 60) . '"', $top));
                    }
                }
                $lines[] = '- ' . ($t['display_name'] ?? '') . $typical;
            }
            $stdTxt = "STANDARD-/TYPISCHE FORMULIERUNGEN DIESES KUNDEN (zum Angleichen):\n" . implode("\n", $lines) . "\n\n";
        }

        return "Du bearbeitest einen bereits DUPLIZIERTEN Projektplan-Entwurf der Thoxan Communications GmbH.\n"
            . "Es ist ein DUPLIKAT des Vorquartals — die Grundstruktur, Reihenfolge und Logik bleiben erhalten.\n"
            . "Neuer Plan-Zeitraum: $period.\n\n"
            . "DEINE AUFGABE (nur Anpassen, nicht neu erfinden):\n"
            . "- Beschreibungen an die neue Periode und die Standard-Formulierungen des Kunden angleichen, Details erhalten.\n"
            . "- Passende OFFENE Asana-Tickets verknuepfen: hoechstens EIN Ticket je Zeile, nur GIDs aus der Liste, sonst keins. Erfinde nie ein Ticket.\n"
            . "- Beim Verknuepfen KEINE URL in die Beschreibung schreiben (der Link steht bereits in der Spalte). Nur wirklich hilfreiche Kerninfos aus dem Ticket knapp einarbeiten.\n"
            . "- Zeitraeume (timeframe) und Deadlines auf die NEUE Periode setzen — mit SAUBEREN Grenzen: geht ein Schritt uebers ganze Quartal, nutze den vollen neuen Quartals-Zeitraum (z.B. \"01.07.-30.09.\"); betrifft er einen Monat, den vollen Monat (z.B. \"01.-31.08.\"). Keine krummen Enddaten wie \"29.09.\".\n"
            . "- Uebernommene Schritte, die jetzt vermutlich nicht mehr passen: unclear=true setzen (NICHT loeschen, KEIN Praefix im Text — die Markierung erscheint als Pill).\n\n"
            . "VERBOTEN:\n"
            . "- Zeilen loeschen, hinzufuegen oder umsortieren. Sektionsstruktur nicht aendern. Stunden nicht aendern.\n"
            . "- Zeilen ausgeben, die Du nicht aenderst.\n"
            . "- Hoefflichkeitsformen Du/Dich/Dir gross schreiben, keine Gedankenstriche.\n\n"
            . "STECKBRIEF:\n$profile\n\n"
            . $stdTxt
            . ($rulesText !== ''
                ? "GELERNTE REGELN FUER DIESEN KUNDEN (vom Team bestaetigt — UNBEDINGT befolgen, sie haben Vorrang):\n" . $rulesText . "\n\n"
                : '')
            . "WICHTIG: JEDE Zeile, die Du aenderst (auch wenn nur Zeitraum/Deadline), MUSS mit ihrer row_id im 'rows'-Array stehen. "
            . "Die rationale ist nur eine kurze Zusammenfassung und ERSETZT das rows-Array NIEMALS. Wenn Du z.B. alle Zeitraeume auf das neue Quartal verschiebst, "
            . "muessen ALLE diese Zeilen einzeln im rows-Array erscheinen.\n\n"
            . "Rufe das Tool 'enrich_plan_rows' mit allen geaenderten Zeilen auf.";
    }

    private function buildUserPrompt(array $plan, array $items, array $sectionOf, array $tickets, string $briefing, bool $linkAsana): string
    {
        // Zeilen gruppiert nach Sektion, mit row_id als Anker.
        $lines = [];
        $lastSec = null;
        foreach ($items as $rid => $r) {
            $sec = $sectionOf[$rid] ?? '';
            if ($sec !== $lastSec) { $lines[] = "\n### Sektion: $sec"; $lastSec = $sec; }
            $tf = trim((string) ($r['timeframe'] ?? ''));
            $dl = trim((string) ($r['deadline'] ?? ''));
            $link = $r['asana_gid'] ? ' [verknuepft: ' . $r['asana_gid'] . ']' : '';
            $lines[] = "row_id={$rid} | Zeitraum: " . ($tf ?: '-') . " | Deadline: " . ($dl ?: '-') . $link
                . "\n  " . trim(str_replace("\n", ' ', (string) $r['description']));
        }
        $rowsTxt = implode("\n", $lines);

        $ticketsTxt = '';
        if ($linkAsana) {
            if (!empty($tickets)) {
                $tl = [];
                foreach ($tickets as $t) {
                    $note = $t['notes'] !== '' ? ' | Notiz: ' . str_replace("\n", ' ', $t['notes']) : '';
                    $tl[] = "gid={$t['gid']} | " . $t['name'] . ' | faellig: ' . ($t['due_on'] ?: '-') . ' | url: ' . $t['url'] . $note;
                }
                $ticketsTxt = "\n\nOFFENE ASANA-TICKETS (nur diese GIDs sind erlaubt):\n" . implode("\n", $tl);
            } else {
                $ticketsTxt = "\n\nOFFENE ASANA-TICKETS: keine verfuegbar (kein Projekt/Token oder keine offenen Tickets). Verknuepfe nichts.";
            }
        }

        $briefTxt = $briefing !== ''
            ? "\n\nGROBE PLANUNG / BRIEFING vom Nutzer (steuert, was angepasst und was als 'unclear' markiert wird):\n" . $briefing
            : "\n\nKein Briefing — gleiche v.a. an die neue Periode und die Standard-Formulierungen an.";

        return "Hier sind die kopierten Item-Zeilen des Entwurfs (Anker = row_id):\n" . $rowsTxt
            . $ticketsTxt
            . $briefTxt
            . "\n\nRufe jetzt das Tool 'enrich_plan_rows' mit ausschliesslich den geaenderten Zeilen auf.";
    }

    // ============================================================
    //  Anwenden
    // ============================================================

    /** @return array [updated, linked, unclear] */
    private function applyChanges(int $planId, array $toolInput, array $items, array $ticketGids, array $tickets, bool $linkAsana, int $maxPos, string $rationale): array
    {
        $ticketByGid = [];
        foreach ($tickets as $t) $ticketByGid[$t['gid']] = $t;

        $updated = 0; $linked = 0; $unclear = 0;
        $this->db->beginTransaction();
        try {
            foreach (($toolInput['rows'] ?? []) as $r) {
                $rid = (int) ($r['row_id'] ?? 0);
                if (!isset($items[$rid])) continue; // unbekannte/halluzinierte id ignorieren

                $update = [];
                $isUnclear = !empty($r['unclear']);

                // Beschreibung — KEIN "Zu klaeren"-Praefix im Text (das kommt als Pill via review_note).
                $desc = array_key_exists('description', $r) ? trim((string) $r['description']) : null;
                if ($desc !== null && $desc !== '') $update['description'] = $desc;

                // Zeitraum / Deadline
                if (array_key_exists('timeframe', $r)) $update['timeframe'] = trim((string) $r['timeframe']) ?: null;
                if (array_key_exists('deadline', $r))  $update['deadline']  = trim((string) $r['deadline']) ?: null;

                // Asana-Verknuepfung — nur gids aus der gelieferten Liste (keine Erfindungen).
                // Die URL kommt in die Spalte (asana_url), NICHT in den Beschreibungstext.
                if ($linkAsana && !empty($r['asana_gid']) && in_array((string) $r['asana_gid'], $ticketGids, true)) {
                    $gid = (string) $r['asana_gid'];
                    $t = $ticketByGid[$gid] ?? [];
                    $update['asana_gid']       = $gid;
                    $update['asana_url']       = (string) ($r['asana_url'] ?? $t['url'] ?? '');
                    $update['asana_task_name'] = (string) ($r['asana_task_name'] ?? $t['name'] ?? '');
                }

                // „Zu klaeren" als Status-Pill (review_note) statt Text-Praefix — auch wenn sonst nichts geaendert wurde.
                if ($isUnclear) $update['review_note'] = 'Zu klären';

                if (empty($update)) continue;

                // Direkt schreiben (NICHT updateRow) — sonst wuerde review_flag automatisch geloescht.
                $update['review_flag'] = 1;
                $this->db->update('pp_plan_rows', $update, 'id = ?', [$rid]);
                $updated++;
                if (!empty($update['asana_gid'])) $linked++;
                if ($isUnclear) $unclear++;
            }

            // Begruendung als Notiz-Zeile ans Ende
            if (trim($rationale) !== '') {
                $this->db->insert('pp_plan_rows', [
                    'plan_id'  => $planId,
                    'row_type' => 'note',
                    'description' => '',
                    'notes'    => 'KI-Anreicherung: ' . trim($rationale),
                    'position' => $maxPos + 1,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        require_once __DIR__ . '/ProjektplannerService.php';
        (new ProjektplannerService($this->db))->markKnowledgeDirty($planId);

        return [$updated, $linked, $unclear];
    }

    // ============================================================
    //  Kontext-Baustein (aus PpPlanGeneratorService gespiegelt)
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

    // ============================================================
    //  Anthropic-Call + Tool-Parsing (aus PpPlanGeneratorService gespiegelt)
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
            throw new \RuntimeException('Anthropic: kein gueltiges JSON: ' . substr((string) $body, 0, 300));
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
            if (($b['type'] ?? '') === 'tool_use' && ($b['name'] ?? '') === 'enrich_plan_rows') {
                return $b['input'] ?? [];
            }
        }
        throw new \RuntimeException('Claude hat das Tool nicht aufgerufen. Rohantwort: ' . substr(json_encode($response), 0, 400));
    }
}
