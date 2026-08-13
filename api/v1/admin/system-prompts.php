<?php
/**
 * System-Prompts API (Admin only) — zentrale Prompt-Verwaltung.
 *
 * POST { action: 'save',  key, content }  → Override setzen
 * POST { action: 'reset', key }           → Override entfernen (Code-Standard greift)
 */

use Core\Response;
use Services\SystemPromptService;

global $method, $input;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$action = $input['action'] ?? 'save';
$key    = (string)($input['key'] ?? '');

if ($key === '' || !array_key_exists($key, SystemPromptService::defaults())) {
    Response::error('Unbekannter Prompt-Schluessel');
}

if ($action === 'history') {
    Response::success([
        'key'      => $key,
        'versions' => SystemPromptService::history($key),
    ]);
}

if ($action === 'restore') {
    $versionId = (int)($input['version_id'] ?? 0);
    $content = $versionId > 0 ? SystemPromptService::versionContent($key, $versionId) : null;
    if ($content === null) {
        Response::error('Version nicht gefunden');
    }
    SystemPromptService::set($key, $content, 'aus Version #' . $versionId . ' wiederhergestellt');
    Response::success([
        'key'       => $key,
        'content'   => $content,
        'isDefault' => false,
    ], 'Version wiederhergestellt');
}

if ($action === 'reset') {
    SystemPromptService::reset($key);
    Response::success([
        'key'      => $key,
        'content'  => SystemPromptService::defaultFor($key),
        'isDefault' => true,
    ], 'Standard wiederhergestellt');
}

// save
$content = trim((string)($input['content'] ?? ''));
if ($content === '') {
    // Leer speichern = auf Standard zuruecksetzen (intuitiver als Fehler)
    SystemPromptService::reset($key);
    Response::success([
        'key'       => $key,
        'content'   => SystemPromptService::defaultFor($key),
        'isDefault' => true,
    ], 'Leeres Feld — Standard wiederhergestellt');
}

SystemPromptService::set($key, $content);
Response::success([
    'key'       => $key,
    'content'   => $content,
    'isDefault' => false,
], 'Prompt gespeichert');
