<?php
/**
 * Migrations-Skript: role_customers-Tabelle anlegen.
 *
 * Diese Tabelle erlaubt, einen Kunden fuer eine ganze Rolle freizuschalten —
 * z.B. „alle Manager sehen FRYKA". Wirkt zusaetzlich zur individuellen
 * Zuweisung in user_customers.
 *
 * Idempotent.
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

echo "=== Schema: role_customers ===\n";
$exists = !empty($db->query("SHOW TABLES LIKE 'role_customers'"));
if (!$exists) {
    $db->execute("
        CREATE TABLE role_customers (
            role VARCHAR(32) NOT NULL,
            customer_id INT NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            granted_by INT NULL,
            PRIMARY KEY (role, customer_id),
            INDEX (role),
            INDEX (customer_id),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Tabelle role_customers angelegt.\n";
} else {
    echo "Tabelle role_customers existiert bereits — uebersprungen.\n";
}

// Sanity: Wie viele User, Rollen, Kunden gibts?
$counts = [
    'customers' => (int)$db->queryValue("SELECT COUNT(*) FROM customers WHERE is_active = 1"),
    'users'     => (int)$db->queryValue("SELECT COUNT(*) FROM users WHERE is_active = 1"),
    'user_customers_direct' => (int)$db->queryValue("SELECT COUNT(*) FROM user_customers"),
    'role_customers' => (int)$db->queryValue("SELECT COUNT(*) FROM role_customers"),
];
echo "\nAktuelle Lage:\n";
foreach ($counts as $k => $v) echo sprintf("  %-25s %d\n", $k, $v);
