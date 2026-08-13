<?php
/**
 * Hauptanwendungsklasse
 */

namespace Core;

class App
{
    private static ?App $instance = null;
    private array $config;
    private Database $db;
    private Router $router;
    private bool $installed = false;

    private function __construct()
    {
        $this->loadConfig();
        $this->setupErrorHandling();
    }

    public static function getInstance(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konfiguration laden
     */
    private function loadConfig(): void
    {
        $configFile = CONFIG_PATH . '/config.php';

        if (file_exists($configFile)) {
            $this->config = require $configFile;
            $this->installed = true;
        } else {
            $this->config = [];
            $this->installed = false;
        }

        // Zeitzone setzen
        date_default_timezone_set($this->config['app']['timezone'] ?? 'Europe/Berlin');
    }

    /**
     * Error Handling
     */
    private function setupErrorHandling(): void
    {
        $debug = $this->config['app']['debug'] ?? false;

        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        set_exception_handler(function (\Throwable $e) use ($debug) {
            $this->handleException($e, $debug);
        });
    }

    /**
     * Exception Handler
     */
    private function handleException(\Throwable $e, bool $debug): void
    {
        $message = $debug ? $e->getMessage() : 'Ein Fehler ist aufgetreten';

        // Log schreiben
        $logFile = LOGS_PATH . '/error.log';
        $logMessage = sprintf(
            "[%s] %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        if (Router::isAjax()) {
            Response::error($message, 500);
        } else {
            http_response_code(500);
            echo "<!DOCTYPE html><html><head><title>Fehler</title></head><body>";
            echo "<h1>Ein Fehler ist aufgetreten</h1>";
            if ($debug) {
                echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n\n";
                echo htmlspecialchars($e->getTraceAsString()) . "</pre>";
            }
            echo "</body></html>";
        }
        exit;
    }

    /**
     * Anwendung starten
     */
    public function run(): void
    {
        // Installer prüfen
        if (!$this->installed) {
            $this->runInstaller();
            return;
        }

        // Datenbank initialisieren
        $this->db = Database::getInstance($this->config['db']);

        // Session starten
        Session::start();

        // Auth initialisieren
        Auth::init($this->db);

        // Router erstellen
        $this->router = new Router();
        $this->setupRoutes();

        // Request verarbeiten
        $this->router->dispatch();
    }

    /**
     * Installer ausführen
     */
    private function runInstaller(): void
    {
        require_once ROOT_PATH . '/install.php';
        $installer = new \Installer();
        $installer->run();
    }

    /**
     * Routen definieren
     */
    private function setupRoutes(): void
    {
        $router = $this->router;
        $db = $this->db;

        // Middleware: Auth prüfen
        $authMiddleware = function () {
            if (!Auth::check()) {
                if (Router::isAjax()) {
                    Response::unauthorized();
                } else {
                    Router::redirect('/login');
                }
                return false;
            }
            return true;
        };

        // Middleware: Admin prüfen
        $adminMiddleware = function () {
            if (!Auth::isAdmin()) {
                Response::forbidden('Nur für Administratoren');
                return false;
            }
            return true;
        };

        // Middleware: Manager+ prüfen
        $managerMiddleware = function () {
            if (!Auth::hasRole(ROLE_MANAGER)) {
                Response::forbidden('Keine Berechtigung');
                return false;
            }
            return true;
        };

        // Middleware-Factory: Capability pruefen.
        // Verwendung in Routen:  [$authMiddleware, $capMiddleware(CAP_LAM)]
        $capMiddleware = function (string $cap) {
            return function () use ($cap) {
                // Modul-Gate ZUERST: ist das Modul hinter diesem Cap auf dieser
                // Installation aktiv? Greift fuer ALLE inkl. Admin. capActive() ist
                // fail-open (Kern-Caps/DB-Fehler -> true), sperrt also nie die
                // Verwaltung aus.
                if (!\Core\Modules::capActive($cap)) {
                    Response::forbidden('Dieses Modul ist auf dieser Installation nicht aktiv.');
                    return false;
                }
                if (!Auth::can($cap)) {
                    Response::forbidden('Keine Berechtigung fuer diese Funktion (Capability: ' . $cap . ')');
                    return false;
                }
                return true;
            };
        };

        // Middleware: Schreibaktionen fuer Read-only-User (Guest) blocken.
        // Idee: GET ist erlaubt, alles andere wird verweigert.
        $writeBlocker = function () {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if (!Auth::isReadOnly() || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                return true;
            }
            Response::forbidden('Dieser Account ist im Lesemodus (Guest). Schreibaktionen sind nicht erlaubt.');
            return false;
        };

        // ===== Customer-Portal-Sperre (global, default-deny) =====
        // Ein 'customer'-User darf ausschliesslich den Kundenbereich /portal/* erreichen
        // (plus Login/Logout). Jede andere Web-Route wird serverseitig geblockt.
        $router->middleware(function () {
            if (!Auth::check() || !Auth::isCustomer()) return true;
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $uri = '/' . trim($uri, '/');
            $allowed = $uri === '/portal' || strpos($uri, '/portal/') === 0
                    || in_array($uri, ['/logout', '/login'], true);
            if ($allowed) return true;
            if (Router::isAjax()) {
                Response::forbidden('Kein Zugriff (Kundenbereich)');
            } else {
                Router::redirect('/portal/dashboard');
            }
            return false;
        });

        // ===== Öffentliche Routen =====

        // Login
        $router->get('/login', function () {
            if (Auth::check()) {
                Router::redirect('/');
                return;
            }
            Response::view('auth/login', [], 'auth');
        });

        $router->post('/login', function () {
            $email = Router::input('email');
            $password = Router::input('password');
            $csrf = Router::input('csrf_token');

            if (!Session::validateCsrfToken($csrf)) {
                Session::flash('error', 'Ungültige Anfrage');
                Router::redirect('/login');
                return;
            }

            $result = Auth::login($email, $password);

            if ($result['success']) {
                if (!empty($result['requires_2fa'])) {
                    Router::redirect('/login/2fa');
                } else {
                    Router::redirect('/');
                }
            } else {
                Session::flash('error', $result['message']);
                Router::redirect('/login');
            }
        });

        // 2FA-Verifizierung beim Login
        $router->get('/login/2fa', function () {
            if (!Auth::hasPending2FA()) {
                Router::redirect('/login');
                return;
            }
            Response::view('auth/2fa-verify', [], 'auth');
        });

        $router->post('/login/2fa', function () {
            $code = Router::input('code');
            $isBackup = Router::input('is_backup') === '1';
            $csrf = Router::input('csrf_token');

            if (!Session::validateCsrfToken($csrf)) {
                Session::flash('error', 'Ungültige Anfrage');
                Router::redirect('/login/2fa');
                return;
            }

            if ($isBackup) {
                $result = Auth::verifyBackupCode($code);
            } else {
                $result = Auth::verify2FA($code);
            }

            if ($result['success']) {
                Router::redirect('/');
            } else {
                Session::flash('error', $result['message']);
                Router::redirect('/login/2fa');
            }
        });

        // Logout
        $router->get('/logout', function () {
            Auth::logout();
            Router::redirect('/login');
        });

        // Passwort setzen (Einladungs-Link)
        $router->get('/set-password', function () use ($db) {
            $token = $_GET['token'] ?? '';
            if (empty($token)) {
                Session::flash('error', 'Ungueltiger Link');
                Router::redirect('/login');
            }
            $user = $db->queryOne(
                "SELECT id, name, email FROM users WHERE invite_token = ? AND invite_expires_at > NOW()",
                [$token]
            );
            if (!$user) {
                Session::flash('error', 'Einladung abgelaufen oder ungueltig');
                Router::redirect('/login');
            }
            Response::view('auth/set-password', ['token' => $token, 'user' => $user], 'auth');
        });

        $router->post('/set-password', function () use ($db) {
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (empty($token) || empty($password)) {
                Session::flash('error', 'Passwort erforderlich');
                Router::redirect('/set-password?token=' . urlencode($token));
            }
            if (strlen($password) < 10) {
                Session::flash('error', 'Passwort muss mindestens 10 Zeichen haben');
                Router::redirect('/set-password?token=' . urlencode($token));
            }
            if (!preg_match('/\d/', $password)) {
                Session::flash('error', 'Passwort muss mindestens eine Ziffer enthalten');
                Router::redirect('/set-password?token=' . urlencode($token));
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
                Session::flash('error', 'Passwort muss mindestens ein Sonderzeichen enthalten');
                Router::redirect('/set-password?token=' . urlencode($token));
            }
            if ($password !== $passwordConfirm) {
                Session::flash('error', 'Passwoerter stimmen nicht ueberein');
                Router::redirect('/set-password?token=' . urlencode($token));
            }

            $user = $db->queryOne(
                "SELECT id FROM users WHERE invite_token = ? AND invite_expires_at > NOW()",
                [$token]
            );
            if (!$user) {
                Session::flash('error', 'Einladung abgelaufen oder ungueltig');
                Router::redirect('/login');
            }

            $db->update('users', [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'invite_token' => null,
                'invite_expires_at' => null,
            ], 'id = ?', [$user['id']]);

            Session::flash('success', 'Passwort gesetzt — melde dich jetzt an!');
            Router::redirect('/login');
        });

        // ===== Geschützte Routen =====

        // Sicherheitseinstellungen + Konto-Verknuepfungen
        $router->get('/settings/security', function () use ($db) {
            $has2FA = Auth::has2FAEnabled();
            $backupCodesRemaining = $has2FA ? Auth::getRemainingBackupCodes() : 0;
            $user = Auth::user();
            $asanaConfigured = !empty(\Core\Settings::get('asana_pat'));
            Response::view('settings/security', [
                'has2FA' => $has2FA,
                'backupCodesRemaining' => $backupCodesRemaining,
                'currentUser' => $user,
                'asanaConfigured' => $asanaConfigured,
            ]);
        }, [$authMiddleware]);

        // Dashboard
        $router->get('/', function () use ($db) {
            $stats = $this->getDashboardStats($db);
            Response::view('admin/dashboard', ['stats' => $stats]);
        }, [$authMiddleware]);

        // ===== Customer Portal (Kundenansicht) =====
        $router->get('/portal', function () {
            Router::redirect('/portal/dashboard');
        }, [$authMiddleware]);

        $router->get('/portal/dashboard', function () use ($db) {
            // Tenant-Isolation: Kunde kommt aus Auth (Customer) bzw. ist Admin-Vorschau.
            $customerId = null;
            if (Auth::isCustomer()) {
                $customerId = Auth::portalCustomerId();
            } elseif (Auth::isAdmin()) {
                $customerId = isset($_GET['customer']) ? (int) $_GET['customer'] : null; // Vorschau
            }
            if (!$customerId) {
                if (Auth::isAdmin()) { Router::redirect('/admin/customers'); return; }
                Response::forbidden('Kein zugeordneter Kunde');
                return;
            }
            require_once SERVICES_PATH . '/CustomerPortalService.php';
            $svc = new \Services\CustomerPortalService($db);
            $header = $svc->customerHeader($customerId);
            if (!$header) { Response::forbidden('Kunde nicht gefunden'); return; }
            $modules = $svc->enabledModules($customerId);
            Response::view('portal/dashboard', [
                'header'        => $header,
                'modules'       => $modules,
                'projektstatus' => in_array('projektstatus', $modules, true) ? $svc->projektstatus($customerId) : null,
                'meilensteine'  => in_array('meilensteine', $modules, true) ? $svc->meilensteine($customerId) : [],
                'cardsByTab'    => $svc->visibleCardsByTab($customerId),
                'tabOrder'      => \Services\CustomerPortalService::TAB_ORDER,
                'tabLabels'     => \Services\CustomerPortalService::TAB_LABELS,
                'ppWidget'      => $svc->projektplannerWidget($customerId), // exakt die Steckbrief-Komponente
                'customerId'    => $customerId,
                'isPreview'     => !Auth::isCustomer(),
            ]); // volles App-Gerüst (main.php) — Sidebar/Topbar wie ueberall
        }, [$authMiddleware]);

        // Kundenportal-Chat (vollbreiter eigener Menuepunkt, KI + Team)
        $router->get('/portal/chat', function () use ($db) {
            $customerId = null;
            if (Auth::isCustomer()) {
                $customerId = Auth::portalCustomerId();
            } elseif (Auth::isAdmin()) {
                $customerId = isset($_GET['customer']) ? (int) $_GET['customer'] : null; // Vorschau
            }
            if (!$customerId) {
                if (Auth::isAdmin()) { Router::redirect('/admin/customers'); return; }
                Response::forbidden('Kein zugeordneter Kunde');
                return;
            }
            require_once SERVICES_PATH . '/CustomerPortalService.php';
            $svc = new \Services\CustomerPortalService($db);
            $header = $svc->customerHeader($customerId);
            if (!$header) { Response::forbidden('Kunde nicht gefunden'); return; }
            Response::view('portal/chat', [
                'header'     => $header,
                'isPreview'  => !Auth::isCustomer(),
                'customerId' => $customerId, // fuer die Vorschau (Team) als ?customer-Param
            ]);
        }, [$authMiddleware]);

        // Chat
        $router->get('/chat', function () use ($db) {
            $customers = Auth::isAdmin()
                ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name")
                : [];
            Response::view('chat', ['customers' => $customers, 'deepLinkChatId' => 0]);
        }, [$authMiddleware, $capMiddleware(CAP_CHAT)]);
        // Deep-Link auf einen einzelnen Chat: /chat/{id} oeffnet die Konversation direkt
        $router->get('/chat/{id}', function ($id) use ($db) {
            $customers = Auth::isAdmin()
                ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name")
                : [];
            Response::view('chat', ['customers' => $customers, 'deepLinkChatId' => (int) $id]);
        }, [$authMiddleware, $capMiddleware(CAP_CHAT)]);

        // Canvas (KI Kompass) — alle ausser Guest
        $router->get('/canvas', function () {
            Response::view('canvas/index', []);
        }, [$authMiddleware, $managerMiddleware]);
        $router->get('/canvas/{id}', function ($id) {
            Response::view('canvas/board', ['canvasId' => $id]);
        }, [$authMiddleware, $managerMiddleware]);

        // Knowledge (RAG) — neue Version unter /wissen
        $router->get('/wissen', function () use ($db) {
            $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
            Response::view('knowledge/rag/index', ['customers' => $customers]);
        }, [$authMiddleware, $capMiddleware(CAP_KNOWLEDGE)]);
        $router->get('/wissen/neu', function () use ($db) {
            $embed = !empty($_GET['embed']);
            $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
            Response::view(
                'knowledge/rag/edit',
                ['customers' => $customers, 'documentId' => null, 'embed' => $embed]
            );
        }, [$authMiddleware, $capMiddleware(CAP_KNOWLEDGE)]);
        $router->get('/wissen/{id}', function ($id) use ($db) {
            $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
            Response::view('knowledge/rag/edit', ['customers' => $customers, 'documentId' => (int) $id]);
        }, [$authMiddleware, $capMiddleware(CAP_KNOWLEDGE)]);
        $router->get('/wissen-graph', function () {
            Response::view('knowledge/rag/graph-global', []);
        }, [$authMiddleware, $capMiddleware(CAP_KNOWLEDGE)]);
        $router->get('/wissen/{id}/graph', function ($id) {
            Response::view('knowledge/rag/graph-view', ['documentId' => (int) $id]);
        }, [$authMiddleware, $capMiddleware(CAP_KNOWLEDGE)]);

        // Guidelines: Modul wurde in /rules integriert (rules.applies_to = tool/content/both).
        // Alte URLs leiten weiter, damit Bookmarks nicht ins Leere laufen.
        $router->get('/guidelines', function () {
            header('Location: /rules?scope=global', true, 301);
            exit;
        }, [$authMiddleware]);

        // Tagesplaner — persoenlicher KI-Tagesplan auf Basis von Asana-Tasks
        $router->get('/tagesplan', function () {
            Response::view('planner/index', []);
        }, [$authMiddleware]);
        $router->get('/tagesplan/accounts', function () use ($db) {
            $userId = Auth::id();
            $accounts = $db->query(
                "SELECT a.id, a.account_label, a.asana_user_gid, a.asana_user_email, a.asana_user_name, a.color_hex,
                        a.is_default, a.is_active, a.sort_order, a.last_sync_at, a.created_at,
                        a.default_customer_id, c.name AS default_customer_name
                 FROM user_asana_accounts a
                 LEFT JOIN customers c ON c.id = a.default_customer_id
                 WHERE a.user_id = ? ORDER BY a.sort_order ASC, a.id ASC",
                [$userId]
            ) ?: [];
            foreach ($accounts as &$a) {
                $a['task_count'] = (int)$db->queryValue(
                    "SELECT COUNT(*) FROM planner_tasks WHERE user_id = ? AND asana_account_id = ?",
                    [$userId, $a['id']]
                );
            }
            unset($a);
            $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name") ?: [];
            Response::view('planner/accounts', ['accounts' => $accounts, 'customers' => $customers]);
        }, [$authMiddleware]);

        // Projekte
        $router->get('/projects', function () use ($db) {
            $isAdmin = Auth::isAdmin();
            $customers = $isAdmin ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name") : [];

            // Filter nach Kunde (nur für Admins)
            $filterCustomerId = null;
            if ($isAdmin && isset($_GET['customer_id']) && $_GET['customer_id'] !== '') {
                $filterCustomerId = (int) $_GET['customer_id'];
            } elseif (!$isAdmin) {
                $filterCustomerId = Auth::customerId();
            }

            $projects = $this->getProjects($db, $filterCustomerId);
            Response::view('modules/text/projects', [
                'projects' => $projects,
                'customers' => $customers,
                'filterCustomerId' => $filterCustomerId,
                'isAdmin' => $isAdmin
            ]);
        }, [$authMiddleware]);

        $router->get('/projects/new', function () use ($db) {
            $isAdmin = Auth::isAdmin();
            $customers = $isAdmin ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name") : [];
            $customerId = Auth::customerId();

            // Alle verfügbaren Regeln (global)
            $rules = $db->query(
                "SELECT id, name, rule_type, description FROM rules
                 WHERE is_active = 1
                 ORDER BY priority DESC, name"
            );

            // Alles verfügbare Wissen (Legacy — knowledge_entries wurde gedroppt)
            $knowledge = [];

            // Kunden-Daten mit zugewiesenen Regeln laden
            $customerData = [];
            if ($isAdmin && !empty($customers)) {
                foreach ($customers as $customer) {
                    $cid = $customer['id'];
                    // Zugewiesene Regeln
                    $assignedRules = $db->query(
                        "SELECT rule_id FROM customer_rules WHERE customer_id = ? AND is_active = 1",
                        [$cid]
                    );
                    $customerData[$cid] = [
                        'rules' => array_map('intval', array_column($assignedRules, 'rule_id')),
                        'knowledge' => []
                    ];
                }
            }

            // Standard-Kunde vorauswählen
            $defaultCustomerId = !empty($customers) ? $customers[0]['id'] : $customerId;
            $selectedRules = $customerData[$defaultCustomerId]['rules'] ?? [];
            $selectedKnowledge = $customerData[$defaultCustomerId]['knowledge'] ?? [];

            // Stile laden
            $styles = $db->query("SELECT slug, name FROM styles WHERE is_active = 1 ORDER BY sort_order, name");

            // Kategorien für Wissensdatenbank
            $knowledgeCategories = $db->query("SELECT * FROM knowledge_categories WHERE is_active = 1 ORDER BY sort_order");

            // KI-Modelle laden
            $aiModels = $db->query("SELECT model_id, display_name, provider FROM ai_models WHERE is_active = 1 ORDER BY sort_order, display_name");

            // Regel-Typen und Kategorien laden
            $ruleTypes = $db->query("SELECT id, slug, name, color, icon FROM rule_types WHERE is_active = 1 ORDER BY sort_order, name");
            $ruleCategories = $db->query("SELECT id, slug, name, color, icon FROM rule_categories WHERE is_active = 1 ORDER BY sort_order, name");

            Response::view('modules/text/editor', [
                'project' => null,
                'projectMetadata' => [],
                'customers' => $customers,
                'availableRules' => $rules,
                'availableKnowledge' => $knowledge,
                'selectedRules' => $selectedRules,
                'selectedKnowledge' => $selectedKnowledge,
                'customerData' => $customerData,
                'styles' => $styles,
                'knowledgeCategories' => $knowledgeCategories,
                'aiModels' => $aiModels,
                'ruleTypes' => $ruleTypes,
                'ruleCategories' => $ruleCategories
            ]);
        }, [$authMiddleware]);

        $router->get('/projects/{id}', function ($id) use ($db) {
            $project = $this->getProject($db, $id);
            if (!$project) {
                Response::notFound('Projekt nicht gefunden');
                return;
            }

            // Metadata parsen
            $projectMetadata = json_decode($project['metadata'] ?? '{}', true) ?: [];

            $isAdmin = Auth::isAdmin();
            $customers = $isAdmin ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name") : [];
            $customerId = $project['customer_id'] ?? Auth::customerId();

            // Alle verfügbaren Regeln
            $rules = $db->query(
                "SELECT id, name, rule_type, description FROM rules
                 WHERE is_active = 1
                 ORDER BY priority DESC, name"
            );

            // Ausgewählte Regeln für dieses Projekt
            $selectedRules = $db->query(
                "SELECT rule_id FROM project_rules WHERE project_id = ?",
                [$id]
            );
            $selectedRuleIds = array_column($selectedRules, 'rule_id');

            // Alles verfügbare Wissen (Legacy — knowledge_entries wurde gedroppt)
            $knowledge = [];
            $selectedKnowledgeIds = [];

            // Kunden-Daten mit zugewiesenen Regeln laden
            $customerData = [];
            if ($isAdmin && !empty($customers)) {
                foreach ($customers as $customer) {
                    $cid = $customer['id'];
                    $assignedRules = $db->query(
                        "SELECT rule_id FROM customer_rules WHERE customer_id = ? AND is_active = 1",
                        [$cid]
                    );
                    $customerData[$cid] = [
                        'rules' => array_map('intval', array_column($assignedRules, 'rule_id')),
                        'knowledge' => []
                    ];
                }
            }

            // Stile laden
            $styles = $db->query("SELECT slug, name FROM styles WHERE is_active = 1 ORDER BY sort_order, name");

            // Kategorien für Wissensdatenbank
            $knowledgeCategories = $db->query("SELECT * FROM knowledge_categories WHERE is_active = 1 ORDER BY sort_order");

            // KI-Modelle laden
            $aiModels = $db->query("SELECT model_id, display_name, provider FROM ai_models WHERE is_active = 1 ORDER BY sort_order, display_name");

            // Regel-Typen und Kategorien laden
            $ruleTypes = $db->query("SELECT id, slug, name, color, icon FROM rule_types WHERE is_active = 1 ORDER BY sort_order, name");
            $ruleCategories = $db->query("SELECT id, slug, name, color, icon FROM rule_categories WHERE is_active = 1 ORDER BY sort_order, name");

            Response::view('modules/text/editor', [
                'project' => $project,
                'projectMetadata' => $projectMetadata,
                'customers' => $customers,
                'availableRules' => $rules,
                'availableKnowledge' => $knowledge,
                'selectedRules' => $selectedRuleIds,
                'selectedKnowledge' => $selectedKnowledgeIds,
                'customerData' => $customerData,
                'styles' => $styles,
                'knowledgeCategories' => $knowledgeCategories,
                'aiModels' => $aiModels,
                'ruleTypes' => $ruleTypes,
                'ruleCategories' => $ruleCategories
            ]);
        }, [$authMiddleware]);

        // Regeln (Master-Detail-Layout: Sidebar mit Global + Kunden)
        $router->get('/rules', function () use ($db) {
            // Kundenliste fuer die Sidebar inkl. Tags/Status fuer die gleichen Filter
            // wie in /admin/customers (Status / Art).
            $customers = $db->query(
                "SELECT id, name, abbreviation, hex_color, is_active, settings FROM customers
                 ORDER BY name"
            ) ?: [];
            foreach ($customers as &$c) {
                $s = json_decode($c['settings'] ?? '{}', true) ?: [];
                $c['tags_array'] = $s['tags'] ?? [];
                unset($c['settings']);
            }
            unset($c);
            $types = $db->query("SELECT * FROM rule_types WHERE is_active = 1 ORDER BY sort_order, name") ?: [];
            $categories = $db->query("SELECT * FROM rule_categories WHERE is_active = 1 ORDER BY sort_order, name") ?: [];

            Response::view('rules/index', [
                'customers' => $customers,
                'types' => $types,
                'categories' => $categories,
            ]);
        }, [$authMiddleware, $managerMiddleware]);

        // ===== Kontexte =====
        $router->get('/contexts', function () use ($db) {
            $customerId = Auth::isAdmin() ? null : Auth::customerId();
            $customers = Auth::isAdmin() ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name") : [];

            $where = ['c.is_active = 1'];
            $params = [];
            if ($customerId) {
                $where[] = '(c.customer_id = ? OR c.customer_id IS NULL)';
                $params[] = $customerId;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $contexts = $db->query(
                "SELECT c.*, cu.name as customer_name, u.name as creator_name,
                        (SELECT COUNT(*) FROM context_items ci WHERE ci.context_id = c.id) as items_count,
                        (SELECT COUNT(*) FROM orders o WHERE o.context_id = c.id) as orders_count
                 FROM contexts c
                 LEFT JOIN customers cu ON c.customer_id = cu.id
                 JOIN users u ON c.created_by = u.id
                 $whereClause
                 ORDER BY c.updated_at DESC",
                $params
            );

            Response::view('contexts/index', [
                'contexts' => $contexts,
                'customers' => $customers
            ]);
        }, [$authMiddleware, $managerMiddleware]);

        $router->get('/contexts/new', function () use ($db) {
            $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");

            $knowledge = [];
            $rules = $db->query(
                "SELECT id, name, rule_type FROM rules WHERE is_active = 1 ORDER BY priority DESC, name"
            );
            $ruleCategories = $db->query(
                "SELECT rc.id, rc.name, COUNT(r.id) as rules_count
                 FROM rule_categories rc
                 LEFT JOIN rules r ON r.category_id = rc.id AND r.is_active = 1
                 WHERE rc.is_active = 1
                 GROUP BY rc.id, rc.name
                 ORDER BY rc.sort_order, rc.name"
            );
            $knowledgeCategories = [];

            Response::view('contexts/edit', [
                'context' => null,
                'customers' => $customers,
                'knowledge' => $knowledge,
                'rules' => $rules,
                'ruleCategories' => $ruleCategories,
                'knowledgeCategories' => $knowledgeCategories
            ]);
        }, [$authMiddleware, $managerMiddleware]);

        $router->get('/contexts/{id}/edit', function ($id) use ($db) {
            require_once SERVICES_PATH . '/ContextService.php';
            $contextService = new \Services\ContextService($db);

            $context = $contextService->getContextWithItems((int) $id);
            if (!$context) {
                Response::notFound('Kontext nicht gefunden');
                return;
            }

            if ($context['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($context['customer_id'])) {
                Response::forbidden();
                return;
            }

            $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
            $knowledge = [];
            $rules = $db->query(
                "SELECT id, name, rule_type FROM rules WHERE is_active = 1 ORDER BY priority DESC, name"
            );
            $ruleCategories = $db->query(
                "SELECT rc.id, rc.name, COUNT(r.id) as rules_count
                 FROM rule_categories rc
                 LEFT JOIN rules r ON r.category_id = rc.id AND r.is_active = 1
                 WHERE rc.is_active = 1
                 GROUP BY rc.id, rc.name
                 ORDER BY rc.sort_order, rc.name"
            );
            $knowledgeCategories = [];

            Response::view('contexts/edit', [
                'context' => $context,
                'customers' => $customers,
                'knowledge' => $knowledge,
                'rules' => $rules,
                'ruleCategories' => $ruleCategories,
                'knowledgeCategories' => $knowledgeCategories
            ]);
        }, [$authMiddleware, $managerMiddleware]);

        // ===== Aufträge =====
        $router->get('/orders', function () use ($db) {
            $customerId = Auth::isAdmin() ? null : Auth::customerId();
            $customers = Auth::isAdmin() ? $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name") : [];

            $where = ['1=1'];
            $params = [];
            if ($customerId) {
                $where[] = 'o.customer_id = ?';
                $params[] = $customerId;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $orders = $db->query(
                "SELECT o.*, c.name as customer_name, u.name as creator_name,
                        ctx.name as context_name,
                        (SELECT ov.word_count FROM order_versions ov WHERE ov.order_id = o.id ORDER BY ov.version_number DESC LIMIT 1) as word_count
                 FROM orders o
                 LEFT JOIN customers c ON o.customer_id = c.id
                 JOIN users u ON o.created_by = u.id
                 LEFT JOIN contexts ctx ON o.context_id = ctx.id
                 $whereClause
                 ORDER BY o.updated_at DESC",
                $params
            );

            $aiModels = $db->query("SELECT model_id, display_name, provider FROM ai_models WHERE is_active = 1 ORDER BY sort_order, display_name");

            Response::view('orders/index', [
                'orders' => $orders,
                'customers' => $customers,
                'aiModels' => $aiModels
            ]);
        }, [$authMiddleware]);

        $router->get('/orders/{id}/workspace', function ($id) use ($db) {
            $order = $db->queryOne(
                "SELECT o.*, c.name as customer_name, u.name as creator_name,
                        ctx.name as context_name
                 FROM orders o
                 LEFT JOIN customers c ON o.customer_id = c.id
                 JOIN users u ON o.created_by = u.id
                 LEFT JOIN contexts ctx ON o.context_id = ctx.id
                 WHERE o.id = ?",
                [$id]
            );

            if (!$order) {
                Response::notFound('Auftrag nicht gefunden');
                return;
            }

            if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
                Response::forbidden();
                return;
            }

            // Chat-Nachrichten laden
            $order['chat_messages'] = $db->query(
                "SELECT * FROM order_chat_messages WHERE order_id = ? ORDER BY created_at ASC",
                [$id]
            );

            // Artefakte laden für den Workspace
            require_once SERVICES_PATH . '/EntityService.php';
            require_once SERVICES_PATH . '/ArtifactService.php';
            $entityService = new \Services\EntityService($db);
            $artifactService = new \Services\ArtifactService($db, $entityService);
            $artifacts = $artifactService->findByScope($order['customer_id']);
            foreach ($artifacts as &$a) {
                $a['meta'] = json_decode($a['meta'], true) ?? [];
                $a['resolved_content'] = $entityService->resolve($a['content'] ?? '');
            }
            unset($a);

            Response::view('orders/workspace', [
                'order' => $order,
                'artifacts' => $artifacts
            ]);
        }, [$authMiddleware]);

        // ===== Admin-Routen =====

        // Kunden
        $router->get('/admin/customers', function () use ($db) {
            // Admin sieht alle Kunden, andere nur die ihm zugewiesenen
            $isAdmin = Auth::isAdmin();
            $myCustomerIds = $isAdmin ? null : array_map(fn($c) => (int)$c['id'], Auth::customers());
            $whereClause = '';
            $params = [];
            if (!$isAdmin) {
                if (empty($myCustomerIds)) {
                    // User ohne zugewiesene Kunden → leere Liste
                    Response::view('admin/customers', ['customers' => []]);
                    return;
                }
                $placeholders = implode(',', array_fill(0, count($myCustomerIds), '?'));
                $whereClause = "WHERE c.id IN ($placeholders)";
                $params = $myCustomerIds;
            }
            $customers = $db->query("
                SELECT c.*,
                    (SELECT COUNT(*) FROM chat_conversations cc WHERE cc.customer_id = c.id AND cc.deleted_at IS NULL) AS chat_count,
                    (SELECT COUNT(*) FROM knowledge_documents kd WHERE kd.customer_id = c.id) AS knowledge_count,
                    (SELECT COUNT(*) FROM knowledge_documents kd WHERE kd.customer_id = c.id AND kd.source_type = 'website') AS website_pages,
                    (SELECT COUNT(*) FROM knowledge_documents kd WHERE kd.customer_id = c.id AND kd.source_type = 'asana') AS asana_docs,
                    (SELECT COUNT(*) FROM customer_cards cards WHERE cards.customer_id = c.id AND cards.is_system = 0) AS user_cards_count,
                    (SELECT MAX(cc.updated_at) FROM chat_conversations cc WHERE cc.customer_id = c.id AND cc.deleted_at IS NULL) AS last_chat_at
                FROM customers c
                $whereClause
                ORDER BY (c.abbreviation IS NULL OR c.abbreviation = ''), c.abbreviation, c.name
            ", $params);
            // settings JSON parsen für Asana / Website-Sync Status / Tags / Domains
            foreach ($customers as &$c) {
                $s = json_decode($c['settings'] ?? '{}', true) ?: [];
                $c['has_asana'] = !empty($s['asana']['project_gids']);
                $c['asana_last_sync'] = $s['asana']['last_sync_at'] ?? null;
                $c['asana_sync_enabled'] = !empty($s['asana']['sync_enabled']);
                $c['has_website'] = !empty($s['website_crawl']['start_url']) || !empty($c['website']);
                $c['website_last_sync'] = $s['website_crawl']['last_sync_at'] ?? null;
                $c['website_sync_enabled'] = !empty($s['website_crawl']['sync_enabled']);
                $c['tags'] = $s['tags'] ?? [];
                $c['additional_domains'] = $s['domains'] ?? [];
                unset($c['settings']);
            }
            unset($c);
            Response::view('admin/customers', ['customers' => $customers]);
        }, [$authMiddleware, $capMiddleware(CAP_CUSTOMERS_VIEW)]);

        // Kunden-Wizard (Step-by-Step Anlage)
        $router->get('/admin/customers/wizard', function () {
            Response::view('admin/customer-wizard', []);
        }, [$authMiddleware, $adminMiddleware]);

        // Kunden-Steckbrief (Detail-Seite)
        $router->get('/admin/customers/{id}/steckbrief', function ($id) use ($db) {
            // Non-Admin: muss diesem Kunden zugewiesen sein
            if (!Auth::isAdmin() && !Auth::canAccessCustomer((int)$id)) {
                Response::forbidden('Kein Zugriff auf diesen Kunden.');
                return;
            }
            $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$id]);
            if (!$customer) {
                Response::notFound('Kunde nicht gefunden');
                return;
            }
            // Zusätzliche Domains + Tags aus settings auspacken
            $s = json_decode($customer['settings'] ?? '{}', true) ?: [];
            $customer['domains'] = $s['domains'] ?? [];
            $customer['tags'] = $s['tags'] ?? [];
            // Verknüpfte CRM-Firma (falls vorhanden) nachladen
            $crmFirmaLinked = null;
            if (!empty($customer['crm_firma_id'])) {
                $crmFirmaLinked = $db->queryOne(
                    "SELECT id, firmenname, branche, website FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL",
                    [(int) $customer['crm_firma_id']]
                );
            }
            $embed = !empty($_GET['embed']) || !empty($_GET['fragment']);
            $fragment = !empty($_GET['fragment']);
            Response::view(
                'admin/customer-steckbrief',
                ['customer' => $customer, 'crmFirmaLinked' => $crmFirmaLinked, 'embed' => $embed, 'fragment' => $fragment],
                $fragment ? null : 'main'
            );
        }, [$authMiddleware, $capMiddleware(CAP_CUSTOMERS_VIEW)]);

        // ===== Kundenportal-Verwaltung (Team steuert, was der Kunde sieht) =====
        $router->get('/admin/customers/{id}/portal', function ($id) use ($db) {
            $cid = (int) $id;
            if (!Auth::isAdmin() && !Auth::canAccessCustomer($cid)) {
                Response::forbidden('Kein Zugriff auf diesen Kunden.');
                return;
            }
            $customer = $db->queryOne("SELECT id, name, abbreviation FROM customers WHERE id = ?", [$cid]);
            if (!$customer) { Response::notFound('Kunde nicht gefunden'); return; }
            require_once SERVICES_PATH . '/CustomerPortalService.php';
            $svc = new \Services\CustomerPortalService($db);
            // Module-Status
            $enabled = $svc->enabledModules($cid);
            // Kuratierbare Karten (nur sichere Typen) inkl. aktuellem Sichtbar-Flag
            $cards = $db->query(
                "SELECT id, type, title, customer_visible FROM customer_cards
                 WHERE customer_id = ? AND is_system = 0 AND type IN ('richtext','kpi','brand')
                 ORDER BY title", [$cid]
            ) ?: [];
            // Customer-User dieses Kunden
            $portalUsers = $db->query(
                "SELECT id, name, email, is_active, last_login FROM users WHERE role = 'customer' AND customer_id = ? ORDER BY name", [$cid]
            ) ?: [];
            Response::view('admin/customer-portal-settings', [
                'customer'    => $customer,
                'allModules'  => \Services\CustomerPortalService::MODULES,
                'enabled'     => $enabled,
                'cards'       => $cards,
                'portalUsers' => $portalUsers,
                'kiActive'    => $svc->kiActive($cid),
            ], 'main');
        }, [$authMiddleware, $capMiddleware(CAP_CUSTOMERS_VIEW)]);

        // Neuer Kunde (Legacy-Form, fuer Bearbeiten weiter genutzt)
        // Legacy-Anlegen-Form abgeschafft — leitet auf den 5-Step-Wizard um.
        $router->get('/admin/customers/new', function () {
            \Core\Router::redirect('/admin/customers/wizard');
        }, [$authMiddleware, $adminMiddleware]);

        $router->get('/admin/customers/new-legacy', function () use ($db) {
            // Regeln mit Typ- und Kategorie-Infos laden (gruppiert nach Kategorie)
            $allRules = $db->query(
                "SELECT r.id, r.name, r.description, r.rule_type, r.priority,
                        rt.name as type_name, rt.slug as type_slug, rt.color as type_color, rt.icon as type_icon,
                        rc.id as category_id, rc.name as category_name, rc.slug as category_slug,
                        rc.color as category_color, rc.icon as category_icon
                 FROM rules r
                 LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
                 LEFT JOIN rule_categories rc ON r.category_id = rc.id
                 WHERE r.customer_id IS NULL AND r.is_active = 1
                 ORDER BY rc.sort_order, rc.name, r.priority DESC, r.name"
            );

            // Regeln nach Kategorien gruppieren
            $rulesGrouped = [];
            $uncategorized = [];
            foreach ($allRules as $rule) {
                if ($rule['category_id']) {
                    $catId = $rule['category_id'];
                    if (!isset($rulesGrouped[$catId])) {
                        $rulesGrouped[$catId] = [
                            'id' => $catId,
                            'name' => $rule['category_name'],
                            'slug' => $rule['category_slug'],
                            'color' => $rule['category_color'],
                            'icon' => $rule['category_icon'],
                            'rules' => []
                        ];
                    }
                    $rulesGrouped[$catId]['rules'][] = $rule;
                } else {
                    $uncategorized[] = $rule;
                }
            }
            if (!empty($uncategorized)) {
                $rulesGrouped['uncategorized'] = [
                    'id' => null,
                    'name' => 'Ohne Kategorie',
                    'slug' => 'uncategorized',
                    'color' => '#9ca3af',
                    'icon' => 'help',
                    'rules' => $uncategorized
                ];
            }

            Response::view('admin/customer-edit', [
                'customer' => null,
                'allRules' => $allRules,
                'rulesGrouped' => array_values($rulesGrouped),
                'allKnowledge' => [],
                'customerRuleIds' => [],
                'customerKnowledgeIds' => [],
                'crmFirmaLinked' => null,
            ]);
        }, [$authMiddleware, $adminMiddleware]);

        // Kunde bearbeiten
        // Legacy Edit-Form abgeschafft — alle Felder liegen im Steckbrief als Karten.
        $router->get('/admin/customers/{id}/edit', function ($id) {
            \Core\Router::redirect('/admin/customers/' . (int)$id . '/steckbrief');
        }, [$authMiddleware, $capMiddleware(CAP_CUSTOMERS_VIEW)]);

        $router->get('/admin/customers/{id}/edit-legacy', function ($id) use ($db) {
            $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$id]);
            if (!$customer) {
                Response::notFound('Kunde nicht gefunden');
                return;
            }

            // Verknüpfte CRM-Firma (falls vorhanden) zur Anzeige im Edit-Feld nachladen
            $crmFirmaLinked = null;
            if (!empty($customer['crm_firma_id'])) {
                $crmFirmaLinked = $db->queryOne(
                    "SELECT id, firmenname, branche, website FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL",
                    [(int) $customer['crm_firma_id']]
                );
            }

            // Regeln mit Typ- und Kategorie-Infos laden (gruppiert nach Kategorie)
            $allRules = $db->query(
                "SELECT r.id, r.name, r.description, r.rule_type, r.priority,
                        rt.name as type_name, rt.slug as type_slug, rt.color as type_color, rt.icon as type_icon,
                        rc.id as category_id, rc.name as category_name, rc.slug as category_slug,
                        rc.color as category_color, rc.icon as category_icon
                 FROM rules r
                 LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
                 LEFT JOIN rule_categories rc ON r.category_id = rc.id
                 WHERE r.customer_id IS NULL AND r.is_active = 1
                 ORDER BY rc.sort_order, rc.name, r.priority DESC, r.name"
            );

            // Regeln nach Kategorien gruppieren
            $rulesGrouped = [];
            $uncategorized = [];
            foreach ($allRules as $rule) {
                if ($rule['category_id']) {
                    $catId = $rule['category_id'];
                    if (!isset($rulesGrouped[$catId])) {
                        $rulesGrouped[$catId] = [
                            'id' => $catId,
                            'name' => $rule['category_name'],
                            'slug' => $rule['category_slug'],
                            'color' => $rule['category_color'],
                            'icon' => $rule['category_icon'],
                            'rules' => []
                        ];
                    }
                    $rulesGrouped[$catId]['rules'][] = $rule;
                } else {
                    $uncategorized[] = $rule;
                }
            }
            if (!empty($uncategorized)) {
                $rulesGrouped['uncategorized'] = [
                    'id' => null,
                    'name' => 'Ohne Kategorie',
                    'slug' => 'uncategorized',
                    'color' => '#9ca3af',
                    'icon' => 'help',
                    'rules' => $uncategorized
                ];
            }

            // Wissen dieses Kunden (aus neuer RAG-Struktur)
            $customerKnowledge = $db->query(
                "SELECT d.id, d.title, d.description, d.source_type, d.source_ref, d.category, d.tags,
                        d.created_at, d.is_active,
                        (SELECT COUNT(*) FROM knowledge_chunks c WHERE c.document_id = d.id) as chunk_count,
                        (SELECT COUNT(*) FROM knowledge_usage u JOIN knowledge_chunks c ON u.chunk_id = c.id WHERE c.document_id = d.id) as usage_count
                 FROM knowledge_documents d
                 WHERE d.customer_id = ?
                 ORDER BY d.updated_at DESC",
                [$id]
            );

            // Zugewiesene Regeln
            $customerRules = $db->query("SELECT rule_id FROM customer_rules WHERE customer_id = ? AND is_active = 1", [$id]);
            $customerRuleIds = array_column($customerRules, 'rule_id');

            Response::view('admin/customer-edit', [
                'customer' => $customer,
                'allRules' => $allRules,
                'rulesGrouped' => array_values($rulesGrouped),
                'customerKnowledge' => $customerKnowledge,
                'allKnowledge' => [],
                'customerRuleIds' => $customerRuleIds,
                'customerKnowledgeIds' => [],
                'crmFirmaLinked' => $crmFirmaLinked,
            ]);
        }, [$authMiddleware, $adminMiddleware]);

        // Benutzer
        $router->get('/admin/users', function () use ($db) {
            $users = $db->query(
                "SELECT u.id, u.email, u.name, u.abbreviation, u.role, u.is_active, u.last_login, u.last_activity, u.created_at,
                        u.asana_user_gid, u.asana_user_email, u.asana_user_name,
                        u.two_factor_enabled, u.invite_token, u.invite_expires_at,
                        (SELECT COUNT(*) FROM chat_conversations cc WHERE cc.user_id = u.id AND cc.deleted_at IS NULL) AS chat_count,
                        (SELECT MAX(cc.updated_at) FROM chat_conversations cc WHERE cc.user_id = u.id AND cc.deleted_at IS NULL) AS last_chat_at,
                        (SELECT COUNT(*) FROM knowledge_documents kd WHERE kd.created_by = u.id) AS knowledge_count,
                        (SELECT COUNT(*) FROM lam_audit_logs la WHERE la.user_id = u.id) AS lam_audit_count,
                        (SELECT COUNT(*) FROM lam_kommunikation lk WHERE lk.user_id = u.id) AS lam_korr_count,
                        (SELECT COUNT(*) FROM internal_feedback i WHERE i.user_id = u.id) AS feedback_count,
                        (SELECT COALESCE(MAX(t.is_active), 0) FROM pp_team_members t WHERE t.user_id = u.id) AS pp_team_active
                 FROM users u
                 ORDER BY u.name"
            );
            // Kunden + Capabilities pro Benutzer laden
            foreach ($users as &$user) {
                $customerData = $db->query(
                    "SELECT c.id, c.name FROM customers c
                     JOIN user_customers uc ON c.id = uc.customer_id
                     WHERE uc.user_id = ?
                     ORDER BY uc.is_default DESC, c.name",
                    [$user['id']]
                );
                $user['customer_ids'] = array_column($customerData, 'id');
                $user['customer_names'] = array_column($customerData, 'name');
                $user['customer_count'] = count($customerData);
                $user['capabilities'] = \Core\Auth::capabilitiesOf((int)$user['id']);
                // Pending-Invite: hat noch nie eingeloggt + invite-Token aktiv
                $user['invite_pending'] = !empty($user['invite_token']) && empty($user['last_login']);
                // Letzte 5 Chats fuer Expand-Sektion
                $user['recent_chats'] = $db->query(
                    "SELECT id, title, updated_at FROM chat_conversations
                     WHERE user_id = ? AND deleted_at IS NULL
                     ORDER BY updated_at DESC LIMIT 5",
                    [(int)$user['id']]
                );
            }
            unset($user);
            $customers = $db->query("SELECT id, name, slug, settings FROM customers WHERE is_active = 1 ORDER BY name");
            // Tags aus settings-JSON ziehen — fuer Segment-Filter im Kunden-Tab
            foreach ($customers as &$c) {
                $s = json_decode($c['settings'] ?? '{}', true) ?: [];
                $c['tags'] = $s['tags'] ?? [];
                unset($c['settings']);
            }
            unset($c);
            // Rollen-Defaults + User-Counts fuer den „Rollen & Caps"-Tab
            $roleDefaults = \Core\Auth::allRoleDefaults();
            $userCounts = [];
            foreach (['admin','manager','user','guest'] as $r) {
                $userCounts[$r] = (int)$db->queryValue("SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1", [$r]);
            }
            // Kunden-Zuordnung: rollenbasiert + direkt pro User — fuer den „Kunden"-Tab
            $roleCustomers = \Core\Auth::allRoleCustomers();
            $userCustomers = \Core\Auth::allDirectUserCustomers();
            // Audit-Log nur laden, wenn Tab aktiv (spart Query bei jedem Page-Load)
            $auditEntries = [];
            $auditTotal = 0;
            if (($_GET['tab'] ?? '') === 'audit') {
                $auditFilter = [
                    'action' => $_GET['action'] ?? null,
                    'target_type' => $_GET['target_type'] ?? null,
                    'target_key' => $_GET['target_key'] ?? null,
                    'limit' => 100,
                ];
                $auditEntries = \Core\AuditLog::list($auditFilter);
                $auditTotal   = \Core\AuditLog::count($auditFilter);
            }
            Response::view('admin/users', [
                'users' => $users,
                'customers' => $customers,
                'roleDefaults' => $roleDefaults,
                'userCounts' => $userCounts,
                'roleCustomers' => $roleCustomers,
                'userCustomers' => $userCustomers,
                'auditEntries' => $auditEntries,
                'auditTotal' => $auditTotal,
            ]);
        }, [$authMiddleware, $adminMiddleware]);

        // Legacy-Redirect: /admin/roles → /admin/users?tab=rollen
        $router->get('/admin/roles', function () {
            header('Location: /admin/users?tab=rollen', true, 301);
            exit;
        }, [$authMiddleware, $adminMiddleware]);

        // Benutzer-Detailseite (Edit) — eigene URL statt Modal, fuer mehr Uebersicht
        $router->get('/admin/users/{id}/edit', function ($id) use ($db) {
            $user = $db->queryOne(
                "SELECT id, email, name, abbreviation, nickname, role, is_active, last_login, created_at,
                        asana_user_gid, asana_user_email, asana_user_name
                 FROM users WHERE id = ?",
                [(int)$id]
            );
            if (!$user) {
                http_response_code(404);
                echo '<h1>Benutzer nicht gefunden</h1><p><a href="/admin/users">Zurueck zur Uebersicht</a></p>';
                return;
            }
            $customers = $db->query("SELECT id, name, slug, is_active FROM customers ORDER BY is_active DESC, name");
            $assignedCustomerIds = array_map('intval', array_column(
                $db->query("SELECT customer_id FROM user_customers WHERE user_id = ?", [(int)$user['id']]),
                'customer_id'
            ));
            $capabilities = \Core\Auth::capabilitiesOf((int)$user['id']);
            $roleDefaults = \Core\Auth::allRoleDefaults();
            // Projektplanner-Felder (Kapazität, Farbe, im Team aktiv) aus pp_team_members
            $ppTeam = $db->queryOne(
                "SELECT capacity_hours, hex_color, is_active FROM pp_team_members WHERE user_id = ?",
                [(int)$user['id']]
            ) ?: ['capacity_hours' => 160, 'hex_color' => null, 'is_active' => 1];
            Response::view('admin/user-edit', [
                'user' => $user,
                'customers' => $customers,
                'assignedCustomerIds' => $assignedCustomerIds,
                'capabilities' => $capabilities,
                'roleDefaults' => $roleDefaults,
                'ppTeam' => $ppTeam,
            ]);
        }, [$authMiddleware, $adminMiddleware]);

        // Einstellungen
        $router->get('/admin/settings', function () use ($db) {
            $rows = $db->query("SELECT * FROM settings ORDER BY setting_key");
            // Als assoziatives Array nach setting_key
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row;
            }
            // KI-Modelle laden
            $aiModels = $db->query("SELECT model_id, display_name, provider FROM ai_models WHERE is_active = 1 ORDER BY sort_order, display_name");
            Response::view('admin/settings', ['settings' => $settings, 'aiModels' => $aiModels]);
        }, [$authMiddleware, $adminMiddleware]);

