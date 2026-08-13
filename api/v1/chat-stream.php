<?php
/**
 * Chat Stream API — SSE Streaming fuer Chat-Antworten
 */

use Core\Auth;
use Core\Response;
use Services\AIService;
use Services\UsageTracker;

global $db, $method, $input, $uri;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Route parsen
if (!preg_match('#^/chat/conversations/(\d+)/stream$#', $uri, $matches)) {
    Response::notFound('Endpunkt nicht gefunden');
}

$conversationId = (int) $matches[1];
$userId = Auth::id();

// Conversation laden
$conv = $db->queryOne("SELECT * FROM chat_conversations WHERE id = ?", [$conversationId]);
if (!$conv) {
    Response::notFound('Konversation nicht gefunden');
}

// Zugriffspruefung: Owner, „fuer alle freigegeben" oder Write-Share
$canWrite = false;
if ($conv['user_id'] === $userId) {
    $canWrite = true;
} elseif (
    !empty($conv['write_open']) && empty($conv['is_private'])
    && ((int)($conv['customer_id'] ?? 0) === 0 || \Core\Auth::canAccessCustomer((int)$conv['customer_id']))
) {
    // Fuer alle freigegeben — aber nur fuer User, die den Kunden des Chats sehen duerfen
    $canWrite = true;
} else {
    // Direct write share
    $share = $db->queryOne(
        "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ? AND permission = 'write'",
        [$userId, $conversationId]
    );
    if ($share) {
        $canWrite = true;
    } elseif ($conv['project_id']) {
        $projShare = $db->queryOne(
            "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'project' AND target_id = ? AND permission = 'write'",
            [$userId, $conv['project_id']]
        );
        if ($projShare) $canWrite = true;
    }
}

if (!$canWrite) {
    Response::forbidden('Kein Schreibzugriff');
}

$message = trim($input['message'] ?? '');
if (empty($message)) {
    Response::error('Nachricht erforderlich');
}

$requestedModel = $input['model'] ?? null;
$attachments = $input['attachments'] ?? [];
// (useArtifacts entfernt — altes System)
$useWebSearch = !empty($input['use_web_search']);
$useKnowledge = !empty($input['use_knowledge']);
// Wissens-Engine: immer V2 (bge-m3 + Qdrant). V1 (OpenAI + MariaDB) ist abgelöst.
$knowledgeEngine = 'v2';
$useRules = !empty($input['use_rules']); // Default: aus — Regeln-Feature ist deprecated
$dualMode = !empty($input['dual_mode']);
$dualGroupId = $input['dual_group_id'] ?? null;
$dualSlot = $input['dual_slot'] ?? null; // 'a' oder 'b'

// Session SOFORT freigeben — wichtig fuer Dual-Chat und parallele Requests
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// Modell bestimmen: Request → Conversation-Default → Settings-Default
$model = $requestedModel ?? $conv['model'] ?? $settings['default_model'] ?? 'gpt-4';

// ===== AUTO-MODE =====
$autoModeUsed = false;
$autoReason = '';

