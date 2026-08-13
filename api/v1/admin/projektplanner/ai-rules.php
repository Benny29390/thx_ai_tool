<?php
/**
 * Projektplanner — gelernte KI-Regeln pro Kunde (Lernschleife fuer „Duplizieren mit KI").
 *
 * GET    /admin/projektplanner/ai-rules?customer_id=X   Liste (aktive + Vorschlaege, inkl. global)
 * POST   /admin/projektplanner/ai-rules                 Anlegen { customer_id?, rule_text, status?, source? }
 * POST   /admin/projektplanner/ai-rules/{id}            Aktualisieren { rule_text?, is_active?, status? }
 * DELETE /admin/projektplanner/ai-rules/{id}            Loeschen
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/PpAiRulesService.php';
$svc = new \Services\PpAiRulesService(Database::getInstance());
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
$ruleId = (int) ($_GET['rule_id'] ?? 0);

try {
    if ($ruleId > 0 && $method === 'DELETE') {
        $svc->delete($ruleId);
        Response::success(['id' => $ruleId], 'Regel gelöscht');
    }
    if ($ruleId > 0 && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $svc->update($ruleId, $body);
        Response::success(['id' => $ruleId], 'Regel aktualisiert');
    }
    if ($ruleId === 0 && $method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $cid = !empty($body['customer_id']) ? (int) $body['customer_id'] : null;
        $id = $svc->add($cid, (string) ($body['rule_text'] ?? ''), (string) ($body['status'] ?? 'vorschlag'), (string) ($body['source'] ?? 'manuell'), $userId);
        Response::success(['id' => $id], 'Regel gespeichert');
    }
    if ($ruleId === 0 && $method === 'GET') {
        $cid = (int) ($_GET['customer_id'] ?? 0);
        Response::success(['rules' => $svc->listForCustomer($cid)]);
    }
    Response::error('Nicht unterstützte Anfrage', 405);
} catch (\Throwable $e) {
    Response::error($e->getMessage());
}
