<?php
/**
 * POST /admin/customers/{id}/project-types
 *   Body: { project_type_id: int }
 *   Fuegt dem Kunden eine Projekt-Art (Tag) hinzu — sowohl in customer_project_types
 *   als auch im Tag-Array unter customers.settings.tags (Single Source of Truth).
 *
 * Wird vom /rules-View aufgerufen, wenn der User aus dem "Andere globale Regeln"-
 * Block heraus eine Projekt-Art beim Kunden ergaenzt, damit die zugehoerigen
 * globalen Regeln dann auch greifen.
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('customer_id erforderlich');

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$ptId = (int)($payload['project_type_id'] ?? 0);
if ($ptId <= 0) Response::error('project_type_id fehlt');

$db = Database::getInstance();

$customer = $db->queryOne("SELECT id, settings FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::notFound('Kunde nicht gefunden');

$pt = $db->queryOne("SELECT id, name FROM project_types WHERE id = ? AND is_active = 1", [$ptId]);
if (!$pt) Response::error('Projekt-Art nicht gefunden');

// 1) customer_project_types eintragen (idempotent)
try {
    $db->insert('customer_project_types', ['customer_id' => $customerId, 'project_type_id' => $ptId]);
} catch (\Throwable $e) {
    // Duplikat = ok
}

// 2) Tags im settings-Feld nachziehen, damit die Single-Source-of-Truth-Sicht intakt bleibt
$settings = json_decode($customer['settings'] ?? '{}', true) ?: [];
$tags = $settings['tags'] ?? [];
if (!is_array($tags)) $tags = [];
$ptName = $pt['name'];
$lowerExisting = array_map('mb_strtolower', $tags);
if (!in_array(mb_strtolower($ptName), $lowerExisting, true)) {
    $tags[] = $ptName;
    $settings['tags'] = $tags;
    $db->update('customers', ['settings' => json_encode($settings)], 'id = ?', [$customerId]);
}

// Audit
try { \Core\AuditLog::record('customer', (string)$customerId, 'project_type_added', ['project_type_id' => $ptId, 'project_type_name' => $ptName]); } catch (\Throwable $e) {}

Response::success(['customer_id' => $customerId, 'project_type_id' => $ptId, 'project_type_name' => $ptName]);
