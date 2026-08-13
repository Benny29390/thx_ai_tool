<?php
namespace Services;

use Core\Database;

/**
 * PpBudgetService — Soll/Ist-Budget pro Kunde, mit TS-Rundung und Forecast.
 *
 * 1 Tagessatz (TS) = 8 Stunden. Anzeige für Kunden wird kundenfreundlich gerundet:
 *   - Math.floor(h/8) volle Tage
 *   - Rest < 4h → abrunden
 *   - Rest >= 4h → +0.5 TS
 */
class PpBudgetService
{
    public const HOURS_PER_TS = 8;
    public const ALLOWED_MODES = ['monthly', 'bimonthly', 'quarterly', 'halfyear', 'yearly'];
    public const BILLING_MODELS = [
        'fix_monatlich'      => ['label' => 'Fester Retainer · monatlich',     'cycle' => 1],
        'fix_bimonatlich'    => ['label' => 'Fester Retainer · 2-monatlich',   'cycle' => 2],
        'fix_quartalsweise'  => ['label' => 'Fester Retainer · quartalsweise', 'cycle' => 3],
        'zuruf_monat'        => ['label' => 'Auf Zuruf · monatlich',           'cycle' => 1],
        'zuruf_quartal'      => ['label' => 'Auf Zuruf · quartalsweise',       'cycle' => 3],
        'einzelprojekt'      => ['label' => 'Einzelprojekt',                   'cycle' => 0],
    ];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** TS aus Stunden mit kundenspezifischer Konvertierung (default 8 Std/TS).
     *  Volle Tage + 0.5 wenn Rest >= halbe Spanne. */
    public function hoursToTs(float $hours, float $hoursPerTs = self::HOURS_PER_TS): float
    {
        if ($hours <= 0) return 0;
        $hoursPerTs = $hoursPerTs > 0 ? $hoursPerTs : self::HOURS_PER_TS;
        $fullDays = floor($hours / $hoursPerTs);
        $remainder = $hours - ($fullDays * $hoursPerTs);
        if ($remainder >= $hoursPerTs / 2) return $fullDays + 0.5;
        return $fullDays;
    }

    public function tsToHours(float $ts, float $hoursPerTs = self::HOURS_PER_TS): float
    {
        return $ts * ($hoursPerTs > 0 ? $hoursPerTs : self::HOURS_PER_TS);
    }

