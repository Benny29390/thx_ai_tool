<?php
/**
 * config.template.php — Vorlage fuer die installationsspezifische config.php.
 *
 * Wird von scripts/provision.sh (und optional vom Installer) genutzt: die
 * __PLATZHALTER__ werden ersetzt und das Ergebnis nach config/config.php
 * geschrieben. config.php selbst ist gitignored und wird bei Updates NIE
 * angefasst — so bleiben DB-Zugang und vor allem der encryption_key erhalten.
 *
 * WICHTIG: __ENCRYPTION_KEY__ wird EINMALIG bei der Erstinstallation erzeugt
 * (bin2hex(random_bytes(32)) = 64 Hex-Zeichen). Danach niemals aendern, sonst
 * sind alle verschluesselten Secrets (API-Keys, SMTP, OAuth-Token) verloren.
 */

return [
    'db' => [
        'host'    => '__DB_HOST__',
        'port'    => __DB_PORT__,
        'name'    => '__DB_NAME__',
        'user'    => '__DB_USER__',
        'pass'    => '__DB_PASS__',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'debug'          => false,
        'url'            => '__APP_URL__',
        'timezone'       => 'Europe/Berlin',
        'encryption_key' => '__ENCRYPTION_KEY__',
    ],

    'ai' => [
        'openai_key'    => '',
        'anthropic_key' => '',
        'default_model' => 'gpt-4',
    ],

    'session' => [
        'name'     => 'ki_tool_session',
        'lifetime' => 86400,
    ],
];
