<?php
/**
 * Chat API - KI-Interaktion
 */

use Core\Auth;
use Core\Response;

global $db, $input;

// Services laden
require_once SERVICES_PATH . '/AIService.php';
require_once SERVICES_PATH . '/UsageTracker.php';

// Eingaben validieren
$projectId = $input['project_id'] ?? null;
$message = $input['message'] ?? '';
$sectionId = $input['section_id'] ?? null;
$model = $input['model'] ?? null;
$ruleIds = $input['rule_ids'] ?? null;  // Optional: Spezifische Regeln
$knowledgeIds = $input['knowledge_ids'] ?? null;  // Optional: Spezifisches Wissen
$isRegenerate = $input['is_regenerate'] ?? false;  // Für Section-Regenerierung

if (empty($message)) {
    Response::error('Nachricht erforderlich');
}

// Projekt prüfen (falls angegeben)
if ($projectId) {
    $project = $db->queryOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if (!$project || !Auth::canAccessCustomer($project['customer_id'])) {
        Response::forbidden();
    }
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// Model bestimmen
$model = $model ?: $settings['default_model'] ?? 'gpt-4';
// Provider verbindlich aus ai_models.provider (nicht aus dem Namen raten)
$modelRow = $db->queryOne("SELECT provider FROM ai_models WHERE model_id = ?", [$model]);
if ($modelRow && !empty($modelRow['provider'])) {
    $provider = $modelRow['provider'];
} else {
    $provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
}
$providerKeys = [
    'openai' => $settings['openai_api_key'] ?? '',
    'anthropic' => $settings['anthropic_api_key'] ?? '',
    'google' => $settings['google_api_key'] ?? '',
    'local' => $settings['local_api_key'] ?? '',
];
$apiKey = $providerKeys[$provider] ?? '';

if (empty($apiKey)) {
    Response::error('API-Key für ' . $provider . ' nicht konfiguriert');
}

// Kontext sammeln (Regeln, Wissen, etc.)
$customerId = $projectId ? $project['customer_id'] : Auth::customerId();

// Für Admins ohne customer_id: ersten verfügbaren Kunden nutzen
if (!$customerId && Auth::isAdmin()) {
    $defaultCustomer = $db->queryOne("SELECT id FROM customers WHERE is_active = 1 ORDER BY id LIMIT 1");
    $customerId = $defaultCustomer['id'] ?? null;
}

$systemPrompt = "Du bist ein hilfreicher Assistent für Content-Erstellung. Antworte auf Deutsch.";

// Kundenkontext laden
if ($customerId) {
    $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if ($customer) {
        $contextParts = [];
        if (!empty($customer['name'])) $contextParts[] = "Unternehmen: " . $customer['name'];
        if (!empty($customer['description'])) $contextParts[] = "Beschreibung: " . $customer['description'];
        if (!empty($customer['industry'])) $contextParts[] = "Branche: " . $customer['industry'];
        if (!empty($customer['target_audience'])) $contextParts[] = "Zielgruppe: " . $customer['target_audience'];
        if (!empty($customer['tone_of_voice'])) {
            $toneLabels = [
                'formal' => 'Formal (Sie-Form)',
                'informal' => 'Informal (Du-Form)',
                'professional' => 'Professionell-sachlich',
                'friendly' => 'Freundlich und nahbar'
            ];
            $contextParts[] = "Tonalität: " . ($toneLabels[$customer['tone_of_voice']] ?? $customer['tone_of_voice']);
        }
        if (!empty($contextParts)) {
            $systemPrompt .= "\n\nUNTERNEHMENSPROFIL:\n" . implode("\n", $contextParts);
        }
    }
}

// Regeln laden - entweder spezifische IDs oder alle für den Kunden
if (!empty($ruleIds) && is_array($ruleIds)) {
    // Spezifische Regeln (z.B. bei Section-Regenerierung)
    $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
    $rules = $db->query(
        "SELECT rule_content FROM rules WHERE id IN ($placeholders) AND is_active = 1 ORDER BY priority DESC",
        array_map('intval', $ruleIds)
    );
} else {
    // Alle Regeln für den Kunden
    $rules = $db->query(
        "SELECT rule_content FROM rules
         WHERE (customer_id = ? OR customer_id IS NULL) AND is_active = 1
         ORDER BY priority DESC",
        [$customerId]
    );
}

if (!empty($rules)) {
    $systemPrompt .= "\n\nWICHTIG - Befolge diese Regeln:\n";
    foreach ($rules as $rule) {
        $systemPrompt .= "- " . $rule['rule_content'] . "\n";
    }
}

// Wissen laden (falls spezifische IDs übergeben)
if (!empty($knowledgeIds) && is_array($knowledgeIds)) {
    $placeholders = implode(',', array_fill(0, count($knowledgeIds), '?'));
    $knowledgeEntries = $db->query(
        "SELECT ke.title, ke.content, ke.usage_context
         FROM knowledge_entries ke
         WHERE ke.id IN ($placeholders)",
        array_map('intval', $knowledgeIds)
    );

    if (!empty($knowledgeEntries)) {
        $systemPrompt .= "\n\nHINTERGRUNDWISSEN - Nutze diese Informationen:\n";
        foreach ($knowledgeEntries as $entry) {
            if ($entry['title']) $systemPrompt .= "### " . $entry['title'] . "\n";
            if ($entry['usage_context']) $systemPrompt .= "[Anweisung: " . $entry['usage_context'] . "]\n";
            $systemPrompt .= $entry['content'] . "\n---\n";
        }
    }
}

// AI Service
$ai = new \Services\AIService($apiKey, $provider);
$ai->setModel($model);
if ($provider === 'local') {
    $ai->configureLocal($settings['local_base_url'] ?? 'https://ki.thoxan.com/llm/v1');
    // Server-Context-Default = 16384 -> Completion auf 4096 deckeln (~12000 fuer Prompt)
    $ai->setMaxTokens(4096);
}

try {
    // Chat-Verlauf laden (falls Projekt)
    $messages = [];
    if ($projectId) {
        $history = $db->query(
            "SELECT role, content FROM chat_messages
             WHERE project_id = ?
             ORDER BY created_at ASC
             LIMIT 20",
            [$projectId]
        );
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }

    // Neue Nachricht hinzufügen
    $messages[] = ['role' => 'user', 'content' => $message];

    // Anfrage senden
    $response = $ai->chat($messages, $systemPrompt);

    // Nachricht speichern (falls Projekt)
    if ($projectId) {
        // User-Nachricht
        $db->insert('chat_messages', [
            'project_id' => $projectId,
            'role' => 'user',
            'content' => $message,
            'section_context' => $sectionId,
            'model_used' => $model
        ]);

        // Assistant-Nachricht
        $db->insert('chat_messages', [
            'project_id' => $projectId,
            'role' => 'assistant',
            'content' => $response['content'],
            'tokens_used' => $response['tokens']['total'] ?? 0,
            'model_used' => $model
        ]);
    }

    // Verbrauch tracken (nur wenn Kunde vorhanden)
    if ($customerId) {
        $tracker = new \Services\UsageTracker($db);
        $tracker->log(
            $customerId,
            Auth::id(),
            'chat',
            $model,
            $provider,
            $response['tokens']['input'] ?? 0,
            $response['tokens']['output'] ?? 0,
            \Services\AIService::countWords($response['content'])
        );
    }

    Response::success([
        'content' => $response['content'],
        'tokens' => $response['tokens'],
        'model' => $model
    ]);

} catch (Exception $e) {
    Response::error('KI-Fehler: ' . $e->getMessage());
}
