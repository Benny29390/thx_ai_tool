<?php
namespace Core;

/**
 * RequestLogger — Pro-Request-Tracing für späteres Debuggen.
 *
 * Schreibt am Ende jedes Requests eine Zeile in `/var/www/storage/logs/requests.log`.
 * Langsame Requests (>2s) oder Memory-intensive (>50 MB Peak) zusätzlich in `slow.log`.
 * Fatal-Errors werden via register_shutdown_function eingefangen.
 *
 * Init: einmal am Anfang von index.php und api/handler.php aufrufen — `RequestLogger::init()`.
 * Wird automatisch via register_shutdown_function in finish() abgeschlossen.
 */
class RequestLogger
{
    public const LOG_DIR = '/var/www/storage/logs';
    public const REQ_LOG = '/var/www/storage/logs/requests.log';
    public const SLOW_LOG = '/var/www/storage/logs/slow.log';
    public const ERR_LOG = '/var/www/storage/logs/errors.log';

    /** Pro-Request-Cap nach dem rotated wird */
    public const ROTATE_BYTES = 20 * 1024 * 1024;   // 20 MB

    public const SLOW_THRESHOLD_MS = 2000;
    public const HEAVY_MEM_BYTES = 50 * 1024 * 1024;

    private static float $startedAt = 0.0;
    private static bool $initialized = false;
    /** @var array<int,array{sql:string,ms:float}> */
    private static array $queries = [];
    private static float $totalDbMs = 0.0;

    public static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;
        self::$startedAt = microtime(true);

        @mkdir(self::LOG_DIR, 0775, true);

        // Fatal-Errors fangen
        register_shutdown_function([self::class, 'finish']);
    }

    /**
     * Wird nach jedem Request aufgerufen — schreibt die Log-Zeile.
     */
    public static function finish(): void
    {
        // Mehrfach-Aufruf schützen (z.B. wenn explizit aufgerufen UND shutdown-handler greift)
        static $done = false;
        if ($done) return;
        $done = true;

        $durMs = (int) round((microtime(true) - self::$startedAt) * 1000);
        $peakMem = memory_get_peak_usage(true);
        $status = http_response_code() ?: 0;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? '-';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
        $userId = 0;
        if (isset($_SESSION['user_id'])) $userId = (int) $_SESSION['user_id'];

        // Skip noisy static stuff
        if (preg_match('#\.(css|js|woff2?|png|jpg|jpeg|svg|gif|ico|map)(\?|$)#i', $uri)) return;

        // Fatal-Error?
        $fatal = '';
        $lastErr = error_get_last();
        if ($lastErr && in_array($lastErr['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $fatal = sprintf('FATAL %s in %s:%d', $lastErr['message'], $lastErr['file'], $lastErr['line']);
        }

        $dbInfo = self::$totalDbMs > 0
            ? sprintf(' | db=%dms/%dq', (int) self::$totalDbMs, count(self::$queries))
            : '';

        $line = sprintf(
            "%s | %-6s %-50s | %3d | %6d ms | %5s%s | u=%d | %s%s\n",
            date('Y-m-d H:i:s'),
            $method,
            mb_substr($uri, 0, 50),
            $status,
            $durMs,
            self::humanBytes($peakMem),
            $dbInfo,
            $userId,
            $ip,
            $fatal !== '' ? ' | ' . $fatal : ''
        );

        self::writeRotated(self::REQ_LOG, $line);

        // Slow/Heavy — zusätzlich Top-Queries dranhängen
        if ($durMs >= self::SLOW_THRESHOLD_MS || $peakMem >= self::HEAVY_MEM_BYTES) {
            $extraLines = $line;
            if (!empty(self::$queries)) {
                $slow = array_filter(self::$queries, fn($q) => $q['ms'] >= 50);
                usort($slow, fn($a, $b) => $b['ms'] <=> $a['ms']);
                $top = array_slice($slow, 0, 8);
                foreach ($top as $q) {
                    $extraLines .= sprintf("    ↳ %6.1f ms | %s\n", $q['ms'], $q['sql']);
                }
            }
            self::writeRotated(self::SLOW_LOG, $extraLines);
        }

        // Fatal in eigenes Log
        if ($fatal !== '') {
            self::writeRotated(self::ERR_LOG, $line);
        }
    }

    /**
     * Von Database.php aufgerufen: jede Query mit ihrer Dauer registrieren.
     */
    public static function addQuery(string $sql, float $ms): void
    {
        self::$totalDbMs += $ms;
        // Nur Top-50 pro Request behalten (Memory-Schutz)
        if (count(self::$queries) < 50) {
            self::$queries[] = ['sql' => mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 200), 'ms' => $ms];
        }
    }

    /**
     * Manuelles Log-Event (z.B. „Import hat 1421 Rows geladen") — wird in requests.log angehängt.
     */
    public static function event(string $message, array $meta = []): void
    {
        $line = sprintf(
            "%s | EVENT  | %s%s\n",
            date('Y-m-d H:i:s'),
            $message,
            empty($meta) ? '' : ' | ' . json_encode($meta, JSON_UNESCAPED_UNICODE)
        );
        self::writeRotated(self::REQ_LOG, $line);
    }

    private static function writeRotated(string $file, string $line): void
    {
        try {
            if (@is_file($file) && @filesize($file) > self::ROTATE_BYTES) {
                @rename($file, $file . '.old');
            }
            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Logging selbst darf nichts hochreißen
        }
    }

    private static function humanBytes(int $b): string
    {
        if ($b < 1024) return $b . 'B';
        if ($b < 1024 * 1024) return round($b / 1024) . 'K';
        return round($b / 1024 / 1024, 1) . 'M';
    }
}
