<?php
/**
 * Migrations-Skript fuer die Sicherheits-Roadmap (Punkte 2, 3 aus docs/benutzer-rechte-roadmap.md):
 *
 *   - login_attempts          (Rate-Limit)
 *   - permission_audit_log    (Audit-Log fuer Rechte-Aenderungen)
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

echo "=== login_attempts ===\n";
if (empty($db->query("SHOW TABLES LIKE 'login_attempts'"))) {
    $db->execute("
        CREATE TABLE login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            ip VARCHAR(45) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_time (email, occurred_at),
            INDEX idx_ip_time (ip, occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  → angelegt.\n";
} else {
    echo "  ✓ existiert.\n";
}

echo "\n=== permission_audit_log ===\n";
if (empty($db->query("SHOW TABLES LIKE 'permission_audit_log'"))) {
    $db->execute("
        CREATE TABLE permission_audit_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            actor_user_id INT NULL,
            target_type VARCHAR(32) NOT NULL,
            target_key VARCHAR(64) NOT NULL,
            action VARCHAR(48) NOT NULL,
            diff JSON NULL,
            INDEX idx_target (target_type, target_key, occurred_at),
            INDEX idx_actor (actor_user_id, occurred_at),
            INDEX idx_action (action, occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  → angelegt.\n";
} else {
    echo "  ✓ existiert.\n";
}

echo "\nFertig.\n";
