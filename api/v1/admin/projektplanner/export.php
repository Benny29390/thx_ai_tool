<?php
/**
 * Projektplanner — Excel-Export
 *
 * GET /admin/projektplanner/plans/{id}/export    Plan-Export als .xls (read)
 * GET /admin/projektplanner/export-person?name=  Personen-Export als .xls
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PpExportService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/PpExportService.php';
require __DIR__ . '/_pp_perm.php';

$svc = new PpExportService(Database::getInstance());
$planId = (int) ($_GET['plan_id'] ?? 0);
$person = trim((string) ($_GET['name'] ?? ''));
$format = (string) ($_GET['format'] ?? 'xls');

if ($_GET['action'] ?? '' === 'backup-json') {
    if (!Auth::isAdmin()) Response::forbidden();
    $db = Database::getInstance();
    $data = [
        'export_version' => '1.0',
        'exported_at' => date('c'),
        'hours_per_ts' => 8,
        'team_members' => $db->query("SELECT * FROM pp_team_members") ?: [],
        'customers' => $db->query("SELECT id, name, slug, abbreviation, hex_color, url, stundensatz, uebertrag_ts, uebertrag_notiz, abrechnungsmodus FROM customers WHERE is_active = 1") ?: [],
        'plans' => [],
        'customer_budgets' => $db->query("SELECT * FROM pp_customer_budget") ?: [],
        'person_shares' => $db->query("SELECT * FROM pp_person_shares") ?: [],
    ];
    $plans = $db->query("SELECT * FROM pp_plans") ?: [];
    foreach ($plans as $p) {
        $p['rows'] = $db->query("SELECT * FROM pp_plan_rows WHERE plan_id = ? ORDER BY position", [(int)$p['id']]) ?: [];
        $p['shares'] = $db->query("SELECT * FROM pp_plan_shares WHERE plan_id = ?", [(int)$p['id']]) ?: [];
        $p['feedback'] = $db->query("SELECT * FROM pp_plan_feedback WHERE plan_id = ?", [(int)$p['id']]) ?: [];
        $p['budget_overrides'] = $db->query("SELECT * FROM pp_plan_budget WHERE plan_id = ?", [(int)$p['id']]) ?: [];
        $data['plans'][] = $p;
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="projektplanner_backup_' . date('Y-m-d_His') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($planId > 0) {
    pp_require($planId, 'read');
    $svc->exportPlan($planId);
    exit;
}

if ($person !== '') {
    $svc->exportPerson($person);
    exit;
}

Response::error('plan_id oder name fehlt');
