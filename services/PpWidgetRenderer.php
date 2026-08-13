<?php
namespace Services;

use Core\Database;

/**
 * Server-side Renderer für die Plan-KPI-Widget-Kachel.
 *
 * Erzeugt dieselbe Optik wie das interaktive Widget in der Projektplanner-Sidebar,
 * aber als statischer Snapshot — wird auf der Kundenseite und im Dashboard
 * eingebettet, beide nur Lese-Sicht. Klick auf die Kachel öffnet den Plan im
 * Projektplanner.
 */
class PpWidgetRenderer
{
    public const HOURS_PER_TS = 8;

    private Database $db;
    private PpBudgetService $budget;

    public function __construct(Database $db)
    {
        $this->db = $db;
        require_once SERVICES_PATH . '/PpBudgetService.php';
        $this->budget = new PpBudgetService($db);
    }

    /** Dashboard-Tile: kompakter Snapshot mit fixer 3-Zonen-Struktur.
     *  - Head: Logo + Kundenname + Plan-Titel
     *  - Progress: Donut + Erledigt + Spielraum
     *  - Cells: Ist | Geplant | Soll
     *  Keine Kontakte, Notizen, Carryover, Website — die kommen nur in der
     *  Sidebar-Vollansicht. Tile ist immer gleich hoch.
     */
    public function renderTile(int $planId): string
    {
        $plan = $this->loadPlan($planId);
        if (!$plan) return '<div class="pp-w-tile pp-w-tile-empty">Plan nicht gefunden.</div>';
        $rows = $this->db->query(
            "SELECT row_type, ist_hours, planned_hours, is_done, is_placeholder, no_ticket
             FROM pp_plan_rows WHERE plan_id = ?",
            [$planId]
        ) ?: [];
        $budgetData = $this->budget->getPlanBudgetSoll($planId);

        $hoursPerTs = (float) ($plan['customer_hours_per_ts'] ?? self::HOURS_PER_TS);
        if ($hoursPerTs <= 0) $hoursPerTs = self::HOURS_PER_TS;

        $ist = 0.0; $planned = 0.0; $done = 0; $total = 0;
        foreach ($rows as $r) {
            if ($r['row_type'] !== 'item') continue;
            if ((int) $r['is_placeholder']) continue;
            $ist += (float) $r['ist_hours'];
            $planned += (float) $r['planned_hours'];
            if ((int) $r['is_done']) $done++;
            $total++;
        }

        $budgetH  = (float) ($budgetData['soll_h']  ?? 0);
        $carryH   = (float) ($budgetData['carryover_h']  ?? 0);
        $effSollH = max(0, $budgetH - $carryH);
        $refSollH = $effSollH > 0 ? $effSollH : $budgetH;
        $noSoll   = $budgetH <= 0; // kein Kontingent (auf Zuruf / pausiert) — am Basis-Soll festmachen
        $gap      = $refSollH > 0 ? ($planned - $refSollH) : 0;
        $progress = $total > 0 ? (int) round($done / $total * 100) : 0;
        $isEinzelprojekt = ($plan['plan_status'] ?? '') === 'einzelprojekt';

        // Spielraum-Farb-Logik invertiert bei Einzelprojekt
        $gapPositive = $gap > 0.001;
        $gapNegative = $gap < -0.001;
        $gapCls = $isEinzelprojekt
            ? ($gapPositive ? 'is-neg' : ($gapNegative ? 'is-pos' : ''))
            : ($gapPositive || abs($gap) < 0.001 ? 'is-pos' : 'is-neg');
        $gapLabel = $isEinzelprojekt
            ? ($gapPositive ? 'Über Angebot' : 'Im Budget')
            : 'Spielraum';

        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $n = function ($v) {
            if ($v === null || $v === '' || !is_finite((float) $v)) return '0';
            $rounded = round((float) $v, 2);
            $s = number_format($rounded, 2, ',', '');
            return rtrim(rtrim($s, '0'), ',');
        };

        $custName = $plan['customer_name'] ?? '';
        $custAbbr = strtoupper((string) ($plan['customer_abbr'] ?? substr($custName ?: $plan['title'] ?? '?', 0, 3)));
        $custLogo = $plan['customer_logo'] ? '/uploads/customers/logos/' . basename($plan['customer_logo']) : null;
        $planUrl  = '/admin/projektplanner/plan/' . (int) $plan['id'];
        $logoHtml = $custLogo
            ? '<img class="pp-w-tile-logo" src="' . $h($custLogo) . '" alt="' . $h($custName) . '">'
            : '<span class="pp-w-tile-abbr">' . $h($custAbbr) . '</span>';

        // Projekt-Status als Pill (risiko_modus) — gleiche Zustaende/Farben wie im Projektplanner.
        $rm = $plan['risiko_modus'] ?? 'auto';
        $rmMap = [
            'auto'           => ['⚙', 'In Arbeit', '#475569', '#eef2f7'],
            'eskaliert'      => ['🔥', 'Brennt',    '#b91c1c', '#fef2f2'],
            'gruen'          => ['✓', 'Erledigt',  '#047857', '#f0fdf4'],
            'nicht_relevant' => ['⏸', 'Läuft mit', '#475569', '#f1f5f9'],
        ];
        $rmi = $rmMap[$rm] ?? $rmMap['auto'];
        $statusPillHtml = '<span class="pp-w-status-pill" title="Projekt-Status" style="display:inline-flex;align-items:center;gap:4px;margin-top:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.5;background:' . $rmi[3] . ';color:' . $rmi[2] . ';white-space:nowrap;">' . $rmi[0] . ' ' . $h($rmi[1]) . '</span>';

        ob_start();
        ?>
        <a class="pp-w-tile" href="<?= $h($planUrl) ?>" title="<?= $h($plan['title'] ?? '') ?> öffnen">
            <div class="pp-w-tile-head">
                <?= $logoHtml ?>
                <div class="pp-w-tile-titles">
                    <div class="pp-w-tile-customer"><?= $h($custName) ?></div>
                    <div class="pp-w-tile-plan"><?= $h($plan['title'] ?? '—') ?></div>
                    <?= $statusPillHtml ?>
                </div>
            </div>
            <div class="pp-w-tile-progress">
                <div class="pp-w-tile-donut" style="--deg:<?= $progress * 3.6 ?>deg;">
                    <div class="pp-w-tile-donut-inner">
                        <span class="pp-w-tile-donut-pct"><?= $progress ?>%</span>
                    </div>
                </div>
                <div class="pp-w-tile-meta">
                    <div class="pp-w-tile-erledigt"><strong><?= $done ?></strong> / <?= $total ?> erledigt</div>
                    <?php if (!$noSoll && $refSollH > 0): ?>
                        <div class="pp-w-tile-gap <?= $gapCls ?>">
                            <?= $h($gapLabel) ?>: <strong><?= $gap > 0 ? '+' : '' ?><?= $n($gap) ?> h</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="pp-w-tile-cells">
                <div class="pp-w-tile-cell is-ist">
                    <span class="pp-w-tile-cell-label">Ist</span>
                    <span class="pp-w-tile-cell-val"><?= $n($ist) ?><span class="pp-w-tile-cell-u">h</span></span>
                </div>
                <div class="pp-w-tile-cell is-planned">
                    <span class="pp-w-tile-cell-label">Geplant</span>
                    <span class="pp-w-tile-cell-val"><?= $n($planned) ?><span class="pp-w-tile-cell-u">h</span></span>
                </div>
                <div class="pp-w-tile-cell">
                    <span class="pp-w-tile-cell-label"><?= $noSoll ? 'auf Zuruf' : ($isEinzelprojekt ? 'Angebot' : 'Soll') ?></span>
                    <span class="pp-w-tile-cell-val"><?= $noSoll ? '—' : $n($refSollH) . '<span class="pp-w-tile-cell-u">h</span>' ?></span>
                </div>
            </div>
        </a>
        <?php
        return ob_get_clean();
    }

