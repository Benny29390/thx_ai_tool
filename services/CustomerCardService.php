<?php
namespace Services;

use Core\Database;

/**
 * CustomerCardService
 *
 * Verwaltet Steckbrief-Widgets pro Kunde (Links, Richtext, Documents, Images, Brand).
 * Synchronisiert Card-Inhalte automatisch in die Wissensdatenbank
 * (Quelle "Kundensteckbrief", external_id "customer-card:{id}").
 */
class CustomerCardService
{
    private Database $db;
    private ?KnowledgeIngestService $ingest;

    public const TYPES = ['links', 'richtext', 'documents', 'images', 'brand', 'contacts', 'kpi', 'tracking_status', 'accounts'];
    public const UPLOAD_BASE = '/var/www/uploads/customers';
    public const MAX_UPLOAD_BYTES = 50 * 1024 * 1024; // 50 MB

    /** System-Cards: vordefinierte Slots, die immer existieren (resize/reorder, aber kein Delete) */
    public const SYSTEM_CARDS = [
        'profile' =>      ['title' => 'Stammdaten',                'size_w' => 2, 'size_h' => 2, 'is_collapsed' => 0, 'sort_order' => 1, 'target_tab' => 'uebersicht'],
        'markenprofil' => ['title' => 'Marken-Profil',             'size_w' => 2, 'size_h' => 2, 'is_collapsed' => 0, 'sort_order' => 6, 'target_tab' => 'marke'],
        'regeln' =>       ['title' => 'Regeln',                    'size_w' => 2, 'size_h' => 2, 'is_collapsed' => 0, 'sort_order' => 7, 'target_tab' => 'inhalte'],
        'asana' =>        ['title' => 'Asana',                     'size_w' => 2, 'size_h' => 2, 'is_collapsed' => 0, 'sort_order' => 2, 'target_tab' => 'uebersicht'],
        'website' =>      ['title' => 'Website',                   'size_w' => 1, 'size_h' => 1, 'is_collapsed' => 0, 'sort_order' => 3, 'target_tab' => 'websites'],
        'site_monitor' => ['title' => 'Monitoring',                'size_w' => 1, 'size_h' => 1, 'is_collapsed' => 0, 'sort_order' => 5, 'target_tab' => 'websites'],
        'knowledge' =>    ['title' => 'Wissen über diesen Kunden', 'size_w' => 2, 'size_h' => 1, 'is_collapsed' => 1, 'sort_order' => 4, 'target_tab' => 'uebersicht'],
    ];

    /**
     * Geseedete Cards: echte, editierbare Cards (is_system=0), die bei jedem Kunden
     * automatisch angelegt werden. Anders als SYSTEM_CARDS sind sie bearbeitbar,
     * loeschbar UND werden in die Wissensdatenbank synchronisiert. Der system_key
     * dient nur als „existiert schon?"-Marker fuers Seeding.
     */
    public const SEEDED_CARDS = [
        'account_ids' => ['type' => 'accounts', 'title' => 'Konten & IDs', 'size_w' => 2, 'size_h' => 1, 'is_collapsed' => 0, 'sort_order' => 8, 'target_tab' => 'websites'],
    ];

    public const MIN_WIDTH = 2; // Cards immer min. 2 Spalten breit

    public function __construct(Database $db, ?KnowledgeIngestService $ingest = null)
    {
        $this->db = $db;
        $this->ingest = $ingest;
    }

