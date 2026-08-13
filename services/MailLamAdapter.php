<?php
namespace Services;

use Core\Database;

/**
 * Verknüpft Mails mit dem LAM-System.
 *
 * Heuristik für Anbieter-Erkennung:
 * - Mail-Domain vergleichen mit allen Kontakt-E-Mail-Domains der Anbieter
 * - Bei eindeutigem Match: Korrespondenz-Eintrag automatisch anlegen
 * - Bei mehrdeutigem/keinem Match: in mail_lam_verknuepfung 'aufgabe' setzen
 */
class MailLamAdapter
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function verarbeite(int $mailId, array $klassifikation): array
    {
        $mail = $this->db->queryOne(
            "SELECT * FROM mail_nachrichten WHERE id = ?",
            [$mailId]
        );
        if (!$mail) return ['fehler' => 'Mail nicht gefunden'];

        $domain = $this->extrahiereDomain($mail['absender_email']);
        if (!$domain) return ['fehler' => 'Keine Domain extrahierbar'];

        // Anbieter via Kontakt-E-Mail oder Anbieter-Webseite suchen
        $anbieter = $this->findeAnbieterFuerDomain($domain, $mail['absender_email']);

        $result = ['anbieter_id' => null, 'korrespondenz_id' => null, 'massnahme_id' => null];

        if (count($anbieter) === 1) {
            $a = $anbieter[0];
            $anbieterId = $a['id'];
            $result['anbieter_id'] = $anbieterId;

            // Optional: Maßnahme via Subject/Body-Match suchen (z.B. enthält Domain der Maßnahme)
            $massnahmeId = $this->findeMassnahmeFuerMail($mail, $anbieterId);
            $result['massnahme_id'] = $massnahmeId;

            // Korrespondenz-Eintrag automatisch
            if ($klassifikation['folgeaktion'] === 'lam_korrespondenz_anlegen') {
                $korrId = $this->legeKorrespondenzAn($mail, $anbieterId, $massnahmeId);
                $result['korrespondenz_id'] = $korrId;

                $this->db->execute(
                    "INSERT INTO mail_lam_verknuepfung (mail_id, typ, ziel_id, automatisch) VALUES (?, 'korrespondenz', ?, 1)",
                    [$mailId, (string)$korrId]
                );
            }
            $this->db->execute(
                "INSERT INTO mail_lam_verknuepfung (mail_id, typ, ziel_id, automatisch) VALUES (?, 'anbieter', ?, 1)",
                [$mailId, (string)$anbieterId]
            );
            if ($massnahmeId) {
                $this->db->execute(
                    "INSERT INTO mail_lam_verknuepfung (mail_id, typ, ziel_id, automatisch) VALUES (?, 'massnahme', ?, 1)",
                    [$mailId, $massnahmeId]
                );
                // KI-Felder aus der Mail in die Maßnahme spülen (nur leere Felder + Vorschlag-Aufgabe für vorhandene)
                $this->verarbeiteExtrahierteMassnahmenFelder($mailId, $massnahmeId, $klassifikation);
            }
        } else {
            // Keine eindeutige Zuordnung → Aufgabe in lam_aufgaben anlegen
            if ($klassifikation['folgeaktion'] === 'lam_anbieter_zuordnen' && count($anbieter) === 0) {
                $this->legeAufgabeAn($mail, $klassifikation);
            }
        }

        return $result;
    }

    private function extrahiereDomain(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        return strtolower(substr(strrchr($email, '@'), 1));
    }

    private function findeAnbieterFuerDomain(string $domain, string $vollstaendigeEmail): array
    {
        // 1) Über lam_kontakte (E-Mail-Domain Match)
        $treffer = $this->db->query(
            "SELECT DISTINCT a.id, a.name
             FROM lam_anbieter a
             JOIN lam_kontakte k ON k.anbieter_id = a.id
             WHERE a.geloescht_am IS NULL
               AND k.geloescht_am IS NULL
               AND (LOWER(k.email) = ? OR LOWER(k.email) LIKE ?)",
            [strtolower($vollstaendigeEmail), '%@' . $domain]
        );
        if (!empty($treffer)) return $treffer;

        // 2) Über Anbieter-eigene Webseite-Domain (lam_domains.anbieter_id + url)
        $treffer = $this->db->query(
            "SELECT DISTINCT a.id, a.name
             FROM lam_anbieter a
             JOIN lam_domains d ON d.anbieter_id = a.id
             WHERE a.geloescht_am IS NULL
               AND d.geloescht_am IS NULL
               AND d.url LIKE ?",
            ['%' . $domain . '%']
        );
        return $treffer;
    }

    private function findeMassnahmeFuerMail(array $mail, string $anbieterId): ?string
    {
        $text = mb_strtolower(($mail['betreff'] ?? '') . ' ' . ($mail['body_plain'] ?? ''));
        // Maßnahmen dieses Anbieters (offen)
        $massnahmen = $this->db->query(
            "SELECT m.id, m.linktext, d.url AS domain_url, m.veroeffentlichungs_url
             FROM lam_massnahmen m
             JOIN lam_domains d ON d.id = m.domain_id
             WHERE m.geloescht_am IS NULL
               AND d.anbieter_id = ?
               AND m.status NOT IN ('archiv', 'storniert')",
            [$anbieterId]
        );
        foreach ($massnahmen as $m) {
            $domainHost = parse_url($m['domain_url'], PHP_URL_HOST) ?: $m['domain_url'];
            $domainKurz = preg_replace('/^www\./', '', mb_strtolower($domainHost));
            if ($domainKurz && mb_strpos($text, $domainKurz) !== false) return $m['id'];
            if (!empty($m['veroeffentlichungs_url']) && mb_strpos($text, mb_strtolower($m['veroeffentlichungs_url'])) !== false) return $m['id'];
            if (!empty($m['linktext']) && mb_strpos($text, mb_strtolower($m['linktext'])) !== false) return $m['id'];
        }
        return null;
    }

    private function legeKorrespondenzAn(array $mail, string $anbieterId, ?string $massnahmeId): string
    {
        require_once SERVICES_PATH . '/LamService.php';
        $svc = new LamService($this->db);
        $id = $svc->ulid();
        $inhalt = "[Auto-Import aus Mail-Modul, Mail-ID {$mail['id']}]\n\n"
                . "Von: " . $mail['absender_email'] . "\n"
                . "Betreff: " . ($mail['betreff'] ?? '') . "\n\n"
                . mb_substr($mail['body_plain'] ?? '', 0, 5000);

        $this->db->execute(
            "INSERT INTO lam_kommunikation
                (id, anbieter_id, massnahme_id, typ, zeitpunkt, betreff, inhalt,
                 absender_mail, mail_id_extern, status, erstellt_am)
             VALUES (?, ?, ?, 'mail_eingang', ?, ?, ?, ?, ?, 'auto_import', NOW())",
            [
                $id, $anbieterId, $massnahmeId,
                $mail['empfangen_am'],
                mb_substr($mail['betreff'] ?? '', 0, 255),
                $inhalt,
                $mail['absender_email'],
                (string) $mail['id'],
            ]
        );
        return $id;
    }

    /**
     * Wendet KI-extrahierte Felder (preis, linktext, veroeffentlichungs_url, linkziel) auf die Maßnahme an.
     *
     * Strategie:
     * - Leere Felder in der Maßnahme: direkt setzen (kein Risiko, war ja leer)
     * - Bereits gefüllte Felder mit anderem Wert: NICHT überschreiben, stattdessen Aufgabe anlegen
     *   („KI schlägt anderen Wert vor — prüfen")
     * - veroeffentlichungs_url + Status: wenn URL kommt und Status noch nicht 'live' → Status-Vorschlag-Aufgabe
     */
    private function verarbeiteExtrahierteMassnahmenFelder(int $mailId, string $massnahmeId, array $klassifikation): void
    {
        $felder = (array)($klassifikation['extrahierte_felder'] ?? []);
        if (empty(array_filter($felder))) return;

        $m = $this->db->queryOne(
            "SELECT status, linktext, veroeffentlichungs_url, linkziel_id
             FROM lam_massnahmen WHERE id = ? AND geloescht_am IS NULL",
            [$massnahmeId]
        );
        if (!$m) return;

        $updates = [];
        $vorschlaege = [];

        // Veröffentlichungs-URL
        $vUrl = trim((string)($felder['veroeffentlichungs_url'] ?? ''));
        if ($vUrl !== '' && filter_var($vUrl, FILTER_VALIDATE_URL)) {
            if (empty($m['veroeffentlichungs_url'])) {
                $updates['veroeffentlichungs_url'] = $vUrl;
                // Status auf 'live' setzen wenn vorher nicht 'live'/'archiv'
                if (!in_array($m['status'], ['live', 'archiv'], true)) {
                    $updates['status'] = 'live';
                    $updates['veroeffentlicht_am'] = date('Y-m-d');
                }
            } elseif ($m['veroeffentlichungs_url'] !== $vUrl) {
                $vorschlaege[] = 'Andere Veröffentlichungs-URL gemeldet: ' . $vUrl . ' (bestehend: ' . $m['veroeffentlichungs_url'] . ')';
            }
        }

        // Linktext
        $linktext = trim((string)($felder['linktext'] ?? ''));
        if ($linktext !== '') {
            if (empty($m['linktext'])) {
                $updates['linktext'] = mb_substr($linktext, 0, 255);
            } elseif (mb_strtolower($m['linktext']) !== mb_strtolower($linktext)) {
                $vorschlaege[] = 'Anderer Linktext gemeldet: „' . $linktext . '" (bestehend: „' . $m['linktext'] . '")';
            }
        }

        // Preis → in lam_auslagen.weiterverrechnet falls noch keine Auslage da
        $preis = is_numeric($felder['preis'] ?? null) ? (float)$felder['preis'] : null;
        if ($preis !== null && $preis > 0) {
            $auslageDa = (int) $this->db->queryValue(
                "SELECT 1 FROM lam_auslagen WHERE massnahme_id = ? AND geloescht_am IS NULL LIMIT 1",
                [$massnahmeId]
            );
            if (!$auslageDa) {
                require_once SERVICES_PATH . '/LamService.php';
                $svc = new \Services\LamService($this->db);
                $this->db->execute(
                    "INSERT INTO lam_auslagen (id, massnahme_id, weiterverrechnet, sonderfall, erstellt_am)
                     VALUES (?, ?, ?, 'normal', NOW())",
                    [$svc->ulid(), $massnahmeId, $preis]
                );
            } else {
                $vorschlaege[] = 'Preis in Mail genannt: ' . number_format($preis, 2, ',', '.') . ' € (Auslage prüfen)';
            }
        }

        // Updates anwenden
        if (!empty($updates)) {
            $set = []; $vals = [];
            foreach ($updates as $k => $v) {
                $set[] = "`$k` = ?";
                $vals[] = $v;
            }
            $vals[] = $massnahmeId;
            $this->db->execute(
                "UPDATE lam_massnahmen SET " . implode(', ', $set) . " WHERE id = ?",
                $vals
            );
        }

        // Vorschläge als Aufgabe anlegen
        if (!empty($vorschlaege)) {
            require_once SERVICES_PATH . '/LamService.php';
            try {
                $svc = new \Services\LamService($this->db);
                $svc->legeAufgabeAn(
                    'mail_massnahme_konflikt',
                    'massnahme',
                    $massnahmeId,
                    'KI-Vorschlag prüfen aus Mail ' . $mailId,
                    "Eine eingehende Mail enthält Felder die anders sind als der Maßnahmen-Stand:\n\n" .
                    implode("\n• ", array_merge(['•'], $vorschlaege)) .
                    "\n\nMail-ID: " . $mailId,
                    date('Y-m-d', strtotime('+2 days'))
                );
            } catch (\Throwable $e) { /* fail-safe */ }
        }
    }

    private function legeAufgabeAn(array $mail, array $klassifikation): void
    {
        require_once SERVICES_PATH . '/LamService.php';
        try {
            $svc = new LamService($this->db);
            $aufgabenId = $svc->legeAufgabeAn(
                'mail_unbekannter_anbieter',
                'mail',
                (string)$mail['id'],
                'Anbieter zuordnen: ' . $mail['absender_email'],
                'Mail eingegangen, KI vermutet Anbieter-Bezug: ' . ($klassifikation['meta']['anbieter_kandidat'] ?? '(unbekannt)')
                . "\n\nBetreff: " . ($mail['betreff'] ?? ''),
                date('Y-m-d', strtotime('+3 days'))
            );
            $this->db->execute(
                "INSERT INTO mail_lam_verknuepfung (mail_id, typ, ziel_id, automatisch) VALUES (?, 'aufgabe', ?, 1)",
                [$mail['id'], $aufgabenId]
            );
        } catch (\Throwable $e) { /* fail-safe */ }
    }
}
