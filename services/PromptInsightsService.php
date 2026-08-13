<?php
namespace Services;

use Core\Database;

/**
 * PromptInsightsService — Import + Anonymisierung + Statistik + Clustering + Regelableitung.
 *
 * Briefing: docs/Prompt-Insights_Briefing.md
 * Discovery: docs/prompt-insights-discovery.md
 *
 * Speichert NUR anonymisierte Inhalte in der DB. ZIP-Datei wird nach Import gelöscht.
 */
class PromptInsightsService
{
    public const MAX_ZIP_SIZE = 200 * 1024 * 1024;  // 200 MB
    public const ASSISTANT_EXCERPT_LEN = 500;
    private const PLACEHOLDER_EMAIL = '<EMAIL>';
    private const PLACEHOLDER_PHONE = '<PHONE>';
    private const PLACEHOLDER_IBAN  = '<IBAN>';
    private const PLACEHOLDER_URL   = '<URL>';
    private const PLACEHOLDER_NAME  = '<NAME>';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /* ====================================================================
       IMPORT
       ==================================================================== */

    /**
     * Importiert eine hochgeladene ZIP-Datei mit Chatverläufen.
     * Erkennt Format (Claude / ChatGPT), parst, anonymisiert, speichert.
     * Returnt ['import_id' => int, 'chats' => int, 'messages' => int, 'source' => string]
     */
    public function importZip(string $tmpZipPath, string $origFilename, int $userId): array
    {
        if (!file_exists($tmpZipPath)) {
            throw new \RuntimeException('Upload-Datei nicht gefunden');
        }
        if (filesize($tmpZipPath) > self::MAX_ZIP_SIZE) {
            throw new \RuntimeException('ZIP > ' . (self::MAX_ZIP_SIZE / 1024 / 1024) . ' MB — bitte aufteilen');
        }

        // Import-Datensatz anlegen (Status: processing)
        $importId = (int)$this->db->insert('pi_imports', [
            'user_id'  => $userId,
            'filename' => $origFilename,
            'source'   => 'unknown',
            'status'   => 'processing',
        ]);

        // Whitelist beim ersten Import dieses Users automatisch vorschlagen
        $this->initWhitelistIfEmpty($userId);
        $whitelist = $this->loadWhitelist($userId);

        try {
            $tmpDir = sys_get_temp_dir() . '/pi-' . $importId;
            @mkdir($tmpDir, 0700, true);

            $zip = new \ZipArchive();
            if ($zip->open($tmpZipPath) !== true) {
                throw new \RuntimeException('ZIP konnte nicht geöffnet werden');
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            // Format erkennen
            $convFile = $this->findConversationsJson($tmpDir);
            if (!$convFile) {
                throw new \RuntimeException('conversations.json nicht im ZIP gefunden');
            }
            $raw = file_get_contents($convFile);
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                throw new \RuntimeException('conversations.json nicht parsebar als JSON');
            }
            $source = $this->detectSource($data);

            // Parsen je nach Format
            $chats = $source === 'claude' ? $this->parseClaude($data) : ($source === 'chatgpt' ? $this->parseChatGpt($data) : []);
            if (empty($chats)) {
                throw new \RuntimeException('Keine Chats erkannt — unbekanntes Format?');
            }

            // Speichern (alle Nachrichten anonymisiert)
            $totalMessages = 0;
            foreach ($chats as $chat) {
                $chatId = (int)$this->db->insert('pi_chats', [
                    'import_id'         => $importId,
                    'external_chat_id'  => mb_substr((string)($chat['external_id'] ?? ''), 0, 120),
                    'title'             => mb_substr((string)($chat['title'] ?? ''), 0, 500),
                    'source'            => $source,
                    'created_at_ext'    => $chat['created_at'] ?? null,
                    'updated_at_ext'    => $chat['updated_at'] ?? null,
                    'message_count'     => count($chat['messages']),
                ]);
                $position = 0;
                $userMsgIndex = 0;
                $lastAssistantContent = '';
                foreach ($chat['messages'] as $msg) {
                    $role = $msg['role'] ?? 'user';
                    $content = (string)($msg['content'] ?? '');
                    if ($content === '') continue;
                    $contentAnon = $this->anonymize($content, $whitelist);

                    // Assistant-Excerpt für User-Nachricht: nächste Assistant-Antwort
                    $excerpt = null;
                    if ($role === 'user') {
                        $excerpt = $this->findNextAssistant($chat['messages'], $position);
                        if ($excerpt) $excerpt = mb_substr($this->anonymize($excerpt, $whitelist), 0, self::ASSISTANT_EXCERPT_LEN);
                    }

                    $sentAt = $msg['sent_at'] ?? null;
                    $ts = $sentAt ? strtotime($sentAt) : null;
                    $weekday = $ts ? (int)date('N', $ts) : null;
                    $hour    = $ts ? (int)date('G', $ts) : null;

                    $this->db->insert('pi_messages', [
                        'chat_id'           => $chatId,
                        'position'          => $position,
                        'role'              => $role,
                        'content_anon'      => $contentAnon,
                        'assistant_excerpt' => $excerpt,
                        'word_count'        => $this->wordCount($contentAnon),
                        'char_count'        => mb_strlen($contentAnon),
                        'has_attachment'    => !empty($msg['has_attachment']) ? 1 : 0,
                        'attachment_type'   => mb_substr((string)($msg['attachment_type'] ?? ''), 0, 60),
                        'is_initial'        => ($role === 'user' && $userMsgIndex === 0) ? 1 : 0,
                        'sent_at'           => $sentAt,
                        'weekday'           => $weekday,
                        'hour'              => $hour,
                    ]);
                    if ($role === 'user') $userMsgIndex++;
                    $totalMessages++;
                    $position++;
                }
            }

            $this->db->update('pi_imports', [
                'source'        => $source,
                'chat_count'    => count($chats),
                'message_count' => $totalMessages,
                'status'        => 'done',
            ], 'id = ?', [$importId]);

            // Aufräumen
            $this->rrmdir($tmpDir);
            @unlink($tmpZipPath);

            return [
                'import_id' => $importId,
                'chats'     => count($chats),
                'messages'  => $totalMessages,
                'source'    => $source,
            ];
        } catch (\Throwable $e) {
            $this->db->update('pi_imports', [
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ], 'id = ?', [$importId]);
            if (isset($tmpDir)) $this->rrmdir($tmpDir);
            @unlink($tmpZipPath);
            throw $e;
        }
    }

