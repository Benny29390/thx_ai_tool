<?php
namespace Services;

use Core\Database;

/**
 * FirewallService — fail2ban-Status lesen und IPs entsperren.
 *
 * WICHTIG zur Architektur:
 * In der Web-Umgebung (Apache mod_php) ist die Befehlsausfuehrung abgeschaltet
 * (disable_functions: shell_exec, system, exec-Familie ...). Die Web-App darf
 * daher KEINE System-Befehle ausfuehren. Deshalb gilt:
 *
 *   - Web-Pfad (Anzeige):   liest den Snapshot (storage/firewall-state.json),
 *                           den der Cron-Worker regelmaessig schreibt.
 *   - Web-Pfad (Entsperren): legt einen Auftrag in firewall_unban_queue ab.
 *   - Cron-Worker (CLI):    fuehrt die fail2ban-Befehle aus (dort ist shell_exec
 *                           erlaubt) ueber die sudo-Bruecke /usr/local/bin/ki-fail2ban,
 *                           schreibt den Snapshot und arbeitet die Queue ab.
 *
 * Die *Methoden mit Shell-Zugriff* (run/overview/jailStatus/unban/ufwStatus/
 * writeSnapshot/processQueue) duerfen NUR aus dem CLI-Worker aufgerufen werden.
 * Die *Web-Methoden* (readSnapshot/enqueueUnban/pendingQueue) sind shell-frei.
 */
class FirewallService
{
    private const WRAPPER = '/usr/local/bin/ki-fail2ban';

    private function snapshotPath(): string
    {
        return __DIR__ . '/../storage/firewall-state.json';
    }

    private function db(): Database
    {
        return Database::getInstance();
    }

    // ========================================================================
    // WEB-PFAD — keinerlei Shell-Aufrufe
    // ========================================================================

