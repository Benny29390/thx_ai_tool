<?php
/**
 * Rule Suggestions API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/AIService.php';
require_once SERVICES_PATH . '/FeedbackAnalyzer.php';
require_once SERVICES_PATH . '/RuleEngine.php';

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        // Vorschläge laden
        $where = Auth::isAdmin() ? '' : 'WHERE rs.customer_id = ?';
        $params = Auth::isAdmin() ? [] : [Auth::customerId()];

        if (isset($_GET['status'])) {
            $where .= ($where ? ' AND ' : 'WHERE ') . 'rs.status = ?';
            $params[] = $_GET['status'];
        }

        $suggestions = $db->query(
            "SELECT rs.*, c.name as customer_name
             FROM rule_suggestions rs
             JOIN customers c ON rs.customer_id = c.id
             $where
             ORDER BY rs.created_at DESC",
            $params
        );

        Response::success($suggestions);
        break;

    case 'POST':
        // Neuen Vorschlag generieren
        if (!Auth::hasRole(ROLE_MANAGER)) {
            Response::forbidden();
        }

        $customerId = Auth::isAdmin() ? ($input['customer_id'] ?? null) : Auth::customerId();
        if (!$customerId) {
            Response::error('Kunde erforderlich');
        }

        // API Key laden
        $apiKey = \Core\Settings::get('openai_api_key');
        if (empty($apiKey)) {
            Response::error('OpenAI API Key nicht konfiguriert');
        }

        $ai = new \Services\AIService($apiKey, 'openai');
        $analyzer = new \Services\FeedbackAnalyzer($db);
        $analyzer->setAIService($ai);

        $suggestion = $analyzer->generateRuleSuggestion($customerId);

        if ($suggestion) {
            Response::success($suggestion, 'Regelvorschlag generiert');
        } else {
            Response::error('Nicht genügend Feedback für Regelvorschlag');
        }
        break;

    case 'PUT':
        // Vorschlag freigeben oder ablehnen
        if (!Auth::isAdmin()) {
            Response::forbidden();
        }

        if (!$id) {
            Response::error('ID erforderlich');
        }

        $action = $input['action'] ?? null;

        $ruleEngine = new \Services\RuleEngine($db);

        if ($action === 'approve') {
            $ruleId = $ruleEngine->approveSuggestion($id, Auth::id(), $input);
            Response::success(['rule_id' => $ruleId], 'Regel freigegeben');
        } elseif ($action === 'reject') {
            $ruleEngine->rejectSuggestion($id, Auth::id());
            Response::success(null, 'Vorschlag abgelehnt');
        } else {
            Response::error('Aktion erforderlich (approve oder reject)');
        }
        break;

    default:
        Response::error('Method not allowed', 405);
}
