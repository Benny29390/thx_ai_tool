<?php
namespace Services;

use Core\Database;

/**
 * AdminTaskService
 *
 * Führt KI-Code-Tasks aus: Admin formuliert eine Anforderung, Claude ändert
 * mittels Tool-Use direkt den Code. Jede Schreib-Operation wird vor dem
 * Ausführen als Snapshot in admin_task_snapshots persistiert, damit ein
 * Rollback jederzeit möglich ist.
 *
 * Sicherheit:
 *   - strikte Pfad-Whitelist pro Scope (Path-Traversal-tolerant via realpath)
 *   - 500 KB max pro write_file
 *   - max 30 Tool-Aufrufe pro Task
 *   - Auto-Rollback bei PHP-Syntax-Fehler oder Fatal-Error im execute()
 */
require_once __DIR__ . '/AdminCodeToolsTrait.php';

class AdminTaskService
{
    use AdminCodeToolsTrait;

    public const ROOT = '/var/www';
    public const MAX_FILE_BYTES = 500 * 1024;

    /**
     * Module = Fokus-Bereiche, mit relevanten Pfaden + Beschreibung.
     * Werden im Frontend als Searchable-Select angezeigt und im System-Prompt mitgegeben.
     */
    public const MODULES = [
        'chat' => [
            'label' => 'Chat',
            'paths' => ['views/chat.php', 'api/v1/chat-stream.php', 'api/v1/chat-conversations.php', 'api/v1/chat-projects.php'],
            'description' => 'Chat-UI mit Sidebar, Streaming, Dual-Modus, Kontextmenü, Themen-Ordner, Papierkorb',
        ],
        'customers_overview' => [
            'label' => 'Kunden — Übersicht',
            'paths' => ['views/admin/customers.php'],
            'description' => 'Kunden-Liste mit Pill-Filter, FLIP-Animation, Card- & Listenansicht',
        ],
        'customer_steckbrief' => [
            'label' => 'Kunden-Steckbrief',
            'paths' => ['views/admin/customer-steckbrief.php', 'services/CustomerCardService.php', 'api/v1/admin/customer-cards.php'],
            'description' => 'Steckbrief mit System-Cards (Profil/Asana/Website/Wissen), Card-System, Tags, Multi-Domain',
        ],
        'customer_wizard' => [
            'label' => 'Kunden-Wizard (Neu anlegen)',
            'paths' => ['views/admin/customer-wizard.php', 'api/v1/admin/customer-wizard.php', 'api/v1/admin/customer-profile-suggest.php'],
            'description' => 'Wizard für neue Kunden mit KI-URL-Analyse',
        ],
        'knowledge' => [
            'label' => 'Wissensbasis',
            'paths' => ['views/knowledge/', 'services/KnowledgeIngestService.php', 'services/KnowledgeEmbeddingService.php', 'services/KnowledgeService.php', 'api/v1/knowledge/'],
            'description' => 'Wissens-Liste, RAG-Suche, Ingest-Pipeline (Chunks + Embeddings), Knowledge-Graph',
        ],
        'website_crawler' => [
            'label' => 'Website-Crawler',
            'paths' => ['services/WebsiteCrawler.php', 'services/WebsiteSyncService.php', 'api/v1/admin/customer-website-crawl.php', 'cli/website-sync.php'],
            'description' => 'Crawler für Kunden-Websites, Multi-Domain, Diff-basierter Re-Sync, Sitemap-Ansicht',
        ],
        'asana_sync' => [
            'label' => 'Asana-Sync',
            'paths' => ['services/AsanaService.php', 'services/AsanaSyncService.php', 'cli/asana-sync.php'],
            'description' => 'Asana-Projekt-Sync ins Wissen',
        ],
        'guidelines' => [
            'label' => 'Guidelines',
            'paths' => ['views/guidelines/', 'services/GuidelineService.php', 'api/v1/guidelines.php'],
            'description' => 'Kundenübergreifende Verhaltensvorgaben für die KI',
        ],
        'canvas' => [
            'label' => 'KI-Kompass (Canvas)',
            'paths' => ['views/canvas/', 'services/CanvasService.php', 'services/CanvasAIService.php', 'api/v1/canvas/'],
            'description' => 'Briefing-/Sparring-Canvas mit KI-Dialog',
        ],
        'dashboard' => [
            'label' => 'Dashboard',
            'paths' => ['views/admin/dashboard.php'],
            'description' => 'Startseite mit Hero, Tiles und Stats',
        ],
        'users_auth' => [
            'label' => 'Benutzer & Auth',
            'paths' => ['views/admin/users.php', 'views/auth/', 'core/Auth.php', 'api/v1/admin/users.php'],
            'description' => 'User-Verwaltung, Login, Rollen-Management',
        ],
        'settings' => [
            'label' => 'Einstellungen',
            'paths' => ['views/admin/settings.php', 'views/settings/', 'api/v1/admin/settings.php', 'api/v1/me/'],
            'description' => 'System-Einstellungen + Mein Konto (Profil, Asana-Link)',
        ],
        'navigation' => [
            'label' => 'Sidebar / Navigation',
            'paths' => ['views/layouts/main.php', 'assets/css/style.css'],
            'description' => 'Top-Bar, Sidebar-Nav, globales Layout',
        ],
        'ki_coworker' => [
            'label' => 'KI-Coworker (diese Seite)',
            'paths' => ['views/admin/tasks.php', 'services/AdminTaskService.php', 'api/v1/admin/tasks.php'],
            'description' => 'Code-Editor mit KI (vorsichtig editieren — Self-Schutz für AdminTaskService.php greift)',
        ],
        'ai_service' => [
            'label' => 'AI-Service (Provider-Layer)',
            'paths' => ['services/AIService.php', 'services/UsageTracker.php'],
            'description' => 'OpenAI/Anthropic/Google-API-Wrapper mit Streaming + Tool-Use',
        ],
        'jobs' => [
            'label' => 'Job-Queue / Worker',
            'paths' => ['views/admin/jobs.php', 'services/JobQueue.php'],
            'description' => 'Hintergrund-Jobs (NICHT cli/worker.php — gesperrt)',
        ],
    ];

