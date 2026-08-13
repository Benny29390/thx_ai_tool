<?php
/**
 * Inaktivitaets-Job: deaktiviert Manager/User/Guest, die seit X Tagen
 * (default 30) nicht eingeloggt waren. Admins werden NIE deaktiviert.
 *
 * Auch User die noch nie eingeloggt waren (last_login NULL) UND aelter
 * als X Tage angelegt sind, werden deaktiviert — bei nicht eingeloesten
 * Einladungen aufraeumen.
 *
 * Aufruf (Cron, taeglich):
 *   0 3 * * * php /var/www/scripts/deactivate-stale-users.php >> /var/log/thx-stale-users.log 2>&1
 *
 * Optionen:
 *   --days=30       Anzahl Tage Inaktivitaet (default 30)
 *   --dry-run       nur listen, nicht aendern
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

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$days   = 30;
foreach ($args as $a) {
    if (preg_match('/^--days=(\d+)$/', $a, $m)) $days = (int)$m[1];
}
$days = max(1, $days);

echo "=== Inaktivitaets-Job (Schwelle: $days Tage) ===\n";
echo $dryRun ? "[DRY-RUN — nichts wird geaendert]\n" : "";
echo "\n";

// Kandidaten: aktive non-admin User mit zu altem Login (oder NULL + zu altes created_at)
$candidates = $db->query("
    SELECT id, email, name, role, last_activity, last_login, created_at,
           COALESCE(last_activity, last_login, created_at) AS last_seen
    FROM users
    WHERE is_active = 1
      AND role != 'admin'
      AND COALESCE(last_activity, last_login, created_at) < DATE_SUB(NOW(), INTERVAL ? DAY)
    ORDER BY last_seen ASC
", [$days]);

if (empty($candidates)) {
    echo "Keine inaktiven User gefunden.\n";
    exit(0);
}

$count = 0;
foreach ($candidates as $u) {
    $when = $u['last_seen'] ?? $u['created_at'];
    $age = (time() - strtotime($when)) / 86400;
    printf("  %-40s  %-8s  zuletzt aktiv: %s (vor %d Tagen)\n",
        $u['email'], $u['role'], $when, (int)$age);

    if (!$dryRun) {
        $db->execute("UPDATE users SET is_active = 0 WHERE id = ?", [(int)$u['id']]);
        \Core\AuditLog::record(
            \Core\AuditLog::TARGET_USER, (string)$u['id'],
            \Core\AuditLog::ACTION_USER_DEACTIVATED,
            ['reason' => 'inactive', 'days' => (int)$age]
        );
    }
    $count++;
}

echo "\n";
echo $dryRun
    ? "WUERDE deaktivieren: $count User.\n"
    : "Deaktiviert: $count User.\n";