    /* ==================== Format-Erkennung + Parser ==================== */

    private function findConversationsJson(string $dir): ?string
    {
        $direct = $dir . '/conversations.json';
        if (file_exists($direct)) return $direct;
        foreach (glob($dir . '/*/conversations.json') ?: [] as $found) return $found;
        return null;
    }

    private function detectSource(array $data): string
    {
        if (!isset($data[0])) return 'unknown';
        $first = $data[0];
        // ChatGPT: 'mapping' (Tree-Struktur), 'create_time' float
        if (isset($first['mapping']) && isset($first['create_time'])) return 'chatgpt';
        // Claude: 'chat_messages' Array, 'uuid' + 'created_at' ISO
        if (isset($first['chat_messages']) || isset($first['uuid'])) return 'claude';
        return 'unknown';
    }

    /** Parser für Claude.ai-Export (conversations.json: Array von Chats mit chat_messages). */
    private function parseClaude(array $data): array
    {
        $chats = [];
        foreach ($data as $chat) {
            if (!isset($chat['chat_messages']) || !is_array($chat['chat_messages'])) continue;
            $messages = [];
            foreach ($chat['chat_messages'] as $msg) {
                $role = ($msg['sender'] ?? '') === 'human' ? 'user' : 'assistant';
                $text = $this->joinContentArray($msg['content'] ?? $msg['text'] ?? '');
                if ($text === '') continue;
                $messages[] = [
                    'role'           => $role,
                    'content'        => $text,
                    'sent_at'        => $this->normalizeTs($msg['created_at'] ?? ''),
                    'has_attachment' => !empty($msg['attachments']),
                    'attachment_type' => !empty($msg['attachments'][0]['file_type']) ? $msg['attachments'][0]['file_type'] : null,
                ];
            }
            if (empty($messages)) continue;
            $chats[] = [
                'external_id' => $chat['uuid'] ?? null,
                'title'       => $chat['name'] ?? '',
                'created_at'  => isset($chat['created_at']) ? $this->normalizeTs($chat['created_at']) : null,
                'updated_at'  => isset($chat['updated_at']) ? $this->normalizeTs($chat['updated_at']) : null,
                'messages'    => $messages,
            ];
        }
        return $chats;
    }

    /** Parser für ChatGPT-Export (mapping ist ein Tree, Reihenfolge via children-Liste). */
    private function parseChatGpt(array $data): array
    {
        $chats = [];
        foreach ($data as $chat) {
            if (!isset($chat['mapping'])) continue;
            $mapping = $chat['mapping'];
            // Tree linearisieren: starte beim root (parent == null), gehe immer ins erste Kind
            $root = null;
            foreach ($mapping as $nid => $node) {
                if (empty($node['parent'])) { $root = $nid; break; }
            }
            if (!$root) continue;
            $messages = [];
            $cursor = $root;
            $guard = 0;
            while ($cursor && $guard++ < 5000) {
                $node = $mapping[$cursor] ?? null;
                if (!$node) break;
                $msg = $node['message'] ?? null;
                if ($msg && isset($msg['author']['role'], $msg['content']['parts'])) {
                    $role = $msg['author']['role'] === 'user' ? 'user' : ($msg['author']['role'] === 'assistant' ? 'assistant' : 'system');
                    $text = $this->joinContentArray($msg['content']['parts']);
                    if ($text !== '' && $role !== 'system') {
                        $ts = $msg['create_time'] ?? null;
                        $messages[] = [
                            'role'           => $role,
                            'content'        => $text,
                            'sent_at'        => $ts ? date('Y-m-d H:i:s', (int)$ts) : null,
                            'has_attachment' => !empty($msg['metadata']['attachments']),
                            'attachment_type' => !empty($msg['metadata']['attachments'][0]['mime_type']) ? $msg['metadata']['attachments'][0]['mime_type'] : null,
                        ];
                    }
                }
                $children = $node['children'] ?? [];
                $cursor = $children[0] ?? null;
            }
            if (empty($messages)) continue;
            $chats[] = [
                'external_id' => $chat['id'] ?? $chat['conversation_id'] ?? null,
                'title'       => $chat['title'] ?? '',
                'created_at'  => !empty($chat['create_time']) ? date('Y-m-d H:i:s', (int)$chat['create_time']) : null,
                'updated_at'  => !empty($chat['update_time']) ? date('Y-m-d H:i:s', (int)$chat['update_time']) : null,
                'messages'    => $messages,
            ];
        }
        return $chats;
    }

    private function joinContentArray($content): string
    {
        if (is_string($content)) return trim($content);
        if (!is_array($content)) return '';
        $parts = [];
        foreach ($content as $part) {
            if (is_string($part)) { $parts[] = $part; continue; }
            if (is_array($part)) {
                if (isset($part['text']))  $parts[] = $part['text'];
                elseif (isset($part['value'])) $parts[] = $part['value'];
                elseif (isset($part['content'])) $parts[] = is_string($part['content']) ? $part['content'] : json_encode($part['content']);
            }
        }
        return trim(implode("\n", array_filter($parts)));
    }

