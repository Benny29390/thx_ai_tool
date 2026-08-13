<?php
/**
 * Session-Management mit CSRF-Schutz
 */

namespace Core;

class Session
{
    private static bool $started = false;

    /**
     * Session starten
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }

        self::$started = true;

        // CSRF-Token generieren falls nicht vorhanden
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            self::regenerateCsrfToken();
        }

        // CSRF-Token erneuern wenn abgelaufen
        if (time() - ($_SESSION['csrf_token_time'] ?? 0) > CSRF_TOKEN_LIFETIME) {
            self::regenerateCsrfToken();
        }
    }

    /**
     * Wert setzen
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Wert lesen
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Wert vorhanden?
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Wert loeschen
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Alle Werte loeschen
     */
    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * Session zerstoeren
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Session-ID regenerieren
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * CSRF-Token generieren
     */
    public static function regenerateCsrfToken(): void
    {
        self::start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }

    /**
     * CSRF-Token lesen
     */
    public static function getCsrfToken(): string
    {
        self::start();
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * CSRF-Token validieren
     */
    public static function validateCsrfToken(?string $token): bool
    {
        self::start();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Flash-Message setzen
     */
    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash'][$type][] = $message;
    }

    /**
     * Flash-Messages lesen und loeschen
     */
    public static function getFlash(?string $type = null): array
    {
        self::start();
        if ($type !== null) {
            $messages = $_SESSION['flash'][$type] ?? [];
            unset($_SESSION['flash'][$type]);
            return $messages;
        }

        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }

    /**
     * Flash-Messages vorhanden?
     */
    public static function hasFlash(?string $type = null): bool
    {
        self::start();
        if ($type !== null) {
            return !empty($_SESSION['flash'][$type]);
        }
        return !empty($_SESSION['flash']);
    }

    /**
     * Session-Lock freigeben. Wichtig fuer langlaufende Requests (KI-Calls,
     * Bulk-Aktionen): solange die Session-Datei exklusiv gesperrt ist, koennen
     * andere Requests desselben Users nicht parallel laufen — der Browser
     * blockiert dann komplett. Nach dem Aufruf bleibt $_SESSION lesbar fuer
     * den aktuellen Request, kann aber nicht mehr persistent geaendert werden.
     */
    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        self::$started = false; // damit get/set noetigenfalls neu starten kann
    }
}
