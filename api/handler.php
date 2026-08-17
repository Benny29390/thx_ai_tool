<?php
/**
 * API Request Handler
 */

// Output Buffering starten um PHP-Warnungen abzufangen
ob_start();

// Fehlerausgabe unterdrücken für saubere JSON-Responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/constants.php';

// Composer-Autoloader (für webklex/php-imap, symfony/mailer etc.)
// Optional: falls Composer-vendor noch nicht installiert ist, läuft das System
// weiter (nur Mail-Modul wird dann mit klarer Fehlermeldung verweigern).
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Request-Logging (vor Autoloader, sodass auch Autoload-Fehler eingefangen werden)
require_once dirname(__DIR__) . '/core/RequestLogger.php';
\Core\RequestLogger::init();

// Autoloader (Eigencode-Namespaces)
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
use Core\Session;
use Core\Auth;
use Core\Response;

// Config laden
$configFile = CONFIG_PATH . '/config.php';
if (!file_exists($configFile)) {
    Response::error('Anwendung nicht installiert', 500);
}

$config = require $configFile;

// Datenbank
$db = Database::getInstance($config['db']);

// Session & Auth
Session::start();
Auth::init($db);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

// API immer als JSON behandeln
header('Content-Type: application/json; charset=utf-8');
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest'; // Forciert JSON-Responses
$_SERVER['HTTP_ACCEPT'] = 'application/json';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// URI parsen
$uri = $_SERVER['REQUEST_URI'];
$uri = parse_url($uri, PHP_URL_PATH);
$uri = str_replace('/api/v1', '', $uri);
$uri = '/' . trim($uri, '/');

// Method
$method = $_SERVER['REQUEST_METHOD'];

// JSON Input
$input = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

// Auth prüfen (außer für bestimmte Endpoints)
$publicEndpoints = [
    '/admin/transkription/loom-quick',  // eigene Token-Auth, siehe loom-quick.php
    '/crm/brevo/webhook',                // Brevo postet hier — HMAC-Validierung im Handler
];
$isPublicPattern = preg_match('#^/crm/doi/(bestaetigen|widerruf)/[a-f0-9]{32,}$#', $uri);
$isPublic = strpos($uri, '/public/') === 0 || in_array($uri, $publicEndpoints, true) || $isPublicPattern;
if (!$isPublic && !Auth::check()) {
    Response::unauthorized('Nicht angemeldet');
}

// ============================================================================
// Customer-Portal-Sperre (default-deny): Ein 'customer'-User darf ueber die API
// AUSSCHLIESSLICH Portal- und Basis-Endpunkte erreichen. Alles andere wird hart
// geblockt — serverseitig, unabhaengig von Caps. Tenant-Isolation greift zusaetzlich
// in den Portal-Endpunkten selbst.
// ============================================================================
if (Auth::check() && !$isPublic && Auth::isCustomer()) {
    $customerApiAllow = ['/portal/', '/auth/', '/csrf', '/me-profile', '/me-', '/me', '/notifications'];
    $allowed = false;
    foreach ($customerApiAllow as $p) {
        if ($uri === rtrim($p, '/') || strpos($uri, $p) === 0) { $allowed = true; break; }
    }
    if (!$allowed) {
        Response::forbidden('Kein Zugriff (Kundenbereich)');
    }
}

// ============================================================================
// Capability-basierte Absicherung von API-Endpunkten.
// Mapping URL-Prefix → benoetigte Capability. Reihenfolge: spezifischere
// Prefixe zuerst — der erste Treffer entscheidet.
// `null` heisst: kein zusaetzlicher Cap-Check (Auth-Login reicht).
// ============================================================================
if (Auth::check() && !$isPublic) {
    $capRules = [
        // --- User-eigener Bereich (keine Cap) ---
        ['/auth/',           null],
        ['/csrf',            null],
        ['/me-profile',      null],
        ['/me-',             null],
        ['/me',              null],
        ['/feedback',        null], // internes Feedback fuer alle erlaubt
        ['/notifications',   null],

        // --- Module ---
        ['/chat-stream',           CAP_CHAT],
        ['/chat-dual-compare',     CAP_CHAT],
        ['/chat-projects',         CAP_CHAT],
        ['/chat-conversations',    CAP_CHAT],
        ['/chat-snippets',         CAP_CHAT],
        ['/chat-export-docx',      CAP_CHAT],
        ['/chat/',                 CAP_CHAT],
        ['/chat',                  CAP_CHAT],
        ['/canvas/',               null],   // KI Kompass — alle ausser Guest (Routes-Layer macht hasRole)
        ['/canvas',                null],
        ['/coworker',              CAP_COWORKER],
        ['/knowledge/',            CAP_KNOWLEDGE],
        ['/wissen',                CAP_KNOWLEDGE],

        // --- KI-Mitarbeiter ---
        ['/ki-mitarbeiter',        CAP_KI_MITARBEITER],
        ['/ai-runs',               CAP_KI_MITARBEITER],
        ['/ai-permissions',        CAP_KI_MITARBEITER],

        // --- LAM ---
        ['/lam/',                  CAP_LAM],

        // --- Mail ---
        ['/mail/',                 CAP_MAIL],

        // --- Asana / Projektplanner ---
        ['/admin/asana',           CAP_PROJEKTPLANNER],
        ['/admin/projektplanner',  CAP_PROJEKTPLANNER],

        // --- Site-Monitor ---
        ['/admin/site-monitor',    CAP_SITE_MONITOR],

        // --- Prompt-Insights ---
        ['/admin/prompt-insights', CAP_PROMPT_INSIGHTS],

        // --- Transkription ---
        ['/admin/transkription',   CAP_TRANSCRIPTION],

        // --- Artefakte / Regeln / Stil / Autoren ---
        ['/admin/artifact',        CAP_ARTIFACTS],
        ['/admin/styles',          CAP_ARTIFACTS],
        ['/admin/authors',         CAP_ARTIFACTS],
        ['/admin/author-',         CAP_ARTIFACTS],
        ['/admin/rules',           CAP_ARTIFACTS],
        ['/admin/rule-',           CAP_ARTIFACTS],
        ['/admin/suggestions',     CAP_ARTIFACTS],
        ['/admin/prompt-optimizer',CAP_ARTIFACTS],

        // --- Kundenverwaltung ---
        ['/admin/customer-',       CAP_CUSTOMERS_MANAGE],
        ['/admin/customers',       CAP_CUSTOMERS_MANAGE],

        // --- Benutzerverwaltung ---
        ['/admin/users',                   CAP_USERS_MANAGE],
        ['/admin/roles',                   CAP_USERS_MANAGE],
        ['/admin/user-customer-mapping',   CAP_USERS_MANAGE],

        // --- Einstellungen / System (Admin-Bereich) ---
        ['/admin/firewall',        CAP_FIREWALL],
        ['/admin/modules',         CAP_SETTINGS_MANAGE],
        ['/admin/update',          CAP_SETTINGS_MANAGE],
        ['/admin/settings',        CAP_SETTINGS_MANAGE],
        ['/admin/system-prompts',  CAP_SETTINGS_MANAGE],
        ['/admin/models',          CAP_SETTINGS_MANAGE],
        ['/admin/backups',         CAP_SETTINGS_MANAGE],
        ['/admin/system-log',      CAP_SETTINGS_MANAGE],
        ['/admin/jobs',            CAP_SETTINGS_MANAGE],
        ['/admin/usage',           CAP_SETTINGS_MANAGE],
        ['/admin/coworker',        CAP_COWORKER],
        ['/admin/tasks',           CAP_USERS_MANAGE],
        ['/admin/feedback',        CAP_USERS_MANAGE],
        ['/admin/measures',        CAP_USERS_MANAGE],

        // --- Sonstiges /admin/ → Admin-Rolle nötig ---
        ['/admin/migrate',         '__admin__'],
    ];

    foreach ($capRules as [$prefix, $cap]) {
        if (strpos($uri, $prefix) !== 0) continue;
        if ($cap === null) break;
        if ($cap === '__admin__') {
            if (!Auth::isAdmin()) Response::forbidden('Nur fuer Administratoren.');
        } else {
            // Modul-Gate ZUERST: ein installationsweit deaktiviertes (oder nicht
            // lizenziertes) Modul ist fuer ALLE gesperrt, auch fuer Admins.
            // capActive() ist fail-open (Kern-Caps/DB-Fehler -> true).
            if (!\Core\Modules::capActive($cap)) {
                Response::forbidden('Dieses Modul ist auf dieser Installation nicht aktiv.');
            }
            if (!Auth::can($cap)) Response::forbidden('Keine Berechtigung (Capability: ' . $cap . ').');
        }
        break;
    }

    // ------------------------------------------------------------------------
    // Read-only fuer Guests: alle modifizierenden Methoden werden geblockt,
    // mit Ausnahme von Auth/Eigenem-Account/Feedback (User darf 2FA setzen,
    // sein Profil aktualisieren, Feedback geben).
    // ------------------------------------------------------------------------
    if (Auth::isReadOnly() && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $guestWriteAllowlist = ['/auth/', '/me-', '/me', '/feedback', '/notifications'];
        $allowed = false;
        foreach ($guestWriteAllowlist as $p) {
            if (strpos($uri, $p) === 0) { $allowed = true; break; }
        }
        if (!$allowed) {
            Response::forbidden('Dieser Account ist im Lesemodus (Guest). Schreibaktionen sind nicht erlaubt.');
        }
    }
}

// CSRF prüfen bei modifizierenden Requests
// Token-authentifizierte Endpoints (Bearer / X-Tr-Token) sind ausgenommen,
// weil dort die Auth ueber den Token laeuft — Zapier/Make/curl koennen keinen
// Session-CSRF mitschicken.
$tokenAuthEndpoints = ['/admin/transkription/loom-quick'];
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])
    && !in_array($uri, $tokenAuthEndpoints, true)) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    if (!Session::validateCsrfToken($csrfToken)) {
        // Temporär deaktiviert für API-Entwicklung
        // Response::error('Ungültige Anfrage (CSRF)', 403);
    }
}