    /**
     * Budget-Soll für einen Plan-Zeitraum. Summiert die Monats-Sollwerte
     * (pp_customer_budget.soll_ts) aller Monate, die der Plan abdeckt.
     * Pläne ohne Kunde oder ohne Zeitraum liefern 0.
     */
    public function getPlanBudgetSoll(int $planId): array
    {
        $plan = $this->db->queryOne(
            "SELECT customer_id, period_from, period_to, plan_status, plan_typ, offer_ts FROM pp_plans WHERE id = ?",
            [$planId]
        );
        $result = [
            'soll_ts' => 0.0, 'soll_h' => 0.0, 'hours_per_ts' => self::HOURS_PER_TS, 'months' => 0,
            'carryover_ts' => 0.0, 'carryover_h' => 0.0,
            'is_einzelprojekt' => false, 'offer_ts' => null,
        ];
        if (!$plan || empty($plan['customer_id'])) {
            return $result;
        }

        // Customer-Konfig fuer Cycle + hours_per_ts + Vorjahres-Uebertrag
        $cust = $this->db->queryOne(
            "SELECT billing_model, hours_per_ts, uebertrag_ts FROM customers WHERE id = ?",
            [(int) $plan['customer_id']]
        ) ?: [];
        $hoursPerTs = (float) ($cust['hours_per_ts'] ?? self::HOURS_PER_TS);
        if ($hoursPerTs <= 0) $hoursPerTs = self::HOURS_PER_TS;

        // === Einzelprojekt-Sonderfall ===
        // plan_typ='einzelprojekt' → offer_ts ist das Gesamt-Soll, keine Monatsverteilung,
        // kein Carryover. plan_typ ist entkoppelt vom Workflow-Status (plan_status), damit
        // archivierte Einzelprojekte ihre Typ-Information behalten.
        // Backwards-compat: alte plan_status='einzelprojekt'-Markierung greift weiterhin.
        $istEinzelprojekt = ($plan['plan_typ'] ?? '') === 'einzelprojekt'
                         || ($plan['plan_status'] ?? '') === 'einzelprojekt';
        if ($istEinzelprojekt) {
            $offerTs = $plan['offer_ts'] !== null ? (float) $plan['offer_ts'] : 0.0;
            $result['soll_ts']      = $offerTs;
            $result['soll_h']       = $offerTs * $hoursPerTs;
            $result['hours_per_ts'] = $hoursPerTs;
            $result['is_einzelprojekt'] = true;
            $result['offer_ts']     = $offerTs;
            return $result;
        }

        // === Regulaerer (monatlicher) Plan ===
        if (empty($plan['period_from']) || empty($plan['period_to'])) {
            return $result;
        }
        $from = new \DateTime($plan['period_from']);
        $to = new \DateTime($plan['period_to']);
        if ($from > $to) return $result;
        $modelCycle = [
            'fix_monatlich' => 1, 'fix_bimonatlich' => 2, 'fix_quartalsweise' => 3,
            'zuruf_monat' => 1, 'zuruf_quartal' => 3, 'einzelprojekt' => 1,
        ];
        $cycle = $modelCycle[$cust['billing_model'] ?? ''] ?? 3;

        $rows = $this->db->query(
            "SELECT year, month, soll_ts FROM pp_customer_budget
             WHERE customer_id = ?
               AND (year * 100 + month) BETWEEN ? AND ?",
            [
                (int) $plan['customer_id'],
                (int) $from->format('Y') * 100 + (int) $from->format('n'),
                (int) $to->format('Y') * 100 + (int) $to->format('n'),
            ]
        ) ?: [];
        $sumTs = 0.0;
        foreach ($rows as $r) $sumTs += (float) $r['soll_ts'];

        $cursor = new \DateTime($from->format('Y-m-01'));
        $end = new \DateTime($to->format('Y-m-01'));
        $months = 0;
        while ($cursor <= $end) { $months++; $cursor->modify('+1 month'); }

        // Carryover: Summe (ist_ts − abg_ts) ueber alle Perioden, die VOR plan.period_from
        // VOLLSTÄNDIG abgerechnet wurden — plus der Vorjahres-Übertrag (uebertrag_ts).
        // Eine halb-abgerechnete laufende Periode darf NICHT zählen.
        $year = (int) $from->format('Y');
        $startMonth = (int) $from->format('n');
        $b = $this->getCustomerBudget((int) $plan['customer_id'], $year);
        // Vorjahres-Übertrag als Startwert (+ = unsere Gunst, − = Kunde-Vorschuss)
        $carryover = (float) ($cust['uebertrag_ts'] ?? 0);
        for ($p = 1; $p <= 12; $p += $cycle) {
            $pEnd = min($p + $cycle - 1, 12);
            if ($pEnd >= $startMonth) break; // Periode reicht in/nach Plan-Start hinein
            $periodAbg = 0.0; $allMonthsAbg = true;
            // Anker (= erster Monat) bestimmt ist_ts: Override oder hoursToTs(period_h)
            $istHTotal = 0.0; $anchorOverride = null;
            for ($m = $p; $m <= $pEnd; $m++) {
                $mi = $b['months'][$m - 1] ?? null;
                if (!$mi) { $allMonthsAbg = false; continue; }
                if ($mi['abgerechnet_ts'] !== null) {
                    $periodAbg += (float) $mi['abgerechnet_ts'];
                } else {
                    $allMonthsAbg = false;
                }
                $istHTotal += (float) ($mi['ist_h'] ?? 0);
                if ($m === $p && $mi['ist_ts_override'] !== null) $anchorOverride = (float) $mi['ist_ts_override'];
            }
            if (!$allMonthsAbg) continue; // Periode nicht vollstaendig reportet → ignorieren
            // 0,25h-Snap
            $istHTotal = round($istHTotal * 4) / 4;
            $periodIstTs = $anchorOverride !== null ? $anchorOverride : $this->hoursToTs($istHTotal, $hoursPerTs);
            $carryover += ($periodIstTs - $periodAbg);
        }

        $result['soll_ts'] = $sumTs;
        $result['soll_h'] = $sumTs * $hoursPerTs;
        $result['hours_per_ts'] = $hoursPerTs;
        $result['months'] = $months;
        $result['carryover_ts'] = $carryover;
        $result['carryover_h'] = $carryover * $hoursPerTs;
        return $result;
    }

