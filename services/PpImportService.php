<?php
namespace Services;

use Core\Database;

/**
 * PpImportService — Importiert eine Tallyr-JSON-Export-Datei.
 *
 * Erwartetes Format: siehe PROJEKTPLANNER-MODUL.md Abschnitt 9.2 (export_version 1.0).
 *
 * Strategie:
 *   - Kunden: mapping per slug oder name (case-insensitive). Match → bestehende ID,
 *     fehlende Felder (stundensatz, hex_color, uebertrag_ts, uebertrag_notiz, abrechnungsmodus)
 *     werden ergänzt falls leer. Kein Match → neuer Kunde mit allen Feldern.
 *   - Team-Tags: mapping per name (case-insensitive). Match → capacity updaten falls leer.
 *     Kein Match → neues pp_team_member (user_id NULL = freie Person).
 *   - Pläne: immer neu, alter `userid` ignoriert (team-shared), alter `share_hash`
 *     übernommen wenn noch frei, sonst neu generiert.
 *   - Rows: immer neu, mit neuer plan_id.
 *   - Budgets/Feedback/Revisionen/Person-Shares: mit gemappten IDs eingefügt.
 *
 * Methode preview() macht dasselbe ohne DB-Writes und gibt nur ein Diff zurück.
 */
class PpImportService
{
    private Database $db;
    private array $customerMap = [];   // tallyr_id => unsere_customer_id
    private array $planMap = [];        // tallyr_id => unsere_plan_id
    private array $rowMap = [];         // tallyr_id => unsere_row_id
    private array $log = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function validate(array $data): void
    {
        if (($data['export_version'] ?? null) !== '1.0') {
            throw new \RuntimeException('export_version 1.0 erwartet, gefunden: ' . ($data['export_version'] ?? '?'));
        }
        if (!isset($data['clients']) || !is_array($data['clients'])) {
            throw new \RuntimeException('clients[]-Array fehlt');
        }
        if (!isset($data['plans']) || !is_array($data['plans'])) {
            throw new \RuntimeException('plans[]-Array fehlt');
        }
    }

    /**
     * Erkennt das echte Tallyr-Export-Format (verschachtelte plans, wp_users statt team_tags)
     * und konvertiert es ins interne flache Format.
     */
    private function normalize(array $data): array
    {
        $isTallyrFormat = isset($data['wp_users']) ||
                          (!empty($data['plans']) && is_array($data['plans']) && isset($data['plans'][0]['plan']));
        if (!$isTallyrFormat) return $data;

        // 1. team_tags aus allen wp_users.meta.team_tags zusammenführen (dedup per Name lowercase)
        $teamTags = $data['team_tags'] ?? [];
        $seen = [];
        foreach ($teamTags as $t) {
            if (!empty($t['name'])) $seen[mb_strtolower(trim($t['name']))] = true;
        }
        foreach ($data['wp_users'] ?? [] as $u) {
            $meta = $u['meta'] ?? [];
            $tags = $meta['team_tags'] ?? [];
            if (is_string($tags)) {
                $decoded = json_decode($tags, true);
                $tags = is_array($decoded) ? $decoded : [];
            }
            foreach ($tags as $t) {
                $name = trim((string) ($t['name'] ?? ''));
                if ($name === '') continue;
                $key = mb_strtolower($name);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $teamTags[] = [
                    'name' => $name,
                    'kuerzel' => $t['kuerzel'] ?? '',
                    'capacity' => $t['capacity'] ?? 160,
                ];
            }
        }
        $data['team_tags'] = $teamTags;

        // 2. plans flach machen + Tallyr-state/abrechnungsmodus normalisieren
        $flatPlans = [];
        foreach ($data['plans'] ?? [] as $wrapper) {
            if (!isset($wrapper['plan'])) {
                $flatPlans[] = $wrapper; // schon flach
                continue;
            }
            $p = $wrapper['plan'];
            // Tallyr-state: 1=aktiv → 1, sonst → 2 (soft-deleted)
            $p['state'] = (int) ($p['state'] ?? 0) === 1 ? 1 : 2;
            $p['rows'] = $wrapper['rows'] ?? [];
            $p['shares'] = $wrapper['shares'] ?? [];
            $p['feedback'] = $wrapper['feedback'] ?? [];
            $p['revisions'] = $wrapper['revisions'] ?? [];
            $p['budget_overrides'] = $wrapper['budget_overrides'] ?? [];
            // Leere share_hashes → NULL (sonst UNIQUE-Kollision)
            if (empty($p['share_hash'])) $p['share_hash'] = null;
            // 'None'-String → null bei Datumsfeldern und ähnlichen
            foreach (['period_from', 'period_to', 'asana_project_gid', 'asana_section_gid', 'share_password', 'quarter'] as $k) {
                if (($p[$k] ?? null) === 'None' || $p[$k] === '') $p[$k] = null;
            }
            $flatPlans[] = $p;
        }
        $data['plans'] = $flatPlans;

        // 3. Customer-Felder normalisieren
        foreach ($data['clients'] ?? [] as $i => $c) {
            // 'ts' (Tagessatz-basiert) → unser Default 'quarterly'
            if (($c['abrechnungsmodus'] ?? '') === 'ts') {
                $data['clients'][$i]['abrechnungsmodus'] = 'quarterly';
            }
            // is_active aus state ableiten
            $data['clients'][$i]['is_active'] = (int) ($c['state'] ?? 1) === 1 ? 1 : 0;
        }

        // 4. client_budgets: 'None' (String) → null
        foreach ($data['client_budgets'] ?? [] as $i => $b) {
            if (($b['ist_override'] ?? null) === 'None' || $b['ist_override'] === '') {
                $data['client_budgets'][$i]['ist_override'] = null;
            }
        }

        return $data;
    }