    public function renderForPlan(int $planId): string
    {
        $plan = $this->loadPlan($planId);
        if (!$plan) return '<div class="pp-widget pp-widget-empty">Plan nicht gefunden.</div>';
        $rows = $this->db->query(
            "SELECT row_type, ist_hours, planned_hours, is_done, is_placeholder, no_ticket
             FROM pp_plan_rows WHERE plan_id = ?",
            [$planId]
        ) ?: [];
        $budgetData = $this->budget->getPlanBudgetSoll($planId);

        return $this->renderHtml($plan, $rows, $budgetData);
    }

    /** Render-Aufruf direkt mit fertigen Daten (z.B. wenn Caller schon alles hat). */
    public function renderForPlanData(array $plan, array $rows, array $budgetData): string
    {
        return $this->renderHtml($plan, $rows, $budgetData);
    }

    private function loadPlan(int $planId): ?array
    {
        $plan = $this->db->queryOne(
            "SELECT p.*,
                    c.name AS customer_name, c.slug AS customer_slug, c.abbreviation AS customer_abbr,
                    c.hex_color AS customer_color, c.logo_path AS customer_logo, c.website AS customer_website,
                    c.billing_model AS customer_billing_model, c.ts_per_month AS customer_ts_per_month,
                    c.billing_notes AS customer_billing_notes, c.uebertrag_ts AS customer_uebertrag_ts,
                    c.hours_per_ts AS customer_hours_per_ts,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].name'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_name,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].role'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_role,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].email'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_email,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].initials'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_initials
             FROM pp_plans p
             LEFT JOIN customers c ON c.id = p.customer_id
             WHERE p.id = ?",
            [$planId]
        );
        return $plan ?: null;
    }

    /** Sucht den juengsten aktiven Plan eines Kunden — fuer die Kunden-Detailseite. */
    public function findLatestActivePlanIdForCustomer(int $customerId): ?int
    {
        $row = $this->db->queryOne(
            "SELECT id FROM pp_plans
             WHERE customer_id = ? AND state = 1 AND plan_status IN ('aktiv', 'einzelprojekt', 'reporting')
             ORDER BY COALESCE(period_from, created_at) DESC, id DESC
             LIMIT 1",
            [$customerId]
        );
        return $row ? (int) $row['id'] : null;
    }

    /** Alle aktiven Plaene fuer das Dashboard. */
    public function getActivePlanIds(): array
    {
        $rows = $this->db->query(
            "SELECT p.id
             FROM pp_plans p
             LEFT JOIN customers c ON c.id = p.customer_id
             WHERE p.state = 1 AND p.plan_status IN ('aktiv', 'einzelprojekt', 'reporting')
             ORDER BY c.abbreviation, c.name, p.period_from DESC, p.id"
        ) ?: [];
        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    private function renderHtml(array $plan, array $rows, array $budgetData): string
    {
        $hoursPerTs = (float) ($plan['customer_hours_per_ts'] ?? self::HOURS_PER_TS);
        if ($hoursPerTs <= 0) $hoursPerTs = self::HOURS_PER_TS;

        // === KPI-Berechnung — Logik gespiegelt von ppKpiSnapshot/ppCalcStats ===
        $ist = 0.0; $planned = 0.0; $done = 0; $total = 0;
        foreach ($rows as $r) {
            if ($r['row_type'] !== 'item') continue;
            if ((int) $r['is_placeholder']) continue;
            $ist += (float) $r['ist_hours'];
            $planned += (float) $r['planned_hours'];
            if ((int) $r['is_done']) $done++;
            $total++;
        }

        $budgetH  = (float) ($budgetData['soll_h']  ?? 0);
        $budgetTs = (float) ($budgetData['soll_ts'] ?? 0);
        $carryTs  = (float) ($budgetData['carryover_ts'] ?? 0);
        $carryH   = (float) ($budgetData['carryover_h']  ?? 0);
        $effSollH  = max(0, $budgetH  - $carryH);
        $effSollTs = max(0, $budgetTs - $carryTs);

        $refSollH = $effSollH > 0 ? $effSollH : $budgetH;
        // Kein Kontingent (auf Zuruf / pausiert): Basis-Soll = 0. Am Basis-Soll festmachen,
        // nicht am effektiven — der Kunden-Übertrag darf sonst ein Phantom-Soll erzeugen.
        $noSoll = $budgetH <= 0;
        $gap = $refSollH > 0 ? ($planned - $refSollH) : 0;
        $progress = $total > 0 ? (int) round($done / $total * 100) : 0;

        // Bar
        $refMax = max($refSollH, $planned, $ist) ?: 1;
        $istEnd  = $refSollH > 0 ? ($ist     / $refMax) * 100 : 0;
        $planEnd = $refSollH > 0 ? ($planned / $refMax) * 100 : 0;
        $sollEnd = $refSollH > 0 ? ($refSollH / $refMax) * 100 : 100;
        $segIst           = $istEnd;
        $segPlannedInSoll = max(0, min($planEnd, $sollEnd) - $istEnd);
        $segOverplanned   = max(0, $planEnd - $sollEnd);
        $segRestSoll      = max(0, $sollEnd - max($planEnd, $istEnd));
        $isOverplanned    = $segOverplanned > 0.001;

        // === HTML ===
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $n = function ($v) {
            if ($v === null || $v === '' || !is_finite((float) $v)) return '0';
            $rounded = round((float) $v, 2);
            $s = number_format($rounded, 2, ',', '');
            return rtrim(rtrim($s, '0'), ',');
        };

        // Kunde-Block
        $custName = $plan['customer_name'] ?? '';
        $custAbbr = strtoupper((string) ($plan['customer_abbr'] ?? substr($custName ?: $plan['title'] ?? '?', 0, 3)));
        $custLogo = $plan['customer_logo'] ? '/uploads/customers/logos/' . basename($plan['customer_logo']) : null;
        $custWebsite = trim((string) ($plan['customer_website'] ?? ''));
        if ($custWebsite && !preg_match('/^https?:\/\//i', $custWebsite)) $custWebsite = 'https://' . $custWebsite;
        $custWebsiteLabel = '';
        if ($custWebsite) {
            $parts = parse_url($custWebsite);
            $custWebsiteLabel = $parts['host'] ?? $custWebsite;
            $custWebsiteLabel = preg_replace('/^www\./', '', $custWebsiteLabel);
        }
        $custDetailUrl = $plan['customer_id'] ? '/admin/customers/' . (int) $plan['customer_id'] . '/steckbrief' : null;
        $logoHtml = $custLogo
            ? '<img class="pp-w-logo" src="' . $h($custLogo) . '" alt="' . $h($custName) . '">'
            : '<span class="pp-w-abbr">' . $h($custAbbr) . '</span>';

        // Kontakt
        $contactName = trim((string) ($plan['customer_main_contact_name'] ?? ''));
        $contactRole = trim((string) ($plan['customer_main_contact_role'] ?? ''));
        $contactEmail = trim((string) ($plan['customer_main_contact_email'] ?? ''));
        $contactInitials = strtoupper(trim((string) ($plan['customer_main_contact_initials'] ?? '')));
        if (!$contactInitials && $contactName) {
            $parts = preg_split('/\s+/', $contactName);
            $contactInitials = strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2))));
        }

        // Info-Zeile
        $infoParts = [];
        if ($plan['customer_ts_per_month'] !== null && (float) $plan['customer_ts_per_month'] > 0) {
            $infoParts[] = $n($plan['customer_ts_per_month']) . ' TS/Monat';
        }
        $modelLabels = [
            'fix_monatlich' => 'fester Retainer · monatlich',
            'fix_bimonatlich' => 'fester Retainer · 2-monatlich',
            'fix_quartalsweise' => 'fester Retainer · quartalsweise',
            'zuruf_monat' => 'auf Zuruf · monatlich',
            'zuruf_quartal' => 'auf Zuruf · quartalsweise',
            'einzelprojekt' => 'Einzelprojekt',
        ];
        if (!empty($plan['customer_billing_model']) && isset($modelLabels[$plan['customer_billing_model']])) {
            $infoParts[] = $modelLabels[$plan['customer_billing_model']];
        }
        $customNotes = trim((string) ($plan['customer_billing_notes'] ?? ''));

        // Plan-URL
        $planUrl = '/admin/projektplanner/plan/' . (int) $plan['id'];
        $planTitle = $plan['title'] ?? '—';

        // Projekt-Status als Pill (risiko_modus) — gleiche Zustaende/Farben wie im Projektplanner.
        $rm = $plan['risiko_modus'] ?? 'auto';
        $rmMap = [
            'auto'           => ['⚙', 'In Arbeit', '#475569', '#eef2f7'],
            'eskaliert'      => ['🔥', 'Brennt',    '#b91c1c', '#fef2f2'],
            'gruen'          => ['✓', 'Erledigt',  '#047857', '#f0fdf4'],
            'nicht_relevant' => ['⏸', 'Läuft mit', '#475569', '#f1f5f9'],
        ];
        $rmi = $rmMap[$rm] ?? $rmMap['auto'];
        $statusPillHtml = '<span class="pp-w-status-pill" title="Projekt-Status" style="display:inline-flex;align-items:center;gap:4px;margin:2px 0 4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.5;background:' . $rmi[3] . ';color:' . $rmi[2] . ';white-space:nowrap;">' . $rmi[0] . ' ' . $h($rmi[1]) . '</span>';

        ob_start();
        ?>
        <div class="pp-widget">
            <div class="pp-w-cust">
                <?= $custDetailUrl
                    ? '<a class="pp-w-cust-link" href="' . $h($custDetailUrl) . '" title="' . $h($custName) . ' öffnen">' . $logoHtml . '</a>'
                    : $logoHtml ?>
                <?php if ($custWebsite): ?>
                    <a class="pp-w-cust-web" href="<?= $h($custWebsite) ?>" target="_blank" rel="noopener">
                        <?= $h($custWebsiteLabel) ?>
                        <span class="material-symbols-rounded">open_in_new</span>
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($contactName): ?>
            <div class="pp-w-contact"<?= $contactEmail ? ' onclick="event.stopPropagation();window.location.href=\'mailto:' . $h($contactEmail) . '\'" style="cursor:pointer;" title="' . $h($contactEmail) . '"' : '' ?>>
                <span class="pp-w-contact-avatar"><?= $h($contactInitials ?: '?') ?></span>
                <div class="pp-w-contact-meta">
                    <div class="pp-w-contact-name"><?= $h($contactName) ?></div>
                    <?php if ($contactRole): ?><div class="pp-w-contact-role"><?= $h($contactRole) ?></div><?php endif; ?>
                </div>
                <?php if ($contactEmail): ?><span class="material-symbols-rounded pp-w-contact-mail">mail</span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($infoParts) || $customNotes): ?>
            <div class="pp-w-info">
                <?php if (!empty($infoParts)): ?>
                    <span class="pp-w-info-summary"><?= $h(implode(' · ', $infoParts)) ?></span>
                <?php endif; ?>
                <?php if ($customNotes): ?>
                    <span class="pp-w-info-notes"><?= $h($customNotes) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <a class="pp-w-title" href="<?= $h($planUrl) ?>" title="<?= $h($planTitle) ?> öffnen"><?= $h($planTitle) ?></a>
            <div><?= $statusPillHtml ?></div>
            <div class="pp-w-donut-row">
                <div class="pp-w-donut" style="--deg:<?= $progress * 3.6 ?>deg;" title="<?= $done ?> von <?= $total ?> erledigt">
                    <div class="pp-w-donut-inner">
                        <span class="pp-w-donut-pct"><?= $progress ?>%</span>
                        <span class="pp-w-donut-sub"><?= $done ?>/<?= $total ?></span>
                    </div>
                </div>
                <div class="pp-w-donut-meta">
                    <div class="pp-w-meta-label">Erledigt</div>
                    <div class="pp-w-meta-strong"><?= $done ?> <span class="pp-w-sub">/ <?= $total ?></span></div>
                    <?php if (!$noSoll && $refSollH > 0): ?>
                        <div class="pp-w-meta-sub">Ist: <?= $n($refSollH > 0 ? ($ist / $refSollH) * 100 : 0) ?> % vom Soll</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($budgetH > 0): ?>
            <div class="pp-w-stack <?= $isOverplanned ? 'is-over' : '' ?>">
                <span class="pp-w-stack-seg is-ist" style="width:<?= $segIst ?>%"></span>
                <span class="pp-w-stack-seg is-planned" style="width:<?= $segPlannedInSoll ?>%"></span>
                <?php if ($isOverplanned): ?>
                    <span class="pp-w-stack-seg is-overseg" style="width:<?= $segOverplanned ?>%"></span>
                    <span class="pp-w-stack-marker" style="left:<?= $sollEnd ?>%"></span>
                <?php else: ?>
                    <span class="pp-w-stack-seg is-rest" style="width:<?= $segRestSoll ?>%"></span>
                <?php endif; ?>
            </div>
            <div class="pp-w-stack-legend">
                <span><i class="pp-w-dot is-ist"></i>Ist</span>
                <span><i class="pp-w-dot is-planned"></i>Geplant</span>
                <?= $isOverplanned
                    ? '<span><i class="pp-w-dot is-overseg"></i>Über Soll</span>'
                    : '<span><i class="pp-w-dot is-rest"></i>Rest Soll</span>' ?>
            </div>
            <?php endif; ?>
            <div class="pp-w-cells">
                <div class="pp-w-cell is-ist">
                    <div class="pp-w-cell-label">IST</div>
                    <div class="pp-w-cell-num"><?= $n($ist) ?> <span class="pp-w-cell-unit">h</span></div>
                    <div class="pp-w-cell-sub"><?= $n($this->budget->hoursToTs($ist, $hoursPerTs)) ?> TS</div>
                </div>
                <div class="pp-w-cell is-planned">
                    <div class="pp-w-cell-label">GEPLANT</div>
                    <div class="pp-w-cell-num"><?= $n($planned) ?> <span class="pp-w-cell-unit">h</span></div>
                    <div class="pp-w-cell-sub"><?= $n($this->budget->hoursToTs($planned, $hoursPerTs)) ?> TS</div>
                </div>
                <?php if (!$noSoll): ?>
                <div class="pp-w-cell is-soll">
                    <div class="pp-w-cell-label">SOLL</div>
                    <div class="pp-w-cell-num"><?= $n($refSollH) ?> <span class="pp-w-cell-unit">h</span></div>
                    <div class="pp-w-cell-sub"><?= $n($effSollTs > 0 ? $effSollTs : $budgetTs) ?> TS</div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!$noSoll && abs($carryTs) > 0.001): ?>
            <div class="pp-w-carry">
                <div class="pp-w-carry-head">
                    Übertrag: <strong><?= $carryTs > 0 ? '+' : '' ?><?= $n($carryTs) ?> TS</strong>
                    — <?= $carryTs > 0 ? 'abbummeln' : 'nachliefern' ?>
                </div>
                <div class="pp-w-carry-sub">Standard: <?= $n($budgetTs) ?> TS / <?= $n($budgetH) ?> h</div>
            </div>
            <?php endif; ?>
            <?php if (!$noSoll && $refSollH > 0): ?>
            <div class="pp-w-puffer <?= $gap >= 0 ? 'is-positive' : 'is-negative' ?>">
                <span class="pp-w-puffer-label">Spielraum</span>
                <span class="pp-w-puffer-value"><?= $gap >= 0 ? '+' : '' ?><?= $n($gap) ?> h</span>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Liefert die CSS-Regeln fuer das Widget. Sollte einmal pro Seite eingebunden werden. */
    public static function css(): string
    {
        return <<<CSS
<style>
.pp-widget {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    padding: 14px 18px 16px;
    display: flex; flex-direction: column;
    gap: 10px;
    font-variant-numeric: tabular-nums;
    font-size: var(--d-fs-sm);
}
.pp-widget-empty { color: var(--slate-400); }

.pp-w-cust { display: flex; align-items: center; gap: 10px; }
.pp-w-logo {
    width: 36px; height: 36px;
    object-fit: contain;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    padding: 3px;
    flex-shrink: 0;
}
.pp-w-abbr {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px;
    background: var(--thoxan-50);
    color: var(--thoxan-800);
    border: 1px solid var(--thoxan-200);
    border-radius: 6px;
    font-size: 11px; font-weight: 700;
    letter-spacing: 0.3px;
    flex-shrink: 0;
    padding-top: 1px;
}
.pp-w-cust-link { display: inline-flex; text-decoration: none; transition: opacity 0.1s; }
.pp-w-cust-link:hover { opacity: 0.85; }
.pp-w-cust-web {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: var(--d-fs-sm); font-weight: 500;
    color: var(--slate-600); text-decoration: none;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    min-width: 0;
}
.pp-w-cust-web:hover { color: var(--thoxan-700); }
.pp-w-cust-web .material-symbols-rounded { font-size: 12px; color: var(--slate-400); transform: translateY(-2px); flex-shrink: 0; }

.pp-w-contact { display: flex; align-items: center; gap: 8px; padding: 2px 0 8px; border-bottom: 1px solid var(--slate-100); }
.pp-w-contact-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px;
    background: var(--slate-100);
    color: var(--slate-700);
    border-radius: 50%;
    font-size: 10px; font-weight: 700;
    flex-shrink: 0; padding-top: 1px;
}
.pp-w-contact-meta { flex: 1; min-width: 0; }
.pp-w-contact-name { font-size: var(--d-fs-xs); font-weight: 600; color: var(--slate-700); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pp-w-contact-role { font-size: 10px; color: var(--slate-500); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pp-w-contact-mail { font-size: 14px; color: var(--slate-400); flex-shrink: 0; transform: translateY(-2px); }

.pp-w-info { display: flex; flex-direction: column; gap: 2px; font-size: var(--d-fs-xs); color: var(--slate-600); padding: 4px 0 8px; border-bottom: 1px solid var(--slate-100); }
.pp-w-info-summary { font-weight: 600; color: var(--slate-700); }
.pp-w-info-notes { color: var(--slate-500); }

.pp-w-title {
    display: block;
    font-size: var(--d-fs-base); font-weight: 700;
    color: var(--slate-900); text-decoration: none;
    line-height: 1.3;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.pp-w-title:hover { color: var(--thoxan-700); }

.pp-w-donut-row { display: flex; align-items: center; gap: 14px; }
.pp-w-donut {
    --deg: 0deg;
    width: 64px; height: 64px;
    border-radius: 50%;
    flex-shrink: 0;
    background: conic-gradient(var(--thoxan-700) var(--deg), var(--slate-200) 0);
    position: relative;
}
.pp-w-donut-inner {
    position: absolute; inset: 6px;
    background: #fff; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    line-height: 1;
}
.pp-w-donut-pct { font-size: 16px; font-weight: 700; color: var(--slate-800); padding-top: 1px; }
.pp-w-donut-sub { font-size: 9px; color: var(--slate-400); margin-top: 2px; }
.pp-w-donut-meta { flex: 1; min-width: 0; }
.pp-w-meta-label { font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 500; }
.pp-w-meta-strong { font-size: 18px; font-weight: 700; color: var(--slate-800); margin-top: 2px; line-height: 1.1; }
.pp-w-meta-sub { font-size: var(--d-fs-xs); color: var(--slate-400); margin-top: 4px; }
.pp-w-sub { font-size: var(--d-fs-xs); font-weight: 500; color: var(--slate-400); }

.pp-w-stack {
    position: relative;
    display: flex; width: 100%; height: 8px;
    background: var(--slate-100);
    border-radius: 4px;
    margin-top: 2px;
    overflow: visible;
}
.pp-w-stack-seg { height: 100%; }
.pp-w-stack-seg:first-child { border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
.pp-w-stack-seg:last-child { border-top-right-radius: 4px; border-bottom-right-radius: 4px; }
.pp-w-stack-seg.is-ist { background: var(--thoxan-700); }
.pp-w-stack-seg.is-planned { background: var(--thoxan-400); }
.pp-w-stack-seg.is-overseg { background: var(--thoxan-200); }
.pp-w-stack-seg.is-rest { background: transparent; }
.pp-w-stack-marker { position: absolute; top: -2px; bottom: -2px; width: 2px; background: var(--slate-700); border-radius: 1px; transform: translateX(-1px); }
.pp-w-stack-legend { display: flex; gap: 10px; font-size: var(--d-fs-xs); color: var(--slate-500); flex-wrap: wrap; }
.pp-w-stack-legend span { display: inline-flex; align-items: center; gap: 4px; }
.pp-w-dot { display: inline-block; width: 8px; height: 8px; border-radius: 2px; }
.pp-w-dot.is-ist { background: var(--thoxan-700); }
.pp-w-dot.is-planned { background: var(--thoxan-400); }
.pp-w-dot.is-rest { background: var(--slate-200); }
.pp-w-dot.is-overseg { background: var(--thoxan-200); }

.pp-w-cells { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }
.pp-w-cell { display: flex; flex-direction: column; gap: 1px; padding: 8px 10px; border-radius: 8px; background: #fff; border: 1px solid var(--slate-200); }
.pp-w-cell-label { font-size: 9px; font-weight: 700; color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.4px; }
.pp-w-cell-num { font-size: 17px; font-weight: 700; color: var(--slate-800); line-height: 1.1; margin-top: 3px; }
.pp-w-cell.is-ist .pp-w-cell-num { color: var(--thoxan-700); }
.pp-w-cell.is-planned .pp-w-cell-num { color: var(--thoxan-500); }
.pp-w-cell-unit { font-size: 11px; color: var(--slate-400); font-weight: 500; margin-left: 1px; }
.pp-w-cell-sub { font-size: 10px; color: var(--slate-400); font-weight: 500; line-height: 1.3; margin-top: 2px; }

.pp-w-carry { padding: 4px 0 2px; font-size: var(--d-fs-xs); color: var(--slate-600); line-height: 1.4; }
.pp-w-carry-head { font-weight: 500; }
.pp-w-carry-head strong { font-weight: 700; color: var(--slate-800); }
.pp-w-carry-sub { margin-top: 2px; font-size: 11px; color: var(--slate-400); }

.pp-w-puffer { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; font-size: var(--d-fs-xs); color: var(--slate-600); padding: 5px 10px; border-radius: 6px; background: var(--slate-50); }
.pp-w-puffer.is-positive { background: var(--emerald-50); color: var(--emerald-800); }
.pp-w-puffer.is-negative { background: var(--rose-50); color: var(--rose-800); }
.pp-w-puffer-label { font-weight: 500; }
.pp-w-puffer-value { font-weight: 700; }

/* ===== Compact-Tile fuer Dashboard ===== */
.pp-w-tile {
    display: flex; flex-direction: column;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    text-decoration: none;
    overflow: hidden;
    transition: border-color 0.12s, box-shadow 0.12s, transform 0.08s;
    font-variant-numeric: tabular-nums;
}
.pp-w-tile:hover { border-color: var(--thoxan-300); box-shadow: 0 4px 12px rgba(0, 76, 155, 0.08); }
.pp-w-tile-empty { padding: 20px; color: var(--slate-400); text-align: center; }
/* Head: Logo + Customer + Plan-Titel */
.pp-w-tile-head {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--slate-100);
}
.pp-w-tile-logo {
    width: 32px; height: 32px;
    object-fit: contain;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 5px;
    padding: 2px;
    flex-shrink: 0;
}
.pp-w-tile-abbr {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px;
    background: var(--thoxan-50);
    color: var(--thoxan-800);
    border: 1px solid var(--thoxan-200);
    border-radius: 5px;
    font-size: 10px; font-weight: 700;
    letter-spacing: 0.3px;
    flex-shrink: 0;
    padding-top: 1px;
}
.pp-w-tile-titles { flex: 1; min-width: 0; }
.pp-w-tile-customer { font-size: 11px; color: var(--slate-500); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pp-w-tile-plan { font-size: var(--d-fs-sm); color: var(--slate-900); font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 1px; }

/* Progress: Donut + Erledigt + Spielraum */
.pp-w-tile-progress {
    display: flex; align-items: center; gap: 14px;
    padding: 14px;
    border-bottom: 1px solid var(--slate-100);
    background: var(--slate-50);
}
.pp-w-tile-donut {
    --deg: 0deg;
    width: 56px; height: 56px;
    border-radius: 50%;
    flex-shrink: 0;
    background: conic-gradient(var(--thoxan-700) var(--deg), var(--slate-200) 0);
    position: relative;
}
.pp-w-tile-donut-inner {
    position: absolute; inset: 5px;
    background: #fff; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    line-height: 1;
}
.pp-w-tile-donut-pct { font-size: 14px; font-weight: 700; color: var(--slate-800); padding-top: 1px; }
.pp-w-tile-meta { flex: 1; min-width: 0; }
.pp-w-tile-erledigt { font-size: var(--d-fs-sm); color: var(--slate-700); }
.pp-w-tile-erledigt strong { font-size: 16px; font-weight: 700; color: var(--slate-900); }
.pp-w-tile-gap { margin-top: 4px; font-size: var(--d-fs-xs); color: var(--slate-500); }
.pp-w-tile-gap.is-pos { color: var(--emerald-700); }
.pp-w-tile-gap.is-neg { color: var(--rose-700); }
.pp-w-tile-gap strong { font-weight: 700; }

/* Cells: Ist | Geplant | Soll/Angebot */
.pp-w-tile-cells {
    display: grid; grid-template-columns: 1fr 1fr 1fr;
    padding: 10px 14px 12px;
    gap: 8px;
}
.pp-w-tile-cell {
    display: flex; flex-direction: column; gap: 2px;
    border-right: 1px solid var(--slate-100);
    padding-right: 8px;
}
.pp-w-tile-cell:last-child { border-right: 0; padding-right: 0; }
.pp-w-tile-cell-label { font-size: 9px; font-weight: 700; color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.4px; }
.pp-w-tile-cell-val { font-size: 16px; font-weight: 700; color: var(--slate-800); line-height: 1.1; }
.pp-w-tile-cell.is-ist .pp-w-tile-cell-val { color: var(--thoxan-700); }
.pp-w-tile-cell.is-planned .pp-w-tile-cell-val { color: var(--thoxan-500); }
.pp-w-tile-cell-u { font-size: 11px; color: var(--slate-400); font-weight: 500; margin-left: 1px; }
</style>
CSS;
    }
}