        // Modul-Verwaltung (installationsweit an/aus, innerhalb Lizenz)
        $router->get('/admin/modules', function () {
            Response::view('admin/module-verwaltung', [
                'module'    => \Core\Modules::withState(),
                'selfcheck' => \Core\Modules::selfCheck(),
            ]);
        }, [$authMiddleware, $capMiddleware(CAP_SETTINGS_MANAGE)]);

        // Migration Route (temporaer)
        $router->get('/admin/migrate', function () use ($db) {
            $results = [];
            try {
                // user_customers Tabelle erstellen
                $db->execute("
                    CREATE TABLE IF NOT EXISTS user_customers (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        customer_id INT NOT NULL,
                        is_default BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_user_customer (user_id, customer_id),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $results[] = "Tabelle user_customers erstellt";

                // Bestehende Daten migrieren
                $migrated = $db->execute("
                    INSERT IGNORE INTO user_customers (user_id, customer_id, is_default)
                    SELECT id, customer_id, 1
                    FROM users
                    WHERE customer_id IS NOT NULL
                ");
                $results[] = "$migrated bestehende Zuweisungen migriert";

                $count = $db->queryValue("SELECT COUNT(*) FROM user_customers");
                $results[] = "Tabelle enthält $count Einträge";

                // Kunden-Profilfelder hinzufügen
                $columns = [
                    'description' => 'TEXT NULL',
                    'industry' => 'VARCHAR(255) NULL',
                    'target_audience' => 'TEXT NULL',
                    'tone_of_voice' => 'VARCHAR(255) NULL',
                    'products_services' => 'TEXT NULL',
                    'unique_selling_points' => 'TEXT NULL',
                    'brand_values' => 'TEXT NULL',
                    'website' => 'VARCHAR(255) NULL',
                    // Tagesplaner: Kunde manuell als 'brennend' markiert (Filter 'Brennende Kunden').
                    'is_hot' => 'TINYINT(1) NOT NULL DEFAULT 0'
                ];

                foreach ($columns as $column => $definition) {
                    try {
                        $db->execute("ALTER TABLE customers ADD COLUMN $column $definition");
                        $results[] = "Spalte customers.$column hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "Spalte $column: " . $e->getMessage();
                        }
                    }
                }

                // model_used zu article_sections hinzufügen
                try {
                    $db->execute("ALTER TABLE article_sections ADD COLUMN model_used VARCHAR(50) NULL");
                    $results[] = "Spalte article_sections.model_used hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "article_sections.model_used: " . $e->getMessage();
                    }
                }

                // ai_models Tabelle erstellen
                $db->execute("
                    CREATE TABLE IF NOT EXISTS ai_models (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        model_id VARCHAR(100) NOT NULL UNIQUE,
                        display_name VARCHAR(100) NOT NULL,
                        provider ENUM('openai', 'anthropic', 'google', 'local') NOT NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        is_default BOOLEAN DEFAULT FALSE,
                        cost_input DECIMAL(10,6) DEFAULT 0,
                        cost_output DECIMAL(10,6) DEFAULT 0,
                        max_tokens INT DEFAULT 4096,
                        sort_order INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $results[] = "Tabelle ai_models erstellt";

                // Standard-Modelle einfügen
                $defaultModels = [
                    ['gpt-5', 'GPT-5', 'openai', 0.005, 0.015, 128000, 1],
                    ['gpt-5-mini', 'GPT-5 Mini', 'openai', 0.0003, 0.0012, 128000, 2],
                    ['gpt-5-nano', 'GPT-5 Nano', 'openai', 0.0001, 0.0004, 128000, 3],
                    ['gpt-4', 'GPT-4', 'openai', 0.03, 0.06, 8192, 4],
                    ['gpt-4-turbo', 'GPT-4 Turbo', 'openai', 0.01, 0.03, 128000, 5],
                    ['gpt-3.5-turbo', 'GPT-3.5 Turbo', 'openai', 0.0005, 0.0015, 16385, 6],
                    ['claude-3-opus-20240229', 'Claude 3 Opus', 'anthropic', 0.015, 0.075, 200000, 7],
                    ['claude-3-sonnet-20240229', 'Claude 3 Sonnet', 'anthropic', 0.003, 0.015, 200000, 8],
                    ['claude-3-haiku-20240307', 'Claude 3 Haiku', 'anthropic', 0.00025, 0.00125, 200000, 9],
                    ['qwen2.5:14b', 'Lokal: Qwen 2.5 14B', 'local', 0, 0, 4096, 20],
                    ['gpt-oss:20b', 'Lokal: GPT-OSS 20B', 'local', 0, 0, 4096, 21],
                    ['llama3.1:8b', 'Lokal: Llama 3.1 8B', 'local', 0, 0, 4096, 22]
                ];

                foreach ($defaultModels as $m) {
                    try {
                        $db->execute(
                            "INSERT IGNORE INTO ai_models (model_id, display_name, provider, cost_input, cost_output, max_tokens, sort_order, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                            [$m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]]
                        );
                    } catch (\Exception $e) {
                        // Ignorieren wenn schon existiert
                    }
                }
                $results[] = "Standard-Modelle eingefügt";

                // Daily Motivations Tabelle
                $db->execute("
                    CREATE TABLE IF NOT EXISTS daily_motivations (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        date DATE NOT NULL,
                        motivation TEXT NOT NULL,
                        model_used VARCHAR(100),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_user_date (user_id, date),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");
                $results[] = "daily_motivations Tabelle erstellt";

                // Feedback-Maßnahmen (To-dos aus internem Feedback)
                $db->execute("
                    CREATE TABLE IF NOT EXISTS feedback_measures (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        description TEXT NULL,
                        area VARCHAR(100) NULL,
                        status ENUM('offen','in_arbeit','erledigt','verworfen') NOT NULL DEFAULT 'offen',
                        priority ENUM('hoch','mittel','niedrig') NOT NULL DEFAULT 'mittel',
                        source ENUM('ki','manuell') NOT NULL DEFAULT 'manuell',
                        created_by INT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_status (status),
                        INDEX idx_priority (priority)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $db->execute("
                    CREATE TABLE IF NOT EXISTS feedback_measure_links (
                        measure_id INT NOT NULL,
                        feedback_id INT NOT NULL,
                        PRIMARY KEY (measure_id, feedback_id),
                        INDEX idx_feedback (feedback_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $results[] = "feedback_measures Tabellen erstellt";

                // Firewall: Warteschlange fuer Entsperr-Auftraege (Web schreibt, Cron-Worker arbeitet ab)
                $db->execute("
                    CREATE TABLE IF NOT EXISTS firewall_unban_queue (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        ip VARCHAR(64) NOT NULL,
                        status ENUM('pending','done','error') NOT NULL DEFAULT 'pending',
                        requested_by INT NULL,
                        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        processed_at DATETIME NULL,
                        result VARCHAR(255) NULL,
                        INDEX idx_status (status),
                        INDEX idx_ip (ip)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $results[] = "firewall_unban_queue Tabelle erstellt";

                // Feedback: mehrere Anhaenge je Feedback (Screenshot + Video nebeneinander)
                $db->execute("
                    CREATE TABLE IF NOT EXISTS feedback_media (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        feedback_id INT NOT NULL,
                        media_type ENUM('screenshot','video') NOT NULL,
                        media_path VARCHAR(500) NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_feedback (feedback_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $results[] = "feedback_media Tabelle erstellt";

                // internal_feedback: KI-Vorschlag + naechste Schritte (Feedback-Cockpit)
                foreach (['title' => 'VARCHAR(255) NULL', 'next_steps' => 'TEXT NULL', 'ai_suggestion' => 'TEXT NULL'] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE internal_feedback ADD COLUMN $col $def");
                        $results[] = "internal_feedback.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "internal_feedback.$col: " . $e->getMessage();
                        }
                    }
                }

                // knowledge_documents: private Sichtbarkeit (Vorbedingung fuer Mail-Wissen).
                // Bisher war `customer_id` die einzige Zugriffsgrenze — es gab keinen Begriff
                // von "gehoert nur diesem Nutzer". visibility='privat' liefert Treffer NUR an
                // owner_user_id aus, bewusst OHNE Admin-Ausnahme.
                // Default 'kunde' => Bestandsdokumente verhalten sich unveraendert.
                foreach ([
                    'owner_user_id' => 'INT NULL',
                    'visibility'    => "ENUM('privat','team','kunde') NOT NULL DEFAULT 'kunde'",
                ] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE knowledge_documents ADD COLUMN $col $def");
                        $results[] = "knowledge_documents.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "knowledge_documents.$col: " . $e->getMessage();
                        }
                    }
                }
                try {
                    $db->execute("ALTER TABLE knowledge_documents ADD INDEX idx_visibility (visibility, owner_user_id)");
                    $results[] = "knowledge_documents idx_visibility hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate key') === false) {
                        $results[] = "knowledge_documents idx_visibility: " . $e->getMessage();
                    }
                }

                // Mail: Microsoft-365-Anbindung (OAuth2) + Nur-Lesen-Modus.
                // Microsoft hat die Passwort-Anmeldung fuer IMAP/SMTP abgeschaltet; Exchange
                // Online geht nur noch ueber OAuth2 (XOAUTH2). nur_lesen schuetzt Postfaecher,
                // in denen der Nutzer parallel mit Outlook arbeitet (kein Verschieben!).
                foreach ([
                    'auth_typ'                => "ENUM('passwort','oauth2') NOT NULL DEFAULT 'passwort'",
                    'nur_lesen'               => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'ist_standard'            => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'oauth_tenant_id'         => 'VARCHAR(120) NULL',
                    'oauth_client_id'         => 'VARCHAR(120) NULL',
                    'oauth_client_secret_enc' => 'TEXT NULL',
                    'oauth_refresh_token_enc' => 'TEXT NULL',
                    'oauth_access_token_enc'  => 'TEXT NULL',
                    'oauth_token_expires'     => 'DATETIME NULL',
                ] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE mail_konten ADD COLUMN $col $def");
                        $results[] = "mail_konten.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false
                            && strpos($e->getMessage(), "doesn't exist") === false) {
                            $results[] = "mail_konten.$col: " . $e->getMessage();
                        }
                    }
                }

                // Ordner-Auswahl je Konto: ZWEI getrennte Schalter.
                // abholen   = Ordner erscheint in /mail (lesen + beantworten)
                // ins_wissen = Inhalt wandert zusaetzlich in die Wissensdatenbank
                // Ohne die Trennung muesste man sich je Ordner zwischen "gar nicht sehen" und
                // "fuer die KI durchsuchbar" entscheiden (z.B. Ordner "Personal": ja/nein).
                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS mail_konten_ordner (
                            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            konto_id INT(10) UNSIGNED NOT NULL,
                            ordner_pfad VARCHAR(255) NOT NULL,
                            abholen TINYINT(1) NOT NULL DEFAULT 0,
                            ins_wissen TINYINT(1) NOT NULL DEFAULT 0,
                            rekursiv TINYINT(1) NOT NULL DEFAULT 0,
                            letzter_pull DATETIME NULL,
                            UNIQUE KEY uniq_konto_ordner (konto_id, ordner_pfad),
                            INDEX (konto_id),
                            CONSTRAINT mail_ko_konto_fk FOREIGN KEY (konto_id)
                                REFERENCES mail_konten(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "mail_konten_ordner Tabelle erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "mail_konten_ordner: " . $e->getMessage();
                    }
                }

                // Herkunfts-Ordner je Nachricht (im Nur-Lesen-Modus wird nichts verschoben,
                // also brauchen wir die Zuordnung in unserer DB).
                foreach (['imap_ordner' => 'VARCHAR(255) NULL', 'imap_uid' => 'INT(11) NULL'] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE mail_nachrichten ADD COLUMN $col $def");
                        $results[] = "mail_nachrichten.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false
                            && strpos($e->getMessage(), "doesn't exist") === false) {
                            $results[] = "mail_nachrichten.$col: " . $e->getMessage();
                        }
                    }
                }

                // App Logo Setting hinzufügen
                try {
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('app_logo', '', 'string', 'Logo (SVG oder Base64-encoded Image)')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key"
                    );
                    $results[] = "app_logo Setting hinzugefügt";
                } catch (\Exception $e) {
                    $results[] = "app_logo: " . $e->getMessage();
                }

                // Optimize Model Setting hinzufügen
                try {
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('optimize_model', 'gpt-4', 'string', 'KI-Modell für Thema-Optimierung')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key"
                    );
                    $results[] = "optimize_model Setting hinzugefügt";
                } catch (\Exception $e) {
                    $results[] = "optimize_model: " . $e->getMessage();
                }

                // Kategorie-Icons auf Material Symbols umstellen
                try {
                    $iconUpdates = [
                        ['📝', 'article'],
                        ['📦', 'inventory_2'],
                        ['🏢', 'business'],
                        ['📖', 'auto_stories'],
                        ['🎨', 'palette'],
                        ['👥', 'group'],
                        ['🔍', 'search'],
                        ['⚔️', 'swords'],
                        ['📚', 'library_books'],
                        ['📁', 'folder']
                    ];
                    foreach ($iconUpdates as $update) {
                        $db->execute(
                            "UPDATE knowledge_categories SET icon = ? WHERE icon = ?",
                            [$update[1], $update[0]]
                        );
                    }
                    $results[] = "Kategorie-Icons aktualisiert";
                } catch (\Exception $e) {
                    $results[] = "Kategorie-Icons: " . $e->getMessage();
                }

                // Kunden-Kürzel hinzufügen
                try {
                    $db->execute("ALTER TABLE customers ADD COLUMN abbreviation VARCHAR(10) NULL");
                    $results[] = "Spalte customers.abbreviation hinzugefügt";
                    try {
                        $db->execute("ALTER TABLE customers ADD COLUMN logo_path VARCHAR(500) NULL AFTER abbreviation");
                        $results[] = "Spalte customers.logo_path hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "customers.logo_path: " . $e->getMessage();
                    }

                    // ====== Projektplanner: Customer-Erweiterungen ======
                    foreach ([
                        'hex_color'        => "VARCHAR(7) NULL AFTER logo_path",
                        'stundensatz'      => "DECIMAL(10,2) NULL AFTER hex_color",
                        'uebertrag_ts'     => "DECIMAL(10,2) DEFAULT 0 AFTER stundensatz",
                        'uebertrag_notiz'  => "VARCHAR(500) NULL AFTER uebertrag_ts",
                        'abrechnungsmodus' => "ENUM('monthly','bimonthly','quarterly','halfyear','yearly') NOT NULL DEFAULT 'quarterly' AFTER uebertrag_notiz",
                        // Abrechnungs-Profil (intelligentes Budget): Modell + Konfig
                        'billing_model'    => "ENUM('fix_monatlich','fix_bimonatlich','fix_quartalsweise','zuruf_quartal','zuruf_monat','einzelprojekt') NULL AFTER abrechnungsmodus",
                        'ts_per_month'     => "DECIMAL(5,2) NULL AFTER billing_model",
                        'hours_per_ts'     => "DECIMAL(4,2) NOT NULL DEFAULT 8.00 AFTER ts_per_month",
                        'hours_per_ts_max' => "DECIMAL(4,2) NOT NULL DEFAULT 10.00 AFTER hours_per_ts",
                        'billing_notes'    => "TEXT NULL AFTER hours_per_ts_max",
                        // Hinweis: main_contact_* wurden temporär hinzugefuegt, dann verworfen
                        // — der Ansprechpartner kommt aus customer_cards (type='contacts').
                    ] as $col => $def) {
                        try {
                            $db->execute("ALTER TABLE customers ADD COLUMN $col $def");
                            $results[] = "Spalte customers.$col hinzugefügt";
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "customers.$col: " . $e->getMessage();
                        }
                    }
                    // pp_plans: Angebots-Soll fuer Einzelprojekte
                    foreach ([
                        'offer_ts' => "DECIMAL(10,2) NULL AFTER plan_status",
                    ] as $col => $def) {
                        try {
                            $db->execute("ALTER TABLE pp_plans ADD COLUMN $col $def");
                            $results[] = "Spalte pp_plans.$col hinzugefügt";
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "pp_plans.$col: " . $e->getMessage();
                        }
                    }
                    // pp_plan_rows: review_flag — markiert Zeilen, an denen der User im
                    // 600ms-Debounce-Bug-Zeitraum gearbeitet hat und die er prüfen sollte.
                    // Klick im UI auf "Passt" entfernt das Flag; Editieren entfernt es automatisch.
                    try {
                        $db->execute("ALTER TABLE pp_plan_rows ADD COLUMN review_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER notes");
                        $results[] = "Spalte pp_plan_rows.review_flag hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "pp_plan_rows.review_flag: " . $e->getMessage();
                    }
                    try { $db->execute("ALTER TABLE pp_plan_rows ADD INDEX idx_review_flag (review_flag)"); }
                    catch (\Exception $e) { /* Duplicate key */ }

                    // pp_plan_rows: review_note — kurzer KI-Status-Text als Pill (z.B. "Zu klären"),
                    // gesetzt von der KI-Anreicherung. "Passt" bzw. Editieren entfernt ihn zusammen mit review_flag.
                    try {
                        $db->execute("ALTER TABLE pp_plan_rows ADD COLUMN review_note VARCHAR(60) NULL AFTER review_flag");
                        $results[] = "Spalte pp_plan_rows.review_note hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "pp_plan_rows.review_note: " . $e->getMessage();
                    }

                    // Regeltyp „projektplanner" fuer die KI-Anreicherung. Die Regeln leben im
                    // geteilten rules-System (kundengescoped, verwaltbar unter /rules) — kein
                    // eigener Speicher. Enum erweitern + Typ-Eintrag anlegen (beides idempotent).
                    try {
                        $rt = $db->queryOne("SHOW COLUMNS FROM rules LIKE 'rule_type'");
                        if ($rt && strpos((string)$rt['Type'], "'projektplanner'") === false) {
                            $db->execute("ALTER TABLE rules MODIFY COLUMN rule_type ENUM('style','format','content','link','tone','seo','language','projektplanner') NOT NULL DEFAULT 'content'");
                            $results[] = "rules.rule_type um 'projektplanner' erweitert";
                        }
                    } catch (\Exception $e) { $results[] = "rules.rule_type: " . $e->getMessage(); }
                    try {
                        if (!$db->queryValue("SELECT id FROM rule_types WHERE slug = 'projektplanner'")) {
                            $db->insert('rule_types', ['slug' => 'projektplanner', 'name' => 'Projektplanner', 'description' => 'Regeln für die KI-Anreicherung im Projektplanner (Duplizieren mit KI)', 'color' => '#0ea5e9', 'icon' => 'checklist', 'sort_order' => 20, 'is_active' => 1, 'is_system' => 0]);
                            $results[] = "rule_types 'projektplanner' angelegt";
                        }
                    } catch (\Exception $e) { $results[] = "rule_types projektplanner: " . $e->getMessage(); }

                    // pp_customer_budget.billing_mode — Abrechnungs-Modell pro Monat (gemischte Projekte:
                    // Retainer in einigen, „auf Zuruf" in anderen Quartalen). NULL = erbt den Kunden-Default.
                    try {
                        $db->execute("ALTER TABLE pp_customer_budget ADD COLUMN billing_mode ENUM('retainer','zuruf') NULL DEFAULT NULL AFTER soll_ts");
                        $results[] = "Spalte pp_customer_budget.billing_mode hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "pp_customer_budget.billing_mode: " . $e->getMessage();
                    }

                    // KI-Sparring pro Plan (Dialog + fortsetzbarer Verlauf).
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS pp_sparring_conversations (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            plan_id INT NOT NULL,
                            customer_id INT NULL,
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY uq_plan (plan_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        $db->execute("CREATE TABLE IF NOT EXISTS pp_sparring_messages (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            conversation_id INT NOT NULL,
                            role ENUM('user','assistant') NOT NULL,
                            content LONGTEXT NOT NULL,
                            model VARCHAR(64) NULL,
                            tokens_in INT NULL,
                            tokens_out INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            KEY idx_conv (conversation_id, id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    } catch (\Exception $e) { $results[] = "pp_sparring: " . $e->getMessage(); }

                    // lam_verlinkungen.ist_topp — Topp-Link-Kennzeichen (Ja/Nein), bulk-setzbar + filterbar
                    try {
                        $db->execute("ALTER TABLE lam_verlinkungen ADD COLUMN ist_topp TINYINT(1) NOT NULL DEFAULT 0 AFTER ist_neu");
                        $results[] = "Spalte lam_verlinkungen.ist_topp hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "lam_verlinkungen.ist_topp: " . $e->getMessage();
                    }
                    try { $db->execute("ALTER TABLE lam_verlinkungen ADD INDEX idx_ist_topp (ist_topp)"); }
                    catch (\Exception $e) { /* Duplicate key */ }

                    // chat_access_requests — Schreibzugriff-Anfragen für fremde Chats
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS chat_access_requests (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            conversation_id INT NOT NULL,
                            requester_id INT NOT NULL,
                            owner_id INT NOT NULL,
                            status ENUM('pending','approved','denied','cancelled') NOT NULL DEFAULT 'pending',
                            message VARCHAR(500) NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            resolved_at TIMESTAMP NULL,
                            resolved_by INT NULL,
                            UNIQUE KEY uniq_pending (conversation_id, requester_id, status),
                            INDEX idx_owner_status (owner_id, status),
                            INDEX idx_conv (conversation_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        $results[] = "Tabelle chat_access_requests erstellt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) $results[] = "chat_access_requests: " . $e->getMessage();
                    }
                    // chat_conversations.write_open — „alle, die den Chat sehen, duerfen schreiben"
                    try {
                        $db->execute("ALTER TABLE chat_conversations ADD COLUMN write_open TINYINT(1) NOT NULL DEFAULT 0 AFTER is_private");
                        $results[] = "Spalte chat_conversations.write_open hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "chat_conversations.write_open: " . $e->getMessage();
                    }

                    // ===== Customer Portal (Kundenansicht) — Phase 1 Fundament =====
                    // Rolle 'customer' (an genau 1 Kunden gebunden, read-only + Kommentare)
                    try {
                        $db->execute("ALTER TABLE users MODIFY COLUMN role enum('admin','manager','user','guest','customer') NOT NULL DEFAULT 'user'");
                        $results[] = "users.role um 'customer' erweitert";
                    } catch (\Exception $e) {
                        $results[] = "users.role ENUM: " . $e->getMessage();
                    }
                    // Permission-Matrix: Kunde × Modul/Tool × Freischaltung
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS customer_portal_permissions (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NOT NULL,
                            module_key VARCHAR(60) NOT NULL,
                            kind ENUM('module','tool') NOT NULL DEFAULT 'module',
                            enabled TINYINT(1) NOT NULL DEFAULT 0,
                            tool_scope JSON NULL,
                            created_by INT NULL,
                            updated_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY uniq_customer_module (customer_id, module_key),
                            INDEX idx_customer (customer_id),
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        $results[] = "Tabelle customer_portal_permissions erstellt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) $results[] = "customer_portal_permissions: " . $e->getMessage();
                    }
                    // Pro Steckbrief-Karte: für Kunden sichtbar ja/nein (Default aus)
                    try {
                        $db->execute("ALTER TABLE customer_cards ADD COLUMN customer_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER is_system");
                        $results[] = "Spalte customer_cards.customer_visible hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "customer_cards.customer_visible: " . $e->getMessage();
                    }
                    // Kommentare (Kunde <-> Team), Sichtbarkeit auf Account-Ebene
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS customer_portal_comments (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NOT NULL,
                            author_user_id INT NOT NULL,
                            author_role ENUM('team','customer') NOT NULL,
                            body TEXT NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_customer_created (customer_id, created_at),
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        $results[] = "Tabelle customer_portal_comments erstellt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) $results[] = "customer_portal_comments: " . $e->getMessage();
                    }
                    // Kundenportal-KI-Chat: KI als Autor-Rolle zulassen
                    try {
                        $db->execute("ALTER TABLE customer_portal_comments MODIFY author_role ENUM('team','customer','ki') NOT NULL");
                    } catch (\Exception $e) { /* idempotent */ }
                    // Setting-Eintraege (z.B. ki_active) in der Permission-Matrix erlauben
                    try {
                        $db->execute("ALTER TABLE customer_portal_permissions MODIFY kind ENUM('module','tool','setting') NOT NULL DEFAULT 'module'");
                    } catch (\Exception $e) { /* idempotent */ }
                    // Kunden-Websites: pm_monitors als einzige Quelle (is_primary markiert die Hauptseite).
                    // Die einmalige Datenmigration (settings.domains + customers.website -> pm_monitors)
                    // erfolgt per scripts/migrate-customer-websites.php (idempotent).
                    try { $db->execute("ALTER TABLE pm_monitors ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_id"); } catch (\Exception $e) { /* idempotent */ }
                    // Kundenportal-Chat: getrennte Unterhaltungen (Threads) je Kunde
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS customer_portal_conversations (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NOT NULL,
                            title VARCHAR(200) NOT NULL DEFAULT 'Neue Unterhaltung',
                            ki_active TINYINT(1) NOT NULL DEFAULT 1,
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_customer_updated (customer_id, updated_at),
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        $results[] = "Tabelle customer_portal_conversations erstellt";
                    } catch (\Exception $e) { if (strpos($e->getMessage(), 'already exists') === false) $results[] = "cpc: " . $e->getMessage(); }
                    // Kommentare einer Unterhaltung zuordnen
                    try { $db->execute("ALTER TABLE customer_portal_comments ADD COLUMN conversation_id INT NULL AFTER customer_id"); } catch (\Exception $e) { /* idempotent */ }
                    try { $db->execute("ALTER TABLE customer_portal_comments ADD INDEX idx_conversation (conversation_id)"); } catch (\Exception $e) { /* idempotent */ }
                    // Datei-Anhaenge zu Nachrichten (PDF etc.) inkl. extrahiertem Text fuer die KI
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS customer_portal_attachments (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NOT NULL,
                            conversation_id INT NULL,
                            comment_id INT NULL,
                            original_name VARCHAR(255) NOT NULL,
                            stored_name VARCHAR(255) NOT NULL,
                            mime VARCHAR(120) NULL,
                            size INT NOT NULL DEFAULT 0,
                            extracted_text MEDIUMTEXT NULL,
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_comment (comment_id),
                            INDEX idx_customer (customer_id),
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        $results[] = "Tabelle customer_portal_attachments erstellt";
                    } catch (\Exception $e) { if (strpos($e->getMessage(), 'already exists') === false) $results[] = "cpa: " . $e->getMessage(); }
                    // Bestehende Kommentare ohne Unterhaltung in eine Standard-Unterhaltung migrieren
                    try {
                        $orphans = $db->query("SELECT DISTINCT customer_id FROM customer_portal_comments WHERE conversation_id IS NULL") ?: [];
                        foreach ($orphans as $o) {
                            $cidp = (int) $o['customer_id'];
                            $convId = (int) $db->insert('customer_portal_conversations', ['customer_id' => $cidp, 'title' => 'Bisherige Unterhaltung']);
                            $db->execute("UPDATE customer_portal_comments SET conversation_id = ? WHERE customer_id = ? AND conversation_id IS NULL", [$convId, $cidp]);
                        }
                        if (!empty($orphans)) $results[] = "Portal-Kommentare in Unterhaltungen migriert";
                    } catch (\Exception $e) { /* best effort */ }

                    // pp_plans: plan_typ entkoppelt vom Workflow-Status — ein Plan kann archiviert sein,
                    // aber trotzdem als Einzelprojekt erkennbar bleiben (sonst geht der Typ beim
                    // Archivieren verloren, weil plan_status='einzelprojekt' überschrieben wird).
                    try {
                        $db->execute("ALTER TABLE pp_plans ADD COLUMN plan_typ ENUM('quartalsprojekt','einzelprojekt') NOT NULL DEFAULT 'quartalsprojekt' AFTER plan_status");
                        $results[] = "Spalte pp_plans.plan_typ hinzugefügt";
                        // Backfill: existierende einzelprojekt-Markierungen + Plans mit offer_ts
                        $db->execute("UPDATE pp_plans SET plan_typ='einzelprojekt' WHERE plan_status='einzelprojekt' OR (offer_ts IS NOT NULL AND offer_ts > 0)");
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "pp_plans.plan_typ: " . $e->getMessage();
                    }
                    try { $db->execute("ALTER TABLE pp_plans ADD INDEX idx_plan_typ (plan_typ)"); }
                    catch (\Exception $e) { /* Duplicate key */ }

                    // pp_multi_shares: gemeinsame Share-Links für eine Mehr-Plan-Übersicht
                    // (z.B. „alle aktiven Quartalsprojekte, gefiltert auf Umsetzung = TKI"). Statt pro Plan
                    // einen Sharelink zu erzeugen, kann ein User einen kuratierten Filter teilen.
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS pp_multi_shares (
                            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            share_hash VARCHAR(64) NOT NULL UNIQUE,
                            title VARCHAR(255) NULL,
                            plan_ids_json LONGTEXT NOT NULL,
                            filters_json LONGTEXT NULL,
                            share_password VARCHAR(255) NULL,
                            expires_at DATETIME NULL,
                            is_snapshot TINYINT(1) NOT NULL DEFAULT 0,
                            snapshot_data_json LONGTEXT NULL,
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            accessed_at TIMESTAMP NULL,
                            access_count INT NOT NULL DEFAULT 0,
                            INDEX idx_share_hash (share_hash),
                            INDEX idx_created_by (created_by),
                            INDEX idx_expires (expires_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    } catch (\Exception $e) { /* Table exists */ }
                    // Nachträgliche Spalten für ältere Installs
                    foreach ([
                        'expires_at'         => "DATETIME NULL AFTER share_password",
                        'is_snapshot'        => "TINYINT(1) NOT NULL DEFAULT 0 AFTER expires_at",
                        'snapshot_data_json' => "LONGTEXT NULL AFTER is_snapshot",
                    ] as $col => $def) {
                        try { $db->execute("ALTER TABLE pp_multi_shares ADD COLUMN $col $def"); }
                        catch (\Exception $e) { /* Duplicate column */ }
                    }

                    // pp_plans: Abrechnungs-Felder für Einzelprojekte (Festbudget-Modell — soll, abgerechnet, Datum, Notiz)
                    foreach ([
                        'abgerechnet_ts'    => "DECIMAL(10,2) NULL AFTER offer_ts",
                        'abgerechnet_am'    => "DATE NULL AFTER abgerechnet_ts",
                        'abrechnung_notiz'  => "TEXT NULL AFTER abgerechnet_am",
                    ] as $col => $def) {
                        try {
                            $db->execute("ALTER TABLE pp_plans ADD COLUMN $col $def");
                            $results[] = "Spalte pp_plans.$col hinzugefügt";
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "pp_plans.$col: " . $e->getMessage();
                        }
                    }
                    // pp_customer_budget: Abgerechnet + Ist-TS-Override + Monatsbemerkung
                    foreach ([
                        'abgerechnet_ts'   => "DECIMAL(10,2) NULL AFTER soll_ts",
                        'ist_ts_override' => "DECIMAL(10,2) NULL AFTER ist_override",
                        'bemerkung'       => "TEXT NULL AFTER ist_note",
                    ] as $col => $def) {
                        try {
                            $db->execute("ALTER TABLE pp_customer_budget ADD COLUMN $col $def");
                            $results[] = "Spalte pp_customer_budget.$col hinzugefügt";
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "pp_customer_budget.$col: " . $e->getMessage();
                        }
                    }

                    // ====== CRM-Verknuepfung: customers <-> crm_firmen ======
                    // Damit ein Thoxan-Kunde manuell mit der entsprechenden CRM-Firma
                    // verlinkt werden kann. Bestimmt spaeter, in welchen Knowledge-
                    // Bucket CRM-Kontakte/Firmen synchronisiert werden.
                    try {
                        $db->execute("ALTER TABLE customers ADD COLUMN crm_firma_id BIGINT UNSIGNED NULL AFTER logo_path");
                        $results[] = "Spalte customers.crm_firma_id hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "customers.crm_firma_id: " . $e->getMessage();
                    }
                    try {
                        $db->execute("ALTER TABLE customers ADD INDEX idx_crm_firma (crm_firma_id)");
                        $results[] = "Index customers.idx_crm_firma hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate key') === false) $results[] = "customers.idx_crm_firma: " . $e->getMessage();
                    }

                    // LAM-Linkquellen: redaktionelle Bewertung aus dem Excel-Import
                    // (Begruendung/Themenpassung + Prio/Urteil) — vorher gingen diese Spalten verloren.
                    foreach ([
                        "ALTER TABLE lam_domains ADD COLUMN beschreibung TEXT NULL AFTER notizen",
                        "ALTER TABLE lam_domains ADD COLUMN bewertung VARCHAR(20) NULL AFTER beschreibung",
                        "ALTER TABLE lam_domains ADD INDEX idx_bewertung (bewertung)",
                    ] as $lamSql) {
                        try {
                            $db->execute($lamSql);
                        } catch (\Exception $e) {
                            $msg = $e->getMessage();
                            if (strpos($msg, 'Duplicate column') === false && strpos($msg, 'Duplicate key') === false) {
                                $results[] = "lam_domains: " . $msg;
                            }
                        }
                    }

                    // CRM-Firma: Logo-Spalte
                    try {
                        $db->execute("ALTER TABLE crm_firmen ADD COLUMN logo_path VARCHAR(255) NULL AFTER website");
                        $results[] = "Spalte crm_firmen.logo_path hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "crm_firmen.logo_path: " . $e->getMessage();
                    }

                    // CRM-Kontakt: firma_status (verknuepft/ohne_firmenbezug/pflege_offen)
                    try {
                        $db->execute("ALTER TABLE crm_kontakte ADD COLUMN firma_status ENUM('verknuepft','ohne_firmenbezug','pflege_offen') NULL DEFAULT 'verknuepft' AFTER firma_id");
                        $results[] = "Spalte crm_kontakte.firma_status hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "crm_kontakte.firma_status: " . $e->getMessage();
                    }
                    try {
                        $db->execute("ALTER TABLE crm_kontakte ADD INDEX idx_firma_status (firma_status)");
                        $results[] = "Index crm_kontakte.idx_firma_status hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate key') === false) $results[] = "crm_kontakte.idx_firma_status: " . $e->getMessage();
                    }

                    // CRM-Kontakt: shared_email-Flag (Ehepaare, geteilte Mailboxen)
                    try {
                        $db->execute("ALTER TABLE crm_kontakte ADD COLUMN shared_email TINYINT(1) NOT NULL DEFAULT 0 AFTER email_zweit");
                        $results[] = "Spalte crm_kontakte.shared_email hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "crm_kontakte.shared_email: " . $e->getMessage();
                    }

                    // Zoho-Modified-Time + Last-Activity-Time aus Legacy-JSON in dedizierte Spalten
                    // — damit das Pflege-Center nach „echter" Aktualität sortieren kann
                    // (Aktivitäten-Tabelle ist nach Import alle gleichzeitig befüllt → wertlos)
                    foreach (['zoho_modified_at', 'zoho_last_activity_at', 'brevo_modified_at'] as $col) {
                        try {
                            $db->execute("ALTER TABLE crm_kontakte ADD COLUMN $col DATETIME NULL");
                            $results[] = "Spalte crm_kontakte.$col hinzugefügt";
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "crm_kontakte.$col: " . $e->getMessage();
                        }
                    }
                    foreach (['zoho_modified' => 'zoho_modified_at', 'brevo_modified' => 'brevo_modified_at'] as $idx => $col) {
                        try {
                            $db->execute("ALTER TABLE crm_kontakte ADD INDEX idx_$idx ($col)");
                        } catch (\Exception $e) { /* Duplicate key */ }
                    }
                    try {
                        $db->execute("ALTER TABLE crm_kontakte ADD INDEX idx_shared_email (shared_email)");
                        $results[] = "Index crm_kontakte.idx_shared_email hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate key') === false) $results[] = "crm_kontakte.idx_shared_email: " . $e->getMessage();
                    }

                    // CRM-Pflege: match_score von DECIMAL(4,3) auf DECIMAL(8,3) — fasst Werte > 9 wie „Anzahl fehlender Felder × Gewichtung"
                    try {
                        $db->execute("ALTER TABLE crm_pflege_issues MODIFY COLUMN match_score DECIMAL(8,3) NULL");
                    } catch (\Exception $e) {
                        // Tabelle existiert evtl. noch nicht — egal, das CREATE unten setzt die richtige Definition
                    }

                    // ====== CRM-Pflege-Center: Issue-Queue ======
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS crm_pflege_issues (
                            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                            typ VARCHAR(40) NOT NULL,
                            schwere ENUM('niedrig','mittel','hoch') NOT NULL DEFAULT 'mittel',
                            titel VARCHAR(255) NOT NULL,
                            beschreibung TEXT NULL,
                            entities_json LONGTEXT NOT NULL,
                            ai_empfehlung_json LONGTEXT NULL,
                            ai_confidence DECIMAL(3,2) NULL,
                            match_score DECIMAL(8,3) NULL,
                            status ENUM('offen','in_bearbeitung','erledigt','ignoriert','obsolet') NOT NULL DEFAULT 'offen',
                            erledigt_durch INT NULL,
                            erledigt_am DATETIME NULL,
                            erledigt_aktion VARCHAR(40) NULL,
                            erledigt_notiz TEXT NULL,
                            dedup_key VARCHAR(120) NOT NULL,
                            gefunden_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            aktualisiert_am DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (id),
                            UNIQUE KEY uniq_dedup (dedup_key),
                            INDEX idx_status_typ (status, typ),
                            INDEX idx_typ_schwere (typ, schwere),
                            INDEX idx_gefunden (gefunden_am)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        $results[] = "Tabelle crm_pflege_issues angelegt";
                    } catch (\Exception $e) {
                        $results[] = "crm_pflege_issues: " . $e->getMessage();
                    }

                    // ====== CRM-Wissens-Sync + Reporting-Import: ENUM erweitern + Queue-Tabelle ======
                    try {
                        $db->execute("ALTER TABLE knowledge_documents MODIFY COLUMN source_type ENUM('upload','web','text','chat','asana','transcript','projektplan','reporting','reporting_summary','kundensteckbrief','crm_kontakt','crm_firma') NOT NULL");
                        $results[] = "ENUM knowledge_documents.source_type um reporting/reporting_summary/crm erweitert";
                    } catch (\Exception $e) {
                        $results[] = "knowledge_documents.source_type ENUM: " . $e->getMessage();
                    }
                    try {
                        $db->execute("CREATE TABLE IF NOT EXISTS crm_sync_queue (
                            entity_typ ENUM('kontakt','firma') NOT NULL,
                            entity_id BIGINT UNSIGNED NOT NULL,
                            enqueued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            last_change_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            last_attempt_at DATETIME NULL,
                            last_error TEXT NULL,
                            attempts INT NOT NULL DEFAULT 0,
                            PRIMARY KEY (entity_typ, entity_id),
                            INDEX idx_change (last_change_at),
                            INDEX idx_attempt (last_attempt_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        $results[] = "Tabelle crm_sync_queue angelegt";
                    } catch (\Exception $e) {
                        $results[] = "crm_sync_queue: " . $e->getMessage();
                    }

                    // ====== Projektplanner: 8 neue pp_-Tabellen ======
                    $ppSchemas = [
                        'pp_team_members' => "CREATE TABLE IF NOT EXISTS pp_team_members (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NULL,
                            name VARCHAR(255) NOT NULL,
                            abbreviation VARCHAR(10) NULL,
                            capacity_hours INT DEFAULT 160,
                            hex_color VARCHAR(7) NULL,
                            sort_order INT DEFAULT 0,
                            is_active TINYINT(1) DEFAULT 1,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY uniq_user_id (user_id),
                            INDEX idx_active_sort (is_active, sort_order),
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_plans' => "CREATE TABLE IF NOT EXISTS pp_plans (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NULL,
                            title VARCHAR(255) NOT NULL,
                            period_from DATE NULL,
                            period_to DATE NULL,
                            quarter VARCHAR(10) NULL,
                            plan_status ENUM('entwurf','aktiv','einzelprojekt','reporting','abgeschlossen','archiviert') DEFAULT 'entwurf',
                            asana_project_gid VARCHAR(64) NULL,
                            asana_section_gid VARCHAR(64) NULL,
                            share_hash VARCHAR(64) NULL UNIQUE,
                            share_password VARCHAR(255) NULL,
                            state TINYINT(1) DEFAULT 1,
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_customer_state (customer_id, state),
                            INDEX idx_share_hash (share_hash),
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_plan_rows' => "CREATE TABLE IF NOT EXISTS pp_plan_rows (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            plan_id INT NOT NULL,
                            row_type ENUM('item','section','note','spacer') NOT NULL DEFAULT 'item',
                            description TEXT NULL,
                            date_from DATE NULL, date_to DATE NULL, timeframe VARCHAR(100) NULL,
                            ist_hours DECIMAL(10,2) DEFAULT 0, planned_hours DECIMAL(10,2) DEFAULT 0,
                            responsible TEXT NULL, lead_responsible VARCHAR(255) NULL,
                            deadline VARCHAR(100) NULL,
                            is_done TINYINT(1) DEFAULT 0, is_placeholder TINYINT(1) DEFAULT 0,
                            is_focus TINYINT(1) DEFAULT 0, no_ticket TINYINT(1) DEFAULT 0,
                            actual_hours VARCHAR(100) NULL, notes TEXT NULL,
                            asana_gid VARCHAR(64) NULL, asana_url VARCHAR(500) NULL, asana_task_name VARCHAR(500) NULL,
                            position INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_plan_position (plan_id, position),
                            INDEX idx_asana_gid (asana_gid),
                            FOREIGN KEY (plan_id) REFERENCES pp_plans(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_plan_revisions' => "CREATE TABLE IF NOT EXISTS pp_plan_revisions (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            plan_id INT NOT NULL, user_id INT NULL,
                            snapshot LONGTEXT NOT NULL, label VARCHAR(255) NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_plan (plan_id, id),
                            FOREIGN KEY (plan_id) REFERENCES pp_plans(id) ON DELETE CASCADE,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_plan_feedback' => "CREATE TABLE IF NOT EXISTS pp_plan_feedback (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            plan_id INT NOT NULL, row_id INT NULL,
                            author_name VARCHAR(255) DEFAULT 'Anonym',
                            feedback_type ENUM('like','dislike','comment') DEFAULT 'comment',
                            message TEXT NULL, read_at TIMESTAMP NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_plan_unread (plan_id, read_at),
                            FOREIGN KEY (plan_id) REFERENCES pp_plans(id) ON DELETE CASCADE,
                            FOREIGN KEY (row_id) REFERENCES pp_plan_rows(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_plan_budget' => "CREATE TABLE IF NOT EXISTS pp_plan_budget (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            plan_id INT NOT NULL, year INT NOT NULL, month INT NOT NULL,
                            soll_ts DECIMAL(10,2) DEFAULT 0,
                            UNIQUE KEY uniq_plan_year_month (plan_id, year, month),
                            FOREIGN KEY (plan_id) REFERENCES pp_plans(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_customer_budget' => "CREATE TABLE IF NOT EXISTS pp_customer_budget (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NOT NULL, year INT NOT NULL, month INT NOT NULL,
                            soll_ts DECIMAL(10,2) DEFAULT 0,
                            ist_override DECIMAL(10,2) NULL, ist_note VARCHAR(500) NULL,
                            UNIQUE KEY uniq_customer_year_month (customer_id, year, month),
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_person_shares' => "CREATE TABLE IF NOT EXISTS pp_person_shares (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            person_name VARCHAR(255) NOT NULL,
                            share_hash VARCHAR(64) NOT NULL UNIQUE,
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_share_hash (share_hash),
                            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pp_plan_shares' => "CREATE TABLE IF NOT EXISTS pp_plan_shares (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            plan_id INT NOT NULL,
                            user_id INT NOT NULL,
                            permission ENUM('read','edit','write') NOT NULL DEFAULT 'read',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY uniq_plan_user (plan_id, user_id),
                            INDEX idx_user (user_id),
                            FOREIGN KEY (plan_id) REFERENCES pp_plans(id) ON DELETE CASCADE,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pm_monitors' => "CREATE TABLE IF NOT EXISTS pm_monitors (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NULL,
                            url VARCHAR(500) NOT NULL,
                            label VARCHAR(255) NOT NULL,
                            check_interval INT NOT NULL DEFAULT 2,
                            alert_email VARCHAR(255) NULL,
                            status ENUM('up','down','paused') DEFAULT 'up',
                            last_check TIMESTAMP NULL,
                            last_status_code INT DEFAULT 0,
                            last_response_time INT DEFAULT 0,
                            sub_urls TEXT NULL,
                            category VARCHAR(100) DEFAULT '',
                            report_schedule ENUM('none','weekly','monthly','both') DEFAULT 'both',
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_status (status),
                            INDEX idx_customer (customer_id),
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pm_monitor_log' => "CREATE TABLE IF NOT EXISTS pm_monitor_log (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            monitor_id INT NOT NULL,
                            checked_url VARCHAR(500) NULL,
                            status_code INT NOT NULL DEFAULT 0,
                            response_time_ms INT NOT NULL DEFAULT 0,
                            is_up TINYINT(1) NOT NULL DEFAULT 1,
                            checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_monitor_time (monitor_id, checked_at, is_up),
                            INDEX idx_checked_at (checked_at),
                            FOREIGN KEY (monitor_id) REFERENCES pm_monitors(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pm_monitor_incidents' => "CREATE TABLE IF NOT EXISTS pm_monitor_incidents (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            monitor_id INT NOT NULL,
                            started_at TIMESTAMP NOT NULL,
                            ended_at TIMESTAMP NULL,
                            duration_minutes INT DEFAULT 0,
                            notified TINYINT(1) NOT NULL DEFAULT 0,
                            INDEX idx_monitor (monitor_id),
                            FOREIGN KEY (monitor_id) REFERENCES pm_monitors(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                        // === Prompt-Insights (Chatverlauf-Analyse) ===
                        'pi_imports' => "CREATE TABLE IF NOT EXISTS pi_imports (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NOT NULL,
                            filename VARCHAR(255) NOT NULL,
                            source ENUM('claude','chatgpt','unknown') NOT NULL DEFAULT 'unknown',
                            imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            chat_count INT DEFAULT 0,
                            message_count INT DEFAULT 0,
                            status ENUM('processing','done','failed') NOT NULL DEFAULT 'processing',
                            error_message TEXT NULL,
                            INDEX idx_user (user_id, imported_at),
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pi_chats' => "CREATE TABLE IF NOT EXISTS pi_chats (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            import_id INT NOT NULL,
                            external_chat_id VARCHAR(120) NULL,
                            title VARCHAR(500) NULL,
                            source ENUM('claude','chatgpt','unknown') NOT NULL DEFAULT 'unknown',
                            created_at_ext TIMESTAMP NULL,
                            updated_at_ext TIMESTAMP NULL,
                            message_count INT DEFAULT 0,
                            INDEX idx_import (import_id),
                            FOREIGN KEY (import_id) REFERENCES pi_imports(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pi_messages' => "CREATE TABLE IF NOT EXISTS pi_messages (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            chat_id INT NOT NULL,
                            position INT NOT NULL DEFAULT 0,
                            role ENUM('user','assistant','system') NOT NULL DEFAULT 'user',
                            content_anon MEDIUMTEXT NULL,
                            assistant_excerpt TEXT NULL,
                            word_count INT DEFAULT 0,
                            char_count INT DEFAULT 0,
                            has_attachment TINYINT(1) DEFAULT 0,
                            attachment_type VARCHAR(60) NULL,
                            is_initial TINYINT(1) DEFAULT 0,
                            sent_at TIMESTAMP NULL,
                            weekday TINYINT NULL,
                            hour TINYINT NULL,
                            embedding_done TINYINT(1) DEFAULT 0,
                            INDEX idx_chat_pos (chat_id, position),
                            INDEX idx_role_initial (role, is_initial),
                            FOREIGN KEY (chat_id) REFERENCES pi_chats(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pi_embeddings' => "CREATE TABLE IF NOT EXISTS pi_embeddings (
                            message_id INT PRIMARY KEY,
                            dim INT NOT NULL DEFAULT 1536,
                            vec MEDIUMBLOB NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (message_id) REFERENCES pi_messages(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pi_clusters' => "CREATE TABLE IF NOT EXISTS pi_clusters (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NOT NULL,
                            import_id INT NULL,
                            label VARCHAR(200) NULL,
                            description TEXT NULL,
                            message_count INT DEFAULT 0,
                            top_terms TEXT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_user (user_id),
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (import_id) REFERENCES pi_imports(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pi_cluster_assignments' => "CREATE TABLE IF NOT EXISTS pi_cluster_assignments (
                            message_id INT NOT NULL,
                            cluster_id INT NOT NULL,
                            distance DECIMAL(8,5) DEFAULT 0,
                            PRIMARY KEY (message_id, cluster_id),
                            INDEX idx_cluster (cluster_id),
                            FOREIGN KEY (message_id) REFERENCES pi_messages(id) ON DELETE CASCADE,
                            FOREIGN KEY (cluster_id) REFERENCES pi_clusters(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pi_rules' => "CREATE TABLE IF NOT EXISTS pi_rules (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NOT NULL,
                            cluster_id INT NULL,
                            text TEXT NOT NULL,
                            status ENUM('vorschlag','freigegeben','verworfen') NOT NULL DEFAULT 'vorschlag',
                            source ENUM('auto','manuell') NOT NULL DEFAULT 'auto',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_user_status (user_id, status),
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (cluster_id) REFERENCES pi_clusters(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                        'pi_whitelist' => "CREATE TABLE IF NOT EXISTS pi_whitelist (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NOT NULL,
                            original VARCHAR(200) NOT NULL,
                            placeholder VARCHAR(80) NOT NULL DEFAULT '<NAME>',
                            source VARCHAR(40) DEFAULT 'manuell',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY uniq_user_original (user_id, original),
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                    ];
                    foreach ($ppSchemas as $name => $sql) {
                        try {
                            $db->execute($sql);
                            $results[] = "Tabelle $name erstellt";
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'already exists') === false) $results[] = "$name: " . $e->getMessage();
                        }
                    }
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "customers.abbreviation: " . $e->getMessage();
                    }
                }

                // 2FA-Spalten für Users hinzufügen
                $twoFaColumns = [
                    'two_factor_secret' => 'VARCHAR(32) NULL',
                    'two_factor_enabled' => 'BOOLEAN DEFAULT FALSE',
                    'two_factor_backup_codes' => 'TEXT NULL',
                    'two_factor_confirmed_at' => 'TIMESTAMP NULL'
                ];
                foreach ($twoFaColumns as $column => $definition) {
                    try {
                        $db->execute("ALTER TABLE users ADD COLUMN $column $definition");
                        $results[] = "Spalte users.$column hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "users.$column: " . $e->getMessage();
                        }
                    }
                }

                // User-Einladungs-Spalten
                $inviteColumns = [
                    'invite_token' => 'VARCHAR(64) NULL',
                    'invite_expires_at' => 'TIMESTAMP NULL',
                ];
                foreach ($inviteColumns as $column => $definition) {
                    try {
                        $db->execute("ALTER TABLE users ADD COLUMN $column $definition");
                        $results[] = "Spalte users.$column hinzugefuegt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "users.$column: " . $e->getMessage();
                        }
                    }
                }

                // Internes Feedback-Tool Tabelle
                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS internal_feedback (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            page_url VARCHAR(500) NOT NULL,
                            feedback_type ENUM('bug', 'feature', 'improvement', 'other') DEFAULT 'other',
                            description TEXT NOT NULL,
                            media_type ENUM('screenshot', 'video', 'none') DEFAULT 'none',
                            media_path VARCHAR(500) NULL,
                            status ENUM('new', 'in_progress', 'resolved', 'wont_fix') DEFAULT 'new',
                            admin_notes TEXT NULL,
                            browser_info TEXT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            resolved_at TIMESTAMP NULL,
                            resolved_by INT NULL,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    $results[] = "Tabelle internal_feedback erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "internal_feedback: " . $e->getMessage();
                    }
                }

                // ===== Regel-Typen Tabelle =====
                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS rule_types (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            slug VARCHAR(50) UNIQUE NOT NULL,
                            name VARCHAR(100) NOT NULL,
                            description TEXT,
                            color VARCHAR(7) DEFAULT '#6b7280',
                            icon VARCHAR(50) DEFAULT 'rule',
                            sort_order INT DEFAULT 0,
                            is_active BOOLEAN DEFAULT TRUE,
                            is_system BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle rule_types erstellt";

                    // Standard-Typen einfügen
                    $defaultTypes = [
                        ['style', 'Schreibstil', 'Regeln für den Schreibstil', '#3b82f6', 'edit_note', 1, 1],
                        ['format', 'Formatierung', 'Regeln für die Textformatierung', '#22c55e', 'format_list_bulleted', 2, 1],
                        ['content', 'Inhalt', 'Regeln für inhaltliche Vorgaben', '#f59e0b', 'article', 3, 1],
                        ['link', 'Links', 'Regeln für Link-Verwendung', '#004c9b', 'link', 4, 1],
                        ['tone', 'Tonalität', 'Regeln für Tonalität und Ansprache', '#ec4899', 'record_voice_over', 5, 1]
                    ];
                    foreach ($defaultTypes as $t) {
                        try {
                            $db->execute(
                                "INSERT IGNORE INTO rule_types (slug, name, description, color, icon, sort_order, is_system)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                                [$t[0], $t[1], $t[2], $t[3], $t[4], $t[5], $t[6]]
                            );
                        } catch (\Exception $e) {
                            // Ignorieren
                        }
                    }
                    $results[] = "Standard-Regeltypen eingefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "rule_types: " . $e->getMessage();
                    }
                }

                // ===== Regel-Kategorien Tabelle =====
                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS rule_categories (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            name VARCHAR(100) NOT NULL,
                            slug VARCHAR(50) UNIQUE NOT NULL,
                            description TEXT,
                            color VARCHAR(7) DEFAULT '#6b7280',
                            icon VARCHAR(50) DEFAULT 'folder',
                            sort_order INT DEFAULT 0,
                            is_active BOOLEAN DEFAULT TRUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle rule_categories erstellt";

                    // Beispiel-Kategorien einfügen
                    $defaultCategories = [
                        ['Allgemein', 'allgemein', 'Allgemeine Regeln für alle Projekte', '#6b7280', 'public', 1],
                        ['Kundenprojekte', 'kundenprojekte', 'Regeln für Kundenprojekte', '#3b82f6', 'business', 2],
                        ['Interne Projekte', 'intern', 'Regeln für interne Projekte', '#22c55e', 'home', 3],
                        ['Marketing', 'marketing', 'Regeln für Marketing-Texte', '#f59e0b', 'campaign', 4]
                    ];
                    foreach ($defaultCategories as $c) {
                        try {
                            $db->execute(
                                "INSERT IGNORE INTO rule_categories (name, slug, description, color, icon, sort_order)
                                 VALUES (?, ?, ?, ?, ?, ?)",
                                [$c[0], $c[1], $c[2], $c[3], $c[4], $c[5]]
                            );
                        } catch (\Exception $e) {
                            // Ignorieren
                        }
                    }
                    $results[] = "Standard-Regelkategorien eingefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "rule_categories: " . $e->getMessage();
                    }
                }

                // ===== rules Tabelle erweitern =====
                try {
                    $db->execute("ALTER TABLE rules ADD COLUMN rule_type_id INT NULL");
                    $results[] = "Spalte rules.rule_type_id hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "rules.rule_type_id: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("ALTER TABLE rules ADD COLUMN category_id INT NULL");
                    $results[] = "Spalte rules.category_id hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "rules.category_id: " . $e->getMessage();
                    }
                }

                // Strikt/Empfehlung pro Regel: 'strict' = 100% einhalten, 'soft' = 80/20-Empfehlung
                try {
                    $db->execute("ALTER TABLE rules ADD COLUMN enforcement ENUM('strict','soft') NOT NULL DEFAULT 'strict'");
                    $results[] = "Spalte rules.enforcement hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "rules.enforcement: " . $e->getMessage();
                    }
                }

                // Wirkungsbereich pro Regel: 'content' = nur erzeugte Inhalte, 'tool' = nur Tool-Dialog, 'both' = beides
                try {
                    $db->execute("ALTER TABLE rules ADD COLUMN applies_to ENUM('content','tool','both') NOT NULL DEFAULT 'both'");
                    $results[] = "Spalte rules.applies_to hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "rules.applies_to: " . $e->getMessage();
                    }
                }

                // Bestehende Daten migrieren: rule_type -> rule_type_id
                try {
                    $db->execute("
                        UPDATE rules r
                        JOIN rule_types rt ON r.rule_type = rt.slug
                        SET r.rule_type_id = rt.id
                        WHERE r.rule_type_id IS NULL AND r.rule_type IS NOT NULL
                    ");
                    $results[] = "Bestehende Regeln auf rule_type_id migriert";
                } catch (\Exception $e) {
                    $results[] = "Migration rule_type_id: " . $e->getMessage();
                }

                // Standardkategorie (Allgemein) für bestehende Regeln setzen
                try {
                    $allgemeinId = $db->queryValue("SELECT id FROM rule_categories WHERE slug = 'allgemein'");
                    if ($allgemeinId) {
                        $db->execute("UPDATE rules SET category_id = ? WHERE category_id IS NULL", [$allgemeinId]);
                        $results[] = "Standardkategorie für bestehende Regeln gesetzt";
                    }
                } catch (\Exception $e) {
                    $results[] = "Standardkategorie: " . $e->getMessage();
                }

                // ===== Generation Jobs Tabelle (Async Artikel-Generierung) =====
                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS generation_jobs (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            project_id INT NOT NULL,
                            customer_id INT,
                            user_id INT NOT NULL,
                            job_type ENUM('full_article', 'single_section', 'regenerate') DEFAULT 'full_article',
                            topic VARCHAR(500),
                            target_words INT DEFAULT 800,
                            style_slug VARCHAR(50),
                            model VARCHAR(100) NOT NULL,
                            sections_config JSON,
                            rule_ids JSON,
                            knowledge_ids JSON,
                            section_index INT NULL,
                            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
                            priority INT DEFAULT 0,
                            attempts INT DEFAULT 0,
                            max_attempts INT DEFAULT 3,
                            result JSON,
                            error_message TEXT,
                            tokens_input INT DEFAULT 0,
                            tokens_output INT DEFAULT 0,
                            processing_time_ms INT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            started_at TIMESTAMP NULL,
                            completed_at TIMESTAMP NULL,
                            INDEX idx_status (status),
                            INDEX idx_project (project_id),
                            INDEX idx_user (user_id),
                            INDEX idx_pending (status, priority DESC, created_at ASC),
                            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                            FOREIGN KEY (user_id) REFERENCES users(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle generation_jobs erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "generation_jobs: " . $e->getMessage();
                    }
                }

                // Projekt-Status Spalten für async Generation
                $projectColumns = [
                    'generation_status' => "ENUM('idle', 'generating', 'completed', 'failed') DEFAULT 'idle'",
                    'current_job_id' => 'INT NULL',
                    'last_generation_error' => 'TEXT NULL'
                ];
                foreach ($projectColumns as $column => $definition) {
                    try {
                        $db->execute("ALTER TABLE projects ADD COLUMN $column $definition");
                        $results[] = "Spalte projects.$column hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "projects.$column: " . $e->getMessage();
                        }
                    }
                }

                // Prompt-Speicherung für Jobs
                try {
                    $db->execute("ALTER TABLE generation_jobs ADD COLUMN prompt_used MEDIUMTEXT NULL");
                    $results[] = "Spalte generation_jobs.prompt_used hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "generation_jobs.prompt_used: " . $e->getMessage();
                    }
                }

                // ===== Autorenprofile =====
                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS author_profiles (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            customer_id INT NULL,
                            name VARCHAR(255) NOT NULL,
                            writing_style TEXT NULL,
                            expertise TEXT NULL,
                            tone VARCHAR(100) NULL,
                            perspective VARCHAR(50) NULL,
                            example_text TEXT NULL,
                            notes TEXT NULL,
                            is_active TINYINT(1) DEFAULT 1,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle author_profiles erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "author_profiles: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("ALTER TABLE context_items MODIFY item_type ENUM('customer','knowledge','url','pdf','text','rule','rule_category','knowledge_category','author')");
                    $results[] = "context_items.item_type um 'author' erweitert";
                } catch (\Exception $e) {
                    $results[] = "context_items ENUM: " . $e->getMessage();
                }

                // ===== Reliable Generation: Fortschritts-Felder =====
                $reliableColumns = [
                    'current_step' => 'VARCHAR(50) DEFAULT NULL',
                    'total_steps' => 'INT DEFAULT 0',
                    'completed_steps' => 'INT DEFAULT 0'
                ];
                foreach ($reliableColumns as $column => $definition) {
                    try {
                        $db->execute("ALTER TABLE generation_jobs ADD COLUMN $column $definition");
                        $results[] = "Spalte generation_jobs.$column hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "generation_jobs.$column: " . $e->getMessage();
                        }
                    }
                }

                // Generation Method Setting
                try {
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('generation_method', 'reliable', 'string', 'Generierungs-Methode: fast oder reliable')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key"
                    );
                    $results[] = "generation_method Setting hinzugefügt";
                } catch (\Exception $e) {
                    $results[] = "generation_method: " . $e->getMessage();
                }


                // ===== Canvas Projekt-Tabellen =====
                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS canvas_projects (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            customer_id INT NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            description TEXT NULL,
                            status ENUM('active','archived','completed') DEFAULT 'active',
                            briefing_readiness TINYINT UNSIGNED DEFAULT 0,
                            created_by INT NOT NULL,
                            updated_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_customer_status (customer_id, status)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle canvas_projects erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "canvas_projects: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS canvas_participants (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            canvas_id INT NOT NULL,
                            user_id INT NOT NULL,
                            canvas_role ENUM('auftraggeber','entwickler') NOT NULL,
                            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY uq_canvas_user (canvas_id, user_id),
                            INDEX idx_canvas (canvas_id),
                            INDEX idx_user (user_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle canvas_participants erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "canvas_participants: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS canvas_cards (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            canvas_id INT NOT NULL,
                            field ENUM('problem','loesung','input','magie','qualitaetssicherung','output','ergebnisse','risiken') NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            content TEXT NULL,
                            status ENUM('entwurf','in_arbeit','vollstaendig') DEFAULT 'entwurf',
                            sort_order INT DEFAULT 0,
                            created_by INT NOT NULL,
                            updated_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_canvas_field (canvas_id, field),
                            INDEX idx_canvas_sort (canvas_id, field, sort_order)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle canvas_cards erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "canvas_cards: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS canvas_card_versions (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            card_id INT NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            content TEXT NULL,
                            status ENUM('entwurf','in_arbeit','vollstaendig') NOT NULL,
                            changed_by INT NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_card (card_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle canvas_card_versions erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "canvas_card_versions: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS canvas_card_references (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            source_card_id INT NOT NULL,
                            target_card_id INT NOT NULL,
                            reference_type ENUM('backflow','relates_to','conflicts_with') DEFAULT 'relates_to',
                            note VARCHAR(500) NULL,
                            created_by INT NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY uq_ref (source_card_id, target_card_id),
                            INDEX idx_source (source_card_id),
                            INDEX idx_target (target_card_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle canvas_card_references erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "canvas_card_references: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("
                        CREATE TABLE IF NOT EXISTS canvas_ai_messages (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            canvas_id INT NOT NULL,
                            field ENUM('problem','loesung','input','magie','qualitaetssicherung','output','ergebnisse','risiken') NOT NULL,
                            role ENUM('user','assistant') NOT NULL,
                            content TEXT NOT NULL,
                            model_used VARCHAR(100) NULL,
                            tokens_input INT DEFAULT 0,
                            tokens_output INT DEFAULT 0,
                            created_by INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_convo (canvas_id, field, created_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $results[] = "Tabelle canvas_ai_messages erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "canvas_ai_messages: " . $e->getMessage();
                    }
                }

                // ===== Knowledge RAG Tabellen (7 Stueck) =====
                $knowledgeTables = [
                    'knowledge_documents' => "CREATE TABLE IF NOT EXISTS knowledge_documents (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        customer_id INT NULL,
                        title VARCHAR(500) NOT NULL,
                        description TEXT NULL,
                        source_type ENUM('upload','url','text','chat') NOT NULL,
                        source_ref VARCHAR(500) NULL,
                        category VARCHAR(100) NULL,
                        tags JSON NULL,
                        content_hash CHAR(64) NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        created_by INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_customer_active (customer_id, is_active),
                        INDEX idx_hash (content_hash),
                        FULLTEXT idx_search (title, description)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'knowledge_chunks' => "CREATE TABLE IF NOT EXISTS knowledge_chunks (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        document_id INT NOT NULL,
                        customer_id INT NULL,
                        chunk_index INT NOT NULL,
                        content TEXT NOT NULL,
                        word_count INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_doc_chunk (document_id, chunk_index),
                        INDEX idx_customer (customer_id),
                        FULLTEXT idx_content (content),
                        FOREIGN KEY (document_id) REFERENCES knowledge_documents(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'knowledge_embeddings' => "CREATE TABLE IF NOT EXISTS knowledge_embeddings (
                        chunk_id INT PRIMARY KEY,
                        customer_id INT NULL,
                        `vector` MEDIUMBLOB NOT NULL,
                        dimensions SMALLINT UNSIGNED NOT NULL DEFAULT 1536,
                        model VARCHAR(64) NOT NULL,
                        norm FLOAT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_customer (customer_id),
                        FOREIGN KEY (chunk_id) REFERENCES knowledge_chunks(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'knowledge_entities' => "CREATE TABLE IF NOT EXISTS knowledge_entities (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        customer_id INT NULL,
                        name VARCHAR(255) NOT NULL,
                        normalized_name VARCHAR(255) NOT NULL,
                        type ENUM('PER','ORG','LOC','PRODUCT','CONCEPT','EVENT','MISC') NOT NULL DEFAULT 'CONCEPT',
                        mention_count INT UNSIGNED DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_entity (customer_id, normalized_name, type),
                        INDEX idx_customer_type (customer_id, type),
                        INDEX idx_normalized (normalized_name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'knowledge_relations' => "CREATE TABLE IF NOT EXISTS knowledge_relations (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        customer_id INT NULL,
                        from_entity_id INT NOT NULL,
                        to_entity_id INT NOT NULL,
                        type VARCHAR(50) NOT NULL,
                        weight FLOAT NOT NULL DEFAULT 0.5,
                        source_document_id INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_from (from_entity_id, type),
                        INDEX idx_to (to_entity_id, type),
                        INDEX idx_customer (customer_id),
                        FOREIGN KEY (from_entity_id) REFERENCES knowledge_entities(id) ON DELETE CASCADE,
                        FOREIGN KEY (to_entity_id) REFERENCES knowledge_entities(id) ON DELETE CASCADE,
                        FOREIGN KEY (source_document_id) REFERENCES knowledge_documents(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'knowledge_chunk_entities' => "CREATE TABLE IF NOT EXISTS knowledge_chunk_entities (
                        chunk_id INT NOT NULL,
                        entity_id INT NOT NULL,
                        PRIMARY KEY (chunk_id, entity_id),
                        INDEX idx_entity (entity_id),
                        FOREIGN KEY (chunk_id) REFERENCES knowledge_chunks(id) ON DELETE CASCADE,
                        FOREIGN KEY (entity_id) REFERENCES knowledge_entities(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                    'knowledge_usage' => "CREATE TABLE IF NOT EXISTS knowledge_usage (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        chunk_id INT NOT NULL,
                        conversation_id INT NULL,
                        score FLOAT NULL,
                        used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_chunk (chunk_id, used_at),
                        INDEX idx_conv (conversation_id),
                        FOREIGN KEY (chunk_id) REFERENCES knowledge_chunks(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                ];
                foreach ($knowledgeTables as $tname => $tsql) {
                    try {
                        $db->execute($tsql);
                        $results[] = "Tabelle {$tname} erstellt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            $results[] = "{$tname}: " . $e->getMessage();
                        }
                    }
                }

                // Spalten fuer Reprocess + Original-Content
                $kdCols = [
                    'original_content' => 'MEDIUMTEXT NULL',
                    'updated_by' => 'INT NULL',
                    'external_id' => 'VARCHAR(64) NULL',
                ];
                foreach ($kdCols as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE knowledge_documents ADD COLUMN {$col} {$def}");
                        $results[] = "Spalte knowledge_documents.{$col} hinzugefuegt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "knowledge_documents.{$col}: " . $e->getMessage();
                        }
                    }
                }

                // source_type Enum: zuerst alle Werte erlauben (Migration-Zwischenzustand),
                // dann url+website auf web mappen, dann Enum aufraeumen.
                try {
                    $db->execute("ALTER TABLE knowledge_documents MODIFY source_type ENUM('upload','url','text','chat','asana','website','transcript','projektplan','kundensteckbrief','web') NOT NULL");
                } catch (\Exception $e) {
                    $results[] = "knowledge_documents.source_type Enum (Phase 1): " . $e->getMessage();
                }
                // ingest_mode-Spalte
                try {
                    $db->execute("ALTER TABLE knowledge_documents ADD COLUMN ingest_mode ENUM('auto','manuell') NULL AFTER source_type");
                    $results[] = "Spalte knowledge_documents.ingest_mode hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "ingest_mode: " . $e->getMessage();
                }
                // Karten-Docs auf neue source_type heben
                try {
                    $db->execute("UPDATE knowledge_documents SET source_type='kundensteckbrief' WHERE source_ref LIKE 'Kundensteckbrief%' AND source_type IN ('text','upload')");
                } catch (\Exception $e) {}
                // url + website → web mit ingest_mode-Trennung
                try {
                    $db->execute("UPDATE knowledge_documents SET source_type='web', ingest_mode='auto' WHERE source_type='website'");
                    $db->execute("UPDATE knowledge_documents SET source_type='web', ingest_mode='manuell' WHERE source_type='url'");
                    $db->execute("UPDATE knowledge_documents SET ingest_mode='manuell' WHERE source_type IN ('upload','text','chat') AND ingest_mode IS NULL");
                    $db->execute("UPDATE knowledge_documents SET ingest_mode='auto' WHERE source_type IN ('asana','transcript','projektplan','kundensteckbrief') AND ingest_mode IS NULL");
                } catch (\Exception $e) {
                    $results[] = "url/website -> web migration: " . $e->getMessage();
                }
                // Endgueltige ENUM ohne url+website
                try {
                    $db->execute("ALTER TABLE knowledge_documents MODIFY source_type ENUM('upload','web','text','chat','asana','transcript','projektplan','kundensteckbrief') NOT NULL");
                    $results[] = "Spalte knowledge_documents.source_type Enum final (web statt url+website)";
                } catch (\Exception $e) {
                    $results[] = "knowledge_documents.source_type Enum (Phase 2): " . $e->getMessage();
                }

                // Index fuer external lookup
                try {
                    $db->execute("ALTER TABLE knowledge_documents ADD INDEX idx_external (source_type, external_id)");
                    $results[] = "Index idx_external hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate key') === false) {
                        $results[] = "idx_external: " . $e->getMessage();
                    }
                }

                // users.asana_user_gid fuer Asana-Mapping
                try {
                    $db->execute("ALTER TABLE users ADD COLUMN asana_user_gid VARCHAR(64) NULL");
                    $results[] = "Spalte users.asana_user_gid hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "users.asana_user_gid: " . $e->getMessage();
                    }
                }

                // users.asana_user_email + asana_user_name — Cache der Asana-Identitaet
                foreach (['asana_user_email' => 'VARCHAR(255) NULL', 'asana_user_name' => 'VARCHAR(255) NULL'] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE users ADD COLUMN {$col} {$def}");
                        $results[] = "Spalte users.{$col} hinzugefuegt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "users.{$col}: " . $e->getMessage();
                        }
                    }
                }

                // users.planner_last_seen_at — Zeitpunkt des letzten Tagesplan-Besuchs (fuer "X neue Tasks"-Badge)
                try {
                    $db->execute("ALTER TABLE users ADD COLUMN planner_last_seen_at TIMESTAMP NULL DEFAULT NULL");
                    $results[] = "Spalte users.planner_last_seen_at hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "users.planner_last_seen_at: " . $e->getMessage();
                }

                // users.last_activity — letzte Aktivitaet (von Auth::init gedrosselt gesetzt); massgeblich
                // fuer den Inaktivitaets-Job statt last_login (Sessions bleiben bestehen).
                try {
                    $db->execute("ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL");
                    // Nur fuer JE eingeloggte User einen Startwert (last_login). Nie-eingeloggte bleiben NULL = 'Nie'.
                    $db->execute("UPDATE users SET last_activity = last_login WHERE last_activity IS NULL AND last_login IS NOT NULL");
                    $results[] = "Spalte users.last_activity hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "users.last_activity: " . $e->getMessage();
                }

                // users.asana_user_pat — persoenlicher Asana-PAT (AES-256-GCM verschluesselt)
                // Wird fuer den Tagesplaner gebraucht: jeder User sieht nur Tasks, die er selbst in Asana sieht.
                try {
                    $db->execute("ALTER TABLE users ADD COLUMN asana_user_pat VARCHAR(512) NULL");
                    $results[] = "Spalte users.asana_user_pat hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "users.asana_user_pat: " . $e->getMessage();
                    }
                }

                // Tabelle planner_tasks — Cache der Asana-Tasks fuer den Tagesplaner
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS planner_tasks (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        asana_task_gid VARCHAR(64) NOT NULL,
                        name VARCHAR(500) NOT NULL,
                        notes TEXT NULL,
                        due_on DATE NULL,
                        due_at DATETIME NULL,
                        asana_project_gid VARCHAR(64) NULL,
                        asana_project_name VARCHAR(255) NULL,
                        customer_id INT NULL,
                        pp_row_id INT NULL,
                        completed_at_asana TIMESTAMP NULL,
                        completed_at_local TIMESTAMP NULL,
                        postponed_to DATE NULL,
                        effort_minutes INT NULL,
                        ai_effort_estimate INT NULL,
                        ai_effort_confidence ENUM('low','medium','high') NULL,
                        ai_effort_reasoning VARCHAR(500) NULL,
                        score DECIMAL(8,3) NULL,
                        asana_modified_at DATETIME NULL,
                        asana_permalink_url VARCHAR(500) NULL,
                        last_synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_user_task (user_id, asana_task_gid),
                        INDEX idx_user_open (user_id, completed_at_local, completed_at_asana, postponed_to),
                        INDEX idx_due (due_on),
                        INDEX idx_customer (customer_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle planner_tasks erstellt";
                } catch (\Exception $e) {
                    $results[] = "planner_tasks: " . $e->getMessage();
                }

                // Planner: KI-Pre-Analyse + Mitlernen + Kanban-Slots
                foreach ([
                    'ai_summary'          => "VARCHAR(300) NULL",
                    'ai_significance'     => "TINYINT NULL",
                    'ai_recommended_when' => "ENUM('asap','this_week','when_possible') NULL",
                    'ai_complexity'       => "ENUM('low','medium','high') NULL",
                    'postpone_count'      => "INT NOT NULL DEFAULT 0",
                    'planner_ignored'     => "TINYINT(1) NOT NULL DEFAULT 0",
                    'manual_priority'     => "ENUM('asap','this_week','when_possible') NULL",
                    // Einheitliches 8-Stufen-Zeitraum-Modell (materialisiert: Default aus Frist, manuell ueberschreibbar)
                    'daily_slot'          => "ENUM('today','tomorrow','day_after','rest_week','next_week','this_month','later','occasion') NOT NULL DEFAULT 'occasion'",
                    'slot_pinned'         => "TINYINT(1) NOT NULL DEFAULT 0",
                    'category_hint'       => "ENUM('private','unclear') NULL",
                    'last_activity'       => "TEXT NULL",
                    'is_quick_task'       => "TINYINT(1) NOT NULL DEFAULT 0",
                    'auto_pushed_to_today_at' => "TIMESTAMP NULL DEFAULT NULL",
                    // Aktivitaetstyp fuer Querschnitts-Buendelung im Tagesplan (Phase 6 Pivot).
                    // KI-erkannt aus dem Task-Titel + last_activity; fester Enum, damit Pivot stabile Gruppen erzeugt.
                    'ai_activity_type'    => "ENUM('meeting','approval','communication','writing','review','research','admin','planning','execution','creative','other') NULL",
                    // 'Warten'-Status: Ball liegt aktuell NICHT bei mir (Delegation, externe Pruefung, etc.).
                    // Orthogonal zu 'completed' — eine Warten-Task ist offen aber nicht to-do.
                    'is_waiting'          => "TINYINT(1) NOT NULL DEFAULT 0",
                    'waiting_on'          => "VARCHAR(100) NULL",
                    'waiting_since'       => "DATETIME NULL",
                    // Auto-Wake-Signal: vom Sync gesetzt, wenn eine wartende Task in Asana neue
                    // Aktivitaet bekam → Ball ist zurueck. UI zeigt 'Signal'-Badge, bis quittiert.
                    'waiting_signal'      => "TINYINT(1) NOT NULL DEFAULT 0",
                    // Re-Analyse nach Asana-Aenderung: KI liest neue Kommentare und extrahiert
                    //  - ai_progress_pct: User hat schon einen Teil erledigt (0..100), minutes ist dann RESTAUFWAND
                    //  - ai_user_scheduled_slot: User hat sich selbst zeitlich festgelegt ("Rest naechste Woche")
                    //  - ai_re_analyzed_signal: 1 wenn die letzte Re-Analyse eine relevante Aenderung gebracht hat
                    //  - ai_re_analyzed_summary: Kurzbegruendung, was sich geaendert hat (fuer Karten-Banner)
                    'ai_progress_pct'        => "TINYINT NULL",
                    'ai_user_scheduled_slot' => "ENUM('today','tomorrow','day_after','rest_week','next_week','this_month','later','occasion') NULL",
                    'ai_re_analyzed_signal'  => "TINYINT(1) NOT NULL DEFAULT 0",
                    'ai_re_analyzed_summary' => "VARCHAR(300) NULL",
                    // Task war einmal an Dich zugewiesen, jetzt ist jemand anders Assignee.
                    // Bleibt im Tagesplan sichtbar (Du beobachtest weiter), wird aber im Sync nicht
                    // mehr ueber 'assignee=Du'-Query gefunden — stattdessen einzeln gefetcht.
                    'is_orphaned_from_asana' => "TINYINT(1) NOT NULL DEFAULT 0",
                    // Wiederkehrende Tasks (Asana hat keine eigene API dafuer — heuristisch erkannt aus
                    // Titel/Notes durch KI: '(monatlich)', '(wt.)', '(alle 2 Wochen)', '(quartalsweise)').
                    // Behandlung: erscheint NICHT staendig im Tagesplan, nur 2-5 Tage vor Faelligkeit
                    // als Quick-Win-Kandidat. Nach Erledigung Hinweis 'Asana-Frist verlaengert?'.
                    'is_recurring'           => "TINYINT(1) NOT NULL DEFAULT 0",
                    'recurring_pattern'      => "VARCHAR(60) NULL",
                    'recurring_interval_days' => "INT NULL",
                    // User-Override gegen Quick-Win-Heuristik: wenn 1, taucht die Task NIE in der
                    // Quick-Wins-Spalte auf, egal was KI / Recurring / Aufwand sagen.
                    // Vom User per Rechtsklick toggelbar, wird vom KI-Lauf NICHT ueberschrieben.
                    'quick_win_user_excluded' => "TINYINT(1) NOT NULL DEFAULT 0",
                    // Kröte des Tages: die eine Aufgabe, die der User heute unbedingt angehen will (Eat-the-Frog).
                    'is_toad'             => "TINYINT(1) NOT NULL DEFAULT 0",
                    // Tagesplan-Commit: der Tag, fuer den die Task explizit eingeplant ist (Phase 7).
                    'planned_for_date'    => "DATE NULL",
                    // Frist lokal angepasst (Re-Planung) — Asana-Sync ueberschreibt due_on dann nicht.
                    'due_locally_set'     => "TINYINT(1) NOT NULL DEFAULT 0",
                    // Was Asana zuletzt als Frist gemeldet hat — fuer den Abweichungs-Hinweis (Tool vs. Asana).
                    'asana_due_on'        => "DATE NULL",
                ] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE planner_tasks ADD COLUMN {$col} {$def}");
                        $results[] = "planner_tasks.{$col} hinzugefuegt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "planner_tasks.{$col}: " . $e->getMessage();
                        }
                    }
                }

                // ====== Einheitliches 8-Stufen-Zeitraum-Modell: Umstieg fuer BESTEHENDE Installationen ======
                // Reihenfolge wichtig: erst Altwerte migrieren, dann ENUM verengen/umstellen.
                try {
                    // 1) Prioritaet: 'maybe_drop' faellt weg -> auf 'when_possible' migrieren (vor ENUM-Aenderung).
                    $db->execute("UPDATE planner_tasks SET manual_priority='when_possible' WHERE manual_priority='maybe_drop'");
                    $db->execute("UPDATE planner_tasks SET ai_recommended_when='when_possible' WHERE ai_recommended_when='maybe_drop'");
                    // 2) daily_slot: temporaer auf VARCHAR weiten, Altwerte auf neue Buckets mappen, dann auf neues ENUM.
                    $isOldSlot = (string)($db->queryOne("SHOW COLUMNS FROM planner_tasks LIKE 'daily_slot'")['Type'] ?? '');
                    if (strpos($isOldSlot, 'pool') !== false || strpos($isOldSlot, 'mid_term') !== false) {
                        $db->execute("ALTER TABLE planner_tasks MODIFY daily_slot VARCHAR(20) NOT NULL DEFAULT 'occasion'");
                        // Bestandswerte grob mappen; wird vom naechsten recomputeBuckets()-Lauf ohnehin frisch gesetzt.
                        $db->execute("UPDATE planner_tasks SET daily_slot = CASE daily_slot
                            WHEN 'today' THEN 'today' WHEN 'tomorrow' THEN 'tomorrow'
                            WHEN 'this_week' THEN 'rest_week' WHEN 'mid_term' THEN 'this_month'
                            WHEN 'long_term' THEN 'later' ELSE 'occasion' END");
                        $db->execute("ALTER TABLE planner_tasks MODIFY daily_slot ENUM('today','tomorrow','day_after','rest_week','next_week','this_month','later','occasion') NOT NULL DEFAULT 'occasion'");
                        $results[] = "daily_slot auf 8-Stufen-Modell umgestellt";
                    }
                    // 3) ai_user_scheduled_slot analog umstellen.
                    $isOldUSlot = (string)($db->queryOne("SHOW COLUMNS FROM planner_tasks LIKE 'ai_user_scheduled_slot'")['Type'] ?? '');
                    if (strpos($isOldUSlot, 'pool') !== false || strpos($isOldUSlot, 'mid_term') !== false) {
                        $db->execute("ALTER TABLE planner_tasks MODIFY ai_user_scheduled_slot VARCHAR(20) NULL");
                        $db->execute("UPDATE planner_tasks SET ai_user_scheduled_slot = CASE ai_user_scheduled_slot
                            WHEN 'this_week' THEN 'rest_week' WHEN 'mid_term' THEN 'this_month'
                            WHEN 'long_term' THEN 'later' WHEN 'pool' THEN 'occasion'
                            ELSE ai_user_scheduled_slot END WHERE ai_user_scheduled_slot IS NOT NULL");
                        $db->execute("ALTER TABLE planner_tasks MODIFY ai_user_scheduled_slot ENUM('today','tomorrow','day_after','rest_week','next_week','this_month','later','occasion') NULL");
                    }
                    // 4) Prioritaets-ENUMs verengen (maybe_drop entfernen).
                    $db->execute("ALTER TABLE planner_tasks MODIFY manual_priority ENUM('asap','this_week','when_possible') NULL");
                    $db->execute("ALTER TABLE planner_tasks MODIFY ai_recommended_when ENUM('asap','this_week','when_possible') NULL");
                } catch (\Exception $e) {
                    $results[] = "Zeitraum-Modell-Migration: " . $e->getMessage();
                }

                // ====== Tagesplaner-Gamification (Baustein A-D) ======
                // score_events: Append-only-Log jedes Punkt-Ereignisses (pro abgehakter Task + Tages-Bonus).
                //   task_id NULL = Tages-Bonus (z.B. "alle Heute-Tasks erledigt"). Dedup pro Task ueber uk_task;
                //   Bonus-Dedup macht der Service (mehrere NULLs sind in MySQL-Unique nicht eindeutig).
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS planner_score_events (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        task_id INT NULL,
                        event_type ENUM('quick','normal','today_hero','overdue','all_today_bonus','toad') NOT NULL,
                        points INT NOT NULL,
                        customer_id INT NULL,
                        event_date DATE NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_task (user_id, task_id, event_type),
                        INDEX idx_user_date (user_id, event_date),
                        INDEX idx_customer (customer_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle planner_score_events erstellt";
                } catch (\Exception $e) {
                    $results[] = "planner_score_events: " . $e->getMessage();
                }
                // event_type-Enum nachruesten (bestehende Installationen): 'toad' (Kröte des Tages).
                try {
                    $db->execute("ALTER TABLE planner_score_events MODIFY event_type ENUM('quick','normal','today_hero','overdue','all_today_bonus','toad') NOT NULL");
                } catch (\Exception $e) { /* schon aktuell */ }

                // achievements: verdiente Badges. Pro Tag einmal je Schluessel verdienbar
                //   (taegliche wie 'sprint' / einmalige wie 'karteileiche' teilen sich die Tabelle).
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS planner_achievements (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        achievement_key VARCHAR(40) NOT NULL,
                        earned_on DATE NOT NULL,
                        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        meta JSON NULL,
                        UNIQUE KEY uk_user_ach_day (user_id, achievement_key, earned_on),
                        INDEX idx_user (user_id, earned_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle planner_achievements erstellt";
                } catch (\Exception $e) {
                    $results[] = "planner_achievements: " . $e->getMessage();
                }

                // daily_stats: ein Roll-up pro User und Tag — speist Streaks (Baustein B) und
                //   Wochenrueckblick (Baustein D), ohne jedes Mal score_events neu zu aggregieren.
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS planner_daily_stats (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        stat_date DATE NOT NULL,
                        tasks_completed INT NOT NULL DEFAULT 0,
                        points INT NOT NULL DEFAULT 0,
                        effort_minutes INT NOT NULL DEFAULT 0,
                        today_planned INT NOT NULL DEFAULT 0,
                        today_done INT NOT NULL DEFAULT 0,
                        all_today_done TINYINT(1) NOT NULL DEFAULT 0,
                        today_capacity_minutes INT NULL,
                        top_customer_id INT NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_user_day (user_id, stat_date),
                        INDEX idx_user_date (user_id, stat_date)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle planner_daily_stats erstellt";
                } catch (\Exception $e) {
                    $results[] = "planner_daily_stats: " . $e->getMessage();
                }
                // today_capacity_minutes nachruesten (fuer bestehende Installationen) — speist Achievement 'Punktlandung'.
                try {
                    $db->execute("ALTER TABLE planner_daily_stats ADD COLUMN today_capacity_minutes INT NULL");
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "planner_daily_stats.today_capacity_minutes: " . $e->getMessage();
                }

                // ====== Tagesplaner-Lernschleife ======
                // ai_is_quick: KI-Originalvorhersage fuer is_quick_task festhalten. is_quick_task wird vom
                //   User ueberschrieben — ohne diese Spalte koennten wir die Korrektur nicht erkennen.
                try {
                    $db->execute("ALTER TABLE planner_tasks ADD COLUMN ai_is_quick TINYINT(1) NULL");
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "planner_tasks.ai_is_quick: " . $e->getMessage();
                }
                // planner_ai_corrections: wo der Inhaber die KI ueberstimmt hat — wird als Few-Shot in den
                //   Analyse-Prompt gespiegelt, damit die KI sich an seine Bewertung anpasst (echtes Mitlernen).
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS planner_ai_corrections (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        task_id INT NOT NULL,
                        task_name VARCHAR(500) NOT NULL,
                        task_project VARCHAR(255) NULL,
                        task_notes_snippet VARCHAR(300) NULL,
                        field ENUM('effort','quick','priority','slot','customer') NOT NULL,
                        ai_value VARCHAR(80) NOT NULL,
                        user_value VARCHAR(80) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_user_task_field (user_id, task_id, field),
                        INDEX idx_user_recent (user_id, created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle planner_ai_corrections erstellt";
                } catch (\Exception $e) {
                    $results[] = "planner_ai_corrections: " . $e->getMessage();
                }
                // field-Enum nachruesten (bestehende Installationen): slot + customer ergaenzen.
                try {
                    $db->execute("ALTER TABLE planner_ai_corrections MODIFY field ENUM('effort','quick','priority','slot','customer') NOT NULL");
                } catch (\Exception $e) { /* schon aktuell */ }
                // planner_learned_rules: aus den Korrekturen destillierte Muster. status=candidate bis der
                //   Inhaber sie freischaltet (active); active-Regeln gehen in den Analyse-Prompt.
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS planner_learned_rules (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        field ENUM('effort','quick','priority','slot','customer','general') NOT NULL DEFAULT 'general',
                        rule_text VARCHAR(400) NOT NULL,
                        pattern_hint VARCHAR(200) NULL,
                        support_count INT NOT NULL DEFAULT 1,
                        status ENUM('candidate','active','dismissed') NOT NULL DEFAULT 'candidate',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        activated_at TIMESTAMP NULL,
                        INDEX idx_user_status (user_id, status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle planner_learned_rules erstellt";
                } catch (\Exception $e) {
                    $results[] = "planner_learned_rules: " . $e->getMessage();
                }
                // field-Enum nachruesten (bestehende Installationen): slot + customer ergaenzen.
                try {
                    $db->execute("ALTER TABLE planner_learned_rules MODIFY field ENUM('effort','quick','priority','slot','customer','general') NOT NULL DEFAULT 'general'");
                } catch (\Exception $e) { /* schon aktuell */ }

                // users.abbreviation fuer Kuerzel-Anzeige (Chat-Liste, Avatare)
                try {
                    $db->execute("ALTER TABLE users ADD COLUMN abbreviation VARCHAR(5) NULL");
                    $results[] = "Spalte users.abbreviation hinzugefuegt";
                    // Auto-Seed: Initialen aus Vor- und Nachname
                    $db->execute("UPDATE users SET abbreviation = UPPER(CONCAT(
                        LEFT(SUBSTRING_INDEX(name, ' ', 1), 1),
                        CASE
                            WHEN LOCATE(' ', name) > 0 THEN LEFT(SUBSTRING_INDEX(SUBSTRING_INDEX(name, ' ', 2), ' ', -1), 1)
                            ELSE LEFT(SUBSTRING(name, 2, 1), 1)
                        END
                    )) WHERE abbreviation IS NULL AND name IS NOT NULL AND name != ''");
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "users.abbreviation: " . $e->getMessage();
                    }
                }

                // knowledge_embeddings.vector_v — native MariaDB-VECTOR fuer ANN-Suche
                try {
                    $db->execute("ALTER TABLE knowledge_embeddings ADD COLUMN vector_v VECTOR(1536) NULL");
                    $results[] = "Spalte knowledge_embeddings.vector_v hinzugefuegt — Backfill: php cli/migrate-vectors.php";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "knowledge_embeddings.vector_v: " . $e->getMessage();
                    }
                }
                // HNSW-Index nur anlegen wenn alle Zeilen befuellt sind
                $missing = (int) $db->queryValue("SELECT COUNT(*) FROM knowledge_embeddings WHERE vector_v IS NULL");
                if ($missing === 0) {
                    try {
                        $db->execute("ALTER TABLE knowledge_embeddings MODIFY vector_v VECTOR(1536) NOT NULL");
                    } catch (\Exception $e) {}
                    try {
                        $db->execute("ALTER TABLE knowledge_embeddings ADD VECTOR INDEX idx_vector_v (vector_v)");
                        $results[] = "HNSW-Index idx_vector_v angelegt";
                    } catch (\Exception $e) {
                        if (stripos($e->getMessage(), 'Duplicate') === false) {
                            $results[] = "idx_vector_v: " . $e->getMessage();
                        }
                    }
                }

                // Guidelines-Tabelle (kundenuebergreifende Verhaltensvorgaben)
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS guidelines (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        category ENUM('tool_communication','content_output','internal') NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        content TEXT NOT NULL,
                        is_active TINYINT(1) DEFAULT 1,
                        sort_order INT DEFAULT 0,
                        created_by INT NULL,
                        updated_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_category_active (category, is_active, sort_order)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle guidelines erstellt";

                    // Default-Seed nur wenn Tabelle leer
                    $count = (int) $db->queryValue("SELECT COUNT(*) FROM guidelines");
                    if ($count === 0) {
                        $defaults = [
                            ['internal', 'Echte Umlaute verwenden', 'Schreibe immer echte Umlaute (ä ö ü ß) statt ASCII-Ersatz (ae oe ue ss). Gilt für alle Antworten und erzeugten Texte.', 10],
                            ['internal', 'Deutsch als Standardsprache', 'Antworte standardmäßig auf Deutsch, sofern der Nutzer nicht explizit eine andere Sprache verwendet oder anfordert.', 20],
                            ['tool_communication', 'Immer dutzen, freundlich aber nicht anbiedernd', 'Spreche den Nutzer dieses Tools immer mit Du an. Sei freundlich und kompetent, aber kein Arschkriecher — keine übertriebenen Komplimente, keine künstliche Begeisterung.', 10],
                            ['tool_communication', 'Direkt auf den Punkt', 'Antworte direkt und ohne unnötige Höflichkeitsfloskeln. Komm zum Wesentlichen, der Nutzer hat es eilig.', 20],
                            ['content_output', 'Keine übermäßigen Gedankenstriche', 'Nutze in erzeugten Texten keine Gedankenstriche (— oder –) zur Hervorhebung oder als Stil-Element. Klassische Satzzeichen reichen.', 10],
                        ];
                        foreach ($defaults as [$cat, $title, $content, $sort]) {
                            $db->insert('guidelines', [
                                'category' => $cat,
                                'title' => $title,
                                'content' => $content,
                                'is_active' => 1,
                                'sort_order' => $sort,
                            ]);
                        }
                        $results[] = "5 Default-Guidelines angelegt";
                    }
                } catch (\Exception $e) {
                    $results[] = "guidelines: " . $e->getMessage();
                }

                // Customer-Cards (Steckbrief-Widgets)
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS customer_cards (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        customer_id INT NOT NULL,
                        type ENUM('links','richtext','documents','images','brand') NOT NULL,
                        title VARCHAR(255) NOT NULL DEFAULT '',
                        body LONGTEXT NULL,
                        sort_order INT NOT NULL DEFAULT 0,
                        is_collapsed TINYINT(1) NOT NULL DEFAULT 0,
                        knowledge_document_id INT NULL,
                        created_by INT NULL,
                        updated_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_customer_sort (customer_id, sort_order),
                        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle customer_cards erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "customer_cards: " . $e->getMessage();
                    }
                }
                // size_w / size_h für Tile-Grid-Layout (1..3)
                foreach (['size_w', 'size_h'] as $col) {
                    try {
                        $db->execute("ALTER TABLE customer_cards ADD COLUMN $col TINYINT UNSIGNED NOT NULL DEFAULT 1");
                        $results[] = "Spalte customer_cards.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "customer_cards.$col: " . $e->getMessage();
                        }
                    }
                }
                // System-Cards Flag + Key
                foreach (['is_system' => 'TINYINT(1) NOT NULL DEFAULT 0', 'system_key' => 'VARCHAR(40) NULL'] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE customer_cards ADD COLUMN $col $def");
                        $results[] = "Spalte customer_cards.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "customer_cards.$col: " . $e->getMessage();
                        }
                    }
                }
                try {
                    $db->execute("ALTER TABLE customer_cards ADD UNIQUE KEY uniq_customer_system (customer_id, system_key)");
                    $results[] = "Index uniq_customer_system hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate key') === false) {
                        $results[] = "uniq_customer_system: " . $e->getMessage();
                    }
                }
                try {
                    $db->execute("ALTER TABLE customer_cards MODIFY type ENUM('links','richtext','documents','images','brand','contacts','kpi','tracking_status','accounts') NOT NULL");
                    $results[] = "ENUM customer_cards.type um 'kpi','tracking_status','accounts' erweitert";
                } catch (\Exception $e) {
                    $results[] = "type ENUM: " . $e->getMessage();
                }
                try {
                    $db->execute("ALTER TABLE card_layout_template_items MODIFY card_type ENUM('links','richtext','documents','images','brand','contacts','kpi','tracking_status','accounts') NOT NULL");
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), "doesn't exist") === false) $results[] = "card_layout_template_items.card_type: " . $e->getMessage();
                }
                // target_tab: Zuordnung Card → Steckbrief-Tab (uebersicht oder inhalte).
                // Andere Tabs (personen/dateien/marke) sind dedizierte Strukturen ohne Cards.
                try {
                    $db->execute("ALTER TABLE customer_cards ADD COLUMN target_tab ENUM('uebersicht','inhalte') NOT NULL DEFAULT 'inhalte'");
                    $results[] = "Spalte customer_cards.target_tab hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "customer_cards.target_tab: " . $e->getMessage();
                    }
                }
                // column_idx: Kanban-Spalte 1..3 fuer das Layout im Steckbrief
                try {
                    $db->execute("ALTER TABLE customer_cards ADD COLUMN column_idx TINYINT UNSIGNED NOT NULL DEFAULT 2");
                    $results[] = "Spalte customer_cards.column_idx hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "customer_cards.column_idx: " . $e->getMessage();
                    }
                }
                // target_tab ENUM auf Sonstiges + Websites erweitern
                try {
                    $db->execute("ALTER TABLE customer_cards MODIFY target_tab ENUM('uebersicht','inhalte','personen','dateien','marke','sonstiges','websites') NOT NULL DEFAULT 'inhalte'");
                    $results[] = "ENUM customer_cards.target_tab erweitert (sonstiges + websites)";
                } catch (\Exception $e) {
                    $results[] = "target_tab ENUM: " . $e->getMessage();
                }
                try {
                    $db->execute("ALTER TABLE card_layout_template_items MODIFY target_tab ENUM('uebersicht','inhalte','personen','dateien','marke','sonstiges','websites') NOT NULL DEFAULT 'inhalte'");
                } catch (\Exception $e) { /* table may not exist yet */ }
                // Card-Layout-Templates
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS card_layout_templates (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        name VARCHAR(120) NOT NULL,
                        description TEXT NULL,
                        source_customer_id INT NULL,
                        created_by INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_name (name),
                        INDEX idx_created_at (created_at DESC)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle card_layout_templates erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "card_layout_templates: " . $e->getMessage();
                }
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS card_layout_template_items (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        template_id INT NOT NULL,
                        card_type ENUM('links','richtext','documents','images','brand','contacts') NOT NULL,
                        system_key VARCHAR(40) NULL,
                        title_hint VARCHAR(255) NOT NULL DEFAULT '',
                        target_tab ENUM('uebersicht','inhalte','personen','dateien','marke','sonstiges') NOT NULL DEFAULT 'inhalte',
                        column_idx TINYINT UNSIGNED NOT NULL DEFAULT 2,
                        size_w TINYINT UNSIGNED NOT NULL DEFAULT 1,
                        position_order INT NOT NULL DEFAULT 0,
                        INDEX idx_template (template_id, position_order),
                        FOREIGN KEY (template_id) REFERENCES card_layout_templates(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle card_layout_template_items erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "card_layout_template_items: " . $e->getMessage();
                }

                // Steckbrief-Vorschlaege: persistente KI-Vorschlaege je Karte
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS customer_card_suggestions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        customer_id INT NOT NULL,
                        card_id INT NULL,
                        slot_key VARCHAR(80) NOT NULL,
                        payload LONGTEXT NOT NULL,
                        snippet TEXT NULL,
                        confidence DECIMAL(3,2) NOT NULL DEFAULT 0.50,
                        source_doc_ids VARCHAR(255) NULL,
                        status ENUM('pending','accepted','rejected','superseded') NOT NULL DEFAULT 'pending',
                        created_by INT NULL,
                        decided_by INT NULL,
                        decided_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_customer_status (customer_id, status),
                        INDEX idx_card (card_id),
                        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                        FOREIGN KEY (card_id) REFERENCES customer_cards(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle customer_card_suggestions erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "customer_card_suggestions: " . $e->getMessage();
                }

                // Steckbrief-Importe: Upload-Lifecycle fuer Stufe A
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS customer_steckbrief_imports (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        customer_id INT NOT NULL,
                        original_filename VARCHAR(255) NOT NULL,
                        file_path VARCHAR(500) NOT NULL,
                        mime_type VARCHAR(120) NULL,
                        status ENUM('uploaded','analyzing','ready','imported','failed') NOT NULL DEFAULT 'uploaded',
                        text_content LONGTEXT NULL,
                        proposed_cards LONGTEXT NULL,
                        error_message TEXT NULL,
                        created_by INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_customer (customer_id, status),
                        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle customer_steckbrief_imports erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "customer_steckbrief_imports: " . $e->getMessage();
                }

                // Admin-Code-Tasks: KI-gesteuerte Live-Code-Änderungen
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS admin_tasks (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        created_by INT NOT NULL,
                        prompt TEXT NOT NULL,
                        scope ENUM('frontend','frontend_backend','all') NOT NULL DEFAULT 'frontend',
                        status ENUM('pending','running','completed','failed','rolled_back') NOT NULL DEFAULT 'pending',
                        summary TEXT NULL,
                        error_message TEXT NULL,
                        tokens_input INT DEFAULT 0,
                        tokens_output INT DEFAULT 0,
                        started_at TIMESTAMP NULL,
                        completed_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_status (status),
                        INDEX idx_created (created_at DESC),
                        FOREIGN KEY (created_by) REFERENCES users(id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle admin_tasks erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "admin_tasks: " . $e->getMessage();
                }
                // Module-Spalte für admin_tasks (für Fokus-Auswahl)
                try {
                    $db->execute("ALTER TABLE admin_tasks ADD COLUMN module VARCHAR(50) NULL");
                    $results[] = "Spalte admin_tasks.module hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "admin_tasks.module: " . $e->getMessage();
                }
                // Status um 'awaiting_user' erweitern
                try {
                    $db->execute("ALTER TABLE admin_tasks MODIFY status ENUM('pending','running','awaiting_user','completed','failed','rolled_back') NOT NULL DEFAULT 'pending'");
                    $results[] = "admin_tasks.status um awaiting_user erweitert";
                } catch (\Exception $e) {
                    $results[] = "admin_tasks.status ENUM: " . $e->getMessage();
                }
                // Conversation-History pro Task (für Rückfragen-Mechanismus)
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS admin_task_messages (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        task_id INT NOT NULL,
                        role ENUM('user','assistant','tool_result') NOT NULL,
                        content LONGTEXT NULL,
                        tool_use_id VARCHAR(100) NULL,
                        tool_is_error TINYINT(1) DEFAULT 0,
                        iteration INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_task (task_id, id),
                        FOREIGN KEY (task_id) REFERENCES admin_tasks(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle admin_task_messages erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "admin_task_messages: " . $e->getMessage();
                }
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS admin_task_snapshots (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        task_id INT NOT NULL,
                        file_path VARCHAR(500) NOT NULL,
                        operation ENUM('write','create','delete') NOT NULL DEFAULT 'write',
                        original_content LONGTEXT NULL,
                        new_content LONGTEXT NULL,
                        file_existed TINYINT(1) NOT NULL DEFAULT 1,
                        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        rolled_back_at TIMESTAMP NULL,
                        INDEX idx_task (task_id, id),
                        FOREIGN KEY (task_id) REFERENCES admin_tasks(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle admin_task_snapshots erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "admin_task_snapshots: " . $e->getMessage();
                }

                // ====== KI-Coworker (Chat-Modus) ======
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS coworker_sessions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        created_by INT NOT NULL,
                        title VARCHAR(255) NULL,
                        status ENUM('active','idle','awaiting_user','closed','failed') NOT NULL DEFAULT 'idle',
                        tokens_input INT DEFAULT 0,
                        tokens_output INT DEFAULT 0,
                        cost_usd DECIMAL(10,4) DEFAULT 0,
                        cost_cap_usd DECIMAL(10,4) NULL,
                        last_activity_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user_status (created_by, status),
                        INDEX idx_last_activity (last_activity_at),
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle coworker_sessions erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "coworker_sessions: " . $e->getMessage();
                }
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS coworker_messages (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        session_id INT NOT NULL,
                        role ENUM('user','assistant','tool_result') NOT NULL,
                        content LONGTEXT NOT NULL,
                        tool_use_id VARCHAR(100) NULL,
                        iteration INT DEFAULT 0,
                        tokens_input INT DEFAULT 0,
                        tokens_output INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_session (session_id, id),
                        FOREIGN KEY (session_id) REFERENCES coworker_sessions(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle coworker_messages erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "coworker_messages: " . $e->getMessage();
                }
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS coworker_snapshots (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        session_id INT NOT NULL,
                        message_id INT NULL,
                        file_path VARCHAR(500) NOT NULL,
                        operation ENUM('write','create','delete','bash_touch') NOT NULL DEFAULT 'write',
                        original_content LONGTEXT NULL,
                        new_content LONGTEXT NULL,
                        file_existed TINYINT(1) NOT NULL DEFAULT 1,
                        source ENUM('write_file','bash') NOT NULL DEFAULT 'write_file',
                        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        rolled_back_at TIMESTAMP NULL,
                        INDEX idx_session_msg (session_id, message_id, id),
                        FOREIGN KEY (session_id) REFERENCES coworker_sessions(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle coworker_snapshots erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "coworker_snapshots: " . $e->getMessage();
                }
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS coworker_bash_log (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        session_id INT NOT NULL,
                        message_id INT NULL,
                        command TEXT NOT NULL,
                        stdout MEDIUMTEXT NULL,
                        stderr MEDIUMTEXT NULL,
                        exit_code INT NULL,
                        duration_ms INT NULL,
                        timed_out TINYINT(1) DEFAULT 0,
                        blocked_by_blacklist TINYINT(1) DEFAULT 0,
                        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_session (session_id, id),
                        FOREIGN KEY (session_id) REFERENCES coworker_sessions(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle coworker_bash_log erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) $results[] = "coworker_bash_log: " . $e->getMessage();
                }

                // chat_projects: Kontext-Scope (Ordner pro Privat / Projektübergreifend / Kunde)
                foreach (['context_scope' => "ENUM('private','projectwide','customer') NOT NULL DEFAULT 'private'", 'customer_id' => 'INT NULL DEFAULT NULL'] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE chat_projects ADD COLUMN $col $def");
                        $results[] = "Spalte chat_projects.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "chat_projects.$col: " . $e->getMessage();
                        }
                    }
                }
                try {
                    $db->execute("ALTER TABLE chat_projects ADD INDEX idx_ctx (context_scope, customer_id, user_id)");
                    $results[] = "Index chat_projects idx_ctx hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate key') === false) {
                        $results[] = "chat_projects idx_ctx: " . $e->getMessage();
                    }
                }

                // chat_conversation_messages: Sender-User-ID (wer hat die Nachricht geschrieben)
                try {
                    $db->execute("ALTER TABLE chat_conversation_messages ADD COLUMN sender_user_id INT NULL DEFAULT NULL");
                    $results[] = "Spalte chat_conversation_messages.sender_user_id hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "chat_conversation_messages.sender_user_id: " . $e->getMessage();
                    }
                }
                try {
                    $db->execute("ALTER TABLE chat_conversation_messages ADD INDEX idx_sender (sender_user_id)");
                    $results[] = "Index chat_conversation_messages.idx_sender hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate key') === false) {
                        $results[] = "chat_conversation_messages idx_sender: " . $e->getMessage();
                    }
                }

                // chat_conversations: Soft-Delete (Papierkorb)
                foreach (['deleted_at' => 'TIMESTAMP NULL DEFAULT NULL', 'deleted_by' => 'INT NULL DEFAULT NULL'] as $col => $def) {
                    try {
                        $db->execute("ALTER TABLE chat_conversations ADD COLUMN $col $def");
                        $results[] = "Spalte chat_conversations.$col hinzugefügt";
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $results[] = "chat_conversations.$col: " . $e->getMessage();
                        }
                    }
                }
                try {
                    $db->execute("ALTER TABLE chat_conversations ADD INDEX idx_deleted (deleted_at)");
                    $results[] = "Index chat_conversations.idx_deleted hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate key') === false) {
                        $results[] = "chat_conversations idx_deleted: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS customer_card_versions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        card_id INT NOT NULL,
                        title VARCHAR(255) NOT NULL DEFAULT '',
                        body LONGTEXT NULL,
                        snapshot_by INT NULL,
                        snapshot_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_card_time (card_id, snapshot_at DESC),
                        FOREIGN KEY (card_id) REFERENCES customer_cards(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle customer_card_versions erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "customer_card_versions: " . $e->getMessage();
                    }
                }

                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS customer_card_files (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        card_id INT NOT NULL,
                        file_path VARCHAR(500) NOT NULL,
                        file_name VARCHAR(255) NOT NULL,
                        file_size BIGINT NOT NULL,
                        mime_type VARCHAR(100) NULL,
                        title VARCHAR(255) NULL,
                        sort_order INT NOT NULL DEFAULT 0,
                        knowledge_document_id INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_card (card_id, sort_order),
                        FOREIGN KEY (card_id) REFERENCES customer_cards(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle customer_card_files erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "customer_card_files: " . $e->getMessage();
                    }
                }

                // ===== Lokale Modelle als zusaetzlicher Provider =====
                // provider-ENUMs um 'google' + 'local' erweitern (idempotent via MODIFY)
                try {
                    $db->execute("ALTER TABLE ai_models MODIFY provider ENUM('openai','anthropic','google','local') NOT NULL");
                    $results[] = "ai_models.provider ENUM um 'local' erweitert";
                } catch (\Exception $e) {
                    $results[] = "ai_models.provider: " . $e->getMessage();
                }
                try {
                    $db->execute("ALTER TABLE usage_logs MODIFY api_provider ENUM('openai','anthropic','google','local') NOT NULL");
                    $results[] = "usage_logs.api_provider ENUM um 'local' erweitert";
                } catch (\Exception $e) {
                    $results[] = "usage_logs.api_provider: " . $e->getMessage();
                }

                // Lokale Modelle seeden (Naming exakt, damit lokal auf einen Blick erkennbar)
                $localModels = [
                    ['qwen2.5:14b', 'Lokal: Qwen 2.5 14B', 'local', 0, 0, 4096, 20],
                    ['gpt-oss:20b', 'Lokal: GPT-OSS 20B', 'local', 0, 0, 4096, 21],
                    ['llama3.1:8b', 'Lokal: Llama 3.1 8B', 'local', 0, 0, 4096, 22],
                ];
                foreach ($localModels as $m) {
                    try {
                        $db->execute(
                            "INSERT IGNORE INTO ai_models (model_id, display_name, provider, cost_input, cost_output, max_tokens, sort_order, is_active, chat_enabled)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)",
                            [$m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]]
                        );
                    } catch (\Exception $e) {
                        // Ignorieren wenn schon vorhanden
                    }
                }
                $results[] = "Lokale Modelle eingefuegt";

                // Default-Settings fuer den lokalen Provider (Key bleibt leer -> per UI setzen)
                try {
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('local_base_url', 'https://ki.thoxan.com/llm/v1', 'string', 'Base-URL des lokalen Inference-Servers (OpenAI-kompatibel)')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key"
                    );
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('local_api_key', '', 'string', 'API-Key des lokalen Inference-Servers')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key"
                    );
                    $results[] = "Settings local_base_url/local_api_key angelegt";
                } catch (\Exception $e) {
                    $results[] = "local settings: " . $e->getMessage();
                }

                // Performance-Log pro LLM-Request (Vergleich lokal vs. Cloud)
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS llm_request_log (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        provider VARCHAR(20) NOT NULL,
                        model VARCHAR(100) NOT NULL,
                        use_case VARCHAR(80) NULL,
                        user_id INT NULL,
                        customer_id INT NULL,
                        tokens_input INT DEFAULT 0,
                        tokens_output INT DEFAULT 0,
                        tokens_total INT DEFAULT 0,
                        ttft_ms INT NULL,
                        total_ms INT NULL,
                        tokens_per_second DECIMAL(8,2) NULL,
                        success TINYINT(1) NOT NULL DEFAULT 1,
                        error_message TEXT NULL,
                        INDEX idx_model_created (model, created_at),
                        INDEX idx_provider_created (provider, created_at),
                        INDEX idx_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle llm_request_log erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "llm_request_log: " . $e->getMessage();
                    }
                }

