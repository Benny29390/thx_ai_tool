<?php
namespace Services;

use Core\Database;

require_once __DIR__ . '/AdminCodeToolsTrait.php';

/**
 * CoworkerService — Chat-Modus für freien Code-Agent-Dialog mit Claude.
 *
 * Anders als AdminTaskService:
 *   - persistente Session mit beliebig vielen User-/Assistant-Turns
 *   - zusätzliches bash-Tool (volle Shell-Power, mit Blacklist + Auto-Snapshot)
 *   - parallele Sessions erlaubt (pro Admin / mehrere Admins gleichzeitig)
 *   - Cost-Cap pro Session, Stop-Button kappt SSE
 *
 * Sicherheit:
 *   - FORBIDDEN_ALWAYS (write_file): config/config.php, core/Database.php,
 *     sql/, vendor/, storage/, .env, cli/worker.php, AdminTaskService.php,
 *     CoworkerService.php (Self-Protect), AdminCodeToolsTrait.php
 *   - bash umgeht write_file-Whitelist BEWUSST (User-Entscheidung) —
 *     UI zeigt Banner-Warnung. Blacklist + Auto-Snapshot fangen
 *     die offensichtlichen Katastrophen.
 */
class CoworkerService
{
    use AdminCodeToolsTrait;

    public const FORBIDDEN_ALWAYS = [
        'config/config.php',
        'core/Database.php',
        'sql/',
        'vendor/',
        'storage/',
        '.env',
        'cli/worker.php',
        'services/AdminTaskService.php',
        'services/CoworkerService.php',
        'services/AdminCodeToolsTrait.php',
    ];

    /** Pro Turn: harte Obergrenze an Tool-Aufrufen (Token-Kosten wachsen quadratisch
     *  mit der Iter-Zahl, weil History pro Iter mitgesendet wird) */
    public const MAX_ITERATIONS_PER_TURN = 25;

    /** Default-Budget pro Session in USD (kann per Session überschrieben werden) */
    public const DEFAULT_COST_CAP_USD = 5.0;

    /** max_tokens für die Anthropic-API — 16K reicht für ~2000-Zeilen-Dateien */
    public const ANTHROPIC_MAX_TOKENS = 16000;

    /** Bash-Defaults */
    public const BASH_DEFAULT_TIMEOUT = 30;
    public const BASH_MAX_TIMEOUT = 300;
    public const BASH_OUTPUT_MAX = 50 * 1024;

    /**
     * Regex-Blacklist gegen destruktive Bash-Befehle.
     *
     * Strategie: lieber zu restriktiv. Wenn die KI was Berechtigtes nicht ausführen
     * darf, soll sie über ask_user beim Admin nachfragen, der dann manuell erlaubt.
     * Datenverlust durch versehentlichen Tool-Use ist das Hauptrisiko.
     */
    public const BASH_BLACKLIST = [
        // ---- rm: NIE rekursiv oder forced erlaubt ----
        '#(?<![a-zA-Z0-9_-])rm\s+(-[a-zA-Z]*[rfRF][a-zA-Z]*)#i',  // rm mit -r oder -f (egal in welcher Flag-Kombi)
        '#(?<![a-zA-Z0-9_-])rm\s+(?:-[a-zA-Z]+\s+)?(/|~|\$HOME)#i',  // rm gegen / oder Home
        // ---- find -delete / find -exec rm ----
        '#\bfind\b.*-(delete|exec\s+rm)\b#i',
        // ---- Block-Device-/Filesystem-Killer ----
        '#\bdd\b.*\bof=#i',                       // dd schreibend
        '#\b(mkfs|fdisk|parted|wipefs|shred)\b#i',
        '#>\s*/dev/(sd|nvme|hd|xvd)[a-z]#i',
        '#\bmount\s+/dev#i',
        // ---- System-Halt / Init-Manipulation ----
        '#\b(shutdown|reboot|halt|poweroff|systemctl\s+(stop|disable|mask)\s+(apache|nginx|mysql|mariadb|php))\b#i',
        '#\bkill(all)?\s+-9?\s*(-1|1\b)#i',       // kill -9 -1 / kill 1 / killall init etc.
        '#\bservice\s+\w+\s+stop\b#i',
        // ---- Permission-Apokalypse ----
        '#\b(chmod|chown|chgrp)\s+-[a-zA-Z]*R#i', // -R rekursiv
        '#\bchmod\s+(000|777)\s+(/|/etc|/var|/usr|/bin|/sbin)#i',
        // ---- Datenbank-Vernichtung ----
        '#\b(DROP\s+(DATABASE|SCHEMA|TABLE)|TRUNCATE\s+TABLE|TRUNCATE\b|DROP\s+USER|FLUSH\s+PRIVILEGES)\b#i',
        '#\bDELETE\s+FROM\b(?!.*\bWHERE\b)#i',    // DELETE FROM ohne WHERE
        '#\bUPDATE\s+\w+\s+SET\b(?!.*\bWHERE\b)#i',// UPDATE ohne WHERE
        // ---- Git-Datenverlust ----
        '#\bgit\s+(reset\s+--hard|clean\s+-[a-zA-Z]*[fd][a-zA-Z]*|push\s+(--force|.*\s-f\b)|filter-branch|update-ref\s+-d)\b#i',
        // ---- Verzeichnis-Verschiebung kritischer Pfade ----
        '#\bmv\s+(/var/www|/etc|/usr|/var/lib/mysql|/var/log)\b#i',
        // ---- Curl/Wget: arbitrary script execution ----
        '#\b(curl|wget)\s+[^\|]*\|\s*(bash|sh|zsh|python|perl|ruby)\b#i',
        // ---- Fork-Bomben ----
        '#:\s*\(\)\s*\{#',
        // ---- Sudo / su: explizit gesperrt (würde sonst die Whitelist umgehen) ----
        '#\bsudo\b#i',
        '#\bsu\s+-#i',
        // ---- /etc / Apache-Config-Manipulation ----
        '#>\s*/etc/(passwd|shadow|sudoers|apache2|nginx|php)#i',
        '#\b(visudo|passwd\s+root)\b#i',
        // ---- Crontab/cron: nur eigene Cron, kein system-cron ----
        '#>\s*/etc/cron#i',
    ];

