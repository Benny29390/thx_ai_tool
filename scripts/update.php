<?php
/**
 * update.php — Installation auf den neuesten Stand bringen (CLI).
 *
 * Holt den zentralen Code-Stand und wendet Migrationen an. Anbieter-neutral:
 * funktioniert mit jedem 'origin'-Remote (GitHub/GitLab/eigener Server).
 *
 * OBERSTE REGEL: KEIN DATENVERLUST. Die Reihenfolge ist bewusst defensiv:
 *   1. Vorbedingungen pruefen (git, remote, composer, kein Parallel-Lauf)
 *   2. Ist ueberhaupt ein Update da? sonst sauber beenden
 *   3. PFLICHT-BACKUP (DB-Dump + config.php + license.json). Schlaegt es fehl,
 *      wird NICHTS veraendert.
 *   4. Wartungsmodus an
 *   5. git reset --hard origin/<branch>   (NIE 'git clean' — untracked Kundendaten
 *      bleiben unangetastet; config/storage/uploads sind ohnehin gitignored)
 *   6. composer install --no-dev
 *   7. Migrationen (additiv/idempotent)
 *   8. OPcache leeren
 *   9. Cron-Abgleich (falls scripts/sync-cron.php vorhanden)
 *  10. Wartungsmodus aus
 * Bei einem Fehler ab Schritt 5 bleibt der Wartungsmodus AN (kein halb
 * aktualisierter Stand wird ausgeliefert) und der Backup-Pfad wird genannt.
 *
 * Aufruf:
 *   php scripts/update.php                 # interaktiv/manuell
 *   php scripts/update.php --branch=main
 *   php scripts/update.php --auto          # fuer Cron: nur wenn Update da, still
 *   php scripts/update.php --check         # nur pruefen, nichts aendern
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

use Core\Version;

$args      = $argv ?? [];
$auto      = in_array('--auto', $args, true);
$checkOnly = in_array('--check', $args, true);
$writeStatus = in_array('--write-status', $args, true);
$ifRequested = in_array('--if-requested', $args, true);
$branch    = 'main';
foreach ($args as $a) {
    if (strpos($a, '--branch=') === 0) $branch = substr($a, strlen('--branch='));
}

$root       = ROOT_PATH;
$logFile    = $root . '/storage/logs/update.log';
$lockFile   = $root . '/storage/UPDATE.lock';
$maintFile  = $root . '/storage/MAINTENANCE';
$statusFile = $root . '/storage/update-status.json';
$requestFile = $root . '/storage/UPDATE_REQUESTED';

@mkdir(dirname($logFile), 0775, true);

// --- Modus --write-status: nur Remote pruefen und Status als JSON ablegen ---
// (laeuft privilegiert per Cron; die Admin-UI liest nur diese Datei, braucht
//  selbst keinen Schreibzugriff aufs Git-Repo.)
if ($writeStatus) {
    $ok = Version::fetch();
    $behind = Version::behindCount($branch);
    $status = [
        'checked_at' => gmdate('c'),
        'ok'         => $ok,
        'branch'     => $branch,
        'current'    => Version::current(),
        'available'  => Version::availableVersion($branch),
        'behind'     => $behind,
        'changes'    => $behind ? Version::changesSince($branch) : [],
    ];
    @file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    @chmod($statusFile, 0664);
    exit($ok ? 0 : 1);
}

// --- Modus --if-requested: nur laufen, wenn die Web-UI ein Update angefordert hat ---
if ($ifRequested) {
    if (!is_file($requestFile)) {
        exit(0); // nichts angefordert
    }
    @unlink($requestFile); // Anforderung konsumieren (kein Dauerlauf)
    // ... faellt durch in den normalen Update-Ablauf unten.
}

function logline(string $m, bool $quiet = false): void
{
    global $logFile;
    $line = '[' . gmdate('Y-m-d H:i:s') . 'Z] ' . $m;
    @file_put_contents($logFile, $line . "\n", FILE_APPEND);
    if (!$quiet) echo $line . "\n";
}
function fail_exit(string $m): void
{
    logline('FEHLER: ' . $m);
    exit(1);
}

// --- 1) Vorbedingungen ---
if (!Version::gitAvailable()) fail_exit('git ist nicht verfuegbar oder /var/www ist kein Git-Checkout.');
if (!Version::hasRemote())    fail_exit('Kein Git-Remote konfiguriert — es gibt keine Quelle fuer Updates.');

$composer = trim((string) @shell_exec('command -v composer 2>/dev/null'));
if ($composer === '') fail_exit('composer nicht gefunden.');

if (is_file($lockFile)) {
    fail_exit('Ein Update laeuft bereits (Lockdatei ' . $lockFile . '). Falls das ein Fehler ist, Datei entfernen.');
}

// --- 2) Update verfuegbar? ---
logline("Pruefe Remote (Branch $branch) ...", $auto);
if (!Version::fetch()) fail_exit('git fetch fehlgeschlagen.');
$behind = Version::behindCount($branch);
$current = Version::current();
$available = Version::availableVersion($branch);

if ($checkOnly) {
    echo "Aktuell:    $current\n";
    echo "Verfuegbar: " . ($available ?? '?') . "\n";
    echo "Rueckstand: " . ($behind === null ? '?' : $behind) . " Commit(s)\n";
    if ($behind) {
        echo "Aenderungen:\n";
        foreach (Version::changesSince($branch) as $c) echo "  - $c\n";
    }
    exit(0);
}

if ($behind === 0) {
    logline("Bereits aktuell ($current) — nichts zu tun.", $auto);
    exit(0);
}
logline("Update verfuegbar: $current -> " . ($available ?? '?') . " ($behind Commit(s)).");

// --- 3) PFLICHT-BACKUP ---
$stamp = gmdate('Ymd-His');
$backupDir = $root . '/backups/pre-update-' . $stamp;
if (!@mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    fail_exit('Backup-Verzeichnis konnte nicht angelegt werden: ' . $backupDir);
}
logline('Backup nach ' . $backupDir);

$cfg = require CONFIG_PATH . '/config.php';
$db = $cfg['db'];
$dumpFile = $backupDir . '/db.sql.gz';
$dumpCmd = sprintf(
    'mysqldump --host=%s --port=%s --user=%s %s --single-transaction --quick --routines %s 2>%s | gzip > %s',
    escapeshellarg($db['host']),
    escapeshellarg((string) ($db['port'] ?? 3306)),
    escapeshellarg($db['user']),
    $db['pass'] !== '' ? '--password=' . escapeshellarg($db['pass']) : '',
    escapeshellarg($db['name']),
    escapeshellarg($backupDir . '/db.err'),
    escapeshellarg($dumpFile)
);
@exec($dumpCmd, $o, $code);
clearstatcache();
if ($code !== 0 || !is_file($dumpFile) || filesize($dumpFile) < 100) {
    $err = @file_get_contents($backupDir . '/db.err');
    fail_exit('Datenbank-Backup fehlgeschlagen — Update abgebrochen, nichts veraendert. ' . trim((string) $err));
}
@copy(CONFIG_PATH . '/config.php', $backupDir . '/config.php');
if (is_file(CONFIG_PATH . '/license.json')) {
    @copy(CONFIG_PATH . '/license.json', $backupDir . '/license.json');
}
// Aktuellen Commit fuers Rollback notieren.
@file_put_contents($backupDir . '/COMMIT.txt', Version::currentCommit() . "\n");
logline('Backup ok (DB-Dump ' . round(filesize($dumpFile) / 1024) . ' KB).');

// Ab hier wird veraendert — Lockfile + Wartungsmodus.
@file_put_contents($lockFile, gmdate('c') . "\n");
@file_put_contents($maintFile, "Update laeuft — die Anwendung ist in wenigen Minuten wieder da.\n");
logline('Wartungsmodus AN.');

$hardFail = function (string $m) use ($lockFile, $backupDir) {
    @unlink($lockFile);
    logline('FEHLER: ' . $m);
    logline('Wartungsmodus bleibt AN (kein halb-aktualisierter Stand wird ausgeliefert).');
    logline('Rollback moeglich aus: ' . $backupDir . ' (git reset --hard <COMMIT.txt>, DB aus db.sql.gz).');
    exit(1);
};

// --- 5) Code aktualisieren (NIE git clean) ---
logline("git reset --hard origin/$branch ...");
$r = Version::git('reset --hard origin/' . escapeshellarg($branch));
if ($r['code'] !== 0) $hardFail('git reset fehlgeschlagen: ' . $r['out']);

// --- 6) Abhaengigkeiten ---
logline('composer install --no-dev ...');
@exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($composer)
    . ' install --no-dev --optimize-autoloader --no-interaction 2>&1', $co, $cc);
if ($cc !== 0) $hardFail('composer install fehlgeschlagen: ' . trim(implode("\n", $co)));

// --- 7) Migrationen ---
logline('Migrationen ...');
@exec('php ' . escapeshellarg($root . '/scripts/migrate.php') . ' 2>&1', $mo, $mc);
foreach ($mo as $l) logline('  migrate: ' . $l, true);
if ($mc !== 0) $hardFail('Migrationen fehlgeschlagen: ' . trim(implode("\n", $mo)));

// --- 8) OPcache leeren (ueber Web-Request, damit auch der Apache-Cache faellt) ---
logline('OPcache leeren ...');
$ocFile = $root . '/assets/_update_oc.php';
@file_put_contents($ocFile, "<?php if (function_exists('opcache_reset')) opcache_reset(); echo 'ok';");
$base = \Core\Brand::url('/assets/_update_oc.php');
if ($base !== '/assets/_update_oc.php') {
    @exec('curl -s -k -m 10 ' . escapeshellarg($base) . ' >/dev/null 2>&1');
}
@unlink($ocFile);

// --- 9) Cron-Abgleich (optional) ---
if (is_file($root . '/scripts/sync-cron.php')) {
    logline('Cron-Abgleich ...');
    @exec('php ' . escapeshellarg($root . '/scripts/sync-cron.php') . ' 2>&1', $so, $sc);
    foreach ($so as $l) logline('  cron: ' . $l, true);
}

// --- 10) Fertig ---
@unlink($maintFile);
@unlink($lockFile);
// Status aktualisieren, damit die Admin-UI sofort "aktuell" zeigt.
$st = [
    'checked_at' => gmdate('c'),
    'ok' => true, 'branch' => $branch,
    'current' => Version::current(),
    'available' => Version::availableVersion($branch),
    'behind' => Version::behindCount($branch),
    'changes' => [],
];
@file_put_contents($statusFile, json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
logline('Wartungsmodus AUS. Update abgeschlossen: ' . Version::current());
exit(0);
