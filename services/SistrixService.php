<?php
namespace Services;

use Core\Database;

/**
 * SistrixService — Vanilla-PHP-Portierung des Laravel-Service aus dem LAM-Prototyp.
 *
 * Endpoints:
 *   - domain.sichtbarkeitsindex (1 Credit)      → SI
 *   - domain.sichtbarkeitsindex.overview (10)   → "sichtbar seit" (weeks zurückrechnen)
 *   - links.overview (25 Credits)               → DP (verlinkende Domains)
 *
 * Caching: pro Domain + Teil + heute max. 1 API-Call. Snapshot in lam_kennzahl_snapshots.
 * Rate-Limit: 300 ms Mindestabstand zwischen Calls (PHP-Prozess-lokal).
 *
 * API-Key liegt in settings.setting_value WHERE setting_key = 'sistrix_api_key'.
 * Wochenkontingent in 'sistrix_wochenkontingent' (default 20000).
 */
class SistrixService
{
    public const TEIL_SI    = 'si';
    public const TEIL_ALTER = 'alter';
    public const TEIL_DP    = 'dp';
    public const TEILE_ALLE = [self::TEIL_SI, self::TEIL_ALTER, self::TEIL_DP];

    public const CREDITS = [
        self::TEIL_SI    => 1,
        self::TEIL_ALTER => 10,
        self::TEIL_DP    => 25,
    ];

