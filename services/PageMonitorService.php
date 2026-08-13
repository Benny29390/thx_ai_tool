<?php
namespace Services;

use Core\Database;

/**
 * PageMonitorService — Website-Monitoring (Uptime + Response-Time).
 *
 * Tabellen:
 *   - pm_monitors           (Konfiguration der Websites)
 *   - pm_monitor_log        (Jeder Check eine Zeile, ältere > 90 Tage werden gelöscht)
 *   - pm_monitor_incidents  (Down-Phasen mit started_at/ended_at/duration_minutes)
 *
 * Cron-Logik (in `scripts/pm-check.php`):
 *   - Alle aktiven Monitore alle X Minuten prüfen (Standard 2)
 *   - HTTP-GET mit 15s timeout, sslverify=false
 *   - Status 200-399 = up, sonst down
 *   - WP-Spezial-Body-Erkennung („Error establishing a database connection" etc.) → down
 *   - Bei 1. Fail: Incident anlegen, keine Mail
 *   - Bei 2. consecutive Fail: Alert-Mail (genau einmal)
 *   - Bei Recovery (down→up): Recovery-Mail (nur wenn Alert raus war)
 *   - Reports wöchentlich (Montag) + monatlich (1. des Monats)
 */
class PageMonitorService
{
    private Database $db;

    /** Mail-Log liegt innerhalb von /var/www, damit es trotz PHP open_basedir lesbar ist. */
    private const MAIL_LOG_FILE = '/var/www/storage/logs/pm-mail.log';

    /** WP-Body-Strings, die einen scheinbar OK-200 trotzdem als down markieren */
    private const ERROR_PATTERNS = [
        'Error establishing a database connection',
        'Fehler beim Aufbau einer Datenbankverbindung',
        'Briefly unavailable for scheduled maintenance',
        'Wegen geplanter Wartungsarbeiten kurzzeitig nicht verfügbar',
    ];

    public function __construct(Database $db) { $this->db = $db; }

    // ===== Liste + Stats =====