// API-Routen
try {
    switch ($uri) {
        // ===== Chat (legacy - generation) =====
        case '/chat':
            if ($method === 'POST') {
                require API_PATH . '/v1/chat.php';
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== Kundenportal: Chat (Unterhaltungen, Nachrichten, Anhaenge) =====
        case '/portal/comments':
            require API_PATH . '/v1/portal/comments.php';
            break;
        case '/portal/conversations':
            require API_PATH . '/v1/portal/conversations.php';
            break;
        case '/portal/upload':
            require API_PATH . '/v1/portal/upload.php';
            break;
        case '/portal/attachment':
            require API_PATH . '/v1/portal/attachment.php';
            break;
        case '/portal/card-file':
            require API_PATH . '/v1/portal/card-file.php';
            break;
        case '/portal/monitor-stats':
            require API_PATH . '/v1/portal/monitor-stats.php';
            break;

        // ===== Chat Conversations =====
        case '/chat/conversations':
            require API_PATH . '/v1/chat-conversations.php';
            break;

        // ===== Chat Message Debug (Info-Panel: Prompt/Tokens/Chunks) =====
        case '/chat/message-debug':
            require API_PATH . '/v1/chat-message-debug.php';
            break;

        // ===== Chat Projects =====
        case '/chat/projects':
            require API_PATH . '/v1/chat-projects.php';
            break;

        // ===== Chat Projects — KI-Cluster =====
        case '/chat/projects/suggest':
            $_GET['action'] = 'suggest';
            require API_PATH . '/v1/chat-projects.php';
            break;

        // ===== Chat User Search =====
        case '/chat/users':
            require API_PATH . '/v1/chat-conversations.php';
            break;

        // ===== Chat Folders (Sidebar Counts) =====
        case '/chat/folders':
            require API_PATH . '/v1/chat-conversations.php';
            break;

        // ===== Chat Snippets (Textbausteine) =====
        case '/chat/snippets':
            require API_PATH . '/v1/chat-snippets.php';
            break;

        // ===== Knowledge (RAG) =====
        case '/knowledge/documents':
            require API_PATH . '/v1/knowledge/documents.php';
            break;
        case '/knowledge/upload':
            require API_PATH . '/v1/knowledge/upload.php';
            break;
        case '/knowledge/url':
            require API_PATH . '/v1/knowledge/url.php';
            break;
        case '/knowledge/website':
            require API_PATH . '/v1/knowledge/website.php';
            break;
        case '/knowledge/text':
            require API_PATH . '/v1/knowledge/text.php';
            break;
        case '/knowledge/chat-import':
            require API_PATH . '/v1/knowledge/chat-import.php';
            break;
        case '/knowledge/chat-transfer':
            require API_PATH . '/v1/knowledge/chat-transfer.php';
            break;
        case '/knowledge/commit':
            require API_PATH . '/v1/knowledge/commit.php';
            break;
        case '/knowledge/search':
            require API_PATH . '/v1/knowledge/search.php';
            break;
        case '/knowledge/tag-bulk':
            require API_PATH . '/v1/knowledge/tag-bulk.php';
            break;
        case '/knowledge/facets':
            require API_PATH . '/v1/knowledge/facets.php';
            break;
        case '/knowledge/dashboard':
            require API_PATH . '/v1/knowledge/dashboard.php';
            break;
        case '/knowledge/graph-global':
            require API_PATH . '/v1/knowledge/graph-global.php';
            break;

        // ===== Chat DOCX Export =====
        case '/chat/export-docx':
            if ($method === 'POST') {
                require API_PATH . '/v1/chat-export-docx.php';
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== Projects =====
        case '/projects':
            require API_PATH . '/v1/projects.php';
            break;

        // ===== Contexts =====
        case '/contexts':
            require API_PATH . '/v1/contexts.php';
            break;

        // ===== Orders =====
        case '/orders':
            require API_PATH . '/v1/orders.php';
            break;

        // ===== Rules =====
        case '/rules':
            require API_PATH . '/v1/rules.php';
            break;

        // ===== Guidelines =====
        case '/guidelines':
            require API_PATH . '/v1/guidelines.php';
            break;

        // ===== Mein Konto =====
        case '/me/profile':
            require API_PATH . '/v1/me-profile.php';
            break;
        case '/me/asana-link':
            require API_PATH . '/v1/me-asana-link.php';
            break;

        // ===== Tagesplaner =====
        case '/planner/tasks':           $_GET['action'] = 'tasks';            require API_PATH . '/v1/planner.php'; break;
        case '/planner/sync':            $_GET['action'] = 'sync';             require API_PATH . '/v1/planner.php'; break;
        case '/planner/resolve-customers':$_GET['action'] = 'resolve-customers';require API_PATH . '/v1/planner.php'; break;
        case '/planner/bulk-set':        $_GET['action'] = 'bulk-set';         require API_PATH . '/v1/planner.php'; break;
        case '/planner/sort-slots':      $_GET['action'] = 'sort-slots';       require API_PATH . '/v1/planner.php'; break;
        case '/planner/reset-analysis':  $_GET['action'] = 'reset-analysis';   require API_PATH . '/v1/planner.php'; break;
        case '/planner/mark-seen':       $_GET['action'] = 'mark-seen';        require API_PATH . '/v1/planner.php'; break;
        case '/planner/pat':             $_GET['action'] = 'pat';              require API_PATH . '/v1/planner.php'; break;
        case '/planner/pat-status':      $_GET['action'] = 'pat-status';       require API_PATH . '/v1/planner.php'; break;
        case '/planner/estimate-efforts':$_GET['action'] = 'estimate-efforts'; require API_PATH . '/v1/planner.php'; break;
        case '/planner/plan-day':        $_GET['action'] = 'plan-day';         require API_PATH . '/v1/planner.php'; break;
        case '/planner/score':           $_GET['action'] = 'score';            require API_PATH . '/v1/planner.php'; break;
        case '/planner/week-review':     $_GET['action'] = 'week-review';      require API_PATH . '/v1/planner.php'; break;
        case '/planner/customer-hot':    $_GET['action'] = 'customer-hot';     require API_PATH . '/v1/planner.php'; break;
        case '/planner/learn/rules':     $_GET['action'] = 'learn-rules';      require API_PATH . '/v1/planner.php'; break;
        case '/planner/learn/analyze':   $_GET['action'] = 'learn-analyze';    require API_PATH . '/v1/planner.php'; break;
        case '/planner/learn/rule-status':$_GET['action'] = 'learn-rule-status';require API_PATH . '/v1/planner.php'; break;
        case '/guidelines/reorder':
            $_GET['action'] = 'reorder';
            require API_PATH . '/v1/guidelines.php';
            break;

        // ===== Feedback =====
        case '/feedback':
            require API_PATH . '/v1/feedback.php';
            break;

        case '/feedback/analyze':
            if ($method === 'POST') {
                require API_PATH . '/v1/feedback-analyze.php';
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case '/feedback/apply-rule':
            if ($method === 'POST') {
                require API_PATH . '/v1/feedback-apply-rule.php';
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== Suggestions =====
        case '/suggestions':
            require API_PATH . '/v1/suggestions.php';
            break;

        // ===== Generate Article =====
        case '/generate':
            require API_PATH . '/v1/generate.php';
            break;

        // ===== Generation Jobs =====
        case '/jobs':
            require API_PATH . '/v1/jobs.php';
            break;

        // ===== Admin: Jobs Log =====
        case '/admin/jobs/log':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            $logFile = '/var/log/generation-worker.log';
            $log = '';
            if (file_exists($logFile) && is_readable($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES);
                $log = implode("\n", array_slice($lines, -100));
            }
            Response::success(['log' => $log ?: 'Keine Log-Einträge']);
            break;

        // ===== Usage =====
        case '/usage':
            require API_PATH . '/v1/usage.php';
            break;

        // ===== Motivation =====
        case '/motivation':
            require API_PATH . '/v1/motivation.php';
            break;

        // ===== Switch Customer =====
        case '/switch-customer':
            if ($method === 'POST') {
                $customerId = (int) ($input['customer_id'] ?? 0);
                if (!$customerId) {
                    Response::error('Kunden-ID erforderlich');
                }
                if (Auth::switchCustomer($customerId)) {
                    Response::success(['customer_id' => $customerId], 'Kunde gewechselt');
                } else {
                    Response::error('Kein Zugriff auf diesen Kunden');
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== Admin-Sicht-Wechsel =====
        case '/auth/login-as':
            if ($method === 'POST') {
                $targetId = (int)($input['user_id'] ?? 0);
                if ($targetId <= 0) Response::error('user_id erforderlich');
                $result = Auth::loginAs($targetId);
                if ($result['success']) {
                    Response::success(['user' => $result['user']], 'Sicht gewechselt');
                } else {
                    Response::error($result['message']);
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case '/auth/switch-back':
            if ($method === 'POST') {
                $result = Auth::switchBack();
                if ($result['success']) {
                    Response::success(null, 'Zurueck zur Admin-Sicht');
                } else {
                    Response::error($result['message']);
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== 2FA =====
        case '/auth/2fa/setup':
            if ($method === 'POST') {
                $result = Auth::setup2FA();
                if ($result['success']) {
                    Response::success($result);
                } else {
                    Response::error($result['message']);
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case '/auth/2fa/confirm':
            if ($method === 'POST') {
                $code = $input['code'] ?? '';
                if (empty($code)) {
                    Response::error('Code erforderlich');
                }
                $result = Auth::confirm2FA($code);
                if ($result['success']) {
                    Response::success($result);
                } else {
                    Response::error($result['message']);
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case '/auth/2fa/disable':
            if ($method === 'POST') {
                $password = $input['password'] ?? '';
                if (empty($password)) {
                    Response::error('Passwort erforderlich');
                }
                $result = Auth::disable2FA($password);
                if ($result['success']) {
                    Response::success($result);
                } else {
                    Response::error($result['message']);
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== Admin: Customers =====
        case '/admin/customers':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/customers.php';
            break;
        case '/customers/quick-create':
            if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
            require API_PATH . '/v1/customers/quick-create.php';
            break;

        // ===== Admin: Customer Profile Suggest (KI-Assistent) =====
        case '/admin/rules/ai-suggest':
            if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
            require API_PATH . '/v1/admin/rules-ai-suggest.php';
            break;
        case '/admin/project-types':
            if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
            require API_PATH . '/v1/admin/project-types.php';
            break;
        case '/admin/customer-profile-suggest':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/customer-profile-suggest.php';
            break;

        // ===== Admin: Customer Wizard (Step-by-Step Anlage) =====
        case '/admin/customer-wizard/start':
            if (!Auth::isAdmin()) Response::forbidden();
            $_GET['action'] = 'start';
            require API_PATH . '/v1/admin/customer-wizard.php';
            break;

        // ===== Admin: Asana =====
        case '/admin/asana-test':
            if (!Auth::isAdmin()) Response::forbidden();
            require API_PATH . '/v1/admin/asana-test.php';
            break;
        // ===== Admin: Brevo =====
        case '/admin/brevo-test':
            if (!Auth::isAdmin()) Response::forbidden();
            require API_PATH . '/v1/admin/brevo-test.php';
            break;
        case '/admin/asana-projects':
            if (!Auth::isAdmin()) Response::forbidden();
            require API_PATH . '/v1/admin/asana-projects.php';
            break;
        case '/admin/asana-users':
            if (!Auth::isAdmin()) Response::forbidden();
            require API_PATH . '/v1/admin/asana-users.php';
            break;

        // ===== Admin: Users =====
        case '/admin/users':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/users.php';
            break;

        // ===== Admin: Rollen-Defaults =====
        case '/admin/roles':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/roles.php';
            break;

        // ===== Admin: Kundenzuordnung (Rollen + Direktzuweisung) =====
        case '/admin/user-customer-mapping':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/user-customer-mapping.php';
            break;

        // ===== Admin: Bulk-Aktionen User =====
        case '/admin/users/bulk':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/users-bulk.php';
            break;

        // ===== Admin: Settings =====
        case '/admin/settings':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/settings.php';
            break;

        // ===== Admin: System-Prompts (zentrale Prompt-Verwaltung) =====
        case '/admin/system-prompts':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/system-prompts.php';
            break;

        // ===== Admin: Wissen V2 (Qdrant + bge-m3, parallel) =====
        case '/admin/wissen-v2-status':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/wissen-v2-status.php';
            break;

        case '/admin/wissen-v2-search':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/wissen-v2-search.php';
            break;

        // ===== Admin: Styles =====
        case '/admin/styles':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/styles.php';
            break;

        // ===== Admin: Rule Types =====
        case '/admin/rule-types':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/rule-types.php';
            break;

        // ===== Admin: Rule Categories =====
        case '/admin/rule-categories':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/rule-categories.php';
            break;

        case '/admin/rule-types/sort':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            if ($method === 'POST' && !empty($input['order'])) {
                foreach ($input['order'] as $item) {
                    $db->update('rule_types', ['sort_order' => (int) $item['sort_order']], 'id = ?', [(int) $item['id']]);
                }
                Response::success(null, 'Sortierung gespeichert');
            }
            Response::error('Invalid request');
            break;

        case '/admin/rule-categories/sort':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            if ($method === 'POST' && !empty($input['order'])) {
                foreach ($input['order'] as $item) {
                    $db->update('rule_categories', ['sort_order' => (int) $item['sort_order']], 'id = ?', [(int) $item['id']]);
                }
                Response::success(null, 'Sortierung gespeichert');
            }
            Response::error('Invalid request');
            break;

        // ===== Admin: Models =====
        case '/admin/models':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/models.php';
            break;

        case '/admin/models/sort':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            if ($method === 'POST' && !empty($input['order'])) {
                foreach ($input['order'] as $item) {
                    $db->update('ai_models', ['sort_order' => (int) $item['sort_order']], 'id = ?', [(int) $item['id']]);
                }
                Response::success(null, 'Sortierung gespeichert');
            }
            Response::error('Invalid request');
            break;

        case '/admin/styles/sort':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            if ($method === 'POST' && !empty($input['order'])) {
                foreach ($input['order'] as $item) {
                    $db->update('styles', ['sort_order' => (int) $item['sort_order']], 'id = ?', [(int) $item['id']]);
                }
                Response::success(null, 'Sortierung gespeichert');
            }
            Response::error('Invalid request');
            break;

        // ===== Internal Feedback =====
        case '/feedback/internal':
            if ($method === 'POST') {
                // Debug: Request-Größe prüfen
                $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
                $postMaxSize = ini_get('post_max_size');
                error_log("Feedback request - Content-Length: {$contentLength}, post_max_size: {$postMaxSize}");

                // Feedback erstellen (für alle eingeloggten User)
                // Titel ist Pflicht, Beschreibung optional.
                $title = trim($input['title'] ?? '');
                $description = trim($input['description'] ?? '');
                if (empty($title)) {
                    Response::error('Bitte einen Titel eingeben');
                }

                // Medien verarbeiten: entweder ein Array $input['media'] = [{type,data},...]
                // (Screenshot UND Video nebeneinander) oder legacy einzelnes media_data.
                $mediaInputs = [];
                if (!empty($input['media']) && is_array($input['media'])) {
                    foreach ($input['media'] as $mi) {
                        if (!empty($mi['data'])) {
                            $mediaInputs[] = $mi['data'];
                        }
                    }
                } elseif (!empty($input['media_data']) && $input['media_data'] !== 'null' && strlen($input['media_data']) > 100) {
                    $mediaInputs[] = $input['media_data'];
                }

                // Ein Daten-URL dekodieren + speichern; gibt ['type','path'] oder null zurueck.
                $saveOneMedia = function ($dataUrl) {
                    if (!is_string($dataUrl) || !preg_match('/^data:(image|video)\/([a-zA-Z0-9]+);base64,/', $dataUrl, $m)) {
                        return null;
                    }
                    $ext = strtolower($m[2]);
                    if ($ext === 'webm') {
                        $type = 'video';
                    } elseif (in_array($ext, ['png', 'jpeg', 'jpg', 'gif'])) {
                        if ($ext === 'jpeg') $ext = 'jpg';
                        $type = 'screenshot';
                    } else {
                        $ext = 'png';
                        $type = 'screenshot';
                    }
                    $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
                    if ($bin === false || strlen($bin) < 1) {
                        return null;
                    }
                    $dir = ROOT_PATH . '/uploads/feedback';
                    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                    $fn = 'feedback_' . time() . '_' . Auth::id() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (file_put_contents($dir . '/' . $fn, $bin) === false) {
                        error_log("Feedback media save FAILED: $fn");
                        return null;
                    }
                    return ['type' => $type, 'path' => '/uploads/feedback/' . $fn];
                };

                $savedMedia = [];
                foreach ($mediaInputs as $du) {
                    $s = $saveOneMedia($du);
                    if ($s) { $savedMedia[] = $s; }
                }
                // Legacy-Felder = erstes Medium (Rueckwaertskompatibilitaet)
                $mediaType = $savedMedia[0]['type'] ?? 'none';
                $mediaPath = $savedMedia[0]['path'] ?? null;

                // Feedback-Type validieren
                $feedbackType = $input['feedback_type'] ?? 'other';
                if (!in_array($feedbackType, ['bug', 'feature', 'improvement', 'other'])) {
                    $feedbackType = 'other';
                }

                try {
                    $feedbackId = $db->insert('internal_feedback', [
                        'user_id' => Auth::id(),
                        'title' => mb_substr($title, 0, 255),
                        'page_url' => substr($input['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? '/', 0, 500),
                        'feedback_type' => $feedbackType,
                        'description' => $description,
                        'media_type' => $mediaType,
                        'media_path' => $mediaPath,
                        'browser_info' => json_encode([
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'screen_width' => $input['screen_width'] ?? null,
                            'screen_height' => $input['screen_height'] ?? null
                        ])
                    ]);

                    // Alle Medien in die n:1-Tabelle (Screenshot + Video nebeneinander)
                    foreach ($savedMedia as $s) {
                        try {
                            $db->insert('feedback_media', [
                                'feedback_id' => $feedbackId,
                                'media_type'  => $s['type'],
                                'media_path'  => $s['path'],
                            ]);
                        } catch (\Throwable $e) { /* feedback_media evtl. noch nicht migriert */ }
                    }

                    Response::success(['id' => $feedbackId], 'Feedback gesendet');
                } catch (\Exception $e) {
                    // Tabelle existiert möglicherweise nicht
                    if (strpos($e->getMessage(), "doesn't exist") !== false) {
                        Response::error('Feedback-Tabelle nicht gefunden. Bitte Migration ausführen: /admin/migrate');
                    }
                    throw $e;
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case '/admin/feedback':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/feedback.php';
            break;

        // ===== Admin: Maßnahmen (aus Feedback) =====
        case '/admin/measures':
        case '/admin/measures/analyze':
        case '/admin/measures/from-feedback':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            if ($uri === '/admin/measures/analyze') {
                $_GET['action'] = 'analyze';
            } elseif ($uri === '/admin/measures/from-feedback') {
                $_GET['action'] = 'from-feedback';
            }
            require API_PATH . '/v1/admin/measures.php';
            break;

        // ===== Admin: Authors =====
        case '/admin/authors':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/authors.php';
            break;

        // ===== Admin: Author Wizard (KI-Profil-Erstellung) =====
        case '/admin/author-wizard':
            if (!Auth::isAdmin()) {
                Response::forbidden();
            }
            require API_PATH . '/v1/admin/author-wizard.php';
            break;

        // ===== Generate: Optimize Topic =====
        case '/generate/optimize-topic':
            if ($method === 'POST') {
                require API_PATH . '/v1/generate-optimize-topic.php';
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== Documents: Extract Text =====
        case '/documents/extract':
            if ($method === 'POST') {
                require API_PATH . '/v1/documents-extract.php';
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ===== Canvas: Referenzen =====
        case '/canvas/references':
            require API_PATH . '/v1/canvas/references.php';
            break;

        default:
            // ===== Canvas API Routes =====
            if (preg_match('#^/canvas/projects(?:/(\d+))?$#', $uri, $matches)) {
                $_GET['id'] = $matches[1] ?? null;
                require API_PATH . '/v1/canvas/projects.php';
            }
            elseif (preg_match('#^/canvas/projects/(\d+)/cards$#', $uri, $matches)) {
                $_GET['canvas_id'] = $matches[1];
                require API_PATH . '/v1/canvas/cards.php';
            }
            elseif (preg_match('#^/canvas/cards/(\d+)(/status)?$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                $_GET['sub_action'] = $matches[2] ?? null;
                require API_PATH . '/v1/canvas/cards.php';
            }
            elseif (preg_match('#^/canvas/projects/(\d+)/participants$#', $uri, $matches)) {
                $_GET['canvas_id'] = $matches[1];
                require API_PATH . '/v1/canvas/participants.php';
            }
            elseif (preg_match('#^/canvas/participants/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/canvas/participants.php';
            }
            elseif (preg_match('#^/canvas/references/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/canvas/references.php';
            }
            elseif (preg_match('#^/canvas/projects/(\d+)/ai-chat$#', $uri, $matches)) {
                $_GET['canvas_id'] = $matches[1];
                require API_PATH . '/v1/canvas/ai-chat.php';
            }
            elseif (preg_match('#^/canvas/projects/(\d+)/ai-messages$#', $uri, $matches)) {
                $_GET['canvas_id'] = $matches[1];
                require API_PATH . '/v1/canvas/ai-chat.php';
            }
            elseif (preg_match('#^/canvas/projects/(\d+)/ai-check$#', $uri, $matches)) {
                $_GET['canvas_id'] = $matches[1];
                $_GET['sub_action'] = '/ai-check';
                require API_PATH . '/v1/canvas/ai-chat.php';
            }
            elseif (preg_match('#^/canvas/projects/(\d+)/export$#', $uri, $matches)) {
                $_GET['canvas_id'] = $matches[1];
                require API_PATH . '/v1/canvas/export.php';
            }
            // ===== End Canvas Routes =====
            // Check for ID-based routes

            // ===== Chat Projects by ID + sub-actions =====
            elseif (preg_match('#^/chat/projects/(\d+)(/share)?(?:/(\d+))?$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                $_GET['sub_action'] = $matches[2] ?? null;
                $_GET['share_user_id'] = $matches[3] ?? null;
                require API_PATH . '/v1/chat-projects.php';
            }
            // ===== Chat Conversations by ID + sub-actions =====
            elseif (preg_match('#^/chat/conversations/(\d+)(/upload|/stream|/share|/feedback|/dual-compare|/restore|/access-requests|/access-request|/take-over)?(?:/(\d+))?$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                $_GET['sub_action'] = $matches[2] ?? null;
                $_GET['share_user_id'] = $matches[3] ?? null;
                $_GET['sub_id'] = $matches[3] ?? null;
                if (($matches[2] ?? null) === '/stream') {
                    require API_PATH . '/v1/chat-stream.php';
                } elseif (($matches[2] ?? null) === '/dual-compare') {
                    require API_PATH . '/v1/chat-dual-compare.php';
                } elseif (($matches[2] ?? null) === '/feedback') {
                    require API_PATH . '/v1/chat-conversation-feedback.php';
                } else {
                    require API_PATH . '/v1/chat-conversations.php';
                }
            }
            // ===== Context Browse Items =====
            if (preg_match('#^/contexts/browse-items$#', $uri)) {
                require API_PATH . '/v1/context-browse-items.php';
            }
            // ===== Context by ID =====
            elseif (preg_match('#^/contexts/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/contexts.php';
            }
            // ===== Order History =====
            elseif (preg_match('#^/orders/(\d+)/history$#', $uri, $matches)) {
                if ($method === 'GET') {
                    require API_PATH . '/v1/order-history.php';
                } else {
                    Response::error('Method not allowed', 405);
                }
            }
            // ===== Order Active Job =====
            elseif (preg_match('#^/orders/(\d+)/active-job$#', $uri, $matches)) {
                if ($method === 'GET') {
                    $activeOrderId = (int) $matches[1];
                    require_once SERVICES_PATH . '/JobQueue.php';
                    $activeJobQueue = new \Services\JobQueue($db);
                    $activeJob = $activeJobQueue->getActiveJobForOrder($activeOrderId);
                    if ($activeJob) {
                        Response::success([
                            'job_id' => $activeJob['id'],
                            'job_type' => $activeJob['job_type'] ?? null,
                            'status' => $activeJob['status']
                        ]);
                    } else {
                        Response::success(null);
                    }
                } else {
                    Response::error('Method not allowed', 405);
                }
            }
            // ===== Order Stream (SSE) =====
            elseif (preg_match('#^/orders/(\d+)/stream/(briefing|briefing-interview|briefing-chat|article|article-chat|inline-edit)$#', $uri, $matches)) {
                require API_PATH . '/v1/order-stream.php';
            }
            // ===== Order Briefing =====
            elseif (preg_match('#^/orders/(\d+)/briefing/(generate|chat|approve|reopen|save)$#', $uri, $matches)) {
                require API_PATH . '/v1/order-briefing.php';
            }
            // ===== Order Article =====
            elseif (preg_match('#^/orders/(\d+)/article/(generate|content|chat)$#', $uri, $matches)) {
                require API_PATH . '/v1/order-article.php';
            }
            // ===== Order Versions =====
            elseif (preg_match('#^/orders/(\d+)/versions(?:/(\d+)(?:/restore)?)?$#', $uri, $matches)) {
                require API_PATH . '/v1/order-versions.php';
            }
            // ===== Order Learning =====
            elseif (preg_match('#^/orders/(\d+)/learning/(suggest-rule|accept-rule|reject-rule|optimize-briefing)(?:/(\d+))?$#', $uri, $matches)) {
                require API_PATH . '/v1/order-learning.php';
            }
            // ===== Order Duplicate =====
            elseif (preg_match('#^/orders/(\d+)/duplicate$#', $uri, $matches)) {
                require API_PATH . '/v1/order-duplicate.php';
            }
            // ===== Order by ID =====
            elseif (preg_match('#^/orders/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/orders.php';
            }
            // ===== Project Versions =====
            elseif (preg_match('#^/projects/(\d+)/versions$#', $uri, $matches)) {
                $projectId = (int) $matches[1];

                // Check access
                $project = $db->queryOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
                if (!$project || !Auth::canAccessCustomer($project['customer_id'])) {
                    Response::forbidden();
                }

                if ($method === 'GET') {
                    // List all versions
                    $versions = $db->query(
                        "SELECT id, version_number, word_count, created_at FROM article_versions
                         WHERE project_id = ? ORDER BY version_number DESC",
                        [$projectId]
                    );
                    Response::success($versions);
                } else {
                    Response::error('Method not allowed', 405);
                }
            } elseif (preg_match('#^/projects/(\d+)/versions/(\d+)/restore$#', $uri, $matches)) {
                $projectId = (int) $matches[1];
                $versionNumber = (int) $matches[2];

                // Check access
                $project = $db->queryOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
                if (!$project || !Auth::canAccessCustomer($project['customer_id'])) {
                    Response::forbidden();
                }

                if ($method === 'POST') {
                    // Restore a version
                    $oldVersion = $db->queryOne(
                        "SELECT * FROM article_versions WHERE project_id = ? AND version_number = ?",
                        [$projectId, $versionNumber]
                    );

                    if (!$oldVersion) {
                        Response::notFound('Version nicht gefunden');
                    }

                    // Get sections from old version
                    $oldSections = $db->query(
                        "SELECT * FROM article_sections WHERE article_version_id = ? ORDER BY section_order",
                        [$oldVersion['id']]
                    );

                    // Get current max version number
                    $maxVersion = $db->queryOne(
                        "SELECT MAX(version_number) as max_ver FROM article_versions WHERE project_id = ?",
                        [$projectId]
                    );
                    $newVersionNumber = ($maxVersion['max_ver'] ?? 0) + 1;

                    // Create new version as copy
                    $newVersionId = $db->insert('article_versions', [
                        'project_id' => $projectId,
                        'version_number' => $newVersionNumber,
                        'content' => $oldVersion['content'],
                        'word_count' => $oldVersion['word_count']
                    ]);

                    // Copy sections
                    foreach ($oldSections as $section) {
                        $db->insert('article_sections', [
                            'article_version_id' => $newVersionId,
                            'section_order' => $section['section_order'],
                            'heading_level' => $section['heading_level'],
                            'heading_text' => $section['heading_text'],
                            'content' => $section['content'],
                            'word_count' => $section['word_count'],
                            'model_used' => $section['model_used']
                        ]);
                    }

                    // Update project timestamp
                    $db->update('projects', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$projectId]);

                    Response::success(['version_number' => $newVersionNumber], 'Version wiederhergestellt');
                } else {
                    Response::error('Method not allowed', 405);
                }
            } elseif (preg_match('#^/projects/(\d+)/versions/(\d+)$#', $uri, $matches)) {
                $projectId = (int) $matches[1];
                $versionNumber = (int) $matches[2];

                // Check access
                $project = $db->queryOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
                if (!$project || !Auth::canAccessCustomer($project['customer_id'])) {
                    Response::forbidden();
                }

                if ($method === 'GET') {
                    // Get specific version
                    $version = $db->queryOne(
                        "SELECT * FROM article_versions WHERE project_id = ? AND version_number = ?",
                        [$projectId, $versionNumber]
                    );

                    if (!$version) {
                        Response::notFound('Version nicht gefunden');
                    }

                    // Get sections
                    $version['sections'] = $db->query(
                        "SELECT * FROM article_sections WHERE article_version_id = ? ORDER BY section_order",
                        [$version['id']]
                    );

                    Response::success($version);
                } else {
                    Response::error('Method not allowed', 405);
                }
            } elseif (preg_match('#^/projects/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/projects.php';
            } elseif (preg_match('#^/knowledge/tags/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/knowledge-tags.php';
            } elseif (preg_match('#^/knowledge/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/knowledge.php';
            } elseif (preg_match('#^/rules/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/rules.php';
            } elseif (preg_match('#^/guidelines/(\d+)/toggle$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                $_GET['action'] = 'toggle';
                require API_PATH . '/v1/guidelines.php';
            } elseif (preg_match('#^/guidelines/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/guidelines.php';
            } elseif ($uri === '/admin/querschnitt-task') {
                // Querschnittsaufgabe: eine Asana-Sammelaufgabe mit je einer Unteraufgabe pro Kunde
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/querschnitt-task.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/knowledge$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                // Legacy — knowledge_entries wurde entfernt
                Response::success([]);
            } elseif (preg_match('#^/admin/customers/(\d+)/websites(?:/(\d+)/(monitoring|primary|delete))?$#', $uri, $matches)) {
                // Kunden-Websites (pm_monitors als einzige Quelle)
                if (!Auth::isAdmin() && !Auth::canAccessCustomer((int)$matches[1])) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                if (!empty($matches[2])) { $_GET['monitor_id'] = $matches[2]; $_GET['ws_action'] = $matches[3]; }
                require API_PATH . '/v1/admin/customer-websites.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/portal/(module|card-visible|user|ki-toggle|shell-visible)$#', $uri, $matches)) {
                // Kundenportal-Verwaltung: Modul-Freischaltung, Kachel-Sichtbarkeit, Customer-User
                if (!Auth::isAdmin() && !Auth::canAccessCustomer((int)$matches[1])) Response::forbidden();
                $_GET['customer_id']   = $matches[1];
                $_GET['portal_action'] = $matches[2];
                require API_PATH . '/v1/admin/customer-portal.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/asana-sync$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-asana-sync.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/asana-status$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'status';
                require API_PATH . '/v1/admin/customer-asana-sync.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/asana-config$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-asana-config.php';
            } elseif ($uri === '/planner/accounts') {
                $_GET['action'] = '';
                require API_PATH . '/v1/planner-accounts.php';
            } elseif (preg_match('#^/planner/accounts/(\d+)$#', $uri, $matches)) {
                $_GET['account_id'] = $matches[1];
                $_GET['action'] = '';
                require API_PATH . '/v1/planner-accounts.php';
            } elseif (preg_match('#^/planner/accounts/(\d+)/(delete)$#', $uri, $matches)) {
                $_GET['account_id'] = $matches[1];
                $_GET['action'] = $matches[2];
                require API_PATH . '/v1/planner-accounts.php';
            } elseif (preg_match('#^/planner/tasks/(\d+)/(effort|complete|postpone|ignore|set-field|ack-signal|ack-reanalyzed|waiting-candidates)$#', $uri, $matches)) {
                $_GET['task_id'] = $matches[1];
                $_GET['action'] = $matches[2];
                require API_PATH . '/v1/planner.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/rules$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-rules.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/project-types$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-add-project-type.php';
            } elseif (preg_match('#^/admin/rules/(\d+)/scope$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['rule_id'] = $matches[1];
                require API_PATH . '/v1/admin/rule-scope.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/website-crawl/sync$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'sync';
                require API_PATH . '/v1/admin/customer-website-crawl.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/website-crawl/sitemap$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'sitemap';
                require API_PATH . '/v1/admin/customer-website-crawl.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/website-crawl$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-website-crawl.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/knowledge-quickadd$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-knowledge-quickadd.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/logo$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-logo.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/fetch-favicon$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-favicon.php';
            } elseif ($uri === '/admin/customers/bulk-fetch-favicons') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['bulk'] = '1';
                require API_PATH . '/v1/admin/customer-favicon.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/(\d+)/versions/(\d+)/restore$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['card_id'] = $matches[2];
                $_GET['version_id'] = $matches[3];
                $_GET['action'] = 'versions';
                $_SERVER['REQUEST_METHOD'] = 'POST';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/(\d+)/versions$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['card_id'] = $matches[2];
                $_GET['action'] = 'versions';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/(\d+)/files/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['card_id'] = $matches[2];
                $_GET['file_id'] = $matches[3];
                $_GET['action'] = 'files';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/(\d+)/files$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['card_id'] = $matches[2];
                $_GET['action'] = 'files';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/reorder$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'reorder';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/kanban$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'kanban';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/apply-layout-template$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'apply';
                require API_PATH . '/v1/admin/card-layout-templates.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/steckbrief-import$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/steckbrief-import.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/steckbrief-import/(\d+)/(analyze|commit)$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['import_id'] = $matches[2];
                $_GET['action'] = $matches[3];
                require API_PATH . '/v1/admin/steckbrief-import.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/steckbrief-suggest$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/steckbrief-suggest.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/steckbrief-suggestions$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'list';
                require API_PATH . '/v1/admin/steckbrief-suggest.php';
            } elseif (preg_match('#^/admin/customer-cards/(\d+)/suggest$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['card_id'] = $matches[1];
                $_GET['action'] = 'one-card';
                require API_PATH . '/v1/admin/steckbrief-suggest.php';
            } elseif (preg_match('#^/admin/customer-cards/(\d+)/suggestions/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['card_id'] = $matches[1];
                $_GET['suggestion_id'] = $matches[2];
                $_GET['action'] = 'decide';
                require API_PATH . '/v1/admin/steckbrief-suggest.php';
            } elseif (preg_match('#^/admin/card-layout-templates(?:/(\d+))?$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                if (!empty($matches[1])) $_GET['template_id'] = $matches[1];
                require API_PATH . '/v1/admin/card-layout-templates.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/auto-arrange$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'auto-arrange';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/auto-import$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'auto-import';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/tasks/(\d+)/rollback$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['id'] = $matches[1];
                $_GET['action'] = 'rollback';
                require API_PATH . '/v1/admin/tasks.php';
            } elseif (preg_match('#^/admin/tasks/(\d+)/reply$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['id'] = $matches[1];
                $_GET['action'] = 'reply';
                require API_PATH . '/v1/admin/tasks.php';
            } elseif (preg_match('#^/admin/tasks/(\d+)/run$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['id'] = $matches[1];
                $_GET['action'] = 'run';
                require API_PATH . '/v1/admin/tasks.php';
            } elseif (preg_match('#^/admin/tasks/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/tasks.php';
            } elseif ($uri === '/admin/tasks/modules') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'modules';
                require API_PATH . '/v1/admin/tasks.php';
            } elseif ($uri === '/admin/tasks') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/admin/tasks.php';
            } elseif (preg_match('#^/admin/coworker/sessions/(\d+)/messages/(\d+)/rollback$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['session_id'] = $matches[1];
                $_GET['message_id'] = $matches[2];
                $_GET['action'] = 'rollback_message';
                require API_PATH . '/v1/admin/coworker.php';
            } elseif (preg_match('#^/admin/coworker/sessions/(\d+)/message$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['session_id'] = $matches[1];
                $_GET['action'] = 'message';
                require API_PATH . '/v1/admin/coworker.php';
            } elseif (preg_match('#^/admin/coworker/sessions/(\d+)/reply$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['session_id'] = $matches[1];
                $_GET['action'] = 'reply';
                require API_PATH . '/v1/admin/coworker.php';
            } elseif (preg_match('#^/admin/coworker/sessions/(\d+)/stop$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['session_id'] = $matches[1];
                $_GET['action'] = 'stop';
                require API_PATH . '/v1/admin/coworker.php';
            } elseif (preg_match('#^/admin/coworker/sessions/(\d+)/rollback$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['session_id'] = $matches[1];
                $_GET['action'] = 'rollback';
                require API_PATH . '/v1/admin/coworker.php';
            } elseif (preg_match('#^/admin/coworker/sessions/(\d+)/bash-log$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['session_id'] = $matches[1];
                $_GET['action'] = 'bash_log';
                require API_PATH . '/v1/admin/coworker.php';
            } elseif (preg_match('#^/admin/coworker/sessions/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['session_id'] = $matches[1];
                require API_PATH . '/v1/admin/coworker.php';
            } elseif ($uri === '/admin/coworker/sessions') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/admin/coworker.php';
            } elseif ($uri === '/admin/backups/run') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'run';
                require API_PATH . '/v1/admin/backups.php';
            } elseif ($uri === '/admin/backups') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/admin/backups.php';
            } elseif ($uri === '/admin/modules') {
                require API_PATH . '/v1/admin/modules.php';
            } elseif ($uri === '/admin/update') {
                require API_PATH . '/v1/admin/update.php';
            }
            // ====== Projektplanner Routen ======
            elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/rows/(\d+)/save-beacon$#', $uri, $m)) {
                // Beacon-Endpoint: POST mit JSON-Body wirkt wie PUT auf die Row.
                // Wird von navigator.sendBeacon beim Tab-Schließen genutzt, weil
                // sendBeacon nur POST kann. Auth läuft normal über Session-Cookie.
                if (!Auth::can(CAP_PROJEKTPLANNER)) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['row_id'] = $m[2];
                $_SERVER['REQUEST_METHOD'] = 'PUT';
                require API_PATH . '/v1/admin/projektplanner/rows.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/rows/reorder$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['action'] = 'reorder';
                require API_PATH . '/v1/admin/projektplanner/rows.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/rows/(\d+)/move$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['row_id'] = $m[2]; $_GET['action'] = 'move';
                require API_PATH . '/v1/admin/projektplanner/rows.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/rows/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['row_id'] = $m[2];
                require API_PATH . '/v1/admin/projektplanner/rows.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/rows$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/rows.php';
            } elseif ($uri === '/admin/projektplanner/generate-plan') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/generate-plan.php';
            } elseif ($uri === '/admin/projektplanner/knowledge-sync-status') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/knowledge-sync-status.php';
            } elseif (preg_match('#^/admin/projektplanner/ai-rules/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['rule_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/ai-rules.php';
            } elseif ($uri === '/admin/projektplanner/ai-rules') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/ai-rules.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/hard$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['action'] = 'hard';
                require API_PATH . '/v1/admin/projektplanner/plans.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/sync-knowledge$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['action'] = 'sync-knowledge';
                require API_PATH . '/v1/admin/projektplanner/plans.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/sparring(?:/(stream|materialize|apply|rule))?$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['sub'] = $m[2] ?? '';
                require API_PATH . '/v1/admin/projektplanner/sparring.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/(duplicate|ai-enrich|share|budget-soll|abrechnung-einzel|restore|share-password)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['action'] = $m[2];
                require API_PATH . '/v1/admin/projektplanner/plans.php';
            } elseif ($uri === '/projektplanner/plan-risiko') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/projektplanner/plan-risiko.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/shares/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['share_user_id'] = $m[2];
                require API_PATH . '/v1/admin/projektplanner/shares.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/shares$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/shares.php';
            } elseif ($uri === '/admin/projektplanner/users-for-share') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'users';
                require API_PATH . '/v1/admin/projektplanner/shares.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/feedback/read-all$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['action'] = 'read-all';
                require API_PATH . '/v1/admin/projektplanner/feedback.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/feedback/(\d+)/(read|unread)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['feedback_id'] = $m[2]; $_GET['action'] = $m[3];
                require API_PATH . '/v1/admin/projektplanner/feedback.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/feedback/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['feedback_id'] = $m[2];
                require API_PATH . '/v1/admin/projektplanner/feedback.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/feedback$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/feedback.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/revisions/(\d+)/restore$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1]; $_GET['revision_id'] = $m[2]; $_GET['action'] = 'restore';
                require API_PATH . '/v1/admin/projektplanner/revisions.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/revisions$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/revisions.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)/export$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/export.php';
            } elseif ($uri === '/admin/projektplanner/export-person') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/export.php';
            } elseif ($uri === '/admin/projektplanner/backup-json') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'backup-json';
                require API_PATH . '/v1/admin/projektplanner/export.php';
            } elseif (preg_match('#^/admin/projektplanner/plans/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['plan_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/plans.php';
            } elseif ($uri === '/admin/projektplanner/plans') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/plans.php';
            } elseif ($uri === '/admin/projektplanner/multi-share') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'multi-share';
                $_GET['plan_id'] = 0;
                require API_PATH . '/v1/admin/projektplanner/plans.php';
            } elseif (preg_match('#^/admin/projektplanner/multi-share/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'multi-share-detail';
                $_GET['plan_id'] = 0;
                $_GET['share_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/plans.php';
            } elseif ($uri === '/admin/projektplanner/team/users') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'users';
                require API_PATH . '/v1/admin/projektplanner/team.php';
            } elseif (preg_match('#^/admin/projektplanner/team/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['member_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/team.php';
            } elseif ($uri === '/admin/projektplanner/team') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/team.php';
            } elseif ($uri === '/admin/projektplanner/import/preview') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'preview';
                require API_PATH . '/v1/admin/projektplanner/import.php';
            } elseif ($uri === '/admin/projektplanner/import/reset') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'reset';
                require API_PATH . '/v1/admin/projektplanner/import.php';
            } elseif ($uri === '/admin/projektplanner/import') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/import.php';
            } elseif (preg_match('#^/admin/projektplanner/budget/overview$#', $uri)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'overview';
                require API_PATH . '/v1/admin/projektplanner/budget.php';
            } elseif (preg_match('#^/admin/projektplanner/budget/(\d+)/(override|uebertrag)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $m[1]; $_GET['action'] = $m[2];
                require API_PATH . '/v1/admin/projektplanner/budget.php';
            } elseif (preg_match('#^/admin/projektplanner/budget/(\d+)/(\d{4})$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $m[1]; $_GET['year'] = $m[2];
                require API_PATH . '/v1/admin/projektplanner/budget.php';
            } elseif (preg_match('#^/admin/projektplanner/budget/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/budget.php';
            } elseif (preg_match('#^/admin/projektplanner/person-shares/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['share_id'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/person-shares.php';
            } elseif ($uri === '/admin/projektplanner/person-shares') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/person-shares.php';
            } elseif ($uri === '/admin/projektplanner/dashboard') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/dashboard.php';
            } elseif ($uri === '/admin/projektplanner/widgets') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/projektplanner/widgets.php';
            } elseif ($uri === '/admin/projektplanner/my-open-tasks') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'my-open-tasks';
                require API_PATH . '/v1/admin/projektplanner/dashboard.php';
            } elseif (preg_match('#^/admin/projektplanner/asana/(projects|sections|search|create|link|unlink|templates|sync-status|refresh-cache|orphans|unlink-orphans|subtasks|import-subtasks)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/asana.php';
            } elseif (preg_match('#^/admin/projektplanner/asana/task/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'task'; $_GET['task_gid'] = $m[1];
                require API_PATH . '/v1/admin/projektplanner/asana.php';
            }
            // ====== Site-Monitor ======
            elseif ($uri === '/admin/site-monitor/categories') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'categories';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/batch-import') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'batch-import';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/bulk-category') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'bulk-category';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/fetch-title') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'fetch-title';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/test-report') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'test-report';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/cleanup-logs') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'cleanup-logs';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/import-preview') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'import-preview';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/import') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'import';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/import-history') {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['action'] = 'import-history';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/test-alert') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'test-alert';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor/mail-log') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['action'] = 'mail-log';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif (preg_match('#^/admin/site-monitor/customer/(\d+)/stats$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $m[1]; $_GET['action'] = 'customer-stats';
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif (preg_match('#^/admin/site-monitor/(\d+)/(toggle|check-now|stats|log|incidents)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['id'] = $m[1]; $_GET['action'] = $m[2];
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif (preg_match('#^/admin/site-monitor/(\d+)$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/admin/site-monitor.php';
            } elseif ($uri === '/admin/site-monitor') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/admin/site-monitor.php';
            }
            // ====== Prompt-Insights ======
            elseif ($uri === '/admin/prompt-insights/imports') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                require API_PATH . '/v1/admin/prompt-insights/imports.php';
            } elseif (preg_match('#^/admin/prompt-insights/imports/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['import_id'] = $m[1];
                require API_PATH . '/v1/admin/prompt-insights/imports.php';
            } elseif ($uri === '/admin/prompt-insights/whitelist') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                require API_PATH . '/v1/admin/prompt-insights/whitelist.php';
            } elseif ($uri === '/admin/prompt-insights/whitelist/init') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'init';
                require API_PATH . '/v1/admin/prompt-insights/whitelist.php';
            } elseif (preg_match('#^/admin/prompt-insights/whitelist/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['entry_id'] = $m[1];
                require API_PATH . '/v1/admin/prompt-insights/whitelist.php';
            } elseif ($uri === '/admin/prompt-insights/stats') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'stats';
                require API_PATH . '/v1/admin/prompt-insights/stats.php';
            } elseif ($uri === '/admin/prompt-insights/browse') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'browse';
                require API_PATH . '/v1/admin/prompt-insights/stats.php';
            } elseif ($uri === '/admin/prompt-insights/embed') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'embed';
                require API_PATH . '/v1/admin/prompt-insights/clusters.php';
            } elseif ($uri === '/admin/prompt-insights/recluster') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'recluster';
                require API_PATH . '/v1/admin/prompt-insights/clusters.php';
            } elseif ($uri === '/admin/prompt-insights/clusters') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'list';
                require API_PATH . '/v1/admin/prompt-insights/clusters.php';
            } elseif (preg_match('#^/admin/prompt-insights/clusters/(\d+)/samples$#', $uri, $m)) {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'samples';
                $_GET['cluster_id'] = $m[1];
                require API_PATH . '/v1/admin/prompt-insights/clusters.php';
            } elseif (preg_match('#^/admin/prompt-insights/rules/derive/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'derive';
                $_GET['cluster_id'] = $m[1];
                require API_PATH . '/v1/admin/prompt-insights/rules.php';
            } elseif ($uri === '/admin/prompt-insights/rules/export') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['action'] = 'export';
                require API_PATH . '/v1/admin/prompt-insights/rules.php';
            } elseif ($uri === '/admin/prompt-insights/rules') {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                require API_PATH . '/v1/admin/prompt-insights/rules.php';
            } elseif (preg_match('#^/admin/prompt-insights/rules/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();
                $_GET['rule_id'] = $m[1];
                require API_PATH . '/v1/admin/prompt-insights/rules.php';
            }
            // ====== Firewall / IP-Sperren (fail2ban) ======
            elseif ($uri === '/admin/firewall' || $uri === '/admin/firewall/unban') {
                if (!Auth::can(CAP_FIREWALL)) Response::forbidden();
                require API_PATH . '/v1/admin/firewall.php';
            }
            // ====== Transkription ======
            elseif ($uri === '/admin/transkription/jobs') {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                require API_PATH . '/v1/admin/transkription/jobs.php';
            } elseif (preg_match('#^/admin/transkription/jobs/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['job_id'] = $m[1];
                require API_PATH . '/v1/admin/transkription/jobs.php';
            } elseif (preg_match('#^/admin/transkription/jobs/(\d+)/retry$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['job_id'] = $m[1]; $_GET['job_action'] = 'retry';
                require API_PATH . '/v1/admin/transkription/jobs.php';
            } elseif (preg_match('#^/admin/transkription/jobs/(\d+)/result$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['job_id'] = $m[1]; $_GET['result_action'] = 'result';
                require API_PATH . '/v1/admin/transkription/result.php';
            } elseif (preg_match('#^/admin/transkription/jobs/(\d+)/speakers$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['job_id'] = $m[1]; $_GET['result_action'] = 'speakers';
                require API_PATH . '/v1/admin/transkription/result.php';
            } elseif (preg_match('#^/admin/transkription/jobs/(\d+)/apply-corrections$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['job_id'] = $m[1]; $_GET['result_action'] = 'apply-corrections';
                require API_PATH . '/v1/admin/transkription/result.php';
            } elseif ($uri === '/admin/transkription/corrections') {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                require API_PATH . '/v1/admin/transkription/corrections.php';
            } elseif (preg_match('#^/admin/transkription/corrections/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['correction_id'] = $m[1];
                require API_PATH . '/v1/admin/transkription/corrections.php';
            } elseif (preg_match('#^/admin/transkription/jobs/(\d+)/outputs$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['job_id'] = $m[1]; $_GET['outputs_action'] = 'list';
                require API_PATH . '/v1/admin/transkription/outputs.php';
            } elseif (preg_match('#^/admin/transkription/jobs/(\d+)/to-knowledge$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['job_id'] = $m[1]; $_GET['outputs_action'] = 'to-knowledge';
                require API_PATH . '/v1/admin/transkription/outputs.php';
            } elseif (preg_match('#^/admin/transkription/outputs/(\d+)/download$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['output_id'] = $m[1]; $_GET['outputs_action'] = 'download';
                require API_PATH . '/v1/admin/transkription/outputs.php';
            } elseif (preg_match('#^/admin/transkription/outputs/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['output_id'] = $m[1]; $_GET['outputs_action'] = 'delete-output';
                require API_PATH . '/v1/admin/transkription/outputs.php';
            } elseif ($uri === '/admin/transkription/inbox') {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                require API_PATH . '/v1/admin/transkription/inbox.php';
            } elseif ($uri === '/admin/transkription/tokens') {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                require API_PATH . '/v1/admin/transkription/tokens.php';
            } elseif (preg_match('#^/admin/transkription/tokens/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['token_id'] = $m[1];
                require API_PATH . '/v1/admin/transkription/tokens.php';
            } elseif ($uri === '/admin/transkription/loom-quick') {
                // Token-Auth — kein Session-Check, eigener Pfad
                require API_PATH . '/v1/admin/transkription/loom-quick.php';
            } elseif ($uri === '/admin/transkription/settings') {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                require API_PATH . '/v1/admin/transkription/settings.php';
            } elseif ($uri === '/admin/transkription/templates') {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                require API_PATH . '/v1/admin/transkription/templates.php';
            } elseif (preg_match('#^/admin/transkription/templates/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_TRANSCRIPTION)) Response::forbidden();
                $_GET['template_id'] = $m[1];
                require API_PATH . '/v1/admin/transkription/templates.php';
            }
            // ====== KI-Mitarbeiter-Builder ======
            elseif (strpos($uri, '/ki-mitarbeiter') === 0 || strpos($uri, '/ai-runs') === 0 || strpos($uri, '/ai-permissions') === 0) {
                // Cap-Gate ist bereits ueber capRules erledigt; interne Sub-Route-Verteilung im File.
                require API_PATH . '/v1/ki-mitarbeiter.php';
            }
            // ====== LAM-System (Linkaufbau-Management) ======
            elseif ($uri === '/lam/dashboard') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/dashboard.php';
            } elseif ($uri === '/lam/dashboard-widgets') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/dashboard-widgets.php';
            } elseif ($uri === '/lam/linkquellen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkquellen.php';
            } elseif ($uri === '/lam/linkquellen-import-preview') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkquellen-import-preview.php';
            } elseif ($uri === '/lam/linkquellen-import-commit') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkquellen-import-commit.php';
            } elseif ($uri === '/lam/domain-detail') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-detail.php';
            } elseif ($uri === '/lam/domain-inline') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-inline.php';
            } elseif ($uri === '/lam/domain-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-save.php';
            } elseif ($uri === '/lam/domain-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-bulk.php';
            } elseif ($uri === '/lam/domain-kunde-toggle') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-kunde-toggle.php';
            } elseif ($uri === '/lam/domain-tag-toggle') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-tag-toggle.php';
            } elseif ($uri === '/lam/domain-tags-set') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-tags-set.php';
            } elseif ($uri === '/lam/tags') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/tags.php';
            } elseif ($uri === '/lam/tag-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/tag-save.php';
            } elseif ($uri === '/lam/tag-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/tag-loeschen.php';
            } elseif ($uri === '/lam/tag-merge') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/tag-merge.php';
            } elseif ($uri === '/lam/linkziele') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkziele.php';
            } elseif ($uri === '/lam/linkziel-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkziel-save.php';
            } elseif ($uri === '/lam/linkziel-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkziel-loeschen.php';
            } elseif ($uri === '/lam/linkziele-kunde') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkziele-kunde.php';
            } elseif ($uri === '/lam/domain-wissen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-wissen.php';
            } elseif ($uri === '/lam/domain-wissen-delete') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-wissen-delete.php';
            } elseif ($uri === '/lam/domain-wissen-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-wissen-save.php';
            } elseif ($uri === '/lam/domain-wissen-anwenden') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-wissen-anwenden.php';
            } elseif ($uri === '/lam/linkprofil-snapshots') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkprofil-snapshots.php';
            } elseif ($uri === '/lam/linkprofil-snapshot-diff') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkprofil-snapshot-diff.php';
            } elseif ($uri === '/lam/linkprofil-statistik') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkprofil-statistik.php';
            } elseif ($uri === '/lam/verlinkung-klassifizieren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkung-klassifizieren.php';
            } elseif ($uri === '/lam/erreichbarkeit-queue') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/erreichbarkeit-queue.php';
            } elseif ($uri === '/lam/verlinkungen-bulk-aktionen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkungen-bulk-aktionen.php';
            } elseif ($uri === '/lam/verlinkungen-zu-linkquellen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkungen-zu-linkquellen.php';
            } elseif ($uri === '/lam/verlinkungen-klassifizieren-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkungen-klassifizieren-bulk.php';
            } elseif ($uri === '/lam/sitewide-cluster') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sitewide-cluster.php';
            } elseif ($uri === '/lam/sitewide-cluster-detail') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sitewide-cluster-detail.php';
            } elseif ($uri === '/lam/sitewide-cluster-aktion') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sitewide-cluster-aktion.php';
            } elseif ($uri === '/lam/historien-import-ausfuehren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/historien-import-ausfuehren.php';
            } elseif ($uri === '/lam/kontakt-verifikation') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kontakt-verifikation.php';
            } elseif ($uri === '/lam/kondition-verifikation') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kondition-verifikation.php';
            } elseif ($uri === '/lam/aufgaben') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufgaben.php';
            } elseif ($uri === '/lam/aufgabe-anlegen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufgabe-anlegen.php';
            } elseif ($uri === '/lam/aufgabe-aktualisieren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufgabe-aktualisieren.php';
            } elseif ($uri === '/lam/ki-linkart') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/ki-linkart.php';
            } elseif ($uri === '/lam/ki-tags-vorschlag') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/ki-tags-vorschlag.php';
            } elseif ($uri === '/lam/ki-recherche-domain') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/ki-recherche-domain.php';
            } elseif ($uri === '/lam/ki-spalten-mapping') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/ki-spalten-mapping.php';
            } elseif ($uri === '/lam/ki-domain-matching') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/ki-domain-matching.php';
            } elseif ($uri === '/lam/xlsx-parse') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/xlsx-parse.php';
            } elseif ($uri === '/lam/linkprofil-excel') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkprofil-excel.php';
            } elseif ($uri === '/lam/auslagen-excel') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/auslagen-excel.php';
            } elseif ($uri === '/lam/audit-log') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/audit-log.php';
            } elseif ($uri === '/lam/domain-erreichbarkeit-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-erreichbarkeit-bulk.php';
            } elseif ($uri === '/lam/anbieter-aus-impressum') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter-aus-impressum.php';
            } elseif ($uri === '/lam/domain-anbieter-zuordnen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-anbieter-zuordnen.php';
            } elseif ($uri === '/lam/domain-anbieter-rolle') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-anbieter-rolle.php';
            } elseif ($uri === '/lam/domain-anbieter-flags') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-anbieter-flags.php';
            } elseif ($uri === '/lam/domain-anbieter-verschieben') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-anbieter-verschieben.php';
            } elseif ($uri === '/lam/anbieter-domain-entfernen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter-domain-entfernen.php';
            } elseif ($uri === '/lam/domain-anbieter-entfernen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-anbieter-entfernen.php';
            } elseif ($uri === '/lam/portfolio-import/upload') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/portfolio-import-upload.php';
            } elseif (preg_match('#^/lam/portfolio-import/([A-Z0-9]{26})/analyse$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['batch_id'] = $m[1];
                require API_PATH . '/v1/lam/portfolio-import-analyse.php';
            } elseif (preg_match('#^/lam/portfolio-import/([A-Z0-9]{26})/preview$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['batch_id'] = $m[1];
                require API_PATH . '/v1/lam/portfolio-import-preview.php';
            } elseif (preg_match('#^/lam/portfolio-import/([A-Z0-9]{26})/commit$#', $uri, $m)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['batch_id'] = $m[1];
                require API_PATH . '/v1/lam/portfolio-import-commit.php';
            } elseif ($uri === '/lam/asana-massnahme-tasks') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-massnahme-tasks.php';
            } elseif ($uri === '/lam/asana-massnahme-sections') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-massnahme-sections.php';
            } elseif ($uri === '/lam/linkoption-asana-verknuepfen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-asana-verknuepfen.php';
            } elseif ($uri === '/lam/linkoption-asana-neu') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-asana-neu.php';
            } elseif ($uri === '/lam/linkoption-asana-vorschau') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-asana-vorschau.php';
            } elseif ($uri === '/lam/linkoption-asana-tasks') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-asana-tasks.php';
            } elseif ($uri === '/lam/linkoption-asana-sections') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-asana-sections.php';
            } elseif ($uri === '/lam/linkoption-artikelthemen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-artikelthemen.php';
            } elseif ($uri === '/lam/linkoption-reorder') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-reorder.php';
            } elseif ($uri === '/lam/linkoption-asana-entkoppeln') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-asana-entkoppeln.php';
            } elseif ($uri === '/lam/linkoption-asana-aktualisieren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-asana-aktualisieren.php';
            } elseif ($uri === '/lam/asana-verknuepfen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-verknuepfen.php';
            } elseif ($uri === '/lam/asana-entkoppeln') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-entkoppeln.php';
            } elseif ($uri === '/lam/asana-aktualisieren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-aktualisieren.php';
            } elseif ($uri === '/lam/asana-extrahieren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-extrahieren.php';
            } elseif ($uri === '/lam/asana-uebernehmen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-uebernehmen.php';
            } elseif ($uri === '/lam/asana-kunde-konfig') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/asana-kunde-konfig.php';
            } elseif ($uri === '/lam/sistrix-abruf') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sistrix-abruf.php';
            } elseif ($uri === '/lam/sistrix-status') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sistrix-status.php';
            } elseif ($uri === '/lam/sistrix-vorschau') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sistrix-vorschau.php';
            } elseif ($uri === '/lam/aufraeum-vorschlaege') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-vorschlaege.php';
            } elseif ($uri === '/lam/aufraeum-domain-detail') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-domain-detail.php';
            } elseif ($uri === '/lam/aufraeum-klassifiziere-ki') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-klassifiziere-ki.php';
            } elseif ($uri === '/lam/aufraeum-annehmen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-annehmen.php';
            } elseif ($uri === '/lam/aufraeum-bulk-annehmen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-bulk-annehmen.php';
            } elseif ($uri === '/lam/aufraeum-rueckgaengig') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-rueckgaengig.php';
            } elseif ($uri === '/lam/aufraeum-domain-notiz') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-domain-notiz.php';
            } elseif ($uri === '/lam/aufraeum-muster-analysieren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-muster-analysieren.php';
            } elseif ($uri === '/lam/aufraeum-muster') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-muster.php';
            } elseif ($uri === '/lam/aufraeum-muster-anwenden') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-muster-anwenden.php';
            } elseif ($uri === '/lam/aufraeum-muster-preview') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/aufraeum-muster-preview.php';
            } elseif ($uri === '/lam/verlinkung-ziel-ermitteln') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkung-ziel-ermitteln.php';
            } elseif ($uri === '/lam/kunden-kontext') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kunden-kontext.php';
            } elseif ($uri === '/lam/kunden-linkarten') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kunden-linkarten.php';
            } elseif ($uri === '/lam/sistrix-manuell') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sistrix-manuell.php';
            } elseif ($uri === '/lam/sistrix-settings') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/lam/sistrix-settings.php';
            } elseif ($uri === '/lam/kondition-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kondition-save.php';
            } elseif ($uri === '/lam/kondition-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kondition-loeschen.php';
            } elseif ($uri === '/lam/domain-link-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-link-save.php';
            } elseif ($uri === '/lam/domain-link-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/domain-link-loeschen.php';
            } elseif ($uri === '/lam/anbieter') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter.php';
            } elseif ($uri === '/lam/anbieter-detail') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter-detail.php';
            } elseif ($uri === '/lam/anbieter-inline') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter-inline.php';
            } elseif ($uri === '/lam/anbieter-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter-bulk.php';
            } elseif ($uri === '/lam/anbieter-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter-save.php';
            } elseif ($uri === '/lam/kontakt-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kontakt-save.php';
            } elseif ($uri === '/lam/kontakt-aktion') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/kontakt-aktion.php';
            } elseif ($uri === '/lam/linkakquise') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkakquise.php';
            } elseif ($uri === '/lam/linkoptionen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoptionen.php';
            } elseif ($uri === '/lam/linkoptionen-kunden') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoptionen-kunden.php';
            } elseif ($uri === '/lam/linkoptionen-pool') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoptionen-pool.php';
            } elseif ($uri === '/lam/linkpool-add') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkpool-add.php';
            } elseif ($uri === '/lam/linkpool-remove') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkpool-remove.php';
            } elseif ($uri === '/lam/massnahmen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/massnahmen.php';
            } elseif ($uri === '/lam/massnahme-detail') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/massnahme-detail.php';
            } elseif ($uri === '/lam/massnahme-inline') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/massnahme-inline.php';
            } elseif ($uri === '/lam/massnahme-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/massnahme-save.php';
            } elseif ($uri === '/lam/vorschlagslisten' ||
                      strpos($uri, '/lam/vorschlagsliste-') === 0) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/vorschlagslisten.php';
            } elseif ($uri === '/lam/linkoption-zu-massnahme') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-zu-massnahme.php';
            } elseif ($uri === '/lam/sistrix-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/sistrix-bulk.php';
            } elseif ($uri === '/lam/monitoring-check') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/monitoring-check.php';
            } elseif ($uri === '/lam/korrespondenz-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/korrespondenz-save.php';
            } elseif ($uri === '/lam/linkprofil-import') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkprofil-import.php';
            } elseif ($uri === '/lam/linkprofil-export'
                   || $uri === '/lam/massnahmen-export'
                   || $uri === '/lam/auslagen-export') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/csv-export.php';
            } elseif ($uri === '/lam/massnahme-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/massnahme-bulk.php';
            } elseif ($uri === '/lam/auslage-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/auslage-save.php';
            } elseif ($uri === '/lam/auslage-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/auslage-loeschen.php';
            } elseif ($uri === '/lam/linkoption-inline') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-inline.php';
            } elseif ($uri === '/lam/linkoption-rueckmeldung') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-rueckmeldung.php';
            } elseif ($uri === '/lam/linkoption-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkoption-bulk.php';
            } elseif ($uri === '/lam/auslagen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/auslagen.php';
            } elseif ($uri === '/lam/monitoring') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/monitoring.php';
            } elseif ($uri === '/lam/monitoring-aktion') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/monitoring-aktion.php';
            } elseif ($uri === '/lam/korrespondenz-anhang') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/korrespondenz-anhang.php';
            } elseif ($uri === '/lam/korrespondenz') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/korrespondenz.php';
            } elseif ($uri === '/lam/anbieter-kurz') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/anbieter-kurz.php';
            } elseif ($uri === '/lam/linkprofil/kunden') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/linkprofil-kunden.php';
            } elseif ($uri === '/lam/verlinkungen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkungen.php';
            } elseif ($uri === '/lam/verlinkung-inline') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkung-inline.php';
            } elseif ($uri === '/lam/verlinkung-bulk') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/lam/verlinkung-bulk.php';
            }
            // ====== CRM (Kontakte) ======
            elseif ($uri === '/crm/dashboard') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/dashboard.php';
            } elseif ($uri === '/crm/kontakte') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/kontakte.php';
            } elseif ($uri === '/crm/kontakte/bulk') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/kontakte-bulk.php';
            } elseif (preg_match('#^/crm/kontakte/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/crm/kontakt.php';
            } elseif (preg_match('#^/crm/kontakte/(\d+)/(aktivitaeten|aktivitaet|tags|listen|foto|inline|merge|adressen|social)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                $_GET['id'] = $m[1];
                $_GET['action'] = $m[2];
                require API_PATH . '/v1/crm/kontakt.php';
            } elseif ($uri === '/crm/firmen') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/firmen.php';
            } elseif (preg_match('#^/crm/firmen/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/crm/firma.php';
            } elseif (preg_match('#^/crm/firmen/(\d+)/logo$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                $_GET['firma_id'] = $m[1];
                require API_PATH . '/v1/crm/firma-logo.php';
            } elseif (preg_match('#^/crm/firmen/(\d+)/fetch-favicon$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                $_GET['firma_id'] = $m[1];
                require API_PATH . '/v1/crm/firma-favicon.php';
            } elseif ($uri === '/crm/pflege') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/pflege.php';
            } elseif ($uri === '/crm/tags') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/tags.php';
            } elseif (preg_match('#^/crm/tags/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM_VOKABULAR)) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/crm/tag.php';
            } elseif ($uri === '/crm/listen') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/listen.php';
            } elseif (preg_match('#^/crm/listen/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM_VOKABULAR)) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/crm/liste.php';
            } elseif ($uri === '/crm/segmente') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/segmente.php';
            } elseif (preg_match('#^/crm/segmente/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/crm/segment.php';
            } elseif ($uri === '/crm/branchen') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/branchen.php';
            } elseif ($uri === '/crm/dubletten') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/dubletten.php';
            } elseif ($uri === '/crm/migration/start') {
                if (!Auth::can(CAP_CRM_MIGRATION)) Response::forbidden();
                require API_PATH . '/v1/crm/migration-start.php';
            } elseif ($uri === '/crm/migration/status') {
                if (!Auth::can(CAP_CRM_MIGRATION)) Response::forbidden();
                require API_PATH . '/v1/crm/migration-status.php';
            } elseif ($uri === '/crm/migration/runs') {
                if (!Auth::can(CAP_CRM_MIGRATION)) Response::forbidden();
                require API_PATH . '/v1/crm/migration-runs.php';
            } elseif ($uri === '/crm/brevo/webhook') {
                // ÖFFENTLICH — Brevo postet hier rein; Signatur-Validierung im Handler
                require API_PATH . '/v1/crm/brevo-webhook.php';
            } elseif ($uri === '/crm/doi/erfassen') {
                if (!Auth::can(CAP_CRM)) Response::forbidden();
                require API_PATH . '/v1/crm/doi-erfassen.php';
            } elseif (preg_match('#^/crm/doi/bestaetigen/([a-f0-9]{32,})$#', $uri, $m)) {
                // ÖFFENTLICH — DOI-Bestätigungslink aus Mail
                $_GET['token'] = $m[1];
                require API_PATH . '/v1/crm/doi-bestaetigen.php';
            } elseif (preg_match('#^/crm/doi/widerruf/([a-f0-9]{32,})$#', $uri, $m)) {
                // ÖFFENTLICH — Abmelde-Link
                $_GET['token'] = $m[1];
                require API_PATH . '/v1/crm/doi-widerruf.php';
            } elseif (preg_match('#^/crm/dsgvo/auskunft/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM_DSGVO)) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/crm/dsgvo-auskunft.php';
            } elseif (preg_match('#^/crm/dsgvo/hard-delete/(\d+)$#', $uri, $m)) {
                if (!Auth::can(CAP_CRM_DSGVO)) Response::forbidden();
                $_GET['id'] = $m[1];
                require API_PATH . '/v1/crm/dsgvo-hard-delete.php';
            }
            // ====== Public-Routen für Projektplanner (kein Auth) ======
            elseif (preg_match('#^/public/projektplan/([a-f0-9]+)/feedback/(\d+)$#', $uri, $m)) {
                $_GET['hash'] = $m[1]; $_GET['feedback_id'] = $m[2];
                require API_PATH . '/v1/public/projektplan.php';
            } elseif (preg_match('#^/public/projektplan/([a-f0-9]+)/feedback$#', $uri, $m)) {
                $_GET['hash'] = $m[1]; $_GET['action'] = 'feedback';
                require API_PATH . '/v1/public/projektplan.php';
            } elseif (preg_match('#^/public/projektplan/([a-f0-9]+)/auth$#', $uri, $m)) {
                $_GET['hash'] = $m[1]; $_GET['action'] = 'auth';
                require API_PATH . '/v1/public/projektplan.php';
            } elseif (preg_match('#^/public/projektplan/([a-f0-9]+)$#', $uri, $m)) {
                $_GET['hash'] = $m[1];
                require API_PATH . '/v1/public/projektplan.php';
            } elseif (preg_match('#^/public/personen-aufgaben/([a-f0-9]+)$#', $uri, $m)) {
                $_GET['hash'] = $m[1];
                require API_PATH . '/v1/public/projektplan-person.php';
            } elseif (preg_match('#^/public/projektplan-uebersicht/([a-f0-9]+)/auth$#', $uri, $m)) {
                $_GET['hash'] = $m[1]; $_GET['action'] = 'auth';
                require API_PATH . '/v1/public/projektplan-uebersicht.php';
            } elseif (preg_match('#^/public/projektplan-uebersicht/([a-f0-9]+)$#', $uri, $m)) {
                $_GET['hash'] = $m[1];
                require API_PATH . '/v1/public/projektplan-uebersicht.php';
            } elseif (preg_match('#^/public/personen-aufgaben/([a-f0-9]+)/toggle-done$#', $uri, $m)) {
                $_GET['hash'] = $m[1];
                $_GET['action'] = 'toggle-done';
                require API_PATH . '/v1/public/projektplan-person.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/ai-search$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['action'] = 'ai-search';
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                $_GET['card_id'] = $matches[2];
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/cards$#', $uri, $matches)) {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                $_GET['customer_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-cards.php';
            } elseif (preg_match('#^/admin/customer-wizard/([a-f0-9]+)/step/([a-z_]+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['wizard_id'] = $matches[1];
                $_GET['step'] = $matches[2];
                $_GET['action'] = 'step';
                require API_PATH . '/v1/admin/customer-wizard.php';
            } elseif (preg_match('#^/admin/customer-wizard/([a-f0-9]+)/commit$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['wizard_id'] = $matches[1];
                $_GET['action'] = 'commit';
                require API_PATH . '/v1/admin/customer-wizard.php';
            } elseif (preg_match('#^/admin/customer-wizard/([a-f0-9]+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['wizard_id'] = $matches[1];
                require API_PATH . '/v1/admin/customer-wizard.php';
            } elseif (preg_match('#^/admin/asana-projects/([a-zA-Z0-9]+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['gid'] = $matches[1];
                require API_PATH . '/v1/admin/asana-projects.php';
            } elseif (preg_match('#^/admin/customers/(\d+)/dependencies$#', $uri, $matches)) {
                if (!Auth::isAdmin()) Response::forbidden();
                $_GET['id'] = $matches[1];
                $_GET['action'] = 'dependencies';
                require API_PATH . '/v1/admin/customers.php';
            } elseif (preg_match('#^/admin/customers/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/customers.php';
            } elseif (preg_match('#^/admin/users/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/users.php';
            } elseif (preg_match('#^/admin/styles/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/styles.php';
            } elseif (preg_match('#^/admin/models/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/models.php';
            } elseif (preg_match('#^/admin/rule-types/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/rule-types.php';
            } elseif (preg_match('#^/admin/rule-categories/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/rule-categories.php';
            } elseif (preg_match('#^/admin/authors/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/authors.php';
            } elseif (preg_match('#^/admin/feedback/(\d+)/analyze$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                $_GET['action'] = 'analyze';
                require API_PATH . '/v1/admin/feedback.php';
            } elseif (preg_match('#^/admin/feedback/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/feedback.php';
            } elseif (preg_match('#^/admin/measures/(\d+)$#', $uri, $matches)) {
                if (!Auth::isAdmin()) {
                    Response::forbidden();
                }
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/admin/measures.php';
            } elseif (preg_match('#^/knowledge/documents/(\d+)/reprocess$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/knowledge/reprocess.php';
            } elseif (preg_match('#^/knowledge/documents/(\d+)(?:/(graph))?$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                if (($matches[2] ?? '') === 'graph') {
                    $_GET['sub_action'] = 'graph';
                }
                require API_PATH . '/v1/knowledge/documents.php';
            } elseif (preg_match('#^/chat/snippets/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/chat-snippets.php';
            } elseif (preg_match('#^/jobs/(\d+)$#', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                require API_PATH . '/v1/jobs.php';
            }
            // ===== Mail-Modul =====
            elseif ($uri === '/mail/konten') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/konten.php';
            } elseif ($uri === '/mail/konto-save') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/konto-save.php';
            } elseif ($uri === '/mail/konto-loeschen') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/konto-loeschen.php';
            } elseif ($uri === '/mail/konto-test') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/konto-test.php';
            } elseif ($uri === '/mail/oauth-start') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/oauth-start.php';
            } elseif ($uri === '/mail/oauth-callback') {
                // Rueckleitung von Microsoft — kommt als normaler Browser-Aufruf mit Session-Cookie.
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/oauth-callback.php';
            } elseif ($uri === '/mail/ordner-auswahl') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/ordner-auswahl.php';
            } elseif ($uri === '/mail/stil-lernen') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/stil-lernen.php';
            } elseif ($uri === '/mail/lernen') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/lernen.php';
            } elseif ($uri === '/mail/antwort-verfeinern') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/antwort-verfeinern.php';
            } elseif ($uri === '/mail/neue-mail-entwurf') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/neue-mail-entwurf.php';
            } elseif ($uri === '/mail/weiterleiten-entwurf') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/weiterleiten-entwurf.php';
            } elseif ($uri === '/mail/weiterleiten') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/weiterleiten.php';
            } elseif ($uri === '/mail/empfaenger-vorschlaege') {
                if (!Auth::isAdmin()) Response::forbidden();
                require API_PATH . '/v1/mail/empfaenger-vorschlaege.php';
            } elseif ($uri === '/mail/pull') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/pull.php';
            } elseif ($uri === '/mail/nachrichten') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/nachrichten.php';
            } elseif ($uri === '/mail/nachricht-detail') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/nachricht-detail.php';
            } elseif ($uri === '/mail/nachricht-aktion') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/nachricht-aktion.php';
            } elseif ($uri === '/mail/klassifizieren') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/klassifizieren.php';
            } elseif ($uri === '/mail/antwort-senden') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/antwort-senden.php';
            } elseif ($uri === '/mail/mail-senden') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/mail-senden.php';
            } elseif ($uri === '/mail/eml-upload') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/eml-upload.php';
            } elseif ($uri === '/mail/anhang') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/anhang.php';
            } elseif ($uri === '/mail/vorlagen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/vorlagen.php';
            } elseif ($uri === '/mail/vorlage-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/vorlage-save.php';
            } elseif ($uri === '/mail/vorlage-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/vorlage-loeschen.php';
            } elseif ($uri === '/mail/regeln') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/regeln.php';
            } elseif ($uri === '/mail/regel-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/regel-save.php';
            } elseif ($uri === '/mail/regel-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/regel-loeschen.php';
            } elseif ($uri === '/mail/lam-verknuepfen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/lam-verknuepfen.php';
            } elseif ($uri === '/mail/lam-kondition-anlegen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/lam-kondition-anlegen.php';
            } elseif ($uri === '/mail/lam-massnahme-status') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/lam-massnahme-status.php';
            } elseif ($uri === '/mail/ungelesen-zaehler') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/ungelesen-zaehler.php';
            } elseif ($uri === '/mail/antwort-anhang-upload') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/antwort-anhang-upload.php';
            } elseif ($uri === '/mail/personen-sicht') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/personen-sicht.php';
            } elseif ($uri === '/mail/ordner') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/ordner.php';
            } elseif ($uri === '/mail/ordner-save') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/ordner-save.php';
            } elseif ($uri === '/mail/ordner-loeschen') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/ordner-loeschen.php';
            } elseif ($uri === '/mail/verschieben') {
                if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
                require API_PATH . '/v1/mail/verschieben.php';
            }
            else {
                Response::notFound('API-Endpunkt nicht gefunden');
            }
    }
} catch (Exception $e) {
    if (headers_sent()) {
        // SSE-Stream laeuft bereits — Fehler als SSE-Event senden
        echo "data: " . json_encode(['type' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    } else {
        Response::error($e->getMessage(), 500);
    }
}
