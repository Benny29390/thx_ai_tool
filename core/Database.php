<?php
/**
 * Datenbank-Klasse (MySQL PDO Wrapper)
 */

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function getInstance(?array $config = null): Database
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \RuntimeException('Database config required for first initialization');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function connect(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'] ?? 3306,
                $this->config['name'],
                $this->config['charset'] ?? 'utf8mb4'
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], $options);
        }

        return $this->pdo;
    }

    public function getConnection(): PDO
    {
        return $this->connect();
    }

    /**
     * Fuehrt eine Query aus und gibt alle Ergebnisse zurueck
     */
    public function query(string $sql, array $params = []): array
    {
        $t0 = microtime(true);
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $this->trackQuery($sql, (microtime(true) - $t0) * 1000);
        return $rows;
    }

    /**
     * Fuehrt eine Query aus und gibt eine einzelne Zeile zurueck
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $t0 = microtime(true);
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        $this->trackQuery($sql, (microtime(true) - $t0) * 1000);
        return $result ?: null;
    }

    /**
     * Fuehrt eine Query aus und gibt einen einzelnen Wert zurueck
     */
    public function queryValue(string $sql, array $params = [])
    {
        $t0 = microtime(true);
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        $this->trackQuery($sql, (microtime(true) - $t0) * 1000);
        return $val;
    }

    /**
     * Fuehrt ein INSERT/UPDATE/DELETE aus
     */
    public function execute(string $sql, array $params = []): int
    {
        $t0 = microtime(true);
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        $rc = $stmt->rowCount();
        $this->trackQuery($sql, (microtime(true) - $t0) * 1000);
        return $rc;
    }

    /**
     * Loggt eine ausgeführte Query an den RequestLogger (falls verfügbar).
     */
    private function trackQuery(string $sql, float $ms): void
    {
        if (class_exists(RequestLogger::class, false)) {
            RequestLogger::addQuery($sql, $ms);
        }
    }

    /**
     * Insert mit Rueckgabe der ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->execute($sql, array_values($data));

        return (int) $this->connect()->lastInsertId();
    }

    /**
     * Update
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        return $this->execute($sql, array_merge(array_values($data), $whereParams));
    }

    /**
     * Delete
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->execute($sql, $params);
    }

    /**
     * Transaktion starten
     */
    public function beginTransaction(): bool
    {
        return $this->connect()->beginTransaction();
    }

    /**
     * Transaktion bestaetigen
     */
    public function commit(): bool
    {
        return $this->connect()->commit();
    }

    /**
     * Transaktion zurueckrollen
     */
    public function rollback(): bool
    {
        return $this->connect()->rollBack();
    }

    /**
     * Verbindung testen
     */
    public static function testConnection(array $config): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $config['host'],
                $config['port'] ?? 3306,
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Pruefen ob Datenbank existiert
            $dbName = $config['name'];
            $result = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'")->fetch();

            return [
                'success' => true,
                'database_exists' => !empty($result),
                'message' => 'Verbindung erfolgreich'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'database_exists' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Datenbank erstellen falls nicht vorhanden
     */
    public static function createDatabase(array $config): bool
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $config['host'],
                $config['port'] ?? 3306,
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $dbName = $config['name'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
