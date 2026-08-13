<?php

namespace Core;

/**
 * Migrator — inkrementeller Datenbank-Migrations-Runner.
 *
 * Loest das fragile manuelle /admin/migrate ab: statt bei jedem Aufruf ALLE
 * idempotenten ALTER-Statements erneut abzufeuern, wird jede Migration genau
 * einmal ausgefuehrt und in `schema_migrations` protokolliert.
 *
 * DATENSICHERHEIT (oberste Regel):
 *  - Migrationen sind AUSSCHLIESSLICH additiv/idempotent (CREATE TABLE IF NOT
 *    EXISTS, ADD COLUMN). Kein DROP/DELETE/verlustreiches MODIFY in
 *    Auto-Migrationen. Datenumbauten nur manuell mit Backup.
 *  - Fehler, die "schon vorhanden" bedeuten (Duplicate column/key, table exists),
 *    werden als Erfolg gewertet — so bleibt jede Migration gefahrlos wiederholbar.
 *  - Der bestehende Legacy-/admin/migrate-Block bleibt unangetastet und dient
 *    weiter als Baseline fuer das Alt-Schema. Der Migrator kuemmert sich um alles
 *    Neue ab `sql/migrations/`.
 *
 * Dateien in sql/migrations/:  NNNN_beschreibung.sql   (z.B. 0001_module_state.sql)
 * Die Version ist der Dateiname ohne Endung. Ausgefuehrt wird in numerischer
 * bzw. alphabetischer Reihenfolge des Dateinamens.
 */
class Migrator
{
    /** Fehlermuster, die "bereits vorhanden" bedeuten und ignoriert werden duerfen. */
    private const HARMLESS = [
        'Duplicate column',
        'Duplicate key name',
        'already exists',
        'Duplicate entry',
        "check that column/key exists", // MySQL: Can't DROP ...; check that column/key exists
        'Multiple primary key defined',
    ];

    /** Verzeichnis mit den Migrationsdateien. */
    private static function dir(): string
    {
        return (defined('ROOT_PATH') ? ROOT_PATH : __DIR__ . '/..') . '/sql/migrations';
    }

    private static function db(?Database $db): Database
    {
        return $db ?? Database::getInstance();
    }

    /** Stellt sicher, dass die Protokoll-Tabelle existiert. */
    private static function ensureTable(Database $db): void
    {
        $db->getConnection()->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(128) PRIMARY KEY,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /** @return string[] bereits angewendete Versionen */
    public static function applied(?Database $db = null): array
    {
        $db = self::db($db);
        self::ensureTable($db);
        $rows = $db->query("SELECT version FROM schema_migrations");
        return array_map(fn($r) => $r['version'], $rows);
    }

    /** @return string[] gefundene Migrationsdateien (Versionen), sortiert */
    public static function all(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.sql') ?: [];
        $versions = array_map(fn($f) => basename($f, '.sql'), $files);
        sort($versions, SORT_STRING);
        return $versions;
    }

    /** @return string[] noch offene Versionen */
    public static function pending(?Database $db = null): array
    {
        $applied = self::applied($db);
        return array_values(array_diff(self::all(), $applied));
    }

    /**
     * Fuehrt alle offenen Migrationen aus.
     * @return array{applied: string[], skipped: string[], messages: string[]}
     */
    public static function run(?Database $db = null): array
    {
        $db = self::db($db);
        self::ensureTable($db);
        $applied = self::applied($db);
        $out = ['applied' => [], 'skipped' => [], 'messages' => []];

        foreach (self::all() as $version) {
            if (in_array($version, $applied, true)) {
                $out['skipped'][] = $version;
                continue;
            }
            $file = self::dir() . '/' . $version . '.sql';
            $sql = @file_get_contents($file);
            if ($sql === false) {
                $out['messages'][] = "Migration $version: Datei nicht lesbar";
                continue;
            }
            try {
                self::execFile($db, $sql);
                self::record($db, $version);
                $out['applied'][] = $version;
                $out['messages'][] = "Migration $version angewendet";
            } catch (\Throwable $e) {
                // Harte Fehler stoppen den Lauf (fail-safe: nicht auf halbem Stand
                // weitermachen). Die bereits angewendeten bleiben protokolliert.
                $out['messages'][] = "Migration $version FEHLER: " . $e->getMessage();
                throw new \RuntimeException(
                    "Migration $version fehlgeschlagen: " . $e->getMessage() .
                    " — bereits angewendet: " . implode(', ', $out['applied']),
                    0,
                    $e
                );
            }
        }
        return $out;
    }

    /**
     * Markiert alle vorhandenen Migrationsdateien als angewendet, OHNE sie
     * auszufuehren. Fuer den Erst-Rollout auf einer bestehenden Installation
     * (Thoxan), deren Schema bereits durch den Legacy-Block aufgebaut wurde.
     * @return string[] neu markierte Versionen
     */
    public static function markBaseline(?Database $db = null): array
    {
        $db = self::db($db);
        self::ensureTable($db);
        $applied = self::applied($db);
        $marked = [];
        foreach (self::all() as $version) {
            if (!in_array($version, $applied, true)) {
                self::record($db, $version);
                $marked[] = $version;
            }
        }
        return $marked;
    }

    private static function record(Database $db, string $version): void
    {
        $db->execute(
            "INSERT IGNORE INTO schema_migrations (version) VALUES (?)",
            [$version]
        );
    }

    /**
     * Fuehrt eine .sql-Datei Statement fuer Statement aus. Trennt an ";" am
     * Zeilenende, ueberspringt Kommentar-/Leerzeilen. "Bereits vorhanden"-Fehler
     * werden geschluckt, damit Migrationen wiederholbar bleiben.
     */
    private static function execFile(Database $db, string $sql): void
    {
        $pdo = $db->getConnection();
        foreach (self::splitStatements($sql) as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (\PDOException $e) {
                if (!self::isHarmless($e->getMessage())) {
                    throw $e;
                }
            }
        }
    }

    /** @return string[] einzelne SQL-Statements */
    private static function splitStatements(string $sql): array
    {
        // Zeilenkommentare (-- ... und # ...) entfernen.
        $lines = preg_split('/\r?\n/', $sql);
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            $clean[] = $line;
        }
        $joined = implode("\n", $clean);
        $parts = array_map('trim', explode(';', $joined));
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }

    private static function isHarmless(string $msg): bool
    {
        foreach (self::HARMLESS as $needle) {
            if (stripos($msg, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