    /**
     * Liest den vom Cron-Worker geschriebenen Snapshot.
     * @return array|null  null wenn noch kein Snapshot existiert
     */
    public function readSnapshot(): ?array
    {
        $path = $this->snapshotPath();
        if (!is_readable($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Legt einen Entsperr-Auftrag in die Warteschlange (idempotent: laesst
     * bereits offene Auftraege fuer dieselbe IP unangetastet).
     * @return array{ok:bool, message:string}
     */
    public function enqueueUnban(string $ip, ?int $userId): array
    {
        $ip = trim($ip);
        if (!$this->isValidIp($ip)) {
            return ['ok' => false, 'message' => 'Ungueltige IP-Adresse.'];
        }
        $existing = $this->db()->queryValue(
            "SELECT COUNT(*) FROM firewall_unban_queue WHERE ip = ? AND status = 'pending'",
            [$ip]
        );
        if ((int) $existing > 0) {
            return ['ok' => true, 'message' => 'Entsperr-Auftrag laeuft bereits — wird in Kuerze ausgefuehrt.'];
        }
        $this->db()->execute(
            "INSERT INTO firewall_unban_queue (ip, status, requested_by) VALUES (?, 'pending', ?)",
            [$ip, $userId]
        );
        return ['ok' => true, 'message' => 'Entsperr-Auftrag angelegt — die IP wird innerhalb einer Minute freigegeben.'];
    }

    /**
     * Offene + kuerzlich erledigte Auftraege (fuer die Anzeige).
     */
    public function recentQueue(int $limit = 50): array
    {
        return $this->db()->query(
            "SELECT id, ip, status, requested_at, processed_at, result
             FROM firewall_unban_queue
             ORDER BY id DESC LIMIT " . (int) $limit
        ) ?: [];
    }

    /** IPs mit offenem Entsperr-Auftrag (fuer Pending-Markierung in der Tabelle). */
    public function pendingIps(): array
    {
        $rows = $this->db()->query("SELECT DISTINCT ip FROM firewall_unban_queue WHERE status = 'pending'") ?: [];
        return array_column($rows, 'ip');
    }

    // ========================================================================
    // CLI-WORKER-PFAD — fuehrt Shell-Befehle aus (nur aus dem Cron-Worker!)
    // ========================================================================

    /**
     * @return array{ok:bool, out:string, err:string}
     */
    private function run(array $args): array
    {
        if (!function_exists('shell_exec')) {
            return ['ok' => false, 'out' => '', 'err' => 'shell_exec ist in dieser Umgebung deaktiviert (nur im CLI-Worker erlaubt).'];
        }
        $cmd = 'sudo -n ' . escapeshellarg(self::WRAPPER);
        foreach ($args as $a) { $cmd .= ' ' . escapeshellarg($a); }
        $cmd .= ' 2>/tmp/ki-fw-err';
        $out = shell_exec($cmd);
        $err = @file_get_contents('/tmp/ki-fw-err') ?: '';
        @unlink('/tmp/ki-fw-err');
        return ['ok' => $out !== null, 'out' => (string) $out, 'err' => trim($err)];
    }

    public function isAvailable(): bool
    {
        $r = $this->run(['status']);
        return $r['ok'] && strpos($r['out'], 'Jail list:') !== false;
    }

    public function listJails(): array
    {
        $r = $this->run(['status']);
        if (!$r['ok'] || !preg_match('/Jail list:\s*(.+)/', $r['out'], $m)) return [];
        return array_values(array_filter(array_map('trim', explode(',', $m[1])), fn($n) => $n !== ''));
    }

    public function jailStatus(string $jail): array
    {
        $r = $this->run(['jail', $jail]);
        $out = $r['ok'] ? $r['out'] : '';
        $intAfter = function (string $label) use ($out): int {
            return preg_match('/' . preg_quote($label, '/') . ':\s*(\d+)/', $out, $m) ? (int) $m[1] : 0;
        };
        $banned = [];
        if (preg_match('/Banned IP list:\s*(.*)/', $out, $m)) {
            $banned = array_values(array_filter(preg_split('/\s+/', trim($m[1]))));
        }
        return [
            'name'             => $jail,
            'currently_failed' => $intAfter('Currently failed'),
            'total_failed'     => $intAfter('Total failed'),
            'currently_banned' => $intAfter('Currently banned'),
            'total_banned'     => $intAfter('Total banned'),
            'banned'           => $banned,
        ];
    }

    public function overview(): array
    {
        $jails = [];
        foreach ($this->listJails() as $name) {
            $jails[] = $this->jailStatus($name);
        }
        return $jails;
    }

    /** @return array<string, array{ip:string, jails:array<string>}> */
    public function bannedIps(?array $overview = null): array
    {
        $overview = $overview ?? $this->overview();
        $map = [];
        foreach ($overview as $jail) {
            foreach ($jail['banned'] as $ip) {
                if (!isset($map[$ip])) $map[$ip] = ['ip' => $ip, 'jails' => []];
                $map[$ip]['jails'][] = $jail['name'];
            }
        }
        return $map;
    }

    /** @return array{ok:bool, message:string} */
    public function unban(string $ip): array
    {
        $ip = trim($ip);
        if (!$this->isValidIp($ip)) return ['ok' => false, 'message' => 'Ungueltige IP-Adresse.'];
        $r = $this->run(['unban', $ip]);
        if (!$r['ok'] || ($r['err'] !== '' && stripos($r['err'], 'NOK') !== false)) {
            return ['ok' => false, 'message' => 'Entsperren fehlgeschlagen: ' . ($r['err'] ?: 'unbekannter Fehler')];
        }
        $count = (int) trim($r['out']);
        return ['ok' => true, 'message' => $count === 0
            ? 'IP war in keinem Jail (mehr) gesperrt.'
            : 'IP entsperrt (' . $count . ' Eintrag/Eintraege entfernt).'];
    }

    /**
     * Sperrzeitpunkte eines Jails: ip => ['banned_at' => 'Y-m-d H:i:s', 'ban_expires' => 'Y-m-d H:i:s'].
     * Quelle: fail2ban-client get <jail> banip --with-time.
     */
    public function jailBanTimes(string $jail): array
    {
        $r = $this->run(['bantimes', $jail]);
        if (!$r['ok']) return [];
        $map = [];
        foreach (preg_split('/\R/', trim($r['out'])) as $line) {
            // Format: "1.2.3.4 \t2026-06-10 06:16:19 + 7200 = 2026-06-10 08:16:19"
            if (preg_match('/^(\S+)\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s*\+\s*\d+\s*=\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', trim($line), $m)) {
                $map[$m[1]] = ['banned_at' => $m[2], 'ban_expires' => $m[3]];
            }
        }
        return $map;
    }

    /**
     * Land einer IP per lokalem geoiplookup (offline, GeoIP-DB).
     * @return array{code:?string, name:?string}
     */
    public function geoLookup(string $ip): array
    {
        static $cache = [];
        if (isset($cache[$ip])) return $cache[$ip];
        $res = ['code' => null, 'name' => null];
        if (function_exists('shell_exec') && filter_var($ip, FILTER_VALIDATE_IP)) {
            $out = (string) shell_exec('geoiplookup ' . escapeshellarg($ip) . ' 2>/dev/null');
            if (preg_match('/:\s*([A-Z]{2}),\s*(.+)$/m', trim($out), $m)) {
                $res = ['code' => $m[1], 'name' => trim($m[2])];
            }
        }
        return $cache[$ip] = $res;
    }

    /** @return array<string> */
    public function ufwStatus(): array
    {
        $r = $this->run(['ufw-status']);
        if (!$r['ok']) return [];
        return array_values(array_filter(preg_split('/\R/', trim($r['out'])), fn($l) => trim($l) !== ''));
    }

    /**
     * Schreibt den aktuellen Stand als Snapshot-JSON (vom Cron-Worker aufgerufen).
     * @param int $ts  Unix-Zeitstempel (vom Aufrufer, da im Worker verfuegbar)
     */
    public function writeSnapshot(int $ts): bool
    {
        $overview = $this->overview();

        // Sperrzeiten je Jail einsammeln (ip => [banned_at, ban_expires])
        $times = [];
        foreach ($overview as $jail) {
            if (empty($jail['banned'])) continue;
            foreach ($this->jailBanTimes($jail['name']) as $ip => $t) {
                $times[$ip][] = $t;
            }
        }

        // Aggregierte, angereicherte Liste der gesperrten IPs
        $banned = array_values($this->bannedIps($overview));
        foreach ($banned as &$b) {
            $ip = $b['ip'];
            // frueheste Sperrung + spaetester Ablauf ueber alle Jails
            $bannedAt = null; $expires = null;
            foreach ($times[$ip] ?? [] as $t) {
                if ($bannedAt === null || $t['banned_at'] < $bannedAt) $bannedAt = $t['banned_at'];
                if ($expires === null || $t['ban_expires'] > $expires) $expires = $t['ban_expires'];
            }
            $b['banned_at']   = $bannedAt;
            $b['ban_expires'] = $expires;
            $geo = $this->geoLookup($ip);
            $b['country_code'] = $geo['code'];
            $b['country']      = $geo['name'];
        }
        unset($b);

        $data = [
            'generated_at' => $ts,
            'available'    => !empty($overview),
            'jails'        => $overview,
            'banned'       => $banned,
            'ufw'          => $this->ufwStatus(),
        ];
        $tmp = $this->snapshotPath() . '.tmp';
        if (@file_put_contents($tmp, json_encode($data)) === false) return false;
        return @rename($tmp, $this->snapshotPath());
    }

    /**
     * Arbeitet offene Entsperr-Auftraege ab (vom Cron-Worker aufgerufen).
     * @return array<array{ip:string, ok:bool, message:string}>
     */
    public function processQueue(): array
    {
        $rows = $this->db()->query("SELECT id, ip, requested_by FROM firewall_unban_queue WHERE status = 'pending' ORDER BY id ASC") ?: [];
        $done = [];
        foreach ($rows as $row) {
            $res = $this->unban($row['ip']);
            $this->db()->execute(
                "UPDATE firewall_unban_queue SET status = ?, processed_at = NOW(), result = ? WHERE id = ?",
                [$res['ok'] ? 'done' : 'error', mb_substr($res['message'], 0, 255), $row['id']]
            );
            $done[] = [
                'ip'           => $row['ip'],
                'ok'           => $res['ok'],
                'message'      => $res['message'],
                'requested_by' => $row['requested_by'] !== null ? (int) $row['requested_by'] : null,
            ];
        }
        return $done;
    }

    // ========================================================================

    public function isValidIp(string $ip): bool
    {
        if (strpos($ip, '/') !== false) {
            [$addr, $mask] = explode('/', $ip, 2);
            if (!ctype_digit($mask)) return false;
            $maskInt = (int) $mask;
            $isV6 = strpos($addr, ':') !== false;
            if ($maskInt < 0 || $maskInt > ($isV6 ? 128 : 32)) return false;
            return filter_var($addr, FILTER_VALIDATE_IP) !== false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
