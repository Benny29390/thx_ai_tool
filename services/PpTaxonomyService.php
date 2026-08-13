<?php
namespace Services;

use Core\Database;

/**
 * PpTaxonomyService — Aggregation der typischen Plan-Sektionen pro Kunde.
 *
 * Scannt alle pp_plan_rows (Sektion + Items darunter), normalisiert Sektionsnamen
 * (lowercase + trim + Synonym-Map) und aggregiert pro (customer_id, section_key):
 *  - first_seen / last_seen
 *  - plan_count (in wievielen Plaenen kam die Sektion vor)
 *  - item_count, avg_items_per_plan
 *  - avg_planned_hours, avg_ist_hours
 *  - typical_items (Top 5 haeufigste Item-Anfaenge mit Stundenangaben)
 *
 * Wird vom Cron stuendlich neu aufgebaut (vollstaendiger Rebuild — Tabelle ist klein).
 */
class PpTaxonomyService
{
    private Database $db;

    public function __construct(Database $db) { $this->db = $db; }

    /**
     * Kompletter Rebuild: leert die Tabelle und baut sie aus pp_plan_rows neu auf.
     * Idempotent. Beruecksichtigt nur Plaene mit state=1 UND customer_id.
     */
    public function rebuild(): array
    {
        // Alle Sektion-Rows mit ihren zugehoerigen Plan- + Kundendaten holen
        $sections = $this->db->query(
            "SELECT r.id AS section_row_id, r.description AS section_name, r.position AS section_pos,
                    p.id AS plan_id, p.customer_id, p.period_from
             FROM pp_plan_rows r
             JOIN pp_plans p ON p.id = r.plan_id
             WHERE r.row_type = 'section'
               AND p.state = 1
               AND p.customer_id IS NOT NULL
               AND TRIM(r.description) <> ''
             ORDER BY r.plan_id, r.position"
        ) ?: [];

        // Pro Plan: alle Item-Rows holen, in Sektion-Buckets sortieren
        // (Items gehoeren zur Sektion mit der naechstkleineren position innerhalb desselben plan_id)
        $itemsByPlan = [];
        $items = $this->db->query(
            "SELECT r.id, r.plan_id, r.position, r.description, r.planned_hours, r.ist_hours
             FROM pp_plan_rows r
             JOIN pp_plans p ON p.id = r.plan_id
             WHERE r.row_type = 'item'
               AND p.state = 1
               AND p.customer_id IS NOT NULL
               AND r.is_placeholder = 0
               AND TRIM(r.description) <> ''
             ORDER BY r.plan_id, r.position"
        ) ?: [];
        foreach ($items as $it) $itemsByPlan[$it['plan_id']][] = $it;

        // Aggregator: customer_id -> section_key -> {display_name, plan_ids[], item_descs[], …}
        $agg = [];
        $sectionsByPlan = [];
        foreach ($sections as $s) $sectionsByPlan[$s['plan_id']][] = $s;

        foreach ($sectionsByPlan as $planId => $secs) {
            $planItems = $itemsByPlan[$planId] ?? [];
            foreach ($secs as $i => $s) {
                $startPos = (int) $s['section_pos'];
                $nextPos  = isset($secs[$i + 1]) ? (int) $secs[$i + 1]['section_pos'] : PHP_INT_MAX;

                // Items dieser Sektion: zwischen startPos und nextPos
                $secItems = array_filter($planItems, fn($it) => $it['position'] > $startPos && $it['position'] < $nextPos);

                $key = self::normalizeSectionName($s['section_name']);
                if ($key === '') continue;

                $cid = (int) $s['customer_id'];
                if (!isset($agg[$cid][$key])) {
                    $agg[$cid][$key] = [
                        'display_name' => trim((string) $s['section_name']),
                        'name_freq'    => [], // Map: display_name -> count
                        'plan_ids'     => [],
                        'items_in_plan'=> [], // plan_id -> count
                        'planned_sum'  => 0.0,
                        'ist_sum'      => 0.0,
                        'item_descs'   => [], // 'first 60 chars' -> count
                        'first_seen'   => null,
                        'last_seen'    => null,
                        'item_total'   => 0,
                    ];
                }
                $a = &$agg[$cid][$key];
                $dn = trim((string) $s['section_name']);
                $a['name_freq'][$dn] = ($a['name_freq'][$dn] ?? 0) + 1;
                $a['plan_ids'][$planId] = true;
                $cnt = count($secItems);
                $a['items_in_plan'][$planId] = ($a['items_in_plan'][$planId] ?? 0) + $cnt;
                $a['item_total'] += $cnt;
                foreach ($secItems as $it) {
                    $a['planned_sum'] += (float) $it['planned_hours'];
                    $a['ist_sum']     += (float) $it['ist_hours'];
                    $key2 = mb_substr(trim((string) $it['description']), 0, 80);
                    if ($key2 !== '') {
                        $a['item_descs'][$key2] = ($a['item_descs'][$key2] ?? 0) + 1;
                    }
                }
                $pf = $s['period_from'];
                if ($pf) {
                    if (!$a['first_seen'] || $pf < $a['first_seen']) $a['first_seen'] = $pf;
                    if (!$a['last_seen']  || $pf > $a['last_seen'])  $a['last_seen']  = $pf;
                }
                unset($a);
            }
        }

        // Tabelle leeren
        $this->db->execute('TRUNCATE TABLE pp_section_taxonomy');

        $totalRows = 0;
        foreach ($agg as $cid => $sections) {
            foreach ($sections as $key => $a) {
                $planCount = count($a['plan_ids']);
                if ($planCount === 0) continue;
                // Anzeige-Name = haeufigste Schreibweise
                arsort($a['name_freq']);
                $displayName = (string) array_key_first($a['name_freq']);

                $avgItems  = $planCount > 0 ? $a['item_total'] / $planCount : 0;
                $avgPlan   = $planCount > 0 ? $a['planned_sum'] / $planCount : 0;
                $avgIst    = $planCount > 0 ? $a['ist_sum']     / $planCount : 0;

                arsort($a['item_descs']);
                $typical = array_slice($a['item_descs'], 0, 5, true);

                $this->db->insert('pp_section_taxonomy', [
                    'customer_id' => $cid,
                    'section_key' => $key,
                    'display_name'=> $displayName,
                    'first_seen'  => $a['first_seen'],
                    'last_seen'   => $a['last_seen'],
                    'plan_count'  => $planCount,
                    'item_count'  => $a['item_total'],
                    'avg_items_per_plan' => round($avgItems, 2),
                    'avg_planned_hours'  => round($avgPlan, 2),
                    'avg_ist_hours'      => round($avgIst, 2),
                    'typical_items' => json_encode($typical, JSON_UNESCAPED_UNICODE),
                ]);
                $totalRows++;
            }
        }

        return [
            'customers' => count($agg),
            'taxonomy_rows' => $totalRows,
        ];
    }

    /** Liefert die Taxonomy-Eintraege fuer einen Kunden, sortiert nach Aktualitaet. */
    public function getForCustomer(int $customerId): array
    {
        return $this->db->query(
            "SELECT * FROM pp_section_taxonomy WHERE customer_id = ?
             ORDER BY last_seen DESC, plan_count DESC, display_name ASC",
            [$customerId]
        ) ?: [];
    }

    /** Normalisiert einen Sektionsnamen auf einen Vergleichs-Key. */
    public static function normalizeSectionName(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') return '';
        $s = mb_strtolower($s);
        // Synonyme / Schreibweisen vereinheitlichen
        $s = strtr($s, [
            '&' => 'und',
            '/' => ' und ',
            '–' => '-',
            '—' => '-',
            '„' => '', '"' => '', '"' => '', '"' => '',
        ]);
        $s = preg_replace('/[,;]+/u', ' ', $s);     // Trennzeichen wegradieren
        $s = preg_replace('/[\s\-\.]+/u', ' ', $s); // Whitespace + Bindestriche normalisieren
        $s = trim($s);
        return $s;
    }
}