    private Database $db;
    private ?string $apiKey = null;
    private static ?float $letzterApiCall = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->apiKey = \Core\Settings::get('sistrix_api_key');
    }

    public function istKonfiguriert(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    public static function creditsFuer(array $teile): int
    {
        $summe = 0;
        foreach ($teile as $teil) $summe += self::CREDITS[$teil] ?? 0;
        return $summe;
    }

    /**
     * Holt Kennzahlen für eine Domain. Funktioniert mit oder ohne Pool-Eintrag:
     * - $domainId kann eine lam_domains.id sein (Pool-Domain) ODER
     * - direkt eine URL (z.B. 'beispiel.de'), wenn die Domain nicht im Pool ist.
     *
     * Snapshots werden primaer ueber domain_url referenziert (siehe Migration).
     *
     * Returns ['snapshot_id', 'werte' => [si, dp, sichtbar_seit, domain_alter], 'credits_verbraucht', 'fehler'].
     */
    public function holeKennzahlen(string $domainIdOderUrl, array $teile = null, bool $erzwingen = false): array
    {
        if (!$this->istKonfiguriert()) {
            throw new \RuntimeException('Sistrix-API-Key nicht in den Einstellungen gesetzt.');
        }

        // ULIDs sind 26 Zeichen, alles andere wird als URL behandelt.
        $domainId  = null;
        $domainUrl = null;
        if (strlen($domainIdOderUrl) === 26 && ctype_alnum($domainIdOderUrl)) {
            $row = $this->db->queryOne("SELECT id, url FROM lam_domains WHERE id = ?", [$domainIdOderUrl]);
            if ($row) { $domainId = $row['id']; $domainUrl = $row['url']; }
        }
        if ($domainUrl === null) {
            // Direkt als URL behandeln. Pool-Lookup optional (fuer sichtbar_seit-Update).
            $domainUrl = $domainIdOderUrl;
            $poolRow = $this->db->queryOne(
                "SELECT id FROM lam_domains WHERE url = ? AND geloescht_am IS NULL LIMIT 1",
                [$domainUrl]
            );
            $domainId = $poolRow['id'] ?? null;
        }

        $teile = $this->normalisiereTeile($teile);
        $istAlles = count($teile) === count(self::TEILE_ALLE);
        $heute = date('Y-m-d');

        // Heutigen Sistrix-Snapshot suchen — primaer ueber domain_url, damit
        // auch Snapshots ohne Pool-Eintrag wiedergefunden werden.
        $vorhanden = $this->db->queryOne(
            "SELECT id, si, dp, domain_alter, roh
             FROM lam_kennzahl_snapshots
             WHERE domain_url = ? AND erfasst_am = ? AND quelle = 'sistrix_api'
             LIMIT 1",
            [$domainUrl, $heute]
        );

        if ($erzwingen || $istAlles) {
            $teileZuHolen = $teile;
        } else {
            $teileZuHolen = array_values(array_filter(
                $teile,
                fn($t) => !$this->teilImSnapshot($vorhanden, $t)
            ));
            if (empty($teileZuHolen) && $vorhanden) {
                return [
                    'cached' => true,
                    'snapshot_id' => $vorhanden['id'],
                    'werte' => [
                        'si' => $vorhanden['si'],
                        'dp' => $vorhanden['dp'],
                        'domain_alter' => $vorhanden['domain_alter'],
                    ],
                    'credits_verbraucht' => 0,
                    'fehler' => [],
                ];
            }
        }

        $roh = $this->ruleSistrixAb($domainUrl, $teileZuHolen);

        // Update/Insert Snapshot
        $update = [];
        if (in_array(self::TEIL_SI, $teileZuHolen, true))    $update['si'] = $roh['si'];
        if (in_array(self::TEIL_DP, $teileZuHolen, true))    $update['dp'] = $roh['dp'];
        if (in_array(self::TEIL_ALTER, $teileZuHolen, true)) $update['domain_alter'] = $roh['domain_alter'];

        // Roh ergänzen, nicht überschreiben
        $rohBestand = ['methoden' => [], 'fehler' => []];
        if ($vorhanden && !empty($vorhanden['roh'])) {
            $altRoh = json_decode($vorhanden['roh'], true) ?: [];
            $rohBestand['methoden'] = $altRoh['methoden'] ?? [];
            $rohBestand['fehler'] = $altRoh['fehler'] ?? [];
        }
        $rohBestand['methoden'] = array_merge($rohBestand['methoden'], $roh['roh']['methoden'] ?? []);
        $rohBestand['fehler'] = array_merge($rohBestand['fehler'], $roh['roh']['fehler'] ?? []);

        if ($vorhanden) {
            $setParts = [];
            $params = [];
            foreach ($update as $feld => $wert) {
                $setParts[] = "`{$feld}` = ?";
                $params[] = $wert;
            }
            $setParts[] = "roh = ?";
            $params[] = json_encode($rohBestand);
            $params[] = $vorhanden['id'];
            $this->db->execute(
                "UPDATE lam_kennzahl_snapshots SET " . implode(', ', $setParts) . " WHERE id = ?",
                $params
            );
            $snapshotId = $vorhanden['id'];
        } else {
            // id ist VARCHAR(26) (ULID) ohne AUTO_INCREMENT/Default — selbst vergeben.
            // domain_url ist primaerer Schluessel, domain_id optional (nur fuer Pool-Domains).
            $cols = ['id', 'domain_id', 'domain_url', 'erfasst_am', 'quelle', 'roh'];
            $vals = [self::generiereUlid(), $domainId, $domainUrl, $heute, 'sistrix_api', json_encode($rohBestand)];
            foreach ($update as $feld => $wert) {
                $cols[] = $feld;
                $vals[] = $wert;
            }
            $placeholders = implode(',', array_fill(0, count($vals), '?'));
            $this->db->execute(
                "INSERT INTO lam_kennzahl_snapshots (" . implode(',', $cols) . ", erstellt_am)
                 VALUES ({$placeholders}, NOW())",
                $vals
            );
            $snapshotId = $vals[0];
        }

        // sichtbar_seit auf der Domain aktualisieren — nur wenn die Domain
        // tatsaechlich im Pool ist. Sonst wird der Wert nur im Snapshot gehalten.
        if ($domainId && in_array(self::TEIL_ALTER, $teileZuHolen, true) && !empty($roh['sichtbar_seit'])) {
            $this->db->execute(
                "UPDATE lam_domains SET sistrix_sichtbar_seit = ? WHERE id = ?",
                [$roh['sichtbar_seit'], $domainId]
            );
        }

        // Credits aus Response summieren
        $creditsVerbraucht = 0;
        foreach (($roh['roh']['methoden'] ?? []) as $body) {
            $used = $body['credits'][0]['used'] ?? null;
            if (is_numeric($used)) $creditsVerbraucht += (int)$used;
        }

        return [
            'cached' => false,
            'snapshot_id' => $snapshotId,
            'werte' => [
                'si' => $roh['si'],
                'dp' => $roh['dp'],
                'domain_alter' => $roh['domain_alter'],
                'sichtbar_seit' => $roh['sichtbar_seit'],
            ],
            'credits_verbraucht' => $creditsVerbraucht,
            'fehler' => $roh['fehler'],
        ];
    }

    /**
     * Wochenstatus: verbrauchte Credits, verbleibend, Wochenkontingent.
     */
    public function wochenStatus(): array
    {
        $kontingent = (int)($this->db->queryValue(
            "SELECT setting_value FROM settings WHERE setting_key = 'sistrix_wochenkontingent'"
        ) ?: 20000);

        // Wochenstart = Montag 00:00
        $heute = new \DateTime('today');
        $wochenStart = clone $heute;
        $wochenStart->modify('Monday this week');
        $wochenStartStr = $wochenStart->format('Y-m-d');

        // Snapshots dieser Woche
        $snapshots = $this->db->query(
            "SELECT roh FROM lam_kennzahl_snapshots
             WHERE erfasst_am >= ? AND quelle = 'sistrix_api'",
            [$wochenStartStr]
        );
        $abfragen = count($snapshots);

        $verbraucht = 0;
        foreach ($snapshots as $s) {
            $roh = !empty($s['roh']) ? (json_decode($s['roh'], true) ?: []) : [];
            foreach (($roh['methoden'] ?? []) as $body) {
                $used = $body['credits'][0]['used'] ?? null;
                if (is_numeric($used)) $verbraucht += (int)$used;
            }
        }

        // Manuelle Korrektur (z.B. „Sistrix-Dashboard sagt noch 17500 Credits")
        // Korrektur gilt nur, wenn sie in dieser Woche gesetzt wurde — ab nächstem Montag automatisch verfallen.
        $korrekturRaw = $this->db->queryValue(
            "SELECT setting_value FROM settings WHERE setting_key = 'sistrix_credits_korrektur'"
        );
        $verbleibend = max(0, $kontingent - $verbraucht);
        $korrekturAktiv = false;
        // queryValue gibt bei nicht vorhandenem Eintrag false zurueck — das war
        // hier ein Bug: false bestand "!== null && !== ''" und landete in der
        // Korrektur-Branch mit wert=0, sodass das Pre-Modal "0 Credits verbleibend"
        // anzeigte obwohl die Sistrix-API noch 19.945 hat.
        if ($korrekturRaw !== null && $korrekturRaw !== false && $korrekturRaw !== '') {
            $korrJson = is_string($korrekturRaw) && str_starts_with($korrekturRaw, '{')
                ? (json_decode($korrekturRaw, true) ?: [])
                : ['wert' => (int)$korrekturRaw, 'wochenstart' => $wochenStartStr];
            if (($korrJson['wochenstart'] ?? '') === $wochenStartStr && isset($korrJson['wert'])) {
                $verbleibend = max(0, (int)$korrJson['wert'] - $verbraucht);
                $korrekturAktiv = true;
            }
        }

        return [
            'abfragen' => $abfragen,
            'credits_verbraucht' => $verbraucht,
            'credits_verbleibend' => $verbleibend,
            'wochenkontingent' => $kontingent,
            'wochenstart' => $wochenStartStr,
            'konfiguriert' => $this->istKonfiguriert(),
            'manuelle_korrektur_aktiv' => $korrekturAktiv,
        ];
    }

    /**
     * Manuell SI/DP/Alter eintragen (ohne API-Call).
     */
    public function speichereManuell(string $domainId, array $werte): int
    {
        $heute = $werte['erfasst_am'] ?? date('Y-m-d');
        $existId = $this->db->queryValue(
            "SELECT id FROM lam_kennzahl_snapshots
             WHERE domain_id = ? AND erfasst_am = ? AND quelle = 'manuell'",
            [$domainId, $heute]
        );
        if ($existId) {
            $this->db->execute(
                "UPDATE lam_kennzahl_snapshots
                 SET si = ?, dp = ?, domain_alter = ?
                 WHERE id = ?",
                [$werte['si'] ?? null, $werte['dp'] ?? null, $werte['domain_alter'] ?? null, $existId]
            );
            return (int)$existId;
        }
        $neueId = self::generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_kennzahl_snapshots (id, domain_id, erfasst_am, quelle, si, dp, domain_alter, erstellt_am)
             VALUES (?, ?, ?, 'manuell', ?, ?, ?, NOW())",
            [$neueId, $domainId, $heute, $werte['si'] ?? null, $werte['dp'] ?? null, $werte['domain_alter'] ?? null]
        );
        return $neueId;
    }

    /**
     * ULID-Generator (Crockford-Base32, 26 chars). Inline-Variante,
     * damit der SistrixService keinen LamService braucht.
     */
    public static function generiereUlid(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $time = (int) (microtime(true) * 1000);
        $timeChars = '';
        for ($i = 0; $i < 10; $i++) {
            $timeChars = $alphabet[$time % 32] . $timeChars;
            $time = (int) ($time / 32);
        }
        $randChars = '';
        for ($i = 0; $i < 16; $i++) {
            $randChars .= $alphabet[random_int(0, 31)];
        }
        return $timeChars . $randChars;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helfer
    // ─────────────────────────────────────────────────────────────────────────

    private function normalisiereTeile(?array $teile): array
    {
        if ($teile === null || $teile === []) return self::TEILE_ALLE;
        $gueltig = array_values(array_intersect(self::TEILE_ALLE, $teile));
        if (empty($gueltig)) {
            throw new \InvalidArgumentException('Sistrix-Teile leer oder ungueltig.');
        }
        return $gueltig;
    }

    private function teilImSnapshot(?array $snapshot, string $teil): bool
    {
        if (!$snapshot) return false;
        $roh = !empty($snapshot['roh']) ? (json_decode($snapshot['roh'], true) ?: []) : [];
        return match ($teil) {
            self::TEIL_SI    => $snapshot['si'] !== null,
            self::TEIL_DP    => $snapshot['dp'] !== null,
            self::TEIL_ALTER => isset($roh['methoden']['domain.sichtbarkeitsindex.overview']),
            default => false,
        };
    }

    private function warteFallsZuSchnell(): void
    {
        if (self::$letzterApiCall !== null) {
            $verstrichenMs = (microtime(true) - self::$letzterApiCall) * 1000;
            if ($verstrichenMs < 300) {
                usleep((int)((300 - $verstrichenMs) * 1000));
            }
        }
        self::$letzterApiCall = microtime(true);
    }

    private function curl(string $url, array $params): array
    {
        $this->warteFallsZuSchnell();
        $params['api_key'] = $this->apiKey;
        $params['format'] = 'json';
        $url = $url . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Thoxan-LAM/1.0',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return [
            'http' => $httpStatus,
            'body' => $response ? (json_decode($response, true) ?: []) : [],
            'fehler' => $err,
        ];
    }

    private function ruleSistrixAb(string $url, array $teile): array
    {
        $rohSammlung = [];
        $werte = ['si' => null, 'dp' => null, 'domain_alter' => null, 'sichtbar_seit' => null];
        $fehler = [];

        if (in_array(self::TEIL_SI, $teile, true)) {
            $rohSammlung['domain.sichtbarkeitsindex'] = $this->ruleSichtbarkeitsindex($url, $werte, $fehler);
        }
        if (in_array(self::TEIL_ALTER, $teile, true)) {
            $rohSammlung['domain.sichtbarkeitsindex.overview'] = $this->ruleOverview($url, $werte, $fehler);
        }
        if (in_array(self::TEIL_DP, $teile, true)) {
            $rohSammlung['links.overview'] = $this->ruleLinksOverview($url, $werte, $fehler);
        }

        return [
            'si' => $werte['si'],
            'dp' => $werte['dp'],
            'domain_alter' => $werte['domain_alter'],
            'sichtbar_seit' => $werte['sichtbar_seit'],
            'roh' => ['methoden' => $rohSammlung, 'fehler' => $fehler],
            'fehler' => $fehler,
        ];
    }

    private function ruleSichtbarkeitsindex(string $url, array &$werte, array &$fehler): array
    {
        $res = $this->curl('https://api.sistrix.com/domain.sichtbarkeitsindex', ['domain' => $url]);
        if ($res['http'] === 429) {
            $fehler[] = 'domain.sichtbarkeitsindex: Sistrix-Rate-Limit (429)';
            return ['rate_limited' => true];
        }
        $body = $res['body'];
        $status = $body['status'] ?? null;
        if ($status === 'fail' || $status === 'error') {
            $fehler[] = 'domain.sichtbarkeitsindex: ' . ($body['error'][0]['error_message'] ?? 'unbekannt');
            return $body;
        }
        $wert = $body['answer'][0]['sichtbarkeitsindex'][0]['value']
             ?? $body['answer'][0]['value']
             ?? null;
        if (is_numeric($wert)) $werte['si'] = (float)$wert;
        else $fehler[] = 'domain.sichtbarkeitsindex: Wert nicht im Response';
        return $body;
    }

    private function ruleOverview(string $url, array &$werte, array &$fehler): array
    {
        $res = $this->curl('https://api.sistrix.com/domain.sichtbarkeitsindex.overview', [
            'domain' => $url, 'address_object' => $url
        ]);
        if ($res['http'] === 429) {
            $fehler[] = 'domain.sichtbarkeitsindex.overview: Rate-Limit (429)';
            return ['rate_limited' => true];
        }
        $body = $res['body'];
        $status = $body['status'] ?? null;
        if ($status === 'fail' || $status === 'error') {
            $fehler[] = 'domain.sichtbarkeitsindex.overview: ' . ($body['error'][0]['error_message'] ?? 'unbekannt');
            return $body;
        }
        $weeks = $body['answer'][0]['sichtbarkeitsindex_overview'][0]['weeks'] ?? null;
        if (is_numeric($weeks) && (int)$weeks > 0) {
            $heute = new \DateTime('today');
            $heute->modify('-' . ((int)$weeks * 7) . ' days');
            $werte['sichtbar_seit'] = $heute->format('Y-m-d');
        } else {
            // Fallback: ältestes Datum aus min/max
            $kandidaten = array_filter([
                $body['answer'][0]['sichtbarkeitsindex_overview_min'][0]['date'] ?? null,
                $body['answer'][0]['sichtbarkeitsindex_overview_max'][0]['date'] ?? null,
            ]);
            if (!empty($kandidaten)) {
                sort($kandidaten);
                $werte['sichtbar_seit'] = $kandidaten[0];
            } else {
                $fehler[] = 'domain.sichtbarkeitsindex.overview: weeks/min/max nicht im Response';
            }
        }
        return $body;
    }

    private function ruleLinksOverview(string $url, array &$werte, array &$fehler): array
    {
        $res = $this->curl('https://api.sistrix.com/links.overview', [
            'domain' => $url, 'address_object' => $url
        ]);
        if ($res['http'] === 429) {
            $fehler[] = 'links.overview: Rate-Limit (429)';
            return ['rate_limited' => true];
        }
        $body = $res['body'];
        $status = $body['status'] ?? null;
        if ($status === 'fail' || $status === 'error') {
            $fehler[] = 'links.overview: ' . ($body['error'][0]['error_message'] ?? 'unbekannt');
            return $body;
        }
        $wert = $body['answer'][0]['domains'][0]['num']
             ?? $body['answer'][0]['domains']['num']
             ?? null;
        if (is_numeric($wert)) $werte['dp'] = (int)$wert;
        else $fehler[] = 'links.overview: Wert nicht im Response';
        return $body;
    }
}
