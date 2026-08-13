<?php
namespace Services;

use Core\Database;

/**
 * CustomerPortalService — serverseitige Zugriffsschicht fuer die Kundenansicht.
 *
 * Liest die BESTEHENDEN Datenquellen (customer_cards, pp_plans) und liefert
 * ausschliesslich kuratierte, fuer den jeweiligen Kunden freigeschaltete und
 * kundengerecht gefilterte Daten zurueck. Tenant-Isolation: die customer_id
 * wird IMMER vom Aufrufer (aus Auth) gesetzt, nie aus dem Request uebernommen.
 *
 * Phase-1/3-Pilot: Module „Projektstatus", „Ergebnisse", „Meilensteine".
 */
class CustomerPortalService
{
    private Database $db;

    public const MODULES = ['projektstatus', 'ergebnisse', 'meilensteine'];

    /** Karten-Typen, die ueberhaupt an Kunden ausgeliefert werden duerfen.
     *  Bewusst KEINE accounts/documents/links/contacts (enthalten Zugangsdaten,
     *  interne Links, personenbezogene interne Daten). */
    private const SAFE_CARD_TYPES = ['richtext', 'kpi', 'brand'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** Kunden-Stammdaten fuer den Header (nur unkritische Felder). */
    public function customerHeader(int $customerId): ?array
    {
        $c = $this->db->queryOne("SELECT id, name, abbreviation, website FROM customers WHERE id = ? AND is_active = 1", [$customerId]);
        return $c ?: null;
    }

    /** Welche Module sind fuer diesen Kunden freigeschaltet? */
    public function enabledModules(int $customerId): array
    {
        $rows = $this->db->query(
            "SELECT module_key FROM customer_portal_permissions WHERE customer_id = ? AND kind = 'module' AND enabled = 1",
            [$customerId]
        ) ?: [];
        return array_values(array_intersect(array_column($rows, 'module_key'), self::MODULES));
    }

    public function moduleEnabled(int $customerId, string $module): bool
    {
        return in_array($module, $this->enabledModules($customerId), true);
    }

    /** Tab-Reihenfolge + Labels fuer das Kundenportal (analog Steckbrief). */
    public const TAB_ORDER  = ['uebersicht', 'inhalte', 'personen', 'websites', 'marke', 'dateien', 'sonstiges'];
    public const TAB_LABELS = ['uebersicht' => 'Übersicht', 'inhalte' => 'Inhalte', 'personen' => 'Personen', 'websites' => 'Websites', 'marke' => 'Marke', 'dateien' => 'Dateien', 'sonstiges' => 'Sonstiges'];

    /**
     * Fuer den Kunden freigegebene Steckbrief-Karten, nach Tab gruppiert.
     * Jede Kachel ist eine bewusste Team-Freigabe (`customer_visible=1`), jeder Typ.
     * Bodies werden auf anzeigegeeignete Felder reduziert.
     */
    public function visibleCardsByTab(int $customerId): array
    {
        $rows = $this->db->query(
            "SELECT id, type, title, target_tab, column_idx, sort_order, body, is_system, system_key
             FROM customer_cards
             WHERE customer_id = ? AND customer_visible = 1
             ORDER BY target_tab, column_idx, sort_order, id",
            [$customerId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            if ((int)$r['is_system'] === 1) {
                // System-Kachel: Inhalt aus der echten Datenquelle rendern
                $body = $this->renderSystemCard((string)$r['system_key'], $customerId);
                if ($body === null) continue;
                $card = ['id' => (int)$r['id'], 'type' => '_system', 'system_key' => (string)$r['system_key'], 'title' => $r['title'], 'body' => $body];
            } else {
                $body = $this->renderCardBody($r['type'], $r['body'], (int)$r['id']);
                if ($body === null) continue;
                $card = ['id' => (int)$r['id'], 'type' => $r['type'], 'title' => $r['title'], 'body' => $body];
            }
            $tab = in_array($r['target_tab'], self::TAB_ORDER, true) ? $r['target_tab'] : 'sonstiges';
            $out[$tab][] = $card;
        }
        return $out;
    }

    /** Body je Typ auf anzeigegeeignete Felder reduzieren. Liefert null bei leer. */
    private function renderCardBody(string $type, ?string $bodyJson, int $cardId): ?array
    {
        $b = $bodyJson ? (json_decode($bodyJson, true) ?: []) : [];
        switch ($type) {
            case 'richtext':
                $html = trim((string)($b['html'] ?? ''));
                return $html !== '' ? ['html' => $html] : null;
            case 'kpi':
                $items = [];
                foreach (($b['items'] ?? []) as $it) {
                    if (trim((string)($it['label'] ?? '')) === '' && trim((string)($it['value'] ?? '')) === '') continue;
                    $items[] = ['label' => (string)($it['label'] ?? ''), 'value' => (string)($it['value'] ?? ''), 'target' => (string)($it['target'] ?? ''), 'period' => (string)($it['period'] ?? '')];
                }
                return $items ? ['items' => $items] : null;
            case 'brand':
                $colors = [];
                foreach (($b['colors'] ?? []) as $c) { if (trim((string)($c['value'] ?? '')) === '') continue; $colors[] = ['name' => (string)($c['name'] ?? ''), 'value' => (string)($c['value'] ?? '')]; }
                $fonts = [];
                foreach (($b['fonts'] ?? []) as $f) { if (trim((string)($f['name'] ?? '')) === '') continue; $fonts[] = ['name' => (string)($f['name'] ?? '')]; }
                return ($colors || $fonts) ? ['colors' => $colors, 'fonts' => $fonts] : null;
            case 'contacts':
                $groups = [];
                foreach (($b['groups'] ?? []) as $g) {
                    $people = [];
                    foreach (($g['people'] ?? []) as $p) {
                        if (trim((string)($p['name'] ?? '')) === '') continue;
                        $people[] = ['name' => (string)($p['name'] ?? ''), 'role' => (string)($p['role'] ?? ''), 'initials' => (string)($p['initials'] ?? ''), 'email' => (string)($p['email'] ?? ''), 'phone' => (string)($p['phone'] ?? '')];
                    }
                    if ($people) $groups[] = ['title' => (string)($g['title'] ?? ''), 'people' => $people];
                }
                return $groups ? ['groups' => $groups] : null;
            case 'links':
                $items = [];
                foreach (($b['items'] ?? []) as $it) { if (trim((string)($it['url'] ?? '')) === '' && trim((string)($it['title'] ?? '')) === '') continue; $items[] = ['title' => (string)($it['title'] ?? ''), 'url' => (string)($it['url'] ?? ''), 'note' => (string)($it['note'] ?? '')]; }
                return $items ? ['items' => $items] : null;
            case 'accounts':
                $items = [];
                foreach (($b['items'] ?? []) as $it) { if (trim((string)($it['label'] ?? '')) === '' && trim((string)($it['account_id'] ?? '')) === '') continue; $items[] = ['label' => (string)($it['label'] ?? ''), 'account_id' => (string)($it['account_id'] ?? ''), 'url' => (string)($it['url'] ?? ''), 'note' => (string)($it['note'] ?? '')]; }
                return $items ? ['items' => $items] : null;
            case 'documents':
            case 'images':
                $files = $this->db->query("SELECT id, file_name, mime_type, file_size, title FROM customer_card_files WHERE card_id = ? ORDER BY sort_order, id", [$cardId]) ?: [];
                $note = trim((string)($b['note'] ?? ''));
                $fl = array_map(fn($f) => ['id' => (int)$f['id'], 'name' => $f['title'] ?: $f['file_name'], 'mime' => $f['mime_type'], 'size' => (int)$f['file_size']], $files);
                return ($fl || $note !== '') ? ['note' => $note, 'files' => $fl, 'kind' => $type] : null;
        }
        return null;
    }

    /** System-Kachel (leerer Body, Spezial-Widget) fuer das Portal aus echten Datenquellen rendern. */
    private function renderSystemCard(string $key, int $customerId): ?array
    {
        $c = $this->db->queryOne(
            "SELECT name, slug, description, industry, target_audience, tone_of_voice, products_services, unique_selling_points, brand_values, website, abbreviation, hex_color, asana_projekt_name FROM customers WHERE id = ?",
            [$customerId]
        );
        if (!$c) return null;
        $rows = [];
        $note = '';
        switch ($key) {
            case 'profile':
                if ($c['name'])         $rows[] = ['label' => 'Firmenname', 'value' => $c['name']];
                if ($c['abbreviation']) $rows[] = ['label' => 'Kürzel', 'value' => $c['abbreviation']];
                if ($c['industry'])     $rows[] = ['label' => 'Branche', 'value' => $c['industry']];
                if ($c['slug'])         $rows[] = ['label' => 'Slug', 'value' => $c['slug']];
                break;
            case 'website':
                require_once SERVICES_PATH . '/PageMonitorService.php';
                foreach ((new \Services\PageMonitorService($this->db))->websitesForCustomer($customerId) as $w) {
                    $rows[] = ['label' => $w['label'] ?: ($w['is_primary'] ? 'Hauptseite' : 'Website'), 'value' => $w['url'], 'url' => $w['url']];
                }
                if (!$rows && $c['website']) $rows[] = ['label' => 'Website', 'value' => $c['website'], 'url' => $c['website']];
                break;
            case 'markenprofil':
                foreach ([['description', 'Beschreibung'], ['target_audience', 'Zielgruppe'], ['products_services', 'Produkte / Services'], ['unique_selling_points', 'USPs'], ['tone_of_voice', 'Tonalität'], ['brand_values', 'Markenwerte']] as [$f, $lbl]) {
                    $v = trim((string)($c[$f] ?? ''));
                    if ($v !== '') $rows[] = ['label' => $lbl, 'value' => $v];
                }
                if ($c['hex_color']) $rows[] = ['label' => 'Hauptfarbe', 'value' => $c['hex_color'], 'color' => $c['hex_color']];
                break;
            case 'site_monitor':
                return $this->renderMonitorCard($customerId);
            // Freigegeben = sichtbar (wie fuer eine Team-Person). Echter Inhalt aus den Datenquellen:
            case 'regeln':
                $rl = $this->db->query("SELECT name, rule_content FROM rules WHERE customer_id = ? ORDER BY priority DESC, id LIMIT 50", [$customerId]) ?: [];
                $items = [];
                foreach ($rl as $r) {
                    $nm = trim((string)$r['name']); $ct = trim((string)$r['rule_content']);
                    if ($nm === '' && $ct === '') continue;
                    $items[] = ['title' => $nm, 'text' => $ct];
                }
                return $items ? ['kind' => 'rules', 'items' => $items] : null;
            case 'knowledge':
                // Private Dokumente HART ausschliessen: Der Betrachter ist hier ein Kunde und
                // damit niemals deren Besitzer. Ohne diese Grenze wuerden spaeter Titel aus
                // dem privaten Postfach im Kundenportal auftauchen.
                $cnt = (int) ($this->db->queryOne("SELECT COUNT(*) c FROM knowledge_documents WHERE customer_id = ? AND is_active = 1 AND visibility <> 'privat'", [$customerId])['c'] ?? 0);
                if ($cnt === 0) return null;
                $docs = $this->db->query("SELECT title, description, source_type, category, updated_at, created_at FROM knowledge_documents WHERE customer_id = ? AND is_active = 1 AND visibility <> 'privat' ORDER BY updated_at DESC LIMIT 50", [$customerId]) ?: [];
                return ['kind' => 'knowledge', 'count' => $cnt, 'items' => array_map(fn($d) => [
                    'title' => (string)$d['title'], 'description' => (string)$d['description'], 'source_type' => (string)$d['source_type'],
                    'category' => (string)$d['category'], 'date' => substr((string)($d['updated_at'] ?: $d['created_at']), 0, 10),
                ], $docs)];
            case 'asana':
                return $this->renderAsanaCard($customerId);
        }
        if (!$rows && $note === '') return null;
        return ['kind' => 'system', 'rows' => $rows, 'note' => $note];
    }

    /** Website-Monitoring (Uptime/Response aus pm_monitors + Log der letzten 30 Tage). */
    private function renderMonitorCard(int $customerId): ?array
    {
        $m = $this->db->queryOne("SELECT id, url, status, last_response_time, last_check FROM pm_monitors WHERE customer_id = ? ORDER BY id LIMIT 1", [$customerId]);
        if (!$m) return null;
        $agg = $this->db->queryOne(
            "SELECT COUNT(*) total, SUM(is_up) ups, AVG(response_time_ms) avgrt FROM pm_monitor_log WHERE monitor_id = ? AND checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [(int)$m['id']]
        );
        $total = (int)($agg['total'] ?? 0);
        $uptime = $total > 0 ? round((int)$agg['ups'] / $total * 100, 2) : null;
        $avg = $agg['avgrt'] !== null ? (int)round($agg['avgrt']) : (int)$m['last_response_time'];
        return [
            'kind' => 'monitor', 'url' => (string)$m['url'], 'status' => (string)$m['status'],
            'uptime' => $uptime, 'avg_ms' => $avg, 'last_check' => (string)$m['last_check'],
        ];
    }

    /** Asana-Aufgaben der konfigurierten Sektion (live, 5-Min-Cache, kundengerecht). */
    private function renderAsanaCard(int $customerId): ?array
    {
        $c = $this->db->queryOne("SELECT asana_section_gid, asana_projekt_name FROM customers WHERE id = ?", [$customerId]);
        $section = (string)($c['asana_section_gid'] ?? '');
        if ($section === '') return null;

        $cacheFile = STORAGE_PATH . '/cache/asana_section_' . preg_replace('/[^0-9]/', '', $section) . '.json';
        $items = null;
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $items = json_decode((string)file_get_contents($cacheFile), true);
        }
        if ($items === null) {
            try {
                $pat = (string) \Core\Settings::get('asana_pat');
                if ($pat === '') return null;
                require_once SERVICES_PATH . '/AsanaService.php';
                $asana = new \Services\AsanaService($pat, 8);
                $tasks = $asana->listTasksInSection($section, 30);
                $items = [];
                foreach ($tasks as $t) {
                    $nm = trim((string)($t['name'] ?? ''));
                    if ($nm === '') continue;
                    $items[] = ['name' => $nm, 'done' => !empty($t['completed'])];
                }
                @mkdir(dirname($cacheFile), 0775, true);
                @file_put_contents($cacheFile, json_encode($items));
            } catch (\Throwable $e) { return null; }
        }
        return !empty($items) ? ['kind' => 'tasks', 'project' => (string)($c['asana_projekt_name'] ?? ''), 'items' => $items] : null;
    }

    /** Ist die Projektplanner-Dashboard-Kachel fuer den Kunden freigeschaltet? */
    public function projektplannerEnabled(int $customerId): bool
    {
        $row = $this->db->queryOne("SELECT enabled FROM customer_portal_permissions WHERE customer_id = ? AND kind = 'setting' AND module_key = 'shell_projektplanner'", [$customerId]);
        return $row !== null && (int)$row['enabled'] === 1;
    }

    /** Projektplanner-Dashboard exakt wie im Steckbrief — DIESELBE Komponente (PpWidgetRenderer). */
    public function projektplannerWidget(int $customerId): ?string
    {
        if (!$this->projektplannerEnabled($customerId)) return null;
        require_once SERVICES_PATH . '/PpWidgetRenderer.php';
        $r = new \Services\PpWidgetRenderer($this->db);
        $pid = $r->findLatestActivePlanIdForCustomer($customerId);
        if (!$pid) return null;
        return $r->renderForPlan($pid);
    }

    /** Karten-Datei (Dokument/Bild) fuer Download — inkl. customer_id + Sichtbarkeit zur Tenant-Pruefung. */
    public function cardFile(int $fileId): ?array
    {
        return $this->db->queryOne(
            "SELECT f.id, f.file_path, f.file_name, f.mime_type, c.customer_id, c.customer_visible
             FROM customer_card_files f JOIN customer_cards c ON c.id = f.card_id
             WHERE f.id = ?",
            [$fileId]
        ) ?: null;
    }

    // ── Unterhaltungen (Threads) ────────────────────────────────────────────

    /** Alle Unterhaltungen eines Kunden, neueste zuerst, mit letztem Schnipsel. */
    public function conversations(int $customerId): array
    {
        $rows = $this->db->query(
            "SELECT k.id, k.title, k.ki_active, k.updated_at,
                    (SELECT body FROM customer_portal_comments WHERE conversation_id = k.id ORDER BY id DESC LIMIT 1) AS last_body,
                    (SELECT COUNT(*) FROM customer_portal_comments WHERE conversation_id = k.id) AS msg_count
             FROM customer_portal_conversations k
             WHERE k.customer_id = ? ORDER BY k.updated_at DESC, k.id DESC",
            [$customerId]
        ) ?: [];
        return $rows;
    }

    /** Meta einer Unterhaltung (inkl. customer_id fuer Tenant-Pruefung). */
    public function conversation(int $convId): ?array
    {
        return $this->db->queryOne("SELECT id, customer_id, title, ki_active FROM customer_portal_conversations WHERE id = ?", [$convId]) ?: null;
    }

    /** Neue Unterhaltung anlegen, KI-Default vom Kunden-Setting. Liefert die ID. */
    public function createConversation(int $customerId, ?int $userId, string $title = ''): int
    {
        $title = trim($title) !== '' ? mb_substr(trim($title), 0, 200) : 'Neue Unterhaltung';
        return (int) $this->db->insert('customer_portal_conversations', [
            'customer_id' => $customerId, 'title' => $title,
            'ki_active' => $this->customerKiDefault($customerId) ? 1 : 0, 'created_by' => $userId,
        ]);
    }

    /** Sorgt fuer mindestens eine Unterhaltung; liefert die neueste/angelegte ID. */
    public function ensureConversation(int $customerId, ?int $userId): int
    {
        $row = $this->db->queryOne("SELECT id FROM customer_portal_conversations WHERE customer_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1", [$customerId]);
        return $row ? (int) $row['id'] : $this->createConversation($customerId, $userId);
    }

    public function renameConversation(int $convId, string $title): void
    {
        $this->db->execute("UPDATE customer_portal_conversations SET title = ? WHERE id = ?", [mb_substr(trim($title), 0, 200), $convId]);
    }

    // ── Nachrichten ─────────────────────────────────────────────────────────

    /** Nachrichten einer Unterhaltung inkl. Datei-Anhaenge. */
    public function comments(int $convId, int $limit = 300): array
    {
        $rows = $this->db->query(
            "SELECT c.id, c.author_role, c.body, c.created_at, u.name AS author_name
             FROM customer_portal_comments c
             LEFT JOIN users u ON u.id = c.author_user_id
             WHERE c.conversation_id = ? ORDER BY c.id ASC LIMIT " . (int)$limit,
            [$convId]
        ) ?: [];
        if (!$rows) return [];
        $ids = array_column($rows, 'id');
        $att = $this->attachmentsForComments($ids);
        foreach ($rows as &$r) { $r['attachments'] = $att[(int)$r['id']] ?? []; }
        return $rows;
    }

    /** Nachricht anlegen ($role: team|customer|ki), Unterhaltung hochdatieren, ggf. Titel setzen. */
    public function addComment(int $convId, int $customerId, int $userId, string $role, string $body): int
    {
        $body = trim($body);
        $role = in_array($role, ['customer', 'ki'], true) ? $role : 'team';
        $id = (int) $this->db->insert('customer_portal_comments', [
            'customer_id'     => $customerId,
            'conversation_id' => $convId,
            'author_user_id'  => $userId,
            'author_role'     => $role,
            'body'            => mb_substr($body, 0, 4000),
        ]);
        $this->db->execute("UPDATE customer_portal_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$convId]);
        // Auto-Titel aus erster Kundennachricht
        if ($role === 'customer') {
            $conv = $this->conversation($convId);
            if ($conv && in_array($conv['title'], ['Neue Unterhaltung', 'Bisherige Unterhaltung'], true)) {
                $this->renameConversation($convId, mb_substr($body, 0, 60));
            }
        }
        return $id;
    }

    // ── KI-Automatik je Unterhaltung ────────────────────────────────────────

    public function kiActive(int $convId): bool
    {
        $row = $this->db->queryOne("SELECT ki_active FROM customer_portal_conversations WHERE id = ?", [$convId]);
        return $row === null ? true : ((int)$row['ki_active'] === 1);
    }

    public function setKiActive(int $convId, bool $on, ?int $userId = null): void
    {
        $this->db->execute("UPDATE customer_portal_conversations SET ki_active = ? WHERE id = ?", [$on ? 1 : 0, $convId]);
    }

    /** Kunden-weiter KI-Default (Setting in der Permission-Matrix). */
    private function customerKiDefault(int $customerId): bool
    {
        $row = $this->db->queryOne("SELECT enabled FROM customer_portal_permissions WHERE customer_id = ? AND kind = 'setting' AND module_key = 'ki_active'", [$customerId]);
        return $row === null ? true : ((int)$row['enabled'] === 1);
    }

    // ── Datei-Anhaenge ──────────────────────────────────────────────────────

    /** Anhang-Datensatz speichern (Datei liegt bereits im Storage). Liefert ID. */
    public function storeAttachmentRecord(int $customerId, ?int $convId, string $orig, string $stored, ?string $mime, int $size, ?string $text, ?int $userId): int
    {
        return (int) $this->db->insert('customer_portal_attachments', [
            'customer_id' => $customerId, 'conversation_id' => $convId, 'comment_id' => null,
            'original_name' => mb_substr($orig, 0, 255), 'stored_name' => $stored,
            'mime' => $mime ? mb_substr($mime, 0, 120) : null, 'size' => $size,
            'extracted_text' => $text, 'created_by' => $userId,
        ]);
    }

    /** Anhaenge an eine Nachricht binden (nur eigene, ungebundene). */
    public function linkAttachments(array $ids, int $commentId, int $convId, int $customerId): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        foreach ($ids as $aid) {
            $this->db->execute(
                "UPDATE customer_portal_attachments SET comment_id = ?, conversation_id = ? WHERE id = ? AND customer_id = ? AND comment_id IS NULL",
                [$commentId, $convId, $aid, $customerId]
            );
        }
    }

    /** Anhaenge je Nachricht (gruppiert), nur Anzeige-Felder. */
    public function attachmentsForComments(array $commentIds): array
    {
        $commentIds = array_values(array_filter(array_map('intval', $commentIds)));
        if (!$commentIds) return [];
        $in = implode(',', $commentIds);
        $rows = $this->db->query("SELECT id, comment_id, original_name, mime, size FROM customer_portal_attachments WHERE comment_id IN ($in)") ?: [];
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['comment_id']][] = ['id' => (int)$r['id'], 'name' => $r['original_name'], 'mime' => $r['mime'], 'size' => (int)$r['size']]; }
        return $out;
    }

    /** Einzelner Anhang (fuer Download) — inkl. customer_id zur Tenant-Pruefung. */
    public function attachment(int $id): ?array
    {
        return $this->db->queryOne("SELECT id, customer_id, original_name, stored_name, mime FROM customer_portal_attachments WHERE id = ?", [$id]) ?: null;
    }

    /** Extrahierter Text der gewaehlten Anhaenge (nur eigene), fuer den KI-Kontext. */
    public function attachmentTextForKi(array $ids, int $customerId, int $maxChars = 8000): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return '';
        $in = implode(',', $ids);
        $rows = $this->db->query("SELECT original_name, extracted_text FROM customer_portal_attachments WHERE id IN ($in) AND customer_id = ?", [$customerId]) ?: [];
        $out = '';
        foreach ($rows as $r) {
            $t = trim((string)($r['extracted_text'] ?? ''));
            if ($t === '') continue;
            $out .= "\n\n[Angehaengte Datei: " . $r['original_name'] . "]\n" . mb_substr($t, 0, $maxChars);
        }
        return $out;
    }

    /**
     * Kundensicherer Kontext fuer die KI-Antwort. NUR kuratierte, freigegebene
     * Portal-Inhalte (Status, Meilensteine, freigegebene Karten). NIEMALS interne
     * Artefakte, Zugaenge, Dokumente, Links oder Kontakte.
     */
    public function aiContext(int $customerId): string
    {
        $modules = $this->enabledModules($customerId);
        $header = $this->customerHeader($customerId);
        $lines = [];
        $lines[] = 'Kunde: ' . ($header['name'] ?? '');

        if (in_array('projektstatus', $modules, true) && ($ps = $this->projektstatus($customerId))) {
            $z = trim((string)($ps['from'] ?? '') . (!empty($ps['to']) ? ' bis ' . $ps['to'] : ''));
            $lines[] = "\nProjektstatus: " . $ps['status'] . ' — ' . $ps['title'] . ($z !== '' ? " (Zeitraum: $z)" : '');
        }
        if (in_array('meilensteine', $modules, true) && ($ms = $this->meilensteine($customerId, 30))) {
            $lines[] = "\nMeilensteine:";
            foreach ($ms as $m) {
                $lines[] = '- [' . (!empty($m['erledigt']) ? 'erledigt' : 'offen') . '] ' . $m['titel'] . (!empty($m['datum']) ? ' (' . $m['datum'] . ')' : '');
            }
        }
        // Alle fuer den Kunden freigegebenen Karten (jede ist bewusste Team-Freigabe)
        foreach ($this->visibleCardsByTab($customerId) as $tab => $cards) {
            foreach ($cards as $card) {
                $title = $card['title'] ?: 'Inhalt';
                $bd = $card['body'];
                switch ($card['type']) {
                    case 'richtext':
                        $txt = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '</li>'], "\n", $bd['html'] ?? '')), ENT_QUOTES));
                        if ($txt !== '') $lines[] = "\n$title:\n$txt";
                        break;
                    case 'kpi':
                        $lines[] = "\n$title (Kennzahlen):";
                        foreach ($bd['items'] as $it) $lines[] = '- ' . $it['label'] . (!empty($it['period']) ? ' (' . $it['period'] . ')' : '') . ': ' . $it['value'];
                        break;
                    case 'brand':
                        $lines[] = "\n$title (Marke):";
                        foreach (($bd['colors'] ?? []) as $c) $lines[] = '- Farbe ' . ($c['name'] !== '' ? $c['name'] . ': ' : '') . $c['value'];
                        break;
                    case 'contacts':
                        $lines[] = "\n$title (Ansprechpartner):";
                        foreach ($bd['groups'] as $g) foreach ($g['people'] as $p) $lines[] = '- ' . $p['name'] . ($p['role'] !== '' ? ' (' . $p['role'] . ')' : '') . ($p['email'] !== '' ? ', ' . $p['email'] : '');
                        break;
                    case 'links':
                        $lines[] = "\n$title (Links):";
                        foreach ($bd['items'] as $it) $lines[] = '- ' . ($it['title'] !== '' ? $it['title'] . ': ' : '') . $it['url'];
                        break;
                    case 'accounts':
                        $lines[] = "\n$title (Konten/IDs):";
                        foreach ($bd['items'] as $it) $lines[] = '- ' . $it['label'] . ($it['account_id'] !== '' ? ': ' . $it['account_id'] : '');
                        break;
                    case 'documents':
                    case 'images':
                        $names = array_map(fn($f) => $f['name'], $bd['files'] ?? []);
                        if ($names) $lines[] = "\n$title (Dateien): " . implode(', ', $names);
                        break;
                    case '_system':
                        $sl = [];
                        $kind = $bd['kind'] ?? 'system';
                        if ($kind === 'rules') {
                            foreach (($bd['items'] ?? []) as $it) $sl[] = trim($it['title'] . ': ' . $it['text']);
                        } elseif ($kind === 'knowledge') {
                            $sl[] = ($bd['count'] ?? 0) . ' Wissenseinträge';
                            foreach (($bd['items'] ?? []) as $it) $sl[] = $it['title'];
                        } elseif ($kind === 'tasks') {
                            foreach (($bd['items'] ?? []) as $it) $sl[] = (!empty($it['done']) ? '[erledigt] ' : '[offen] ') . $it['name'];
                        } elseif ($kind === 'monitor') {
                            $sl[] = 'Status: ' . ($bd['status'] === 'up' ? 'Online' : 'Offline');
                            if ($bd['uptime'] !== null) $sl[] = 'Uptime (30 Tage): ' . $bd['uptime'] . ' %';
                            $sl[] = 'Ø Antwortzeit: ' . $bd['avg_ms'] . ' ms';
                        } else {
                            foreach (($bd['rows'] ?? []) as $rw) $sl[] = $rw['label'] . ': ' . $rw['value'];
                            if (!empty($bd['note'])) $sl[] = $bd['note'];
                        }
                        if ($sl) $lines[] = "\n$title:\n- " . implode("\n- ", $sl);
                        break;
                }
            }
        }
        return implode("\n", $lines);
    }

    /** Aktueller Projektstatus aus dem Projektplaner, kundengerecht (ohne interne Felder). */
    public function projektstatus(int $customerId): ?array
    {
        $plan = $this->db->queryOne(
            "SELECT title, plan_status, period_from, period_to
             FROM pp_plans
             WHERE customer_id = ? AND state = 1 AND plan_status IN ('aktiv','einzelprojekt','reporting')
             ORDER BY updated_at DESC LIMIT 1",
            [$customerId]
        );
        if (!$plan) return null;
        $labels = ['aktiv' => 'Läuft', 'einzelprojekt' => 'Läuft', 'reporting' => 'Reporting', 'entwurf' => 'In Vorbereitung'];
        return [
            'title'  => $plan['title'],
            'status' => $labels[$plan['plan_status']] ?? 'Läuft',
            'from'   => $plan['period_from'],
            'to'     => $plan['period_to'],
        ];
    }

    /** Meilensteine (erreicht/anstehend) aus pp_plan_rows des aktuellen Plans. */
    public function meilensteine(int $customerId, int $limit = 12): array
    {
        $plan = $this->db->queryOne(
            "SELECT id FROM pp_plans WHERE customer_id = ? AND state = 1 AND plan_status IN ('aktiv','einzelprojekt','reporting') ORDER BY updated_at DESC LIMIT 1",
            [$customerId]
        );
        if (!$plan) return [];
        $rows = $this->db->query(
            "SELECT description, deadline, is_done FROM pp_plan_rows
             WHERE plan_id = ? AND deadline IS NOT NULL AND TRIM(COALESCE(description,'')) <> ''
             ORDER BY deadline ASC LIMIT " . (int)$limit,
            [(int)$plan['id']]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['titel' => (string)$r['description'], 'datum' => $r['deadline'], 'erledigt' => (int)$r['is_done'] === 1];
        }
        return $out;
    }
}
