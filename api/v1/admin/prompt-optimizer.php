<?php
/**
 * Prompt Optimizer API
 *
 * GET  /admin/prompt-optimizer                — Aktuelle Version + Historie
 * POST /admin/prompt-optimizer/feedback       — Feedback zu aktueller Version
 * POST /admin/prompt-optimizer/optimize       — Prompt optimieren lassen
 * POST /admin/prompt-optimizer/save           — Manuell angepassten Prompt speichern
 * POST /admin/prompt-optimizer/rollback       — Zu aelterer Version zurueckkehren
 * POST /admin/prompt-optimizer/init           — Aktuellen Default-Prompt als v1 speichern
 */

use Core\Response;
use Core\Auth;

if (!Auth::isAdmin()) {
    Response::forbidden();
}

global $db, $method, $input;

require_once SERVICES_PATH . '/PromptOptimizer.php';
$optimizer = new \Services\PromptOptimizer($db, 'artifact_analysis');

$action = $params['action'] ?? null;

if ($method === 'GET') {
    $current = $optimizer->getCurrentPrompt();
    $history = $optimizer->getHistory(20);
    Response::success([
        'current' => $current,
        'history' => $history,
        'version' => $optimizer->getCurrentVersion(),
    ]);
}

if ($method === 'POST') {
    switch ($action) {
        case 'init':
            // Aktuellen Default-Prompt als erste Version speichern
            if ($optimizer->getCurrentVersion() > 0) {
                Response::error('Es existiert bereits eine Version. Nutze "save" fuer Aenderungen.');
            }
            require_once SERVICES_PATH . '/EntityService.php';
            require_once SERVICES_PATH . '/ArtifactService.php';
            require_once SERVICES_PATH . '/ArtifactImportService.php';
            require_once SERVICES_PATH . '/DocumentProcessor.php';
            require_once SERVICES_PATH . '/AIService.php';

            $entityService = new \Services\EntityService($db);
            $artifactService = new \Services\ArtifactService($db, $entityService);
            $importService = new \Services\ArtifactImportService($db, $artifactService);

            // Default-Prompt holen via Reflection (der Fallback-Prompt)
            $defaultPrompt = $importService->getDefaultAnalysisPrompt();
            $id = $optimizer->saveVersion($defaultPrompt, 'Initialer Default-Prompt', Auth::id());
            Response::success(['id' => $id, 'version' => 1], 'Version 1 gespeichert');

        case 'feedback':
            $positive = $input['positive'] ?? '';
            $negative = $input['negative'] ?? '';
            $score = isset($input['score']) ? (int)$input['score'] : null;
            $current = $optimizer->getCurrentPrompt();
            if (!$current) Response::error('Kein Prompt vorhanden');
            $optimizer->saveFeedback((int)$current['id'], $positive, $negative, $score);
            Response::success(null, 'Feedback gespeichert');

        case 'optimize':
            $additionalFeedback = $input['feedback'] ?? null;
            $model = $input['model'] ?? null;

            $settings = [];
            foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $settings = \Core\Settings::decryptMap($settings);

            if (!$model) $model = $settings['default_model'] ?? 'gpt-4o-mini';
            $provider = (strpos($model, 'claude') !== false) ? 'anthropic' : ((strpos($model, 'gemini') !== false) ? 'google' : 'openai');
            $providerKeys = ['openai' => $settings['openai_api_key'] ?? '', 'anthropic' => $settings['anthropic_api_key'] ?? '', 'google' => $settings['google_api_key'] ?? ''];
            $apiKey = $providerKeys[$provider] ?? '';
            if (empty($apiKey)) Response::error('API-Key nicht konfiguriert');

            require_once SERVICES_PATH . '/AIService.php';
            $ai = new \Services\AIService($apiKey, $provider);
            $ai->setModel($model);
            $ai->setMaxTokens(8192);

            try {
                $result = $optimizer->optimize($ai, $additionalFeedback);
                $id = $optimizer->saveVersion($result['prompt_text'], $result['reasoning'], Auth::id());
                Response::success([
                    'id' => $id,
                    'version' => $optimizer->getCurrentVersion(),
                    'reasoning' => $result['reasoning'],
                    'prompt_preview' => mb_substr($result['prompt_text'], 0, 500) . '...',
                ], 'Prompt optimiert');
            } catch (\Exception $e) {
                Response::error('Optimierung fehlgeschlagen: ' . $e->getMessage());
            }

        case 'save':
            $promptText = $input['prompt_text'] ?? '';
            if (empty($promptText)) Response::error('Prompt-Text erforderlich');
            $reasoning = $input['reasoning'] ?? 'Manuell angepasst';
            $id = $optimizer->saveVersion($promptText, $reasoning, Auth::id());
            Response::success([
                'id' => $id,
                'version' => $optimizer->getCurrentVersion(),
            ], 'Neue Version gespeichert');

        case 'rollback':
            $toVersion = (int)($input['version'] ?? 0);
            if (!$toVersion) Response::error('Version erforderlich');
            $id = $optimizer->rollback($toVersion, Auth::id());
            Response::success([
                'id' => $id,
                'version' => $optimizer->getCurrentVersion(),
            ], "Rollback zu Version {$toVersion}");

        default:
            Response::error('Unbekannte Aktion', 400);
    }
}

Response::error('Method not allowed', 405);