    /**
     * Einzelprojekt-Bilanz für einen einzelnen Plan.
     * Liefert Soll (offer_ts), Ist (Summe ist_hours aus pp_plan_rows), Abgerechnet (manueller Wert),
     * Differenz und Stundenumrechnung. Wird vom Plan-spezifischen Abrechnungs-Modal genutzt.
     */
    public function getEinzelprojektAbrechnung(int $planId): array
    {
        $plan = $this->db->queryOne(
            "SELECT p.id, p.title, p.customer_id, p.offer_ts, p.abgerechnet_ts, p.abgerechnet_am, p.abrechnung_notiz,
                    p.plan_status, p.plan_typ, p.period_from, p.period_to,
                    c.hours_per_ts, c.hours_per_ts_max, c.name AS customer_name
             FROM pp_plans p
             LEFT JOIN customers c ON c.id = p.customer_id
             WHERE p.id = ?",
            [$planId]
        );
        if (!$plan) {
            return ['error' => 'Plan nicht gefunden'];
        }
        $hoursPerTs    = (float) ($plan['hours_per_ts']     ?? self::HOURS_PER_TS) ?: self::HOURS_PER_TS;
        $hoursPerTsMax = (float) ($plan['hours_per_ts_max'] ?? $hoursPerTs)        ?: $hoursPerTs;

        // Ist-Stunden = Summe aller ist_hours der Plan-Items (außer Placeholder)
        $istH = (float) $this->db->queryValue(
            "SELECT COALESCE(SUM(ist_hours), 0) FROM pp_plan_rows
             WHERE plan_id = ? AND row_type = 'item' AND is_placeholder = 0",
            [$planId]
        );
        $istH = round($istH, 2);
        $istTs       = $this->hoursToTs($istH, $hoursPerTs);    // kulante TS (8 h/TS)
        $istTsKulanz = $this->hoursToTs($istH, $hoursPerTsMax); // strenger (10 h/TS) für „Kulanz-Reserve"

        $sollTs = $plan['offer_ts'] !== null ? (float) $plan['offer_ts'] : 0.0;
        $abgTs  = $plan['abgerechnet_ts'] !== null ? (float) $plan['abgerechnet_ts'] : null;

        return [
            'plan_id'           => (int) $plan['id'],
            'plan_title'        => $plan['title'],
            'customer_id'       => $plan['customer_id'] ? (int) $plan['customer_id'] : null,
            'customer_name'     => $plan['customer_name'] ?? null,
            'plan_status'       => $plan['plan_status'],
            'plan_typ'          => $plan['plan_typ'],
            'period_from'       => $plan['period_from'],
            'period_to'         => $plan['period_to'],
            'hours_per_ts'      => $hoursPerTs,
            'hours_per_ts_max'  => $hoursPerTsMax,
            'soll_ts'           => $sollTs,
            'soll_h'            => round($sollTs * $hoursPerTs, 2),
            'ist_h'             => $istH,
            'ist_ts'            => $istTs,
            'ist_ts_kulanz'     => $istTsKulanz,
            'abgerechnet_ts'    => $abgTs,
            'abgerechnet_am'    => $plan['abgerechnet_am'],
            'abrechnung_notiz'  => $plan['abrechnung_notiz'],
            'diff_ts'           => $abgTs !== null ? round($istTs - $abgTs, 2) : null,
            'restbudget_ts'     => $sollTs > 0 ? round($sollTs - $istTs, 2) : null,
        ];
    }

