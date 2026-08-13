<?php
/**
 * Projektplanner — Widget-HTML-Endpoint fuer Dashboard-Kachel-Ansicht.
 *
 * GET /admin/projektplanner/widgets                Alle aktiven Plaene als Widget-HTMLs
 * GET /admin/projektplanner/widgets?sort=risk      Sortierung: alphabetisch (default) oder risk
 *
 * Liefert: { success, data: { items: [...], css: '...' } }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PpWidgetRenderer;
use Services\PpBudgetService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/PpWidgetRenderer.php';
require_once SERVICES_PATH . '/PpBudgetService.php';

$db = Database::getInstance();
$renderer = new PpWidgetRenderer($db);
$budgetSvc = new PpBudgetService($db);

$sort = $_GET['sort'] ?? 'alpha';
$planIds = $renderer->getActivePlanIds();

$out = [];
foreach ($planIds as $planId) {
    $plan = $db->queryOne(
        "SELECT p.id, p.title, p.customer_id,
                p.risiko_modus, p.risiko_notiz,
                c.name AS customer_name, c.abbreviation AS customer_abbr
         FROM pp_plans p
         LEFT JOIN customers c ON c.id = p.customer_id
         WHERE p.id = ?",
        [$planId]
    );
    if (!$plan) continue;

    // KPIs fuer Sortierung
    $rows = $db->query(
        "SELECT row_type, ist_hours, planned_hours, is_done, is_placeholder, no_ticket
         FROM pp_plan_rows WHERE plan_id = ?",
        [$planId]
    ) ?: [];
    $budgetData = $budgetSvc->getPlanBudgetSoll($planId);

    $ist = 0.0; $planned = 0.0; $done = 0; $total = 0;
    foreach ($rows as $r) {
        if ($r['row_type'] !== 'item') continue;
        if ((int) $r['is_placeholder']) continue;
        $ist += (float) $r['ist_hours'];
        $planned += (float) $r['planned_hours'];
        if ((int) $r['is_done']) $done++;
        $total++;
    }
    $budgetH = (float) ($budgetData['soll_h'] ?? 0);
    $carryH  = (float) ($budgetData['carryover_h'] ?? 0);
    $effSollH = max(0, $budgetH - $carryH);
    $refSollH = $effSollH > 0 ? $effSollH : $budgetH;
    $spielraum = $refSollH > 0 ? ($planned - $refSollH) : 0;
    $autoRiskScore = $refSollH > 0 ? $spielraum / max($refSollH, 1) : 0;

    // Manuelle Risiko-Stufe (eskaliert | gruen | nicht_relevant) uebersteuert
    // den berechneten Score. Bei 'auto' bleibt der automatische Score.
    $modus = $plan['risiko_modus'] ?? 'auto';
    $manuellRangBoost = match ($modus) {
        'eskaliert'      => -1000.0, // ganz oben in Risiko-Sortierung
        'gruen'          =>  +500.0, // ans Ende
        'nicht_relevant' => +1000.0, // ganz unten / wird im UI ggf. ausgeblendet
        default          =>     0.0,
    };
    $finalRiskScore = $modus === 'auto' ? $autoRiskScore : $manuellRangBoost;

    $out[] = [
        'plan_id'        => (int) $plan['id'],
        'plan_title'     => $plan['title'],
        'customer_id'    => (int) $plan['customer_id'],
        'customer_name'  => $plan['customer_name'] ?? '',
        'customer_abbr'  => $plan['customer_abbr'] ?? '',
        'sort_key'       => strtolower(($plan['customer_abbr'] ?? '') . ' ' . ($plan['customer_name'] ?? '') . ' ' . ($plan['title'] ?? '')),
        'risk_score'     => $finalRiskScore,
        'risk_score_auto'=> $autoRiskScore,
        'risiko_modus'   => $modus,
        'risiko_notiz'   => $plan['risiko_notiz'] ?? null,
        'spielraum_h'    => round($spielraum, 2),
        'ist_h'          => round($ist, 2),
        'planned_h'      => round($planned, 2),
        'soll_h'         => round($refSollH, 2),
        'progress_pct'   => $total > 0 ? (int) round($done / $total * 100) : 0,
        'html'           => $renderer->renderTile((int) $plan['id']),
    ];
}

if ($sort === 'risk') {
    usort($out, fn($a, $b) => $a['risk_score'] <=> $b['risk_score']);
} else {
    usort($out, fn($a, $b) => strcmp($a['sort_key'], $b['sort_key']));
}

Response::success([
    'items' => $out,
    'css'   => PpWidgetRenderer::css(),
    'sort'  => $sort,
]);