if ($model === 'auto') {
    // Pruefen ob bereits Nachrichten existieren → Modell der letzten Assistant-Antwort wiederverwenden
    $lastAssistantModel = $db->queryOne(
        "SELECT model_used FROM chat_conversation_messages WHERE conversation_id = ? AND role = 'assistant' AND model_used IS NOT NULL ORDER BY id DESC LIMIT 1",
        [$conversationId]
    );
    if ($lastAssistantModel && !empty($lastAssistantModel['model_used'])) {
        // Modell aus vorheriger Antwort uebernehmen — keine erneute Klassifikation
        $model = $lastAssistantModel['model_used'];
        $autoModeUsed = true;
        $autoReason = 'Modell aus vorheriger Nachricht uebernommen';
    } else {
    // 1. Guenstigstes Modell fuer Klassifikation
    // Lokale Modelle (Kosten 0) als Klassifizierer ausschliessen — Cold-Start/Serialisierung
    $cheapModel = $db->queryOne(
        "SELECT model_id, provider FROM ai_models WHERE is_active = 1 AND provider != 'local' ORDER BY cost_input ASC LIMIT 1"
    );
    $classifierModelId = $cheapModel['model_id'] ?? 'gpt-4';
    $classifierProvider = $cheapModel['provider'] ?? 'openai';
    $classifierApiKey = $classifierProvider === 'anthropic'
        ? ($settings['anthropic_api_key'] ?? '')
        : ($settings['openai_api_key'] ?? '');

    // Fallback: anderen Provider nutzen wenn Key fehlt
    if (empty($classifierApiKey)) {
        $altProvider = $classifierProvider === 'anthropic' ? 'openai' : 'anthropic';
        $classifierApiKey = $altProvider === 'anthropic'
            ? ($settings['anthropic_api_key'] ?? '')
            : ($settings['openai_api_key'] ?? '');
        if (!empty($classifierApiKey)) {
            $altModel = $db->queryOne(
                "SELECT model_id FROM ai_models WHERE is_active = 1 AND provider = ? ORDER BY cost_input ASC LIMIT 1",
                [$altProvider]
            );
            if ($altModel) {
                $classifierModelId = $altModel['model_id'];
                $classifierProvider = $altProvider;
            }
        }
    }

    // 2. Verfuegbare Chat-Modelle + Namespaces
    $availableModels = $db->query(
        "SELECT model_id, display_name, provider, cost_input FROM ai_models WHERE is_active = 1 AND chat_enabled = 1 ORDER BY cost_input"
    );
    $modelList = array_map(fn($m) => $m['model_id'] . ' (' . $m['display_name'] . ', $' . $m['cost_input'] . '/1M)', $availableModels);

    // 3. Classifier-Prompt (minimal, JSON-only)
    $classifierUserMsg = "Nachricht: \"" . mb_substr($message, 0, 500) . "\"\n\n"
        . "Modelle:\n" . implode("\n", $modelList) . "\n\n"
        . "Antworte NUR mit JSON: {\"model\":\"<model_id>\",\"reason\":\"<kurz>\"}\n"
        . "Regeln: Waehle das guenstigste Modell das die Aufgabe gut loest. Einfache Fragen → guenstiges Modell. Komplexe Analyse/Code → staerkeres Modell.";

    // 4. Sync-Call an Classifier
    if (!empty($classifierApiKey)) {
        $classifierAi = new AIService($classifierApiKey, $classifierProvider);
        $classifierAi->setModel($classifierModelId);
        $classifierAi->setMaxTokens(200);
        $classifierAi->setTimeout(10);

        $classStart = microtime(true);
        try {
            $classResult = $classifierAi->chat(
                [['role' => 'user', 'content' => $classifierUserMsg]],
                \Services\SystemPromptService::get('auto_classifier')
            );
            $classTotalMs = (int) round((microtime(true) - $classStart) * 1000);

            $classJson = json_decode($classResult['content'] ?? '', true);
            if (!$classJson) {
                // Fallback: JSON aus Markdown extrahieren
                if (preg_match('/\{[^}]+\}/', $classResult['content'] ?? '', $jsonMatch)) {
                    $classJson = json_decode($jsonMatch[0], true);
                }
            }

            if ($classJson && !empty($classJson['model'])) {
                $validModel = $db->queryOne(
                    "SELECT model_id FROM ai_models WHERE model_id = ? AND is_active = 1 AND chat_enabled = 1",
                    [$classJson['model']]
                );
                $model = $validModel ? $classJson['model'] : ($settings['default_model'] ?? 'gpt-4');
            } else {
                $model = $settings['default_model'] ?? 'gpt-4';
            }

            $autoModeUsed = true;
            $autoReason = $classJson['reason'] ?? '';

            // Classifier-Usage tracken
            try {
                require_once SERVICES_PATH . '/UsageTracker.php';
                $classTracker = new UsageTracker($db);
                $classTracker->log(
                    $conv['customer_id'],
                    $userId,
                    'chat_auto_classify',
                    $classifierModelId,
                    $classifierProvider,
                    $classResult['tokens']['input'] ?? 0,
                    $classResult['tokens']['output'] ?? 0,
                    0,
                    ['conversation_id' => $conversationId, 'selected_model' => $model]
                );
            } catch (\Exception $e) {
                // Ignore tracking errors
            }

            // Performance-Log (Classifier)
            \Services\LlmRequestLog::record([
                'provider' => $classifierProvider,
                'model' => $classifierModelId,
                'use_case' => 'auto_classify',
                'user_id' => $userId,
                'customer_id' => $conv['customer_id'] ?? null,
                'tokens_input' => $classResult['tokens']['input'] ?? 0,
                'tokens_output' => $classResult['tokens']['output'] ?? 0,
                'total_ms' => $classTotalMs,
                'success' => true,
            ]);

        } catch (\Exception $e) {
            // Klassifikation fehlgeschlagen → Fallback
            $model = $settings['default_model'] ?? 'gpt-4';
            $autoModeUsed = true;
            $autoReason = 'Klassifikation fehlgeschlagen: ' . $e->getMessage();
            \Services\LlmRequestLog::record([
                'provider' => $classifierProvider,
                'model' => $classifierModelId,
                'use_case' => 'auto_classify',
                'user_id' => $userId,
                'customer_id' => $conv['customer_id'] ?? null,
                'total_ms' => (int) round((microtime(true) - $classStart) * 1000),
                'success' => false,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);
        }
    } else {
        // Kein API-Key verfuegbar
        $model = $settings['default_model'] ?? 'gpt-4';
        $autoModeUsed = true;
        $autoReason = 'Kein API-Key fuer Klassifikation';
    }
    } // end else (erste Nachricht → Klassifikation)
}
// ===== END AUTO-MODE =====

// Provider + API-Key (nach Auto-Mode steht das finale Modell fest).
// Provider verbindlich aus ai_models.provider bestimmen — NICHT aus dem Namen raten
// (sonst landen lokale Modelle wie 'gpt-oss:20b'/'llama3.1:8b' faelschlich bei OpenAI).
$modelRow = $db->queryOne("SELECT provider FROM ai_models WHERE model_id = ?", [$model]);
if ($modelRow && !empty($modelRow['provider'])) {
    $provider = $modelRow['provider'];
} elseif (strpos($model, 'claude') !== false) {
    $provider = 'anthropic';
} elseif (strpos($model, 'gemini') !== false) {
    $provider = 'google';
} else {
    $provider = 'openai';
}
$providerKeys = [
    'openai' => $settings['openai_api_key'] ?? '',
    'anthropic' => $settings['anthropic_api_key'] ?? '',
    'google' => $settings['google_api_key'] ?? '',
    'local' => $settings['local_api_key'] ?? '',
];
$apiKey = $providerKeys[$provider] ?? '';

if (empty($apiKey)) {
    Response::error('API-Key fuer ' . $provider . ' nicht konfiguriert.');
}

// User-Message in DB speichern (bei Dual nur Slot A)
$attachmentsJson = !empty($attachments) ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : null;
if (!$dualMode || $dualSlot === 'a') {
    $db->insert('chat_conversation_messages', [
        'conversation_id' => $conversationId,
        'role' => 'user',
        'sender_user_id' => $userId,
        'content' => $message,
        'model_used' => $model,
        'attachments' => $attachmentsJson,
        'dual_group_id' => $dualGroupId,
    ]);
}

// Auto-Titel wenn "Neuer Chat" (bei Dual nur Slot A)
if ((!$dualMode || $dualSlot === 'a') && $conv['title'] === 'Neuer Chat') {
    $autoTitle = mb_substr(preg_replace('/\s+/', ' ', $message), 0, 60);
    if (mb_strlen($message) > 60) $autoTitle .= '...';
    $db->update('chat_conversations', ['title' => $autoTitle], 'id = ?', [$conversationId]);
}

// Messages-Array aufbauen (bei Dual: nur Haupt-Nachrichten, keine parallelen Antworten)
if ($dualMode) {
    $dbMessages = $db->query(
        "SELECT role, content, attachments FROM chat_conversation_messages
         WHERE conversation_id = ? AND (dual_slot IS NULL OR dual_slot = 'a')
         ORDER BY created_at ASC",
        [$conversationId]
    );
} else {
    $dbMessages = $db->query(
        "SELECT role, content, attachments FROM chat_conversation_messages WHERE conversation_id = ? ORDER BY created_at ASC",
        [$conversationId]
    );
}

$aiMessages = [];
$totalChars = 0;
$maxChars = 100000;

foreach ($dbMessages as $dbMsg) {
    $content = $dbMsg['content'];

    // Attachments an Content anhaengen
    if ($dbMsg['attachments']) {
        $atts = json_decode($dbMsg['attachments'], true);
        if (is_array($atts)) {
            foreach ($atts as $att) {
                $content .= "\n\n---\n[Datei: " . ($att['filename'] ?? 'Dokument') . "]\n" . ($att['extracted_text'] ?? '') . "\n---";
            }
        }
    }

    $totalChars += mb_strlen($content);
    $aiMessages[] = ['role' => $dbMsg['role'], 'content' => $content];
}

// Sicherheit: Wenn Messages leer (Race Condition bei Dual-Chat), aktuelle Message einfuegen
if (empty($aiMessages) || !array_filter($aiMessages, fn($m) => $m['role'] === 'user')) {
    $aiMessages[] = ['role' => 'user', 'content' => $message];
}

// Kontext begrenzen: aelteste Nachrichten kuerzen wenn noetig
if ($totalChars > $maxChars) {
    $excess = $totalChars - $maxChars;
    for ($i = 0; $i < count($aiMessages) - 2 && $excess > 0; $i++) {
        $len = mb_strlen($aiMessages[$i]['content']);
        if ($len <= $excess) {
            $aiMessages[$i]['content'] = '[... gekuerzt ...]';
            $excess -= $len;
        } else {
            $aiMessages[$i]['content'] = mb_substr($aiMessages[$i]['content'], $excess) . "\n[... Anfang gekuerzt ...]";
            $excess = 0;
        }
    }
}

// System-Prompt
// Reihenfolge: 1) eigener Prompt der Konversation, 2) globaler Standard aus der
// zentralen Prompt-Verwaltung (/admin/settings?tab=prompts → chat_default).
$globalDefaultPrompt = \Services\SystemPromptService::get('chat_default');
$systemPrompt = (isset($conv['system_prompt']) && trim((string)$conv['system_prompt']) !== '')
    ? $conv['system_prompt']
    : $globalDefaultPrompt;

// ===== USER-KONTEXT — wer fragt gerade? =====
// Damit Fragen wie "was sind meine offenen Aufgaben bei Wittekind" funktionieren,
// muss die KI den aktiven Nutzer kennen. Asana-Task-Wissen enthaelt Assignee-Daten —
// die KI kann dann selbst matchen ohne dass wir das im Retrieval filtern muessen.
$currentUser = Auth::user();
if ($currentUser) {
    $userBlock = "AKTUELLER NUTZER (das ist die Person die gerade schreibt):\n";
    $userBlock .= "- Name (in dieser App): " . ($currentUser['name'] ?? '-') . "\n";
    $userBlock .= "- App-E-Mail: " . ($currentUser['email'] ?? '-') . "\n";
    if (!empty($currentUser['role'])) $userBlock .= "- Rolle: " . $currentUser['role'] . "\n";

    // Asana-Identitaet ist OFT eine ANDERE als App-Identitaet — die Asana-Daten zaehlen fuer Task-Matching
    if (!empty($currentUser['asana_user_gid'])) {
        $userBlock .= "- Asana-User-GID: " . $currentUser['asana_user_gid'] . "\n";
        if (!empty($currentUser['asana_user_name'])) {
            $userBlock .= "- Name in Asana: " . $currentUser['asana_user_name'] . "\n";
        }
        if (!empty($currentUser['asana_user_email'])) {
            $userBlock .= "- E-Mail in Asana: " . $currentUser['asana_user_email'] . "\n";
        }
    }
    $userBlock .= "- Heute: " . date('d.m.Y, l') . "\n";

    // Aktiver Kunden-Kontext (falls Conv mit Kunde verknuepft)
    if (!empty($conv['customer_id'])) {
        $cust = $db->queryOne("SELECT name, abbreviation FROM customers WHERE id = ?", [$conv['customer_id']]);
        if ($cust) {
            $userBlock .= "- Aktiver Kunden-Kontext: " . $cust['name'];
            if (!empty($cust['abbreviation'])) $userBlock .= " (" . $cust['abbreviation'] . ")";
            $userBlock .= "\n";
        }
    }

    $userBlock .= "\nWICHTIG: Wenn der Nutzer 'ich', 'mein', 'mir', 'meine' etc. nutzt, bezieht sich das auf diese Person.";
    if (!empty($currentUser['asana_user_gid'])) {
        $userBlock .= "\nAsana-Tasks im Wissen enthalten 'Zugewiesen an: NAME <email>'. ";
        $userBlock .= "Fuer das Matching der EIGENEN Tasks nutze die ASANA-Identitaet";
        if (!empty($currentUser['asana_user_email'])) {
            $userBlock .= " (E-Mail: " . $currentUser['asana_user_email'] . ")";
        }
        $userBlock .= ", NICHT die App-E-Mail. App- und Asana-E-Mail koennen sich unterscheiden.";
    } else {
        $userBlock .= "\nFuer Asana-Tasks gibt es keine Verknuepfung im Profil. Wenn der Nutzer 'meine Tasks' fragt, ";
        $userBlock .= "weise darauf hin dass er sein Asana-Profil unter Mein Konto verknuepfen sollte.";
    }

    $systemPrompt .= "\n\n" . $userBlock;
}

// ===== CUSTOMER DETECTION — laeuft bei JEDER User-Message wenn Wissen aktiv =====
// Damit das Retrieval immer den aktuell-relevanten Kunden treffen kann, auch wenn der Chat-Kunde
// nicht passt (z.B. Privat-Chat mit Frage zu einem Kunden).
// SSE-Event wird spaeter (nach SSE-Setup) gesendet.
// Originalen Chat-Kunden merken, BEVOR er fuers Retrieval ueberschrieben wird —
// damit das Banner nur erscheint, wenn ein ANDERER Kunde erkannt wird (s. unten).
$convCustomerIdBefore = (int) ($conv['customer_id'] ?? 0);
$detectedCustomer = null;
if ($useKnowledge) {
    try {
        require_once SERVICES_PATH . '/CustomerDetectionService.php';
        $openaiKey = $settings['openai_api_key'] ?? '';
        $detectAI = !empty($openaiKey) ? new \Services\AIService($openaiKey, 'openai') : null;
        $detector = new \Services\CustomerDetectionService($db, $detectAI);

        // Detection nur ueber die fuer diesen User erlaubten Kunden — verhindert
        // dass ein Manager (z.B. Christian) FRYKA „erkannt" bekommt, obwohl er
        // gar keinen Zugriff hat.
        $detectionPool = null;
        if (!Auth::isAdmin()) {
            $allowedIds = array_map(fn($c) => (int)$c['id'], Auth::customers());
            if (empty($allowedIds)) {
                $detectionPool = []; // nichts erkennen
            } else {
                $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
                $detectionPool = $db->query(
                    "SELECT id, name, slug, abbreviation FROM customers
                     WHERE is_active = 1 AND id IN ($placeholders)",
                    $allowedIds
                ) ?: [];
            }
        }
        $detectedCustomer = $detector->detectCustomer($message, $detectionPool);
        if ($detectedCustomer) {
            // Erkannter Kunde hat Vorrang fuer Retrieval (nicht fuer conv-Persistenz!).
            $conv['customer_id'] = $detectedCustomer['customer_id'];
        }
    } catch (\Exception $e) {
        error_log('Customer-Detection-Error: ' . $e->getMessage());
    }
}

// ===== REGELN — deterministisch, opt-out via Toggle =====
// (Guidelines wurden in rules migriert — siehe scripts/migrate-guidelines-to-rules.php)
if ($useRules) {
    try {
        // Eigene + globale Regeln, gefiltert per Geltungsbereich (rule_project_types ⇔ customer_project_types),
        // abzgl. kundenspezifischer Overrides (customer_rules.is_active=0).
        $ruleCustomerId = $conv['customer_id'] ?? null;
        if ($ruleCustomerId) {
            $rules = $db->query(
                "SELECT rule_content, enforcement, applies_to FROM rules r
                 WHERE r.is_active = 1
                   AND (
                       r.customer_id = ?
                       OR (r.customer_id IS NULL AND (
                           NOT EXISTS (SELECT 1 FROM rule_project_types rpt WHERE rpt.rule_id = r.id)
                           OR EXISTS (SELECT 1 FROM rule_project_types rpt
                               JOIN customer_project_types cpt ON cpt.project_type_id = rpt.project_type_id
                               WHERE rpt.rule_id = r.id AND cpt.customer_id = ?)
                           OR EXISTS (SELECT 1 FROM customer_rules cr2
                               WHERE cr2.customer_id = ? AND cr2.rule_id = r.id AND cr2.is_active = 1)
                       ))
                   )
                   AND r.id NOT IN (SELECT rule_id FROM customer_rules WHERE customer_id = ? AND is_active = 0)
                 ORDER BY r.priority DESC, r.name",
                [$ruleCustomerId, $ruleCustomerId, $ruleCustomerId, $ruleCustomerId]
            );
        } else {
            $rules = $db->query(
                "SELECT rule_content, enforcement, applies_to FROM rules WHERE is_active = 1 AND customer_id IS NULL ORDER BY priority DESC, name"
            );
        }
        if (!empty($rules)) {
            // Vierteilung: Hauptachse = enforcement (strikt/empfehlung), Sekundaer = applies_to via Marker pro Regel.
            // Marker pro Regel zeigt der KI, WO die Regel greift — [Tool], [Content], oder kein Marker = beides.
            $strictRules = [];
            $softRules = [];
            $applyLabel = function (string $applies): string {
                if ($applies === 'tool')    return ' [nur Tool-Antworten]';
                if ($applies === 'content') return ' [nur erzeugte Inhalte]';
                return ''; // both: kein Marker — gilt ueberall
            };
            foreach ($rules as $rule) {
                $rc = trim($rule['rule_content'] ?? '');
                if ($rc === '') continue;
                $marker = $applyLabel($rule['applies_to'] ?? 'both');
                $line = $rc . $marker;
                if (($rule['enforcement'] ?? 'strict') === 'soft') $softRules[] = $line;
                else $strictRules[] = $line;
            }
            if (!empty($strictRules)) {
                $systemPrompt .= "\n\nREGELN (STRIKT) — diese MUESSEN eingehalten werden. Verletzungen sind Fehler. Marker am Ende einer Regel zeigt den Wirkungsbereich an — ohne Marker gilt die Regel sowohl fuer Deine Tool-Antworten als auch fuer erzeugte Inhalte:\n";
                foreach ($strictRules as $rc) $systemPrompt .= "- " . $rc . "\n";
                $systemPrompt .= "\nAusnahme: Wenn der User in seinem aktuellen Prompt EXPLIZIT und unmissverstaendlich verlangt, dass eine bestimmte strikte Regel ignoriert werden soll, darfst Du dem folgen. In diesem Fall MUSST Du am ANFANG Deiner Antwort einen deutlichen Warnhinweis ausgeben im Format:\n";
                $systemPrompt .= "\n> **Warnung — strikte Regel auf Nutzerwunsch ignoriert:**\n> - <Regelinhalt in Kurzform>: auf ausdruecklichen Wunsch des Users.\n";
                $systemPrompt .= "\nOhne expliziten Nutzerwunsch ist eine strikte Regel unverletzlich. Eigene Abwaegungen (z.B. \"das wuerde den Text schlechter machen\") sind KEIN ausreichender Grund zur Verletzung.\n";
            }
            if (!empty($softRules)) {
                $systemPrompt .= "\n\nEMPFEHLUNGEN — diese SOLLTEN befolgt werden. Bei direktem Konflikt mit den strikten Regeln, der Klarheit oder dem Nutzen des Textes darf abgewichen werden. Marker am Ende einer Empfehlung zeigt den Wirkungsbereich an — ohne Marker gilt sie ueberall:\n";
                foreach ($softRules as $rc) $systemPrompt .= "- " . $rc . "\n";
                $systemPrompt .= "\nWenn Du von einer oder mehreren Empfehlungen abweichst: Haenge am ENDE Deiner Antwort einen kurzen Hinweis-Block im folgenden Format an:\n";
                $systemPrompt .= "\n> **Hinweis zu Empfehlungen:**\n> - <Empfehlung in Kurzform>: <kurze Begruendung, 1 Satz>\n";
                $systemPrompt .= "\nHaenge NICHTS an, wenn Du alle Empfehlungen eingehalten hast.\n";
            }
        }
    } catch (\Exception $e) {
        error_log('Rules-Load-Error: ' . $e->getMessage());
    }
}

// ===== KNOWLEDGE (RAG) — Hybrid Retrieval =====
$knowledgeUsed = [];      // schlanke Metadaten fuers Frontend (Titel/Score/Quelle)
$knowledgeForLog = [];    // inkl. Chunk-Inhalt — nur fuers Detail-Log, nicht ans Frontend
$rerankMeta = null;       // Rerank-Protokoll pro Nachricht (Anbieter/Modell/volle Rangliste)
if ($useKnowledge) {
    try {
        require_once SERVICES_PATH . '/QdrantClient.php';
        require_once SERVICES_PATH . '/LocalEmbeddingService.php';
        require_once SERVICES_PATH . '/KnowledgeRetrievalServiceV2.php';

        // Wissensdatenbank V2: bge-m3 (lokal über ki.thoxan.com) + Qdrant.
        // Kein V1-Fallback — wenn lokal nicht erreichbar, sieht der User eine klare Fehlermeldung.
        $localKey = $settings['local_api_key'] ?? '';
        $kbDim = (int)($settings['embedding_local_dim'] ?? 1024) ?: 1024;
        if (empty($localKey)) {
            throw new \RuntimeException('Lokaler Embedding-API-Key (local_api_key) ist nicht konfiguriert');
        }
        $kbQdrant = new \Services\QdrantClient(
            $settings['qdrant_url'] ?? 'http://localhost:6333',
            $settings['qdrant_api_key'] ?? '',
            'knowledge_bge_m3',
            $kbDim
        );
        $kbEmbedder = new \Services\LocalEmbeddingService(
            $settings['embedding_local_url'] ?? 'https://ki.thoxan.com/embeddings/embeddings',
            $localKey,
            $settings['embedding_local_model'] ?? 'bge-m3',
            $kbDim
        );
        $kbRetrieval = new \Services\KnowledgeRetrievalServiceV2($db, $kbQdrant, $kbEmbedder);

        {
            $kbCustomerId = $conv['customer_id'] ?? null;
            // Sicherheits-Filter: Non-Admin sieht nur Wissen aus seinen effektiven
            // Kunden (direkt + ueber Rolle) plus globales Wissen (customer_id NULL).
            $allowedCustomerIds = null;
            if (!Auth::isAdmin()) {
                $allowedCustomerIds = array_map(fn($c) => (int)$c['id'], Auth::customers());
                // Wenn der Chat einen bestimmten Kunden hat, der NICHT in der erlaubten
                // Liste ist, ignorieren — der User darf hier nicht ran. Wir setzen den
                // Kunden-Filter auf NULL (Suche im erlaubten Pool + Global).
                if ($kbCustomerId !== null && !in_array((int)$kbCustomerId, $allowedCustomerIds, true)) {
                    $kbCustomerId = null;
                }
            }
            // Reranking aktiv? Dann breiter retrieven (mehr Kandidaten), damit der Reranker
            // wirklich aussortieren kann. Sonst wie bisher die Top 10.
            $rerankEnabled = !empty($settings['rerank_enabled']) && $settings['rerank_enabled'] !== '0';
            $retrieveK = $rerankEnabled ? max(10, (int)($settings['rerank_candidates'] ?? 40)) : 10;
            $kbChunks = $kbRetrieval->retrieve($message, $kbCustomerId, $retrieveK, $allowedCustomerIds);

            if (!empty($kbChunks)) {
                // Duplikate entfernen — die Wissensbasis liefert oft denselben Inhalt mehrfach
                // (z.B. Impressum). Verrauschter Kontext bringt schwaechere Modelle zum Halluzinieren.
                $seenKb = [];
                $kbDeduped = [];
                foreach ($kbChunks as $c) {
                    $sig = md5(mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)($c['content'] ?? '')))));
                    if (isset($seenKb[$sig])) continue;
                    $seenKb[$sig] = true;
                    $kbDeduped[] = $c;
                }
                $kbChunks = $kbDeduped;

                // ===== Reranking — die "Bibliothekarin": Kandidaten nach echter Relevanz
                // neu sortieren und nur die Top-N behalten (siehe docs/rag-optimierung.md).
                $rerankApplied = false;
                $rerankTopN = (int)($settings['rerank_top_n'] ?? 8) ?: 8;
                if ($rerankEnabled && count($kbChunks) > 1) {
                    $rkProvider = $settings['rerank_provider'] ?? 'local';
                    $rkModel = trim((string)($settings['rerank_model'] ?? '')) ?: \Services\RerankService::defaultModelFor($rkProvider);
                    // Pro-Nachricht-Protokoll — Grundlage fuers Info-Panel und die Optimierung.
                    $rerankMeta = [
                        'enabled'    => true,
                        'provider'   => $rkProvider,
                        'model'      => $rkModel,
                        'candidates' => count($kbChunks),
                        'kept'       => 0,
                        'applied'    => false,
                        'ms'         => null,
                        'ranking'    => [],   // volle Rangliste inkl. aussortierter Kandidaten
                    ];
                    try {
                        require_once SERVICES_PATH . '/RerankService.php';
                        $rkUrl = trim((string)($settings['rerank_url'] ?? '')) ?: \Services\RerankService::defaultUrlFor($rkProvider);
                        // Eigener Rerank-Key, sonst (bei lokal) der lokale Inference-Key.
                        $rkKey = (string)($settings['rerank_api_key'] ?? '');
                        if ($rkKey === '' && $rkProvider === 'local') $rkKey = (string)($settings['local_api_key'] ?? '');

                        if ($rkUrl === '') {
                            $rerankMeta['error'] = 'Keine Rerank-URL konfiguriert';
                        } else {
                            $reranker = new \Services\RerankService($rkProvider, $rkUrl, $rkModel, $rkKey);
                            $docs = array_map(function ($c) {
                                $t = trim((string)($c['title'] ?? ''));
                                return ($t !== '' ? $t . "\n" : '') . (string)($c['content'] ?? '');
                            }, $kbChunks);
                            $rkStart = microtime(true);
                            // ALLE Kandidaten scoren (volle Rangliste fuers Log), erst danach
                            // fuer den Prompt auf rerank_top_n kuerzen.
                            $ranked = $reranker->rerank($message, $docs, count($docs));
                            $rerankMeta['ms'] = (int) round((microtime(true) - $rkStart) * 1000);
                            if (!empty($ranked)) {
                                $reordered = [];
                                foreach ($ranked as $pos => $r) {
                                    if (!isset($kbChunks[$r['index']])) continue;
                                    $c = $kbChunks[$r['index']];
                                    $kept = $pos < $rerankTopN;
                                    $rerankMeta['ranking'][] = [
                                        'title'       => $c['title'] ?? '',
                                        'source_type' => $c['source_type'] ?? null,
                                        'score'       => round($r['score'], 4),
                                        'kept'        => $kept,
                                    ];
                                    if ($kept) {
                                        $c['score'] = round($r['score'], 4);
                                        $c['sources'] = array_values(array_unique(array_merge((array)($c['sources'] ?? []), ['rerank'])));
                                        $reordered[] = $c;
                                    }
                                }
                                if (!empty($reordered)) {
                                    $kbChunks = $reordered;
                                    $rerankApplied = true;
                                    $rerankMeta['applied'] = true;
                                    $rerankMeta['kept'] = count($reordered);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Reranker nicht erreichbar/fehlerhaft -> urspruengliche Reihenfolge behalten.
                        error_log('Rerank-Fehler: ' . $e->getMessage());
                        $rerankMeta['error'] = $e->getMessage();
                    }
                }
                // Breit geholt, aber Rerank hat nicht gegriffen (aus/fehlgeschlagen)?
                // Dann nicht alle Kandidaten in den Prompt kippen — auf Top-N kuerzen.
                if ($rerankEnabled && !$rerankApplied && count($kbChunks) > $rerankTopN) {
                    $kbChunks = array_slice($kbChunks, 0, $rerankTopN);
                }

                // Lokale Modelle (qwen/gpt-oss) sind empfindlich gegen viel/verrauschten Kontext
                // und driften sonst ab (bis hin zu falscher Sprache) -> straffer Kontext + Sprach-Anker.
                $isLocal = ($provider === 'local');
                $maxKbChars = $isLocal ? 6000 : 20000;
                if ($isLocal) {
                    $kbChunks = array_slice($kbChunks, 0, 6);
                }

                // ===== Ganzdokument-Laden fuer Projektplaene UND Reportings =====
                // Ein Plan/Reporting ist in viele kleine Aufgaben-Chunks zerlegt (z.B. FRYKA Q2 =
                // 24 Chunks). Das Top-N-Retrieval kann nie alle laden -> ein vollstaendiges Bild aus
                // verstreuten Einzel-Chunks ist unmoeglich (8 < 24). Loesung: Trifft das Retrieval
                // (nach Rerank) einen Projektplan ODER ein Reporting in den oberen Treffern,
                // ersetzen wir dessen Einzel-Chunks durch den KOMPLETTEN Text als EINEN Block.
                // Nur fuer Cloud-/grosse Modelle — lokale Modelle vertragen den langen Kontext nicht.
                if (!$isLocal && !empty($kbChunks)) {
                    // Distinkte Plan-/Reporting-Dokumente unter den Treffern sammeln (Array-Reihenfolge
                    // = Ranking, bestplatziert zuerst). Fuer REPORTINGS koennen das MEHRERE sein
                    // (Historien-/Sammelfragen "was haben wir fuer X gemacht") — anders als bei Plaenen,
                    // wo pro Frage meist genau einer relevant ist. Wir laden alle komplett, bis ein
                    // Gesamt-Budget erreicht ist, damit eine Sammelfrage nicht "ein Doc voll + Fragmente"
                    // bekommt (die "Scheinvollstaendigkeit"). Nur Cloud-/grosse Modelle.
                    $fullSourceTypes = ['projektplan', 'reporting', 'reporting_summary'];
                    $docOrder = [];      // doc_id => bester Score (erste Nennung = bestes Ranking)
                    foreach ($kbChunks as $c) {
                        if (!in_array($c['source_type'] ?? '', $fullSourceTypes, true)) continue;
                        $did = (int) $c['document_id'];
                        if (!isset($docOrder[$did])) $docOrder[$did] = (float) ($c['score'] ?? 1.0);
                    }
                    if (!empty($docOrder)) {
                        $perDocCap   = 30000;   // ein einzelnes XXL-Dokument nicht ueberborden lassen
                        $totalBudget = 55000;   // Gesamtbudget fuer ALLE Volltext-Bloecke zusammen
                        $usedFull = 0; $loadedIds = []; $fullBlocks = [];
                        foreach ($docOrder as $did => $score) {
                            if ($usedFull >= $totalBudget) break;
                            // Sichtbarkeit auch hier pruefen: dieser Pfad laedt Volltexte an der
                            // Chunk-Suche vorbei. Die IDs stammen zwar aus bereits gefilterten
                            // Treffern, aber ein Volltext-Lader ohne eigenen Filter waere eine
                            // Zeitbombe, sobald ihn jemand anders speist.
                            $doc = $db->queryOne(
                                "SELECT id, title, category, source_type, original_content
                                 FROM knowledge_documents
                                 WHERE id = ? AND is_active = 1
                                   AND (visibility <> 'privat' OR owner_user_id = ?)",
                                [$did, \Core\Auth::id()]
                            );
                            $txt = $doc ? trim((string) $doc['original_content']) : '';
                            if ($txt === '') continue;
                            $cap = min($perDocCap, $totalBudget - $usedFull);
                            if (mb_strlen($txt) > $cap) $txt = mb_substr($txt, 0, $cap) . "\n[... gekuerzt ...]";
                            $usedFull += mb_strlen($txt);
                            $loadedIds[$did] = true;
                            $fullBlocks[] = [
                                'id'          => 0,            // synthetischer Block, kein echter Chunk
                                'document_id' => $did,
                                'title'       => $doc['title'],
                                'category'    => $doc['category'],
                                'content'     => $txt,
                                'score'       => $score,
                                'source_type' => $doc['source_type'],
                                'sources'     => ['volldokument'],
                            ];
                        }
                        if ($fullBlocks) {
                            // Einzel-Chunks der geladenen Dokumente entfernen, Voll-Bloecke nach vorne
                            // (bestbewertet zuerst), Rest der Treffer dahinter.
                            $rest = array_values(array_filter($kbChunks, fn($c) => !isset($loadedIds[(int) $c['document_id']])));
                            $kbChunks = array_merge($fullBlocks, $rest);
                            // Budget so anheben, dass die Voll-Bloecke + ein paar weitere Treffer passen.
                            $maxKbChars = min(60000, max($maxKbChars, $usedFull + 4000));
                        }
                    }
                }

                // ===== Historien-Uebersicht garantiert bereitstellen =====
                // Breite Rueckblick-/Sammelfragen ("was haben wir gemacht", "Ueberblick",
                // "Historie", "ueber die Jahre") werden vom semantischen Retrieval oft vom
                // Asana-Volumen eines Kunden verdraengt — die eigens dafuer gebaute Reporting-
                // Historie taucht dann gar nicht auf. Ist ein Kunde im Kontext UND die Frage
                // retrospektiv, laden wir dessen Reporting-Historie-Doc garantiert komplett dazu.
                if (!$isLocal && $kbCustomerId) {
                    $retroHit = preg_match('/(überblick|ueberblick|historie|rückblick|rueckblick|zusammenfass|über die jahre|ueber die jahre|alle (arbeiten|reportings|quartale|massnahmen|maßnahmen)|was haben wir|was wurde .*(gemacht|umgesetzt|geleistet)|welche arbeiten|bisher(ige)? (arbeiten|leistungen))/iu', (string) $message);
                    $alreadySummary = false;
                    foreach ($kbChunks as $c) if (($c['source_type'] ?? '') === 'reporting_summary' && (int) $c['document_id'] > 0) { $alreadySummary = true; break; }
                    if ($retroHit && !$alreadySummary) {
                        $sumDoc = $db->queryOne(
                            "SELECT id, title, category, original_content
                             FROM knowledge_documents
                             WHERE source_type = 'reporting_summary' AND customer_id = ? AND is_active = 1
                               AND (visibility <> 'privat' OR owner_user_id = ?)
                             LIMIT 1",
                            [$kbCustomerId, \Core\Auth::id()]
                        );
                        $sumTxt = $sumDoc ? trim((string) $sumDoc['original_content']) : '';
                        if ($sumTxt !== '') {
                            if (mb_strlen($sumTxt) > 30000) $sumTxt = mb_substr($sumTxt, 0, 30000) . "\n[... gekuerzt ...]";
                            // Einzel-Chunks dieses Uebersichts-Docs raus, Voll-Block ganz nach vorne.
                            $kbChunks = array_values(array_filter($kbChunks, fn($c) => (int) $c['document_id'] !== (int) $sumDoc['id']));
                            array_unshift($kbChunks, [
                                'id'          => 0,
                                'document_id' => (int) $sumDoc['id'],
                                'title'       => $sumDoc['title'],
                                'category'    => $sumDoc['category'],
                                'content'     => $sumTxt,
                                'score'       => 1.0,
                                'source_type' => 'reporting_summary',
                                'sources'     => ['historie-uebersicht'],
                            ]);
                            $maxKbChars = min(60000, max($maxKbChars, mb_strlen($sumTxt) + 4000));
                        }
                    }
                }

                // Erdungs-Anweisung: schwaechere (lokale) Modelle ignorieren Kontext sonst
                // und halluzinieren aus dem Trainingswissen. Klar anweisen, sich aufs WISSEN zu stuetzen.
                $kbContext = "\n\n" . \Services\SystemPromptService::get('knowledge_grounding') . "\n"
                    . ($isLocal ? "Antworte ausschließlich auf Deutsch.\n" : "")
                    . "\n--- WISSEN ---\n";
                $kbCharsUsed = 0;
                foreach ($kbChunks as $c) {
                    $block = "\n### " . $c['title'] . "\n" . $c['content'] . "\n";
                    if ($kbCharsUsed + mb_strlen($block) > $maxKbChars) break;
                    $kbContext .= $block;
                    $kbCharsUsed += mb_strlen($block);

                    $knowledgeUsed[] = [
                        'chunk_id' => (int) $c['id'],
                        'document_id' => (int) $c['document_id'],
                        'title' => $c['title'],
                        'category' => $c['category'],
                        'score' => round($c['score'], 3),
                        'sources' => $c['sources'],
                    ];

                    // Fuers Detail-Log zusaetzlich Inhalt + Quelle (source_type) festhalten —
                    // so ist im Detail-Log sichtbar, ob z.B. CRM-Mini-Chunks die guten Treffer verdraengen.
                    $knowledgeForLog[] = [
                        'chunk_id' => (int) $c['id'],
                        'document_id' => (int) $c['document_id'],
                        'title' => $c['title'],
                        'source_type' => $c['source_type'] ?? null,
                        'word_count' => isset($c['word_count']) ? (int) $c['word_count'] : null,
                        'score' => round($c['score'], 3),
                        'sources' => $c['sources'],
                        'content' => $c['content'],
                    ];

                    // Usage-Tracking — synthetische Voll-Block-Eintraege (id 0) ueberspringen.
                    if ((int) $c['id'] > 0) {
                        try {
                            $db->insert('knowledge_usage', [
                                'chunk_id' => (int) $c['id'],
                                'conversation_id' => $conversationId,
                                'score' => $c['score'],
                            ]);
                        } catch (\Exception $e) {}
                    }
                }
                $kbContext .= "\n--- ENDE WISSEN ---\n";
                $systemPrompt .= $kbContext;
            }
        }
    } catch (\Throwable $e) {
        // V2 ist Pflicht (kein V1-Fallback). Klare Fehlermeldung an den User —
        // wir sind noch VOR dem SSE-Setup, also normaler JSON-Error.
        error_log('Knowledge Retrieval V2 Fehler: ' . $e->getMessage());
        Response::error(
            'Wissensdatenbank ist gerade nicht erreichbar: ' . $e->getMessage() . '. '
            . 'Bitte den lokalen Embedding-Server (ki.thoxan.com) und Qdrant prüfen — '
            . 'oder ohne "Wissen"-Toggle erneut senden.',
            503
        );
    }
}