    private Database $db;
    private int $currentSessionId = 0;
    private int $currentMessageId = 0;
    /** Counter für Read-Operationen in Folge — wird bei write_file/done zurückgesetzt */
    private int $consecutiveReads = 0;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    protected function getForbiddenAlways(): array
    {
        return self::FORBIDDEN_ALWAYS;
    }

    /**
     * Snapshot-Persistierung in coworker_snapshots (von Trait::toolWriteFile genutzt).
     */
    protected function persistWriteSnapshot(string $absPath, ?string $originalContent, string $newContent, bool $existed): int
    {
        return (int) $this->db->insert('coworker_snapshots', [
            'session_id' => $this->currentSessionId,
            'message_id' => $this->currentMessageId ?: null,
            'file_path' => $absPath,
            'operation' => $existed ? 'write' : 'create',
            'original_content' => $originalContent,
            'new_content' => $newContent,
            'file_existed' => $existed ? 1 : 0,
            'source' => 'write_file',
        ]);
    }

    protected function markSnapshotRolledBack(int $snapshotId): void
    {
        $this->db->update('coworker_snapshots',
            ['rolled_back_at' => date('Y-m-d H:i:s')],
            'id = ?', [$snapshotId]);
    }

    // ====== Public API ======

    public function createSession(int $userId, ?float $costCap = null): int
    {
        $id = $this->db->insert('coworker_sessions', [
            'created_by' => $userId,
            'status' => 'idle',
            'cost_cap_usd' => $costCap !== null ? round($costCap, 4) : self::DEFAULT_COST_CAP_USD,
            'last_activity_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $id;
    }

    public function sendMessage(int $sessionId, string $userText, callable $sseOut): void
    {
        $session = $this->db->queryOne("SELECT * FROM coworker_sessions WHERE id = ?", [$sessionId]);
        if (!$session) throw new \RuntimeException('Session nicht gefunden');
        if ($session['status'] === 'closed') throw new \RuntimeException('Session ist geschlossen');

        $this->currentSessionId = $sessionId;
        $this->snapshottedThisRun = [];

        // User-Message persistieren + Title-Auto-Set
        $this->persistMessage($sessionId, 'user', $userText);
        if (empty($session['title'])) {
            $this->autosetTitle($sessionId, $userText);
        }

        $this->db->update('coworker_sessions', [
            'status' => 'active',
            'last_activity_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$sessionId]);

        $sseOut('session_active', ['session_id' => $sessionId]);
        $this->runLoop($sessionId, $sseOut);
    }

    public function reply(int $sessionId, string $userText, callable $sseOut): void
    {
        $session = $this->db->queryOne("SELECT * FROM coworker_sessions WHERE id = ?", [$sessionId]);
        if (!$session) throw new \RuntimeException('Session nicht gefunden');
        if ($session['status'] !== 'awaiting_user') {
            throw new \RuntimeException('Session wartet nicht auf eine Antwort (Status: ' . $session['status'] . ')');
        }
        $this->sendMessage($sessionId, $userText, $sseOut);
    }

    public function stop(int $sessionId): void
    {
        $this->db->update('coworker_sessions', [
            'status' => 'idle',
            'last_activity_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND status = ?', [$sessionId, 'active']);
    }

    /**
     * Rollt alle (noch nicht zurückgerollten) Snapshots einer Session rückwärts.
     */
    public function rollbackSession(int $sessionId): array
    {
        return $this->rollbackSnapshots(
            $this->db->query(
                "SELECT * FROM coworker_snapshots WHERE session_id = ? AND rolled_back_at IS NULL ORDER BY id DESC",
                [$sessionId]
            ) ?: []
        );
    }

    /**
     * Rollt alle Snapshots ab einer bestimmten Message-ID (inklusive späterer Msgs) zurück.
     */
    public function rollbackMessage(int $sessionId, int $messageId): array
    {
        return $this->rollbackSnapshots(
            $this->db->query(
                "SELECT * FROM coworker_snapshots WHERE session_id = ? AND message_id >= ? AND rolled_back_at IS NULL ORDER BY id DESC",
                [$sessionId, $messageId]
            ) ?: []
        );
    }

    private function rollbackSnapshots(array $rows): array
    {
        $restored = 0; $failed = 0;
        foreach ($rows as $snap) {
            $abs = $snap['file_path'];
            try {
                if ($snap['operation'] === 'create') {
                    if (is_file($abs)) @unlink($abs);
                } else {
                    if ($snap['original_content'] === null) {
                        if (is_file($abs)) @unlink($abs);
                    } else {
                        @file_put_contents($abs, $snap['original_content']);
                    }
                }
                $this->db->update('coworker_snapshots', ['rolled_back_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $snap['id']]);
                $restored++;
            } catch (\Throwable $e) {
                $failed++;
                error_log('Coworker rollback failed: ' . $abs . ' — ' . $e->getMessage());
            }
        }
        return ['restored' => $restored, 'failed' => $failed];
    }

    public function closeSession(int $sessionId): void
    {
        $this->db->update('coworker_sessions', ['status' => 'closed'], 'id = ?', [$sessionId]);
    }

    public function autosetTitle(int $sessionId, string $firstMessage): void
    {
        $clean = trim(preg_replace('/\s+/', ' ', $firstMessage) ?? '');
        $title = mb_substr($clean, 0, 60);
        if (mb_strlen($clean) > 60) $title .= '…';
        $this->db->update('coworker_sessions', ['title' => $title], 'id = ?', [$sessionId]);
    }

    // ====== Tool-Set ======

    public function getTools(): array
    {
        return [
            [
                'name' => 'list_files',
                'description' => 'Listet Dateien in einem Verzeichnis (relativ zu /var/www).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'directory' => ['type' => 'string', 'description' => 'Verzeichnis relativ zu /var/www'],
                    ],
                    'required' => ['directory'],
                ],
            ],
            [
                'name' => 'read_file',
                'description' => 'Liest den Inhalt einer Datei (relativ zu /var/www, max 500KB). Bei großen Dateien (>500 Zeilen) IMMER Range-Read nutzen — from_line/to_line angeben, sonst kostet jede Folge-Iteration zigtausende Tokens. Für gezieltes Suchen lieber search_code.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'from_line' => ['type' => 'integer', 'description' => '1-basiert, optional. Wenn gesetzt, wird nur dieser Bereich gelesen.'],
                        'to_line' => ['type' => 'integer', 'description' => '1-basiert, optional. Default = Dateiende.'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'search_code',
                'description' => 'grep nach String im Code (max 40 Treffer).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'directory' => ['type' => 'string'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'write_file',
                'description' => 'Schreibt eine Datei. Vorher Snapshot, danach PHP-Syntax-Check. FORBIDDEN_ALWAYS-Pfade (config/config.php, core/Database.php, sql/, vendor/, storage/, .env, cli/worker.php, AdminTaskService.php, CoworkerService.php) sind gesperrt. Sende immer den VOLLSTÄNDIGEN Inhalt.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                    ],
                    'required' => ['path', 'content'],
                ],
            ],
            [
                'name' => 'bash',
                'description' => 'Führt einen Shell-Befehl auf dem Server aus. Volle Power (alles was der www-data-User darf), aber: Blacklist sperrt rm -rf außerhalb /tmp, shutdown, mkfs, DROP DATABASE etc. Nutze das für git, php -l, tail logs, ls, grep, mysql -e SELECT, composer/npm wenn nötig. ALLE Datei-Änderungen unter /var/www werden automatisch gesnapshottet und sind via Rollback rückgängig zu machen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'command' => ['type' => 'string', 'description' => 'Shell-Befehl'],
                        'timeout_seconds' => ['type' => 'integer', 'description' => 'Default 30, max 300'],
                        'working_dir' => ['type' => 'string', 'description' => 'Default /var/www; muss unter /var/www oder /tmp liegen'],
                    ],
                    'required' => ['command'],
                ],
            ],
            [
                'name' => 'ask_user',
                'description' => 'Stelle eine Rückfrage und pausiere — nur wenn wirklich nötig.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['question' => ['type' => 'string']],
                    'required' => ['question'],
                ],
            ],
            [
                'name' => 'done',
                'description' => 'Schließt den aktuellen Turn ab mit Zusammenfassung. Session bleibt offen für weitere Nachrichten.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['summary' => ['type' => 'string']],
                    'required' => ['summary'],
                ],
            ],
        ];
    }

    public function dispatchTool(int $sessionId, string $toolName, array $args): array
    {
        $this->currentSessionId = $sessionId;
        $readOnlyTools = ['list_files', 'read_file', 'search_code', 'bash'];
        $isReadLike = in_array($toolName, $readOnlyTools, true);

        try {
            switch ($toolName) {
                case 'list_files':  $result = $this->toolListFiles('all', (string) ($args['directory'] ?? '')); break;
                case 'read_file':   $result = $this->toolReadFile('all', (string) ($args['path'] ?? ''),
                                       isset($args['from_line']) ? (int) $args['from_line'] : null,
                                       isset($args['to_line']) ? (int) $args['to_line'] : null); break;
                case 'search_code': $result = $this->toolSearchCode('all', (string) ($args['query'] ?? ''), (string) ($args['directory'] ?? '')); break;
                case 'write_file':  $result = $this->toolWriteFile('all', (string) ($args['path'] ?? ''), (string) ($args['content'] ?? '')); break;
                case 'bash':        $result = $this->toolBash(
                    (string) ($args['command'] ?? ''),
                    (int) ($args['timeout_seconds'] ?? self::BASH_DEFAULT_TIMEOUT),
                    (string) ($args['working_dir'] ?? self::TRAIT_ROOT)
                ); break;
                case 'ask_user':    return ['__text' => 'Frage gestellt — warte auf Antwort.', '__ask_user' => true, '__ask_user_question' => (string) ($args['question'] ?? '')];
                case 'done':        return ['__text' => 'OK', '__done' => true];
                default:            return ['__error' => true, '__text' => "Unbekanntes Tool: $toolName"];
            }
        } catch (\Throwable $e) {
            return ['__error' => true, '__text' => 'Tool-Exception: ' . $e->getMessage()];
        }

        // Read-Streak tracken — bei 5+ Reads ohne Aktion einen Hint anhängen
        if ($isReadLike) {
            $this->consecutiveReads++;
            if ($this->consecutiveReads >= 5 && empty($result['__error'])) {
                $hint = "\n\n⚠ EFFIZIENZ-HINWEIS: Das ist deine {$this->consecutiveReads}. Lese-Operation in Folge ohne write_file/ask_user/done. "
                      . "Token-Kosten wachsen quadratisch — du näherst dich dem Cost-Cap und 25-Iter-Limit. "
                      . "STOPPE mit Erkundung. Jetzt: entweder write_file (los machen) oder ask_user (falls unklar) oder done (falls fertig).";
                $result['__text'] = ($result['__text'] ?? '') . $hint;
            }
        } else {
            $this->consecutiveReads = 0; // reset
        }

        return $result;
    }

    // ====== Bash-Tool ======

    private function toolBash(string $command, int $timeout, string $workingDir): array
    {
        $command = trim($command);
        if ($command === '') return ['__error' => true, '__text' => 'Leerer Befehl'];

        $timeout = max(1, min(self::BASH_MAX_TIMEOUT, $timeout));

        // Working-Dir-Check
        $cwd = realpath($workingDir) ?: $workingDir;
        if ($cwd === false || (!str_starts_with($cwd, '/var/www') && !str_starts_with($cwd, '/tmp'))) {
            return ['__error' => true, '__text' => "Working-Dir nicht erlaubt (nur /var/www/* und /tmp/*): $workingDir"];
        }

        // Blacklist-Check
        foreach (self::BASH_BLACKLIST as $pattern) {
            if (preg_match($pattern, $command)) {
                $this->db->insert('coworker_bash_log', [
                    'session_id' => $this->currentSessionId,
                    'message_id' => $this->currentMessageId ?: null,
                    'command' => $command,
                    'exit_code' => -1,
                    'blocked_by_blacklist' => 1,
                ]);
                return ['__error' => true, '__text' => "BLOCKIERT durch Blacklist (Pattern: $pattern). Befehl nicht ausgeführt."];
            }
        }

        // Snapshot-Plan: vor dem Bash-Call merken wir uns alle Dateien unter /var/www
        // die *kürzlich* (mtime > now - 60s) geändert wurden — dann nach dem Call dito
        // und Diff. So fangen wir das, was Bash an Dateien anfasst.
        $preMtimes = $this->snapshotMtimes();

        // proc_open ist in Apache disabled — wir nutzen exec mit `timeout`-Wrapper.
        // Stderr wird via Temp-Datei eingefangen, weil exec nur stdout liefert.
        $stderrFile = tempnam(sys_get_temp_dir(), 'cw_err_');
        $wrapped = sprintf(
            'cd %s && timeout --preserve-status %d bash -c %s 2>%s',
            escapeshellarg($cwd),
            $timeout,
            escapeshellarg($command),
            escapeshellarg($stderrFile)
        );
        $start = microtime(true);
        $outputLines = [];
        $exitCode = 0;
        @exec($wrapped, $outputLines, $exitCode);
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $stdout = implode("\n", $outputLines);
        $stderr = @file_get_contents($stderrFile) ?: '';
        @unlink($stderrFile);
        // `timeout --preserve-status` gibt 124 zurück, wenn der Befehl getötet wurde
        $timedOut = ($exitCode === 124 || ($durationMs >= $timeout * 1000 && $exitCode !== 0));

        // Snapshot-Diff: was hat sich in /var/www geändert?
        $postMtimes = $this->snapshotMtimes();
        $this->snapshotChangedFiles($preMtimes, $postMtimes);

        // Output begrenzen
        $stdoutTrim = strlen($stdout) > self::BASH_OUTPUT_MAX
            ? mb_substr($stdout, 0, self::BASH_OUTPUT_MAX) . "\n[…gekürzt, " . strlen($stdout) . " Bytes total]"
            : $stdout;
        $stderrTrim = strlen($stderr) > self::BASH_OUTPUT_MAX
            ? mb_substr($stderr, 0, self::BASH_OUTPUT_MAX) . "\n[…gekürzt, " . strlen($stderr) . " Bytes total]"
            : $stderr;

        // Audit-Log
        $this->db->insert('coworker_bash_log', [
            'session_id' => $this->currentSessionId,
            'message_id' => $this->currentMessageId ?: null,
            'command' => $command,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'timed_out' => $timedOut ? 1 : 0,
        ]);

        $text = "Exit-Code: $exitCode" . ($timedOut ? ' (TIMEOUT)' : '')
            . "\nDauer: {$durationMs}ms"
            . "\n--- STDOUT ---\n" . ($stdoutTrim ?: '(leer)')
            . "\n--- STDERR ---\n" . ($stderrTrim ?: '(leer)');

        return [
            '__text' => $text,
            '__error' => $exitCode !== 0,
        ];
    }

    /**
     * Liest alle Dateien unter /var/www (ohne vendor/storage/cache) mit mtime — als Map.
     * Wird vor und nach jedem Bash-Call ausgeführt, um Datei-Änderungen zu erkennen.
     */
    private function snapshotMtimes(): array
    {
        $cmd = 'find /var/www -type f '
             . '-not -path "/var/www/vendor/*" '
             . '-not -path "/var/www/storage/cache/*" '
             . '-not -path "/var/www/storage/uploads/*" '
             . '-not -path "/var/www/storage/vectors/*" '
             . '-not -path "/var/www/.git/*" '
             . '-printf "%T@ %p\n" 2>/dev/null';
        $lines = [];
        $rc = 0;
        @exec($cmd, $lines, $rc);
        if (empty($lines)) return [];
        $map = [];
        foreach ($lines as $line) {
            $sp = strpos($line, ' ');
            if ($sp === false) continue;
            $mtime = (float) substr($line, 0, $sp);
            $path = substr($line, $sp + 1);
            $map[$path] = $mtime;
        }
        return $map;
    }

    /**
     * Findet alle Dateien, deren mtime sich geändert hat (oder neu sind / gelöscht),
     * und legt einen bash_touch-Snapshot pro Datei an.
     */
    private function snapshotChangedFiles(array $pre, array $post): void
    {
        $touched = [];
        foreach ($post as $path => $mtime) {
            if (!isset($pre[$path])) {
                $touched[$path] = ['op' => 'create', 'orig' => null];
            } elseif ($pre[$path] !== $mtime) {
                // Original-Content haben wir nicht mehr — Bash hat ihn überschrieben.
                // Wir loggen NULL als original_content; Rollback per find/restore ist
                // damit nur möglich wenn write_file vorher einen sauberen Snapshot hatte.
                // Für den Audit reicht es, die Datei-Liste zu haben.
                $touched[$path] = ['op' => 'write', 'orig' => null];
            }
        }
        foreach ($pre as $path => $mtime) {
            if (!isset($post[$path])) {
                $touched[$path] = ['op' => 'delete', 'orig' => null];
            }
        }
        foreach ($touched as $path => $info) {
            // Vermeide Doppel-Snapshot wenn write_file für diese Datei schon einen hat
            if (isset($this->snapshottedThisRun[$path])) continue;
            $newContent = ($info['op'] === 'delete' || !is_file($path)) ? null : @file_get_contents($path);
            if ($newContent !== null && strlen($newContent) > self::TRAIT_MAX_FILE_BYTES) {
                $newContent = '[zu groß für Snapshot — ' . strlen($newContent) . ' Bytes]';
            }
            $this->db->insert('coworker_snapshots', [
                'session_id' => $this->currentSessionId,
                'message_id' => $this->currentMessageId ?: null,
                'file_path' => $path,
                'operation' => $info['op'] === 'create' ? 'create' : ($info['op'] === 'delete' ? 'delete' : 'bash_touch'),
                'original_content' => null,
                'new_content' => $newContent,
                'file_existed' => $info['op'] === 'create' ? 0 : 1,
                'source' => 'bash',
            ]);
        }
    }

    // ====== Conversation-Loop ======

    private function runLoop(int $sessionId, callable $sseOut): void
    {
        $session = $this->db->queryOne("SELECT * FROM coworker_sessions WHERE id = ?", [$sessionId]);
        if (!$session) throw new \RuntimeException('Session nicht gefunden');

        $settings = [];
        $rows = $this->db->query("SELECT setting_key, setting_value FROM settings");
        foreach ($rows ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];
        $settings = \Core\Settings::decryptMap($settings);
        $anthropicKey = $settings['anthropic_api_key'] ?? '';
        if (empty($anthropicKey)) throw new \RuntimeException('Anthropic API-Key nicht konfiguriert');

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($anthropicKey, 'anthropic');
        $ai->setModel('claude-sonnet-4-6');
        $ai->setMaxTokens(self::ANTHROPIC_MAX_TOKENS);
        $ai->setTimeout(180);

        $messages = $this->loadMessages($sessionId);
        $tools = $this->getTools();
        $systemPrompt = $this->buildSystemPrompt();

        // Token-Baseline der Session (alte Turns) — die wird zur Iter-Usage addiert
        $baseIn = (int) ($session['tokens_input'] ?? 0);
        $baseOut = (int) ($session['tokens_output'] ?? 0);

        $self = $this;
        $dispatcher = function (string $name, array $args) use ($self, $sessionId) {
            return $self->dispatchTool($sessionId, $name, $args);
        };
        $onMessage = function (array $msg) use ($self, $sessionId) {
            $role = ($msg['role'] === 'user' && is_array($msg['content']) && isset($msg['content'][0]['type']) && $msg['content'][0]['type'] === 'tool_result')
                ? 'tool_result' : $msg['role'];
            $messageId = $self->_persistMessagePublic($sessionId, $role, $msg['content']);
            $self->_setCurrentMessageId($messageId);
        };
        // Live-Usage: nach jeder Iteration die Session in der DB updaten, sodass
        // shouldStop den aktuellen Cost-Wert sehen kann (sonst greift Cap erst am Turn-Ende).
        $onUsage = function (int $turnIn, int $turnOut) use ($self, $sessionId, $baseIn, $baseOut, $sseOut) {
            $totalIn = $baseIn + $turnIn;
            $totalOut = $baseOut + $turnOut;
            $cost = $self->_calculateCostPublic($totalIn, $totalOut);
            $self->_updateSessionUsage($sessionId, $totalIn, $totalOut, $cost);
            $sseOut('cost_update', ['tokens_input' => $totalIn, 'tokens_output' => $totalOut, 'cost_usd' => $cost]);
        };
        $shouldStop = function () use ($self, $sessionId) {
            return $self->_shouldSessionStop($sessionId);
        };

        try {
            $result = $ai->chatWithTools($systemPrompt, $messages, $tools, $dispatcher, $sseOut, self::MAX_ITERATIONS_PER_TURN, $onMessage, $shouldStop, $onUsage);

            // tokens + cost wurden bereits live von $onUsage in die DB geschrieben.
            // Hier nur noch Status + finales SSE-Event.
            $finalRow = $this->db->queryOne("SELECT tokens_input, tokens_output, cost_usd, cost_cap_usd FROM coworker_sessions WHERE id = ?", [$sessionId]);
            $tokensIn = (int) $finalRow['tokens_input'];
            $tokensOut = (int) $finalRow['tokens_output'];
            $cost = (float) $finalRow['cost_usd'];
            $cap = $finalRow['cost_cap_usd'] !== null ? (float) $finalRow['cost_cap_usd'] : null;

            if (!empty($result['awaiting_user'])) {
                $this->db->update('coworker_sessions', ['status' => 'awaiting_user'], 'id = ?', [$sessionId]);
                $sseOut('awaiting_user', ['question' => $result['question'] ?? '']);
                return;
            }
            if (!empty($result['stopped'])) {
                $this->db->update('coworker_sessions', ['status' => 'idle'], 'id = ?', [$sessionId]);
                $reason = ($cap !== null && $cost >= $cap) ? 'cost_cap' : 'manual';
                $sseOut('idle', ['stopped' => true, 'reason' => $reason]);
                if ($reason === 'cost_cap') $sseOut('cost_cap_reached', ['cost_usd' => $cost, 'cap' => $cap]);
                return;
            }

            $this->db->update('coworker_sessions', ['status' => 'idle'], 'id = ?', [$sessionId]);

            if (!empty($result['done'])) {
                $sseOut('done', ['summary' => $result['summary'] ?? '', 'iterations' => $result['iterations'] ?? 0]);
            } else {
                $sseOut('idle', []);
            }
            if ($cap !== null && $cost >= $cap) {
                $sseOut('cost_cap_reached', ['cost_usd' => $cost, 'cap' => $cap]);
            }
        } catch (\Throwable $e) {
            $this->db->update('coworker_sessions', [
                'status' => 'failed',
                'last_activity_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$sessionId]);
            $sseOut('error', ['message' => $e->getMessage()]);
        }
    }

    public function loadMessages(int $sessionId): array
    {
        $rows = $this->db->query(
            "SELECT role, content FROM coworker_messages WHERE session_id = ? ORDER BY id ASC",
            [$sessionId]
        ) ?: [];
        $messages = [];
        foreach ($rows as $r) {
            $decoded = json_decode((string) $r['content'], true);
            $messages[] = [
                'role' => $r['role'] === 'tool_result' ? 'user' : $r['role'],
                'content' => $decoded ?? (string) $r['content'],
            ];
        }
        return $messages;
    }

    private function persistMessage(int $sessionId, string $role, $content): int
    {
        return (int) $this->db->insert('coworker_messages', [
            'session_id' => $sessionId,
            'role' => $role,
            'content' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string) $content,
        ]);
    }

    /** Closure-Helper (PHP-Trait-Property-Access-Workaround) */
    public function _persistMessagePublic(int $sessionId, string $role, $content): int
    {
        return $this->persistMessage($sessionId, $role, $content);
    }

    public function _setCurrentMessageId(int $messageId): void
    {
        $this->currentMessageId = $messageId;
    }

    public function _calculateCostPublic(int $tokensIn, int $tokensOut): float
    {
        return $this->calculateCost($tokensIn, $tokensOut);
    }

    public function _updateSessionUsage(int $sessionId, int $tokensIn, int $tokensOut, float $cost): void
    {
        $this->db->update('coworker_sessions', [
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'cost_usd' => $cost,
            'last_activity_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$sessionId]);
    }

    public function _shouldSessionStop(int $sessionId): bool
    {
        $status = $this->db->queryValue("SELECT status FROM coworker_sessions WHERE id = ?", [$sessionId]);
        // Wenn jemand (z.B. /stop-Endpoint oder Cost-Cap) den Status auf idle/closed/failed
        // gesetzt hat während die Loop noch läuft → Loop beenden.
        if (in_array($status, ['idle', 'closed', 'failed'], true)) return true;

        // Cost-Cap-Check zur Laufzeit
        $row = $this->db->queryOne("SELECT cost_usd, cost_cap_usd FROM coworker_sessions WHERE id = ?", [$sessionId]);
        if ($row && $row['cost_cap_usd'] !== null && (float) $row['cost_usd'] >= (float) $row['cost_cap_usd']) {
            return true;
        }
        return false;
    }

    private function buildSystemPrompt(): string
    {
        return "Du bist ein erfahrener Code-Agent für ein PHP/MySQL/Vanilla-JS-Webprojekt unter /var/www.\n"
            . "Sprache: Deutsch. Echte Umlaute (ä ö ü ß) — KEIN ae/oe/ue/ss.\n\n"
            . "Du hast einen Chat-Modus mit persistentem Verlauf. Tools:\n"
            . "  - list_files, read_file (optional from_line/to_line), search_code  (lesen)\n"
            . "  - write_file                          (schreiben; bestimmte Pfade gesperrt)\n"
            . "  - bash                                 (volle Shell; Blacklist sperrt Katastrophen)\n"
            . "  - ask_user                            (pausiert für Rückfrage)\n"
            . "  - done                                 (beendet aktuellen Turn mit Zusammenfassung)\n\n"
            . "============== SPEED-FIRST: ACTION VOR ANALYSE ==============\n"
            . "Token-Kosten wachsen quadratisch mit jeder Iteration (jede Iter sendet die gesamte\n"
            . "History mit). Du hast eine **harte Obergrenze von 25 Iterationen** und ein\n"
            . "**Cost-Cap pro Session**. Effizienz ist überlebenswichtig.\n\n"
            . "ARBEITSWEISE:\n"
            . "1. **Mach max. 3-5 Lese-Operationen vor der ersten konkreten Aktion** (write_file,\n"
            . "    ask_user, done). Wenn du nach 5 Reads noch nicht weißt was zu tun ist, ist deine\n"
            . "    Strategie falsch — frage den User mit ask_user oder schreibe das was du weißt.\n"
            . "2. **search_code ist immer der erste Schritt** bei der Frage 'wo ist X im Code'. Niemals\n"
            . "    blind list_files-Bäume durchforsten oder cat auf große Dateien.\n"
            . "3. **read_file: bei großen Dateien Range-Read** (from_line/to_line). Eine 1000-Zeilen-\n"
            . "    Datei komplett zu lesen kostet ~25K Tokens, die du danach in JEDER Folge-Iteration\n"
            . "    erneut sendest. Lies nur die relevante Section.\n"
            . "4. **Bei UI-Tasks NIEMALS** das gesamte App.php oder views/layouts/main.php komplett\n"
            . "    in eine Iteration laden — nutze grep/search_code.\n"
            . "5. **Schema-Checks** via `mysql -e \"DESCRIBE customers\"` (kurz) statt sql/-Dateien zu lesen.\n"
            . "6. Code schreiben: write_file mit VOLLSTÄNDIGEM Inhalt der Datei (kein Diff).\n"
            . "7. Bei Mehrdeutigkeit: ask_user (NICHT raten, NICHT raten).\n"
            . "8. Am Ende: done(summary) — IMMER, auch bei Teil-Erfolg.\n\n"
            . "BENUTZER-SIGNALE LESEN:\n"
            . "- Kurze Antworten wie \"und?\", \"hm\", \"weiter\", \"hä\" = ungeduldig. SOFORT konkret\n"
            . "  werden: entweder ergebnis liefern oder ask_user mit präziser Frage. KEINE weitere\n"
            . "  Erkundung.\n"
            . "- Lange Erklärungen vom User = Kontext, ernst nehmen.\n\n"
            . "============== SICHERHEIT / SCOPE ==============\n"
            . "Schreibe NIE: services/CoworkerService.php, services/AdminCodeToolsTrait.php (Self-Schutz).\n"
            . "Gesperrt: config/config.php, core/Database.php, sql/, vendor/, storage/, cli/worker.php.\n\n"
            . "FAIL-FAST: Bei wiederholtem Fehler desselben Tools mit demselben Symptom (2×):\n"
            . "STOPPE. Keine Workarounds. done() mit klarer Erklärung.\n\n"
            . "VERBOTENE WORKAROUND-VERSUCHE (führen zu Loops und Token-Verbrennung):\n"
            . "- write_file scheitert: NIEMALS via bash umgehen (kein cp /tmp, sudo, tee, find -perm).\n"
            . "- Pfad gesperrt: NICHT andere Wege probieren — sag dem User Bescheid und stoppe.\n"
            . "- bash Blacklist: das ist Absicht. KEINE Alternativ-Befehle suchen.\n"
            . "- 'ABGELEHNT: write_file ohne content' = max_tokens überschritten, Datei zu groß für\n"
            . "  einen Turn. NICHT erneut probieren — done() mit Empfehlung an User.\n\n"
            . "PROJEKT LÄUFT ALS www-data. Falls Permission-Probleme: `sudo chown www-data:www-data /var/www/<pfad>`.";
    }

    /**
     * Anthropic Claude Sonnet 4.6 Preise: $3 / Mio Input, $15 / Mio Output.
     */
    private function calculateCost(int $tokensIn, int $tokensOut): float
    {
        return round(($tokensIn / 1_000_000) * 3 + ($tokensOut / 1_000_000) * 15, 4);
    }
}
