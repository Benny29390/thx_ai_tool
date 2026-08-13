<?php
/**
 * Transkription — Templates API (Prompt-Vorlagen pro Ausgabe-Typ)
 *
 * GET /admin/transkription/templates
 * PUT /admin/transkription/templates/{id}    Body: { label?, prompt_text?, output_format?, is_active? }
 *
 * Schreibend nur fuer Admin.
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\TranskriptionOutputService;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

require_once SERVICES_PATH . '/TranskriptionOutputService.php';
$svc = new TranskriptionOutputService(Database::getInstance());

$method = $_SERVER['REQUEST_METHOD'];
$id = (int)($_GET['template_id'] ?? 0);

if ($method === 'GET') {
    Response::success(['templates' => $svc->listTemplates()]);
}

if ($method === 'PUT') {
    if (!Auth::isAdmin()) Response::forbidden('Nur Admin darf Vorlagen aendern');
    if (!$id) Response::error('template_id fehlt');
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    if (!is_array($json)) Response::error('Body muss JSON sein');
    try {
        $svc->updateTemplate($id, $json);
        Response::success(['updated' => $id], 'Vorlage gespeichert');
    } catch (\Throwable $e) {
        Response::error('Speichern fehlgeschlagen: ' . $e->getMessage());
    }
}

Response::error('Methode nicht unterstuetzt', 405);