    /**
     * Hauptmethode: Kunden-Budget-Übersicht für ein Jahr.
     * Liefert Konfig + 12 Monate × {soll_ts, abgerechnet_ts, ist_h, ist_ts, bemerkung}
     * sowie Quartalssummen und Jahres-Saldo.
     */
    public function getCustomerBudget(int $customerId, int $year): array
    {
        $customer = $this->db->queryOne(
            "SELECT id, name, slug, hex_color, abbreviation, logo_path, stundensatz,
                    uebertrag_ts, uebertrag_notiz, abrechnungsmodus,
                    billing_model, ts_per_month, hours_per_ts, hours_per_ts_max, billing_notes,
                    main_contact_name, main_contact_role, main_contact_email
             FROM customers WHERE id = ?",
            [$customerId]
        );
        if (!$customer) throw new \RuntimeException('Kunde nicht gefunden');

        $hoursPerTs = (float) ($customer['hours_per_ts'] ?? self::HOURS_PER_TS);
        if ($hoursPerTs <= 0) $hoursPerTs = self::HOURS_PER_TS;

        // Default-Abrechnungsmodus aus dem Kunden-Modell (für Monate ohne expliziten billing_mode).
        $defaultMode = in_array($customer['billing_model'] ?? '', ['zuruf_monat', 'zuruf_quartal'], true) ? 'zuruf' : 'retainer';

        // Alle 12 Monats-Daten aus pp_customer_budget
        $budgetRows = $this->db->query(
            "SELECT month, soll_ts, billing_mode, abgerechnet_ts, ist_override, ist_ts_override, ist_note, bemerkung
             FROM pp_customer_budget WHERE customer_id = ? AND year = ?",
            [$customerId, $year]
        ) ?: [];
        $budgetByMonth = [];
        foreach ($budgetRows as $b) $budgetByMonth[(int) $b['month']] = $b;

        // Ist-Stunden aus allen Plan-Zeilen des Kunden, auf Monate verteilt
        $istByMonth = $this->calcIstHoursByMonth($customerId, $year);

        $months = [];
        $totalSollTs = 0; $totalAbgTs = 0; $totalIstH = 0; $totalIstTs = 0;
        $now = new \DateTime();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');

        for ($m = 1; $m <= 12; $m++) {
            $b = $budgetByMonth[$m] ?? null;
            $sollTs = (float) ($b['soll_ts'] ?? 0);
            $sollH = $sollTs * $hoursPerTs;
            $abgTs = $b !== null && $b['abgerechnet_ts'] !== null ? (float) $b['abgerechnet_ts'] : null;
            $istCalc = (float) ($istByMonth[$m] ?? 0);
            $istManual = $b !== null && $b['ist_override'] !== null ? (float) $b['ist_override'] : null;
            $istH = $istManual !== null ? $istManual : $istCalc;
            // Ist-TS: manueller Override gewinnt, sonst aus h kulant umgerechnet
            $istTsOverride = $b !== null && $b['ist_ts_override'] !== null ? (float) $b['ist_ts_override'] : null;
            $istTs = $istTsOverride !== null ? $istTsOverride : $this->hoursToTs($istH, $hoursPerTs);

            $isFuture = ($year > $currentYear) || ($year === $currentYear && $m > $currentMonth);
            $diffH = $isFuture ? 0 : ($istH - $sollH);
            $diffTs = $isFuture ? 0 : ($istTs - $sollTs);

            $months[] = [
                'month'           => $m,
                'quarter'         => (int) ceil($m / 3),
                'mode'            => ($b !== null && !empty($b['billing_mode'])) ? $b['billing_mode'] : $defaultMode,
                'soll_ts'         => $sollTs,
                'soll_h'          => round($sollH, 2),
                'abgerechnet_ts'  => $abgTs,                  // null = noch nicht erfasst
                'ist_h'           => round($istH, 2),
                'ist_calc'        => round($istCalc, 2),
                'ist_manual'      => $istManual,
                'ist_ts'          => round($istTs, 2),
                'ist_ts_override' => $istTsOverride,           // null = berechnet
                'ist_note'        => $b['ist_note'] ?? null,
                'bemerkung'       => $b['bemerkung'] ?? null,
                'diff_h'          => round($diffH, 2),
                'diff_ts'         => round($diffTs, 2),
                'is_future'       => $isFuture,
            ];
            $totalSollTs += $sollTs;
            if ($abgTs !== null) $totalAbgTs += $abgTs;
            if (!$isFuture) { $totalIstH += $istH; $totalIstTs += $istTs; }
        }

        // Quartals-Aggregate fuer die UI-Anzeige (Q1=Mon 1-3, Q2=4-6 etc.)
        $quarters = [];
        for ($q = 1; $q <= 4; $q++) {
            $qMonths = array_slice($months, ($q - 1) * 3, 3);
            $qSoll = array_sum(array_column($qMonths, 'soll_ts'));
            $qAbg  = 0; $qAbgHas = false;
            foreach ($qMonths as $mm) { if ($mm['abgerechnet_ts'] !== null) { $qAbg += $mm['abgerechnet_ts']; $qAbgHas = true; } }
            $qIstH = array_sum(array_column($qMonths, 'ist_h'));
            $qIstTs = array_sum(array_column($qMonths, 'ist_ts'));
            $quarters[] = [
                'quarter'        => $q,
                'mode'           => $qMonths[0]['mode'] ?? $defaultMode,
                'soll_ts'        => $qSoll,
                'abgerechnet_ts' => $qAbgHas ? $qAbg : null,
                'ist_h'          => round($qIstH, 2),
                'ist_ts'         => round($qIstTs, 2),
            ];
        }

        $totalSollH = $totalSollTs * $hoursPerTs;
        return [
            'customer'      => $customer,
            'months'        => $months,
            'quarters'      => $quarters,
            'total_all'     => [
                'soll_ts'        => $totalSollTs,
                'soll_h'         => round($totalSollH, 2),
                'abgerechnet_ts' => $totalAbgTs,
                'ist_h'          => round($totalIstH, 2),
                'ist_ts'         => round($totalIstTs, 2),
                'diff_h'         => round($totalIstH - $totalSollH, 2),
                'diff_ts'        => round($totalIstTs - $totalSollTs, 2),
                'saldo_ts'       => round($totalAbgTs - $totalIstTs, 2),  // + = Thoxan hat noch was zu liefern
            ],
            'hours_per_ts'  => $hoursPerTs,
            'hours_per_ts_max' => (float) ($customer['hours_per_ts_max'] ?? 10),
            'billing_models' => self::BILLING_MODELS,
            'years'         => $this->availableYears($customerId),
            'year'          => $year,
            'customer_id'   => $customerId,
            // Einzelprojekt-Pläne immer mitliefern — UI entscheidet, ob sie als
            // Haupt-Layout (bei billing_model='einzelprojekt') oder als Beiwerk
            // angezeigt werden.
            'einzelprojekt_plans' => $this->getEinzelprojektPlans($customerId, $year, $hoursPerTs),
        ];
    }

    /**
     * Liefert alle Einzelprojekt-Pläne eines Kunden mit aggregierten Stats
     * (offer_ts, planned_h, ist_h, spielraum_h). Filtert auf Pläne, deren
     * Zeitraum das gegebene Jahr beruehrt.
     */
    public function getEinzelprojektPlans(int $customerId, int $year, ?float $hoursPerTs = null): array
    {
        if ($hoursPerTs === null || $hoursPerTs <= 0) $hoursPerTs = self::HOURS_PER_TS;
        $plans = $this->db->query(
            "SELECT p.id, p.title, p.period_from, p.period_to, p.offer_ts, p.plan_status, p.state
             FROM pp_plans p
             WHERE p.customer_id = ? AND p.state = 1 AND p.plan_status = 'einzelprojekt'
               AND ((p.period_from IS NULL AND YEAR(p.created_at) = ?)
                    OR (YEAR(p.period_from) <= ? AND (p.period_to IS NULL OR YEAR(p.period_to) >= ?)))
             ORDER BY p.period_from DESC, p.id DESC",
            [$customerId, $year, $year, $year]
        ) ?: [];

        $out = [];
        foreach ($plans as $p) {
            $stats = $this->db->queryOne(
                "SELECT COALESCE(SUM(r.ist_hours), 0) AS ist_h,
                        COALESCE(SUM(r.planned_hours), 0) AS planned_h
                 FROM pp_plan_rows r
                 WHERE r.plan_id = ? AND r.row_type = 'item' AND r.is_placeholder = 0",
                [(int) $p['id']]
            ) ?: ['ist_h' => 0, 'planned_h' => 0];
            $offerTs = $p['offer_ts'] !== null ? (float) $p['offer_ts'] : 0.0;
            $offerH  = $offerTs * $hoursPerTs;
            $istH    = (float) $stats['ist_h'];
            $plannedH = (float) $stats['planned_h'];
            $spielraumH = $offerH > 0 ? ($plannedH - $offerH) : 0;
            $out[] = [
                'id'          => (int) $p['id'],
                'title'       => $p['title'],
                'period_from' => $p['period_from'],
                'period_to'   => $p['period_to'],
                'offer_ts'    => $offerTs,
                'offer_h'     => round($offerH, 2),
                'planned_h'   => round($plannedH, 2),
                'ist_h'       => round($istH, 2),
                'spielraum_h' => round($spielraumH, 2),
                'plan_url'    => '/admin/projektplanner/plan/' . (int) $p['id'],
            ];
        }
        return $out;
    }

