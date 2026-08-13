<?php
/**
 * Beispiel-Konfiguration
 * Kopiere diese Datei nach config.php und passe die Werte an
 */

return [
    // Datenbank
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'ki_tool',
        'user' => 'ki_tool',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],

    // Anwendung
    'app' => [
        'debug' => false,
        'url' => 'http://localhost',
        'timezone' => 'Europe/Berlin'
    ],

    // KI-APIs (werden ueber Settings verwaltet)
    'ai' => [
        'openai_key' => '',
        'anthropic_key' => '',
        'default_model' => 'gpt-4'
    ],

    // Session
    'session' => [
        'name' => 'ki_tool_session',
        'lifetime' => 86400
    ]
];
