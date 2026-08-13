<?php

/**
 * Card-Layout-Templates: Speichern + Anwenden eines Steckbrief-Layouts.
 *
 * GET    /admin/card-layout-templates                       — Liste
 * POST   /admin/card-layout-templates                       — Neu (Body: { customer_id, name, description? })
 * DELETE /admin/card-layout-templates/{id}                  — Loeschen
 * POST   /admin/customers/{id}/apply-layout-template        — Anwenden (Body: { template_id })
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\CardLayoutTemplateService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/CardLayoutTemplateService.php';

$db = Database::getInstance();
$service = new CardLayoutTemplateService($db);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$templateId = (int) ($_GET['template_id'] ?? 0);
$customerId = (int) ($_GET['customer_id'] ?? 0);
$userId = Auth::id();

// Anwenden auf einen Kunden
if ($action === 'apply' && $customerId > 0 && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $tplId = (int) ($input['template_id'] ?? 0);
    if ($tplId <= 0) Response::error('template_id fehlt');
    try {
        $stats = $service->applyToCustomer($customerId, $tplId);
        Response::success($stats, 'Layout angewendet');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Anwenden fehlgeschlagen');
    }
}

// Loeschen
if ($templateId > 0 && $method === 'DELETE') {
    $service->delete($templateId);
    Response::success(null, 'Template gelöscht');
}

// Liste
if ($method === 'GET') {
    Response::success(['templates' => $service->list()]);
}

// Neu (Snapshot eines Kunden)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $cid = (int) ($input['customer_id'] ?? 0);
    $name = trim((string) ($input['name'] ?? ''));
    $description = $input['description'] ?? null;
    if ($cid <= 0) Response::error('customer_id fehlt');
    if ($name === '') Response::error('Name fehlt');
    try {
        $id = $service->createFromCustomer($cid, $name, $description, $userId);
        Response::success(['id' => $id], 'Template gespeichert');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Speichern fehlgeschlagen');
    }
}

Response::error('Methode nicht unterstützt');
