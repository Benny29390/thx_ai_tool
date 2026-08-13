<?php
/**
 * Anwendungskonstanten
 */

// Anwendung
define('APP_NAME', 'KI Text Tool');
define('APP_VERSION', '1.0.0');
define('APP_LANGUAGE', 'de');

// Pfade
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CORE_PATH', ROOT_PATH . '/core');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('SERVICES_PATH', ROOT_PATH . '/services');
define('MODELS_PATH', ROOT_PATH . '/models');
define('API_PATH', ROOT_PATH . '/api');

// Storage-Unterverzeichnisse
define('VECTORS_PATH', STORAGE_PATH . '/vectors');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('EXPORTS_PATH', STORAGE_PATH . '/exports');
define('LOGS_PATH', STORAGE_PATH . '/logs');

// Session
define('SESSION_NAME', 'ki_tool_session');
define('SESSION_LIFETIME', 15552000); // 180 Tage

// Benutzerrollen — 4-stufige Hierarchie
//   admin   = Vollzugriff inkl. Settings + Userverwaltung
//   manager = Kernteam, volle Schreibrechte in den freigeschalteten Caps
//   user    = Standard-Mitarbeiter, kann eigene Inhalte anlegen/bearbeiten
//   guest   = Lesemodus, Schreibaktionen blockiert
define('ROLE_ADMIN',   'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_USER',    'user');
define('ROLE_GUEST',   'guest');
// Customer Portal: externer Kundennutzer, an genau 1 Kunden gebunden, read-only + Kommentare.
// Hat KEINE Team-Caps (default-deny); Sichtbarkeit steuert die Portal-Permission-Matrix.
define('ROLE_CUSTOMER', 'customer');
// Legacy-Alias (vor 4-Rollen-Refactor): 'editor' war die niedrigste Stufe
define('ROLE_EDITOR',  'user');

// Capabilities — feingranulare Funktions-Freischaltungen
define('CAP_CHAT',              'chat');
define('CAP_ARTIFACTS',         'artifacts');
define('CAP_KNOWLEDGE',         'knowledge');
define('CAP_COWORKER',          'coworker');
define('CAP_LAM',               'lam');
define('CAP_PROJEKTPLANNER',    'projektplanner');
define('CAP_MAIL',               'mail');
define('CAP_SITE_MONITOR',      'site_monitor');
define('CAP_CUSTOMERS_VIEW',    'customers_view');
define('CAP_CUSTOMERS_MANAGE',  'customers_manage');
define('CAP_USERS_MANAGE',      'users_manage');
define('CAP_SETTINGS_MANAGE',   'settings_manage');
define('CAP_PROMPT_INSIGHTS',   'prompt_insights');
define('CAP_TRANSCRIPTION',     'transcription');
define('CAP_CRM',               'crm');
define('CAP_CRM_DSGVO',         'crm_dsgvo');
define('CAP_CRM_MIGRATION',     'crm_migration');
define('CAP_CRM_VOKABULAR',     'crm_vokabular');
define('CAP_FIREWALL',          'firewall');
define('CAP_STYLEGUIDE',        'styleguide');

// Regel-Typen
define('RULE_STYLE', 'style');
define('RULE_FORMAT', 'format');
define('RULE_CONTENT', 'content');
define('RULE_LINK', 'link');
define('RULE_TONE', 'tone');

// KI-Provider
define('AI_OPENAI', 'openai');
define('AI_ANTHROPIC', 'anthropic');

// Standard-Einstellungen
define('DEFAULT_MODEL', 'gpt-4');
define('DEFAULT_MAX_TOKENS', 4000);
define('DEFAULT_EMBEDDING_MODEL', 'text-embedding-3-small');
define('EMBEDDING_DIMENSIONS', 1536);

// Feedback-Typen
define('FEEDBACK_POSITIVE', 'positive');
define('FEEDBACK_NEGATIVE', 'negative');
define('FEEDBACK_NEUTRAL', 'neutral');

// Projekt-Status
define('STATUS_DRAFT', 'draft');
define('STATUS_IN_PROGRESS', 'in_progress');
define('STATUS_REVIEW', 'review');
define('STATUS_COMPLETED', 'completed');

// CSRF Token Lifetime
define('CSRF_TOKEN_LIFETIME', 3600); // 1 Stunde