    /** Pfade, die NIE geändert werden dürfen — unabhängig vom Scope */
    public const FORBIDDEN_ALWAYS = [
        'config/config.php',
        'core/Database.php',
        'sql/',
        'vendor/',
        'storage/',
        '.env',
        'cli/worker.php',
        'services/AdminTaskService.php',   // Self-Schutz
        'services/AdminCodeToolsTrait.php', // Trait-Schutz
        'services/CoworkerService.php',     // Coworker auch nicht via Auftrags-Modus änderbar
    ];

    private Database $db;
    /** @var int während dispatchTool gesetzt — von persistWriteSnapshot benutzt */
    private int $currentTaskId = 0;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    protected function getForbiddenAlways(): array
    {
        return self::FORBIDDEN_ALWAYS;
    }

    protected function persistWriteSnapshot(string $absPath, ?string $originalContent, string $newContent, bool $existed): int
    {
        return (int) $this->db->insert('admin_task_snapshots', [
            'task_id' => $this->currentTaskId,
            'file_path' => $absPath,
            'operation' => $existed ? 'write' : 'create',
            'original_content' => $originalContent,
            'new_content' => $newContent,
            'file_existed' => $existed ? 1 : 0,
        ]);
    }

    protected function markSnapshotRolledBack(int $snapshotId): void
    {
        $this->db->update('admin_task_snapshots',
            ['rolled_back_at' => date('Y-m-d H:i:s')],
            'id = ?', [$snapshotId]);
    }

