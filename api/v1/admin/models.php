<?php
/**
 * Admin Models API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

// Admin-Check
if (!Auth::isAdmin()) {
    Response::forbidden();
}

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $model = $db->queryOne("SELECT * FROM ai_models WHERE id = ?", [$id]);
            if (!$model) {
                Response::notFound('Modell nicht gefunden');
            }
            Response::success($model);
        } else {
            $models = $db->query("SELECT * FROM ai_models ORDER BY sort_order, display_name");
            Response::success($models);
        }
        break;

    case 'POST':
        // Neues Modell oder Sortierung
        if (isset($input['order'])) {
            // Sortierung aktualisieren
            foreach ($input['order'] as $item) {
                $db->update('ai_models', ['sort_order' => $item['sort_order']], 'id = ?', [$item['id']]);
            }
            Response::success(null, 'Sortierung gespeichert');
        }

        $modelId = trim($input['model_id'] ?? '');
        $displayName = trim($input['display_name'] ?? '');
        $provider = $input['provider'] ?? 'openai';
        $costInput = floatval($input['cost_input'] ?? 0);
        $costOutput = floatval($input['cost_output'] ?? 0);
        $maxTokens = intval($input['max_tokens'] ?? 4096);

        if (!$modelId || !$displayName) {
            Response::error('Model ID und Anzeigename erforderlich');
        }

        if (!in_array($provider, ['openai', 'anthropic', 'google', 'local'])) {
            Response::error('Ungültiger Provider');
        }

        // Prüfen ob Model ID bereits existiert
        $existing = $db->queryOne("SELECT id FROM ai_models WHERE model_id = ?", [$modelId]);
        if ($existing) {
            Response::error('Ein Modell mit dieser ID existiert bereits');
        }

        $newId = $db->insert('ai_models', [
            'model_id' => $modelId,
            'display_name' => $displayName,
            'provider' => $provider,
            'cost_input' => $costInput,
            'cost_output' => $costOutput,
            'max_tokens' => $maxTokens,
            'is_active' => 1,
            'is_default' => 0,
            'sort_order' => 999
        ]);

        Response::success(['id' => $newId], 'Modell erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $model = $db->queryOne("SELECT * FROM ai_models WHERE id = ?", [$id]);
        if (!$model) {
            Response::notFound('Modell nicht gefunden');
        }

        $updates = [];

        if (isset($input['display_name'])) {
            $updates['display_name'] = trim($input['display_name']);
        }
        if (isset($input['model_id'])) {
            $newModelId = trim($input['model_id']);
            // Prüfen ob neue Model ID bereits existiert (nicht bei gleichem Modell)
            $existing = $db->queryOne("SELECT id FROM ai_models WHERE model_id = ? AND id != ?", [$newModelId, $id]);
            if ($existing) {
                Response::error('Ein Modell mit dieser ID existiert bereits');
            }
            $updates['model_id'] = $newModelId;
        }
        if (isset($input['provider'])) {
            if (!in_array($input['provider'], ['openai', 'anthropic', 'google', 'local'])) {
                Response::error('Ungültiger Provider');
            }
            $updates['provider'] = $input['provider'];
        }
        if (isset($input['cost_input'])) {
            $updates['cost_input'] = floatval($input['cost_input']);
        }
        if (isset($input['cost_output'])) {
            $updates['cost_output'] = floatval($input['cost_output']);
        }
        if (isset($input['max_tokens'])) {
            $updates['max_tokens'] = intval($input['max_tokens']);
        }
        if (isset($input['is_active'])) {
            $updates['is_active'] = $input['is_active'] ? 1 : 0;
        }
        if (isset($input['chat_enabled'])) {
            $updates['chat_enabled'] = $input['chat_enabled'] ? 1 : 0;
        }
        if (isset($input['is_default'])) {
            // Wenn als Standard gesetzt, andere zurücksetzen
            if ($input['is_default']) {
                $db->execute("UPDATE ai_models SET is_default = 0");
            }
            $updates['is_default'] = $input['is_default'] ? 1 : 0;
        }

        if (!empty($updates)) {
            $db->update('ai_models', $updates, 'id = ?', [$id]);
        }

        Response::success(null, 'Modell aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $model = $db->queryOne("SELECT * FROM ai_models WHERE id = ?", [$id]);
        if (!$model) {
            Response::notFound('Modell nicht gefunden');
        }

        $db->delete('ai_models', 'id = ?', [$id]);
        Response::success(null, 'Modell gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
