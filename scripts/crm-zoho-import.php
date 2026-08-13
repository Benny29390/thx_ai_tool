<?php
/**
 * Zoho-CSV-Import.
 *
 * Liest /var/www/docs/zoho-export/Contacts_2026_06_01.csv ein und matched:
 *   1. legacy_zoho_id (Eintrag-ID aus Zoho)
 *   2. email_primaer
 *
 * Verhalten je Match:
 *   - Gefunden: NUR leere Felder überschreiben (Brevo-Daten bleiben Vorrang)
 *     + erstellt_am/geaendert_am IMMER aus Zoho übernehmen (echte Daten)
 *     + legacy_zoho_id + legacy_zoho_json IMMER setzen
 *     + Tags vereinigen, Listen-Mitgliedschaften ergänzen
 *   - Nicht gefunden: NEU anlegen (nur wenn E-Mail vorhanden)
 *
 * Usage:
 *   php scripts/crm-zoho-import.php [--dry-run] [--limit=N] [--start=N] [--datei=pfad.csv]
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Database.php';

$opts = getopt('', ['dry-run', 'limit::', 'start::', 'datei::']);
$dryRun = isset($opts['dry-run']);
$limit  = isset($opts['limit']) ? (int)$opts['limit'] : PHP_INT_MAX;
$start  = isset($opts['start']) ? (int)$opts['start'] : 0;
$datei  = $opts['datei'] ?? BASE_PATH . '/docs/zoho-export/Contacts_2026_06_01.csv';

if (!file_exists($datei)) {
    fwrite(STDERR, "Datei nicht gefunden: $datei\n");
    exit(1);
}

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

echo "─── Zoho-Import ───\n";
echo "Datei : $datei\n";
echo "Modus : " . ($dryRun ? "DRY-RUN (nichts schreiben)" : "LIVE") . "\n";
echo "Start : $start, Limit: " . ($limit === PHP_INT_MAX ? 'kein Limit' : $limit) . "\n\n";

// ─── Helpers ──────────────────────────────────────────────────────────
function anrede_mappen($z) {
    return ['1' => 'Herr', '2' => 'Frau'][trim((string)$z)] ?? trim((string)$z) ?: null;
}
function optin_mappen($z) {
    $v = strtolower(trim((string)$z));
    return [
        'bestätigt' => 'double_opted_in',
        'bestaetigt' => 'double_opted_in',
        'confirmed' => 'double_opted_in',
        'pending' => 'pending',
        'unsubscribed' => 'unsubscribed',
        'abgemeldet' => 'unsubscribed',
        'invalid' => 'invalid',
    ][$v] ?? null;
}
function status_mappen($tag) {
    $v = strtolower(trim((string)$tag));
    $map = [
        'ehemaliger kunde' => 'ehemaliger_kunde',
        'kunde' => 'kunde',
        'lead' => 'lead',
        'interessent' => 'interessent',
        'partner' => 'partner',
        'wunschkunde' => 'wunschkunde',
        'dienstleister' => 'dienstleister',
    ];
    return $map[$v] ?? null;
}
function bool_aus_x($v) {
    $v = trim((string)$v);
    return in_array(strtolower($v), ['x', 'true', '1', 'ja', 'yes'], true);
}
function bool_aus_truefalse($v) {
    return strtolower(trim((string)$v)) === 'true' ? 1 : 0;
}
function feld_normalisieren($v) {
    $v = trim((string)$v);
    return $v === '' || $v === '-' || $v === 'NULL' ? null : $v;
}
function datum_normalisieren($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return null;
    // Zoho: 2021-05-31 10:46:35  → MySQL-kompatibel
    if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $v)) return $v;
    return null;
}

// ─── Listen-Mapping: Zoho-Spalte → unsere crm_listen.name ────────────
$listenZuordnung = [
    'THX Partner'                  => 'THX Partner',
    'THX Hauptliste'               => 'THX Hauptliste',
    'Follow-Up Sichtbarkeit'       => 'Follow-Up Sichtbarkeit',
    'Follow-Up Profilierung'       => 'Follow-Up Profilierung',
    'Follow-Up Kontinuität'        => 'Follow-Up Kontinuität',
    'Follow-Up Verkauf'            => 'Follow-Up Verkauf',
    'Follow-Up Branding-Check'     => 'Follow-Up Branding-Check',
    'Akquise-Mastermind Onboarding' => 'Akquise-Mastermind Onboarding',
    'Wunschkunden-Konferenz'       => 'Wunschkunden-Konferenz',
    'Wunschkunden-Podcast'         => 'Wunschkunden-Podcast',
    'IHK'                          => 'IHK',
];
$leadmagnetZuordnung = [
    'Kontaktformular'   => 'Lead-Magnet: Kontaktformular',
    'Strategie-Check'   => 'Lead-Magnet: Strategie-Check',
    'Terminbuchung'     => 'Lead-Magnet: Terminbuchung',
    'Portal-Videos'     => 'Lead-Magnet: Portal-Videos',
    'Podcast-Alarm'     => 'Lead-Magnet: Podcast-Alarm',
    'Konferenz-Infos'   => 'Lead-Magnet: Konferenz-Infos',
    'Branding-Check'    => 'Lead-Magnet: Branding-Check',
    'IHK-Workshop'      => 'Lead-Magnet: IHK-Workshop',
];

// Listen-Namen → id auflösen
$listenIds = [];
foreach (array_merge($listenZuordnung, $leadmagnetZuordnung) as $zoho => $name) {
    $id = $db->queryValue("SELECT id FROM crm_listen WHERE name = ?", [$name]);
    if ($id) $listenIds[$name] = (int)$id;
}

// ─── Counter ─────────────────────────────────────────────────────────
$stats = [
    'gelesen' => 0, 'gefunden_per_id' => 0, 'gefunden_per_mail' => 0,
    'angereichert' => 0, 'neu_angelegt' => 0,
    'uebersprungen_keine_mail' => 0, 'fehler' => 0,
    'tags_vergeben' => 0, 'listen_zugeordnet' => 0, 'adressen_angelegt' => 0,
];

// ─── CSV einlesen ────────────────────────────────────────────────────
$f = fopen($datei, 'r');
$header = fgetcsv($f);
$headerMap = array_flip($header);

if ($start > 0) {
    for ($i = 0; $i < $start; $i++) {
        if (fgetcsv($f) === false) break;
    }
}

while (($row = fgetcsv($f)) !== false) {
    if ($stats['gelesen'] >= $limit) break;
    $stats['gelesen']++;
    $assoc = array_combine($header, $row);

    // ─── Zoho-Felder mappen ──────────────────────────────────────────
    $zohoId   = feld_normalisieren($assoc['Eintrag-ID'] ?? '');
    $email    = strtolower(trim((string)($assoc['E-Mail'] ?? '')));
    $emailZw  = strtolower(trim((string)($assoc['Zweite E-Mail-Adresse'] ?? '')));
    $vorname  = feld_normalisieren($assoc['Vorname'] ?? '');
    $nachname = feld_normalisieren($assoc['Nachname'] ?? '');

    // Skip wenn weder Mail noch Nachname
    if (!$email && !$nachname) {
        $stats['uebersprungen_keine_mail']++;
        continue;
    }

    // ─── Match suchen ────────────────────────────────────────────────
    $kontaktId = null;
    if ($zohoId) {
        $kontaktId = $db->queryValue("SELECT id FROM crm_kontakte WHERE legacy_zoho_id = ?", [$zohoId]);
        if ($kontaktId) $stats['gefunden_per_id']++;
    }
    if (!$kontaktId && $email) {
        $kontaktId = $db->queryValue("SELECT id FROM crm_kontakte WHERE email_primaer = ?", [$email]);
        if ($kontaktId) $stats['gefunden_per_mail']++;
    }

    // ─── Felder zusammenstellen ──────────────────────────────────────
    $felder = [
        'anrede'        => anrede_mappen($assoc['Anrede'] ?? ''),
        'titel'         => feld_normalisieren($assoc['Titel'] ?? ''),
        'vorname'       => $vorname,
        'nachname'      => $nachname ?: '(unbekannt)',
        'funktion'      => feld_normalisieren($assoc['Funktion'] ?? ''),
        'abteilung'     => feld_normalisieren($assoc['Abteilung'] ?? ''),
        'geburtsdatum'  => datum_normalisieren($assoc['Geburtsdatum'] ?? ''),
        'email_primaer' => $email ?: null,
        'email_zweit'   => $emailZw ?: null,
        'telefon'       => feld_normalisieren($assoc['Tel.'] ?? ''),
        'telefon_alt'   => feld_normalisieren($assoc['Telefon alternativ'] ?? ''),
        'mobil'         => feld_normalisieren($assoc['Mobil'] ?? ''),
        'fax'           => feld_normalisieren($assoc['Fax'] ?? ''),
        'website'       => feld_normalisieren($assoc['Website'] ?? ''),
        'interessen'    => feld_normalisieren($assoc['Interessen'] ?? ''),
        'merkmale'      => feld_normalisieren($assoc['Merkmale'] ?? ''),
        'beschreibung'  => feld_normalisieren($assoc['Beschreibung'] ?? ''),
        'bevorzugtes_thema' => feld_normalisieren($assoc['Bevorzugtes Thema'] ?? ''),
        'kontakt_status' => status_mappen($assoc['Tag'] ?? '') ?: status_mappen($assoc['Kontakt-Status'] ?? ''),
        'lead_quelle'   => feld_normalisieren($assoc['Lead-Quelle'] ?? ''),
        'opt_in_status' => optin_mappen($assoc['Optin-Status'] ?? ''),
        'thx_score'     => is_numeric($assoc['THX-Score'] ?? '') ? (int)$assoc['THX-Score'] : null,
        // UTM
        'utm_source'    => feld_normalisieren($assoc['utm_source'] ?? ''),
        'utm_medium'    => feld_normalisieren($assoc['utm_medium'] ?? ''),
        'utm_campaign'  => feld_normalisieren($assoc['utm_campaign'] ?? ''),
        'utm_content'   => feld_normalisieren($assoc['utm_content'] ?? ''),
        'utm_term'      => feld_normalisieren($assoc['utm_term'] ?? ''),
        'herkunft_referrer' => feld_normalisieren($assoc['Herkunft / Referrer'] ?? ''),
        // Lead-Magnet
        'lead_magnet_name' => feld_normalisieren($assoc['Lead-Magnet Name'] ?? '') ?: feld_normalisieren($assoc['Lead-Magnet-Typ'] ?? ''),
        'lead_magnet_url'  => feld_normalisieren($assoc['Lead-Magnet URL'] ?? ''),
        // Wunschkunden-Podcast
        'podcast_titel'         => feld_normalisieren($assoc['Wunschkunden-Podcast Titel'] ?? ''),
        'podcast_subtitel'      => feld_normalisieren($assoc['Wunschkunden-Podcast Subtitel'] ?? ''),
        'podcast_release_datum' => datum_normalisieren($assoc['Wunschkunden-Podcast Release Datum'] ?? ''),
        'podcast_release_url'   => feld_normalisieren($assoc['Wunschkunden-Podcast Release URL'] ?? ''),
        'podcast_release_mail'  => feld_normalisieren($assoc['Wunschkunden-Podcast Release Mail'] ?? ''),
        // Meta / Trigger
        'ac_sync'                 => bool_aus_truefalse($assoc['AC Sync'] ?? 'false'),
        'kuendigungsoption'       => feld_normalisieren($assoc['E-Mail-Kündigungsoption'] ?? ''),
        'stand_datensatz'         => feld_normalisieren($assoc['Stand Datensatz'] ?? ''),
        'layout_name'             => feld_normalisieren($assoc['Layout'] ?? '') ?: 'Thoxan',
        'trigger_kontaktformular' => bool_aus_truefalse($assoc['Trigger Kontaktformular'] ?? 'false'),
        'trigger_terminbuchung'   => bool_aus_truefalse($assoc['Trigger Terminbuchung'] ?? 'false'),
        'trigger_strategie_check' => bool_aus_truefalse($assoc['Trigger Strategie-Check'] ?? 'false'),
        'trigger_lead_magnet'     => bool_aus_truefalse($assoc['Trigger Lead-Magnet'] ?? 'false'),
        'trigger_test'            => bool_aus_truefalse($assoc['Test-Trigger'] ?? 'false'),
    ];

    // Echte Zoho-Zeitstempel
    $erstellt = datum_normalisieren($assoc['Zeitpunkt der Erstellung'] ?? '');
    $geaendert = datum_normalisieren($assoc['Zeitpunkt der Änderung'] ?? '');

    // Backup-JSON
    $jsonBackup = json_encode($assoc, JSON_UNESCAPED_UNICODE);

    try {
        if ($kontaktId) {
            // ─── ANREICHERN: nur leere Felder befüllen ────────────────
            $vorhanden = $db->queryOne("SELECT * FROM crm_kontakte WHERE id = ?", [$kontaktId]);
            $update = [];
            foreach ($felder as $sp => $wert) {
                if ($wert === null) continue;
                // Bestehendes leer/0? Dann übernehmen
                $bestehend = $vorhanden[$sp] ?? null;
                if ($bestehend === null || $bestehend === '' || ($sp === 'thx_score' && $bestehend == 0)) {
                    $update[$sp] = $wert;
                }
            }
            // Zeitstempel + Legacy IMMER setzen
            if ($erstellt)  $update['erstellt_am'] = $erstellt;
            if ($geaendert) $update['geaendert_am'] = $geaendert;
            if ($zohoId)    $update['legacy_zoho_id'] = $zohoId;
            $update['legacy_zoho_json'] = $jsonBackup;

            if ($update && !$dryRun) {
                $db->update('crm_kontakte', $update, 'id = ?', [$kontaktId]);
            }
            $stats['angereichert']++;
        } else {
            // ─── NEU ANLEGEN ─────────────────────────────────────────
            if (!$email) { $stats['uebersprungen_keine_mail']++; continue; }
            $insertDaten = array_filter($felder, fn($v) => $v !== null);
            $insertDaten['legacy_zoho_id'] = $zohoId;
            $insertDaten['legacy_zoho_json'] = $jsonBackup;
            $insertDaten['erstellt_am'] = $erstellt ?: date('Y-m-d H:i:s');
            if ($geaendert) $insertDaten['geaendert_am'] = $geaendert;
            if (!$dryRun) {
                $kontaktId = (int)$db->insert('crm_kontakte', $insertDaten);
                // Aktivität extra try/catch — wenn die ENUM o.ä. mault, sollen wir den Kontakt trotzdem als angelegt zählen
                try {
                    $db->insert('crm_aktivitaeten', [
                        'kontakt_id' => $kontaktId,
                        'typ' => 'kontakt_angelegt',
                        'titel' => 'Aus Zoho-Export importiert',
                        'quelle' => 'zoho_import',
                    ]);
                } catch (\Throwable $eAct) {
                    // Aktivitäts-Eintrag ist nice-to-have, nicht kritisch
                }
            } else {
                $kontaktId = 0;
            }
            $stats['neu_angelegt']++;
        }

        // ─── Tags vereinigen (aus Multiselect Tags + Tag) ───────────
        // Zoho mischt Komma- und Semikolon-Trenner durcheinander → an beidem splitten
        $tagText = trim(($assoc['Multiselect Tags'] ?? '') . ',' . ($assoc['Tag'] ?? ''), ',');
        if ($tagText && $kontaktId && !$dryRun) {
            $namen = array_filter(array_map('trim', preg_split('/[,;]/', $tagText)));
            foreach ($namen as $tagName) {
                if (!$tagName) continue;
                $tagId = $db->queryValue("SELECT id FROM crm_tags WHERE LOWER(name) = LOWER(?)", [$tagName]);
                if (!$tagId) {
                    $tagId = $db->insert('crm_tags', [
                        'name' => $tagName,
                        'slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tagName)),
                    ]);
                }
                try {
                    $db->execute("INSERT IGNORE INTO crm_kontakt_tags (kontakt_id, tag_id) VALUES (?, ?)", [$kontaktId, $tagId]);
                    $stats['tags_vergeben']++;
                } catch (\Throwable $e) {}
            }
        }

        // ─── Listen-Mitgliedschaften (X-Spalten) ────────────────────
        foreach ($listenZuordnung as $zohoSpalte => $listenName) {
            if (!isset($assoc[$zohoSpalte])) continue;
            if (!bool_aus_x($assoc[$zohoSpalte])) continue;
            $listenId = $listenIds[$listenName] ?? null;
            if (!$listenId || !$kontaktId || $dryRun) continue;
            try {
                $db->execute("INSERT IGNORE INTO crm_kontakt_listen (kontakt_id, listen_id, status) VALUES (?, ?, 'aktiv')",
                             [$kontaktId, $listenId]);
                $stats['listen_zugeordnet']++;
            } catch (\Throwable $e) {}
        }

        // ─── Lead-Magnet-Listen (gleicher Mechanismus) ──────────────
        foreach ($leadmagnetZuordnung as $zohoSpalte => $listenName) {
            if (!isset($assoc[$zohoSpalte])) continue;
            if (!bool_aus_x($assoc[$zohoSpalte])) continue;
            $listenId = $listenIds[$listenName] ?? null;
            if (!$listenId || !$kontaktId || $dryRun) continue;
            try {
                $db->execute("INSERT IGNORE INTO crm_kontakt_listen (kontakt_id, listen_id, status) VALUES (?, ?, 'aktiv')",
                             [$kontaktId, $listenId]);
                $stats['listen_zugeordnet']++;
            } catch (\Throwable $e) {}
        }

        // ─── Adressen ───────────────────────────────────────────────
        $adrGeschaeft = [
            'strasse'    => feld_normalisieren($assoc['Postadresse Straße'] ?? ''),
            'plz'        => feld_normalisieren($assoc['Postadresse PLZ'] ?? ''),
            'stadt'      => feld_normalisieren($assoc['Postadresse Stadt'] ?? ''),
            'bundesland' => feld_normalisieren($assoc['Postadresse Bundesland'] ?? ''),
            'land'       => feld_normalisieren($assoc['Postadresse Land'] ?? '') ?: 'Deutschland',
        ];
        $adrPrivat = [
            'strasse'    => feld_normalisieren($assoc['Straße (privat)'] ?? '') ?: feld_normalisieren($assoc['Alternative Straße'] ?? ''),
            'plz'        => feld_normalisieren($assoc['PLZ (privat)'] ?? '') ?: feld_normalisieren($assoc['Alternative Postleitzahl'] ?? ''),
            'stadt'      => feld_normalisieren($assoc['Stadt (privat)'] ?? '') ?: feld_normalisieren($assoc['Alternative Stadt'] ?? ''),
            'bundesland' => feld_normalisieren($assoc['Bundesland (privat)'] ?? '') ?: feld_normalisieren($assoc['Alternatives Bundesland'] ?? ''),
            'land'       => feld_normalisieren($assoc['Land (privat)'] ?? '') ?: feld_normalisieren($assoc['Alternatives Land'] ?? '') ?: 'Deutschland',
        ];
        foreach (['geschaeftlich' => $adrGeschaeft, 'privat' => $adrPrivat] as $typ => $adr) {
            if (!$adr['strasse'] && !$adr['stadt'] && !$adr['plz']) continue;
            if (!$kontaktId || $dryRun) continue;
            $exists = $db->queryValue("SELECT id FROM crm_adressen WHERE kontakt_id = ? AND typ = ?", [$kontaktId, $typ]);
            if (!$exists) {
                $db->insert('crm_adressen', array_merge(['kontakt_id' => $kontaktId, 'typ' => $typ, 'ist_primaer' => $typ === 'geschaeftlich' ? 1 : 0], $adr));
                $stats['adressen_angelegt']++;
            }
        }

        // ─── Social Media ───────────────────────────────────────────
        $social = [
            'linkedin'  => feld_normalisieren($assoc['Linkedin'] ?? ''),
            'xing'      => feld_normalisieren($assoc['XING'] ?? ''),
            'facebook'  => feld_normalisieren($assoc['Facebook'] ?? ''),
            'instagram' => feld_normalisieren($assoc['Instagram'] ?? ''),
            'twitter'   => feld_normalisieren($assoc['Twitter'] ?? '') ?: feld_normalisieren($assoc['Twitter.'] ?? ''),
            'youtube'   => feld_normalisieren($assoc['YouTube'] ?? ''),
        ];
        foreach ($social as $plattform => $url) {
            if (!$url || !$kontaktId || $dryRun) continue;
            try {
                $db->execute("INSERT INTO crm_social_links (kontakt_id, plattform, url) VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE url = VALUES(url)",
                             [$kontaktId, $plattform, $url]);
            } catch (\Throwable $e) {}
        }
    } catch (\Throwable $e) {
        $stats['fehler']++;
        fwrite(STDERR, "Fehler bei Zeile {$stats['gelesen']} ($email): " . $e->getMessage() . "\n");
    }

    if ($stats['gelesen'] % 100 === 0) {
        echo "  · {$stats['gelesen']} verarbeitet …\n";
    }
}
fclose($f);

echo "\n─── Statistik ───\n";
foreach ($stats as $k => $v) {
    printf("  %-30s : %d\n", $k, $v);
}
echo "\n" . ($dryRun ? "DRY-RUN — keine Änderungen geschrieben." : "Fertig.") . "\n";