                // Detail-Log pro Request: finaler System-Prompt, User-Prompt, RAG-Chunks
                // und Antwort — fuer volle Nachvollziehbarkeit/Analyse. Separate Tabelle,
                // damit die schlanke Performance-Tabelle schnell bleibt; rotiert nach 90 Tagen.
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS llm_request_detail (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        log_id INT NOT NULL,
                        conversation_id INT NULL,
                        message_id INT NULL,
                        system_prompt MEDIUMTEXT NULL,
                        user_message MEDIUMTEXT NULL,
                        response_text MEDIUMTEXT NULL,
                        rag_chunks MEDIUMTEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_log (log_id),
                        INDEX idx_conv (conversation_id),
                        INDEX idx_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle llm_request_detail erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "llm_request_detail: " . $e->getMessage();
                    }
                }
                // Rerank-Protokoll pro Nachricht (Anbieter/Modell/volle Rangliste mit Scores).
                try {
                    $db->execute("ALTER TABLE llm_request_detail ADD COLUMN rerank_meta MEDIUMTEXT NULL AFTER rag_chunks");
                    $results[] = "llm_request_detail.rerank_meta hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "llm_request_detail.rerank_meta: " . $e->getMessage();
                    }
                }

                // Globaler Standard-System-Prompt fuer den Chat (zentral pflegbar unter
                // /admin/settings?tab=chat). Greift, wenn eine Konversation keinen eigenen
                // Prompt hat. Enthaelt eine harte Faktentreue-Regel gegen Halluzinationen.
                try {
                    $defaultChatPrompt =
                        "Du bist der KI-Assistent von Thoxan und unterstuetzt das Team beim Schreiben, "
                        . "Recherchieren und Beantworten von Fragen. Antworte praezise, sachlich und klar "
                        . "strukturiert auf Deutsch.\n\n"
                        . "Faktentreue (sehr wichtig): Stuetze Dich ausschliesslich auf belegte Informationen "
                        . "— auf das bereitgestellte Wissen aus der Wissensdatenbank, die geltenden Regeln, "
                        . "angehaengte Dateien und den bisherigen Gespraechsverlauf. Erfinde keine Fakten, "
                        . "Namen, Zahlen oder Quellen. Wenn die noetige Information nicht vorliegt, sage das "
                        . "ehrlich und benenne, was fehlt, statt zu raten. Kennzeichne klar, wenn etwas eine "
                        . "Einschaetzung und kein Beleg ist.";
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('chat_system_prompt', ?, 'string', 'Globaler Standard-System-Prompt fuer den Chat (Fallback, wenn eine Konversation keinen eigenen Prompt hat)')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key",
                        [$defaultChatPrompt]
                    );
                    $results[] = "Setting chat_system_prompt angelegt";
                } catch (\Exception $e) {
                    $results[] = "chat_system_prompt: " . $e->getMessage();
                }

                // Zentrale Prompt-Verwaltung: Tabelle fuer Overrides der LLM-System-Prompts.
                // Verwaltung unter /admin/settings?tab=prompts; Code-Standards in SystemPromptService.
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS system_prompts (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        prompt_key VARCHAR(80) NOT NULL UNIQUE,
                        content MEDIUMTEXT NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        updated_by INT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle system_prompts erstellt";

                    // Bisherigen Chat-Default-Prompt (Setting chat_system_prompt) als Override
                    // fuer chat_default uebernehmen, damit frühere Anpassungen erhalten bleiben.
                    $old = $db->queryOne("SELECT setting_value FROM settings WHERE setting_key = 'chat_system_prompt'");
                    if ($old && trim((string)($old['setting_value'] ?? '')) !== '') {
                        $db->execute(
                            "INSERT INTO system_prompts (prompt_key, content) VALUES ('chat_default', ?)
                             ON DUPLICATE KEY UPDATE prompt_key = prompt_key",
                            [$old['setting_value']]
                        );
                        $results[] = "chat_default aus chat_system_prompt uebernommen";
                    }
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "system_prompts: " . $e->getMessage();
                    }
                }

                // Versionshistorie aller System-Prompts (Nachvollziehbarkeit + Gold-Standard-Optimierung).
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS system_prompt_versions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        prompt_key VARCHAR(80) NOT NULL,
                        content MEDIUMTEXT NULL,
                        note VARCHAR(255) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        created_by INT NULL,
                        INDEX idx_key_created (prompt_key, created_at),
                        INDEX idx_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle system_prompt_versions erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "system_prompt_versions: " . $e->getMessage();
                    }
                }

                // Reranking-Defaults (aus; lokal; 40 Kandidaten -> Top 8). Verwaltung unter
                // /admin/settings?tab=ki ("Reranking"). URL/Modell leer = Anbieter-Default.
                try {
                    $rerankDefaults = [
                        ['rerank_enabled', '0', 'bool', 'Reranking der RAG-Treffer an/aus'],
                        ['rerank_provider', 'local', 'string', 'Rerank-Anbieter: local|cohere|jina|voyage'],
                        ['rerank_url', '', 'string', 'Rerank-Endpoint-URL (nur fuer local/custom noetig)'],
                        ['rerank_model', '', 'string', 'Rerank-Modell (leer = Anbieter-Default)'],
                        ['rerank_candidates', '40', 'int', 'Wie viele Kandidaten vor dem Rerank geholt werden'],
                        ['rerank_top_n', '8', 'int', 'Wie viele Chunks nach dem Rerank ans LLM gehen'],
                    ];
                    foreach ($rerankDefaults as $rd) {
                        $db->execute(
                            "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                             VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key",
                            $rd
                        );
                    }
                    $results[] = "Reranking-Defaults angelegt";
                } catch (\Exception $e) {
                    $results[] = "rerank settings: " . $e->getMessage();
                }

                // ===== Transkription: Backend pro Job (lokal vs. Remote-GPU-Whisper) =====
                try {
                    $db->execute("ALTER TABLE tr_jobs ADD COLUMN transcription_backend ENUM('local','remote') NOT NULL DEFAULT 'local' AFTER model");
                    $results[] = "tr_jobs.transcription_backend hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "tr_jobs.transcription_backend: " . $e->getMessage();
                    }
                }
                try {
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('whisper_remote_url', 'https://ki.thoxan.com/whisper/v1/audio/transcriptions', 'string', 'URL des lokalen Whisper-Servers (OpenAI-kompatibel)')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key"
                    );
                    $db->execute(
                        "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                         VALUES ('whisper_remote_model', 'Systran/faster-whisper-large-v3', 'string', 'Modellname fuer den Remote-Whisper')
                         ON DUPLICATE KEY UPDATE setting_key = setting_key"
                    );
                    $results[] = "Whisper-Remote-Settings angelegt";
                } catch (\Exception $e) {
                    $results[] = "whisper settings: " . $e->getMessage();
                }

                // ===== Wissen V2 (paralleles System: bge-m3 + Qdrant) =====
                try {
                    $db->execute("CREATE TABLE IF NOT EXISTS kb_qdrant_sync (
                        chunk_id BIGINT PRIMARY KEY,
                        document_id BIGINT NOT NULL,
                        customer_id BIGINT NULL,
                        status ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
                        model VARCHAR(64) NULL,
                        error TEXT NULL,
                        synced_at TIMESTAMP NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_status (status),
                        INDEX idx_document (document_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $results[] = "Tabelle kb_qdrant_sync erstellt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = "kb_qdrant_sync: " . $e->getMessage();
                    }
                }
                // Default-Settings fuer Wissen V2 (job_type ist varchar -> keine ENUM-Migration noetig)
                $wissenV2Defaults = [
                    ['qdrant_url', 'http://localhost:6333', 'URL der Qdrant-Vektordatenbank (Wissen V2)'],
                    ['qdrant_api_key', '', 'API-Key fuer Qdrant (optional)'],
                    ['embedding_local_url', 'https://ki.thoxan.com/embeddings/embeddings', 'Lokaler OpenAI-kompatibler Embeddings-Endpoint (bge-m3)'],
                    ['embedding_local_model', 'bge-m3', 'Modellname fuer lokale Embeddings'],
                    ['embedding_local_dim', '1024', 'Vektor-Dimension des lokalen Embedding-Modells'],
                ];
                foreach ($wissenV2Defaults as $sd) {
                    try {
                        $db->execute(
                            "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                             VALUES (?, ?, 'string', ?) ON DUPLICATE KEY UPDATE setting_key = setting_key",
                            [$sd[0], $sd[1], $sd[2]]
                        );
                    } catch (\Exception $e) { /* ignorieren */ }
                }
                $results[] = "Wissen-V2-Settings angelegt";
                // Pro-Konversation waehlbare Wissens-Engine (v1=OpenAI/MariaDB, v2=bge-m3/Qdrant)
                try {
                    $db->execute("ALTER TABLE chat_conversations ADD COLUMN knowledge_engine VARCHAR(8) NOT NULL DEFAULT 'v1' AFTER use_knowledge");
                    $results[] = "chat_conversations.knowledge_engine hinzugefuegt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                        $results[] = "chat_conversations.knowledge_engine: " . $e->getMessage();
                    }
                }

                // Linkoption: artikelthema von varchar(255) auf TEXT erweitern (Thema + Kontext kombiniert)
                try {
                    $db->execute("ALTER TABLE lam_vorschlagsliste_eintraege MODIFY COLUMN artikelthema TEXT NULL");
                    $results[] = "lam_vorschlagsliste_eintraege.artikelthema → TEXT";
                } catch (\Exception $e) { /* idempotent */ }
                // Separates Feld für „Kontext für Linkeinbau" (vorher: zusammen mit Thema in artikelthema)
                try {
                    $db->execute("ALTER TABLE lam_vorschlagsliste_eintraege ADD COLUMN kontext_einbau TEXT NULL AFTER artikelthema");
                    $results[] = "lam_vorschlagsliste_eintraege.kontext_einbau hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "kontext_einbau: " . $e->getMessage();
                }
                // Interner Einkaufspreis beim Anbieter — NIEMALS in Asana / Excel-Kunde
                try {
                    $db->execute("ALTER TABLE lam_vorschlagsliste_eintraege ADD COLUMN preis_anbieter DECIMAL(10,2) NULL AFTER preis_kunde");
                    $results[] = "lam_vorschlagsliste_eintraege.preis_anbieter hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "preis_anbieter: " . $e->getMessage();
                }
                // Beispielartikel/Kategorie-URL der Zieldomain — optional, nur falls vorhanden ins Asana-Ticket
                try {
                    $db->execute("ALTER TABLE lam_vorschlagsliste_eintraege ADD COLUMN beispielartikel_url VARCHAR(500) NULL AFTER preis_anbieter");
                    $results[] = "lam_vorschlagsliste_eintraege.beispielartikel_url hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "beispielartikel_url: " . $e->getMessage();
                }
                // Flag: darf der Kunden-/Markenname im Kontext-Absatz genannt werden? (manche Plattformen verbieten das)
                try {
                    $db->execute("ALTER TABLE lam_vorschlagsliste_eintraege ADD COLUMN mit_anbieternennung TINYINT(1) NOT NULL DEFAULT 0 AFTER kontext_einbau");
                    $results[] = "lam_vorschlagsliste_eintraege.mit_anbieternennung hinzugefügt";
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') === false) $results[] = "mit_anbieternennung: " . $e->getMessage();
                }

            } catch (\Exception $e) {
                $results[] = "Fehler: " . $e->getMessage();
            }

            // Neue, inkrementelle Migrationen aus sql/migrations/ mitziehen.
            // Der obige Legacy-Block bleibt die Baseline fuers Alt-Schema; alles
            // Neue laeuft ueber den Migrator (protokolliert, genau einmal).
            try {
                $mig = \Core\Migrator::run($db);
                foreach ($mig['applied'] as $v) {
                    $results[] = "Migrator: $v angewendet";
                }
            } catch (\Throwable $e) {
                $results[] = "Migrator-Fehler: " . $e->getMessage();
            }

            Response::success($results, 'Migration abgeschlossen');
        }, [$authMiddleware, $adminMiddleware]);

        // KI-Modelle verwalten
        $router->get('/admin/models', function () use ($db) {
            $models = $db->query("SELECT * FROM ai_models ORDER BY sort_order, display_name");
            Response::view('admin/models', ['models' => $models]);
        }, [$authMiddleware, $adminMiddleware]);

        // Stile
        $router->get('/admin/styles', function () use ($db) {
            $styles = $db->query("SELECT * FROM styles ORDER BY sort_order, name");
            Response::view('admin/styles', ['styles' => $styles]);
        }, [$authMiddleware, $adminMiddleware]);

        // Regeltypen
        // Regel-Typen-Verwaltung wurde in /rules aufgeloest (Header-Button "Kategorien")
        $router->get('/admin/rule-types', function () {
            \Core\Router::redirect('/rules');
        }, [$authMiddleware, $adminMiddleware]);

        // Autorenprofile
        $router->get('/admin/authors', function () use ($db) {
            $authors = $db->query(
                "SELECT ap.*, c.name as customer_name
                 FROM author_profiles ap
                 LEFT JOIN customers c ON ap.customer_id = c.id
                 ORDER BY ap.name"
            );
            $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
            $models = $db->query("SELECT id, display_name, model_id, provider FROM ai_models WHERE is_active = 1 ORDER BY sort_order");
            Response::view('admin/authors', ['authors' => $authors, 'customers' => $customers, 'models' => $models]);
        }, [$authMiddleware, $adminMiddleware]);

        // Regelkategorien
        // Regel-Kategorien-Verwaltung wurde in /rules aufgeloest (Header-Button "Kategorien")
        $router->get('/admin/rule-categories', function () {
            \Core\Router::redirect('/rules');
        }, [$authMiddleware, $adminMiddleware]);

        // Verbrauchsstatistiken
        $router->get('/admin/usage', function () use ($db) {
            $stats = $this->getUsageStats($db);
            Response::view('admin/usage-stats', ['stats' => $stats]);
        }, [$authMiddleware, $adminMiddleware]);

        // LLM-Performance-Auswertung (lokal vs. Cloud)
        $router->get('/admin/llm-performance', function () use ($db) {
            $days = max(1, min(365, (int)($_GET['days'] ?? 30)));
            $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $byModel = $db->query(
                "SELECT model, provider,
                        COUNT(*) AS requests,
                        SUM(success = 1) AS ok,
                        SUM(success = 0) AS errors,
                        ROUND(SUM(success = 0) * 100.0 / COUNT(*), 1) AS error_rate,
                        ROUND(AVG(CASE WHEN success = 1 AND tokens_per_second IS NOT NULL THEN tokens_per_second END), 2) AS avg_tps,
                        ROUND(AVG(CASE WHEN ttft_ms IS NOT NULL THEN ttft_ms END)) AS avg_ttft_ms,
                        ROUND(AVG(CASE WHEN success = 1 THEN total_ms END)) AS avg_total_ms,
                        ROUND(AVG(CASE WHEN success = 1 THEN tokens_output END)) AS avg_out_tokens
                 FROM llm_request_log
                 WHERE created_at >= ?
                 GROUP BY model, provider
                 ORDER BY provider, model",
                [$since]
            );

            $perDayRaw = $db->query(
                "SELECT model, DATE(created_at) AS day, COUNT(*) AS requests
                 FROM llm_request_log
                 WHERE created_at >= ?
                 GROUP BY model, DATE(created_at)
                 ORDER BY day DESC, model",
                [$since]
            );

            $totals = $db->queryOne(
                "SELECT COUNT(*) AS requests,
                        SUM(success = 0) AS errors,
                        ROUND(AVG(CASE WHEN success = 1 AND tokens_per_second IS NOT NULL THEN tokens_per_second END), 2) AS avg_tps
                 FROM llm_request_log
                 WHERE created_at >= ?",
                [$since]
            );

            // Letzte Chat-Anfragen mit Detail (Prompt/Chunks/Antwort vorhanden)
            $recent = [];
            try {
                $recent = $db->query(
                    "SELECT l.id, l.created_at, l.provider, l.model, l.use_case,
                            l.tokens_input, l.tokens_output, l.total_ms, l.success,
                            d.id AS detail_id, d.user_message,
                            CHAR_LENGTH(COALESCE(d.system_prompt,'')) AS sys_len,
                            (d.rag_chunks IS NOT NULL AND d.rag_chunks <> '') AS has_chunks,
                            u.name AS user_name, c.name AS customer_name
                     FROM llm_request_log l
                     JOIN llm_request_detail d ON d.log_id = l.id
                     LEFT JOIN users u ON u.id = l.user_id
                     LEFT JOIN customers c ON c.id = l.customer_id
                     WHERE l.created_at >= ?
                     ORDER BY l.id DESC
                     LIMIT 100",
                    [$since]
                );
            } catch (\Exception $e) {
                // Detail-Tabelle evtl. noch nicht migriert — dann einfach leer lassen.
            }

            Response::view('admin/llm-performance', [
                'byModel'   => $byModel,
                'perDayRaw' => $perDayRaw,
                'totals'    => $totals ?: ['requests' => 0, 'errors' => 0, 'avg_tps' => null],
                'days'      => $days,
                'recent'    => $recent,
            ]);
        }, [$authMiddleware, $adminMiddleware]);

        // Detail einer einzelnen Anfrage: finaler System-Prompt, User-Frage, RAG-Chunks, Antwort.
        $router->get('/admin/llm-request-detail', function () use ($db) {
            $id = (int)($_GET['id'] ?? 0);
            $row = $db->queryOne(
                "SELECT l.*, d.system_prompt, d.user_message, d.response_text, d.rag_chunks, d.rerank_meta,
                        d.conversation_id, d.message_id,
                        u.name AS user_name, c.name AS customer_name
                 FROM llm_request_log l
                 JOIN llm_request_detail d ON d.log_id = l.id
                 LEFT JOIN users u ON u.id = l.user_id
                 LEFT JOIN customers c ON c.id = l.customer_id
                 WHERE l.id = ?",
                [$id]
            );
            if (!$row) {
                Response::view('admin/llm-request-detail', ['row' => null]);
                return;
            }
            $chunks = [];
            if (!empty($row['rag_chunks'])) {
                $decoded = json_decode($row['rag_chunks'], true);
                if (is_array($decoded)) $chunks = $decoded;
            }
            $rerank = null;
            if (!empty($row['rerank_meta'])) {
                $decoded = json_decode($row['rerank_meta'], true);
                if (is_array($decoded)) $rerank = $decoded;
            }
            Response::view('admin/llm-request-detail', ['row' => $row, 'chunks' => $chunks, 'rerank' => $rerank]);
        }, [$authMiddleware, $adminMiddleware]);

        // Wissens-Status (bge-m3 + Qdrant) — Health-Check, Backfill-Progress
        $router->get('/admin/wissen-status', function () {
            Response::view('admin/wissen-v2', []);
        }, [$authMiddleware, $adminMiddleware]);
        // Legacy-Alias (alte Bookmarks)
        $router->get('/admin/wissen-v2', function () {
            Response::view('admin/wissen-v2', []);
        }, [$authMiddleware, $adminMiddleware]);

        // Generierungs-Jobs
        $router->get('/admin/jobs', function () use ($db) {
            require_once SERVICES_PATH . '/JobQueue.php';
            $jobQueue = new \Services\JobQueue($db);

            $status = $_GET['status'] ?? null;
            $where = $status ? "WHERE j.status = ?" : "";
            $params = $status ? [$status] : [];

            $jobs = $db->query(
                "SELECT j.*, u.name as user_name, p.title as project_title
                 FROM generation_jobs j
                 JOIN users u ON j.user_id = u.id
                 LEFT JOIN projects p ON j.project_id = p.id
                 $where
                 ORDER BY j.created_at DESC
                 LIMIT 100",
                $params
            );

            $stats = $jobQueue->getStats();

            // Worker-Log laden (letzte 100 Zeilen) — open_basedir-tolerant
            $logFile = '/var/log/generation-worker.log';
            $workerLog = '';
            try {
                if (@is_file($logFile) && @is_readable($logFile)) {
                    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
                    if ($lines !== false) $workerLog = implode("\n", array_slice($lines, -100));
                }
            } catch (\Throwable $e) { /* open_basedir o.ä. — Log nicht zugreifbar, kein Fehler */ }

            Response::view('admin/jobs', [
                'jobs' => $jobs,
                'stats' => $stats,
                'workerLog' => $workerLog,
                'currentStatus' => $status
            ]);
        }, [$authMiddleware, $adminMiddleware]);

        // Regelvorschläge
        $router->get('/admin/suggestions', function () use ($db) {
            $suggestions = $db->query(
                "SELECT rs.*, c.name as customer_name
                 FROM rule_suggestions rs
                 JOIN customers c ON rs.customer_id = c.id
                 WHERE rs.status = 'pending'
                 ORDER BY rs.created_at DESC"
            );
            Response::view('admin/rule-suggestions', ['suggestions' => $suggestions]);
        }, [$authMiddleware, $adminMiddleware]);

        // KI-Coworker: Admin-Code-Tasks (Auftrags-Modus)
        $router->get('/admin/tasks', function () {
            Response::view('admin/tasks', []);
        }, [$authMiddleware, $adminMiddleware]);

        // KI-Coworker: Chat-Modus (freier Code-Agent)
        $router->get('/admin/coworker', function () {
            Response::view('admin/coworker', []);
        }, [$authMiddleware, $adminMiddleware]);

        // Backup-Status
        $router->get('/admin/backups', function () {
            Response::view('admin/backups', []);
        }, [$authMiddleware, $adminMiddleware]);

        // System-Log
        $router->get('/admin/system-log', function () {
            Response::view('admin/system-log', []);
        }, [$authMiddleware, $adminMiddleware]);

        // ====== Projektplanner ======
        $router->get('/admin/projektplanner', function () {
            Response::view('projektplanner/index', ['ppDeepLinkId' => null]);
        }, [$authMiddleware, $capMiddleware(CAP_PROJEKTPLANNER)]);
        // Deep-Link: einzelner Plan ueber URL aufrufbar
        $router->get('/admin/projektplanner/plan/{id}', function ($id) {
            Response::view('projektplanner/index', ['ppDeepLinkId' => (int)$id]);
        }, [$authMiddleware, $capMiddleware(CAP_PROJEKTPLANNER)]);
        $router->get('/admin/projektplanner/dashboard', function () use ($db) {
            $u = \Core\Auth::user();
            $u2 = $u ? $db->queryOne("SELECT name, abbreviation, nickname FROM users WHERE id = ?", [(int)$u['id']]) : null;
            Response::view('projektplanner/dashboard', ['ppCurrentUser' => $u2]);
        }, [$authMiddleware, $capMiddleware(CAP_PROJEKTPLANNER)]);
        // Legacy-Redirect: PP-Settings wurde in /admin/users + /admin/settings?tab=asana aufgeteilt
        // Team-Daten werden jetzt direkt pro User unter /admin/users/{id}/edit gepflegt.
        $router->get('/admin/projektplanner/settings', function () {
            header('Location: /admin/users?tab=benutzer', true, 301);
            exit;
        }, [$authMiddleware, $capMiddleware(CAP_PROJEKTPLANNER)]);
        $router->get('/admin/projektplanner/import', function () {
            Response::view('projektplanner/import', []);
        }, [$authMiddleware, $adminMiddleware]);

        // Public (kein Auth) — Sharelinks
        $router->get('/projektplan/{hash}', function ($hash) {
            Response::view('projektplanner/share', ['share_hash' => $hash]);
        });
        $router->get('/personen-aufgaben/{hash}', function ($hash) {
            Response::view('projektplanner/person', ['share_hash' => $hash]);
        });
        // Public (kein Auth) — Multi-Plan-Übersicht (kuratiert teilbar)
        $router->get('/projektplan-uebersicht/{hash}', function ($hash) {
            Response::view('projektplanner/multi-share', ['share_hash' => $hash]);
        });

        // ====== Site-Monitor (Website-Uptime-Checks) ======
        $router->get('/admin/site-monitor', function () {
            Response::view('admin/site-monitor', []);
        }, [$authMiddleware, $capMiddleware(CAP_SITE_MONITOR)]);

        // Druckfertiger Uptime-/Downtime-Report je Kunde (eigenständige Seite, KEIN main-Layout).
        $router->get('/admin/site-monitor/report', function () {
            Response::view('admin/site-monitor-report', [], null);
        }, [$authMiddleware, $capMiddleware(CAP_SITE_MONITOR)]);

        // ====== Firewall / IP-Sperren (fail2ban) ======
        $router->get('/admin/firewall', function () {
            Response::view('admin/firewall', []);
        }, [$authMiddleware, $capMiddleware(CAP_FIREWALL)]);

        // ====== Styleguide (Thoxan Corporate Design) ======
        // Quelle: Claude-Design-Projekt "Thoxan Styleguide Entwicklung".
        // Re-Import-Anleitung: docs/design-reference/thoxan-styleguide/README.md
        $router->get('/admin/styleguide', function () {
            Response::view('admin/styleguide', []);
        }, [$authMiddleware, $capMiddleware(CAP_STYLEGUIDE)]);

        // ====== Prompt-Insights (Chatverlauf-Analyse) ======
        $router->get('/admin/prompt-insights', function () {
            Response::view('admin/prompt-insights/index', []);
        }, [$authMiddleware, $capMiddleware(CAP_PROMPT_INSIGHTS)]);

        // ====== Transkription (Audio/Video → Wissen) ======
        $router->get('/admin/transkription', function () {
            Response::view('admin/transkription/index', []);
        }, [$authMiddleware, $capMiddleware(CAP_TRANSCRIPTION)]);

        // ====== CRM (Kontakte) ======
        $router->get('/crm', function () {
            // Dashboard war inhaltsleer → direkt auf Kontakte-Liste weiterleiten
            header('Location: /crm/kontakte', true, 302);
            exit;
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/kontakte', function () {
            Response::view('crm/kontakte', ['title' => 'Kontakte | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/kontakte/{id}', function ($id) {
            $embed = !empty($_GET['embed']);
            Response::view('crm/kontakt-detail', ['title' => 'Kontakt | CRM', 'kontaktId' => $id, 'embed' => $embed]);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/firmen', function () {
            Response::view('crm/firmen', ['title' => 'Firmen | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/firmen/{id}', function ($id) {
            $embed = !empty($_GET['embed']);
            Response::view('crm/firma-detail', ['title' => 'Firma | CRM', 'firmaId' => $id, 'embed' => $embed]);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/segmente', function () {
            Response::view('crm/segmente', ['title' => 'Segmente | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/listen', function () {
            Response::view('crm/listen', ['title' => 'Listen | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/tags', function () {
            Response::view('crm/tags', ['title' => 'Tags | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/dubletten', function () {
            // Legacy-URL — weiterleiten auf /crm/pflege?typ=dublette_*
            header('Location: /crm/pflege', true, 302);
            exit;
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/pflege', function () {
            Response::view('crm/pflege', ['title' => 'Pflege | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM)]);
        $router->get('/crm/migration', function () {
            Response::view('crm/migration', ['title' => 'Migration | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM_MIGRATION)]);
        $router->get('/crm/dsgvo', function () {
            Response::view('crm/dsgvo', ['title' => 'DSGVO | CRM']);
        }, [$authMiddleware, $capMiddleware(CAP_CRM_DSGVO)]);

        // ====== LAM-System (Linkaufbau-Management) ======
        $router->get('/lam', function () {
            Response::view('lam/dashboard', ['title' => 'LAM-System']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkquellen', function () {
            Response::view('lam/linkquellen', ['title' => 'Linkquellen | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkquellen/{id}', function ($id) {
            Response::view('lam/linkquellen-detail', ['title' => 'Linkquellen-Detail | LAM', 'domainId' => $id]);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkprofil', function () {
            Response::view('lam/linkprofil', ['title' => 'Linkprofil | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/anbieter', function () {
            Response::view('lam/anbieter', ['title' => 'Anbieter | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/anbieter/{id}', function ($id) {
            Response::view('lam/anbieter-detail', ['title' => 'Anbieter-Detail | LAM', 'anbieterId' => $id]);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkakquise', function () {
            Response::view('lam/linkakquise', ['title' => 'Linkakquise | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkoptionen', function () {
            Response::view('lam/linkoptionen', ['title' => 'Linkoptionen | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkoptionen/{id}', function ($id) {
            // Einzel-Detail-Ansicht abgelöst — alle Funktionen sind in der Vorschlagslisten-Ansicht.
            // Wir leiten auf die Vorschlagsliste mit Anker-Scroll zum richtigen Eintrag.
            $db = \Core\Database::getInstance();
            $listenId = $db->queryValue(
                "SELECT vorschlagsliste_id FROM lam_vorschlagsliste_eintraege WHERE id = ?",
                [$id]
            );
            if ($listenId) {
                header('Location: /lam/vorschlagslisten/' . urlencode($listenId) . '#eintrag-' . urlencode($id));
                exit;
            }
            header('Location: /lam/vorschlagslisten');
            exit;
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/vorschlagslisten', function () {
            Response::view('lam/vorschlagslisten', ['title' => 'Vorschlagslisten | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/vorschlagslisten/{id}', function ($id) {
            Response::view('lam/vorschlagslisten-detail', ['title' => 'Vorschlagsliste | LAM', 'listeId' => $id]);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/massnahmen', function () {
            Response::view('lam/massnahmen', ['title' => 'Maßnahmen | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/massnahmen/kanban', function () {
            Response::view('lam/massnahmen-kanban', ['title' => 'Maßnahmen-Kanban | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/massnahmen/{id}', function ($id) {
            Response::view('lam/massnahmen-detail', ['title' => 'Maßnahmen-Detail | LAM', 'massnahmeId' => $id]);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/auslagen', function () {
            Response::view('lam/auslagen', ['title' => 'Auslagen | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/monitoring', function () {
            Response::view('lam/monitoring', ['title' => 'Monitoring | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/korrespondenz', function () {
            Response::view('lam/korrespondenz', ['title' => 'Korrespondenz | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/tags', function () {
            Response::view('lam/tags', ['title' => 'Tags | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/aufgaben', function () {
            Response::view('lam/aufgaben', ['title' => 'Aufgaben | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/audit-log', function () {
            Response::view('lam/audit-log', ['title' => 'Audit-Log | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);

        // ===== Mail-Modul =====
        $router->get('/mail', function () {
            Response::view('mail/inbox', ['title' => 'Mail-Posteingang']);
        }, [$authMiddleware, $capMiddleware(CAP_MAIL)]);

        $router->get('/lam/ki-vorschlaege', function () {
            Response::view('lam/ki-vorschlaege', ['title' => 'KI-Vorschläge | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkziele', function () {
            Response::view('lam/linkziele', ['title' => 'Linkziele | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkprofil/snapshots', function () {
            Response::view('lam/linkprofil-snapshots', ['title' => 'Linkprofil-Snapshots | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkprofil/aufraeumen', function () {
            Response::view('lam/linkprofil-aufraeumen', ['title' => 'Aufräum-Modus | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkprofil/statistik', function () {
            Response::view('lam/linkprofil-statistik', ['title' => 'Linkprofil-Statistik | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/linkprofil/domain-wissen', function () {
            Response::view('lam/domain-wissensbasis', ['title' => 'Domain-Wissensbasis | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        $router->get('/lam/historien-import', function () {
            Response::view('lam/historien-import', ['title' => 'Historien-Import | LAM']);
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);
        // Sistrix-Einstellungen sind jetzt zentral unter /admin/settings?tab=sistrix.
        // Wir behalten den alten Pfad als Permanent-Redirect, damit Bookmarks weiter funktionieren.
        $router->get('/lam/sistrix-einstellungen', function () {
            header('Location: /admin/settings?tab=sistrix', true, 301);
            exit;
        }, [$authMiddleware, $capMiddleware(CAP_LAM)]);

        // Internes Feedback
        $router->get('/admin/feedback', function () use ($db) {
            $status = $_GET['status'] ?? 'all';
            $where = $status !== 'all' ? "WHERE f.status = ?" : "";
            $params = $status !== 'all' ? [$status] : [];

            $feedback = $db->query(
                "SELECT f.*, u.name as user_name, u.email as user_email,
                        r.name as resolver_name
                 FROM internal_feedback f
                 JOIN users u ON f.user_id = u.id
                 LEFT JOIN users r ON f.resolved_by = r.id
                 $where
                 ORDER BY
                    CASE f.status
                        WHEN 'new' THEN 1
                        WHEN 'in_progress' THEN 2
                        WHEN 'resolved' THEN 3
                        WHEN 'wont_fix' THEN 4
                    END,
                    f.created_at DESC",
                $params
            );

            // Verknuepfte Maßnahmen je Feedback anhaengen (im Ticket dokumentiert)
            try {
                $links = $db->query(
                    "SELECT l.feedback_id, m.id, m.title, m.status, m.priority
                     FROM feedback_measure_links l
                     JOIN feedback_measures m ON m.id = l.measure_id
                     ORDER BY m.created_at DESC"
                );
                $byFeedback = [];
                foreach ($links as $r) {
                    $byFeedback[(int)$r['feedback_id']][] = [
                        'id' => (int)$r['id'], 'title' => $r['title'],
                        'status' => $r['status'], 'priority' => $r['priority'],
                    ];
                }
                foreach ($feedback as &$f) { $f['measures'] = $byFeedback[(int)$f['id']] ?? []; }
                unset($f);
            } catch (\Throwable $e) {
                foreach ($feedback as &$f) { $f['measures'] = []; }
                unset($f);
            }

            // Medien je Feedback (Screenshot + Video nebeneinander); Fallback auf Legacy-Spalte
            try {
                $mediaRows = $db->query("SELECT feedback_id, media_type, media_path FROM feedback_media ORDER BY id");
                $byFb = [];
                foreach ($mediaRows as $r) {
                    $byFb[(int)$r['feedback_id']][] = ['type' => $r['media_type'], 'path' => $r['media_path']];
                }
                foreach ($feedback as &$f) {
                    $m = $byFb[(int)$f['id']] ?? [];
                    if (!$m && !empty($f['media_path'])) {
                        $m = [['type' => $f['media_type'] ?: 'screenshot', 'path' => $f['media_path']]];
                    }
                    $f['media'] = $m;
                }
                unset($f);
            } catch (\Throwable $e) {
                foreach ($feedback as &$f) {
                    $f['media'] = (!empty($f['media_path'])) ? [['type' => $f['media_type'] ?: 'screenshot', 'path' => $f['media_path']]] : [];
                }
                unset($f);
            }

            $stats = [
                'new' => $db->queryValue("SELECT COUNT(*) FROM internal_feedback WHERE status = 'new'"),
                'in_progress' => $db->queryValue("SELECT COUNT(*) FROM internal_feedback WHERE status = 'in_progress'"),
                'resolved' => $db->queryValue("SELECT COUNT(*) FROM internal_feedback WHERE status = 'resolved'"),
                'total' => $db->queryValue("SELECT COUNT(*) FROM internal_feedback")
            ];

            // Maßnahmen (To-dos) mit ihren verknuepften Feedbacks fuer dieselbe Cockpit-Ansicht
            require_once SERVICES_PATH . '/FeedbackMeasureService.php';
            $mSvc = new \Services\FeedbackMeasureService($db);
            $measures = $mSvc->listMeasures('all');
            try {
                $mlinks = $db->query(
                    "SELECT l.measure_id, f.id, f.title, f.description, f.status
                     FROM feedback_measure_links l
                     JOIN internal_feedback f ON f.id = l.feedback_id
                     ORDER BY f.created_at DESC"
                );
                $byMeasure = [];
                foreach ($mlinks as $r) {
                    $byMeasure[(int)$r['measure_id']][] = [
                        'id' => (int)$r['id'], 'title' => $r['title'],
                        'description' => $r['description'], 'status' => $r['status'],
                    ];
                }
                foreach ($measures as &$mm) { $mm['feedbacks'] = $byMeasure[(int)$mm['id']] ?? []; }
                unset($mm);
            } catch (\Throwable $e) {
                foreach ($measures as &$mm) { $mm['feedbacks'] = []; }
                unset($mm);
            }

            Response::view('admin/feedback', [
                'feedback' => $feedback,
                'stats' => $stats,
                'currentStatus' => $status,
                'measures' => $measures,
            ]);
        }, [$authMiddleware, $adminMiddleware]);

        // ===== Maßnahmen: eigene Seite entfaellt, ist ins Feedback-Cockpit integriert =====
        // Alte Links (z.B. die woechentliche E-Mail-Routine) auf das Cockpit umleiten.
        $router->get('/admin/measures', function () {
            $st = $_GET['status'] ?? 'offen';
            header('Location: /admin/feedback?ms=' . urlencode($st), true, 302);
            exit;
        }, [$authMiddleware, $adminMiddleware]);

        // ===== API-Routen werden separat geladen =====
    }

    /**
     * Dashboard-Statistiken
     */
    private function getDashboardStats(Database $db): array
    {
        $userId = Auth::id();
        $isAdmin = Auth::isAdmin();

        try {
            $knowledgeDocs = (int) $db->queryValue("SELECT COUNT(*) FROM knowledge_documents");
        } catch (\Exception $e) { $knowledgeDocs = 0; }

        try {
            $knowledgeChunks = (int) $db->queryValue("SELECT COUNT(*) FROM knowledge_chunks");
        } catch (\Exception $e) { $knowledgeChunks = 0; }

        try {
            $customers = (int) $db->queryValue("SELECT COUNT(*) FROM customers WHERE is_active = 1");
        } catch (\Exception $e) { $customers = 0; }

        try {
            $chats = (int) $db->queryValue(
                "SELECT COUNT(*) FROM chat_conversations WHERE is_private = 0 OR user_id = ?",
                [$userId]
            );
        } catch (\Exception $e) { $chats = 0; }

        try {
            $asanaTasks = (int) $db->queryValue(
                "SELECT COUNT(*) FROM knowledge_documents WHERE source_type = 'asana' AND external_id LIKE 'task:%'"
            );
        } catch (\Exception $e) { $asanaTasks = 0; }

        try {
            $apiCallsToday = (int) $db->queryValue(
                "SELECT COUNT(*) FROM usage_logs WHERE DATE(created_at) = CURDATE()"
            );
        } catch (\Exception $e) { $apiCallsToday = 0; }

        try {
            $myChats = (int) $db->queryValue(
                "SELECT COUNT(*) FROM chat_conversations WHERE user_id = ?",
                [$userId]
            );
        } catch (\Exception $e) { $myChats = 0; }

        try {
            $guidelinesActive = (int) $db->queryValue("SELECT COUNT(*) FROM guidelines WHERE is_active = 1");
        } catch (\Exception $e) { $guidelinesActive = 0; }

        return [
            'knowledge_docs' => $knowledgeDocs,
            'knowledge_chunks' => $knowledgeChunks,
            'customers' => $customers,
            'chats' => $chats,
            'my_chats' => $myChats,
            'asana_tasks' => $asanaTasks,
            'api_calls_today' => $apiCallsToday,
            'guidelines_active' => $guidelinesActive,
        ];
    }

    /**
     * Projekte laden
     */
    private function getProjects(Database $db, ?int $customerId): array
    {
        // Prüfen ob abbreviation-Spalte existiert
        $hasAbbreviation = false;
        try {
            $columns = $db->query("SHOW COLUMNS FROM customers LIKE 'abbreviation'");
            $hasAbbreviation = !empty($columns);
        } catch (\Exception $e) {
            // Ignorieren
        }

        $abbreviationSelect = $hasAbbreviation ? ', c.abbreviation as customer_abbreviation' : ', NULL as customer_abbreviation';

        if ($customerId) {
            return $db->query(
                "SELECT p.*, u.name as author_name{$abbreviationSelect}
                 FROM projects p
                 JOIN users u ON p.created_by = u.id
                 JOIN customers c ON p.customer_id = c.id
                 WHERE p.customer_id = ?
                 ORDER BY p.updated_at DESC",
                [$customerId]
            );
        }

        return $db->query(
            "SELECT p.*, u.name as author_name, c.name as customer_name{$abbreviationSelect}
             FROM projects p
             JOIN users u ON p.created_by = u.id
             JOIN customers c ON p.customer_id = c.id
             ORDER BY p.updated_at DESC"
        );
    }

    /**
     * Einzelnes Projekt laden
     */
    private function getProject(Database $db, int $id): ?array
    {
        $project = $db->queryOne(
            "SELECT p.*, c.name as customer_name
             FROM projects p
             JOIN customers c ON p.customer_id = c.id
             WHERE p.id = ?",
            [$id]
        );

        if (!$project) {
            return null;
        }

        // Zugriff prüfen
        if (!Auth::isAdmin() && $project['customer_id'] !== Auth::customerId()) {
            return null;
        }

        // Aktuelle (neueste) Version laden
        $version = $db->queryOne(
            "SELECT * FROM article_versions
             WHERE project_id = ?
             ORDER BY version_number DESC
             LIMIT 1",
            [$id]
        );

        $project['version'] = $version;
        $project['current_version'] = $version['version_number'] ?? 1;

        // Abschnitte laden
        if ($version) {
            $project['sections'] = $db->query(
                "SELECT * FROM article_sections
                 WHERE article_version_id = ?
                 ORDER BY section_order",
                [$version['id']]
            );
        }

        return $project;
    }

    /**
     * Regeln laden
     */
    private function getRules(Database $db, ?int $customerId): array
    {
        if ($customerId) {
            return $db->query(
                "SELECT * FROM rules
                 WHERE customer_id = ? OR customer_id IS NULL
                 ORDER BY priority DESC, name",
                [$customerId]
            );
        }

        return $db->query(
            "SELECT r.*, c.name as customer_name
             FROM rules r
             LEFT JOIN customers c ON r.customer_id = c.id
             ORDER BY r.priority DESC, r.name"
        );
    }

    /**
     * Verbrauchsstatistiken
     */
    private function getUsageStats(Database $db): array
    {
        $currentMonth = $_GET['month'] ?? date('Y-m');
        // Sicherheit: YYYY-MM Format erzwingen
        if (!preg_match('/^\d{4}-\d{2}$/', $currentMonth)) $currentMonth = date('Y-m');

        // Direkt aus usage_logs aggregieren — usage_summary wird nicht mehr genutzt
        // (dort fehlten alle NULL-customer-Logs).
        return [
            'month' => $currentMonth,
            'totals' => $db->queryOne(
                "SELECT
                    COUNT(*) as total_calls,
                    COALESCE(SUM(tokens_input), 0) as total_input,
                    COALESCE(SUM(tokens_output), 0) as total_output,
                    COALESCE(SUM(words_generated), 0) as total_words,
                    COALESCE(SUM(cost_estimate), 0) as total_cost
                 FROM usage_logs
                 WHERE DATE_FORMAT(created_at, '%Y-%m') = ?",
                [$currentMonth]
            ),
            // Pro Kunde — inkl. "Ohne Kunde"
            'by_customer' => $db->query(
                "SELECT
                    ul.customer_id,
                    COALESCE(c.name, 'Ohne Kunde / Projektuebergreifend') as customer_name,
                    COUNT(*) as total_calls,
                    COALESCE(SUM(ul.tokens_input), 0) as total_tokens_input,
                    COALESCE(SUM(ul.tokens_output), 0) as total_tokens_output,
                    COALESCE(SUM(ul.words_generated), 0) as total_words_generated,
                    COALESCE(SUM(ul.cost_estimate), 0) as total_cost_estimate
                 FROM usage_logs ul
                 LEFT JOIN customers c ON c.id = ul.customer_id
                 WHERE DATE_FORMAT(ul.created_at, '%Y-%m') = ?
                 GROUP BY ul.customer_id, c.name
                 ORDER BY total_cost_estimate DESC, total_calls DESC",
                [$currentMonth]
            ),
            // Pro Modell
            'by_model' => $db->query(
                "SELECT model_used,
                        COUNT(*) as total_calls,
                        COALESCE(SUM(tokens_input), 0) as total_tokens_input,
                        COALESCE(SUM(tokens_output), 0) as total_tokens_output,
                        COALESCE(SUM(words_generated), 0) as total_words_generated,
                        COALESCE(SUM(cost_estimate), 0) as total_cost_estimate
                 FROM usage_logs
                 WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                 GROUP BY model_used
                 ORDER BY total_cost_estimate DESC",
                [$currentMonth]
            ),
            // Pro Aktion-Typ (chat / generation / embedding / etc.)
            'by_action' => $db->query(
                "SELECT action_type,
                        COUNT(*) as total_calls,
                        COALESCE(SUM(tokens_input), 0) as total_tokens_input,
                        COALESCE(SUM(tokens_output), 0) as total_tokens_output,
                        COALESCE(SUM(cost_estimate), 0) as total_cost_estimate
                 FROM usage_logs
                 WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                 GROUP BY action_type
                 ORDER BY total_calls DESC",
                [$currentMonth]
            ),
            // Top-User
            'by_user' => $db->query(
                "SELECT u.name, u.email,
                        COUNT(*) as total_calls,
                        COALESCE(SUM(ul.cost_estimate), 0) as total_cost_estimate
                 FROM usage_logs ul
                 JOIN users u ON u.id = ul.user_id
                 WHERE DATE_FORMAT(ul.created_at, '%Y-%m') = ?
                 GROUP BY u.id, u.name, u.email
                 ORDER BY total_cost_estimate DESC
                 LIMIT 10",
                [$currentMonth]
            ),
        ];
    }

    /**
     * Datenbank-Instanz
     */
    public function getDb(): Database
    {
        return $this->db;
    }

    /**
     * Konfiguration
     */
    public function getConfig(?string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Installiert?
     */
    public function isInstalled(): bool
    {
        return $this->installed;
    }
}