    public function listForCustomer(int $customerId): array
    {
        $this->ensureSystemCards($customerId);
        $cards = $this->db->query(
            "SELECT * FROM customer_cards WHERE customer_id = ? ORDER BY sort_order ASC, id ASC",
            [$customerId]
        ) ?: [];
        // Files anhängen
        if (!empty($cards)) {
            $ids = array_column($cards, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $files = $this->db->query(
                "SELECT * FROM customer_card_files WHERE card_id IN ($placeholders) ORDER BY sort_order ASC, id ASC",
                $ids
            ) ?: [];
            $byCard = [];
            foreach ($files as $f) {
                $f['public_url'] = $this->publicUrl($f);
                $byCard[$f['card_id']][] = $f;
            }
            foreach ($cards as &$c) {
                $c['body_decoded'] = $this->decodeBody($c['type'], $c['body']);
                $c['files'] = $byCard[$c['id']] ?? [];
            }
            unset($c);
        }
        return $cards;
    }

    public function get(int $cardId): ?array
    {
        $c = $this->db->queryOne("SELECT * FROM customer_cards WHERE id = ?", [$cardId]);
        if (!$c) return null;
        $c['body_decoded'] = $this->decodeBody($c['type'], $c['body']);
        $c['files'] = $this->db->query(
            "SELECT * FROM customer_card_files WHERE card_id = ? ORDER BY sort_order ASC, id ASC",
            [$cardId]
        ) ?: [];
        foreach ($c['files'] as &$f) $f['public_url'] = $this->publicUrl($f);
        unset($f);
        return $c;
    }

    public function create(int $customerId, string $type, string $title, int $userId): int
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Ungültiger Card-Typ');
        }
        $maxOrder = (int) ($this->db->queryValue(
            "SELECT COALESCE(MAX(sort_order), 0) FROM customer_cards WHERE customer_id = ?",
            [$customerId]
        ) ?: 0);
        return $this->db->insert('customer_cards', [
            'customer_id' => $customerId,
            'type' => $type,
            'title' => $title !== '' ? $title : $this->defaultTitle($type),
            'body' => json_encode($this->defaultBody($type)),
            'sort_order' => $maxOrder + 10,
            'is_collapsed' => 0,
            'size_w' => self::MIN_WIDTH,
            'size_h' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function update(int $cardId, array $patch, int $userId): void
    {
        $card = $this->get($cardId);
        if (!$card) throw new \RuntimeException('Card nicht gefunden');

        $updates = ['updated_by' => $userId];
        $isSystem = !empty($card['is_system']);

        if (array_key_exists('title', $patch)) $updates['title'] = (string) $patch['title'];
        if (array_key_exists('is_collapsed', $patch)) $updates['is_collapsed'] = $patch['is_collapsed'] ? 1 : 0;
        if (array_key_exists('size_w', $patch)) $updates['size_w'] = max(1, min(3, (int) $patch['size_w']));
        if (array_key_exists('size_h', $patch)) $updates['size_h'] = max(1, min(3, (int) $patch['size_h']));
        if (array_key_exists('target_tab', $patch)) {
            $tab = (string) $patch['target_tab'];
            if (in_array($tab, ['uebersicht','inhalte','personen','dateien','marke','sonstiges','websites'], true)) $updates['target_tab'] = $tab;
        }
        if (array_key_exists('column_idx', $patch)) {
            $updates['column_idx'] = max(0, min(3, (int) $patch['column_idx'])); // 0 = Hero
        }

        // Body nur für nicht-System-Cards
        if (!$isSystem && array_key_exists('body', $patch)) {
            $normalized = $this->normalizeBody($card['type'], $patch['body']);
            $newBody = json_encode($normalized);
            $oldBody = (string) ($card['body'] ?? '');
            // Snapshot nur, wenn sich der Body wirklich ändert
            if ($oldBody !== $newBody) {
                $this->snapshotCurrent($cardId, $userId);
            }
            $updates['body'] = $newBody;
        }
        // Snapshot auch wenn nur Title sich ändert (für nicht-System)
        if (!$isSystem && array_key_exists('title', $patch) && !array_key_exists('body', $patch)) {
            if ((string) $card['title'] !== (string) $patch['title']) {
                $this->snapshotCurrent($cardId, $userId);
            }
        }

        if (!empty($updates)) {
            $this->db->update('customer_cards', $updates, 'id = ?', [$cardId]);
        }

        // Knowledge-Sync (System-Cards + images übersprungen)
        if (!$isSystem && $card['type'] !== 'images') {
            $this->syncToKnowledge($cardId, $userId);
        }
    }

    public function listVersions(int $cardId, int $limit = 50): array
    {
        $rows = $this->db->query(
            "SELECT v.id, v.card_id, v.title, v.snapshot_at, v.snapshot_by, u.name AS user_name
             FROM customer_card_versions v
             LEFT JOIN users u ON u.id = v.snapshot_by
             WHERE v.card_id = ?
             ORDER BY v.id DESC
             LIMIT " . (int) $limit,
            [$cardId]
        ) ?: [];
        return $rows;
    }

    public function getVersion(int $versionId): ?array
    {
        $v = $this->db->queryOne("SELECT * FROM customer_card_versions WHERE id = ?", [$versionId]);
        if (!$v) return null;
        // Body decoden für Vorschau
        $card = $this->db->queryOne("SELECT type FROM customer_cards WHERE id = ?", [(int) $v['card_id']]);
        $type = $card['type'] ?? 'richtext';
        $v['body_decoded'] = $this->decodeBody($type, $v['body']);
        $v['type'] = $type;
        return $v;
    }

    public function restoreVersion(int $cardId, int $versionId, int $userId): void
    {
        $version = $this->db->queryOne("SELECT * FROM customer_card_versions WHERE id = ? AND card_id = ?", [$versionId, $cardId]);
        if (!$version) throw new \RuntimeException('Version nicht gefunden');
        $card = $this->db->queryOne("SELECT * FROM customer_cards WHERE id = ?", [$cardId]);
        if (!$card) throw new \RuntimeException('Card nicht gefunden');
        if (!empty($card['is_system'])) throw new \RuntimeException('System-Cards haben keine Versionen');

        // Aktuellen Stand vorher als neue Version sichern
        $this->snapshotCurrent($cardId, $userId);

        // Body + Title wiederherstellen
        $this->db->update('customer_cards', [
            'title' => $version['title'],
            'body' => $version['body'],
            'updated_by' => $userId,
        ], 'id = ?', [$cardId]);

        // Knowledge-Sync (außer images)
        if ($card['type'] !== 'images') {
            $this->syncToKnowledge($cardId, $userId);
        }
    }

    private function snapshotCurrent(int $cardId, int $userId): void
    {
        $card = $this->db->queryOne("SELECT title, body FROM customer_cards WHERE id = ?", [$cardId]);
        if (!$card) return;
        $this->db->insert('customer_card_versions', [
            'card_id' => $cardId,
            'title' => $card['title'],
            'body' => $card['body'],
            'snapshot_by' => $userId,
        ]);
        // Limit: 50 Versionen pro Card behalten
        try {
            $this->db->execute(
                "DELETE FROM customer_card_versions
                 WHERE card_id = ? AND id NOT IN (
                   SELECT id FROM (
                     SELECT id FROM customer_card_versions WHERE card_id = ? ORDER BY id DESC LIMIT 50
                   ) sub
                 )",
                [$cardId, $cardId]
            );
        } catch (\Throwable $e) { /* limit cleanup ist optional */ }
    }

    public function ensureSystemCards(int $customerId): void
    {
        $existing = $this->db->query(
            "SELECT system_key FROM customer_cards WHERE customer_id = ? AND is_system = 1",
            [$customerId]
        ) ?: [];
        $haveKeys = array_column($existing, 'system_key');
        foreach (self::SYSTEM_CARDS as $key => $cfg) {
            if (in_array($key, $haveKeys, true)) continue;
            $this->db->insert('customer_cards', [
                'customer_id' => $customerId,
                'type' => 'richtext', // ENUM-Pflichtwert; wird für System-Cards ignoriert
                'is_system' => 1,
                'system_key' => $key,
                'title' => $cfg['title'],
                'body' => null,
                'sort_order' => $cfg['sort_order'],
                'is_collapsed' => $cfg['is_collapsed'],
                'size_w' => $cfg['size_w'],
                'size_h' => $cfg['size_h'],
                'target_tab' => $cfg['target_tab'] ?? 'inhalte',
            ]);
        }

        // Geseedete editierbare Cards (z.B. „Konten & IDs"): normale Cards mit
        // system_key als Marker — editierbar, loeschbar, Knowledge-synchronisiert.
        $seeded = $this->db->query(
            "SELECT system_key FROM customer_cards WHERE customer_id = ? AND system_key IS NOT NULL",
            [$customerId]
        ) ?: [];
        $haveSeeded = array_column($seeded, 'system_key');
        foreach (self::SEEDED_CARDS as $key => $cfg) {
            if (in_array($key, $haveSeeded, true)) continue;
            $this->db->insert('customer_cards', [
                'customer_id' => $customerId,
                'type' => $cfg['type'],
                'is_system' => 0,
                'system_key' => $key,
                'title' => $cfg['title'],
                'body' => json_encode($this->defaultBody($cfg['type'])),
                'sort_order' => $cfg['sort_order'],
                'is_collapsed' => $cfg['is_collapsed'],
                'size_w' => $cfg['size_w'],
                'size_h' => $cfg['size_h'],
                'target_tab' => $cfg['target_tab'] ?? 'websites',
            ]);
        }
    }

    public function delete(int $cardId): void
    {
        $card = $this->db->queryOne("SELECT * FROM customer_cards WHERE id = ?", [$cardId]);
        if (!$card) return;
        if (!empty($card['is_system'])) {
            throw new \RuntimeException('System-Cards können nicht gelöscht werden');
        }

        // Knowledge-Doc löschen (Card-Body-Doc)
        if (!empty($card['knowledge_document_id'])) {
            try {
                $this->db->delete('knowledge_documents', 'id = ?', [(int) $card['knowledge_document_id']]);
            } catch (\Throwable $e) { /* ignore */ }
        }
        // Files: Knowledge-Docs der Files löschen + physische Files
        $files = $this->db->query("SELECT * FROM customer_card_files WHERE card_id = ?", [$cardId]) ?: [];
        foreach ($files as $f) {
            if (!empty($f['knowledge_document_id'])) {
                try {
                    $this->db->delete('knowledge_documents', 'id = ?', [(int) $f['knowledge_document_id']]);
                } catch (\Throwable $e) { /* ignore */ }
            }
            if (!empty($f['file_path']) && is_file($f['file_path'])) {
                @unlink($f['file_path']);
            }
        }
        // Card-Verzeichnis löschen, wenn leer
        $cardDir = self::UPLOAD_BASE . '/' . (int) $card['customer_id'] . '/cards/' . $cardId;
        if (is_dir($cardDir)) @rmdir($cardDir);

        $this->db->delete('customer_cards', 'id = ?', [$cardId]);
    }

    public function reorder(int $customerId, array $idsInOrder): void
    {
        $sort = 10;
        foreach ($idsInOrder as $id) {
            $this->db->update(
                'customer_cards',
                ['sort_order' => $sort],
                'id = ? AND customer_id = ?',
                [(int) $id, $customerId]
            );
            $sort += 10;
        }
    }

    // ===== Files =====

    public function addFile(int $cardId, array $file, ?string $title, int $userId): array
    {
        $card = $this->get($cardId);
        if (!$card) throw new \RuntimeException('Card nicht gefunden');
        if (!in_array($card['type'], ['documents', 'images'], true)) {
            throw new \RuntimeException('Diese Card erlaubt keine Datei-Uploads');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload-Fehler: ' . $file['error']);
        }
        if ($file['size'] > self::MAX_UPLOAD_BYTES) {
            throw new \RuntimeException('Datei zu groß (max ' . (self::MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB)');
        }
        if ($card['type'] === 'images') {
            $imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
            $detected = $this->detectMime($file['tmp_name'], $file['name']);
            if (!in_array($detected, $imageMimes, true)) {
                throw new \RuntimeException('Nur Bilder erlaubt (jpg, png, webp, gif, svg)');
            }
        }

        $customerId = (int) $card['customer_id'];
        $cardDir = self::UPLOAD_BASE . '/' . $customerId . '/cards/' . $cardId;
        if (!is_dir($cardDir)) {
            if (!@mkdir($cardDir, 0755, true)) {
                throw new \RuntimeException('Upload-Verzeichnis kann nicht erstellt werden');
            }
        }

        $safeName = $this->safeFilename($file['name']);
        $target = $cardDir . '/' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden');
        }

        $maxOrder = (int) ($this->db->queryValue(
            "SELECT COALESCE(MAX(sort_order), 0) FROM customer_card_files WHERE card_id = ?",
            [$cardId]
        ) ?: 0);

        $fileId = $this->db->insert('customer_card_files', [
            'card_id' => $cardId,
            'file_path' => $target,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $this->detectMime($target, $file['name']),
            'title' => $title ?: null,
            'sort_order' => $maxOrder + 10,
        ]);

        // Documents → in Knowledge
        if ($card['type'] === 'documents' && $this->ingest) {
            try {
                $kbId = $this->ingestFileIntoKnowledge($cardId, $fileId, $target, $file['name'], $userId);
                $this->db->update('customer_card_files', ['knowledge_document_id' => $kbId], 'id = ?', [$fileId]);
            } catch (\Throwable $e) {
                error_log('CustomerCardService: file→knowledge failed: ' . $e->getMessage());
            }
        }

        $row = $this->db->queryOne("SELECT * FROM customer_card_files WHERE id = ?", [$fileId]);
        $row['public_url'] = $this->publicUrl($row);
        return $row;
    }

    public function deleteFile(int $fileId): void
    {
        $f = $this->db->queryOne("SELECT * FROM customer_card_files WHERE id = ?", [$fileId]);
        if (!$f) return;
        if (!empty($f['knowledge_document_id'])) {
            try {
                $this->db->delete('knowledge_documents', 'id = ?', [(int) $f['knowledge_document_id']]);
            } catch (\Throwable $e) { /* ignore */ }
        }
        if (!empty($f['file_path']) && is_file($f['file_path'])) {
            @unlink($f['file_path']);
        }
        $this->db->delete('customer_card_files', 'id = ?', [$fileId]);
    }

    // ===== Knowledge-Sync =====

    /**
     * Synchronisiert Card-Body (links/richtext/brand) als Wissens-Eintrag.
     * Bei Update: bestehender Eintrag wird ersetzt (reprocess).
     */
    private function syncToKnowledge(int $cardId, int $userId): void
    {
        if (!$this->ingest) return;
        $card = $this->db->queryOne("SELECT * FROM customer_cards WHERE id = ?", [$cardId]);
        if (!$card) return;
        if (!in_array($card['type'], ['links', 'richtext', 'brand', 'contacts', 'documents', 'kpi', 'tracking_status', 'accounts'], true)) return;

        $text = $this->cardToPlainText($card);
        if (mb_strlen(trim($text)) < 20) {
            // Inhalt zu kurz → bestehenden KB-Eintrag entfernen
            if (!empty($card['knowledge_document_id'])) {
                try { $this->db->delete('knowledge_documents', 'id = ?', [(int) $card['knowledge_document_id']]); } catch (\Throwable $e) {}
                $this->db->update('customer_cards', ['knowledge_document_id' => null], 'id = ?', [$cardId]);
            }
            return;
        }

        $customer = $this->db->queryOne("SELECT name FROM customers WHERE id = ?", [(int) $card['customer_id']]);
        $cardTitle = trim($card['title']) !== '' ? $card['title'] : $this->defaultTitle($card['type']);
        $externalId = 'customer-card:' . $cardId;

        $context = ['customer_name' => $customer['name'] ?? ''];
        $context['user_context'] = 'Aus Kundensteckbrief — Card "' . $cardTitle . '"';

        try {
            // Existiert bereits?
            $existingId = (int) $card['knowledge_document_id'];
            if ($existingId) {
                $existsCheck = $this->db->queryValue("SELECT id FROM knowledge_documents WHERE id = ?", [$existingId]);
                if (!$existsCheck) $existingId = 0;
            }

            if ($existingId) {
                $this->ingest->reprocess(
                    $existingId,
                    $text,
                    [
                        'title' => $cardTitle,
                        'customer_id' => (int) $card['customer_id'],
                        'category' => 'Kundensteckbrief',
                        'tags' => ['kundensteckbrief', 'card-' . $card['type']],
                    ],
                    $context,
                    $userId,
                    true
                );
                $this->db->update('knowledge_documents', [
                    'source_type' => 'kundensteckbrief',
                    'source_ref' => 'Kundensteckbrief',
                    'external_id' => $externalId,
                    'updated_by' => $userId,
                ], 'id = ?', [$existingId]);
            } else {
                $prepared = $this->ingest->prepare($text, $context);
                $docId = $this->ingest->commit(
                    $prepared,
                    [
                        'title' => $cardTitle,
                        'customer_id' => (int) $card['customer_id'],
                        'category' => 'Kundensteckbrief',
                        'tags' => ['kundensteckbrief', 'card-' . $card['type']],
                    ],
                    [
                        'source_type' => 'kundensteckbrief',
                        'source_ref' => 'Kundensteckbrief',
                        'external_id' => $externalId,
                        'created_by' => $userId,
                    ]
                );
                $this->db->update('customer_cards', ['knowledge_document_id' => $docId], 'id = ?', [$cardId]);
            }
        } catch (\Throwable $e) {
            error_log('CustomerCardService: sync failed for card ' . $cardId . ': ' . $e->getMessage());
        }
    }

    private function ingestFileIntoKnowledge(int $cardId, int $fileId, string $path, string $origName, int $userId): int
    {
        $card = $this->db->queryOne("SELECT * FROM customer_cards WHERE id = ?", [$cardId]);
        $customer = $this->db->queryOne("SELECT name FROM customers WHERE id = ?", [(int) $card['customer_id']]);

        // Text aus Datei extrahieren via DocumentProcessor
        $processor = new DocumentProcessor();
        $mime = $this->detectMime($path, $origName);
        try {
            $result = $processor->processFile($path, $mime, $origName);
            $text = $result['text'] ?? '';
        } catch (\Throwable $e) {
            throw new \RuntimeException('Aus dieser Datei konnte kein Text extrahiert werden: ' . $e->getMessage());
        }
        if (!$text || mb_strlen(trim($text)) < 30) {
            throw new \RuntimeException('Aus dieser Datei konnte kein Text extrahiert werden');
        }

        $cardTitle = trim($card['title']) !== '' ? $card['title'] : 'Dokumente';
        $context = [
            'customer_name' => $customer['name'] ?? '',
            'user_context' => 'Aus Kundensteckbrief — Card "' . $cardTitle . '"',
        ];

        $prepared = $this->ingest->prepare($text, $context);
        return $this->ingest->commit(
            $prepared,
            [
                'title' => $cardTitle . ' · ' . $origName,
                'customer_id' => (int) $card['customer_id'],
                'category' => 'Kundensteckbrief',
                'tags' => ['kundensteckbrief', 'card-documents'],
            ],
            [
                'source_type' => 'upload',
                'source_ref' => 'Kundensteckbrief: ' . $origName,
                'external_id' => 'customer-card-file:' . $fileId,
                'created_by' => $userId,
            ]
        );
    }

    // ===== Helpers =====

    private function defaultTitle(string $type): string
    {
        return match ($type) {
            'links' => 'Links',
            'richtext' => 'Notiz',
            'documents' => 'Dokumente',
            'images' => 'Bilder',
            'brand' => 'Markenidentität',
            'contacts' => 'Ansprechpartner',
            'kpi' => 'Kennzahlen',
            'tracking_status' => 'Tracking-Status',
            'accounts' => 'Konten & IDs',
            default => 'Card',
        };
    }

    private function defaultBody(string $type): array
    {
        return match ($type) {
            'links' => ['items' => []],
            'richtext' => ['html' => ''],
            'documents' => ['note' => ''],
            'images' => ['note' => ''],
            'brand' => ['colors' => [], 'fonts' => [], 'note' => ''],
            'contacts' => ['groups' => []],
            'kpi' => ['items' => []],
            'tracking_status' => ['items' => []],
            'accounts' => ['items' => []],
            default => [],
        };
    }

    private function decodeBody(string $type, ?string $body): array
    {
        $decoded = $body ? (json_decode($body, true) ?: []) : [];
        return array_replace_recursive($this->defaultBody($type), $decoded);
    }

    private function normalizeBody(string $type, $body): array
    {
        if (!is_array($body)) $body = [];
        $default = $this->defaultBody($type);

        switch ($type) {
            case 'links':
                $items = [];
                foreach (($body['items'] ?? []) as $it) {
                    $title = trim((string) ($it['title'] ?? ''));
                    $url = trim((string) ($it['url'] ?? ''));
                    $note = trim((string) ($it['note'] ?? ''));
                    if ($title === '' && $url === '') continue;
                    $items[] = ['title' => $title, 'url' => $url, 'note' => $note];
                }
                return ['items' => $items];

            case 'richtext':
                return ['html' => $this->sanitizeHtml((string) ($body['html'] ?? ''))];

            case 'documents':
            case 'images':
                return ['note' => trim((string) ($body['note'] ?? ''))];

            case 'brand':
                $colors = [];
                foreach (($body['colors'] ?? []) as $c) {
                    $name = trim((string) ($c['name'] ?? ''));
                    $value = trim((string) ($c['value'] ?? ''));
                    if ($name === '' && $value === '') continue;
                    $colors[] = ['name' => $name, 'value' => $value];
                }
                $fonts = [];
                foreach (($body['fonts'] ?? []) as $f) {
                    $name = trim((string) ($f['name'] ?? ''));
                    $note = trim((string) ($f['note'] ?? ''));
                    if ($name === '' && $note === '') continue;
                    $fonts[] = ['name' => $name, 'note' => $note];
                }
                return [
                    'colors' => $colors,
                    'fonts' => $fonts,
                    'note' => trim((string) ($body['note'] ?? '')),
                ];

            case 'contacts':
                $groups = [];
                foreach (($body['groups'] ?? []) as $g) {
                    $title = trim((string) ($g['title'] ?? ''));
                    $people = [];
                    foreach (($g['people'] ?? []) as $p) {
                        $name = trim((string) ($p['name'] ?? ''));
                        $role = trim((string) ($p['role'] ?? ''));
                        $initials = trim((string) ($p['initials'] ?? ''));
                        $email = trim((string) ($p['email'] ?? ''));
                        $phone = trim((string) ($p['phone'] ?? ''));
                        $note = trim((string) ($p['note'] ?? ''));
                        if ($name === '' && $role === '' && $email === '') continue;
                        $people[] = compact('name', 'role', 'initials', 'email', 'phone', 'note');
                    }
                    if ($title === '' && empty($people)) continue;
                    $groups[] = ['title' => $title, 'people' => $people];
                }
                return ['groups' => $groups];

            case 'kpi':
                $items = [];
                foreach (($body['items'] ?? []) as $it) {
                    $label  = trim((string) ($it['label']  ?? ''));
                    $value  = trim((string) ($it['value']  ?? ''));
                    $target = trim((string) ($it['target'] ?? ''));
                    $period = trim((string) ($it['period'] ?? ''));
                    if ($label === '' && $value === '' && $target === '') continue;
                    $items[] = compact('label', 'value', 'target', 'period');
                }
                return ['items' => $items];

            case 'tracking_status':
                $items = [];
                $allowed = ['ok','fehlt','tbd','na'];
                foreach (($body['items'] ?? []) as $it) {
                    $label  = trim((string) ($it['label'] ?? ''));
                    $status = trim((string) ($it['status'] ?? 'tbd'));
                    if (!in_array($status, $allowed, true)) $status = 'tbd';
                    $note   = trim((string) ($it['note']  ?? ''));
                    if ($label === '' && $note === '') continue;
                    $items[] = compact('label', 'status', 'note');
                }
                return ['items' => $items];

            case 'accounts':
                $items = [];
                foreach (($body['items'] ?? []) as $it) {
                    $label      = trim((string) ($it['label'] ?? ''));
                    $accountId  = trim((string) ($it['account_id'] ?? ''));
                    $url        = trim((string) ($it['url'] ?? ''));
                    $note       = trim((string) ($it['note'] ?? ''));
                    if ($label === '' && $accountId === '') continue;
                    $items[] = ['label' => $label, 'account_id' => $accountId, 'url' => $url, 'note' => $note];
                }
                return ['items' => $items];
        }
        return $default;
    }

    /**
     * Sehr einfache HTML-Sanitization (TipTap-Output): Whitelist statt Parser.
     */
    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><a>';
        $clean = strip_tags($html, $allowed);
        // <a>: nur href erlauben, target=_blank erzwingen
        $clean = preg_replace_callback('#<a([^>]*)>#i', function ($m) {
            $attrs = $m[1];
            if (preg_match('#href\s*=\s*"([^"]+)"#i', $attrs, $hm)) {
                $href = trim($hm[1]);
                if (!preg_match('#^https?://|^mailto:#i', $href)) $href = '#';
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener">';
            }
            return '<a>';
        }, $clean);
        return $clean;
    }

    /**
     * Wandelt Card-Body in lesbaren Text für Knowledge-Embedding.
     */
    private function cardToPlainText(array $card): string
    {
        $body = $this->decodeBody($card['type'], $card['body']);
        $title = trim($card['title']) !== '' ? $card['title'] : $this->defaultTitle($card['type']);
        $lines = [$title, ''];

        switch ($card['type']) {
            case 'links':
                foreach (($body['items'] ?? []) as $it) {
                    $line = '- ';
                    if (!empty($it['title'])) $line .= $it['title'] . ': ';
                    $line .= $it['url'] ?? '';
                    if (!empty($it['note'])) $line .= ' (' . $it['note'] . ')';
                    $lines[] = $line;
                }
                break;

            case 'richtext':
                $html = $body['html'] ?? '';
                $text = strip_tags(str_replace(['<br>', '</p>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>'], "\n", $html));
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $lines[] = trim($text);
                break;

            case 'brand':
                if (!empty($body['colors'])) {
                    $lines[] = 'Farben:';
                    foreach ($body['colors'] as $c) {
                        $lines[] = '- ' . trim(($c['name'] ?? '') . ' ' . ($c['value'] ?? ''));
                    }
                }
                if (!empty($body['fonts'])) {
                    $lines[] = '';
                    $lines[] = 'Schriften:';
                    foreach ($body['fonts'] as $f) {
                        $lines[] = '- ' . trim(($f['name'] ?? '') . (!empty($f['note']) ? ' — ' . $f['note'] : ''));
                    }
                }
                if (!empty($body['note'])) {
                    $lines[] = '';
                    $lines[] = $body['note'];
                }
                break;

            case 'contacts':
                foreach (($body['groups'] ?? []) as $g) {
                    if (!empty($g['title'])) {
                        $lines[] = '';
                        $lines[] = $g['title'] . ':';
                    }
                    foreach (($g['people'] ?? []) as $p) {
                        $parts = [];
                        if (!empty($p['role'])) $parts[] = $p['role'];
                        if (!empty($p['name'])) $parts[] = $p['name'];
                        if (!empty($p['initials'])) $parts[] = '(' . $p['initials'] . ')';
                        if (!empty($p['email'])) $parts[] = $p['email'];
                        if (!empty($p['phone'])) $parts[] = $p['phone'];
                        if (!empty($p['note'])) $parts[] = '— ' . $p['note'];
                        $lines[] = '- ' . implode(' ', $parts);
                    }
                }
                break;

            case 'documents':
                // Body-Notiz + Liste der angehaengten Dateinamen (Inhalt der Files
                // selbst wird separat per ingestFileIntoKnowledge synchronisiert).
                if (!empty($body['note'])) $lines[] = $body['note'];
                $files = $this->db->query("SELECT file_name FROM customer_card_files WHERE card_id = ? ORDER BY sort_order ASC", [$card['id']]) ?: [];
                if (!empty($files)) {
                    $lines[] = '';
                    $lines[] = 'Anhaenge:';
                    foreach ($files as $f) $lines[] = '- ' . ($f['file_name'] ?? '');
                }
                break;

            case 'kpi':
                foreach (($body['items'] ?? []) as $it) {
                    $parts = [];
                    if (!empty($it['label']))  $parts[] = $it['label'] . ':';
                    if (!empty($it['value']))  $parts[] = $it['value'];
                    if (!empty($it['period'])) $parts[] = '(' . $it['period'] . ')';
                    if (!empty($it['target'])) $parts[] = '— Ziel: ' . $it['target'];
                    $lines[] = '- ' . implode(' ', $parts);
                }
                break;

            case 'tracking_status':
                $labels = ['ok' => 'aktiv', 'fehlt' => 'fehlt', 'tbd' => 'offen', 'na' => 'n/a'];
                foreach (($body['items'] ?? []) as $it) {
                    $status = $labels[$it['status'] ?? 'tbd'] ?? 'offen';
                    $line = '- [' . $status . '] ' . ($it['label'] ?? '');
                    if (!empty($it['note'])) $line .= ' — ' . $it['note'];
                    $lines[] = $line;
                }
                break;

            case 'accounts':
                foreach (($body['items'] ?? []) as $it) {
                    $line = '- ';
                    if (!empty($it['label'])) $line .= $it['label'] . ': ';
                    $line .= 'ID ' . ($it['account_id'] ?? '');
                    if (!empty($it['url'])) $line .= ' (' . $it['url'] . ')';
                    if (!empty($it['note'])) $line .= ' — ' . $it['note'];
                    $lines[] = $line;
                }
                break;
        }
        return implode("\n", $lines);
    }

    private function publicUrl(array $file): ?string
    {
        $path = $file['file_path'] ?? '';
        if (!$path) return null;
        // Liegt unter /var/www/uploads → öffentlich erreichbar als /uploads/...
        $prefix = '/var/www';
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }
        return null;
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[\x00-\x1f\\\\\/:*?"<>|]/u', '', $name) ?: 'file';
        return mb_substr($name, 0, 180);
    }

    private function detectMime(string $path, string $origName): string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if ($m) return $m;
        }
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'webp' => 'image/webp',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml',
            'zip' => 'application/zip',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
