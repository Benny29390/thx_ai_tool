<?php
/**
 * Transkription — globale Settings (Auto-Pipeline-Defaults, HF-Token).
 *
 * GET  /admin/transkription/settings        Liest die drei Settings-Keys
 * PUT  /admin/transkription/settings        Body: { default_templates?, default_knowledge_template?, huggingface_token? }
 *
 * Schreibend nur fuer Admin.
 */

use Core\Auth;
use Core\Response;
use Core\Settings;

if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $hf = (string)Settings::get('huggingface_token');
    Response::success([
        'default_templates'          => (string)Settings::get('transkription_default_templates'),
        'default_knowledge_template' => (string)Settings::get('transkription_default_knowledge_template'),
        'huggingface_token_set'      => $hf !== '',
        'loom_sdk_public_app_id'     => (string)Settings::get('loom_sdk_public_app_id'),
        'whisper_remote_url'         => (string)Settings::get('whisper_remote_url', 'https://ki.thoxan.com/whisper/v1/audio/transcriptions'),
        'whisper_remote_model'       => (string)Settings::get('whisper_remote_model', 'Systran/faster-whisper-large-v3'),
    ]);
}

if ($method === 'PUT') {
    if (!Auth::isAdmin()) Response::forbidden('Nur Admin darf Settings aendern');
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    if (!is_array($json)) Response::error('Body muss JSON sein');

    if (isset($json['default_templates'])) {
        $v = is_array($json['default_templates']) ? implode(',', $json['default_templates']) : (string)$json['default_templates'];
        Settings::set('transkription_default_templates', $v);
    }
    if (isset($json['default_knowledge_template'])) {
        Settings::set('transkription_default_knowledge_template', (string)$json['default_knowledge_template']);
    }
    if (isset($json['huggingface_token'])) {
        // Leerstring loescht den Token (Settings::set verschluesselt automatisch wenn Inhalt)
        Settings::set('huggingface_token', (string)$json['huggingface_token']);
    }
    if (isset($json['loom_sdk_public_app_id'])) {
        Settings::set('loom_sdk_public_app_id', trim((string)$json['loom_sdk_public_app_id']));
    }
    if (isset($json['whisper_remote_url'])) {
        Settings::set('whisper_remote_url', trim((string)$json['whisper_remote_url']));
    }
    if (isset($json['whisper_remote_model'])) {
        Settings::set('whisper_remote_model', trim((string)$json['whisper_remote_model']));
    }
    Response::success(['saved' => true], 'Einstellungen gespeichert');
}

Response::error('Methode nicht unterstuetzt', 405);
