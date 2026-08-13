#!/usr/bin/env php
<?php
/**
 * Generation Worker - Verarbeitet asynchrone Artikel-Generierungs-Jobs
 *
 * Aufruf: php /var/www/cli/worker.php
 * Cron:   * * * * * php /var/www/cli/worker.php >> /var/log/generation-worker.log 2>&1
 *
 * Der Worker laeuft maximal 55 Sekunden und verarbeitet Jobs aus der Queue.
 * Bei leerem GPT-Response wird automatisch ein Retry versucht (max 3x).
 */

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Konstanten und Autoloader laden
require_once dirname(__DIR__) . '/config/constants.php';

spl_autoload_register(function ($class) {
    $namespaces = [
        'Core\\' => 'core/',
        'Models\\' => 'models/',
        'Services\\' => 'services/'
    ];

    foreach ($namespaces as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $relativeClass = substr($class, strlen($namespace));
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

use Core\Database;
use Services\JobQueue;
use Services\AIService;
use Services\UsageTracker;
use Services\ContextService;

// Config laden
$configFile = CONFIG_PATH . '/config.php';
if (!file_exists($configFile)) {
    logMessage("ERROR: Config nicht gefunden");
    exit(1);
}

$config = require $configFile;
$db = Database::getInstance($config['db']);

// Services initialisieren
$jobQueue = new JobQueue($db);

// Worker-Konfiguration
$maxRuntime = 55; // Sekunden (Cron laeuft jede Minute)
$sleepInterval = 5; // Sekunden warten wenn keine Jobs
$startTime = time();

logMessage("Worker gestartet");

// Haengende Jobs aufraumen
$cleaned = $jobQueue->cleanupStuckJobs(10);
if ($cleaned > 0) {
    logMessage("$cleaned haengende Jobs zurueckgesetzt");
}

// Settings laden fuer Generation Method
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);
$generationMethod = $settings['generation_method'] ?? 'reliable';

logMessage("Generierungs-Methode: $generationMethod");

// Hauptschleife
while ((time() - $startTime) < $maxRuntime) {
    $job = $jobQueue->getNextPendingJob();

    if (!$job) {
        sleep($sleepInterval);
        continue;
    }

    // Asana-Sync-Jobs separat verarbeiten
    if (($job['job_type'] ?? '') === 'asana_sync') {
        processAsanaSyncJob($job, $db, $jobQueue, $settings);
        continue;
    }

    // Website-Sync-Jobs separat verarbeiten
    if (($job['job_type'] ?? '') === 'website_sync') {
        processWebsiteSyncJob($job, $db, $jobQueue, $settings);
        continue;
    }

    // Qdrant-Sync-Jobs (Wissen V2) separat verarbeiten
    if (($job['job_type'] ?? '') === 'qdrant_sync') {
        processQdrantSyncJob($job, $db, $jobQueue);
        continue;
    }

    // Order-Jobs separat verarbeiten
    if (!empty($job['order_id'])) {
        processOrderJob($job, $db, $jobQueue, $settings);
        continue;
    }

    // Je nach Methode verarbeiten
    if ($generationMethod === 'reliable') {
        processJobReliable($job, $db, $jobQueue, $settings);
    } else {
        processJob($job, $db, $jobQueue);
    }
}

logMessage("Worker beendet (Laufzeit: " . (time() - $startTime) . "s)");

// ==========================================
// Funktionen
// ==========================================

function logMessage(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
}

function processJob(array $job, Database $db, JobQueue $jobQueue): void
{
    $jobId = $job['id'];
    $startTime = microtime(true);

    logMessage("Job #$jobId gestartet (Typ: {$job['job_type']}, Topic: {$job['topic']})");

    // Job als "in Bearbeitung" markieren
    $jobQueue->startJob($jobId);

    try {
        // Settings laden (mit Entschluesselung — sonst sind enc:v1:... Strings unbrauchbar)
        $settings = [];
        foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $settings = \Core\Settings::decryptMap($settings);

        // API-Key bestimmen
        $model = $job['model'];
        $provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
        $apiKey = $provider === 'anthropic'
            ? ($settings['anthropic_api_key'] ?? '')
            : ($settings['openai_api_key'] ?? '');

        if (empty($apiKey)) {
            throw new \Exception("API-Key fuer $provider nicht konfiguriert");
        }

        // Prompt aufbauen
        $promptData = buildPrompt($job, $db, $settings);

        // Prompt in DB speichern fuer Debugging/Einsicht
        $db->update('generation_jobs', [
            'prompt_used' => json_encode([
                'system' => $promptData['systemPrompt'],
                'user' => $promptData['userMessage']
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ], 'id = ?', [$jobId]);

        // AI Service initialisieren
        $ai = new AIService($apiKey, $provider);
        $ai->setModel($model);

        // Generierung ausfuehren
        $response = $ai->chat(
            [['role' => 'user', 'content' => $promptData['userMessage']]],
            $promptData['systemPrompt']
        );

        // Response verarbeiten
        $content = $response['content'];
        $articleSections = parseArticleResponse($content, $job['topic']);

        // Aktuellen Job-Status aus DB holen (attempts wurde in startJob erhoeht)
        $currentJob = $jobQueue->getJob($jobId);
        $currentAttempts = $currentJob['attempts'] ?? $job['attempts'];

        // Leere Response pruefen
        if (empty($articleSections) || isEmptyResponse($articleSections)) {
            logMessage("Job #$jobId: Leere Antwort erhalten (Versuch $currentAttempts/{$job['max_attempts']})");

            if ($currentAttempts < $job['max_attempts']) {
                if ($jobQueue->retryJob($jobId, 'Leere Antwort - automatischer Retry')) {
                    logMessage("Job #$jobId: Retry geplant");
                    return;
                }
            }

            // Max attempts erreicht oder retry fehlgeschlagen
            $jobQueue->failJob($jobId, "Nach {$job['max_attempts']} Versuchen keine gueltige Antwort erhalten");
            logMessage("Job #$jobId: Fehlgeschlagen - max. Versuche erreicht");
            return;
        }

        // Wortanzahl berechnen
        $totalWords = 0;
        foreach ($articleSections as &$section) {
            $section['word_count'] = AIService::countWords($section['content'] ?? '');
            $totalWords += $section['word_count'];
        }

        // Verarbeitungszeit
        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Job erfolgreich abschliessen
        $jobQueue->completeJob(
            $jobId,
            $articleSections,
            $response['tokens']['input'] ?? 0,
            $response['tokens']['output'] ?? 0,
            $processingTimeMs
        );

        // Sections in Datenbank speichern
        saveArticleSections($job, $articleSections, $db);

        // Usage tracken
        $tracker = new UsageTracker($db);
        $tracker->log(
            $job['customer_id'],
            $job['user_id'],
            'generation',
            $model,
            $provider,
            $response['tokens']['input'] ?? 0,
            $response['tokens']['output'] ?? 0,
            $totalWords
        );

        logMessage("Job #$jobId abgeschlossen: $totalWords Woerter, {$processingTimeMs}ms");

    } catch (\Exception $e) {
        $error = $e->getMessage();
        logMessage("Job #$jobId fehlgeschlagen: $error");

        // Pruefen ob Retry moeglich
        if ($job['attempts'] < $job['max_attempts'] && isRetryableError($error)) {
            $jobQueue->retryJob($jobId, $error);
            logMessage("Job #$jobId: Retry geplant wegen: $error");
        } else {
            $jobQueue->failJob($jobId, $error);
        }
    }
}

function buildPrompt(array $job, Database $db, array $settings): array
{
    $sections = $job['sections_config']['count'] ?? 5;
    $wordsPerSection = $job['sections_config']['words_per_section'] ?? 200;
    $wordsPerSectionMax = intval($wordsPerSection * 1.5);
    $targetWords = $job['target_words'];
    $topic = $job['topic'];

    // Stil laden
    $styleData = $db->queryOne(
        "SELECT name, prompt_addition FROM styles WHERE slug = ? AND is_active = 1",
        [$job['style_slug'] ?? 'informativ']
    );
    $styleName = $styleData['name'] ?? 'Informativ';
    $stylePrompt = $styleData['prompt_addition'] ?? '';

    // Kundenkontext
    $customerContext = buildCustomerContext($job['customer_id'], $db);

    // Regeln laden
    $rulesText = buildRulesText($job['rule_ids'] ?? [], $job['customer_id'], $db);

    // Wissen laden
    $knowledgeContext = buildKnowledgeContext($job['knowledge_ids'] ?? [], $job['customer_id'], $db, $settings, $topic);

    // System Prompt
    $systemPrompt = "Du bist ein professioneller Content-Writer und SEO-Experte. Du schreibst ausfuehrliche, hochwertige, gut strukturierte Artikel auf Deutsch.

AUFGABE: Generiere einen kompletten, ausfuehrlichen Artikel zum angegebenen Thema.

FORMAT: Antworte AUSSCHLIESSLICH mit einem JSON-Array. Keine Erklaerungen davor oder danach.

Das JSON-Array soll folgende Struktur haben:
[
  {
    \"heading\": \"Die Hauptueberschrift\",
    \"level\": \"h1\",
    \"content\": \"Der Einleitungstext...\"
  },
  {
    \"heading\": \"Erster Unterabschnitt\",
    \"level\": \"h2\",
    \"content\": \"Der Text fuer diesen Abschnitt...\"
  }
]

KRITISCHE ANFORDERUNGEN:
- Generiere genau {$sections} Abschnitte
- WICHTIG: Jeder Abschnitt MUSS zwischen {$wordsPerSection} und {$wordsPerSectionMax} Woerter enthalten!
- Das Gesamtziel sind {$targetWords} Woerter - erreiche dieses Ziel!
- Erster Abschnitt ist die Einleitung mit h1, alle weiteren mit h2

STIL ({$styleName}):
{$stylePrompt}

SCHREIBSTIL:
- Schreibe ausfuehrliche, detaillierte Absaetze mit 4-6 Saetzen pro Absatz
- Verwende Beispiele, Erklaerungen und praktische Tipps
- Keine Aufzaehlungspunkte oder Gedankenstriche am Satzanfang
- Keine Markdown-Formatierung im Content (keine **, keine #, keine -)
- Schreibe natuerlichen, fliessenden Fliesstext
- Jeder Abschnitt soll mehrere Absaetze haben
{$customerContext}
{$rulesText}
{$knowledgeContext}";

    // Guidelines (content_output + internal) anhaengen
    try {
        require_once SERVICES_PATH . '/GuidelineService.php';
        $glBlock = (new \Services\GuidelineService($db))->buildPromptBlock(['content_output', 'internal']);
        if ($glBlock !== '') $systemPrompt .= "\n\n" . $glBlock;
    } catch (\Exception $e) {}

    return [
        'systemPrompt' => $systemPrompt,
        'userMessage' => "Schreibe einen Artikel zum Thema: {$topic}"
    ];
}

function buildCustomerContext(?int $customerId, Database $db): string
{
    if (!$customerId) return '';

    $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) return '';

    $contextParts = [];

    if (!empty($customer['name'])) {
        $contextParts[] = "Unternehmen: " . $customer['name'];
    }
    if (!empty($customer['description'])) {
        $contextParts[] = "Beschreibung: " . $customer['description'];
    }
    if (!empty($customer['industry'])) {
        $contextParts[] = "Branche: " . $customer['industry'];
    }
    if (!empty($customer['target_audience'])) {
        $contextParts[] = "Zielgruppe: " . $customer['target_audience'];
    }
    if (!empty($customer['products_services'])) {
        $contextParts[] = "Produkte/Dienstleistungen: " . $customer['products_services'];
    }
    if (!empty($customer['unique_selling_points'])) {
        $contextParts[] = "Alleinstellungsmerkmale: " . $customer['unique_selling_points'];
    }
    if (!empty($customer['brand_values'])) {
        $contextParts[] = "Markenwerte: " . $customer['brand_values'];
    }
    if (!empty($customer['tone_of_voice'])) {
        $toneLabels = [
            'formal' => 'Formal (Sie-Form, gehoben)',
            'informal' => 'Informal (Du-Form, locker)',
            'professional' => 'Professionell-sachlich',
            'friendly' => 'Freundlich und nahbar',
            'technical' => 'Technisch-fachlich',
            'casual' => 'Locker-umgangssprachlich'
        ];
        $contextParts[] = "Gewuenschte Tonalitaet: " . ($toneLabels[$customer['tone_of_voice']] ?? $customer['tone_of_voice']);
    }

    if (empty($contextParts)) return '';

    return "\n\nUNTERNEHMENSPROFIL - Schreibe den Artikel passend fuer dieses Unternehmen:\n" . implode("\n", $contextParts);
}

function buildRulesText(array $ruleIds, ?int $customerId, Database $db): string
{
    // Wenn keine Regeln ausgewaehlt wurden, keine Regeln verwenden
    // (Benutzer hat explizit keine Regeln angehakt)
    if (empty($ruleIds)) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
    $rules = $db->query(
        "SELECT rule_content, rule_type FROM rules WHERE id IN ($placeholders) AND is_active = 1 ORDER BY priority DESC",
        array_map('intval', $ruleIds)
    );

    if (empty($rules)) return '';

    $rulesText = "\n\nWICHTIG - Befolge diese Regeln strikt:\n";
    foreach ($rules as $rule) {
        $rulesText .= "- " . $rule['rule_content'] . "\n";
    }

    return $rulesText;
}

function buildKnowledgeContext(array $knowledgeIds, ?int $customerId, Database $db, array $settings, string $topic): string
{
    // Wenn kein Wissen ausgewaehlt wurde, keins verwenden
    // (Benutzer hat explizit nichts angehakt)
    if (empty($knowledgeIds)) {
        return '';
    }

    $formatEntry = function($entry) {
        $text = "";
        if ($entry['title']) {
            $text .= "### " . $entry['title'] . "\n";
        }
        if (!empty($entry['usage_context'])) {
            $text .= "[ANWEISUNG: " . $entry['usage_context'] . "]\n";
        } elseif (!empty($entry['category_name'])) {
            $text .= "[Kategorie: " . $entry['category_name'] . "]\n";
        }
        $text .= $entry['content'] . "\n";
        return $text;
    };

    $placeholders = implode(',', array_fill(0, count($knowledgeIds), '?'));
    $knowledgeEntries = $db->query(
        "SELECT ke.title, ke.content, ke.usage_context, kc.name as category_name
         FROM knowledge_entries ke
         LEFT JOIN knowledge_categories kc ON ke.category_id = kc.id
         WHERE ke.id IN ($placeholders)",
        array_map('intval', $knowledgeIds)
    );

    if (empty($knowledgeEntries)) return '';

    $context = "\n\nHINTERGRUNDWISSEN - Nutze diese Informationen fuer den Artikel:\n\n";
    foreach ($knowledgeEntries as $entry) {
        $context .= "---\n" . $formatEntry($entry);
    }

    return $context;
}

function parseArticleResponse(string $content, string $topic): array
{
    // JSON aus Response extrahieren
    $jsonMatch = [];
    if (preg_match('/\[[\s\S]*\]/m', $content, $jsonMatch)) {
        $content = $jsonMatch[0];
    }

    $articleSections = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($articleSections)) {
        // Fallback: Einzelner Abschnitt
        return [
            [
                'heading' => $topic,
                'level' => 'h1',
                'content' => $content
            ]
        ];
    }

    // Content bereinigen: Escaped newlines in echte Newlines umwandeln
    foreach ($articleSections as &$section) {
        if (!empty($section['content'])) {
            $section['content'] = str_replace(['\\n\\n', '\\n'], ["\n\n", "\n"], $section['content']);
            $section['content'] = preg_replace('/\n{3,}/', "\n\n", $section['content']);
            $section['content'] = trim($section['content']);
        }
    }
    unset($section); // Referenz aufheben

    return $articleSections;
}

function isEmptyResponse(array $sections): bool
{
    if (empty($sections)) return true;

    $totalContent = '';
    foreach ($sections as $section) {
        $totalContent .= $section['content'] ?? '';
    }

    // Weniger als 50 Woerter gilt als leer
    return AIService::countWords($totalContent) < 50;
}

function isRetryableError(string $error): bool
{
    $retryablePatterns = [
        'timeout',
        'rate limit',
        'overloaded',
        '503',
        '502',
        '500',
        'connection',
        'temporarily unavailable'
    ];

    $errorLower = strtolower($error);
    foreach ($retryablePatterns as $pattern) {
        if (strpos($errorLower, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

function saveArticleSections(array $job, array $sections, Database $db): void
{
    $projectId = $job['project_id'];

    // Aktuelle Version holen oder neue erstellen
    $currentVersion = $db->queryOne(
        "SELECT id, version_number FROM article_versions
         WHERE project_id = ?
         ORDER BY version_number DESC
         LIMIT 1",
        [$projectId]
    );

    if ($currentVersion) {
        // Bestehende Sections loeschen
        $db->delete('article_sections', 'article_version_id = ?', [$currentVersion['id']]);
        $versionId = $currentVersion['id'];
    } else {
        // Neue Version erstellen
        $versionId = $db->insert('article_versions', [
            'project_id' => $projectId,
            'version_number' => 1,
            'content' => '',
            'word_count' => 0
        ]);
    }

    // Sections speichern
    $totalWords = 0;
    foreach ($sections as $index => $section) {
        $wordCount = $section['word_count'] ?? AIService::countWords($section['content'] ?? '');
        $totalWords += $wordCount;

        $db->insert('article_sections', [
            'article_version_id' => $versionId,
            'section_order' => $index + 1,
            'heading_level' => $section['level'] ?? 'h2',
            'heading_text' => $section['heading'] ?? '',
            'content' => $section['content'] ?? '',
            'word_count' => $wordCount,
            'model_used' => $job['model']
        ]);
    }

    // Version-Wortanzahl aktualisieren
    $db->update('article_versions', [
        'word_count' => $totalWords
    ], 'id = ?', [$versionId]);

    // Projekt aktualisieren
    $db->update('projects', [
        'updated_at' => date('Y-m-d H:i:s'),
        'current_version' => $currentVersion['version_number'] ?? 1
    ], 'id = ?', [$projectId]);
}

// ==========================================
// RELIABLE GENERATION - Step-by-Step
// ==========================================

/**
 * Job-Fortschritt in DB aktualisieren
 */
function updateJobProgress(int $jobId, string $step, int $total, int $completed, Database $db): void
{
    $db->update('generation_jobs', [
        'current_step' => $step,
        'total_steps' => $total,
        'completed_steps' => $completed
    ], 'id = ?', [$jobId]);
}

/**
 * Reliable Verarbeitung: Erst Gliederung, dann Abschnitte einzeln
 */
function processJobReliable(array $job, Database $db, JobQueue $jobQueue, array $settings): void
{
    $jobId = $job['id'];
    $startTime = microtime(true);
    $totalTokensInput = 0;
    $totalTokensOutput = 0;

    logMessage("Job #$jobId gestartet (Methode: reliable, Topic: {$job['topic']})");

    // Job als "in Bearbeitung" markieren
    $jobQueue->startJob($jobId);

    // SOFORT alte Sections loeschen damit Live-Preview sauber startet
    $projectId = $job['project_id'];
    $currentVersion = $db->queryOne(
        "SELECT id FROM article_versions WHERE project_id = ? ORDER BY version_number DESC LIMIT 1",
        [$projectId]
    );
    if ($currentVersion) {
        $db->delete('article_sections', 'article_version_id = ?', [$currentVersion['id']]);
        logMessage("Job #$jobId: Alte Sections geloescht fuer Live-Preview");
    }

    try {
        // API-Key bestimmen
        $model = $job['model'];
        $provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
        $apiKey = $provider === 'anthropic'
            ? ($settings['anthropic_api_key'] ?? '')
            : ($settings['openai_api_key'] ?? '');

        if (empty($apiKey)) {
            throw new \Exception("API-Key fuer $provider nicht konfiguriert");
        }

        // AI Service initialisieren
        $ai = new AIService($apiKey, $provider);
        $ai->setModel($model);

        $sectionsCount = $job['sections_config']['count'] ?? 5;
        $wordsPerSection = $job['sections_config']['words_per_section'] ?? intval($job['target_words'] / $sectionsCount);
        $wordsPerSectionMax = intval($wordsPerSection * 1.5);

        // Total steps = 1 (Gliederung) + N (Abschnitte)
        $totalSteps = 1 + $sectionsCount;
        updateJobProgress($jobId, 'outline', $totalSteps, 0, $db);

        // ========== SCHRITT 1: Gliederung generieren ==========
        logMessage("Job #$jobId: Generiere Gliederung...");

        $outlineResult = generateOutline($job, $db, $ai, $settings, $sectionsCount);
        $outline = $outlineResult['outline'];
        $totalTokensInput += $outlineResult['tokens_input'];
        $totalTokensOutput += $outlineResult['tokens_output'];

        // Aktuellen Job-Status aus DB holen
        $currentJob = $jobQueue->getJob($jobId);
        $currentAttempts = $currentJob['attempts'] ?? $job['attempts'];

        if (empty($outline) || count($outline) < 2) {
            logMessage("Job #$jobId: Leere Gliederung erhalten (Versuch $currentAttempts/{$job['max_attempts']})");

            if ($currentAttempts < $job['max_attempts']) {
                if ($jobQueue->retryJob($jobId, 'Leere Gliederung - automatischer Retry')) {
                    logMessage("Job #$jobId: Retry geplant");
                    return;
                }
            }

            $jobQueue->failJob($jobId, "Nach {$job['max_attempts']} Versuchen keine gueltige Gliederung erhalten");
            logMessage("Job #$jobId: Fehlgeschlagen - max. Versuche erreicht");
            return;
        }

        // Gliederung in Job speichern
        $sectionsConfig = $job['sections_config'];
        $sectionsConfig['outline'] = $outline;
        $db->update('generation_jobs', [
            'sections_config' => json_encode($sectionsConfig)
        ], 'id = ?', [$jobId]);

        updateJobProgress($jobId, 'section_1', $totalSteps, 1, $db);
        logMessage("Job #$jobId: Gliederung mit " . count($outline) . " Abschnitten erstellt");

        // Prompts sammeln fuer Debugging
        $allPrompts = [
            'outline' => $outlineResult['prompt_used'] ?? null,
            'sections' => []
        ];

        // ========== SCHRITT 2-N: Abschnitte generieren ==========
        $sections = [];

        foreach ($outline as $index => $headingInfo) {
            $stepNum = $index + 2; // Step 2, 3, 4...
            $sectionNum = $index + 1;

            // Heading kann String oder Array sein
            $heading = is_array($headingInfo) ? ($headingInfo['heading'] ?? $headingInfo['title'] ?? "Abschnitt $sectionNum") : $headingInfo;

            updateJobProgress($jobId, "section_$sectionNum", $totalSteps, $stepNum - 1, $db);
            logMessage("Job #$jobId: Generiere Abschnitt $sectionNum/" . count($outline) . ": $heading");

            $sectionResult = generateSingleSection($job, $db, $ai, $settings, $outline, $index, $wordsPerSection, $wordsPerSectionMax);
            $section = $sectionResult['section'];

            $totalTokensInput += $sectionResult['tokens_input'];
            $totalTokensOutput += $sectionResult['tokens_output'];
            $allPrompts['sections'][] = $sectionResult['prompt_used'] ?? null;

            if (empty($section['content'])) {
                // Retry fuer diesen Abschnitt
                $retryCount = 0;
                while (empty($section['content']) && $retryCount < 3) {
                    $retryCount++;
                    logMessage("Job #$jobId: Retry $retryCount fuer Abschnitt $sectionNum");
                    sleep(2); // Kurze Pause

                    $sectionResult = generateSingleSection($job, $db, $ai, $settings, $outline, $index, $wordsPerSection, $wordsPerSectionMax);
                    $section = $sectionResult['section'];
                    $totalTokensInput += $sectionResult['tokens_input'];
                    $totalTokensOutput += $sectionResult['tokens_output'];
                }

                if (empty($section['content'])) {
                    $jobQueue->failJob($jobId, "Abschnitt $sectionNum ($heading) konnte nicht generiert werden");
                    return;
                }
            }

            $sections[] = $section;

            // Zwischenspeichern nach jedem erfolgreichen Abschnitt
            saveSingleSection($job, $section, $index, $db);
            logMessage("Job #$jobId: Abschnitt $sectionNum gespeichert (" . $section['word_count'] . " Woerter)");
        }

        // ========== ABSCHLUSS ==========
        updateJobProgress($jobId, 'completed', $totalSteps, $totalSteps, $db);

        // Wortanzahl berechnen
        $totalWords = array_sum(array_column($sections, 'word_count'));

        // Verarbeitungszeit
        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Prompt in DB speichern fuer Debugging
        $db->update('generation_jobs', [
            'prompt_used' => json_encode($allPrompts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ], 'id = ?', [$jobId]);

        // Job erfolgreich abschliessen
        $jobQueue->completeJob(
            $jobId,
            $sections,
            $totalTokensInput,
            $totalTokensOutput,
            $processingTimeMs
        );

        // Usage tracken
        $tracker = new UsageTracker($db);
        $tracker->log(
            $job['customer_id'],
            $job['user_id'],
            'generation',
            $model,
            $provider,
            $totalTokensInput,
            $totalTokensOutput,
            $totalWords
        );

        logMessage("Job #$jobId abgeschlossen: $totalWords Woerter, {$processingTimeMs}ms");

    } catch (\Exception $e) {
        $error = $e->getMessage();
        logMessage("Job #$jobId fehlgeschlagen: $error");

        // Pruefen ob Retry moeglich
        if ($job['attempts'] < $job['max_attempts'] && isRetryableError($error)) {
            $jobQueue->retryJob($jobId, $error);
            logMessage("Job #$jobId: Retry geplant wegen: $error");
        } else {
            $jobQueue->failJob($jobId, $error);
        }
    }
}

/**
 * Gliederung generieren (nur Ueberschriften)
 */
function generateOutline(array $job, Database $db, AIService $ai, array $settings, int $sectionsCount): array
{
    // Kontext aufbauen
    $customerContext = buildCustomerContext($job['customer_id'], $db);
    $rulesText = buildRulesText($job['rule_ids'] ?? [], $job['customer_id'], $db);

    // Stil laden
    $styleData = $db->queryOne(
        "SELECT name, prompt_addition FROM styles WHERE slug = ? AND is_active = 1",
        [$job['style_slug'] ?? 'informativ']
    );
    $styleName = $styleData['name'] ?? 'Informativ';

    $systemPrompt = "Du bist ein SEO-Experte und Content-Stratege. Erstelle eine logische Gliederung fuer einen Artikel.

AUFGABE: Generiere genau {$sectionsCount} Ueberschriften fuer einen Artikel zum angegebenen Thema.

FORMAT: Antworte NUR mit einem JSON-Array von Strings. Keine Erklaerungen, kein anderer Text.
Beispiel: [\"Hauptueberschrift\", \"Erster Abschnitt\", \"Zweiter Abschnitt\"]

REGELN:
- Erste Ueberschrift ist die Hauptueberschrift (wird spaeter H1)
- Alle weiteren sind Zwischenueberschriften (werden spaeter H2)
- SEO-freundliche, aussagekraeftige Titel
- Logische, nachvollziehbare Reihenfolge
- Deutsch
- Stil: {$styleName}
{$customerContext}
{$rulesText}";

    // Guidelines (content_output + internal)
    try {
        require_once SERVICES_PATH . '/GuidelineService.php';
        $glBlock = (new \Services\GuidelineService($db))->buildPromptBlock(['content_output', 'internal']);
        if ($glBlock !== '') $systemPrompt .= "\n\n" . $glBlock;
    } catch (\Exception $e) {}

    $userMessage = "Erstelle eine Gliederung mit {$sectionsCount} Abschnitten fuer: {$job['topic']}";

    $response = $ai->chat(
        [['role' => 'user', 'content' => $userMessage]],
        $systemPrompt
    );

    // JSON parsen
    $content = $response['content'];
    $outline = [];

    // JSON aus Response extrahieren (falls umgeben von Text)
    if (preg_match('/\[[\s\S]*\]/m', $content, $matches)) {
        $outline = json_decode($matches[0], true) ?? [];
    }

    return [
        'outline' => $outline,
        'tokens_input' => $response['tokens']['input'] ?? 0,
        'tokens_output' => $response['tokens']['output'] ?? 0,
        'prompt_used' => ['system' => $systemPrompt, 'user' => $userMessage]
    ];
}

/**
 * Einzelnen Abschnitt generieren
 */
function generateSingleSection(array $job, Database $db, AIService $ai, array $settings, array $outline, int $index, int $wordsTarget, int $wordsTargetMax = 0): array
{
    if ($wordsTargetMax <= $wordsTarget) {
        $wordsTargetMax = intval($wordsTarget * 1.5);
    }

    // Heading extrahieren (kann String oder Array sein)
    $headingInfo = $outline[$index];
    $heading = is_array($headingInfo) ? ($headingInfo['heading'] ?? $headingInfo['title'] ?? "Abschnitt " . ($index + 1)) : $headingInfo;
    $level = $index === 0 ? 'h1' : 'h2';

    // Kontext aufbauen
    $customerContext = buildCustomerContext($job['customer_id'], $db);
    $rulesText = buildRulesText($job['rule_ids'] ?? [], $job['customer_id'], $db);
    $knowledgeContext = buildKnowledgeContext($job['knowledge_ids'] ?? [], $job['customer_id'], $db, $settings, $job['topic']);

    // Stil laden
    $styleData = $db->queryOne(
        "SELECT name, prompt_addition FROM styles WHERE slug = ? AND is_active = 1",
        [$job['style_slug'] ?? 'informativ']
    );
    $styleName = $styleData['name'] ?? 'Informativ';
    $stylePrompt = $styleData['prompt_addition'] ?? '';

    // Kontext: Was kommt vorher und nachher?
    $contextInfo = "ARTIKEL-GLIEDERUNG:\n";
    foreach ($outline as $i => $h) {
        $hText = is_array($h) ? ($h['heading'] ?? $h['title'] ?? "Abschnitt " . ($i + 1)) : $h;
        $marker = $i === $index ? " <-- AKTUELL (diesen Abschnitt schreiben)" : "";
        $contextInfo .= ($i + 1) . ". $hText$marker\n";
    }

    $systemPrompt = "Du bist ein professioneller Content-Writer und SEO-Experte. Schreibe hochwertige, ausfuehrliche Texte auf Deutsch.

AUFGABE: Schreibe NUR den Abschnitt \"{$heading}\" fuer einen Artikel. Nicht den ganzen Artikel, nur diesen einen Abschnitt!

{$contextInfo}

KRITISCHE ANFORDERUNGEN:
- Schreibe zwischen {$wordsTarget} und {$wordsTargetMax} Woerter fuer diesen Abschnitt
- Fliessender, natuerlicher Text OHNE Aufzaehlungen
- Mehrere Absaetze mit je 4-6 Saetzen
- KEINE Markdown-Formatierung (keine **, keine #, keine -)
- Der Text soll inhaltlich zum vorherigen und naechsten Abschnitt passen
- Wiederhole NICHT die Ueberschrift im Text

STIL ({$styleName}):
{$stylePrompt}

FORMAT: Antworte NUR mit einem JSON-Objekt. Keine Erklaerungen davor oder danach.
{
  \"heading\": \"{$heading}\",
  \"level\": \"{$level}\",
  \"content\": \"Der ausfuehrliche Text fuer diesen Abschnitt...\"
}
{$customerContext}
{$rulesText}
{$knowledgeContext}";

    // Guidelines (content_output + internal)
    try {
        require_once SERVICES_PATH . '/GuidelineService.php';
        $glBlock = (new \Services\GuidelineService($db))->buildPromptBlock(['content_output', 'internal']);
        if ($glBlock !== '') $systemPrompt .= "\n\n" . $glBlock;
    } catch (\Exception $e) {}

    $userMessage = "Schreibe jetzt den Abschnitt: {$heading}";

    $response = $ai->chat(
        [['role' => 'user', 'content' => $userMessage]],
        $systemPrompt
    );

    // JSON parsen
    $content = $response['content'];
    $section = [];

    // JSON aus Response extrahieren
    if (preg_match('/\{[\s\S]*\}/m', $content, $matches)) {
        $section = json_decode($matches[0], true) ?? [];
    }

    // Fallback falls JSON-Parsing fehlschlaegt
    if (empty($section['content'])) {
        // Versuche den rohen Text als Content zu verwenden
        $cleanContent = preg_replace('/^[\s\S]*?"content"\s*:\s*"/', '', $content);
        $cleanContent = preg_replace('/"[\s\S]*$/', '', $cleanContent);
        if (strlen($cleanContent) > 50) {
            $section = [
                'heading' => $heading,
                'level' => $level,
                'content' => $cleanContent
            ];
        }
    }

    // Content bereinigen: Escaped newlines in echte Newlines umwandeln
    if (!empty($section['content'])) {
        // Manchmal gibt KI "\\n" zurueck, das nach JSON-Decode zu "\n" (literal) wird
        $section['content'] = str_replace(['\\n\\n', '\\n'], ["\n\n", "\n"], $section['content']);
        // Ueberfluessige Leerzeilen reduzieren (max 2 Newlines)
        $section['content'] = preg_replace('/\n{3,}/', "\n\n", $section['content']);
        // Trim
        $section['content'] = trim($section['content']);
    }

    // Wortanzahl berechnen
    $section['word_count'] = AIService::countWords($section['content'] ?? '');

    // Sicherstellen dass heading und level gesetzt sind
    $section['heading'] = $section['heading'] ?? $heading;
    $section['level'] = $section['level'] ?? $level;

    return [
        'section' => $section,
        'tokens_input' => $response['tokens']['input'] ?? 0,
        'tokens_output' => $response['tokens']['output'] ?? 0,
        'prompt_used' => ['system' => $systemPrompt, 'user' => $userMessage]
    ];
}

/**
 * Einzelnen Abschnitt in DB speichern (waehrend der Generierung)
 */
function saveSingleSection(array $job, array $section, int $index, Database $db): void
{
    $projectId = $job['project_id'];

    // Aktuelle Version holen oder neue erstellen
    $currentVersion = $db->queryOne(
        "SELECT id, version_number FROM article_versions
         WHERE project_id = ?
         ORDER BY version_number DESC
         LIMIT 1",
        [$projectId]
    );

    if ($currentVersion) {
        $versionId = $currentVersion['id'];
    } else {
        // Neue Version erstellen
        $versionId = $db->insert('article_versions', [
            'project_id' => $projectId,
            'version_number' => 1,
            'content' => '',
            'word_count' => 0
        ]);
    }

    // Pruefen ob Section bereits existiert
    $existingSection = $db->queryOne(
        "SELECT id FROM article_sections WHERE article_version_id = ? AND section_order = ?",
        [$versionId, $index + 1]
    );

    $sectionData = [
        'article_version_id' => $versionId,
        'section_order' => $index + 1,
        'heading_level' => $section['level'] ?? 'h2',
        'heading_text' => $section['heading'] ?? '',
        'content' => $section['content'] ?? '',
        'word_count' => $section['word_count'] ?? AIService::countWords($section['content'] ?? ''),
        'model_used' => $job['model']
    ];

    if ($existingSection) {
        // Update
        $db->update('article_sections', $sectionData, 'id = ?', [$existingSection['id']]);
    } else {
        // Insert
        $db->insert('article_sections', $sectionData);
    }

    // Version-Wortanzahl aktualisieren
    $totalWords = $db->queryValue(
        "SELECT COALESCE(SUM(word_count), 0) FROM article_sections WHERE article_version_id = ?",
        [$versionId]
    );
    $db->update('article_versions', ['word_count' => $totalWords], 'id = ?', [$versionId]);

    // Projekt aktualisieren
    $db->update('projects', [
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$projectId]);
}

// ==========================================
// ORDER JOB PROCESSING
// ==========================================

/**
 * Order-basierte Jobs verarbeiten (Briefing, Artikel, Chat)
 */
function processOrderJob(array $job, Database $db, JobQueue $jobQueue, array $settings): void
{
    $jobId = $job['id'];
    $orderId = $job['order_id'];
    $jobType = $job['job_type'];
    $startTime = microtime(true);

    logMessage("Order-Job #$jobId gestartet (Typ: $jobType, Order: $orderId)");

    // Job als "in Bearbeitung" markieren
    $jobQueue->startJob($jobId);

    try {
        // Order laden
        $order = $db->queryOne(
            "SELECT o.*, ctx.name as context_name FROM orders o
             JOIN contexts ctx ON o.context_id = ctx.id
             WHERE o.id = ?",
            [$orderId]
        );

        if (!$order) {
            throw new \Exception("Order #$orderId nicht gefunden");
        }

        // AI-Provider bestimmen
        $model = $job['model'];
        $provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
        $apiKey = $provider === 'anthropic'
            ? ($settings['anthropic_api_key'] ?? '')
            : ($settings['openai_api_key'] ?? '');

        if (empty($apiKey)) {
            throw new \Exception("API-Key fuer $provider nicht konfiguriert");
        }

        // Services initialisieren
        $ai = new AIService($apiKey, $provider);
        $ai->setModel($model);

        $contextService = new ContextService($db);
        $tracker = new UsageTracker($db);

        $resultData = [];
        $tokensInput = 0;
        $tokensOutput = 0;
        $promptUsed = null;

        switch ($jobType) {
            case 'order_briefing':
                $result = processOrderBriefing($job, $order, $db, $ai, $contextService);
                $resultData = $result['data'];
                $tokensInput = $result['tokens_input'];
                $tokensOutput = $result['tokens_output'];
                $promptUsed = $result['prompt_used'];

                // Briefing in Order speichern
                $db->update('orders', [
                    'briefing_content' => $resultData['briefing_content'],
                    'model' => $model
                ], 'id = ?', [$orderId]);

                // Chat-Nachrichten speichern
                $db->insert('order_chat_messages', [
                    'order_id' => $orderId,
                    'phase' => 'briefing',
                    'role' => 'user',
                    'content' => "Erstelle ein Briefing fuer: \"{$order['title']}\"",
                    'tokens_used' => 0,
                    'model_used' => $model
                ]);
                $db->insert('order_chat_messages', [
                    'order_id' => $orderId,
                    'phase' => 'briefing',
                    'role' => 'assistant',
                    'content' => 'Briefing wurde erstellt.',
                    'tokens_used' => ($tokensInput + $tokensOutput),
                    'model_used' => $model
                ]);

                // Usage tracken
                $tracker->log(
                    $order['customer_id'],
                    $job['user_id'],
                    'order_briefing',
                    $model,
                    $provider,
                    $tokensInput,
                    $tokensOutput,
                    0,
                    ['order_id' => $orderId]
                );
                break;

            case 'order_briefing_chat':
                $result = processOrderBriefingChat($job, $order, $db, $ai, $contextService);
                $resultData = $result['data'];
                $tokensInput = $result['tokens_input'];
                $tokensOutput = $result['tokens_output'];
                $promptUsed = $result['prompt_used'];

                // Briefing aktualisieren
                $db->update('orders', [
                    'briefing_content' => $resultData['briefing_content']
                ], 'id = ?', [$orderId]);

                // Chat-Nachrichten speichern
                $db->insert('order_chat_messages', [
                    'order_id' => $orderId,
                    'phase' => 'briefing',
                    'role' => 'user',
                    'content' => $job['chat_message'],
                    'tokens_used' => 0,
                    'model_used' => $model
                ]);
                $db->insert('order_chat_messages', [
                    'order_id' => $orderId,
                    'phase' => 'briefing',
                    'role' => 'assistant',
                    'content' => 'Briefing wurde ueberarbeitet.',
                    'tokens_used' => ($tokensInput + $tokensOutput),
                    'model_used' => $model
                ]);

                // Usage tracken
                $tracker->log(
                    $order['customer_id'],
                    $job['user_id'],
                    'order_briefing_chat',
                    $model,
                    $provider,
                    $tokensInput,
                    $tokensOutput,
                    0,
                    ['order_id' => $orderId]
                );
                break;

            case 'order_article':
                $result = processOrderArticle($job, $order, $db, $ai, $contextService);
                $resultData = $result['data'];
                $tokensInput = $result['tokens_input'];
                $tokensOutput = $result['tokens_output'];
                $promptUsed = $result['prompt_used'];

                // Version erstellen
                $articleContent = $resultData['article_content'];
                $maxVersion = $db->queryValue(
                    "SELECT MAX(version_number) FROM order_versions WHERE order_id = ?",
                    [$orderId]
                );
                $versionNumber = ($maxVersion ?? 0) + 1;
                $plainText = strip_tags($articleContent);
                $wordCount = count(preg_split('/\s+/', trim($plainText)));

                $db->insert('order_versions', [
                    'order_id' => $orderId,
                    'version_number' => $versionNumber,
                    'article_content' => $articleContent,
                    'briefing_content' => $order['briefing_content'],
                    'change_description' => 'Initiale Artikel-Generierung',
                    'change_source' => 'generation',
                    'word_count' => $wordCount,
                    'created_by' => $job['user_id']
                ]);

                $db->update('orders', [
                    'current_version' => $versionNumber,
                    'article_content' => $articleContent,
                    'status' => 'editing'
                ], 'id = ?', [$orderId]);

                $resultData['version_number'] = $versionNumber;
                $resultData['word_count'] = $wordCount;

                // Usage tracken
                $tracker->log(
                    $order['customer_id'],
                    $job['user_id'],
                    'order_article_generate',
                    $model,
                    $provider,
                    $tokensInput,
                    $tokensOutput,
                    $wordCount,
                    ['order_id' => $orderId, 'version' => $versionNumber]
                );
                break;

            case 'order_article_chat':
                $result = processOrderArticleChat($job, $order, $db, $ai, $contextService);
                $resultData = $result['data'];
                $tokensInput = $result['tokens_input'];
                $tokensOutput = $result['tokens_output'];
                $promptUsed = $result['prompt_used'];

                $chatResponse = $resultData['chat_response'] ?? 'Aenderung durchgefuehrt.';
                $versionNumber = null;

                if ($resultData['changed'] && !empty($resultData['article_html'])) {
                    // Neue Version erstellen
                    $maxVersion = $db->queryValue(
                        "SELECT MAX(version_number) FROM order_versions WHERE order_id = ?",
                        [$orderId]
                    );
                    $versionNumber = ($maxVersion ?? 0) + 1;
                    $plainText = strip_tags($resultData['article_html']);
                    $wordCount = count(preg_split('/\s+/', trim($plainText)));

                    $db->insert('order_versions', [
                        'order_id' => $orderId,
                        'version_number' => $versionNumber,
                        'article_content' => $resultData['article_html'],
                        'briefing_content' => $order['briefing_content'],
                        'change_description' => $resultData['change_description'] ?? 'KI-Aenderung',
                        'change_source' => 'ai',
                        'word_count' => $wordCount,
                        'created_by' => $job['user_id']
                    ]);

                    $db->update('orders', [
                        'current_version' => $versionNumber,
                        'article_content' => $resultData['article_html']
                    ], 'id = ?', [$orderId]);

                    $resultData['version_number'] = $versionNumber;
                }

                // Chat-Nachrichten speichern
                $db->insert('order_chat_messages', [
                    'order_id' => $orderId,
                    'phase' => 'editing',
                    'role' => 'user',
                    'content' => $job['chat_message'],
                    'tokens_used' => 0,
                    'model_used' => $model
                ]);
                $db->insert('order_chat_messages', [
                    'order_id' => $orderId,
                    'phase' => 'editing',
                    'role' => 'assistant',
                    'content' => $chatResponse,
                    'applied_change' => $resultData['changed'] ? json_encode([
                        'change_description' => $resultData['change_description'] ?? '',
                        'version_number' => $versionNumber
                    ]) : null,
                    'tokens_used' => ($tokensInput + $tokensOutput),
                    'model_used' => $model
                ]);

                // Usage tracken
                $tracker->log(
                    $order['customer_id'],
                    $job['user_id'],
                    'order_article_chat',
                    $model,
                    $provider,
                    $tokensInput,
                    $tokensOutput,
                    0,
                    ['order_id' => $orderId, 'changed' => $resultData['changed']]
                );
                break;

            default:
                throw new \Exception("Unbekannter Order-Job-Typ: $jobType");
        }

        // Verarbeitungszeit
        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Prompt speichern
        if ($promptUsed) {
            $db->update('generation_jobs', [
                'prompt_used' => json_encode($promptUsed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ], 'id = ?', [$jobId]);
        }

        // Job erfolgreich abschliessen
        $jobQueue->completeJob(
            $jobId,
            $resultData,
            $tokensInput,
            $tokensOutput,
            $processingTimeMs
        );

        logMessage("Order-Job #$jobId abgeschlossen ({$processingTimeMs}ms)");

    } catch (\Exception $e) {
        $error = $e->getMessage();
        logMessage("Order-Job #$jobId fehlgeschlagen: $error");

        if ($job['attempts'] < $job['max_attempts'] && isRetryableError($error)) {
            $jobQueue->retryJob($jobId, $error);
            logMessage("Order-Job #$jobId: Retry geplant wegen: $error");
        } else {
            $jobQueue->failJob($jobId, $error);
        }
    }
}

/**
 * Order-Briefing generieren
 */
function processOrderBriefing(array $job, array $order, Database $db, AIService $ai, ContextService $contextService): array
{
    $ai->setMaxTokens(4096);

    $contextText = $contextService->assembleContextText($order['context_id']);
    $rulesText = $contextService->assembleRulesText($order['context_id'], $order['customer_id']);
    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein erfahrener Content-Stratege. Erstelle ein detailliertes Briefing fuer einen Artikel.\n\n";
    $systemPrompt .= "Das Briefing soll enthalten:\n";
    $systemPrompt .= "1. Arbeitstitel\n";
    $systemPrompt .= "2. Kernbotschaft (1-2 Saetze)\n";
    $systemPrompt .= "3. Zielgruppe\n";
    $systemPrompt .= "4. Gliederung mit Ueberschriften (H2/H3) und kurzer Beschreibung je Abschnitt\n";
    $systemPrompt .= "5. Empfohlene Wortanzahl\n";
    $systemPrompt .= "6. Tonalitaet und Stil\n";
    $systemPrompt .= "7. Keywords (falls relevant)\n\n";
    $systemPrompt .= "Formatiere das Briefing als sauberes HTML mit Ueberschriften, Listen und Absaetzen.\n\n";

    if ($contextText) {
        $systemPrompt .= $contextText . "\n\n";
    }
    if ($rulesText) {
        $systemPrompt .= $rulesText . "\n\n";
    }
    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    $userMessage = "Erstelle ein Briefing fuer den Artikel: \"{$order['title']}\"";
    $messages = [['role' => 'user', 'content' => $userMessage]];

    $result = $ai->chat($messages, $systemPrompt);

    return [
        'data' => ['briefing_content' => $result['content']],
        'tokens_input' => $result['tokens']['input'] ?? 0,
        'tokens_output' => $result['tokens']['output'] ?? 0,
        'prompt_used' => ['system' => $systemPrompt, 'user' => $userMessage]
    ];
}

/**
 * Order-Briefing Chat verarbeiten
 */
function processOrderBriefingChat(array $job, array $order, Database $db, AIService $ai, ContextService $contextService): array
{
    $ai->setMaxTokens(4096);

    $contextText = $contextService->assembleContextText($order['context_id']);
    $rulesText = $contextService->assembleRulesText($order['context_id'], $order['customer_id']);
    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein erfahrener Content-Stratege. Du hilfst beim Ueberarbeiten eines Briefings.\n\n";
    $systemPrompt .= "Aktuelles Briefing:\n" . ($order['briefing_content'] ?? 'Noch kein Briefing vorhanden.') . "\n\n";

    if ($contextText) {
        $systemPrompt .= $contextText . "\n\n";
    }
    if ($rulesText) {
        $systemPrompt .= $rulesText . "\n\n";
    }
    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    $systemPrompt .= "Wenn der Benutzer Aenderungen wuenscht, gib das KOMPLETTE ueberarbeitete Briefing als HTML zurueck.\n";
    $systemPrompt .= "Antworte NUR mit dem ueberarbeiteten Briefing-HTML, ohne zusaetzliche Erklaerungen.\n";

    // Bisherige Chat-Nachrichten laden
    $chatHistory = $db->query(
        "SELECT role, content FROM order_chat_messages
         WHERE order_id = ? AND phase = 'briefing'
         ORDER BY created_at ASC",
        [$order['id']]
    );

    $messages = [];
    foreach ($chatHistory as $msg) {
        if ($msg['role'] !== 'system') {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $job['chat_message']];

    $result = $ai->chat($messages, $systemPrompt);

    return [
        'data' => ['briefing_content' => $result['content']],
        'tokens_input' => $result['tokens']['input'] ?? 0,
        'tokens_output' => $result['tokens']['output'] ?? 0,
        'prompt_used' => ['system' => $systemPrompt, 'user' => $job['chat_message']]
    ];
}

/**
 * Order-Artikel generieren
 */
function processOrderArticle(array $job, array $order, Database $db, AIService $ai, ContextService $contextService): array
{
    $ai->setMaxTokens(8192);

    // Status auf generating setzen
    $db->update('orders', ['status' => 'generating'], 'id = ?', [$order['id']]);

    $rulesText = $contextService->assembleRulesText($order['context_id'], $order['customer_id']);
    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein professioneller Content-Autor. Schreibe einen hochwertigen Fliesstext-Artikel basierend auf dem Briefing.\n\n";
    $systemPrompt .= "WICHTIGE REGELN:\n";
    $systemPrompt .= "- Schreibe ausschliesslich den Artikel als sauberes HTML\n";
    $systemPrompt .= "- Verwende <h2>, <h3> fuer Ueberschriften\n";
    $systemPrompt .= "- Verwende <p> fuer Absaetze\n";
    $systemPrompt .= "- Verwende <ul>/<ol> fuer Listen wenn passend\n";
    $systemPrompt .= "- Verwende <strong> und <em> fuer Hervorhebungen\n";
    $systemPrompt .= "- Schreibe auf Deutsch\n";
    $systemPrompt .= "- KEINE Meta-Kommentare, keine Erklaerungen - NUR der Artikel-HTML\n\n";

    if ($rulesText) {
        $systemPrompt .= $rulesText . "\n\n";
    }
    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    $userMessage = "Schreibe den Artikel basierend auf diesem Briefing:\n\n" . $order['briefing_content'];
    $messages = [['role' => 'user', 'content' => $userMessage]];

    $result = $ai->chat($messages, $systemPrompt);

    return [
        'data' => ['article_content' => $result['content']],
        'tokens_input' => $result['tokens']['input'] ?? 0,
        'tokens_output' => $result['tokens']['output'] ?? 0,
        'prompt_used' => ['system' => $systemPrompt, 'user' => $userMessage]
    ];
}

/**
 * Order-Artikel Chat verarbeiten
 */
function processOrderArticleChat(array $job, array $order, Database $db, AIService $ai, ContextService $contextService): array
{
    $ai->setMaxTokens(8192);

    $sectionsConfig = json_decode($job['sections_config'] ?? '{}', true);
    $currentArticle = $sectionsConfig['current_article'] ?? $order['article_content'] ?? '';

    $rulesText = $contextService->assembleRulesText($order['context_id'], $order['customer_id']);
    $artifactsText = $contextService->assembleArtifactsText($order['customer_id']);

    $systemPrompt = "Du bist ein professioneller Content-Editor. Der Benutzer moechte Aenderungen am Artikel vornehmen.\n\n";
    $systemPrompt .= "Aktueller Artikel:\n" . $currentArticle . "\n\n";

    if ($rulesText) {
        $systemPrompt .= $rulesText . "\n\n";
    }
    if ($artifactsText) {
        $systemPrompt .= $artifactsText . "\n\n";
    }

    $systemPrompt .= "ANTWORT-FORMAT: Antworte IMMER als valides JSON mit genau diesen Feldern:\n";
    $systemPrompt .= "{\n";
    $systemPrompt .= "  \"changed\": true/false,\n";
    $systemPrompt .= "  \"article_html\": \"...kompletter Artikel als HTML...\",\n";
    $systemPrompt .= "  \"change_description\": \"Kurze Beschreibung der Aenderung\",\n";
    $systemPrompt .= "  \"chat_response\": \"Antwort an den Benutzer\"\n";
    $systemPrompt .= "}\n\n";
    $systemPrompt .= "Wenn changed=true, muss article_html den KOMPLETTEN ueberarbeiteten Artikel enthalten.\n";
    $systemPrompt .= "Wenn der Benutzer nur eine Frage stellt (keine Aenderung), setze changed=false.\n";

    // Bisherige Editing-Chat-Nachrichten laden (letzte 10)
    $chatHistory = $db->query(
        "SELECT role, content FROM order_chat_messages
         WHERE order_id = ? AND phase = 'editing'
         ORDER BY created_at DESC LIMIT 10",
        [$order['id']]
    );
    $chatHistory = array_reverse($chatHistory);

    $messages = [];
    foreach ($chatHistory as $msg) {
        if ($msg['role'] !== 'system') {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $job['chat_message']];

    $result = $ai->chat($messages, $systemPrompt);

    // JSON parsen
    $responseContent = $result['content'];

    // JSON aus Markdown-Codeblock extrahieren falls noetig
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $responseContent, $jsonMatch)) {
        $responseContent = $jsonMatch[1];
    }

    $parsed = json_decode($responseContent, true);

    if (!$parsed || !isset($parsed['changed'])) {
        $parsed = [
            'changed' => false,
            'article_html' => $currentArticle,
            'change_description' => '',
            'chat_response' => $responseContent
        ];
    }

    return [
        'data' => [
            'changed' => $parsed['changed'],
            'article_html' => $parsed['article_html'] ?? $currentArticle,
            'change_description' => $parsed['change_description'] ?? '',
            'chat_response' => $parsed['chat_response'] ?? 'Erledigt.'
        ],
        'tokens_input' => $result['tokens']['input'] ?? 0,
        'tokens_output' => $result['tokens']['output'] ?? 0,
        'prompt_used' => ['system' => $systemPrompt, 'user' => $job['chat_message']]
    ];
}

// ==========================================
// ASANA SYNC JOBS
// ==========================================

/**
 * Asana-Sync-Job: Holt Asana-Projekte/Tasks/Attachments fuer einen Kunden.
 */
function processAsanaSyncJob(array $job, Database $db, JobQueue $jobQueue, array $settings): void
{
    $jobId = $job['id'];
    $customerId = (int) ($job['customer_id'] ?? 0);
    $startTime = microtime(true);

    if (!$customerId) {
        $jobQueue->failJob($jobId, 'asana_sync ohne customer_id');
        return;
    }

    logMessage("Asana-Sync-Job #$jobId gestartet (customer_id: $customerId)");
    $jobQueue->startJob($jobId);

    try {
        $pat = $settings['asana_pat'] ?? '';
        if (empty($pat)) {
            throw new \Exception('Asana PAT nicht konfiguriert');
        }

        $openaiKey = $settings['openai_api_key'] ?? '';
        if (empty($openaiKey)) {
            throw new \Exception('OpenAI API-Key nicht konfiguriert (fuer Embeddings)');
        }

        require_once SERVICES_PATH . '/AsanaService.php';
        require_once SERVICES_PATH . '/AsanaSyncService.php';
        require_once SERVICES_PATH . '/KnowledgeExtractionService.php';
        require_once SERVICES_PATH . '/KnowledgeIngestService.php';
        require_once SERVICES_PATH . '/KnowledgeService.php';
        require_once SERVICES_PATH . '/DocumentProcessor.php';

        $asana = new \Services\AsanaService($pat);
        // Embeddings macht der qdrant_sync-Worker (bge-m3 lokal) — kein OpenAI-Embed mehr.
        $extractAI = new \Services\AIService($openaiKey, 'openai');
        $extractionService = new \Services\KnowledgeExtractionService($extractAI);
        $ingestService = new \Services\KnowledgeIngestService($db, null, $extractionService);
        $knowledgeService = new \Services\KnowledgeService($db);
        $docProcessor = new \Services\DocumentProcessor();

        $sync = new \Services\AsanaSyncService(
            $db, $asana, $knowledgeService, $ingestService, $docProcessor, (int) $job['user_id']
        );

        $stats = $sync->syncCustomer($customerId, false);

        // Token-Counter nur noch aus KnowledgeExtractionService (OpenAI fuer Metadaten).
        // Embeddings macht der qdrant_sync-Worker lokal, hier kein Token-Verbrauch.
        $tokensIn = (int) ($extractionService->tokensIn ?? 0);
        $tokensOut = (int) ($extractionService->tokensOut ?? 0);
        $stats['tokens_input'] = $tokensIn;
        $stats['tokens_output'] = $tokensOut;

        // Modell-Label setzen
        $db->update('generation_jobs', ['model' => 'asana-sync'], 'id = ?', [$jobId]);

        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);
        $jobQueue->completeJob($jobId, $stats, $tokensIn, $tokensOut, $processingTimeMs);

        logMessage("Asana-Sync-Job #$jobId abgeschlossen: "
            . "{$stats['projects']} Projekte, {$stats['tasks_synced']} Tasks, "
            . "{$stats['attachments']} Anhaenge, {$processingTimeMs}ms");

    } catch (\Exception $e) {
        $error = $e->getMessage();
        logMessage("Asana-Sync-Job #$jobId fehlgeschlagen: $error");
        if ($job['attempts'] < $job['max_attempts'] && isRetryableError($error)) {
            $jobQueue->retryJob($jobId, $error);
        } else {
            $jobQueue->failJob($jobId, $error);
        }
    }
}

/**
 * Verarbeitet einen Qdrant-Sync-Job (Wissen V2: bge-m3 + Qdrant).
 * op (in sections_config): 'upsert_document' | 'delete_document', plus document_id.
 * Nutzt Settings::get (entschlüsselt) statt des rohen $settings-Arrays.
 */
function processQdrantSyncJob(array $job, Database $db, JobQueue $jobQueue): void
{
    $jobId = $job['id'];
    $startTime = microtime(true);
    $cfg = is_array($job['sections_config'] ?? null) ? $job['sections_config'] : [];
    $op = $cfg['op'] ?? 'upsert_document';
    $docId = (int) ($cfg['document_id'] ?? 0);

    if ($docId <= 0) {
        $jobQueue->failJob($jobId, 'qdrant_sync ohne document_id');
        return;
    }

    logMessage("Qdrant-Sync-Job #$jobId gestartet (op=$op, doc=$docId)");
    $jobQueue->startJob($jobId);

    try {
        $sync = \Services\QdrantSyncService::fromSettings($db);

        if ($op === 'delete_document') {
            $sync->deleteDocument($docId);
            $stats = ['op' => 'delete', 'document_id' => $docId];
        } else {
            $count = $sync->syncDocument($docId);
            $stats = ['op' => 'upsert', 'document_id' => $docId, 'chunks' => $count];
        }

        $db->update('generation_jobs', ['model' => 'qdrant-sync'], 'id = ?', [$jobId]);
        $ms = (int) ((microtime(true) - $startTime) * 1000);
        $jobQueue->completeJob($jobId, $stats, $sync->tokensUsed(), 0, $ms);
        logMessage("Qdrant-Sync-Job #$jobId fertig in {$ms}ms: " . json_encode($stats));
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        logMessage("Qdrant-Sync-Job #$jobId FEHLER: $error");
        if ($job['attempts'] < $job['max_attempts'] && isRetryableError($error)) {
            $jobQueue->retryJob($jobId, $error);
        } else {
            $jobQueue->failJob($jobId, $error);
        }
    }
}

function processWebsiteSyncJob(array $job, Database $db, JobQueue $jobQueue, array $settings): void
{
    $jobId = $job['id'];
    $customerId = (int) ($job['customer_id'] ?? 0);
    $startTime = microtime(true);

    if (!$customerId) {
        $jobQueue->failJob($jobId, 'website_sync ohne customer_id');
        return;
    }

    logMessage("Website-Sync-Job #$jobId gestartet (customer_id: $customerId)");
    $jobQueue->startJob($jobId);

    try {
        $openaiKey = $settings['openai_api_key'] ?? '';
        if (empty($openaiKey)) throw new \Exception('OpenAI API-Key nicht konfiguriert');

        $customer = $db->queryOne("SELECT id, name, settings FROM customers WHERE id = ?", [$customerId]);
        if (!$customer) throw new \Exception('Kunde nicht gefunden');
        $cs = json_decode($customer['settings'] ?? '{}', true) ?: [];
        $cfg = $cs['website_crawl'] ?? [];
        $startUrl = trim((string) ($cfg['start_url'] ?? ''));
        if ($startUrl === '') throw new \Exception('Keine Start-URL konfiguriert');
        $maxPages = max(1, min(200, (int) ($cfg['max_pages'] ?? 120)));
        $maxDepth = max(1, min(5, (int) ($cfg['max_depth'] ?? 4)));

        require_once SERVICES_PATH . '/WebSearchService.php';
        require_once SERVICES_PATH . '/WebsiteCrawler.php';
        require_once SERVICES_PATH . '/KnowledgeEmbeddingService.php';
        require_once SERVICES_PATH . '/KnowledgeExtractionService.php';
        require_once SERVICES_PATH . '/KnowledgeIngestService.php';
        require_once SERVICES_PATH . '/WebsiteSyncService.php';
        require_once SERVICES_PATH . '/AIService.php';

        $web = new \Services\WebSearchService($settings['brave_search_api_key'] ?? '');
        $crawler = new \Services\WebsiteCrawler($web, $maxPages, $maxDepth);
        // Embeddings macht der qdrant_sync-Worker (bge-m3 lokal) — kein OpenAI-Embed mehr.
        $extractAI = new \Services\AIService($openaiKey, 'openai');
        $extractionService = new \Services\KnowledgeExtractionService($extractAI);
        $ingest = new \Services\KnowledgeIngestService($db, null, $extractionService);
        $sync = new \Services\WebsiteSyncService($db, $crawler, $ingest);

        $stats = $sync->sync($customerId, $startUrl, (int) ($job['user_id'] ?? 0));

        // Token-Counter aus den Services einsammeln (Embeddings + Extraction)
        $tokensIn = (int) ($emb->tokensUsed + $extractionService->tokensIn);
        $tokensOut = (int) ($extractionService->tokensOut);
        $stats['tokens_input'] = $tokensIn;
        $stats['tokens_output'] = $tokensOut;

        // last_sync_at in customers.settings persistieren
        $cs['website_crawl'] = array_merge($cfg, [
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_stats' => $stats,
        ]);
        $db->update('customers', ['settings' => json_encode($cs)], 'id = ?', [$customerId]);

        // Modell-Label setzen (für Jobs-Übersicht)
        $db->update('generation_jobs', ['model' => 'website-sync'], 'id = ?', [$jobId]);

        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);
        $jobQueue->completeJob($jobId, $stats, $tokensIn, $tokensOut, $processingTimeMs);
        logMessage("Website-Sync-Job #$jobId fertig in {$processingTimeMs}ms: " . json_encode($stats));
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        logMessage("Website-Sync-Job #$jobId FEHLER: $error");
        if ($job['attempts'] < $job['max_attempts'] && isRetryableError($error)) {
            $jobQueue->retryJob($jobId, $error);
        } else {
            $jobQueue->failJob($jobId, $error);
        }
    }
}
