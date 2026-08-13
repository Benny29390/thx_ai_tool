<?php

/**
 * KI-Vorschlaege fuer Steckbrief-Karten (Stufe B).
 *
 * POST /admin/customer-cards/{cardId}/suggest          — Vorschlaege fuer EINE Karte
 *      Response: { created: N, note? }
 *
 * POST /admin/customers/{customerId}/steckbrief-suggest — Vorschlaege fuer alle Karten
 *      Response: { cards_processed, cards_skipped, suggestions_created }
 *
 * GET  /admin/customers/{customerId}/steckbrief-suggestions — Liste pending per Customer
 *
 * GET  /admin/customer-cards/{cardId}/suggest          — Liste pending pro Karte
 *
 * POST /admin/customer-cards/{cardId}/suggestions/{sid} — accept/reject
 *      Body: { action: 'accept'|'reject' }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\SteckbriefSuggestionService;
use Services\CustomerCardService;

require_once SERVICES_PATH . '/SteckbriefSuggestionService.php';
require_once SERVICES_PATH . '/CustomerCardService.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? '');
$customerId = (int) ($_GET['customer_id'] ?? 0);
$cardId = (int) ($_GET['card_id'] ?? 0);
$suggestionId = (int) ($_GET['suggestion_id'] ?? 0);
$userId = (int) Auth::id();

// OpenAI-Key
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") ?: [] as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);
$openaiKey = (string) ($settings['openai_api_key'] ?? '');
if ($openaiKey === '') Response::error('OpenAI API-Key nicht konfiguriert');

$svc = new SteckbriefSuggestionService($db, $openaiKey, $settings);

// Customer-Auth: Karte/Kunden gehoeren zum User?
if ($cardId > 0 && $customerId === 0) {
    $cust = $db->queryValue("SELECT customer_id FROM customer_cards WHERE id = ?", [$cardId]);
    if (!$cust) Response::notFound('Karte nicht gefunden');
    $customerId = (int) $cust;
}
if ($customerId > 0 && !Auth::canAccessCustomer($customerId)) Response::forbidden();

// GET: Listen
if ($method === 'GET') {
    if ($cardId > 0 && $action === 'one-card') {
        Response::success(['suggestions' => $svc->listForCard($cardId)]);
    }
    if ($action === 'list' && $customerId > 0) {
        Response::success(['suggestions' => $svc->listForCustomer($customerId)]);
    }
    Response::error('Unbekannte GET-Aktion');
}

if ($method !== 'POST') Response::error('Methode nicht erlaubt');

// Decide (accept/reject) eine Suggestion
if ($action === 'decide' && $suggestionId > 0 && $cardId > 0) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $a = (string) ($input['action'] ?? '');
    if (!in_array($a, ['accept', 'reject'], true)) Response::error('action muss accept|reject sein');
    try {
        if ($a === 'accept') {
            $cs = new CustomerCardService($db);
            $svc->accept($suggestionId, $userId, $cs);
            Response::success(['accepted' => true], 'Übernommen');
        }
        $svc->reject($suggestionId, $userId);
        Response::success(['rejected' => true], 'Abgelehnt');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Fehler');
    }
}

// Vorschlaege fuer eine einzelne Karte
if ($action === 'one-card' && $cardId > 0) {
    try {
        $res = $svc->suggestForCard($cardId, $userId);
        Response::success($res, 'Vorschlaege erzeugt');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Fehler');
    }
}

// Bulk: alle Karten eines Kunden
if ($customerId > 0 && $action === '') {
    try {
        $res = $svc->suggestForAllCards($customerId, $userId);
        Response::success($res, 'Vorschlaege erzeugt');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Fehler');
    }
}

Response::error('Unbekannte Aktion');