// AI-Service
require_once SERVICES_PATH . '/UsageTracker.php';
$ai = new AIService($apiKey, $provider);
$ai->setModel($model);
if ($provider === 'local') {
    $ai->configureLocal($settings['local_base_url'] ?? 'https://ki.thoxan.com/llm/v1');
    // Server-Context-Default = 16384 Tokens -> Completion auf 4096 deckeln,
    // bleiben ~12000 fuer den Prompt. Fuer Spezial-Calls mit langen Dokumenten
    // kann num_ctx pro Request via $ai->setNumCtx() hochgesetzt werden.
    $ai->setMaxTokens(4096);
} else {
    // Output-Limit je Modell aus ai_models.max_tokens (pflegbar unter /admin/models),
    // geklemmt auf das, was die API wirklich akzeptiert. Ohne das lief jeder Chat gegen
    // DEFAULT_MAX_TOKENS (4000) und lange Antworten brachen mitten im Satz ab.
    $modelMax = (int) ($db->queryValue(
        "SELECT max_tokens FROM ai_models WHERE model_id = ? AND provider = ?",
        [$model, $provider]
    ) ?: 0);
    $cap = AIService::outputTokenCap($provider, $model);
    $effectiveMax = min($modelMax > 0 ? $modelMax : DEFAULT_MAX_TOKENS, $cap);
    $ai->setMaxTokens(max(1024, $effectiveMax));
}
$tracker = new UsageTracker($db);

