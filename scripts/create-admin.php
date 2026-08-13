<?php
/**
 * create-admin.php — Admin-Benutzer anlegen oder aktualisieren (CLI).
 *
 * Aufruf:
 *   php scripts/create-admin.php --email=chef@firma.de --password=... --name="Vorname Nachname"
 *
 * Idempotent: Existiert die E-Mail bereits, werden Name/Passwort/Rolle
 * aktualisiert und der Account aktiviert (kein Duplikat, kein Datenverlust).
 * Passwort wird bcrypt-gehasht (password_hash), nie im Klartext gespeichert.
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

$configFile = CONFIG_PATH . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Keine config.php — bitte zuerst installieren.\n");
    exit(1);
}
$config = require $configFile;
$db = \Core\Database::getInstance($config['db']);

/** Kleines --key=value / --key value Parsing. */
function arg(string $name, array $argv): ?string
{
    foreach ($argv as $i => $a) {
        if (strpos($a, "--$name=") === 0) {
            return substr($a, strlen("--$name="));
        }
        if ($a === "--$name" && isset($argv[$i + 1])) {
            return $argv[$i + 1];
        }
    }
    return null;
}

$email = trim((string) arg('email', $argv));
$pass  = (string) arg('password', $argv);
$name  = trim((string) arg('name', $argv)) ?: 'Administrator';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Ungueltige oder fehlende --email.\n");
    exit(1);
}
if (strlen($pass) < 8) {
    fwrite(STDERR, "--password fehlt oder ist kuerzer als 8 Zeichen.\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$existing = $db->queryOne("SELECT id FROM users WHERE email = ?", [$email]);

if ($existing) {
    $db->update('users', [
        'password_hash' => $hash,
        'name'          => $name,
        'role'          => 'admin',
        'is_active'     => 1,
    ], 'id = ?', [$existing['id']]);
    echo "Admin aktualisiert: $email (ID {$existing['id']})\n";
} else {
    $id = $db->insert('users', [
        'email'         => $email,
        'password_hash' => $hash,
        'name'          => $name,
        'role'          => 'admin',
        'is_active'     => 1,
    ]);
    echo "Admin angelegt: $email (ID $id)\n";
}
exit(0);