    /**
     * Filtert die Kunden-Liste: nur Clients behalten, die in mindestens einem
     * Plan referenziert sind (über `client_id`). Verhindert, dass Tallyr-Exporte
     * mit allen Mandanten-Kunden Müll im KI-Tool anlegen.
     */
    private function filterClientsToReferenced(array $data): array
    {
        if (empty($data['plans']) || empty($data['clients'])) return $data;
        $usedIds = [];
        foreach ($data['plans'] as $p) {
            $cid = $p['client_id'] ?? null;
            if ($cid) $usedIds[(string) $cid] = true;
        }
        if (empty($usedIds)) return $data;
        // Kunden filtern
        $data['clients'] = array_values(array_filter($data['clients'], function ($c) use ($usedIds) {
            return isset($usedIds[(string) ($c['id'] ?? '')]);
        }));
        // Customer-Budgets auch filtern (sonst Budgets ins Leere)
        if (!empty($data['client_budgets']) && is_array($data['client_budgets'])) {
            $data['client_budgets'] = array_values(array_filter($data['client_budgets'], function ($b) use ($usedIds) {
                return isset($usedIds[(string) ($b['client_id'] ?? '')]);
            }));
        }
        // Team-Tags: nur die behalten, die in einer Plan-Zeile (responsible/lead) genannt werden
        if (!empty($data['team_tags']) && is_array($data['team_tags']) && !empty($data['plans'])) {
            $usedNames = [];
            foreach ($data['plans'] as $p) {
                foreach ($p['rows'] ?? [] as $r) {
                    $lead = trim((string) ($r['lead_responsible'] ?? ''));
                    if ($lead !== '') $usedNames[mb_strtolower($lead)] = true;
                    foreach (explode(',', (string) ($r['responsible'] ?? '')) as $n) {
                        $n = trim($n);
                        if ($n !== '') $usedNames[mb_strtolower($n)] = true;
                    }
                }
            }
            $data['team_tags'] = array_values(array_filter($data['team_tags'], function ($t) use ($usedNames) {
                $n = mb_strtolower(trim((string) ($t['name'] ?? '')));
                return $n !== '' && isset($usedNames[$n]);
            }));
        }
        return $data;
    }

