<?php
/**
 * KI-Mitarbeiter — Wizard-Endpunkte.
 * Eingehängt aus api/v1/ki-mitarbeiter.php mit gesetztem $employeeId + $wizardSub.
 *   GET  /ki-mitarbeiter/{id}/wizard/state     — Verlauf + Profil + Vollständigkeit
 *   POST /ki-mitarbeiter/{id}/wizard/messages  — eine Wizard-Runde
 */

use Core\Auth;
use Core\Response;

/** @var int $employeeId */ /** @var string $wizardSub */
global $db, $method, $input;

require_once SERVICES_PATH . '/KiWizardService.php';
require_once SERVICES_PATH . '/KiMitarbeiterService.php';

$wiz = new \Services\KiWizardService($db);
$svc = new \Services\KiMitarbeiterService($db);
$actor = (int) Auth::id();

if ($wizardSub === '/wizard/state' && $method === 'GET') {
    $e = $svc->get($employeeId);
    Response::success([
        'messages'     => $wiz->verlauf($employeeId),
        'profile'      => $e['profile'] ?? [],
        'completeness' => $e['completeness'] ?? ['percentage' => 0, 'missing_sections' => []],
        'status'       => $e['status'] ?? 'draft',
        'name'         => $e['name'] ?? '',
    ]);
}

if ($wizardSub === '/wizard/messages' && $method === 'POST') {
    $msg = trim((string) ($input['message'] ?? ''));
    if ($msg === '') Response::error('Nachricht fehlt.');
    set_time_limit(120);
    try {
        $result = $wiz->antwort($employeeId, $msg, $actor);
    } catch (\Throwable $ex) {
        Response::error('Wizard-Fehler: ' . $ex->getMessage());
    }
    Response::success($result);
}

Response::error('Unbekannte Wizard-Route', 404);