// ==========================================
// SSE Setup
// ==========================================

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
header('Content-Encoding: identity');

ignore_user_abort(true);
set_time_limit(300);

// Output-Buffer komplett aus — PHP soll jedes echo sofort durchreichen.
// Wichtig fuer SSE, sonst sieht der Browser die ersten Events erst, wenn der
// Server-Buffer voll ist (Apache buffert ~4KB).
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
ob_implicit_flush(true);

function sendSSE(string $type, array $data = []): void
{
    $data['type'] = $type;
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

// Initial-Padding: 2 KB Kommentar + Heartbeat. Faengt den Apache-Buffer ab,
// damit der Stream im Browser sofort „lebt" — bevor die langsamere Pipeline
// (Customer-Detection, RAG-Retrieval, AI-Stream) anlaeuft.
echo ': ' . str_repeat(' ', 2048) . "\n\n";
echo ": stream-ready\n\n";
@flush();

// Auto-Mode Info an Client senden
if ($autoModeUsed) {
    $autoModelName = $db->queryOne("SELECT display_name FROM ai_models WHERE model_id = ?", [$model]);
    sendSSE('auto_info', [
        'model' => $model,
        'model_name' => $autoModelName['display_name'] ?? $model,
        'reason' => $autoReason,
    ]);
}

// Customer-Detection dem Frontend mitteilen (vor dem Wissen, damit der Banner sofort erscheint).
// Banner NUR senden, wenn ein ANDERER Kunde erkannt wurde als der bereits am Chat
// gemerkte/in der Sidebar gewaehlte. Sonst spammt es bei jeder Nachricht, obwohl der
// Kunde eindeutig feststeht (Bugfix 23.06.2026).
if (!empty($detectedCustomer)
    && (int) ($detectedCustomer['customer_id'] ?? 0) !== $convCustomerIdBefore) {
    sendSSE('customer_detected', $detectedCustomer);
}

// Knowledge dem Frontend mitteilen
if (!empty($knowledgeUsed)) {
    sendSSE('knowledge_used', ['chunks' => $knowledgeUsed]);
}

// Web-Suche (wenn aktiviert)
if ($useWebSearch) {
    $braveKey = $settings['brave_search_api_key'] ?? '';
    if (!empty($braveKey)) {
        try {
            require_once SERVICES_PATH . '/WebSearchService.php';
            $webSearch = new \Services\WebSearchService($braveKey);

            // Suchbegriffe extrahieren mit einem kleinen LLM-Call
            $oaiKeyForSearch = $settings['openai_api_key'] ?? '';
            if (!empty($oaiKeyForSearch)) {
                $searchAi = new AIService($oaiKeyForSearch, 'openai');
                $searchAi->setModel('gpt-4o-mini');
                $queries = $webSearch->extractSearchQueries($message, $searchAi);
            } else {
                // Fallback: Nachricht direkt als Query
                $queries = [mb_substr($message, 0, 200)];
            }

            if (!empty($queries)) {
                sendSSE('web_search', ['status' => 'searching', 'queries' => $queries]);

                $allSearchResults = [];
                foreach ($queries as $q) {
                    try {
                        $searchResult = $webSearch->search($q, 5);
                        $allSearchResults[] = $searchResult;
                    } catch (\Exception $e) {
                        error_log("Web search error for '$q': " . $e->getMessage());
                    }
                }

                if (!empty($allSearchResults)) {
                    // Top-Ergebnisse crawlen fuer vollstaendigen Seiteninhalt
                    $crawledCount = 0;
                    $maxCrawl = 3;
                    sendSSE('web_search', ['status' => 'crawling']);
                    foreach ($allSearchResults as &$srGroup) {
                        foreach ($srGroup['results'] as &$srResult) {
                            if ($crawledCount >= $maxCrawl) break;
                            try {
                                $pageContent = $webSearch->fetchUrlContent($srResult['url'], 8, 8000);
                                if ($pageContent && mb_strlen($pageContent) > 100) {
                                    $srResult['content'] = $pageContent;
                                    $crawledCount++;
                                }
                            } catch (\Exception $e) {
                                error_log("URL crawl error for {$srResult['url']}: " . $e->getMessage());
                            }
                        }
                        unset($srResult);
                        if ($crawledCount >= $maxCrawl) break;
                    }
                    unset($srGroup);

                    // Ergebnisse in System-Prompt injizieren
                    $searchContext = $webSearch->formatResultsAsContext($allSearchResults);
                    $systemPrompt .= "\n\n" . $searchContext;

                    // Quellen ans Frontend senden
                    $sources = [];
                    foreach ($allSearchResults as $group) {
                        foreach ($group['results'] as $r) {
                            $sources[] = [
                                'title' => $r['title'],
                                'url' => $r['url'],
                                'description' => $r['description'],
                            ];
                        }
                    }
                    sendSSE('web_search', [
                        'status' => 'done',
                        'queries' => $queries,
                        'sources' => $sources,
                    ]);
                }
            }
        } catch (\Exception $e) {
            error_log("Web search error: " . $e->getMessage());
            sendSSE('web_search', ['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

// URL-Erkennung: URLs in der User-Nachricht automatisch crawlen
if (preg_match_all('#https?://[^\s<>"\')\]\},]+#i', $message, $urlMatches)) {
    $userUrls = array_unique(array_slice($urlMatches[0], 0, 3));
    if (!empty($userUrls)) {
        require_once SERVICES_PATH . '/WebSearchService.php';
        $urlFetcher = new \Services\WebSearchService('');

        sendSSE('url_fetch', ['status' => 'fetching', 'urls' => $userUrls]);

        $urlContents = [];
        foreach ($userUrls as $userUrl) {
            try {
                $urlContent = $urlFetcher->fetchUrlContent($userUrl, 10, 12000);
                if ($urlContent && mb_strlen($urlContent) > 50) {
                    $urlContents[] = ['url' => $userUrl, 'content' => $urlContent];
                }
            } catch (\Exception $e) {
                error_log("User URL fetch error for {$userUrl}: " . $e->getMessage());
            }
        }

        if (!empty($urlContents)) {
            $urlContext = "\n\n--- VERLINKTE INHALTE ---\n";
            $urlContext .= "Der User hat folgende URLs in seiner Nachricht angegeben. Hier sind die Inhalte:\n\n";
            foreach ($urlContents as $uc) {
                $urlContext .= "URL: {$uc['url']}\n";
                $urlContext .= "{$uc['content']}\n\n";
            }
            $urlContext .= "--- ENDE VERLINKTE INHALTE ---\n";
            $systemPrompt .= $urlContext;
        }

        sendSSE('url_fetch', [
            'status' => 'done',
            'urls' => array_map(fn($uc) => $uc['url'], $urlContents),
            'count' => count($urlContents),
        ]);
    }
}

$reqStart = microtime(true);
$ttftMs = null;
try {
    $fullContent = '';

    $result = $ai->chatStream($aiMessages, $systemPrompt, function ($token) use (&$fullContent, &$ttftMs, $reqStart) {
        if ($ttftMs === null) {
            $ttftMs = (int) round((microtime(true) - $reqStart) * 1000);
        }
        $fullContent .= $token;
        sendSSE('token', ['content' => $token]);
    });

    $totalMs = (int) round((microtime(true) - $reqStart) * 1000);
    $tokensInput = $result['tokens']['input'] ?? 0;
    $tokensOutput = $result['tokens']['output'] ?? 0;

    // Antwort am Token-Limit abgeschnitten? Nicht mehr stillschweigend hinnehmen:
    // Der Client bekommt Bescheid und kann sauber fortsetzen lassen.
    if (!empty($result['truncated'])) {
        error_log(sprintf(
            'chat-stream: Antwort abgeschnitten (Modell %s, %d Output-Tokens, Limit erreicht)',
            $model,
            $tokensOutput
        ));
        sendSSE('truncated', [
            'reason'  => $result['stop_reason'] ?? 'max_tokens',
            'message' => 'Die Antwort hat das Längen-Limit erreicht und wurde abgeschnitten.',
        ]);
    }

    // Assistant-Message speichern (nur wenn Inhalt vorhanden)
    if (empty(trim($fullContent))) {
        $fullContent = '[Keine Antwort vom Modell erhalten]';
    }
    $msgData = [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => $fullContent,
        'model_used' => $model,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput,
    ];
    if ($dualGroupId) {
        $msgData['dual_group_id'] = $dualGroupId;
        $msgData['dual_slot'] = $dualSlot;
    }
    $msgId = $db->insert('chat_conversation_messages', $msgData);

    // Conversation updated_at + Toggle-States aktualisieren
    $convUpdate = ['updated_at' => date('Y-m-d H:i:s')];
    if (!$dualMode || $dualSlot === 'a') {
        $convUpdate['use_web_search'] = $useWebSearch ? 1 : 0;
        $convUpdate['use_knowledge'] = $useKnowledge ? 1 : 0;
        $convUpdate['knowledge_engine'] = $knowledgeEngine;
        $convUpdate['use_rules'] = $useRules ? 1 : 0;
        $convUpdate['dual_mode'] = $dualMode ? 1 : 0;
        if ($dualMode && !empty($input['dual_model_b'])) {
            $convUpdate['dual_model_b'] = $input['dual_model_b'];
        }
    }
    $db->update('chat_conversations', $convUpdate, 'id = ?', [$conversationId]);

    // Usage tracken
    try {
        $tracker->log(
            $conv['customer_id'],
            $userId,
            'chat_conversation',
            $model,
            $provider,
            $tokensInput,
            $tokensOutput,
            str_word_count($fullContent),
            ['conversation_id' => $conversationId]
        );
    } catch (\Exception $e) {
        // Usage-Tracking-Fehler nicht an User weitergeben
    }

    // Performance-Log (alle Provider) — fuer Vergleich lokal vs. Cloud.
    // Detail (finaler System-Prompt, User-Frage, RAG-Chunks inkl. Inhalt, Antwort)
    // wandert in die separate, nach 90 Tagen rotierende Detail-Tabelle.
    \Services\LlmRequestLog::record([
        'provider' => $provider,
        'model' => $model,
        'use_case' => 'chat_conversation',
        'user_id' => $userId,
        'customer_id' => $conv['customer_id'] ?? null,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput,
        'ttft_ms' => $ttftMs,
        'total_ms' => $totalMs,
        'success' => true,
        'detail' => [
            'conversation_id' => $conversationId,
            'message_id' => $msgId,
            'system_prompt' => $systemPrompt,
            'user_message' => $message,
            'response_text' => $fullContent,
            'rag_chunks' => $knowledgeForLog,
            'rerank_meta' => $rerankMeta,
        ],
    ]);

    sendSSE('done', [
        'message_id' => $msgId,
        'tokens_input' => $tokensInput,
        'tokens_output' => $tokensOutput,
        'model' => $model,
        'auto_mode' => $autoModeUsed,
    ]);

} catch (\Exception $e) {
    \Services\LlmRequestLog::record([
        'provider' => $provider,
        'model' => $model,
        'use_case' => 'chat_conversation',
        'user_id' => $userId,
        'customer_id' => $conv['customer_id'] ?? null,
        'ttft_ms' => $ttftMs,
        'total_ms' => (int) round((microtime(true) - $reqStart) * 1000),
        'success' => false,
        'error_message' => mb_substr($e->getMessage(), 0, 1000),
        'detail' => [
            'conversation_id' => $conversationId,
            'system_prompt' => $systemPrompt,
            'user_message' => $message,
            'response_text' => $fullContent ?? null,
            'rag_chunks' => $knowledgeForLog,
            'rerank_meta' => $rerankMeta,
        ],
    ]);
    sendSSE('error', ['message' => $e->getMessage()]);
}

// SSE-Endpunkt: der Stream ist fertig (Output laeuft seit Zeile ~669). Der
// Request MUSS hier selbst enden — sonst laeuft der Router (api/handler.php)
// nach dem require weiter und ruft am Ende Response::notFound()/error(), das
// dann http_response_code()/header() nach bereits gesendetem Body aufruft
// => "headers already sent"-Warnung. exit verhindert das (Bugfix 09.06.2026).
exit;