    /**
     * Kunden-Cockpit: Übersicht aller Kunden, die in $year ein Budget gepflegt haben ODER aktive Pläne besitzen.
     * Liefert pro Kunde: Soll-Stunden YTD/Year, Ist-Stunden YTD, Diff, Auslastungs-%, monatlicher Mittelwert, Restlaufzeit + Status.
     */
    public function getCustomersOverview(int $year): array
    {
        // Kandidaten: alle Kunden mit Budget-Eintrag im Jahr ODER Plänen im Zeitraum des Jahres
        $custIds = $this->db->query(
            "SELECT DISTINCT id FROM customers c
             WHERE EXISTS (SELECT 1 FROM pp_customer_budget b WHERE b.customer_id = c.id AND b.year = ?)
                OR EXISTS (SELECT 1 FROM pp_plans p WHERE p.customer_id = c.id AND p.state = 1
                           AND (YEAR(COALESCE(p.period_from, p.created_at)) <= ? AND YEAR(COALESCE(p.period_to, p.created_at)) >= ?))
             ORDER BY id",
            [$year, $year, $year]
        ) ?: [];

        $now = new \DateTime();
        $currentYear  = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');
        $monthsElapsed = ($year < $currentYear) ? 12 : (($year > $currentYear) ? 0 : $currentMonth);
        $monthsRemaining = 12 - $monthsElapsed;

        $list = [];
        foreach ($custIds as $row) {
            $cid = (int) $row['id'];
            $cust = $this->db->queryOne(
                "SELECT id, name, hex_color, abbreviation FROM customers WHERE id = ?",
                [$cid]
            );
            if (!$cust) continue;

            // Soll-TS aus pp_customer_budget (Jahres-Summe)
            $sollTs = (float) ($this->db->queryValue(
                "SELECT COALESCE(SUM(soll_ts), 0) FROM pp_customer_budget WHERE customer_id = ? AND year = ?",
                [$cid, $year]
            ) ?? 0);
            $sollH = $sollTs * self::HOURS_PER_TS;

            // Ist-Stunden YTD: Summe aus calcIstHoursByMonth bis aktueller Monat (oder bis 12 falls Vorjahr)
            $istByMonth = $this->calcIstHoursByMonth($cid, $year);
            $istYtd = 0;
            for ($m = 1; $m <= $monthsElapsed; $m++) {
                $istYtd += (float) ($istByMonth[$m] ?? 0);
            }
            $istFull = array_sum($istByMonth);

            // Geplant (offen + erledigt) aus aktiven Plänen
            $plannedH = (float) ($this->db->queryValue(
                "SELECT COALESCE(SUM(r.planned_hours), 0)
                 FROM pp_plan_rows r
                 JOIN pp_plans p ON p.id = r.plan_id
                 WHERE p.customer_id = ? AND p.state = 1 AND r.row_type = 'item' AND r.is_placeholder = 0
                   AND ((p.period_from IS NOT NULL AND YEAR(p.period_from) <= ? AND (p.period_to IS NULL OR YEAR(p.period_to) >= ?))
                        OR (p.period_from IS NULL AND YEAR(p.created_at) = ?))",
                [$cid, $year, $year, $year]
            ) ?? 0);

            $planCount = (int) ($this->db->queryValue(
                "SELECT COUNT(*) FROM pp_plans WHERE customer_id = ? AND state = 1
                 AND ((period_from IS NOT NULL AND YEAR(period_from) <= ? AND (period_to IS NULL OR YEAR(period_to) >= ?))
                      OR (period_from IS NULL AND YEAR(created_at) = ?))",
                [$cid, $year, $year, $year]
            ) ?? 0);

            // Auslastung + Status
            $usagePct  = $sollH > 0 ? round(($istYtd / $sollH) * 100, 1) : null;
            $remainingH = $sollH - $istYtd;
            // Bedarfs-Check für Restjahr: ist Geplant + bereits verbraucht > Soll?
            $forecastH = $istYtd + max(0, $plannedH - $istFull); // grobe Schätzung
            $forecastOver = $sollH > 0 ? ($forecastH > $sollH) : false;

            $status = 'ok';
            if ($sollH <= 0) $status = 'no-budget';
            elseif ($istYtd > $sollH) $status = 'over';
            elseif ($forecastOver) $status = 'risk';
            elseif ($monthsElapsed > 0 && $usagePct < ($monthsElapsed / 12 * 100 - 20)) $status = 'under';

            $list[] = [
                'customer_id'      => $cid,
                'name'             => $cust['name'],
                'abbreviation'     => $cust['abbreviation'],
                'color'            => $cust['hex_color'] ?: '#94a3b8',
                'plan_count'       => $planCount,
                'soll_ts'          => $sollTs,
                'soll_h'           => round($sollH, 1),
                'ist_h_ytd'        => round($istYtd, 1),
                'ist_h_full'       => round($istFull, 1),
                'planned_h'        => round($plannedH, 1),
                'remaining_h'      => round($remainingH, 1),
                'usage_pct'        => $usagePct,
                'months_elapsed'   => $monthsElapsed,
                'months_remaining' => $monthsRemaining,
                'monthly_avg_h'    => $monthsElapsed > 0 ? round($istYtd / $monthsElapsed, 1) : 0,
                'status'           => $status,  // ok | over | risk | under | no-budget
            ];
        }

        // Sortierung: erst Risiken oben, dann nach Soll-h absteigend
        $statusRank = ['over' => 0, 'risk' => 1, 'under' => 2, 'ok' => 3, 'no-budget' => 4];
        usort($list, function ($a, $b) use ($statusRank) {
            $sa = $statusRank[$a['status']] ?? 9;
            $sb = $statusRank[$b['status']] ?? 9;
            if ($sa !== $sb) return $sa - $sb;
            return ($b['soll_h'] <=> $a['soll_h']);
        });

        return [
            'year'             => $year,
            'months_elapsed'   => $monthsElapsed,
            'months_remaining' => $monthsRemaining,
            'customers'        => $list,
        ];
    }

