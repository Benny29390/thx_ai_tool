<?php
/**
 * Order Learning API - Lern-Schleife: Regel-Vorschläge und Briefing-Optimierung
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input, $uri;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

// URI parsen
if (preg_match('#^/orders/(\d+)/learning/(suggest-rule|accept-rule|reject-rule|optimize-briefing)(?:/(\d+))?$#', $uri, $matches)) {
    $orderId = (int) $matches[1];
    $action = $matches[2];
    $ruleId = isset($matches[3]) ? (int) $matches[3] : null;
} else {
    Response::notFound('Endpunkt nicht gefunden');
}

// Auftrag laden
$order = $db->queryOne(
    "SELECT o.*, ctx.name as context_name FROM orders o
     LEFT JOIN contexts ctx ON o.context_id = ctx.id
     WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    Response::notFound('Auftrag nicht gefunden');
}

if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
    Response::forbidden();
}

// Services laden
require_once SERVICES_PATH . '/ContextService.php';
require_once SERVICES_PATH . '/AIService.php';
require_once SERVICES_PATH . '/UsageTracker.php';

$contextService = new \Services\ContextService($db);

// Settings laden (API-Keys, Default-Model)
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

$model = $order['model'] ?? $settings['default_model'] ?? 'gpt-4';
$provider = (strpos($model, 'claude') !== false) ? 'anthropic' : 'openai';
$apiKey = $provider === 'anthropic' ? ($settings['anthropic_api_key'] ?? '') : ($settings['openai_api_key'] ?? '');

if (empty($apiKey)) {
    Response::error('API-Key für ' . $provider . ' nicht konfiguriert. Bitte unter Einstellungen hinterlegen.');
}

$aiService = new \Services\AIService($apiKey, $provider);
$aiService->setModel($model);
$aiService->setMaxTokens(2048);

$usageTracker = new \Services\UsageTracker($db);

switch ($action) {
    case 'suggest-rule':
        $selectedText = trim($input['selected_text'] ?? '');

        if (!empty($selectedText)) {
            // Regel aus markiertem Text ableiten
            $systemPrompt = "Du bist ein Regel-Experte. Analysiere den folgenden Text-Ausschnitt und leite eine allgemeine, wiederverwendbare Schreibregel daraus ab, damit zukünftige Texte genauso geschrieben werden.\n\n";
            $systemPrompt .= "Text-Ausschnitt:\n" . $selectedText . "\n\n";
            $systemPrompt .= "Antworte als JSON:\n";
            $systemPrompt .= "{\n";
            $systemPrompt .= "  \"rule_name\": \"Kurzer Name der Regel\",\n";
            $systemPrompt .= "  \"rule_content\": \"Die Regel als klare Anweisung\",\n";
            $systemPrompt .= "  \"rule_type\": \"style|format|content|tone|link\"\n";
            $systemPrompt .= "}\n";

            $messages = [
                ['role' => 'user', 'content' => 'Leite eine wiederverwendbare Schreibregel aus diesem Text-Ausschnitt ab.']
            ];
        } else {
            // KI schlägt eine Regel basierend auf den bisherigen Änderungen vor
            $recentChanges = $db->query(
                "SELECT content, applied_change FROM order_chat_messages
                 WHERE order_id = ? AND phase = 'editing' AND applied_change IS NOT NULL
                 ORDER BY created_at DESC LIMIT 5",
                [$orderId]
            );

            if (empty($recentChanges)) {
                Response::error('Keine Änderungen vorhanden');
            }

            $changesText = "";
            foreach ($recentChanges as $change) {
                $appliedChange = json_decode($change['applied_change'], true);
                $changesText .= "- " . ($appliedChange['change_description'] ?? $change['content']) . "\n";
            }

            $systemPrompt = "Du bist ein Regel-Experte. Analysiere die folgenden Änderungen und leite eine allgemeine, wiederverwendbare Regel daraus ab.\n\n";
            $systemPrompt .= "Änderungen:\n" . $changesText . "\n\n";
            $systemPrompt .= "Antworte als JSON:\n";
            $systemPrompt .= "{\n";
            $systemPrompt .= "  \"rule_name\": \"Kurzer Name der Regel\",\n";
            $systemPrompt .= "  \"rule_content\": \"Die Regel als klare Anweisung\",\n";
            $systemPrompt .= "  \"rule_type\": \"style|format|content|tone|link\"\n";
            $systemPrompt .= "}\n";

            $messages = [
                ['role' => 'user', 'content' => 'Leite eine allgemeine Regel aus diesen Änderungen ab.']
            ];
        }

        try {
            $result = $aiService->chat($messages, $systemPrompt);

            $responseContent = $result['content'];
            if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $responseContent, $jsonMatch)) {
                $responseContent = $jsonMatch[1];
            }

            $parsed = json_decode($responseContent, true);

            if (!$parsed || empty($parsed['rule_content'])) {
                Response::error('KI konnte keine Regel ableiten');
            }

            // Vorschlag speichern
            $suggestionId = $db->insert('order_rule_suggestions', [
                'order_id' => $orderId,
                'context_id' => $order['context_id'],
                'suggested_rule' => $parsed['rule_content'],
                'rule_name' => $parsed['rule_name'] ?? null,
                'rule_type' => $parsed['rule_type'] ?? 'content'
            ]);

            $usageTracker->log(
                $order['customer_id'],
                Auth::id(),
                'order_suggest_rule',
                $model,
                $provider,
                $result['tokens']['input'] ?? 0,
                $result['tokens']['output'] ?? 0,
                0,
                ['order_id' => $orderId]
            );

            Response::success([
                'id' => $suggestionId,
                'rule_name' => $parsed['rule_name'] ?? null,
                'rule_content' => $parsed['rule_content'],
                'rule_type' => $parsed['rule_type'] ?? 'content'
            ], 'Regel-Vorschlag erstellt');

        } catch (\Exception $e) {
            Response::error('Regel-Vorschlag fehlgeschlagen: ' . $e->getMessage());
        }
        break;

    case 'accept-rule':
        if (!$ruleId) {
            Response::error('Regel-ID erforderlich');
        }

        $suggestion = $db->queryOne(
            "SELECT * FROM order_rule_suggestions WHERE id = ? AND order_id = ? AND status = 'pending'",
            [$ruleId, $orderId]
        );

        if (!$suggestion) {
            Response::notFound('Vorschlag nicht gefunden');
        }

        // User-Edits übernehmen (aus Formular), Fallback auf Suggestion-Werte
        $ruleName = trim($input['name'] ?? '') ?: ($suggestion['rule_name'] ?? 'KI-generierte Regel');
        $ruleContent = trim($input['rule_content'] ?? '') ?: $suggestion['suggested_rule'];
        $ruleTypeSlug = $input['rule_type'] ?? $suggestion['rule_type'] ?? 'content';

        // Artefakt erstellen via ArtifactService
        require_once SERVICES_PATH . '/EntityService.php';
        require_once SERVICES_PATH . '/ArtifactService.php';
        $entityService = new \Services\EntityService($db);
        $artifactService = new \Services\ArtifactService($db, $entityService);

        $meta = [
            'type' => 'Regel',
            'name' => $ruleName,
            'rule_type' => $ruleTypeSlug,
            'source' => 'ai_learning'
        ];
        if ($order['customer_id']) {
            $meta['scope'] = ['customers' => [(int)$order['customer_id']]];
        }

        $artifactId = $artifactService->create([
            'text' => $ruleContent,
            'meta' => $meta
        ]);

        // Vorschlag als akzeptiert markieren
        $db->update('order_rule_suggestions', [
            'status' => 'accepted'
        ], 'id = ?', [$ruleId]);

        Response::success([
            'artifact_id' => $artifactId
        ], 'Regel als Artefakt erstellt');
        break;

    case 'reject-rule':
        if (!$ruleId) {
            Response::error('Regel-ID erforderlich');
        }

        $db->update('order_rule_suggestions', [
            'status' => 'rejected'
        ], 'id = ? AND order_id = ?', [$ruleId, $orderId]);

        Response::success(null, 'Vorschlag abgelehnt');
        break;

    case 'optimize-briefing':
        if (empty($order['briefing_content'])) {
            Response::error('Kein Briefing vorhanden');
        }

        // Bisherige Änderungen laden
        $changes = $db->query(
            "SELECT content, applied_change FROM order_chat_messages
             WHERE order_id = ? AND phase = 'editing' AND applied_change IS NOT NULL
             ORDER BY created_at DESC LIMIT 10",
            [$orderId]
        );

        $changesText = "";
        foreach ($changes as $change) {
            $appliedChange = json_decode($change['applied_change'], true);
            $changesText .= "- " . ($appliedChange['change_description'] ?? $change['content']) . "\n";
        }

        $systemPrompt = "Du bist ein Content-Stratege. Optimiere das Briefing basierend auf den Erfahrungen.\n\n";
        $systemPrompt .= "Aktuelles Briefing:\n" . $order['briefing_content'] . "\n\n";
        $systemPrompt .= "Bisherige Änderungen am Artikel:\n" . $changesText . "\n\n";
        $systemPrompt .= "Gib das KOMPLETTE optimierte Briefing als HTML zurück, ohne zusätzliche Erklärungen.\n";
        $systemPrompt .= "Integriere die Erkenntnisse aus den Änderungen ins Briefing.\n";

        $messages = [
            ['role' => 'user', 'content' => 'Optimiere das Briefing basierend auf den bisherigen Erfahrungen.']
        ];

        try {
            $result = $aiService->chat($messages, $systemPrompt);

            // Optimiertes Briefing speichern
            $db->update('orders', [
                'briefing_content' => $result['content']
            ], 'id = ?', [$orderId]);

            $usageTracker->log(
                $order['customer_id'],
                Auth::id(),
                'order_optimize_briefing',
                $model,
                $provider,
                $result['tokens']['input'] ?? 0,
                $result['tokens']['output'] ?? 0,
                0,
                ['order_id' => $orderId]
            );

            Response::success([
                'briefing_content' => $result['content']
            ], 'Briefing optimiert');

        } catch (\Exception $e) {
            Response::error('Briefing-Optimierung fehlgeschlagen: ' . $e->getMessage());
        }
        break;

    default:
        Response::notFound('Aktion nicht gefunden');
}