    /**
     * Liste aller Monitore mit 24h-Statistik (uptime_24h, up_24h, total_24h, last_24h_max_response).
     */
    public function getAll(array $filter = []): array
    {
        $sql = "SELECT m.*, c.name AS customer_name, c.abbreviation AS customer_abbr, c.hex_color AS customer_color,
                       c.logo_path AS customer_logo, c.settings AS customer_settings,
                       (SELECT COUNT(*) FROM pm_monitor_log l WHERE l.monitor_id = m.id AND l.checked_url = m.url AND l.checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS total_24h,
                       (SELECT COUNT(*) FROM pm_monitor_log l WHERE l.monitor_id = m.id AND l.checked_url = m.url AND l.is_up = 1 AND l.checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS up_24h
                FROM pm_monitors m
                LEFT JOIN customers c ON c.id = m.customer_id
                WHERE 1=1";
        $params = [];
        if (!empty($filter['customer_id'])) { $sql .= " AND m.customer_id = ?"; $params[] = (int) $filter['customer_id']; }
        if (!empty($filter['category'])) { $sql .= " AND m.category = ?"; $params[] = $filter['category']; }
        if (!empty($filter['status'])) { $sql .= " AND m.status = ?"; $params[] = $filter['status']; }
        $sql .= " ORDER BY m.label ASC";
        $rows = $this->db->query($sql, $params) ?: [];
        foreach ($rows as &$r) {
            $total = (int) $r['total_24h']; $up = (int) $r['up_24h'];
            $r['uptime_24h'] = $total > 0 ? round(($up / $total) * 100, 2) : 100.0;
            $r['sub_urls_arr'] = !empty($r['sub_urls']) ? (json_decode($r['sub_urls'], true) ?: []) : [];
            // Customer-Tags aus settings.tags fuer Filter / Anzeige
            $s = !empty($r['customer_settings']) ? (json_decode($r['customer_settings'], true) ?: []) : [];
            $r['customer_tags'] = $s['tags'] ?? [];
            unset($r['customer_settings']);
        }
        unset($r);
        return $rows;
    }

    public function getById(int $id): ?array
    {
        $row = $this->db->queryOne(
            "SELECT m.*, c.name AS customer_name, c.abbreviation AS customer_abbr, c.hex_color AS customer_color
             FROM pm_monitors m LEFT JOIN customers c ON c.id = m.customer_id
             WHERE m.id = ?", [$id]
        );
        if (!$row) return null;
        $row['sub_urls_arr'] = !empty($row['sub_urls']) ? (json_decode($row['sub_urls'], true) ?: []) : [];
        return $row;
    }

    public function getCategories(): array
    {
        $rows = $this->db->query(
            "SELECT category, COUNT(*) AS cnt FROM pm_monitors WHERE category IS NOT NULL AND category != ''
             GROUP BY category ORDER BY category ASC"
        ) ?: [];
        return $rows;
    }

    // ===== CRUD =====

    public function save(array $data, int $userId): int
    {
        $url = trim((string) ($data['url'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));
        if ($url === '' || $label === '') throw new \RuntimeException('URL und Label sind Pflicht');
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new \RuntimeException('URL ungültig');

        $report = in_array($data['report_schedule'] ?? '', ['none', 'weekly', 'monthly', 'both'], true)
            ? $data['report_schedule'] : 'both';

        $subUrls = null;
        if (!empty($data['sub_urls'])) {
            $arr = is_array($data['sub_urls']) ? $data['sub_urls']
                : array_filter(array_map('trim', explode("\n", (string) $data['sub_urls'])));
            $arr = array_values(array_filter($arr, fn($u) => filter_var(trim((string) $u), FILTER_VALIDATE_URL)));
            if (!empty($arr)) $subUrls = json_encode($arr);
        }

        $row = [
            'url' => $url,
            'label' => $label,
            'customer_id' => !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            'alert_email' => !empty($data['alert_email']) && filter_var($data['alert_email'], FILTER_VALIDATE_EMAIL)
                ? $data['alert_email'] : null,
            'report_schedule' => $report,
            'sub_urls' => $subUrls,
            'category' => trim((string) ($data['category'] ?? '')),
        ];

        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            $this->db->update('pm_monitors', $row, 'id = ?', [$id]);
            return $id;
        }
        $row['created_by'] = $userId;
        return (int) $this->db->insert('pm_monitors', $row);
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM pm_monitors WHERE id = ?", [$id]);
    }

    public function togglePause(int $id): string
    {
        $cur = $this->db->queryValue("SELECT status FROM pm_monitors WHERE id = ?", [$id]);
        $newStatus = $cur === 'paused' ? 'up' : 'paused';
        $this->db->update('pm_monitors', ['status' => $newStatus], 'id = ?', [$id]);
        return $newStatus;
    }

    // ── Kunden-Websites (pm_monitors als einzige Quelle) ────────────────────────

    private function normHost(string $u): string
    {
        $u = strtolower(trim($u));
        $u = preg_replace('#^https?://#', '', $u);
        $u = preg_replace('#^www\.#', '', $u);
        return rtrim((string)$u, '/');
    }

    /** customers.website (+ Crawl-Start-URL bei gleichem Host) auf die primäre Website spiegeln. */
    private function syncPrimaryToCustomer(int $customerId): void
    {
        $p = $this->db->queryOne("SELECT url FROM pm_monitors WHERE customer_id = ? AND is_primary = 1 LIMIT 1", [$customerId]);
        $newUrl = $p['url'] ?? null;
        $this->db->execute("UPDATE customers SET website = ? WHERE id = ?", [$newUrl, $customerId]);
        if ($newUrl) {
            // Crawl-Start-URL mitführen, wenn sie denselben Host hatte (oder leer war) — echte abweichende Start-URLs bleiben.
            $row = $this->db->queryOne("SELECT settings FROM customers WHERE id = ?", [$customerId]);
            $s = json_decode($row['settings'] ?? '{}', true) ?: [];
            if (isset($s['website_crawl'])) {
                $su = trim((string)($s['website_crawl']['start_url'] ?? ''));
                if ($su === '' || $this->normHost($su) === $this->normHost($newUrl)) {
                    $s['website_crawl']['start_url'] = $newUrl;
                    $this->db->execute("UPDATE customers SET settings = ? WHERE id = ?", [json_encode($s), $customerId]);
                }
            }
        }
    }

    /** Websites eines Kunden (= seine Monitore), Primär zuerst. monitoring=false ⇒ pausiert. */
    public function websitesForCustomer(int $customerId): array
    {
        $rows = $this->db->query("SELECT id, url, label, is_primary, status FROM pm_monitors WHERE customer_id = ? ORDER BY is_primary DESC, label ASC, id", [$customerId]) ?: [];
        return array_map(fn($r) => [
            'id' => (int)$r['id'], 'url' => $r['url'], 'label' => (string)$r['label'],
            'is_primary' => (int)$r['is_primary'] === 1, 'monitoring' => $r['status'] !== 'paused',
        ], $rows);
    }

    /** Neue Website (= Monitor) für einen Kunden. $monitor=false ⇒ nur registriert (paused). */
    public function addWebsiteForCustomer(int $customerId, string $url, string $label, bool $monitor, int $userId): int
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new \RuntimeException('URL ungültig');
        $label = trim($label) !== '' ? trim($label) : (parse_url($url, PHP_URL_HOST) ?: 'Website');
        $norm = $this->normHost($url);
        foreach ($this->db->query("SELECT url FROM pm_monitors WHERE customer_id = ?", [$customerId]) ?: [] as $m) {
            if ($this->normHost($m['url']) === $norm) throw new \RuntimeException('Diese Website ist bereits hinterlegt.');
        }
        $isFirst = !$this->db->queryOne("SELECT id FROM pm_monitors WHERE customer_id = ?", [$customerId]);
        $id = (int) $this->db->insert('pm_monitors', [
            'customer_id' => $customerId, 'url' => $url, 'label' => $label,
            'status' => $monitor ? 'up' : 'paused', 'is_primary' => $isFirst ? 1 : 0,
            'category' => 'Kunde', 'created_by' => $userId,
        ]);
        if ($isFirst) $this->syncPrimaryToCustomer($customerId);
        return $id;
    }

    /** Monitoring an/aus (paused) — nur für eine Website dieses Kunden. */
    public function setMonitoring(int $customerId, int $monitorId, bool $on): void
    {
        $this->db->execute("UPDATE pm_monitors SET status = ? WHERE id = ? AND customer_id = ?", [$on ? 'up' : 'paused', $monitorId, $customerId]);
    }

    /** Eine Website als primär markieren (nur innerhalb des Kunden). */
    public function setPrimaryWebsite(int $customerId, int $monitorId): void
    {
        $this->db->execute("UPDATE pm_monitors SET is_primary = 0 WHERE customer_id = ?", [$customerId]);
        $this->db->execute("UPDATE pm_monitors SET is_primary = 1 WHERE id = ? AND customer_id = ?", [$monitorId, $customerId]);
        $this->syncPrimaryToCustomer($customerId);
    }

    /** Website entfernen; war sie primär, wird die nächste primär. */
    public function removeWebsite(int $customerId, int $monitorId): void
    {
        $wasPrimary = (int)($this->db->queryValue("SELECT is_primary FROM pm_monitors WHERE id = ? AND customer_id = ?", [$monitorId, $customerId]) ?? 0) === 1;
        $this->db->execute("DELETE FROM pm_monitors WHERE id = ? AND customer_id = ?", [$monitorId, $customerId]);
        if ($wasPrimary) {
            $next = $this->db->queryOne("SELECT id FROM pm_monitors WHERE customer_id = ? ORDER BY id LIMIT 1", [$customerId]);
            if ($next) $this->db->execute("UPDATE pm_monitors SET is_primary = 1 WHERE id = ?", [(int)$next['id']]);
            $this->syncPrimaryToCustomer($customerId);
        }
    }

    public function setCategoryBulk(array $ids, string $category): int
    {
        $ids = array_map('intval', array_filter($ids));
        if (empty($ids)) return 0;
        $in = implode(',', $ids);
        return $this->db->execute(
            "UPDATE pm_monitors SET category = ? WHERE id IN ($in)", [$category]
        );
    }

    // ===== Batch-Import =====

    /**
     * Importiert URLs Zeile für Zeile, holt Title via cURL.
     * @return array ['imported' => int, 'errors' => string[]]
     */
    public function batchImport(string $urlsRaw, ?int $customerId, string $category, string $reportSchedule, int $userId): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $urlsRaw)));
        $imported = 0; $errors = [];
        foreach ($lines as $line) {
            if (!filter_var($line, FILTER_VALIDATE_URL)) { $errors[] = $line; continue; }
            $exists = $this->db->queryValue("SELECT id FROM pm_monitors WHERE url = ?", [$line]);
            if ($exists) { $errors[] = $line . ' (existiert bereits)'; continue; }
            $title = $this->fetchPageTitle($line);
            if (!$title) {
                $parsed = parse_url($line);
                $title = $parsed['host'] ?? $line;
            }
            $this->db->insert('pm_monitors', [
                'url' => $line, 'label' => $title,
                'customer_id' => $customerId ?: null,
                'category' => $category,
                'report_schedule' => in_array($reportSchedule, ['none','weekly','monthly','both'], true) ? $reportSchedule : 'both',
                'created_by' => $userId, 'status' => 'up',
            ]);
            $imported++;
        }
        return ['imported' => $imported, 'errors' => $errors];
    }

    public function fetchPageTitle(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'KI-Tool-SiteMonitor/1.0',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!is_string($body)) return '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $body, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            return preg_replace('/\s*[\|–—-]\s*$/', '', $title);
        }
        return '';
    }

    // ===== Cron-Check =====

    /**
     * Prüft einen einzelnen Monitor + alle seine Sub-URLs.
     * Schreibt pro URL ein Log, updated Monitor-Status, verwaltet Incidents + Mails.
     * @return array ['checked' => N, 'is_down' => bool, 'mail_sent' => 'down'|'recovery'|null]
     */
    public function runCheck(int $monitorId): array
    {
        $monitor = $this->db->queryOne("SELECT * FROM pm_monitors WHERE id = ?", [$monitorId]);
        if (!$monitor || $monitor['status'] === 'paused') {
            return ['checked' => 0, 'is_down' => false, 'mail_sent' => null];
        }
        $urls = [$monitor['url']];
        if (!empty($monitor['sub_urls'])) {
            $sub = json_decode($monitor['sub_urls'], true);
            if (is_array($sub)) $urls = array_merge($urls, $sub);
        }
        $allUp = true; $mainCode = 0; $mainMs = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($urls as $i => $url) {
            $url = trim($url);
            if (!$url) continue;
            [$code, $ms, $isUp] = $this->httpCheck($url);
            $this->db->insert('pm_monitor_log', [
                'monitor_id' => $monitorId, 'checked_url' => $url,
                'status_code' => $code, 'response_time_ms' => $ms,
                'is_up' => $isUp, 'checked_at' => $now,
            ]);
            if (!$isUp) $allUp = false;
            if ($i === 0) { $mainCode = $code; $mainMs = $ms; }
        }
        $newStatus = $allUp ? 'up' : 'down';
        $oldStatus = $monitor['status'];
        $this->db->update('pm_monitors', [
            'status' => $newStatus, 'last_check' => $now,
            'last_status_code' => $mainCode, 'last_response_time' => $mainMs,
        ], 'id = ?', [$monitorId]);

        $mailSent = null;

        // 1. Fail: Incident anlegen
        if ($oldStatus !== 'down' && $newStatus === 'down') {
            $this->db->insert('pm_monitor_incidents', [
                'monitor_id' => $monitorId, 'started_at' => $now, 'notified' => 0,
            ]);
        }
        // 2. consecutive Fail: Alert
        if ($newStatus === 'down') {
            $recent = $this->db->query(
                "SELECT is_up FROM pm_monitor_log
                 WHERE monitor_id = ? AND checked_url = ?
                 ORDER BY checked_at DESC LIMIT 10",
                [$monitorId, $monitor['url']]
            ) ?: [];
            $consecutive = 0;
            foreach ($recent as $r) { if ((int) $r['is_up'] === 0) $consecutive++; else break; }
            if ($consecutive === 2) {
                $incident = $this->db->queryOne(
                    "SELECT id, notified FROM pm_monitor_incidents
                     WHERE monitor_id = ? AND ended_at IS NULL
                     ORDER BY started_at DESC LIMIT 1", [$monitorId]
                );
                if ($incident && !$incident['notified']) {
                    $this->db->update('pm_monitor_incidents', ['notified' => 1], 'id = ?', [(int) $incident['id']]);
                }
                $monitor['last_status_code'] = $mainCode;
                $this->sendAlertMail($monitor, 'down');
                $mailSent = 'down';
            }
        }
        // Recovery: down → up
        if ($oldStatus === 'down' && $newStatus === 'up') {
            $incident = $this->db->queryOne(
                "SELECT id, started_at, notified FROM pm_monitor_incidents
                 WHERE monitor_id = ? AND ended_at IS NULL
                 ORDER BY started_at DESC LIMIT 1", [$monitorId]
            );
            $wasNotified = false;
            if ($incident) {
                $wasNotified = ((int) $incident['notified']) === 1;
                $duration = round((strtotime($now) - strtotime($incident['started_at'])) / 60);
                $this->db->update('pm_monitor_incidents', [
                    'ended_at' => $now, 'duration_minutes' => $duration,
                ], 'id = ?', [(int) $incident['id']]);
            }
            if ($wasNotified) {
                $this->sendAlertMail($monitor, 'recovery');
                $mailSent = 'recovery';
            }
        }

        return ['checked' => count($urls), 'is_down' => !$allUp, 'mail_sent' => $mailSent];
    }

    private function httpCheck(string $url): array
    {
        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'KI-Tool-SiteMonitor/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $ms = (int) round((microtime(true) - $start) * 1000);
        if ($err || $code === 0) return [0, $ms, 0];
        $isUp = ($code >= 200 && $code < 400) ? 1 : 0;
        if ($isUp && is_string($body)) {
            foreach (self::ERROR_PATTERNS as $p) {
                if (stripos($body, $p) !== false) return [503, $ms, 0];
            }
        }
        return [$code, $ms, $isUp];
    }

    // ===== Mails =====

    private function sendAlertMail(array $monitor, string $type): void
    {
        $to = !empty($monitor['alert_email']) ? $monitor['alert_email'] : $this->defaultAlertEmail();
        if (!$to) return;
        $label = htmlspecialchars($monitor['label'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $rawUrl = (string) ($monitor['url'] ?? '');
        $url = htmlspecialchars($rawUrl, ENT_QUOTES, 'UTF-8');
        $code = (int) ($monitor['last_status_code'] ?? 0);
        $now = date('d.m.Y H:i');

        // Link zur Detail-Ansicht (Monitor öffnen)
        $pmId = isset($monitor['id']) && is_numeric($monitor['id']) ? (int) $monitor['id'] : $this->findMonitorIdByUrl($rawUrl);
        $detailUrl = htmlspecialchars($this->monitorUrlByPmId($pmId), ENT_QUOTES, 'UTF-8');

        if ($type === 'down') {
            $subject = "DOWN: $label ({$rawUrl})";
            $headerBg = '#d32f2f'; $title = 'Website ist nicht erreichbar';
            $statusLine = "<tr><td style=\"padding:4px 0;color:#666;font-size:14px;\"><strong>Status-Code:</strong></td><td style=\"padding:4px 0;font-size:14px;\">$code</td></tr>";
            $footer = '<p style="margin:16px 0 0;color:#d32f2f;font-weight:bold;font-size:14px;">2 Checks fehlgeschlagen. Du bekommst eine Nachricht, sobald die Website wieder erreichbar ist.</p>';
            $btnLabel = 'Im Monitor ansehen';
        } else {
            $subject = "RECOVERY: $label ist wieder online";
            $headerBg = '#388e3c'; $title = 'Website ist wieder erreichbar';
            $statusLine = '';
            $footer = '<p style="margin:16px 0 0;color:#388e3c;font-weight:bold;font-size:14px;">Alles wieder normal.</p>';
            $btnLabel = 'Verlauf ansehen';
        }

        $body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f5f5f5;padding:20px 10px;">'
            . '<div style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">'
            . "<div style=\"background:$headerBg;padding:18px 20px;\">"
            . "<h2 style=\"margin:0;color:#fff;font-size:18px;font-weight:700;\">$title</h2></div>"
            . '<div style="padding:18px 20px;">'
            . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">'
            . "<tr><td style=\"padding:4px 0;color:#666;font-size:14px;width:110px;\"><strong>Website:</strong></td><td style=\"padding:4px 0;font-size:14px;word-break:break-word;\">$label</td></tr>"
            . "<tr><td style=\"padding:4px 0;color:#666;font-size:14px;\"><strong>URL:</strong></td><td style=\"padding:4px 0;font-size:14px;word-break:break-all;\"><a href=\"$url\" style=\"color:#1976d2;\" target=\"_blank\">$url</a></td></tr>"
            . $statusLine
            . "<tr><td style=\"padding:4px 0;color:#666;font-size:14px;\"><strong>Zeitpunkt:</strong></td><td style=\"padding:4px 0;font-size:14px;\">$now Uhr</td></tr>"
            . '</table>'
            . $footer
            . '<div style="margin-top:18px;text-align:center;">'
            . "<a href=\"$detailUrl\" target=\"_blank\" style=\"display:inline-block;background:$headerBg;color:#fff;text-decoration:none;padding:10px 22px;border-radius:6px;font-weight:600;font-size:14px;\">$btnLabel →</a>"
            . '</div>'
            . '</div></div>'
            . '<div style="text-align:center;padding-top:10px;color:#aaa;font-size:11px;">Thoxan Website-Monitor</div>'
            . '</div>';

        $this->sendMail($to, $subject, $body);
        $this->logEmail($type, $to, $subject, $monitor['label'] ?? '');
    }

    private function defaultAlertEmail(): ?string
    {
        return \Core\Settings::get('site_monitor_default_alert_email') ?: \Core\Settings::get('app_admin_email');
    }

    private function appUrl(): string
    {
        return \Core\Brand::url();
    }

    private function monitorUrlByPmId(?int $pmId): string
    {
        $base = $this->appUrl() . '/admin/site-monitor';
        return $pmId ? ($base . '?monitor=' . $pmId) : $base;
    }

    /** Findet die KI-Tool-Monitor-ID per URL für die Mail-Links. */
    private function findMonitorIdByUrl(string $url): ?int
    {
        $id = $this->db->queryValue("SELECT id FROM pm_monitors WHERE url = ?", [$url]);
        return $id ? (int) $id : null;
    }

    private function sendMail(string $to, string $subject, string $htmlBody): bool
    {
        // EmailService::fromSettings() liest SMTP-Config + entschlüsselt Secrets automatisch
        // WICHTIG: in CLI/Cron-Pfaden kein Autoloader aktiv → manuell laden
        if (!class_exists('\\Services\\EmailService', false) && defined('SERVICES_PATH')) {
            $file = SERVICES_PATH . '/EmailService.php';
            if (file_exists($file)) require_once $file;
        }
        if (!class_exists('\\Services\\EmailService', false)) {
            @file_put_contents(self::MAIL_LOG_FILE,
                '[' . date('Y-m-d H:i:s') . "] SEND-SKIP → $to | EmailService-Klasse nicht ladbar\n", FILE_APPEND);
            return false;
        }
        try {
            $svc = \Services\EmailService::fromSettings($this->db);
            if (!$svc->isConfigured()) {
                @file_put_contents(self::MAIL_LOG_FILE,
                    '[' . date('Y-m-d H:i:s') . "] SEND-SKIP → $to | EmailService nicht konfiguriert\n", FILE_APPEND);
                return false;
            }
            $ok = $svc->send($to, $subject, $htmlBody);
            if (!$ok) {
                @file_put_contents(self::MAIL_LOG_FILE,
                    '[' . date('Y-m-d H:i:s') . "] SEND-FALSE → $to | $subject | send() returned false\n", FILE_APPEND);
            }
            return $ok;
        } catch (\Throwable $e) {
            @file_put_contents(self::MAIL_LOG_FILE,
                '[' . date('Y-m-d H:i:s') . "] SEND-EXC → $to | $subject | " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    /**
     * Sendet einen Test-Alert (DOWN oder RECOVERY) an die angegebene Mail.
     * Nutzt einen existierenden Monitor aus der DB (erster aktiver) damit der Detail-Link funktioniert.
     */
    public function testAlertMail(string $to, string $type): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $monitor = $this->db->queryOne(
            "SELECT id, label, url, last_status_code FROM pm_monitors WHERE status != 'paused' ORDER BY id ASC LIMIT 1"
        );
        if (!$monitor) {
            // Fallback Demo wenn keine Monitore vorhanden
            $monitor = ['id' => null, 'label' => 'BKK Gildemeister Seidensticker', 'url' => 'https://www.bkkgs.de'];
        }
        $monitor['alert_email'] = $to;
        $monitor['last_status_code'] = $type === 'down' ? 503 : 200;
        $this->sendAlertMail($monitor, $type);
        return true;
    }

    private function logEmail(string $type, string $to, string $subject, string $label): void
    {
        $logFile = self::MAIL_LOG_FILE;
        $line = sprintf("[%s] %s → %s | %s | %s\n", date('Y-m-d H:i:s'), strtoupper($type), $to, $label, $subject);
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    // ===== Stats / Logs / Reports =====

    public function getStats(int $monitorId, int $days = 30): array
    {
        $fromSql = date('Y-m-d 00:00:00', strtotime("-$days days midnight"));
        $monitor = $this->getById($monitorId);
        if (!$monitor) return [];
        $urls = array_merge([$monitor['url']], $monitor['sub_urls_arr']);
        $urlStats = [];
        $totalAll = 0; $upAll = 0; $respSum = 0; $respCount = 0;
        foreach ($urls as $u) {
            $total = (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND checked_at >= ?",
                [$monitorId, $u, $fromSql]
            );
            $up = (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND is_up = 1 AND checked_at >= ?",
                [$monitorId, $u, $fromSql]
            );
            $avg = (float) $this->db->queryValue(
                "SELECT AVG(response_time_ms) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND is_up = 1 AND checked_at >= ?",
                [$monitorId, $u, $fromSql]
            );
            $urlStats[] = [
                'url' => $u,
                'checks' => $total,
                'uptime' => $total > 0 ? round(($up / $total) * 100, 2) : 100,
                'avg_ms' => round($avg, 2),
            ];
            $totalAll += $total; $upAll += $up;
            if ($avg > 0) { $respSum += $avg * $total; $respCount += $total; }
        }
        $incidents = $this->db->query(
            "SELECT id, started_at, ended_at, duration_minutes
             FROM pm_monitor_incidents
             WHERE monitor_id = ? AND started_at >= ?
             ORDER BY started_at DESC", [$monitorId, $fromSql]
        ) ?: [];
        $downtimeMin = (int) $this->db->queryValue(
            "SELECT COALESCE(SUM(duration_minutes), 0) FROM pm_monitor_incidents WHERE monitor_id = ? AND started_at >= ?",
            [$monitorId, $fromSql]
        );
        // Taeglicher Response-Mittelwert (ueber alle URLs des Monitors, nur Up-Checks)
        $dailyResponse = $this->db->query(
            "SELECT DATE(checked_at) AS d, ROUND(AVG(response_time_ms), 1) AS avg_ms, COUNT(*) AS cnt
             FROM pm_monitor_log
             WHERE monitor_id = ? AND is_up = 1 AND checked_at >= ?
             GROUP BY DATE(checked_at) ORDER BY d ASC",
            [$monitorId, $fromSql]
        ) ?: [];
        return [
            'days' => $days,
            'urls' => $urlStats,
            'summary' => [
                'checks' => $totalAll,
                'uptime' => $totalAll > 0 ? round(($upAll / $totalAll) * 100, 2) : 100,
                'avg_ms' => $respCount > 0 ? round($respSum / $respCount, 2) : 0,
                'outages' => count($incidents),
                'downtime_min' => $downtimeMin,
            ],
            'incidents' => $incidents,
            'daily_response' => array_map(fn($r) => [
                'd' => $r['d'], 'avg_ms' => (float) $r['avg_ms'], 'cnt' => (int) $r['cnt'],
            ], $dailyResponse),
        ];
    }

    /**
     * Aggregierte Stats für alle Monitore eines Kunden (Summen/Mittelwerte über N Tage).
     * Liefert dieselbe Struktur wie getStats() — plus 'monitors' (pro Monitor: id, label,
     * status, checks, uptime, avg_ms, outages, downtime_min).
     */
    public function getStatsForCustomer(int $customerId, int $days = 30): array
    {
        $fromSql = date('Y-m-d 00:00:00', strtotime("-$days days midnight"));
        $monitors = $this->db->query(
            "SELECT id, label, url, sub_urls, status FROM pm_monitors WHERE customer_id = ? ORDER BY label ASC",
            [$customerId]
        ) ?: [];
        if (empty($monitors)) {
            return [
                'days' => $days,
                'monitors' => [],
                'urls' => [],
                'summary' => ['checks' => 0, 'uptime' => 100, 'avg_ms' => 0, 'outages' => 0, 'downtime_min' => 0, 'monitor_count' => 0],
                'incidents' => [],
                'daily_response' => [],
            ];
        }

        $allMonitorRows = [];
        $allUrlRows = [];
        $allIncidents = [];
        $totalAll = 0; $upAll = 0; $respSum = 0; $respCount = 0; $downtimeAll = 0; $outagesAll = 0;

        foreach ($monitors as $m) {
            $mid = (int) $m['id'];
            $sub = !empty($m['sub_urls']) ? (json_decode($m['sub_urls'], true) ?: []) : [];
            $urls = array_merge([$m['url']], is_array($sub) ? $sub : []);
            $mTotal = 0; $mUp = 0; $mAvgWeightedSum = 0; $mAvgWeightedCnt = 0;
            foreach ($urls as $u) {
                $total = (int) $this->db->queryValue(
                    "SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND checked_at >= ?",
                    [$mid, $u, $fromSql]
                );
                $up = (int) $this->db->queryValue(
                    "SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND is_up = 1 AND checked_at >= ?",
                    [$mid, $u, $fromSql]
                );
                $avg = (float) $this->db->queryValue(
                    "SELECT AVG(response_time_ms) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND is_up = 1 AND checked_at >= ?",
                    [$mid, $u, $fromSql]
                );
                $allUrlRows[] = [
                    'monitor_id' => $mid,
                    'monitor_label' => $m['label'],
                    'url' => $u,
                    'checks' => $total,
                    'uptime' => $total > 0 ? round(($up / $total) * 100, 2) : 100,
                    'avg_ms' => round($avg, 2),
                ];
                $mTotal += $total; $mUp += $up;
                if ($avg > 0) { $mAvgWeightedSum += $avg * $total; $mAvgWeightedCnt += $total; }
            }
            $mIncidents = $this->db->query(
                "SELECT id, started_at, ended_at, duration_minutes
                 FROM pm_monitor_incidents
                 WHERE monitor_id = ? AND started_at >= ?
                 ORDER BY started_at DESC", [$mid, $fromSql]
            ) ?: [];
            $mDowntime = (int) $this->db->queryValue(
                "SELECT COALESCE(SUM(duration_minutes), 0) FROM pm_monitor_incidents WHERE monitor_id = ? AND started_at >= ?",
                [$mid, $fromSql]
            );
            $mAvg = $mAvgWeightedCnt > 0 ? round($mAvgWeightedSum / $mAvgWeightedCnt, 2) : 0;
            $mUptime = $mTotal > 0 ? round(($mUp / $mTotal) * 100, 2) : 100;

            // Tagesgranularitaet fuer Heatmap: pro Tag im Zeitraum 'up'/'down'/'nodata'
            $dailyRaw = $this->db->query(
                "SELECT DATE(checked_at) AS d, MIN(is_up) AS min_up, COUNT(*) AS cnt
                 FROM pm_monitor_log
                 WHERE monitor_id = ? AND checked_url = ? AND checked_at >= ?
                 GROUP BY DATE(checked_at) ORDER BY d ASC",
                [$mid, $m['url'], $fromSql]
            ) ?: [];
            $dailyMap = [];
            foreach ($dailyRaw as $dr) {
                $dailyMap[$dr['d']] = (int) $dr['min_up'] === 1 ? 'up' : 'down';
            }
            $daily = [];
            $cursor = strtotime("-".($days-1)." days midnight");
            $today = strtotime("today");
            while ($cursor <= $today) {
                $dKey = date('Y-m-d', $cursor);
                $daily[] = ['date' => $dKey, 'status' => $dailyMap[$dKey] ?? 'nodata'];
                $cursor += 86400;
            }

            $allMonitorRows[] = [
                'id' => $mid,
                'label' => $m['label'],
                'status' => $m['status'],
                'checks' => $mTotal,
                'uptime' => $mUptime,
                'avg_ms' => $mAvg,
                'outages' => count($mIncidents),
                'downtime_min' => $mDowntime,
                'daily' => $daily,
            ];
            foreach ($mIncidents as $inc) {
                $inc['monitor_id'] = $mid;
                $inc['monitor_label'] = $m['label'];
                $allIncidents[] = $inc;
            }

            $totalAll += $mTotal; $upAll += $mUp;
            $respSum += $mAvgWeightedSum; $respCount += $mAvgWeightedCnt;
            $downtimeAll += $mDowntime; $outagesAll += count($mIncidents);
        }

        // Incidents sortiert nach Startzeit DESC (über alle Monitors)
        usort($allIncidents, fn($a, $b) => strcmp($b['started_at'], $a['started_at']));

        // Taeglicher Response-Mittelwert ueber ALLE Monitore des Kunden (nur Up-Checks)
        $monitorIds = array_map(fn($m) => (int) $m['id'], $monitors);
        $ph = implode(',', array_fill(0, count($monitorIds), '?'));
        $drRows = $this->db->query(
            "SELECT DATE(checked_at) AS d, ROUND(AVG(response_time_ms), 1) AS avg_ms, COUNT(*) AS cnt
             FROM pm_monitor_log
             WHERE monitor_id IN ($ph) AND is_up = 1 AND checked_at >= ?
             GROUP BY DATE(checked_at) ORDER BY d ASC",
            array_merge($monitorIds, [$fromSql])
        ) ?: [];

        return [
            'days' => $days,
            'monitors' => $allMonitorRows,
            'urls' => $allUrlRows,
            'summary' => [
                'checks' => $totalAll,
                'uptime' => $totalAll > 0 ? round(($upAll / $totalAll) * 100, 2) : 100,
                'avg_ms' => $respCount > 0 ? round($respSum / $respCount, 2) : 0,
                'outages' => $outagesAll,
                'downtime_min' => $downtimeAll,
                'monitor_count' => count($monitors),
            ],
            'incidents' => $allIncidents,
            'daily_response' => array_map(fn($r) => [
                'd' => $r['d'], 'avg_ms' => (float) $r['avg_ms'], 'cnt' => (int) $r['cnt'],
            ], $drRows),
        ];
    }

    public function getRecentLogs(int $monitorId, int $limit = 100): array
    {
        return $this->db->query(
            "SELECT * FROM pm_monitor_log WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT $limit",
            [$monitorId]
        ) ?: [];
    }

    public function cleanupOldLogs(int $days = 90): int
    {
        return $this->db->execute(
            "DELETE FROM pm_monitor_log WHERE checked_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }

    // ===== JSON-Import aus Tallyr =====

    /**
     * Erwartet eines dieser Formate:
     *
     *  Format A (Tallyr-Style — kommt aus inc/export-monitor.php):
     *    {
     *      "export_version": "1.0",
     *      "monitors": [
     *        { "id": 123, "client_id": 12, "url": "...", "label": "...",
     *          "category": "...", "alert_email": "...", "report_schedule": "both",
     *          "sub_urls": ["..."], "status": "up" },
     *        ...
     *      ],
     *      "logs": [...]?,         // optional, max 90 Tage
     *      "incidents": [...]?     // optional
     *    }
     *
     *  Format B (rohes wp_options-Dump, einfach { monitors: [...] }) wird auch akzeptiert.
     *
     * Kunden-Mapping:
     *  - `client_id` aus Tallyr → über `customer_mapping[tallyrClientId] = kiCustomerId | "skip" | "none"` zuordnen
     *  - Default ohne Mapping: per shortdesc/name versuchen, sonst customer_id=NULL
     *  - "skip" überspringt den Monitor komplett
     *
     * Preview-Mode (dry-run) liefert Counts ohne DB-Writes.
     */
    public function importPreview(array $data): array
    {
        $monitors = $this->extractMonitors($data);
        $existingUrls = array_flip(array_column($this->db->query("SELECT url FROM pm_monitors") ?: [], 'url'));

        // KI-Tool-Kunden für Auto-Match (per Name, abbreviation, slug, website-Domain)
        $kiCustomers = $this->db->query(
            "SELECT id, name, abbreviation, slug, website FROM customers WHERE is_active = 1"
        ) ?: [];
        $byAbbr = []; $byName = []; $byDomain = [];
        foreach ($kiCustomers as $c) {
            if (!empty($c['abbreviation'])) $byAbbr[strtolower($c['abbreviation'])] = $c;
            if (!empty($c['name'])) $byName[strtolower($c['name'])] = $c;
            if (!empty($c['website'])) {
                foreach (explode("\n", $c['website']) as $u) {
                    $u = trim($u); if (!$u) continue;
                    if (!preg_match('#^https?://#i', $u)) $u = 'http://' . $u;
                    $host = parse_url($u, PHP_URL_HOST);
                    if ($host) $byDomain[strtolower(preg_replace('/^www\./', '', $host))] = $c;
                }
            }
        }

        // Index der Tallyr-Clients
        $tallyrClients = [];
        foreach (($data['clients'] ?? []) as $c) {
            if (!empty($c['id'])) $tallyrClients[(int) $c['id']] = $c;
        }

        $newCount = 0; $dupCount = 0;
        $monitorRows = [];        // Liste aller neuen Monitore mit URL+Label+Auto-Match

        foreach ($monitors as $m) {
            $url = (string) ($m['url'] ?? '');
            if (!$url) continue;
            if (isset($existingUrls[$url])) { $dupCount++; continue; }
            $newCount++;

            $cid = (int) ($m['client_id'] ?? 0);
            $tallyrMonitorId = (string) ($m['id'] ?? $url);
            $label = (string) ($m['label'] ?? '');
            $tallyrClientName = ($cid > 0 && isset($tallyrClients[$cid])) ? $tallyrClients[$cid]['title'] : null;

            // Auto-Match-Hierarchie:
            // 1) Über Tallyr-Kunden-Name (wenn client_id != 0)
            // 2) Über Label-Abkürzung
            // 3) Über URL-Domain
            $matched = null; $via = null;
            if ($tallyrClientName && isset($byName[strtolower($tallyrClientName)])) {
                $matched = $byName[strtolower($tallyrClientName)];
                $via = 'tallyr-client';
            }
            if (!$matched && !empty($label) && isset($byAbbr[strtolower(trim($label))])) {
                $matched = $byAbbr[strtolower(trim($label))];
                $via = 'label';
            }
            if (!$matched) {
                $host = parse_url($url, PHP_URL_HOST);
                if ($host) {
                    $hostKey = strtolower(preg_replace('/^www\./', '', $host));
                    if (isset($byDomain[$hostKey])) {
                        $matched = $byDomain[$hostKey];
                        $via = 'domain';
                    }
                }
            }

            $monitorRows[] = [
                'tallyr_monitor_id' => $tallyrMonitorId,
                'url' => $url,
                'label' => $label,
                'category' => (string) ($m['category'] ?? ''),
                'tallyr_client_name' => $tallyrClientName,
                'matched_customer_id' => $matched ? (int) $matched['id'] : null,
                'matched_customer_name' => $matched ? $matched['name'] : null,
                'matched_via' => $via,
            ];
        }
        return [
            'total' => count($monitors),
            'new' => $newCount,
            'duplicates' => $dupCount,
            'monitors' => $monitorRows,
        ];
    }

    /**
     * Führt den Import durch.
     * @param array $data Tallyr-Export
     * @param array $customerMapping  [tallyrClientId => ki_customer_id | 'skip' | 'none']
     * @param int $userId
     */
    /**
     * @param array $data Tallyr-JSON
     * @param array $customerMapping  [tallyrClientId => ki_customer_id | 'skip' | 'none']
     * @param int $userId
     * @param array $monitorMapping  [tallyrMonitorId => ki_customer_id | 'skip' | 'none']
     *   Überschreibt das Kunden-Mapping für einzelne Monitore (für `client_id=0`-Fälle)
     */
    public function importFromJson(array $data, array $customerMapping, int $userId, array $monitorMapping = []): array
    {
        $monitors = $this->extractMonitors($data);
        $existingUrls = array_flip(array_column($this->db->query("SELECT url FROM pm_monitors") ?: [], 'url'));
        $imported = 0; $skipped = 0; $duplicates = 0;
        $log = [];

        $this->db->beginTransaction();
        try {
            foreach ($monitors as $m) {
                $url = trim((string) ($m['url'] ?? ''));
                if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) { $skipped++; continue; }
                if (isset($existingUrls[$url])) {
                    $duplicates++;
                    $log[] = "DUPLICATE: $url (übersprungen)";
                    continue;
                }
                $tallyrCid = (int) ($m['client_id'] ?? 0);
                $tallyrMid = (string) ($m['id'] ?? $url);

                // Monitor-Mapping hat Vorrang vor Customer-Mapping
                $mapAction = null;
                if (array_key_exists($tallyrMid, $monitorMapping)) {
                    $mapAction = $monitorMapping[$tallyrMid];
                } elseif ($tallyrCid > 0) {
                    $mapAction = $customerMapping[(string) $tallyrCid] ?? 'none';
                } else {
                    $mapAction = 'none';
                }
                if ($mapAction === 'skip') {
                    $skipped++;
                    $log[] = "SKIP: $url";
                    continue;
                }
                $customerId = null;
                if (is_numeric($mapAction) && (int) $mapAction > 0) {
                    $customerId = (int) $mapAction;
                }
                $subUrls = $m['sub_urls'] ?? null;
                if (is_string($subUrls)) {
                    $subUrls = json_decode($subUrls, true);
                }
                if (is_array($subUrls)) {
                    $subUrls = array_values(array_filter($subUrls, fn($u) => filter_var(trim((string) $u), FILTER_VALIDATE_URL)));
                    $subUrls = $subUrls ? json_encode($subUrls) : null;
                } else {
                    $subUrls = null;
                }
                $report = in_array($m['report_schedule'] ?? '', ['none', 'weekly', 'monthly', 'both'], true)
                    ? $m['report_schedule'] : 'both';
                $alertEmail = !empty($m['alert_email']) && filter_var($m['alert_email'], FILTER_VALIDATE_EMAIL)
                    ? $m['alert_email'] : null;
                $this->db->insert('pm_monitors', [
                    'url' => $url,
                    'label' => (string) ($m['label'] ?? $url),
                    'customer_id' => $customerId,
                    'alert_email' => $alertEmail,
                    'report_schedule' => $report,
                    'sub_urls' => $subUrls,
                    'category' => (string) ($m['category'] ?? ''),
                    'status' => 'up',
                    'created_by' => $userId,
                ]);
                $imported++;
                $existingUrls[$url] = true;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'log' => $log,
        ];
    }

    /**
     * Importiert Logs + Incidents aus einer Tallyr-JSON mit `?history=1`.
     * Matching erfolgt über die `monitor_url` (eindeutige Spalte aus dem Tallyr-Export-JOIN):
     *   - jeder Log/Incident referenziert via `monitor_url` den Tallyr-Monitor-URL
     *   - wir suchen dazu unseren `pm_monitors.id` per URL-Match
     *   - Logs für unbekannte URLs werden geskippt
     *
     * `checked_url` wird 1:1 übernommen (kann main URL oder sub URL sein).
     * Duplikate vermeiden: pro Monitor + checked_url + checked_at wird nur INSERT IGNORE genutzt
     *   (keine UNIQUE-Constraint, aber wir machen DELETE+INSERT pro Zeitfenster).
     *
     * Strategie:
     *   - Wenn `history.replace=true`: alle existierenden Logs/Incidents für die betroffenen Monitore
     *     im Import-Zeitfenster (min/max checked_at) löschen, dann frisch einfügen
     *   - Sonst: einfach insert, Doppelte werden inkauf genommen (sollte selten sein)
     */
    public function importHistory(array $data, bool $replace = true): array
    {
        $logs = is_array($data['logs'] ?? null) ? $data['logs'] : [];
        $incidents = is_array($data['incidents'] ?? null) ? $data['incidents'] : [];
        if (empty($logs) && empty($incidents)) return ['logs_imported' => 0, 'incidents_imported' => 0, 'skipped_urls' => []];

        // URL → pm_monitor_id Lookup
        $urlMap = [];
        foreach ($this->db->query("SELECT id, url FROM pm_monitors") ?: [] as $r) {
            $urlMap[$r['url']] = (int) $r['id'];
        }

        // Pro Monitor-URL die min/max checked_at sammeln (für Replace-Mode)
        $monitorTimeRange = []; // [pm_id => [min, max]]
        $skippedUrls = [];

        $logsByMonitor = []; // pm_id => array of log-rows
        foreach ($logs as $l) {
            $monUrl = $l['monitor_url'] ?? null;
            if (!$monUrl) continue;
            $pmId = $urlMap[$monUrl] ?? null;
            if (!$pmId) { $skippedUrls[$monUrl] = true; continue; }
            $checkedAt = $l['checked_at'] ?? null;
            if (!$checkedAt) continue;
            $logsByMonitor[$pmId][] = $l;
            $ts = strtotime($checkedAt);
            if (!isset($monitorTimeRange[$pmId])) $monitorTimeRange[$pmId] = [$ts, $ts];
            if ($ts < $monitorTimeRange[$pmId][0]) $monitorTimeRange[$pmId][0] = $ts;
            if ($ts > $monitorTimeRange[$pmId][1]) $monitorTimeRange[$pmId][1] = $ts;
        }

        $incidentsByMonitor = [];
        foreach ($incidents as $i) {
            $monUrl = $i['monitor_url'] ?? null;
            $pmId = $monUrl ? ($urlMap[$monUrl] ?? null) : null;
            if (!$pmId) { if ($monUrl) $skippedUrls[$monUrl] = true; continue; }
            $incidentsByMonitor[$pmId][] = $i;
        }

        $this->db->beginTransaction();
        try {
            $logsImported = 0; $incidentsImported = 0;

            // Replace-Mode: existierende Logs im Zeitfenster löschen
            if ($replace && !empty($monitorTimeRange)) {
                foreach ($monitorTimeRange as $pmId => $range) {
                    $from = date('Y-m-d H:i:s', $range[0]);
                    $to = date('Y-m-d H:i:s', $range[1]);
                    $this->db->execute(
                        "DELETE FROM pm_monitor_log WHERE monitor_id = ? AND checked_at BETWEEN ? AND ?",
                        [$pmId, $from, $to]
                    );
                }
            }
            if ($replace && !empty($incidentsByMonitor)) {
                // Für die betroffenen Monitore alle Incidents löschen (komplettes Replacement)
                $pmIds = array_keys($incidentsByMonitor);
                if (!empty($pmIds)) {
                    $in = implode(',', array_map('intval', $pmIds));
                    $this->db->execute("DELETE FROM pm_monitor_incidents WHERE monitor_id IN ($in)");
                }
            }

            // Logs einfügen (Batch in Chunks für Speed)
            foreach ($logsByMonitor as $pmId => $rows) {
                $chunkSize = 500;
                $chunks = array_chunk($rows, $chunkSize);
                foreach ($chunks as $chunk) {
                    $values = []; $params = [];
                    foreach ($chunk as $l) {
                        $values[] = '(?, ?, ?, ?, ?, ?)';
                        $params[] = $pmId;
                        $params[] = $l['checked_url'] ?? null;
                        $params[] = (int) ($l['status_code'] ?? 0);
                        $params[] = (int) ($l['response_time_ms'] ?? 0);
                        $params[] = (int) ($l['is_up'] ?? 0);
                        $params[] = $l['checked_at'];
                    }
                    $sql = "INSERT INTO pm_monitor_log (monitor_id, checked_url, status_code, response_time_ms, is_up, checked_at) VALUES "
                         . implode(',', $values);
                    $this->db->execute($sql, $params);
                    $logsImported += count($chunk);
                }
            }

            // Incidents einfügen
            foreach ($incidentsByMonitor as $pmId => $rows) {
                foreach ($rows as $i) {
                    $this->db->insert('pm_monitor_incidents', [
                        'monitor_id' => $pmId,
                        'started_at' => $i['started_at'] ?? date('Y-m-d H:i:s'),
                        'ended_at' => $i['ended_at'] ?: null,
                        'duration_minutes' => (int) ($i['duration_minutes'] ?? 0),
                        'notified' => (int) ($i['notified'] ?? 0),
                    ]);
                    $incidentsImported++;
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'logs_imported' => $logsImported,
            'incidents_imported' => $incidentsImported,
            'skipped_urls' => array_keys($skippedUrls),
        ];
    }

    /**
     * Extrahiert das Monitors-Array aus diversen Format-Varianten.
     */
    private function extractMonitors(array $data): array
    {
        if (isset($data['monitors']) && is_array($data['monitors'])) {
            return $data['monitors'];
        }
        // Format aus rohem SQL-Dump: { tallyr_monitors: [...] }
        if (isset($data['tallyr_monitors']) && is_array($data['tallyr_monitors'])) {
            return $data['tallyr_monitors'];
        }
        // Notfalls: data direkt als Array
        if (isset($data[0]) && is_array($data[0]) && isset($data[0]['url'])) {
            return $data;
        }
        return [];
    }

    // ===== Reports (weekly + monthly) =====

    public function sendReports(bool $force = false): array
    {
        $dow = (int) date('N'); // 1=Mo
        $dom = (int) date('j');
        $sendWeekly  = $force || $dow === 1;
        $sendMonthly = $force || $dom === 1;
        if (!$sendWeekly && !$sendMonthly) return ['sent' => 0];

        // Getrennte Marker pro Typ → wenn am 1. ein Montag ist, kommen BEIDE Reports raus
        $todayKey  = date('Y-m-d');
        $lastWeekly  = \Core\Settings::get('site_monitor_weekly_last_sent');
        $lastMonthly = \Core\Settings::get('site_monitor_monthly_last_sent');

        // Empfänger-Liste (gemeinsam für beide Report-Typen)
        $emails = $this->db->query(
            "SELECT DISTINCT alert_email FROM pm_monitors WHERE alert_email IS NOT NULL AND alert_email != '' AND report_schedule != 'none'"
        ) ?: [];
        $recipients = array_unique(array_column($emails, 'alert_email'));
        if (empty($recipients)) {
            $def = $this->defaultAlertEmail();
            if ($def) $recipients = [$def];
        }
        if (empty($recipients)) {
            return ['sent' => 0, 'skipped' => 'no recipients (set site_monitor_default_alert_email or alert_email per monitor)'];
        }

        $totalSent = 0; $totalFailed = 0; $sentSummary = [];

        // === WEEKLY: vorherige Mo–So ===
        if ($sendWeekly && ($force || $lastWeekly !== $todayKey)) {
            $rangeFrom = date('Y-m-d', strtotime('monday last week'));
            $rangeTo   = date('Y-m-d', strtotime('sunday last week'));
            $weekSent = 0; $weekFailed = 0;
            foreach ($recipients as $to) {
                if (!$to) continue;
                $html = $this->buildReportHtml($to, 7, 'Wöchentlicher', 'weekly', false, $rangeFrom, $rangeTo);
                if (!$html) continue;
                $subject = 'Wöchentlicher Uptime-Report — ' . date('d.m.Y', strtotime($rangeFrom)) . ' – ' . date('d.m.Y', strtotime($rangeTo));
                $ok = $this->sendMail($to, $subject, $html);
                $this->logEmail($ok ? 'report' : 'report-failed', $to, $subject, 'Wöchentlicher');
                if ($ok) $weekSent++; else $weekFailed++;
            }
            if ($weekSent > 0) \Core\Settings::set('site_monitor_weekly_last_sent', $todayKey);
            $totalSent += $weekSent; $totalFailed += $weekFailed;
            $sentSummary['weekly'] = ['sent' => $weekSent, 'failed' => $weekFailed, 'range' => "$rangeFrom – $rangeTo"];
        }

        // === MONTHLY: vorheriger 1. – ultimo ===
        if ($sendMonthly && ($force || $lastMonthly !== $todayKey)) {
            $rangeFrom = date('Y-m-d', strtotime('first day of previous month'));
            $rangeTo   = date('Y-m-d', strtotime('last day of previous month'));
            $days = (int)((strtotime($rangeTo) - strtotime($rangeFrom)) / 86400) + 1;
            $monSent = 0; $monFailed = 0;
            foreach ($recipients as $to) {
                if (!$to) continue;
                $html = $this->buildReportHtml($to, $days, 'Monatlicher', 'monthly', false, $rangeFrom, $rangeTo);
                if (!$html) continue;
                $subject = 'Monatlicher Uptime-Report — ' . date('d.m.Y', strtotime($rangeFrom)) . ' – ' . date('d.m.Y', strtotime($rangeTo));
                $ok = $this->sendMail($to, $subject, $html);
                $this->logEmail($ok ? 'report' : 'report-failed', $to, $subject, 'Monatlicher');
                if ($ok) $monSent++; else $monFailed++;
            }
            if ($monSent > 0) \Core\Settings::set('site_monitor_monthly_last_sent', $todayKey);
            $totalSent += $monSent; $totalFailed += $monFailed;
            $sentSummary['monthly'] = ['sent' => $monSent, 'failed' => $monFailed, 'range' => "$rangeFrom – $rangeTo"];
        }

        return ['sent' => $totalSent, 'failed' => $totalFailed, 'details' => $sentSummary];
    }

    public function testReport(string $to, int $days = 7, string $periodLabel = 'Test'): bool
    {
        $html = $this->buildReportHtml($to, $days, $periodLabel, 'both', true);
        if (!$html) return false;
        $subject = "$periodLabel Uptime-Report — " . date('d.m.Y', strtotime("-$days days")) . ' – ' . date('d.m.Y');
        $this->sendMail($to, $subject, $html);
        $this->logEmail('report-test', $to, $subject, $periodLabel);
        return true;
    }

    private function buildReportHtml(string $recipientEmail, int $days, string $periodLabel, string $matchSchedule, bool $allMonitors = false, ?string $rangeFrom = null, ?string $rangeTo = null): string
    {
        // Wenn rangeFrom/rangeTo gesetzt → exakte Kalender-Grenzen nutzen.
        // Sonst (z.B. Test-Mail): rollende „letzte N Tage" wie früher.
        $monthsDe = [1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        if ($rangeFrom && $rangeTo) {
            $fromSql = $rangeFrom . ' 00:00:00';
            $toSql   = $rangeTo   . ' 23:59:59';
            $fromTs  = strtotime($fromSql);
            $toTs    = strtotime($rangeTo . ' midnight');
        } else {
            $fromSql = date('Y-m-d 00:00:00', strtotime("-$days days midnight"));
            $toSql   = date('Y-m-d H:i:s');
            $fromTs  = strtotime("-$days days midnight");
            $toTs    = strtotime('today midnight');
        }
        $dateFromLong = date('d. ', $fromTs) . $monthsDe[(int) date('n', $fromTs)] . date(' Y', $fromTs);
        $dateToLong   = date('d. ', $toTs)   . $monthsDe[(int) date('n', $toTs)]   . date(' Y', $toTs);

        if ($allMonitors) {
            // Test-Modus: alle Monitore, unabhängig von alert_email
            $sql = "SELECT * FROM pm_monitors WHERE status != 'paused' ORDER BY label ASC";
            $params = [];
        } else {
            $where = "(alert_email = ? OR alert_email IS NULL OR alert_email = '')";
            $params = [$recipientEmail];
            if ($matchSchedule !== 'both') {
                $where .= " AND (report_schedule = ? OR report_schedule = 'both')";
                $params[] = $matchSchedule;
            }
            $sql = "SELECT * FROM pm_monitors WHERE $where ORDER BY label ASC";
        }
        $monitors = $this->db->query($sql, $params) ?: [];
        if (empty($monitors)) return '';

        $totChecks = 0; $totUp = 0; $totOut = 0; $totDowntime = 0; $respSum = 0; $respCount = 0;
        $rows = [];
        foreach ($monitors as $m) {
            $urls = [$m['url']];
            if (!empty($m['sub_urls'])) {
                $sub = json_decode($m['sub_urls'], true);
                if (is_array($sub)) $urls = array_merge($urls, $sub);
            }
            $urlData = [];
            foreach ($urls as $u) {
                $total = (int) $this->db->queryValue(
                    "SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND checked_at >= ? AND checked_at <= ?",
                    [$m['id'], $u, $fromSql, $toSql]);
                $up = (int) $this->db->queryValue(
                    "SELECT COUNT(*) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND is_up = 1 AND checked_at >= ? AND checked_at <= ?",
                    [$m['id'], $u, $fromSql, $toSql]);
                $avg = (float) $this->db->queryValue(
                    "SELECT AVG(response_time_ms) FROM pm_monitor_log WHERE monitor_id = ? AND checked_url = ? AND is_up = 1 AND checked_at >= ? AND checked_at <= ?",
                    [$m['id'], $u, $fromSql, $toSql]);
                $urlData[] = ['url' => $u, 'checks' => $total, 'uptime' => $total > 0 ? round($up / $total * 100, 2) : 100, 'avg_ms' => round($avg, 2)];
                $totChecks += $total; $totUp += $up;
                if ($avg > 0) { $respSum += $avg * $total; $respCount += $total; }
            }
            $outages = (int) $this->db->queryValue(
                "SELECT COUNT(*) FROM pm_monitor_incidents WHERE monitor_id = ? AND started_at >= ? AND started_at <= ?",
                [$m['id'], $fromSql, $toSql]);
            $downtime = (int) $this->db->queryValue(
                "SELECT COALESCE(SUM(duration_minutes), 0) FROM pm_monitor_incidents WHERE monitor_id = ? AND started_at >= ? AND started_at <= ?",
                [$m['id'], $fromSql, $toSql]);
            $totOut += $outages; $totDowntime += $downtime;
            $rows[] = ['label' => $m['label'], 'urls' => $urlData, 'outages' => $outages, 'downtime_min' => $downtime];
        }
        $summaryUp = $totChecks > 0 ? round($totUp / $totChecks * 100, 2) : 100;
        $summaryResp = $respCount > 0 ? round($respSum / $respCount, 2) : 0;
        $fmtDowntime = function (int $min): string {
            if ($min <= 0) return '–';
            if ($min < 60) return "$min Min";
            $h = (int) floor($min / 60); $m = $min % 60;
            return "$h Std" . ($m > 0 ? " $m Min" : '');
        };
        $uColor = fn($u) => $u >= 99 ? '#388e3c' : ($u >= 95 ? '#f57c00' : '#d32f2f');

        // URL-Lookup pro Monitor-URL für Link
        $pmIdByUrl = [];
        foreach ($monitors as $m) {
            $pmIdByUrl[$m['url']] = (int) $m['id'];
        }
        $appUrl = $this->appUrl();
        $mainLink = htmlspecialchars($appUrl . '/admin/site-monitor', ENT_QUOTES, 'UTF-8');

        // Responsive E-Mail-CSS für die Summary-Tabelle (Detail nutzt Card-Layout, braucht keine Media-Query)
        $css = '
        @media only screen and (max-width:480px){
            .sm-mail .sum-cell{display:block!important;width:100%!important;text-align:left!important;padding:6px 0!important;border-bottom:1px solid #eee!important;}
            .sm-mail .sum-cell-val{display:inline-block!important;margin-left:8px!important;}
        }';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . "<style>$css</style></head>"
            . '<body style="margin:0;padding:0;background:#f5f5f5;">'
            . '<div class="sm-mail" style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;background:#f5f5f5;padding:20px 10px;">';
        $html .= '<div style="background:#fff;padding:20px;border-radius:8px 8px 0 0;border:1px solid #eee;border-bottom:none;">';
        $html .= "<h2 style=\"margin:0;color:#333;font-size:18px;\">$periodLabel Uptime-Report</h2>";
        $html .= "<p style=\"margin:6px 0 0;color:#999;font-size:13px;\">$dateFromLong 00:00 Uhr – $dateToLong 00:00 Uhr</p>";
        $html .= '</div>';

        // Summary
        $html .= '<div style="background:#fff;padding:16px 20px;border-left:1px solid #eee;border-right:1px solid #eee;">';
        $html .= '<h3 style="margin:0 0 12px;font-size:15px;color:#333;">Zusammenfassung</h3>';
        $html .= '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;table-layout:fixed;">';
        $sumHead = function ($label) { return "<td class=\"sum-cell\" style=\"text-align:center;font-size:11px;color:#888;font-weight:600;text-transform:uppercase;padding:8px 4px;border-bottom:2px solid #eee;\">$label</td>"; };
        $sumVal = function ($val, $color = null) {
            $c = $color ? "color:$color;" : '';
            return "<td class=\"sum-cell\" style=\"text-align:center;padding:10px 4px;font-weight:700;font-size:14px;$c\"><span class=\"sum-cell-val\">$val</span></td>";
        };
        $html .= '<tr>' . $sumHead('Checks') . $sumHead('Uptime') . $sumHead('Ausfälle') . $sumHead('Ausfallzeit') . $sumHead('Response') . '</tr>';
        $html .= '<tr>'
            . $sumVal(number_format($totChecks, 0, ',', '.'))
            . $sumVal($summaryUp . '%', $uColor($summaryUp))
            . $sumVal($totOut)
            . $sumVal($fmtDowntime($totDowntime))
            . $sumVal($summaryResp . ' ms')
            . '</tr>';
        $html .= '</table>';
        $html .= '</div>';

        // Detail: Card-Layout pro Website (kein Tabellen-Grid, robust auf jeder Bildschirmgröße)
        $html .= '<div style="background:#fff;padding:16px 20px;border-left:1px solid #eee;border-right:1px solid #eee;border-radius:0 0 8px 8px;border-bottom:1px solid #eee;">';
        $html .= '<h3 style="margin:0 0 12px;font-size:15px;color:#333;">Details pro Website</h3>';

        foreach ($rows as $r) {
            $main = $r['urls'][0] ?? ['uptime' => 100, 'avg_ms' => 0, 'url' => ''];
            $pmId = $pmIdByUrl[$main['url']] ?? null;
            $detailUrl = htmlspecialchars($this->monitorUrlByPmId($pmId), ENT_QUOTES, 'UTF-8');
            $labelEsc = htmlspecialchars($r['label']);
            $urlEsc = htmlspecialchars($main['url']);
            $upClr = $uColor($main['uptime']);

            $html .= '<div style="border-top:1px solid #eee;padding:12px 0;">'
                // Website-Header: Label + Pfeil als KI-Tool-Link (Block) → externer URL-Link drunter
                . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">'
                . '<tr><td style="vertical-align:top;">'
                . "<a href=\"$detailUrl\" target=\"_blank\" style=\"color:#1976d2;text-decoration:none;font-weight:700;font-size:15px;display:inline-block;\">$labelEsc <span style=\"font-size:13px;\">→</span></a><br>"
                . "<a href=\"$urlEsc\" target=\"_blank\" style=\"color:#999;font-size:12px;word-break:break-all;text-decoration:none;\">$urlEsc</a>"
                . '</td></tr></table>'
                // Stats-Line kompakt
                . '<div style="margin-top:6px;font-size:13px;color:#333;line-height:1.7;">'
                . "<span style=\"color:$upClr;font-weight:700;\">Uptime {$main['uptime']}%</span>"
                . "<span style=\"color:#ddd;margin:0 6px;\">·</span>"
                . "<span>Ausfälle <strong>{$r['outages']}</strong></span>"
                . "<span style=\"color:#ddd;margin:0 6px;\">·</span>"
                . "<span>Zeit <strong>{$fmtDowntime($r['downtime_min'])}</strong></span>"
                . "<span style=\"color:#ddd;margin:0 6px;\">·</span>"
                . "<span>Ø <strong>{$main['avg_ms']} ms</strong></span>"
                . '</div>';

            // Sub-URLs (eingerückt, leichter Hintergrund)
            for ($i = 1; $i < count($r['urls']); $i++) {
                $s = $r['urls'][$i];
                $subUrlEsc = htmlspecialchars($s['url']);
                $subClr = $uColor($s['uptime']);
                $html .= '<div style="margin-top:6px;padding:6px 10px;background:#fafafa;border-radius:4px;font-size:12px;color:#666;">'
                    . "↳ <a href=\"$subUrlEsc\" target=\"_blank\" style=\"color:#999;text-decoration:none;word-break:break-all;\">$subUrlEsc</a>"
                    . '<div style="margin-top:3px;">'
                    . "<span style=\"color:$subClr;font-weight:600;\">Uptime {$s['uptime']}%</span>"
                    . "<span style=\"color:#ddd;margin:0 6px;\">·</span>"
                    . "<span>Ø {$s['avg_ms']} ms</span>"
                    . '</div></div>';
            }
            $html .= '</div>';
        }

        // Hauptseiten-Link
        $html .= "<div style=\"margin-top:18px;text-align:center;\"><a href=\"$mainLink\" target=\"_blank\" style=\"display:inline-block;background:#1976d2;color:#fff;text-decoration:none;padding:10px 22px;border-radius:6px;font-weight:600;font-size:14px;\">Alle Websites im Monitor ansehen →</a></div>";

        $html .= '</div>';
        $html .= '<div style="padding:15px 10px;text-align:center;color:#999;font-size:11px;">Thoxan Website-Monitor – Automatischer ' . htmlspecialchars($periodLabel) . ' Report</div>';
        $html .= '</div></body></html>';
        return $html;
    }
}