    /**
     * Berechnet Ist-Stunden pro Monat: summiert ist_hours aller Plan-Zeilen des Kunden,
     * proportional auf die Plan-Zeitraum-Monate verteilt.
     */
    private function calcIstHoursByMonth(int $customerId, int $year): array
    {
        // Konsistent mit ppCalcStats im JS-Widget: Placeholder ausgeschlossen,
        // "Kein-Ticket"-Zeilen mitgezaehlt (sind reale Arbeit ohne Asana-Ticket).
        // WICHTIG: Einzelprojekte werden ausgeschlossen — die haben ihr eigenes Budget
        // (offer_ts) und ihre eigene Bilanz; ihre Stunden würden sonst die laufende
        // Kunden-Quartalsbilanz verfälschen.
        $rows = $this->db->query(
            "SELECT p.period_from, p.period_to,
                    COALESCE(SUM(r.ist_hours), 0) AS sum_ist
             FROM pp_plans p
             LEFT JOIN pp_plan_rows r ON r.plan_id = p.id AND r.row_type = 'item' AND r.is_placeholder = 0
             WHERE p.customer_id = ?
               AND (p.plan_typ IS NULL OR p.plan_typ != 'einzelprojekt')
               AND (p.plan_status IS NULL OR p.plan_status != 'einzelprojekt')
             GROUP BY p.id, p.period_from, p.period_to",
            [$customerId]
        ) ?: [];

        $byMonth = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $sum = (float) $r['sum_ist'];
            if ($sum <= 0) continue;
            if (empty($r['period_from']) || empty($r['period_to'])) {
                // Kein Zeitraum → alles in aktuellen Monat
                $month = (int) date('m');
                $byMonth[$month] += $sum;
                continue;
            }
            $from = new \DateTime($r['period_from']);
            $to = new \DateTime($r['period_to']);
            // Bestimme die Monate des Plans, die in $year fallen
            $planMonths = [];
            $cursor = clone $from;
            $cursor->modify('first day of this month');
            while ($cursor <= $to) {
                if ((int) $cursor->format('Y') === $year) {
                    $planMonths[] = (int) $cursor->format('m');
                }
                $cursor->modify('+1 month');
            }
            if (empty($planMonths)) continue;
            $perMonth = $sum / count($planMonths);
            foreach ($planMonths as $m) $byMonth[$m] += $perMonth;
        }
        return $byMonth;
    }

    private function availableYears(int $customerId): array
    {
        $years = $this->db->query(
            "SELECT DISTINCT year FROM pp_customer_budget WHERE customer_id = ? ORDER BY year DESC",
            [$customerId]
        ) ?: [];
        $list = array_map(fn($r) => (int) $r['year'], $years);
        $current = (int) date('Y');
        if (!in_array($current, $list, true)) $list[] = $current;
        if (!in_array($current + 1, $list, true)) $list[] = $current + 1;
        sort($list);
        return $list;
    }

    /**
     * Speichert ein einzelnes Monats-Soll. Insert oder Update.
     */
    public function saveCustomerMonth(int $customerId, int $year, int $month, float $sollTs): void
    {
        $existing = $this->db->queryOne(
            "SELECT id FROM pp_customer_budget WHERE customer_id = ? AND year = ? AND month = ?",
            [$customerId, $year, $month]
        );
        if ($existing) {
            $this->db->update('pp_customer_budget', ['soll_ts' => $sollTs], 'id = ?', [(int) $existing['id']]);
        } else {
            $this->db->insert('pp_customer_budget', [
                'customer_id' => $customerId,
                'year' => $year,
                'month' => $month,
                'soll_ts' => $sollTs,
            ]);
        }
    }

    /** Abrechnungs-Modus für alle drei Monate eines Quartals setzen (gemischte Projekte). */
    public function saveQuarterMode(int $customerId, int $year, int $quarter, string $mode): void
    {
        if ($quarter < 1 || $quarter > 4) throw new \RuntimeException('Ungültiges Quartal.');
        if (!in_array($mode, ['retainer', 'zuruf'], true)) throw new \RuntimeException('Ungültiger Modus.');
        $startMonth = ($quarter - 1) * 3 + 1;
        for ($m = $startMonth; $m < $startMonth + 3; $m++) {
            $existing = $this->db->queryOne(
                "SELECT id FROM pp_customer_budget WHERE customer_id = ? AND year = ? AND month = ?",
                [$customerId, $year, $m]
            );
            if ($existing) {
                $this->db->update('pp_customer_budget', ['billing_mode' => $mode], 'id = ?', [(int) $existing['id']]);
            } else {
                $this->db->insert('pp_customer_budget', [
                    'customer_id' => $customerId, 'year' => $year, 'month' => $m, 'billing_mode' => $mode, 'soll_ts' => 0,
                ]);
            }
        }
    }

    public function saveCustomerMonthBatch(int $customerId, int $year, array $entries): int
    {
        $count = 0;
        foreach ($entries as $e) {
            $m = (int) ($e['month'] ?? 0);
            if ($m < 1 || $m > 12) continue;
            $this->saveCustomerMonth($customerId, $year, $m, (float) ($e['soll_ts'] ?? 0));
            $count++;
        }
        return $count;
    }

    public function saveIstOverride(int $customerId, int $year, int $month, ?float $istOverride, ?string $istNote): void
    {
        $existing = $this->db->queryOne(
            "SELECT id FROM pp_customer_budget WHERE customer_id = ? AND year = ? AND month = ?",
            [$customerId, $year, $month]
        );
        if ($existing) {
            $this->db->update('pp_customer_budget',
                ['ist_override' => $istOverride, 'ist_note' => $istNote],
                'id = ?', [(int) $existing['id']]
            );
        } else {
            $this->db->insert('pp_customer_budget', [
                'customer_id' => $customerId,
                'year' => $year,
                'month' => $month,
                'soll_ts' => 0,
                'ist_override' => $istOverride,
                'ist_note' => $istNote,
            ]);
        }
    }

    public function saveUebertrag(int $customerId, float $uebertragTs, ?string $notiz, string $abrechnungsmodus): void
    {
        if (!in_array($abrechnungsmodus, self::ALLOWED_MODES, true)) {
            $abrechnungsmodus = 'quarterly';
        }
        $this->db->update('customers', [
            'uebertrag_ts' => $uebertragTs,
            'uebertrag_notiz' => $notiz,
            'abrechnungsmodus' => $abrechnungsmodus,
        ], 'id = ?', [$customerId]);
    }

    /**
     * Speichert die Abrechnungs-Konfiguration pro Kunde (Modell, TS/Monat, Std/TS,
     * Übertrag, Notiz). Alle Felder optional — nur was nicht null ist wird gesetzt.
     */
    public function saveCustomerConfig(int $customerId, array $config): void
    {
        $allowed = ['billing_model', 'ts_per_month', 'hours_per_ts', 'hours_per_ts_max',
                    'billing_notes', 'uebertrag_ts', 'uebertrag_notiz', 'abrechnungsmodus'];
        // (Kontakt wird im Kunden-Steckbrief gepflegt, nicht in der Abrechnungs-Lightbox)
        $update = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $config)) continue;
            $v = $config[$k];
            if ($k === 'billing_model') {
                if ($v !== null && !array_key_exists($v, self::BILLING_MODELS)) continue;
            }
            if ($k === 'abrechnungsmodus') {
                if (!in_array($v, self::ALLOWED_MODES, true)) continue;
            }
            $update[$k] = $v;
        }
        if (!empty($update)) {
            $this->db->update('customers', $update, 'id = ?', [$customerId]);
        }
    }

    /** Abgerechnet (TS) pro Monat. null = nicht abgerechnet. */
    public function saveAbgerechnet(int $customerId, int $year, int $month, ?float $abgerechnetTs): void
    {
        $this->upsertMonth($customerId, $year, $month, ['abgerechnet_ts' => $abgerechnetTs]);
    }

    /** Manueller TS-Override fuer Ist (kulante Bewertung). null = automatisch aus Stunden. */
    public function saveIstTsOverride(int $customerId, int $year, int $month, ?float $istTs): void
    {
        $this->upsertMonth($customerId, $year, $month, ['ist_ts_override' => $istTs]);
    }

    /** Bemerkung pro Monat. Leerer String -> null. */
    public function saveBemerkung(int $customerId, int $year, int $month, ?string $bemerkung): void
    {
        $bemerkung = $bemerkung !== null && trim($bemerkung) === '' ? null : $bemerkung;
        $this->upsertMonth($customerId, $year, $month, ['bemerkung' => $bemerkung]);
    }

    /**
     * Quartal-Eingabe: nimmt Quartals-Summen (h und/oder ts) und verteilt sie
     * gleichmaessig auf die 3 Monate. Bestehende Werte werden ueberschrieben.
     * Bei null werden die Werte zurueckgesetzt.
     */
    public function applyQuarterIst(int $customerId, int $year, int $quarter, ?float $totalH, ?float $totalTs): void
    {
        if ($quarter < 1 || $quarter > 4) throw new \InvalidArgumentException('Quartal 1-4');
        $perH  = $totalH  !== null ? round($totalH / 3, 2)  : null;
        $perTs = $totalTs !== null ? round($totalTs / 3, 2) : null;
        for ($i = 0; $i < 3; $i++) {
            $m = ($quarter - 1) * 3 + $i + 1;
            $this->upsertMonth($customerId, $year, $m, [
                'ist_override'    => $perH,
                'ist_ts_override' => $perTs,
            ]);
        }
    }

    /**
     * Wendet die Konfiguration eines Kunden auf ein Jahr an: setzt soll_ts in
     * allen 12 Monaten gemaess ts_per_month. Bei billing_model = einzelprojekt
     * oder zuruf_* wird nichts gesetzt (soll bleibt manuell).
     * Nur Monate die noch keinen soll_ts haben oder mit force=true werden gesetzt.
     */
    public function applyDefaultsForYear(int $customerId, int $year, bool $force = false): int
    {
        $customer = $this->db->queryOne(
            "SELECT billing_model, ts_per_month FROM customers WHERE id = ?",
            [$customerId]
        );
        if (!$customer || empty($customer['billing_model']) || $customer['ts_per_month'] === null) return 0;
        $model = $customer['billing_model'];
        if (in_array($model, ['zuruf_quartal', 'zuruf_monat', 'einzelprojekt'], true)) return 0;
        $tsPerMonth = (float) $customer['ts_per_month'];
        $count = 0;
        for ($m = 1; $m <= 12; $m++) {
            $existing = $this->db->queryValue(
                "SELECT soll_ts FROM pp_customer_budget WHERE customer_id = ? AND year = ? AND month = ?",
                [$customerId, $year, $m]
            );
            if ($existing !== null && (float) $existing > 0 && !$force) continue;
            $this->upsertMonth($customerId, $year, $m, ['soll_ts' => $tsPerMonth]);
            $count++;
        }
        return $count;
    }

    /**
     * Cross-Kunden-Matrix fuer das Dashboard (lost die Excel ab).
     * Liefert eine 12-Monats-Matrix aller Kunden mit Budget oder Plaenen im Jahr.
     */
    public function getMatrix(int $year): array
    {
        $custIds = $this->db->query(
            "SELECT DISTINCT id FROM customers c
             WHERE EXISTS (SELECT 1 FROM pp_customer_budget b WHERE b.customer_id = c.id AND b.year = ?)
                OR c.billing_model IS NOT NULL
                OR EXISTS (SELECT 1 FROM pp_plans p WHERE p.customer_id = c.id AND p.state = 1)
             ORDER BY id",
            [$year]
        ) ?: [];

        $list = [];
        foreach ($custIds as $row) {
            $cid = (int) $row['id'];
            $b = $this->getCustomerBudget($cid, $year);
            $cust = $b['customer'];
            $list[] = [
                'customer_id'    => $cid,
                'name'           => $cust['name'],
                'abbreviation'   => $cust['abbreviation'],
                'logo_path'      => $cust['logo_path'] ?? null,
                'color'          => $cust['hex_color'] ?: '#94a3b8',
                'billing_model'  => $cust['billing_model'] ?? null,
                'ts_per_month'   => $cust['ts_per_month'] !== null ? (float) $cust['ts_per_month'] : null,
                'months'         => array_map(fn($m) => [
                    'm'         => $m['month'],
                    'soll'      => $m['soll_ts'],
                    'abg'       => $m['abgerechnet_ts'],
                    'ist'       => $m['ist_ts'],
                ], $b['months']),
                'quarters'       => $b['quarters'],
                'total'          => $b['total_all'],
                'uebertrag_ts'   => (float) ($cust['uebertrag_ts'] ?? 0),
            ];
        }
        return [
            'year'      => $year,
            'customers' => $list,
            'billing_models' => self::BILLING_MODELS,
        ];
    }

    /** Generischer Upsert fuer eine Monatszeile. $data hat Spaltennamen als Keys. */
    private function upsertMonth(int $customerId, int $year, int $month, array $data): void
    {
        if ($month < 1 || $month > 12) throw new \InvalidArgumentException('Monat 1-12');
        $existing = $this->db->queryOne(
            "SELECT id FROM pp_customer_budget WHERE customer_id = ? AND year = ? AND month = ?",
            [$customerId, $year, $month]
        );
        if ($existing) {
            $this->db->update('pp_customer_budget', $data, 'id = ?', [(int) $existing['id']]);
        } else {
            $this->db->insert('pp_customer_budget', array_merge([
                'customer_id' => $customerId,
                'year'        => $year,
                'month'       => $month,
                'soll_ts'     => 0,
            ], $data));
        }
    }
}
