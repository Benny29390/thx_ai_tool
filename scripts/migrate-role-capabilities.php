<?php
/**
 * Migrations-Skript: role_capabilities-Tabelle anlegen + initial mit den
 * aktuellen Defaults aus Auth::DEFAULT_CAPS befuellen.
 *
 * Idempotent — kann mehrfach laufen.
 */
require_once __DIR__ . '/../config/constants.php';
spl_autoload_register(function ($class) {
    $namespaces = ['Core\\' => 'core/', 'Models\\' => 'models/', 'Services\\' => 'services/'];
    foreach ($namespaces as $ns => $dir) {
        if (strpos($class, $ns) === 0) {
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

echo "=== Schema: role_capabilities ===\n";
$exists = !empty($db->query("SHOW TABLES LIKE 'role_capabilities'"));
if (!$exists) {
    $db->execute("
        CREATE TABLE role_capabilities (
            role VARCHAR(32) NOT NULL,
            capability VARCHAR(64) NOT NULL,
            PRIMARY KEY (role, capability),
            INDEX (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Tabelle role_capabilities angelegt.\n";
} else {
    echo "Tabelle role_capabilities existiert bereits.\n";
}

echo "\n=== Seed: Default-Caps pro Rolle ===\n";
// Identisch zur Auth::DEFAULT_CAPS-Konstante — wird damit nun in der DB hinterlegt.
$defaults = [
    ROLE_ADMIN => [
        CAP_CHAT, CAP_ARTIFACTS, CAP_KNOWLEDGE, CAP_COWORKER, CAP_LAM,
        CAP_PROJEKTPLANNER, CAP_CUSTOMERS_VIEW, CAP_CUSTOMERS_MANAGE,
        CAP_USERS_MANAGE, CAP_SETTINGS_MANAGE,
    ],
    ROLE_MANAGER => [
        CAP_CHAT, CAP_ARTIFACTS, CAP_KNOWLEDGE, CAP_COWORKER, CAP_LAM,
        CAP_PROJEKTPLANNER, CAP_CUSTOMERS_VIEW, CAP_CUSTOMERS_MANAGE,
    ],
    ROLE_USER => [
        CAP_CHAT, CAP_ARTIFACTS, CAP_KNOWLEDGE, CAP_COWORKER,
        CAP_PROJEKTPLANNER, CAP_CUSTOMERS_VIEW,
    ],
    ROLE_GUEST => [
        CAP_CUSTOMERS_VIEW,
    ],
];

foreach ($defaults as $role => $caps) {
    $existingCaps = array_column(
        $db->query("SELECT capability FROM role_capabilities WHERE role = ?", [$role]),
        'capability'
    );
    if (!empty($existingCaps)) {
        echo sprintf("  ✓ %-8s schon konfiguriert (%d Caps) — uebersprungen\n", $role, count($existingCaps));
        continue;
    }
    foreach ($caps as $cap) {
        $db->insert('role_capabilities', ['role' => $role, 'capability' => $cap]);
    }
    echo sprintf("  → %-8s %d Caps eingetragen\n", $role, count($caps));
}

echo "\nFertig.\n";