    public function getTools(): array
    {
        return [
            [
                'name' => 'list_files',
                'description' => 'Listet Dateien in einem Verzeichnis (relativ zu /var/www, z.B. "views/admin/"). Nur Dateien im erlaubten Scope erscheinen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'directory' => ['type' => 'string', 'description' => 'Verzeichnis relativ zu /var/www, z.B. "views/admin"'],
                    ],
                    'required' => ['directory'],
                ],
            ],
            [
                'name' => 'read_file',
                'description' => 'Liest den Inhalt einer Datei (relativ zu /var/www). Bei großen Dateien (>500 Zeilen) from_line/to_line nutzen — sonst wird die Conversation mit jeder Iter exponentiell teurer.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Datei-Pfad relativ zu /var/www'],
                        'from_line' => ['type' => 'integer', 'description' => '1-basiert, optional Range-Start'],
                        'to_line' => ['type' => 'integer', 'description' => '1-basiert, optional Range-Ende'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'search_code',
                'description' => 'Sucht eine Zeichenkette im Code (grep-artig) und gibt Treffer mit Zeilennummer + Snippet zurück. Maximal 40 Treffer.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Suchbegriff (Literal-String)'],
                        'directory' => ['type' => 'string', 'description' => 'Optional: Verzeichnis zum Eingrenzen, z.B. "views/admin"'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'write_file',
                'description' => 'Schreibt eine Datei. VORHER wird ein Snapshot gespeichert. Bei PHP-Syntax-Fehler wird die Datei automatisch zurückgerollt und ein Fehler zurückgegeben. Pfad-Whitelist nach Scope.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Datei-Pfad relativ zu /var/www'],
                        'content' => ['type' => 'string', 'description' => 'Vollständiger neuer Datei-Inhalt'],
                    ],
                    'required' => ['path', 'content'],
                ],
            ],
            [
                'name' => 'ask_user',
                'description' => 'Stellt dem Admin eine Rückfrage und PAUSIERT die Task, bis er antwortet. Nutze das, wenn der Auftrag mehrdeutig ist (z.B. „Welche Farbe genau?" / „In welcher Datei?") — NICHT für triviale Annahmen, da kannst du selbst entscheiden.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'question' => ['type' => 'string', 'description' => 'Konkrete Frage an den Admin'],
                    ],
                    'required' => ['question'],
                ],
            ],
            [
                'name' => 'done',
                'description' => 'Schließt die Task ab und gibt eine kurze Zusammenfassung der durchgeführten Änderungen zurück.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string', 'description' => 'Kurzer Bericht: was wurde geändert und warum'],
                    ],
                    'required' => ['summary'],
                ],
            ],
        ];
    }

    /**
     * Tool-Dispatcher — wird aus AIService::chatWithTools aufgerufen
     */
    public function dispatchTool(int $taskId, string $scope, string $toolName, array $args): array
    {
        $this->currentTaskId = $taskId;
        try {
            switch ($toolName) {
                case 'list_files':  return $this->toolListFiles($scope, (string) ($args['directory'] ?? ''));
                case 'read_file':   return $this->toolReadFile($scope, (string) ($args['path'] ?? ''),
                                       isset($args['from_line']) ? (int) $args['from_line'] : null,
                                       isset($args['to_line']) ? (int) $args['to_line'] : null);
                case 'search_code': return $this->toolSearchCode($scope, (string) ($args['query'] ?? ''), (string) ($args['directory'] ?? ''));
                case 'write_file':  return $this->toolWriteFile($scope, (string) ($args['path'] ?? ''), (string) ($args['content'] ?? ''));
                case 'ask_user':    return ['__text' => 'Frage gestellt — warte auf Antwort.', '__ask_user' => true, '__ask_user_question' => (string) ($args['question'] ?? '')];
                case 'done':        return ['__text' => 'OK', '__done' => true];
                default:            return ['__error' => true, '__text' => "Unbekanntes Tool: $toolName"];
            }
        } catch (\Throwable $e) {
            return ['__error' => true, '__text' => 'Tool-Exception: ' . $e->getMessage()];
        }
    }

    // ====== Tool-Implementierungen kommen aus AdminCodeToolsTrait ======

    // (Tool-Implementierungen, Path-Whitelist und Validierung kommen aus AdminCodeToolsTrait)

    // ====== Rollback ======

    /**
     * Rollt alle Snapshots einer Task rückwärts zurück (jüngste zuerst).
     */
    public function rollback(int $taskId, ?string $reason = null): array
    {
        $rows = $this->db->query(
            "SELECT * FROM admin_task_snapshots WHERE task_id = ? AND rolled_back_at IS NULL ORDER BY id DESC",
            [$taskId]
        ) ?: [];
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
                $this->db->update('admin_task_snapshots', ['rolled_back_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $snap['id']]);
                $restored++;
            } catch (\Throwable $e) {
                $failed++;
                error_log('AdminTask rollback failed: ' . $abs . ' — ' . $e->getMessage());
            }
        }
        // Task-Status update
        $this->db->update('admin_tasks', [
            'status' => 'rolled_back',
            'error_message' => $reason,
        ], 'id = ?', [$taskId]);
        return ['restored' => $restored, 'failed' => $failed];
    }

    /**
     * Vollständige Task-Ausführung — wird vom SSE-Endpoint gerufen.
     *
     * @param int $taskId
     * @param callable $sseOut  fn(string $event, array $data)
     */
    /**
     * Lädt persistierte Conversation-History einer Task im Anthropic-Format.
     */
    public function loadMessages(int $taskId): array
    {
        $rows = $this->db->query(
            "SELECT role, content, tool_use_id, tool_is_error FROM admin_task_messages WHERE task_id = ? ORDER BY id ASC",
            [$taskId]
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

    private function persistMessage(int $taskId, string $role, $content): void
    {
        $this->db->insert('admin_task_messages', [
            'task_id' => $taskId,
            'role' => $role,
            'content' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : (string) $content,
        ]);
    }

    private function buildSystemPrompt(string $scope, ?string $module): string
    {
        $prompt = "Du bist ein erfahrener Code-Editor für ein PHP/MySQL/Vanilla-JS-Webprojekt unter /var/www.\n"
            . "Sprache: Deutsch. Echte Umlaute (ä ö ü ß) — KEIN ae/oe/ue/ss.\n"
            . "Du bekommst Tools (list_files, read_file, search_code, write_file, ask_user, done).\n"
            . "Aktueller Scope: $scope (nur erlaubte Pfade — alles andere wirft Fehler).\n\n"
            . "WORKFLOW:\n"
            . "1. Finde die richtige(n) Datei(en) mit search_code/list_files\n"
            . "2. Lies sie IMMER zuerst mit read_file (auch wenn du sie zu kennen glaubst)\n"
            . "3. Bei write_file: schicke den GESAMTEN neuen Dateiinhalt mit (KEIN Diff!).\n"
            . "   Die Datei wird komplett ersetzt — wenn du nur 1 Zeile änderst,\n"
            . "   musst du den unveränderten Rest trotzdem komplett mitschicken.\n"
            . "   Niemals nur den geänderten Ausschnitt.\n"
            . "4. Sei minimal-invasiv im Inhalt: ändere nur, was die Anforderung verlangt.\n"
            . "5. Bei Mehrdeutigkeit: ask_user statt zu raten\n"
            . "6. Am Ende: done(summary) mit kurzem Bericht\n\n"
            . "Wenn write_file mit Fehler zurückkommt (Syntax oder Abgelehnt): lies die Datei erneut\n"
            . "mit read_file und schicke den korrekten VOLLSTÄNDIGEN Inhalt.\n"
            . "Wenn ein Pfad nicht im Scope ist: erkläre dem User, dass der Scope erweitert werden muss.";

        if ($module && isset(self::MODULES[$module])) {
            $m = self::MODULES[$module];
            $prompt .= "\n\nMODUL-FOKUS: \"" . $m['label'] . "\"\n";
            $prompt .= $m['description'] . "\n";
            $prompt .= "Relevante Pfade (Start hier):\n  - " . implode("\n  - ", $m['paths']) . "\n";
        }
        return $prompt;
    }

    public function execute(int $taskId, callable $sseOut): void
    {
        $task = $this->db->queryOne("SELECT * FROM admin_tasks WHERE id = ?", [$taskId]);
        if (!$task) throw new \RuntimeException('Task nicht gefunden');
        $scope = $task['scope'];
        $prompt = $task['prompt'];
        $module = $task['module'] ?? null;

        // Settings für API-Key (Secrets liegen verschluesselt — transparent dekryptieren)
        $settingsRows = $this->db->query("SELECT setting_key, setting_value FROM settings");
        $settings = []; foreach ($settingsRows ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];
        $settings = \Core\Settings::decryptMap($settings);
        $anthropicKey = $settings['anthropic_api_key'] ?? '';
        if (empty($anthropicKey)) throw new \RuntimeException('Anthropic API-Key nicht konfiguriert (settings.anthropic_api_key)');

        $isNew = empty($task['started_at']);
        if ($isNew) {
            $this->db->update('admin_tasks', [
                'status' => 'running',
                'started_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);
            // Erste User-Message persistieren
            $this->persistMessage($taskId, 'user', $prompt);
        } else {
            $this->db->update('admin_tasks', ['status' => 'running'], 'id = ?', [$taskId]);
        }
        $sseOut('start', ['task_id' => $taskId, 'scope' => $scope, 'module' => $module]);

        // Conversation-History aus DB laden (bei Resume mit gesamter Historie)
        $messages = $this->loadMessages($taskId);

        // AI-Service
        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($anthropicKey, 'anthropic');
        $ai->setModel('claude-sonnet-4-6');
        $ai->setMaxTokens(8000);
        $ai->setTimeout(180);

        $systemPrompt = $this->buildSystemPrompt($scope, $module);
        $tools = $this->getTools();
        $self = $this;
        $dispatcher = function (string $name, array $args) use ($self, $taskId, $scope) {
            return $self->dispatchTool($taskId, $scope, $name, $args);
        };
        $onMessage = function (array $msg) use ($self, $taskId) {
            // Tool-Result-Messages bekommen 'role' = 'user', wir speichern sie aber als 'tool_result' für klare Filterung
            $role = ($msg['role'] === 'user' && is_array($msg['content']) && isset($msg['content'][0]['type']) && $msg['content'][0]['type'] === 'tool_result')
                ? 'tool_result' : $msg['role'];
            $self->_persistMessagePublic($taskId, $role, $msg['content']);
        };

        try {
            $result = $ai->chatWithTools($systemPrompt, $messages, $tools, $dispatcher, $sseOut, 30, $onMessage);

            $tokensIn = (int) ($result['tokens_input'] ?? 0) + (int) ($task['tokens_input'] ?? 0);
            $tokensOut = (int) ($result['tokens_output'] ?? 0) + (int) ($task['tokens_output'] ?? 0);

            if (!empty($result['awaiting_user'])) {
                // KI hat ask_user benutzt → Task pausiert
                $this->db->update('admin_tasks', [
                    'status' => 'awaiting_user',
                    'tokens_input' => $tokensIn,
                    'tokens_output' => $tokensOut,
                ], 'id = ?', [$taskId]);
                $sseOut('awaiting_user', ['question' => $result['question'] ?? '']);
                return;
            }

            $changes = (int) ($this->db->queryValue("SELECT COUNT(*) FROM admin_task_snapshots WHERE task_id = ? AND rolled_back_at IS NULL", [$taskId]) ?? 0);
            $this->db->update('admin_tasks', [
                'status' => 'completed',
                'summary' => $result['summary'] ?? '',
                'tokens_input' => $tokensIn,
                'tokens_output' => $tokensOut,
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);
            $sseOut('done', [
                'summary' => $result['summary'] ?? '',
                'files_changed' => $changes,
                'iterations' => $result['iterations'] ?? 0,
                'tokens_input' => $tokensIn,
                'tokens_output' => $tokensOut,
            ]);
        } catch (\Throwable $e) {
            $rb = $this->rollback($taskId, $e->getMessage());
            $this->db->update('admin_tasks', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);
            $sseOut('error', [
                'message' => $e->getMessage(),
                'rolled_back' => true,
                'restored_files' => $rb['restored'],
            ]);
        }
    }

    /**
     * Bei einer awaiting_user-Task: User-Antwort hinzufügen und Loop fortsetzen.
     */
    public function reply(int $taskId, string $replyText, callable $sseOut): void
    {
        $task = $this->db->queryOne("SELECT * FROM admin_tasks WHERE id = ?", [$taskId]);
        if (!$task) throw new \RuntimeException('Task nicht gefunden');
        if ($task['status'] !== 'awaiting_user') {
            throw new \RuntimeException('Task ist nicht im Status awaiting_user (aktuell: ' . $task['status'] . ')');
        }
        // User-Antwort an Conversation anhängen, dann normal execute (lädt selbst die History)
        $this->persistMessage($taskId, 'user', $replyText);
        $this->execute($taskId, $sseOut);
    }

    /** Public wrapper für use in closures (PHP 7 closure-binding-Problem umgehen) */
    public function _persistMessagePublic(int $taskId, string $role, $content): void
    {
        $this->persistMessage($taskId, $role, $content);
    }
}