    /**
     * Preview-Mode: zählt was passieren würde, ohne DB-Writes.
     * Liefert zusätzlich Detail-Liste der unmappbaren Kunden mit Plan-Counts,
     * damit der User entscheiden kann ob er die Pläne überspringen will.
     */
    public function preview(array $data): array
    {
        $this->validate($data);
        $data = $this->normalize($data);
        $data = $this->filterClientsToReferenced($data);

        // Plan-Counts pro Kunden-ID berechnen
        $plansByCust = [];
        foreach ($data['plans'] ?? [] as $p) {
            $cid = (string) ($p['client_id'] ?? '');
            if ($cid) $plansByCust[$cid] = ($plansByCust[$cid] ?? 0) + 1;
        }

        $newCustomers = 0; $mappedCustomers = 0;
        $unmappedDetails = [];
        $unmappedCustIds = [];
        foreach ($data['clients'] ?? [] as $c) {
            $existing = $this->findCustomer($c);
            if ($existing) {
                $mappedCustomers++;
            } else {
                $newCustomers++;
                $cid = (string) ($c['id'] ?? '');
                $planCount = $plansByCust[$cid] ?? 0;
                $unmappedDetails[] = [
                    'tallyr_id' => $cid,
                    'title' => $c['title'] ?? '?',
                    'shortdesc' => $c['shortdesc'] ?? '',
                    'plans' => $planCount,
                ];
                $unmappedCustIds[$cid] = true;
            }
        }
        $newTeam = 0; $mappedTeam = 0;
        foreach ($data['team_tags'] ?? [] as $t) {
            $existing = $this->db->queryOne(
                "SELECT id FROM pp_team_members WHERE LOWER(name) = LOWER(?) LIMIT 1",
                [trim((string) ($t['name'] ?? ''))]
            );
            if ($existing) $mappedTeam++; else $newTeam++;
        }
        $totalPlans = count($data['plans'] ?? []);
        // Anzahl Pläne, deren Customer NICHT gemappt ist
        $unmappedPlanCount = 0;
        $unmappedRowCount = 0;
        foreach ($data['plans'] ?? [] as $p) {
            $cid = (string) ($p['client_id'] ?? '');
            if (isset($unmappedCustIds[$cid])) {
                $unmappedPlanCount++;
                $unmappedRowCount += count($p['rows'] ?? []);
            }
        }
        $totalRows = 0;
        foreach ($data['plans'] ?? [] as $p) $totalRows += count($p['rows'] ?? []);
        $totalBudgets = count($data['client_budgets'] ?? []);
        $totalShares = count($data['person_shares'] ?? []);

        return [
            'customers' => ['total' => count($data['clients'] ?? []), 'new' => $newCustomers, 'mapped' => $mappedCustomers, 'unmapped_list' => $unmappedDetails],
            'team_members' => ['total' => count($data['team_tags'] ?? []), 'new' => $newTeam, 'mapped' => $mappedTeam],
            'plans' => ['total' => $totalPlans, 'new' => $totalPlans, 'unmapped' => $unmappedPlanCount],
            'plan_rows' => ['total' => $totalRows, 'new' => $totalRows, 'unmapped' => $unmappedRowCount],
            'customer_budgets' => ['total' => $totalBudgets, 'new' => $totalBudgets],
            'person_shares' => ['total' => $totalShares, 'new' => $totalShares],
        ];
    }

