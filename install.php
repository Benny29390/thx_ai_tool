<?php
/**
 * Installer-Wizard
 */

// Konstanten laden falls nicht bereits geladen
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/config/constants.php';
}

class Installer
{
    private int $step = 1;
    private array $errors = [];
    private array $data = [];

    public function run(): void
    {
        session_start();

        // Bereits installiert?
        if (file_exists(CONFIG_PATH . '/config.php')) {
            header('Location: /');
            exit;
        }

        // Schritt ermitteln
        $this->step = (int) ($_GET['step'] ?? $_SESSION['install_step'] ?? 1);
        $this->data = $_SESSION['install_data'] ?? [];

        // POST verarbeiten
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processStep();
        }

        // View anzeigen
        $this->render();
    }

    private function processStep(): void
    {
        switch ($this->step) {
            case 1:
                // Voraussetzungen - nur weiter wenn OK
                if ($this->checkRequirements()) {
                    $this->nextStep();
                }
                break;

            case 2:
                // Datenbank-Konfiguration
                $this->data['db'] = [
                    'host' => trim($_POST['db_host'] ?? 'localhost'),
                    'port' => (int) ($_POST['db_port'] ?? 3306),
                    'name' => trim($_POST['db_name'] ?? ''),
                    'user' => trim($_POST['db_user'] ?? ''),
                    'pass' => $_POST['db_pass'] ?? '',
                    'charset' => 'utf8mb4'
                ];

                $result = $this->testDatabase();
                if ($result['success']) {
                    $this->saveData();
                    $this->nextStep();
                } else {
                    $this->errors[] = $result['message'];
                }
                break;

            case 3:
                // Admin-Account
                $this->data['admin'] = [
                    'email' => trim($_POST['admin_email'] ?? ''),
                    'password' => $_POST['admin_password'] ?? '',
                    'name' => trim($_POST['admin_name'] ?? '')
                ];

                $errors = $this->validateAdmin();
                if (empty($errors)) {
                    $this->saveData();
                    $this->nextStep();
                } else {
                    $this->errors = $errors;
                }
                break;

            case 4:
                // API-Keys
                $this->data['ai'] = [
                    'openai_key' => trim($_POST['openai_key'] ?? ''),
                    'anthropic_key' => trim($_POST['anthropic_key'] ?? ''),
                    'default_model' => $_POST['default_model'] ?? 'gpt-4'
                ];
                $this->saveData();
                $this->nextStep();
                break;

            case 5:
                // Installation abschliessen
                if ($this->finishInstallation()) {
                    session_destroy();
                    header('Location: /login');
                    exit;
                }
                break;
        }
    }

    private function checkRequirements(): bool
    {
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'config_writable' => is_writable(CONFIG_PATH),
            'storage_writable' => is_writable(STORAGE_PATH)
        ];

        return !in_array(false, $requirements, true);
    }

    private function testDatabase(): array
    {
        require_once CORE_PATH . '/Database.php';
        return \Core\Database::testConnection($this->data['db']);
    }

    private function validateAdmin(): array
    {
        $errors = [];

        if (empty($this->data['admin']['email'])) {
            $errors[] = 'E-Mail ist erforderlich';
        } elseif (!filter_var($this->data['admin']['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungueltige E-Mail-Adresse';
        }

        if (empty($this->data['admin']['name'])) {
            $errors[] = 'Name ist erforderlich';
        }

        $password = $this->data['admin']['password'];
        if (strlen($password) < 8) {
            $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein';
        }

        return $errors;
    }

    private function finishInstallation(): bool
    {
        try {
            // Datenbank erstellen/verbinden
            require_once CORE_PATH . '/Database.php';
            \Core\Database::createDatabase($this->data['db']);

            $db = \Core\Database::getInstance($this->data['db']);

            // Schema ausfuehren
            $schema = file_get_contents(ROOT_PATH . '/sql/schema.sql');
            $statements = array_filter(
                array_map('trim', explode(';', $schema)),
                fn($s) => !empty($s) && strpos($s, '--') !== 0
            );

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $db->execute($statement);
                }
            }

            // Admin erstellen
            $passwordHash = password_hash($this->data['admin']['password'], PASSWORD_DEFAULT);
            $db->insert('users', [
                'email' => $this->data['admin']['email'],
                'password_hash' => $passwordHash,
                'name' => $this->data['admin']['name'],
                'role' => 'admin',
                'is_active' => 1
            ]);

            // API-Keys speichern
            if (!empty($this->data['ai']['openai_key'])) {
                $db->execute(
                    "UPDATE settings SET setting_value = ? WHERE setting_key = 'openai_api_key'",
                    [$this->data['ai']['openai_key']]
                );
            }
            if (!empty($this->data['ai']['anthropic_key'])) {
                $db->execute(
                    "UPDATE settings SET setting_value = ? WHERE setting_key = 'anthropic_api_key'",
                    [$this->data['ai']['anthropic_key']]
                );
            }
            $db->execute(
                "UPDATE settings SET setting_value = ? WHERE setting_key = 'default_model'",
                [$this->data['ai']['default_model']]
            );

            // config.php erstellen
            $config = [
                'db' => $this->data['db'],
                'app' => [
                    'debug' => true,
                    'url' => $this->getBaseUrl(),
                    'timezone' => 'Europe/Berlin',
                    // 32-Byte-Schluessel (64 Hex-Zeichen) fuer AES-256-GCM der Settings-Secrets.
                    // WIRD EINMALIG BEI DER INSTALLATION ERZEUGT und darf danach NIE geaendert
                    // werden, sonst sind alle verschluesselten Secrets (API-Keys, SMTP-Passwort,
                    // OAuth-Token) unlesbar. config.php ist gitignored und wird bei Updates nie
                    // ueberschrieben, damit dieser Schluessel erhalten bleibt.
                    'encryption_key' => bin2hex(random_bytes(32)),
                ],
                'ai' => $this->data['ai'],
                'session' => [
                    'name' => 'ki_tool_session',
                    'lifetime' => 86400
                ]
            ];

            $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
            file_put_contents(CONFIG_PATH . '/config.php', $configContent);

            return true;

        } catch (\Exception $e) {
            $this->errors[] = 'Installationsfehler: ' . $e->getMessage();
            return false;
        }
    }

    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'];
    }

    private function nextStep(): void
    {
        $this->step++;
        $_SESSION['install_step'] = $this->step;
        header('Location: /install.php?step=' . $this->step);
        exit;
    }

    private function saveData(): void
    {
        $_SESSION['install_data'] = $this->data;
    }

    private function render(): void
    {
        $step = $this->step;
        $errors = $this->errors;
        $data = $this->data;

        // Layout laden
        include VIEWS_PATH . '/layouts/install.php';
    }

    public function getStepContent(): void
    {
        $step = $this->step;
        $errors = $this->errors;
        $data = $this->data;

        $viewFile = VIEWS_PATH . '/install/step' . $step . '.php';
        if (file_exists($viewFile)) {
            include $viewFile;
        }
    }
}

// Direkter Aufruf
if (basename($_SERVER['SCRIPT_FILENAME']) === 'install.php') {
    $installer = new Installer();
    $installer->run();
}
