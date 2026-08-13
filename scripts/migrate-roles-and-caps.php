<?php
/**
 * Migrations-Skript: Rollen-Hierarchie auf 4 Stufen + Capabilities einfuehren.
 *
 * Idempotent: kann mehrfach laufen, ohne Schaden anzurichten.
 *
 *   - users.role ENUM erweitern auf (admin, manager, user, guest)
 *   - bestehende 'editor'-Rollen migrieren auf 'user'
 *   - Tabelle user_capabilities anlegen (user_id, capability)
 *   - Rollen-Umstellung der bestehenden 10 User
 *   - Default-Caps pro Rolle in user_capabilities eintragen
 *
 * Aufruf:
 *   php scripts/migrate-roles-and-caps.php
 *   php scripts/migrate-roles-and-caps.php --dry-run
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

$dryRun = in_array('--dry-run', $argv ?? [], true);
$db = \Core\Database::getInstance();

echo "=== Phase 1: Schema-Migration ===\n";

// 1a) ENUM erweitern
$col = $db->queryOne("SHOW COLUMNS FROM users LIKE 'role'");
$type = $col['Type'] ?? '';
echo "Aktuelles ENUM:  $type\n";
$ziel = "enum('admin','manager','user','guest')";
if (stripos($type, "'user'") === false || stripos($type, "'guest'") === false) {
    $sql = "ALTER TABLE users MODIFY COLUMN role $ziel NOT NULL DEFAULT 'user'";
    if ($dryRun) {
        echo "WUERDE ausfuehren: $sql\n";
    } else {
        $db->execute($sql);
        echo "ENUM erweitert auf: $ziel\n";
    }
} else {
    echo "ENUM bereits aktuell — uebersprungen.\n";
}

// 1b) Bestehende 'editor' → 'user'
$editorCount = (int)$db->queryValue("SELECT COUNT(*) FROM users WHERE role = 'editor'");
if ($editorCount > 0) {
    if ($dryRun) {
        echo "WUERDE $editorCount editor-User auf 'user' migrieren\n";
    } else {
        $db->execute("UPDATE users SET role = 'user' WHERE role = 'editor'");
        echo "$editorCount editor-User auf 'user' migriert.\n";
    }
} else {
    echo "Kein 'editor'-User gefunden — uebersprungen.\n";
}

// 1c) Tabelle user_capabilities
$tablesQuery = $db->query("SHOW TABLES LIKE 'user_capabilities'");
if (empty($tablesQuery)) {
    $createSql = "
        CREATE TABLE user_capabilities (
            user_id INT NOT NULL,
            capability VARCHAR(64) NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            granted_by INT NULL,
            PRIMARY KEY (user_id, capability),
            INDEX (capability),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($dryRun) {
        echo "WUERDE Tabelle user_capabilities anlegen\n";
    } else {
        $db->execute($createSql);
        echo "Tabelle user_capabilities angelegt.\n";
    }
} else {
    echo "Tabelle user_capabilities existiert bereits — uebersprungen.\n";
}

echo "\n=== Phase 2: Rollen-Umstellung der bestehenden User ===\n";

// Gewuenschte Rollen-Verteilung (Stand 21.05.2026, mit User abgestimmt):
$gewuenschteRollen = [
    'admin@thoxan-dev.de'                  => ROLE_ADMIN,
    'thomas.kilian@thoxan.com'             => ROLE_ADMIN,
    'test@thoxan-dev.de'                   => ROLE_GUEST,
    'benjamin.koehler@thoxan.com'          => ROLE_MANAGER,
    'ralf.bohnert@thoxan.com'              => ROLE_MANAGER,
    'gabriele.bohnert@thoxan.com'          => ROLE_MANAGER,
    'daniel.kilian@wittekind-moebel.de'    => ROLE_MANAGER,
    'christian.deuschle@thoxan.com'        => ROLE_MANAGER,
    'michaela-warning@gmx.de'              => ROLE_MANAGER,
    'thomas.kilian@wittekind-moebel.de'    => ROLE_USER,
];

$updates = 0;
foreach ($gewuenschteRollen as $email => $zielRolle) {
    $existing = $db->queryOne("SELECT id, role FROM users WHERE email = ?", [$email]);
    if (!$existing) {
        echo sprintf("  · %-40s NICHT GEFUNDEN\n", $email);
        continue;
    }
    if ($existing['role'] === $zielRolle) {
        echo sprintf("  ✓ %-40s schon %s\n", $email, $zielRolle);
        continue;
    }
    if ($dryRun) {
        echo sprintf("  → %-40s %s → %s (WUERDE)\n", $email, $existing['role'], $zielRolle);
    } else {
        $db->execute("UPDATE users SET role = ? WHERE id = ?", [$zielRolle, (int)$existing['id']]);
        echo sprintf("  → %-40s %s → %s\n", $email, $existing['role'], $zielRolle);
        $updates++;
    }
}

echo "\n=== Phase 3: Default-Capabilities pro Rolle eintragen ===\n";

// In dry-run kann Phase 3 die Tabelle noch nicht abfragen (existiert noch nicht).
// Wir zeigen nur die geplanten Defaults.
$hasTable = !empty($db->query("SHOW TABLES LIKE 'user_capabilities'"));
if (!$hasTable && $dryRun) {
    echo "Dry-Run: Tabelle user_capabilities existiert noch nicht. ";
    echo "Im scharfen Lauf werden die Default-Caps pro Rolle gesetzt.\n";
    echo "------------------------------------------------------------\n";
    echo "Rollen geaendert (geplant): siehe Phase 2 oben\n";
    echo "Dry-Run — nichts geschrieben. Ohne --dry-run scharf laufen lassen.\n";
    exit(0);
}


// Default-Caps pro Rolle (zentral hier, parallel zu Auth::DEFAULT_CAPS — bei Aenderung beides updaten)
$defaultCaps = [
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

$users = $db->query("SELECT id, email, role FROM users WHERE is_active = 1 ORDER BY id");
$capsTotal = 0;
foreach ($users as $u) {
    $rolle = $u['role'];
    $caps = $defaultCaps[$rolle] ?? [];
    // Schon vorhandene Caps des Users
    $existing = [];
    $rows = $db->query("SELECT capability FROM user_capabilities WHERE user_id = ?", [(int)$u['id']]);
    foreach ($rows as $r) $existing[] = $r['capability'];

    $neueCaps = array_diff($caps, $existing);
    if (empty($neueCaps)) {
        echo sprintf("  ✓ %-40s (%s) — alle Defaults bereits gesetzt\n", $u['email'], $rolle);
        continue;
    }
    foreach ($neueCaps as $cap) {
        if ($dryRun) {
            echo sprintf("  → %-40s (%s) +%s\n", $u['email'], $rolle, $cap);
        } else {
            $db->insert('user_capabilities', [
                'user_id' => (int)$u['id'],
                'capability' => $cap,
                'granted_by' => null,
            ]);
            $capsTotal++;
        }
    }
    if (!$dryRun) {
        echo sprintf("  → %-40s (%s) %d Caps gesetzt\n", $u['email'], $rolle, count($neueCaps));
    }
}

echo "\n";
echo "------------------------------------------------------------\n";
echo "Rollen geaendert:          $updates\n";
echo "Capability-Eintraege neu:  $capsTotal\n";
echo "------------------------------------------------------------\n";
if ($dryRun) echo "Dry-Run — nichts geschrieben. Ohne --dry-run scharf laufen lassen.\n";