    /**
     * Echter Import. Returnt Statistik.
     */
    /**
     * Echter Import.
     * @param array $options
     *   - 'skip_unmapped' => bool — globaler Skip für alle unmappable Pläne
     *   - 'customer_mapping' => array<tallyrCustomerId, action>
     *     action = "skip" | "new" | int (KI-Tool-Customer-ID zum Mappen)
     *     Wenn ein Tallyr-Kunde in dieser Map ist, überschreibt sie das Default-Verhalten.
     */
    public function importFromJson(array $data, int $userId, array $options = []): array
    {
        $this->validate($data);
        $data = $this->normalize($data);
        $data = $this->filterClientsToReferenced($data);
        $skipUnmapped = !empty($options['skip_unmapped']);
        $customerMapping = $options['customer_mapping'] ?? [];

        // Schritt 1: Custom-Mapping aus UI anwenden (überschreibt Auto-Match)
        if (!empty($customerMapping)) {
            $remaining = [];
            $skippedPlans = 0; $skippedRows = 0;
            $skippedCustomerIds = [];
            foreach ($data['clients'] ?? [] as $c) {
                $tid = (string) ($c['id'] ?? '');
                $action = $customerMapping[$tid] ?? null;
                if ($action === 'skip') {
                    $skippedCustomerIds[$tid] = true;
                    continue;
                }
                if (is_numeric($action) && (int) $action > 0) {
                    // Forced mapping auf existierenden Customer
                    $this->customerMap[(int) $tid] = (int) $action;
                    continue; // nicht in importCustomers re-anlegen
                }
                // 'new' oder kein Mapping → normal weiter
                $remaining[] = $c;
            }
            $data['clients'] = $remaining;

            // Pläne der geskippten Customers entfernen
            if (!empty($skippedCustomerIds)) {
                $data['plans'] = array_values(array_filter($data['plans'] ?? [], function ($p) use ($skippedCustomerIds, &$skippedPlans, &$skippedRows) {
                    if (isset($skippedCustomerIds[(string) ($p['client_id'] ?? '')])) {
                        $skippedPlans++;
                        $skippedRows += count($p['rows'] ?? []);
                        return false;
                    }
                    return true;
                }));
                if (!empty($data['client_budgets'])) {
                    $data['client_budgets'] = array_values(array_filter($data['client_budgets'], fn($b) => !isset($skippedCustomerIds[(string) ($b['client_id'] ?? '')])));
                }
                $this->log[] = "Manuell übersprungen: $skippedPlans Pläne + $skippedRows Zeilen (Kunden bewusst übersprungen)";
            }
        }

        // Schritt 2: globaler Skip-Modus für Rest-Unmappable
        if ($skipUnmapped) {
            $unmappableIds = [];
            foreach ($data['clients'] ?? [] as $c) {
                if (!$this->findCustomer($c)) {
                    $unmappableIds[(string) ($c['id'] ?? '')] = true;
                }
            }
            $skippedPlans = 0; $skippedRows = 0;
            $data['plans'] = array_values(array_filter($data['plans'] ?? [], function ($p) use ($unmappableIds, &$skippedPlans, &$skippedRows) {
                if (isset($unmappableIds[(string) ($p['client_id'] ?? '')])) {
                    $skippedPlans++;
                    $skippedRows += count($p['rows'] ?? []);
                    return false;
                }
                return true;
            }));
            // Customers nochmal filtern (nur gemappte zulassen)
            $data['clients'] = array_values(array_filter($data['clients'] ?? [], function ($c) use ($unmappableIds) {
                return !isset($unmappableIds[(string) ($c['id'] ?? '')]);
            }));
            // Customer-Budgets ebenfalls
            if (!empty($data['client_budgets'])) {
                $data['client_budgets'] = array_values(array_filter($data['client_budgets'], function ($b) use ($unmappableIds) {
                    return !isset($unmappableIds[(string) ($b['client_id'] ?? '')]);
                }));
            }
            $this->log[] = "Übersprungen: $skippedPlans Pläne + $skippedRows Zeilen (Kunde nicht im KI-Tool)";
        }

        $this->customerMap = [];
        $this->planMap = [];
        $this->rowMap = [];

        $this->db->beginTransaction();
        try {
            $this->importCustomers($data['clients'] ?? []);
            $this->importTeamTags($data['team_tags'] ?? []);
            $this->importPlans($data['plans'] ?? [], $userId);
            $this->importCustomerBudgets($data['client_budgets'] ?? []);
            $this->importPersonShares($data['person_shares'] ?? [], $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'log' => $this->log,
            'imported' => [
                'customers' => count($this->customerMap),
                'plans' => count($this->planMap),
                'rows' => count($this->rowMap),
            ],
        ];
    }

    // ===== Customers =====

    private function findCustomer(array $c): ?array
    {
        // 1. Slug-Match
        $slugCandidate = $this->slugify((string) ($c['shortdesc'] ?? $c['title'] ?? ''));
        if ($slugCandidate) {
            $row = $this->db->queryOne("SELECT id, name, slug FROM customers WHERE slug = ? LIMIT 1", [$slugCandidate]);
            if ($row) return $row;
        }
        // 2. Name-Match (case-insensitive)
        $name = trim((string) ($c['title'] ?? ''));
        if ($name) {
            $row = $this->db->queryOne(
                "SELECT id, name, slug FROM customers WHERE LOWER(name) = LOWER(?) LIMIT 1",
                [$name]
            );
            if ($row) return $row;
        }
        return null;
    }

    private function importCustomers(array $clients): void
    {
        foreach ($clients as $c) {
            $oldId = (int) ($c['id'] ?? 0);
            if (!$oldId) continue;
            $name = trim((string) ($c['title'] ?? ''));
            if (!$name) continue;

            $existing = $this->findCustomer($c);
            if ($existing) {
                $this->customerMap[$oldId] = (int) $existing['id'];
                // Ergänzende Felder updaten falls leer
                $updates = [];
                $patches = [
                    'abbreviation' => $c['shortdesc'] ?? null,
                    'hex_color' => $c['hexcolor'] ?? null,
                    'website' => $c['url'] ?? null,
                    'stundensatz' => isset($c['stundensatz']) ? (float) $c['stundensatz'] : null,
                    'uebertrag_ts' => isset($c['uebertrag_ts']) ? (float) $c['uebertrag_ts'] : null,
                    'uebertrag_notiz' => $c['uebertrag_notiz'] ?? null,
                    'abrechnungsmodus' => $c['abrechnungsmodus'] ?? null,
                ];
                foreach ($patches as $field => $val) {
                    if ($val === null || $val === '') continue;
                    $current = $this->db->queryValue("SELECT $field FROM customers WHERE id = ?", [$existing['id']]);
                    if ($current === null || $current === '' || $current === '0' || $current == 0) {
                        $updates[$field] = $val;
                    }
                }
                if (!empty($updates)) {
                    $this->db->update('customers', $updates, 'id = ?', [$existing['id']]);
                    $this->log[] = "Kunde #{$existing['id']} '{$existing['name']}' ergänzt: " . implode(', ', array_keys($updates));
                }
            } else {
                $slug = $this->ensureUniqueSlug($this->slugify((string) ($c['shortdesc'] ?? $name)));
                $newId = (int) $this->db->insert('customers', [
                    'name' => $name,
                    'slug' => $slug,
                    'abbreviation' => mb_substr((string) ($c['shortdesc'] ?? ''), 0, 10),
                    'hex_color' => $c['hexcolor'] ?? null,
                    'website' => $c['url'] ?? null,
                    'stundensatz' => isset($c['stundensatz']) ? (float) $c['stundensatz'] : null,
                    'uebertrag_ts' => isset($c['uebertrag_ts']) ? (float) $c['uebertrag_ts'] : 0,
                    'uebertrag_notiz' => $c['uebertrag_notiz'] ?? null,
                    'abrechnungsmodus' => in_array($c['abrechnungsmodus'] ?? '', ['monthly','bimonthly','quarterly','halfyear','yearly'], true)
                        ? $c['abrechnungsmodus'] : 'quarterly',
                    'is_active' => isset($c['is_active']) ? (int) $c['is_active'] : 1,
                ]);
                $this->customerMap[$oldId] = $newId;
                $this->log[] = "Kunde NEU: #$newId '$name' (slug=$slug)";
            }
        }
    }

    // ===== Team Tags =====

    private function importTeamTags(array $tags): void
    {
        $maxOrder = (int) ($this->db->queryValue("SELECT COALESCE(MAX(sort_order), 0) FROM pp_team_members") ?? 0);
        foreach ($tags as $t) {
            $name = trim((string) ($t['name'] ?? ''));
            if (!$name) continue;
            $existing = $this->db->queryOne(
                "SELECT id, name, capacity_hours FROM pp_team_members WHERE LOWER(name) = LOWER(?) LIMIT 1",
                [$name]
            );
            if ($existing) {
                if (!empty($t['capacity']) && (int) $existing['capacity_hours'] !== (int) $t['capacity']) {
                    $this->db->update('pp_team_members',
                        ['capacity_hours' => (int) $t['capacity']],
                        'id = ?', [$existing['id']]
                    );
                    $this->log[] = "Team '{$existing['name']}' capacity → {$t['capacity']}";
                }
            } else {
                $maxOrder++;
                $newId = $this->db->insert('pp_team_members', [
                    'user_id' => null,
                    'name' => $name,
                    'abbreviation' => mb_substr((string) ($t['kuerzel'] ?? ''), 0, 10),
                    'capacity_hours' => (int) ($t['capacity'] ?? 160),
                    'sort_order' => $maxOrder,
                    'is_active' => 1,
                ]);
                $this->log[] = "Team NEU (extern): #$newId '$name'";
            }
        }
    }

    // ===== Plans + Rows =====

    private function importPlans(array $plans, int $userId): void
    {
        foreach ($plans as $p) {
            $oldId = (int) ($p['id'] ?? 0);
            if (!$oldId) continue;
            $title = trim((string) ($p['title'] ?? ''));
            if (!$title) continue;

            $customerId = !empty($p['client_id']) ? ($this->customerMap[(int) $p['client_id']] ?? null) : null;
            $shareHash = $p['share_hash'] ?? null;
            if ($shareHash) {
                $existsHash = $this->db->queryOne("SELECT id FROM pp_plans WHERE share_hash = ?", [$shareHash]);
                if ($existsHash) $shareHash = bin2hex(random_bytes(16));
            }
            $status = in_array($p['plan_status'] ?? '', ['entwurf','aktiv','einzelprojekt','reporting','abgeschlossen','archiviert'], true)
                ? $p['plan_status'] : 'entwurf';

            $newId = (int) $this->db->insert('pp_plans', [
                'customer_id' => $customerId,
                'title' => $title,
                'period_from' => !empty($p['period_from']) ? $p['period_from'] : null,
                'period_to' => !empty($p['period_to']) ? $p['period_to'] : null,
                'quarter' => $p['quarter'] ?? null,
                'plan_status' => $status,
                'asana_project_gid' => $p['asana_project_gid'] ?? null,
                'asana_section_gid' => $p['asana_section_gid'] ?? null,
                'share_hash' => $shareHash,
                'state' => (int) ($p['state'] ?? 1),
                'created_by' => $userId,
            ]);
            $this->planMap[$oldId] = $newId;
            $this->log[] = "Plan NEU: #$newId '$title'";

            // Rows
            foreach ($p['rows'] ?? [] as $r) {
                $oldRowId = (int) ($r['id'] ?? 0);
                $rowType = in_array($r['type'] ?? '', ['item','section','note','spacer'], true) ? $r['type'] : 'item';
                $newRowId = (int) $this->db->insert('pp_plan_rows', [
                    'plan_id' => $newId,
                    'row_type' => $rowType,
                    'description' => $r['description'] ?? '',
                    'date_from' => !empty($r['date_from']) ? $r['date_from'] : null,
                    'date_to' => !empty($r['date_to']) ? $r['date_to'] : null,
                    'timeframe' => $r['timeframe'] ?? null,
                    'ist_hours' => (float) ($r['ist_hours'] ?? 0),
                    'planned_hours' => (float) ($r['planned_hours'] ?? 0),
                    'responsible' => $r['responsible'] ?? null,
                    'lead_responsible' => $r['lead_responsible'] ?? null,
                    'deadline' => $r['deadline'] ?? null,
                    'is_done' => (int) ($r['is_done'] ?? 0),
                    'is_placeholder' => (int) ($r['is_placeholder'] ?? 0),
                    'is_focus' => (int) ($r['is_focus'] ?? 0),
                    'no_ticket' => (int) ($r['no_ticket'] ?? 0),
                    'actual_hours' => $r['actual_hours'] ?? null,
                    'notes' => $r['notes'] ?? null,
                    'asana_gid' => $r['asana_gid'] ?? null,
                    'asana_url' => $r['asana_url'] ?? null,
                    'asana_task_name' => $r['asana_task_name'] ?? null,
                    'position' => (int) ($r['position'] ?? 0),
                ]);
                if ($oldRowId) $this->rowMap[$oldRowId] = $newRowId;
            }

            // Plan-Level Budget-Overrides
            foreach ($p['budget_overrides'] ?? [] as $bo) {
                $this->db->insert('pp_plan_budget', [
                    'plan_id' => $newId,
                    'year' => (int) $bo['year'],
                    'month' => (int) $bo['month'],
                    'soll_ts' => (float) ($bo['soll_ts'] ?? 0),
                ]);
            }

            // Feedback
            foreach ($p['feedback'] ?? [] as $fb) {
                $oldRowId = (int) ($fb['row_id'] ?? 0);
                $newRowId = $oldRowId && isset($this->rowMap[$oldRowId]) ? $this->rowMap[$oldRowId] : null;
                $this->db->insert('pp_plan_feedback', [
                    'plan_id' => $newId,
                    'row_id' => $newRowId,
                    'author_name' => $fb['author_name'] ?? 'Anonym',
                    'feedback_type' => in_array($fb['feedback_type'] ?? '', ['like','dislike','comment'], true) ? $fb['feedback_type'] : 'comment',
                    'message' => $fb['message'] ?? null,
                    'created_at' => $fb['created'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            // Revisionen
            foreach ($p['revisions'] ?? [] as $rev) {
                $this->db->insert('pp_plan_revisions', [
                    'plan_id' => $newId,
                    'user_id' => $userId,
                    'snapshot' => $rev['snapshot'] ?? '{}',
                    'label' => $rev['label'] ?? null,
                    'created_at' => $rev['created'] ?? date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    // ===== Customer Budgets =====

    private function importCustomerBudgets(array $budgets): void
    {
        foreach ($budgets as $b) {
            $oldCustomerId = (int) ($b['client_id'] ?? 0);
            $newCustomerId = $this->customerMap[$oldCustomerId] ?? null;
            if (!$newCustomerId) continue;
            try {
                $this->db->insert('pp_customer_budget', [
                    'customer_id' => $newCustomerId,
                    'year' => (int) $b['year'],
                    'month' => (int) $b['month'],
                    'soll_ts' => (float) ($b['soll_ts'] ?? 0),
                    'ist_override' => isset($b['ist_override']) ? (float) $b['ist_override'] : null,
                    'ist_note' => $b['ist_note'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // Duplicate (gleicher customer/year/month) → updaten statt fehlschlagen
                $this->db->update('pp_customer_budget',
                    [
                        'soll_ts' => (float) ($b['soll_ts'] ?? 0),
                        'ist_override' => isset($b['ist_override']) ? (float) $b['ist_override'] : null,
                        'ist_note' => $b['ist_note'] ?? null,
                    ],
                    'customer_id = ? AND year = ? AND month = ?',
                    [$newCustomerId, (int) $b['year'], (int) $b['month']]
                );
            }
        }
    }

    // ===== Person Shares =====

    private function importPersonShares(array $shares, int $userId): void
    {
        foreach ($shares as $s) {
            $name = trim((string) ($s['person_name'] ?? ''));
            $hash = $s['share_hash'] ?? '';
            if (!$name || !$hash) continue;
            $exists = $this->db->queryOne("SELECT id FROM pp_person_shares WHERE share_hash = ?", [$hash]);
            if ($exists) $hash = bin2hex(random_bytes(16));
            try {
                $this->db->insert('pp_person_shares', [
                    'person_name' => $name,
                    'share_hash' => $hash,
                    'created_by' => $userId,
                ]);
            } catch (\Throwable $e) { /* skip duplicates */ }
        }
    }

    // ===== Helpers =====

    private function slugify(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        $s = mb_strtolower($s);
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim((string) $s, '-');
    }

    private function ensureUniqueSlug(string $base): string
    {
        if ($base === '') $base = 'kunde-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $slug = $base;
        $i = 1;
        while ($this->db->queryOne("SELECT id FROM customers WHERE slug = ?", [$slug])) {
            $i++;
            $slug = $base . '-' . $i;
        }
        return $slug;
    }
}