    private function normalizeTs(?string $ts): ?string
    {
        if (!$ts) return null;
        $t = strtotime($ts);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    private function findNextAssistant(array $messages, int $fromIdx): ?string
    {
        for ($i = $fromIdx + 1; $i < count($messages); $i++) {
            if (($messages[$i]['role'] ?? '') === 'assistant') {
                return (string)$messages[$i]['content'];
            }
        }
        return null;
    }

    /* ==================== ANONYMISIERUNG ==================== */

    /**
     * Anonymisiert Klartext: Mails, Telefonnummern, IBANs, URLs, Whitelist-Eigennamen.
     * Reihenfolge wichtig: Eigennamen zuerst (sonst frisst URL-Regex „https://example.com/Maxmustermann").
     */
    public function anonymize(string $text, array $whitelist): string
    {
        if ($text === '') return '';
        // 1. Whitelist (Eigennamen) — exakte Word-Boundary, case-insensitive
        foreach ($whitelist as $entry) {
            $original = $entry['original'];
            $placeholder = $entry['placeholder'] ?: self::PLACEHOLDER_NAME;
            if ($original === '') continue;
            // Vollwort-Match, case-insensitive
            $text = preg_replace('/\b' . preg_quote($original, '/') . '\b/iu', $placeholder, $text) ?? $text;
        }
        // 2. E-Mail
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', self::PLACEHOLDER_EMAIL, $text) ?? $text;
        // 3. IBAN (DE + generisch ISO13616: 2 Buchstaben + 2 Ziffern + bis zu 30 alphanum)
        $text = preg_replace('/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/', self::PLACEHOLDER_IBAN, $text) ?? $text;
        // 4. Deutsche Telefonnummern (relativ tolerant: +49, 0, mit/ohne Trennzeichen, mind. 7 Ziffern)
        $text = preg_replace('/(?:\+49|0049|0)[\s\-\.\/]?\d(?:[\s\-\.\/]?\d){6,13}/', self::PLACEHOLDER_PHONE, $text) ?? $text;
        // 5. URL (http/https/www)
        $text = preg_replace('/\bhttps?:\/\/[^\s<>"\']+/i', self::PLACEHOLDER_URL, $text) ?? $text;
        $text = preg_replace('/\bwww\.[^\s<>"\']+/i', self::PLACEHOLDER_URL, $text) ?? $text;
        return $text;
    }

    /** Last-Mile-Check vor Outbound-LLM-Call (Layer 4). Returnt bool: enthält noch PII? */
    public function containsPii(string $text): bool
    {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text)) return true;
        if (preg_match('/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/', $text)) return true;
        if (preg_match('/(?:\+49|0049|0)[\s\-\.\/]?\d(?:[\s\-\.\/]?\d){6,13}/', $text)) return true;
        return false;
    }

    /* ==================== WHITELIST ==================== */

    public function loadWhitelist(int $userId): array
    {
        return $this->db->query(
            "SELECT id, original, placeholder, source FROM pi_whitelist WHERE user_id = ? ORDER BY LENGTH(original) DESC",
            [$userId]
        ) ?: [];
    }

    /** Beim ersten Aufruf eines Users: Kunden + Team auto-vorschlagen. */
    public function initWhitelistIfEmpty(int $userId): int
    {
        $existing = (int)$this->db->queryValue("SELECT COUNT(*) FROM pi_whitelist WHERE user_id = ?", [$userId]);
        if ($existing > 0) return 0;
        $added = 0;
        // Kunden
        $custs = $this->db->query("SELECT name, abbreviation FROM customers WHERE is_active = 1") ?: [];
        foreach ($custs as $c) {
            foreach (array_filter([$c['name'], $c['abbreviation']]) as $term) {
                if (mb_strlen($term) < 2) continue;
                try {
                    $this->db->insert('pi_whitelist', [
                        'user_id'     => $userId,
                        'original'    => $term,
                        'placeholder' => '<KUNDE>',
                        'source'      => 'auto-customer',
                    ]);
                    $added++;
                } catch (\Throwable $_) {}
            }
        }
        // Team-Members + User-Names (Vollname, Spitzname, Kürzel)
        $people = $this->db->query(
            "SELECT DISTINCT u.name, u.abbreviation, u.nickname, t.name AS team_name, t.abbreviation AS team_abbr
             FROM users u
             LEFT JOIN pp_team_members t ON t.user_id = u.id
             WHERE u.is_active = 1"
        ) ?: [];
        foreach ($people as $p) {
            foreach (array_filter([$p['name'], $p['abbreviation'], $p['nickname'], $p['team_name'], $p['team_abbr']]) as $term) {
                if (mb_strlen($term) < 2) continue;
                try {
                    $this->db->insert('pi_whitelist', [
                        'user_id'     => $userId,
                        'original'    => $term,
                        'placeholder' => '<PERSON>',
                        'source'      => 'auto-person',
                    ]);
                    $added++;
                } catch (\Throwable $_) {}
            }
        }
        return $added;
    }

    /* ==================== HELPERS ==================== */

    private function wordCount(string $s): int
    {
        return count(preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /* ==================== LISTING / DELETE ==================== */

    public function listImports(int $userId): array
    {
        return $this->db->query(
            "SELECT id, filename, source, imported_at, chat_count, message_count, status, error_message
             FROM pi_imports WHERE user_id = ? ORDER BY imported_at DESC",
            [$userId]
        ) ?: [];
    }

    public function deleteImport(int $importId, int $userId): bool
    {
        $row = $this->db->queryOne("SELECT id FROM pi_imports WHERE id = ? AND user_id = ?", [$importId, $userId]);
        if (!$row) return false;
        // CASCADE loescht pi_chats → pi_messages → pi_embeddings, pi_cluster_assignments
        $this->db->execute("DELETE FROM pi_imports WHERE id = ?", [$importId]);
        // Foreign-Key pi_clusters.import_id ist SET NULL (damit Cluster ueber mehrere
        // Imports leben koennen). Beim Loeschen raeumen wir Cluster/Rules dieses Users
        // weg, wenn sie keinen lebenden Import + keine Assignments mehr haben.
        $this->cleanupOrphanedClustersAndRules($userId);
        return true;
    }

    /**
     * Loescht „verwaiste" Cluster und Rules eines Users:
     *   - Cluster ohne lebenden Import (import_id NULL) UND ohne Assignments
     *   - Rules ohne Cluster (cluster_id NULL) — werden ohnehin nie wieder ausgespielt
     */
    public function cleanupOrphanedClustersAndRules(int $userId): array
    {
        $deletedRules = $this->db->execute(
            "DELETE FROM pi_rules WHERE user_id=? AND cluster_id IS NULL",
            [$userId]
        );
        $deletedClusters = $this->db->execute(
            "DELETE FROM pi_clusters
             WHERE user_id=? AND import_id IS NULL
               AND id NOT IN (SELECT cluster_id FROM pi_cluster_assignments)",
            [$userId]
        );
        return ['clusters' => $deletedClusters, 'rules' => $deletedRules];
    }

    /* ====================================================================
       LAYER 2: STATISTIK
       ==================================================================== */

    /**
     * Aggregate Statistik für die Imports eines Users (alle oder ein bestimmter).
     * Returnt komplettes Daten-Bündel fürs Dashboard.
     */
    public function getStats(int $userId, ?int $importId = null): array
    {
        $where = "i.user_id = ?";
        $params = [$userId];
        if ($importId) { $where .= " AND i.id = ?"; $params[] = $importId; }

        // Basis-Counts
        $totals = $this->db->queryOne(
            "SELECT COUNT(DISTINCT i.id) AS imports, COUNT(DISTINCT c.id) AS chats, COUNT(m.id) AS messages,
                    SUM(CASE WHEN m.role = 'user' THEN 1 ELSE 0 END) AS user_messages,
                    SUM(CASE WHEN m.role = 'user' AND m.is_initial = 1 THEN 1 ELSE 0 END) AS initial_prompts,
                    SUM(CASE WHEN m.has_attachment = 1 THEN 1 ELSE 0 END) AS with_attachment
             FROM pi_imports i
             LEFT JOIN pi_chats c ON c.import_id = i.id
             LEFT JOIN pi_messages m ON m.chat_id = c.id
             WHERE $where",
            $params
        ) ?: [];

        // Promptlängen-Quantile (User-Prompts, Wortzahlen)
        $lengths = array_map('intval', array_column(
            $this->db->query(
                "SELECT m.word_count FROM pi_messages m
                 JOIN pi_chats c ON c.id = m.chat_id
                 JOIN pi_imports i ON i.id = c.import_id
                 WHERE $where AND m.role = 'user' AND m.word_count > 0
                 ORDER BY m.word_count ASC",
                $params
            ) ?: [], 'word_count'
        ));
        $quantiles = $this->quantiles($lengths);

        // Top-Verben am Promptanfang (erste 1-2 Wörter, normalisiert)
        $verbs = $this->topOpeningTerms($userId, $importId, 12);

        // Verhältnis Initial vs. Folge + Iterationen
        $chatStats = $this->db->queryOne(
            "SELECT AVG(initial_cnt) AS avg_initial, AVG(followup_cnt) AS avg_followup, AVG(total) AS avg_total
             FROM (
                SELECT c.id,
                       SUM(CASE WHEN m.role='user' AND m.is_initial=1 THEN 1 ELSE 0 END) AS initial_cnt,
                       SUM(CASE WHEN m.role='user' AND m.is_initial=0 THEN 1 ELSE 0 END) AS followup_cnt,
                       SUM(CASE WHEN m.role='user' THEN 1 ELSE 0 END) AS total
                FROM pi_chats c
                JOIN pi_imports i ON i.id = c.import_id
                LEFT JOIN pi_messages m ON m.chat_id = c.id
                WHERE $where
                GROUP BY c.id
             ) t",
            $params
        ) ?: ['avg_initial' => 0, 'avg_followup' => 0, 'avg_total' => 0];

        // Zeitliche Heatmap (Wochentag × Stunde) — nur User-Prompts mit timestamp
        $heatmap = array_fill(1, 7, array_fill(0, 24, 0));
        $tsRows = $this->db->query(
            "SELECT m.weekday, m.hour FROM pi_messages m
             JOIN pi_chats c ON c.id = m.chat_id
             JOIN pi_imports i ON i.id = c.import_id
             WHERE $where AND m.role = 'user' AND m.weekday IS NOT NULL AND m.hour IS NOT NULL",
            $params
        ) ?: [];
        foreach ($tsRows as $r) {
            $w = (int)$r['weekday']; $h = (int)$r['hour'];
            if ($w >= 1 && $w <= 7 && $h >= 0 && $h <= 23) $heatmap[$w][$h]++;
        }

        // Quellen-Vergleich
        $bySource = $this->db->query(
            "SELECT i.source, COUNT(DISTINCT c.id) AS chats, COUNT(m.id) AS messages
             FROM pi_imports i
             LEFT JOIN pi_chats c ON c.import_id = i.id
             LEFT JOIN pi_messages m ON m.chat_id = c.id
             WHERE $where
             GROUP BY i.source",
            $params
        ) ?: [];

        return [
            'totals' => [
                'imports'         => (int)($totals['imports'] ?? 0),
                'chats'           => (int)($totals['chats'] ?? 0),
                'messages'        => (int)($totals['messages'] ?? 0),
                'user_messages'   => (int)($totals['user_messages'] ?? 0),
                'initial_prompts' => (int)($totals['initial_prompts'] ?? 0),
                'with_attachment' => (int)($totals['with_attachment'] ?? 0),
            ],
            'word_count' => $quantiles,
            'top_verbs'  => $verbs,
            'avg_chat'   => [
                'initial'  => round((float)($chatStats['avg_initial'] ?? 0), 2),
                'followup' => round((float)($chatStats['avg_followup'] ?? 0), 2),
                'total'    => round((float)($chatStats['avg_total'] ?? 0), 2),
            ],
            'heatmap'   => $heatmap,
            'by_source' => $bySource,
        ];
    }

    /** Wortzahlen → P25/Median/P75/Max */
    private function quantiles(array $sortedAsc): array
    {
        $n = count($sortedAsc);
        if ($n === 0) return ['p25' => 0, 'median' => 0, 'p75' => 0, 'max' => 0, 'count' => 0];
        $get = function ($q) use ($sortedAsc, $n) {
            $idx = (int)floor(($n - 1) * $q);
            return $sortedAsc[max(0, min($n - 1, $idx))];
        };
        return [
            'p25'    => $get(0.25),
            'median' => $get(0.5),
            'p75'    => $get(0.75),
            'max'    => $sortedAsc[$n - 1],
            'count'  => $n,
        ];
    }

    /** Top-N häufigste Eröffnungs-Wörter (erstes Wort jedes Initialprompts, lowercased). */
    private function topOpeningTerms(int $userId, ?int $importId, int $limit): array
    {
        $where = "i.user_id = ? AND m.role = 'user' AND m.is_initial = 1 AND m.content_anon != ''";
        $params = [$userId];
        if ($importId) { $where .= " AND i.id = ?"; $params[] = $importId; }
        $rows = $this->db->query(
            "SELECT m.content_anon FROM pi_messages m
             JOIN pi_chats c ON c.id = m.chat_id
             JOIN pi_imports i ON i.id = c.import_id
             WHERE $where LIMIT 5000",
            $params
        ) ?: [];
        $counts = [];
        foreach ($rows as $r) {
            $first = mb_strtolower(trim((string)$r['content_anon']));
            // Erstes Wort (ohne Satzzeichen)
            $first = preg_replace('/^[\s\p{P}]+/u', '', $first);
            $tokens = preg_split('/[\s\p{P}]+/u', $first, 2) ?: [];
            $word = $tokens[0] ?? '';
            if (mb_strlen($word) < 2 || mb_strlen($word) > 30) continue;
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice(array_map(fn($k, $v) => ['term' => $k, 'count' => $v], array_keys($counts), array_values($counts)), 0, $limit);
    }

    /* ====================================================================
       PROMPT-BROWSER
       ==================================================================== */

    /** Filterbare Liste der Messages für die Browser-View. */
    public function browseMessages(int $userId, array $filter = [], int $limit = 100, int $offset = 0): array
    {
        $where = "i.user_id = ?";
        $params = [$userId];
        if (!empty($filter['source'])) { $where .= " AND i.source = ?"; $params[] = $filter['source']; }
        if (!empty($filter['import_id'])) { $where .= " AND i.id = ?"; $params[] = (int)$filter['import_id']; }
        if (!empty($filter['role'])) { $where .= " AND m.role = ?"; $params[] = $filter['role']; }
        if (isset($filter['initial_only']) && $filter['initial_only']) { $where .= " AND m.is_initial = 1 AND m.role = 'user'"; }
        if (!empty($filter['search'])) { $where .= " AND m.content_anon LIKE ?"; $params[] = '%' . $filter['search'] . '%'; }
        if (!empty($filter['cluster_id'])) {
            $where .= " AND EXISTS (SELECT 1 FROM pi_cluster_assignments ca WHERE ca.message_id = m.id AND ca.cluster_id = ?)";
            $params[] = (int)$filter['cluster_id'];
        }

        $total = (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM pi_messages m
             JOIN pi_chats c ON c.id = m.chat_id
             JOIN pi_imports i ON i.id = c.import_id
             WHERE $where",
            $params
        );

        $params2 = array_merge($params, [$limit, $offset]);
        $messages = $this->db->query(
            "SELECT m.id, m.role, m.content_anon, m.assistant_excerpt, m.word_count, m.position,
                    m.is_initial, m.has_attachment, m.attachment_type, m.sent_at,
                    c.id AS chat_id, c.title AS chat_title, c.source
             FROM pi_messages m
             JOIN pi_chats c ON c.id = m.chat_id
             JOIN pi_imports i ON i.id = c.import_id
             WHERE $where
             ORDER BY m.sent_at DESC, m.id DESC
             LIMIT ? OFFSET ?",
            $params2
        ) ?: [];

        return ['total' => $total, 'messages' => $messages];
    }

    /* ====================================================================
       LAYER 3: CLUSTERING (Embeddings + Cosine-Threshold)
       ==================================================================== */

    /**
     * Erstellt Embeddings für alle Initialprompts eines Users, die noch keins haben.
     * Returnt ['done' => int, 'skipped' => int]
     */
    public function embedInitialPrompts(int $userId, int $limit = 500): array
    {
        if (!class_exists('\\Services\\AIService', false)) {
            require_once SERVICES_PATH . '/AIService.php';
        }
        // Embeddings laufen IMMER über OpenAI (siehe AIService::createEmbedding)
        $apiKey = (string)\Core\Settings::get('openai_api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI-API-Key fehlt — bitte unter /admin/settings?tab=ki konfigurieren');
        }
        $ai = new \Services\AIService($apiKey, 'openai');

        $rows = $this->db->query(
            "SELECT m.id, m.content_anon
             FROM pi_messages m
             JOIN pi_chats c ON c.id = m.chat_id
             JOIN pi_imports i ON i.id = c.import_id
             WHERE i.user_id = ? AND m.role = 'user' AND m.is_initial = 1 AND m.embedding_done = 0
               AND m.content_anon IS NOT NULL AND m.content_anon != ''
             LIMIT ?",
            [$userId, $limit]
        ) ?: [];

        $done = 0; $skipped = 0;
        foreach ($rows as $r) {
            $text = mb_substr((string)$r['content_anon'], 0, 8000);  // OpenAI-Token-Limit grob abdecken
            if (mb_strlen($text) < 10) { $skipped++; continue; }
            try {
                $res = $ai->createEmbedding($text);
                $vec = $res['embedding'] ?? null;
                if (!is_array($vec) || count($vec) < 100) { $skipped++; continue; }
                // Als BINARY (float32) speichern für kompakte Storage
                $blob = '';
                foreach ($vec as $f) $blob .= pack('f', (float)$f);
                $this->db->execute(
                    "INSERT INTO pi_embeddings (message_id, dim, vec) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE dim = VALUES(dim), vec = VALUES(vec)",
                    [(int)$r['id'], count($vec), $blob]
                );
                $this->db->update('pi_messages', ['embedding_done' => 1], 'id = ?', [(int)$r['id']]);
                $done++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }
        return ['done' => $done, 'skipped' => $skipped];
    }

    /**
     * Cosine-Threshold-Clustering (hierarchisch, single-pass).
     * Algorithmus: für jeden Vektor → finde nächst-stärksten Cluster-Repräsentanten;
     * wenn Cosine >= $threshold, hinzufügen; sonst neuen Cluster eröffnen.
     *
     * threshold 0.85 = Cluster sind sehr ähnlich (eng zusammen)
     * threshold 0.65 = lockere Cluster, fängt mehr ab
     *
     * Returnt Anzahl gebildeter Cluster.
     */
    public function recluster(int $userId, float $threshold = 0.78): array
    {
        // Alle Embeddings dieses Users laden
        $rows = $this->db->query(
            "SELECT e.message_id, e.dim, e.vec, m.content_anon
             FROM pi_embeddings e
             JOIN pi_messages m ON m.id = e.message_id
             JOIN pi_chats c ON c.id = m.chat_id
             JOIN pi_imports i ON i.id = c.import_id
             WHERE i.user_id = ?",
            [$userId]
        ) ?: [];
        if (empty($rows)) return ['clusters' => 0, 'assigned' => 0];

        // Vektoren entpacken + L2-normalisieren (für reines Dot-Product = Cosine)
        $vectors = [];
        foreach ($rows as $r) {
            $vec = array_values(unpack('f*', $r['vec']));
            // L2-norm
            $norm = 0;
            foreach ($vec as $f) $norm += $f * $f;
            $norm = sqrt($norm);
            if ($norm > 0) foreach ($vec as $i => $f) $vec[$i] = $f / $norm;
            $vectors[] = [
                'message_id' => (int)$r['message_id'],
                'content'    => (string)$r['content_anon'],
                'vec'        => $vec,
            ];
        }

        // Single-pass greedy: jeder Vektor → ähnlichster vorhandener Repräsentant
        $clusters = [];  // [{representative, members: [{msg_id, sim}], terms}]
        foreach ($vectors as $v) {
            $bestIdx = -1; $bestSim = $threshold;
            foreach ($clusters as $i => $c) {
                $sim = 0;
                foreach ($v['vec'] as $k => $f) $sim += $f * $c['representative'][$k];
                if ($sim > $bestSim) { $bestSim = $sim; $bestIdx = $i; }
            }
            if ($bestIdx >= 0) {
                $clusters[$bestIdx]['members'][] = ['msg_id' => $v['message_id'], 'sim' => $bestSim, 'content' => $v['content']];
            } else {
                $clusters[] = ['representative' => $v['vec'], 'members' => [['msg_id' => $v['message_id'], 'sim' => 1.0, 'content' => $v['content']]]];
            }
        }

        // Cluster-Beschriftung via Top-Terms (einfach: häufigste Wörter > 3 Zeichen, ohne Stopwords)
        $stopwords = $this->germanStopwords();
        foreach ($clusters as &$c) {
            $terms = [];
            foreach ($c['members'] as $m) {
                $words = preg_split('/[\s\p{P}]+/u', mb_strtolower($m['content'])) ?: [];
                foreach ($words as $w) {
                    if (mb_strlen($w) < 4 || isset($stopwords[$w]) || is_numeric($w)) continue;
                    $terms[$w] = ($terms[$w] ?? 0) + 1;
                }
            }
            arsort($terms);
            $c['top_terms'] = array_slice(array_keys($terms), 0, 5);
            $c['label'] = !empty($c['top_terms']) ? implode(', ', array_slice($c['top_terms'], 0, 3)) : 'Cluster ohne Top-Terms';
        }
        unset($c);

        // In DB schreiben — alte Cluster + Assignments des Users löschen, dann neu
        $this->db->execute(
            "DELETE FROM pi_clusters WHERE user_id = ?",
            [$userId]
        );
        $assigned = 0;
        // Größenrichtung absteigend speichern (größter Cluster zuerst)
        usort($clusters, fn($a, $b) => count($b['members']) - count($a['members']));
        foreach ($clusters as $c) {
            $clusterId = (int)$this->db->insert('pi_clusters', [
                'user_id'       => $userId,
                'label'         => mb_substr($c['label'], 0, 200),
                'description'   => null,
                'message_count' => count($c['members']),
                'top_terms'     => implode(', ', $c['top_terms']),
            ]);
            foreach ($c['members'] as $m) {
                $this->db->execute(
                    "INSERT INTO pi_cluster_assignments (message_id, cluster_id, distance) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE cluster_id = VALUES(cluster_id), distance = VALUES(distance)",
                    [$m['msg_id'], $clusterId, round(1.0 - $m['sim'], 5)]
                );
                $assigned++;
            }
        }

        return ['clusters' => count($clusters), 'assigned' => $assigned, 'threshold' => $threshold];
    }

    public function listClusters(int $userId): array
    {
        return $this->db->query(
            "SELECT id, label, description, message_count, top_terms, created_at
             FROM pi_clusters WHERE user_id = ? ORDER BY message_count DESC",
            [$userId]
        ) ?: [];
    }

    public function getClusterSamples(int $clusterId, int $userId, int $limit = 30): array
    {
        $row = $this->db->queryOne("SELECT id, user_id FROM pi_clusters WHERE id = ?", [$clusterId]);
        if (!$row || (int)$row['user_id'] !== $userId) return [];
        return $this->db->query(
            "SELECT m.id, m.content_anon, m.assistant_excerpt, m.word_count, m.has_attachment,
                    c.title AS chat_title, c.source, ca.distance
             FROM pi_cluster_assignments ca
             JOIN pi_messages m ON m.id = ca.message_id
             JOIN pi_chats c ON c.id = m.chat_id
             WHERE ca.cluster_id = ?
             ORDER BY ca.distance ASC, m.id ASC
             LIMIT ?",
            [$clusterId, $limit]
        ) ?: [];
    }

    /* ====================================================================
       LAYER 4: REGELABLEITUNG (LLM-gestützt)
       ==================================================================== */

    /**
     * Erzeugt Regelvorschläge für einen Cluster via Standard-Modell aus Settings.
     * Returnt Anzahl neuer Vorschläge.
     */
    public function deriveRulesForCluster(int $clusterId, int $userId, int $sampleSize = 25): array
    {
        $cluster = $this->db->queryOne(
            "SELECT id, label, top_terms, message_count FROM pi_clusters WHERE id = ? AND user_id = ?",
            [$clusterId, $userId]
        );
        if (!$cluster) throw new \RuntimeException('Cluster nicht gefunden');

        $samples = $this->db->query(
            "SELECT m.content_anon, m.assistant_excerpt
             FROM pi_cluster_assignments ca
             JOIN pi_messages m ON m.id = ca.message_id
             WHERE ca.cluster_id = ?
             ORDER BY ca.distance ASC
             LIMIT ?",
            [$clusterId, $sampleSize]
        ) ?: [];
        if (empty($samples)) throw new \RuntimeException('Keine Sample-Prompts im Cluster');

        // Last-mile PII-Check
        $sampleText = '';
        foreach ($samples as $idx => $s) {
            $line = "[Prompt " . ($idx + 1) . "]\n" . trim((string)$s['content_anon']) . "\n";
            if (!empty($s['assistant_excerpt'])) {
                $line .= "[Antwort-Auszug] " . trim((string)$s['assistant_excerpt']) . "\n";
            }
            if ($this->containsPii($line)) {
                continue;  // skip — anonymisierung lückenhaft
            }
            $sampleText .= $line . "\n";
        }
        if (trim($sampleText) === '') throw new \RuntimeException('Alle Samples enthalten noch PII — bitte Whitelist erweitern, neu importieren');

        // LLM-Call
        $systemPrompt = "Du bist ein erfahrener Prompt-Engineering-Coach. Analysiere die folgenden anonymisierten Prompts und ihre Antwort-Ausschnitte. "
                     . "Antworte AUSSCHLIESSLICH mit gültigem JSON in der folgenden Struktur (keine Erklärung außerhalb):\n"
                     . "{\n"
                     . "  \"struktur\": \"kurze Beschreibung der typischen Initialprompt-Struktur in 1-2 Sätzen\",\n"
                     . "  \"luecken\": [\"Lücke 1\", \"Lücke 2\"],\n"
                     . "  \"korrektur_patterns\": [\"Pattern 1\", \"Pattern 2\"],\n"
                     . "  \"idealer_prompt\": \"1 Absatz Bauplan für den idealen Prompt\",\n"
                     . "  \"regeln\": [\"Spielregel 1\", \"Spielregel 2\", \"Spielregel 3\"]\n"
                     . "}\n"
                     . "Die Regeln sollen konkret, im Imperativ, auf Deutsch, 1-2 Sätze pro Regel. 3 bis 5 Regeln.";

        $userPrompt = "Cluster-Label: " . $cluster['label'] . "\n"
                    . "Cluster-Größe: " . $cluster['message_count'] . " Initialprompts\n"
                    . "Top-Begriffe: " . $cluster['top_terms'] . "\n\n"
                    . "Sample-Prompts ({$sampleSize}):\n" . $sampleText;

        $result = $this->callLLM($systemPrompt, $userPrompt);

        // JSON parsen
        $parsed = $this->extractJson($result);
        if (!is_array($parsed) || empty($parsed['regeln'])) {
            throw new \RuntimeException('Antwort enthielt keine Regeln (JSON nicht parsebar): ' . mb_substr($result, 0, 200));
        }

        // Description am Cluster setzen
        $desc = trim((string)($parsed['struktur'] ?? '') . "\n\n"
              . (!empty($parsed['idealer_prompt']) ? "Idealer Prompt: " . $parsed['idealer_prompt'] : ''));
        $this->db->update('pi_clusters', ['description' => mb_substr($desc, 0, 2000)], 'id = ?', [$clusterId]);

        // Regelvorschläge anlegen (alte automatische verwerfen, neue als 'vorschlag')
        $this->db->execute(
            "DELETE FROM pi_rules WHERE user_id = ? AND cluster_id = ? AND source = 'auto' AND status = 'vorschlag'",
            [$userId, $clusterId]
        );
        $created = 0;
        foreach ((array)$parsed['regeln'] as $regel) {
            $r = trim((string)$regel);
            if (mb_strlen($r) < 5) continue;
            $this->db->insert('pi_rules', [
                'user_id'    => $userId,
                'cluster_id' => $clusterId,
                'text'       => mb_substr($r, 0, 1000),
                'status'     => 'vorschlag',
                'source'     => 'auto',
            ]);
            $created++;
        }

        return [
            'cluster_id' => $clusterId,
            'created'    => $created,
            'parsed'     => $parsed,
        ];
    }

    /** LLM-Call über den AIService mit Standard-Modell aus Settings. */
    private function callLLM(string $systemPrompt, string $userPrompt): string
    {
        if (!class_exists('\\Services\\AIService', false)) {
            require_once SERVICES_PATH . '/AIService.php';
        }
        // Standard-Modell + passender API-Key + Provider aus Settings
        $defaultModel = (string)\Core\Settings::get('default_model') ?: 'gpt-4o';
        $provider = (str_contains($defaultModel, 'claude') ? 'anthropic'
                    : (str_contains($defaultModel, 'gemini') ? 'google' : 'openai'));
        $apiKey = match ($provider) {
            'anthropic' => (string)\Core\Settings::get('anthropic_api_key'),
            'google'    => (string)\Core\Settings::get('google_api_key'),
            default     => (string)\Core\Settings::get('openai_api_key'),
        };
        if ($apiKey === '') {
            throw new \RuntimeException("Kein $provider-API-Key in Settings — bitte unter /admin/settings?tab=ki konfigurieren");
        }
        $ai = new \Services\AIService($apiKey, $provider);
        $ai->setModel($defaultModel);
        $ai->setMaxTokens(1500);
        $resp = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $systemPrompt);
        return (string)($resp['text'] ?? $resp['content'] ?? '');
    }

    /** Extrahiert das erste JSON-Objekt aus einer LLM-Antwort (toleriert Markdown-Code-Blöcke). */
    private function extractJson(string $text)
    {
        // Markdown-Code-Block entfernen
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        // Erstes { bis letztes }
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) return null;
        $json = substr($text, $start, $end - $start + 1);
        return json_decode($json, true);
    }

    /* ====================================================================
       RULES (Spielregel-Bibliothek)
       ==================================================================== */

    public function listRules(int $userId, ?string $status = null, ?int $clusterId = null): array
    {
        $where = "r.user_id = ?";
        $params = [$userId];
        if ($status) { $where .= " AND r.status = ?"; $params[] = $status; }
        if ($clusterId) { $where .= " AND r.cluster_id = ?"; $params[] = $clusterId; }
        return $this->db->query(
            "SELECT r.id, r.cluster_id, r.text, r.status, r.source, r.created_at, r.updated_at,
                    c.label AS cluster_label
             FROM pi_rules r
             LEFT JOIN pi_clusters c ON c.id = r.cluster_id
             WHERE $where
             ORDER BY r.updated_at DESC",
            $params
        ) ?: [];
    }

    public function updateRule(int $ruleId, int $userId, array $patch): bool
    {
        $row = $this->db->queryOne("SELECT id FROM pi_rules WHERE id = ? AND user_id = ?", [$ruleId, $userId]);
        if (!$row) return false;
        $allowed = [];
        if (isset($patch['text']))   $allowed['text']   = mb_substr((string)$patch['text'], 0, 1000);
        if (isset($patch['status']) && in_array($patch['status'], ['vorschlag','freigegeben','verworfen'], true)) {
            $allowed['status'] = $patch['status'];
        }
        if (!$allowed) return false;
        $this->db->update('pi_rules', $allowed, 'id = ?', [$ruleId]);
        return true;
    }

    public function createRule(int $userId, string $text, ?int $clusterId = null): int
    {
        return (int)$this->db->insert('pi_rules', [
            'user_id'    => $userId,
            'cluster_id' => $clusterId,
            'text'       => mb_substr($text, 0, 1000),
            'status'     => 'freigegeben',
            'source'     => 'manuell',
        ]);
    }

    public function deleteRule(int $ruleId, int $userId): bool
    {
        return (bool)$this->db->execute("DELETE FROM pi_rules WHERE id = ? AND user_id = ?", [$ruleId, $userId]);
    }

    /** Export der freigegebenen Regeln als Markdown oder JSON. */
    public function exportRules(int $userId, string $format = 'markdown'): string
    {
        $rules = $this->listRules($userId, 'freigegeben');
        if ($format === 'json') {
            return json_encode([
                'user_id'   => $userId,
                'exported'  => date('c'),
                'count'     => count($rules),
                'rules'     => array_map(fn($r) => [
                    'text'           => $r['text'],
                    'cluster_label'  => $r['cluster_label'],
                    'source'         => $r['source'],
                    'updated_at'     => $r['updated_at'],
                ], $rules),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        // Markdown
        $md = "# Spielregeln für Prompts\n\n";
        $md .= "Exportiert: " . date('Y-m-d H:i') . " · " . count($rules) . " freigegebene Regeln\n\n";
        $byCluster = [];
        foreach ($rules as $r) {
            $key = $r['cluster_label'] ?: '— Ohne Cluster —';
            $byCluster[$key][] = $r;
        }
        foreach ($byCluster as $cluster => $list) {
            $md .= "## " . $cluster . "\n\n";
            foreach ($list as $r) {
                $md .= "- " . $r['text'] . "\n";
            }
            $md .= "\n";
        }
        return $md;
    }

    /** Minimal-Stopwordliste (deutsch + ein paar englisch) */
    private function germanStopwords(): array
    {
        return array_flip([
            'aber','alle','allem','allen','aller','alles','also','andere','anderen','anderer','anderes','aus',
            'bei','beim','bist','bitte','dann','dass','daß','dein','deine','deinem','deinen','deiner','deines',
            'dem','den','der','des','die','doch','dort','dort','durch','eben','eine','einem','einen','einer','eines',
            'einig','einige','einigem','einigen','einiger','einiges','etwa','euch','euer','eure','euren','eurer',
            'für','gegen','habe','haben','hast','hatte','hatten','heute','hier','hinter','ihnen','ihre','ihren','ihrer',
            'jede','jedem','jeden','jeder','jedes','jene','jenen','jener','jenes','kann','keine','keinen','keiner','keines',
            'können','konnte','konnten','machen','mehr','meine','meinen','meiner','meines','mich','mußten',
            'muss','müssen','nach','nicht','nichts','noch','ohne','oder','ohne','sehr','seid','sein','seine','seinem','seinen','seiner',
            'sich','sind','solch','solche','solchem','solchen','solcher','solches','sollte','sollten','sondern','soviel',
            'über','unser','unsere','unserem','unseren','unserer','unter','viel','vielleicht','viele','vom','vor','war','waren',
            'warum','weil','weiter','welche','welchem','welchen','welcher','welches','wenn','werde','werden','wie','wieder',
            'will','wir','wird','wirst','wollen','wollte','wollten','würde','würden','zum','zur','zwar','zwischen',
            // englisch — falls Prompts englisch sind
            'about','after','again','against','all','also','any','because','been','before','being','between','both',
            'cannot','could','does','doing','down','during','each','from','further','have','having','here','here',
            'into','itself','just','more','most','only','other','others','over','same','should','some','such','than','that',
            'their','them','then','there','these','they','this','those','through','under','until','very','what','when',
            'where','which','while','with','your','yours','yourself',
            // generisch
            'bitte','danke','okay','prompt','prompts','frage','antwort',
        ]);
    }
}
