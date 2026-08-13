<?php
/**
 * LAMS ETL — Migrationsskript SQLite (LAMS-Prototyp) -> MySQL (KI-Tool)
 *
 * Liest /tmp/lams_source.sqlite und schreibt nach `ki_tool`.lam_* Tabellen.
 * Mapping fuer customer_id und user_id erwartet die Tabellen
 * lam_id_map_customer und lam_id_map_user als bereits befuellt.
 *
 * Idempotent: pro Tabelle wird zuerst die Ziel-Tabelle geleert, dann neu
 * befuellt. Foreign-Key-Checks bleiben aktiviert; Reihenfolge ist
 * topologisch sortiert.
 *
 * Aufruf: php /var/www/scripts/lam_import.php
 */

declare(strict_types=1);

const SQLITE_PATH = '/tmp/lams_source.sqlite';
const CONFIG_PATH = '/var/www/config/config.php';
const BATCH_SIZE  = 500;

// -----------------------------------------------------------------------------

function logInfo(string $msg): void {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function logSection(string $title): void {
    echo PHP_EOL . str_repeat('=', 78) . PHP_EOL;
    echo '  ' . $title . PHP_EOL;
    echo str_repeat('=', 78) . PHP_EOL;
}

function connectSqlite(): PDO {
    $pdo = new PDO('sqlite:' . SQLITE_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function connectMysql(): PDO {
    $config = require CONFIG_PATH;
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'], $db['port'], $db['name'], $db['charset']
    );
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);
    return $pdo;
}

/**
 * Liest die Mapping-Tabellen einmalig und stellt Hash-Maps bereit.
 */
function ladeMappings(PDO $mysql): array {
    $customers = [];
    foreach ($mysql->query('SELECT kuerzel, customer_id FROM lam_id_map_customer') as $r) {
        $customers[$r['kuerzel']] = (int) $r['customer_id'];
    }
    $users = [];
    foreach ($mysql->query('SELECT mitarbeiter_kuerzel, user_id FROM lam_id_map_user') as $r) {
        $users[$r['mitarbeiter_kuerzel']] = (int) $r['user_id'];
    }
    return ['customer' => $customers, 'user' => $users];
}

function mapCustomerId(?string $kuerzel, array $maps): ?int {
    if ($kuerzel === null || $kuerzel === '') {
        return null;
    }
    return $maps['customer'][$kuerzel] ?? null;
}

function mapUserId(?string $kuerzel, array $maps): ?int {
    if ($kuerzel === null || $kuerzel === '') {
        return null;
    }
    return $maps['user'][$kuerzel] ?? null;
}

/**
 * Bulk-Insert mit prepared Statement und chunked Rows.
 */
function bulkInsert(PDO $mysql, string $table, array $rows): int {
    if (empty($rows)) {
        return 0;
    }
    $cols     = array_keys($rows[0]);
    $colList  = '`' . implode('`, `', $cols) . '`';
    $placeRow = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';

    $total = 0;
    foreach (array_chunk($rows, BATCH_SIZE) as $chunk) {
        $placeholders = implode(', ', array_fill(0, count($chunk), $placeRow));
        $sql = "INSERT INTO `{$table}` ({$colList}) VALUES {$placeholders}";
        $stmt = $mysql->prepare($sql);
        $i = 1;
        foreach ($chunk as $row) {
            foreach ($cols as $c) {
                $stmt->bindValue($i++, $row[$c]);
            }
        }
        $stmt->execute();
        $total += $stmt->rowCount();
    }
    return $total;
}

function truncate(PDO $mysql, string $table): void {
    $mysql->exec("DELETE FROM `{$table}`");
}

// -----------------------------------------------------------------------------
// Tabellen-Migrationen — jede Funktion gibt die Anzahl uebertragener Zeilen zurueck.
// -----------------------------------------------------------------------------

function migriereAnbieter(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM anbieter') as $r) {
        $rows[] = [
            'id'               => $r['id'],
            'name'             => $r['name'],
            'firma'            => $r['firma'],
            'beziehungsstatus' => $r['beziehungsstatus'],
            'ist_betreiber'    => (int) $r['ist_betreiber'],
            'ist_vermittler'   => (int) $r['ist_vermittler'],
            'notizen'          => $r['notizen'],
            'erstellt_am'      => $r['erstellt_am'],
            'geloescht_am'     => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_anbieter', $rows);
}

function migriereImportBatches(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM import_batches') as $r) {
        $rows[] = [
            'id'                     => $r['id'],
            'dateiname'              => $r['dateiname'],
            'datei_datum'            => $r['datei_datum'],
            'importiert_am'          => $r['importiert_am'],
            'importiert_von_user_id' => mapUserId($r['importiert_von'], $maps),
            'anzahl_neu'             => (int) $r['anzahl_neu'],
            'anzahl_dublette'        => (int) $r['anzahl_dublette'],
            'anzahl_fehler'          => (int) $r['anzahl_fehler'],
            'status'                 => $r['status'],
            'notizen'                => $r['notizen'],
            'mapping'                => $r['mapping'],
            'erstellt_am'            => $r['erstellt_am'],
            'geloescht_am'           => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_import_batches', $rows);
}

function migriereTags(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM tags') as $r) {
        $rows[] = [
            'id'               => (int) $r['id'],
            'slug'             => $r['slug'],
            'name'             => $r['name'],
            'beschreibung'     => $r['beschreibung'],
            'verwendungs_zahl' => (int) $r['verwendungs_zahl'],
            'erstellt_am'      => $r['erstellt_am'],
            'geloescht_am'     => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_tags', $rows);
}

function migriereDomains(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM domains') as $r) {
        $rows[] = [
            'id'                              => $r['id'],
            'url'                             => $r['url'],
            'anbieter_id'                     => $r['anbieter_id'],
            'quelle_recherche'                => $r['quelle_recherche'],
            'buchbar_via'                     => $r['buchbar_via'],
            'disqualifiziert'                 => (int) $r['disqualifiziert'],
            'disqualifikations_grund'         => $r['disqualifikations_grund'],
            'notizen'                         => $r['notizen'],
            'wartezeit_bis'                   => $r['wartezeit_bis'],
            'verifikation_status'             => $r['verifikation_status'],
            'verifiziert_am'                  => $r['verifiziert_am'],
            'verifiziert_von_user_id'         => mapUserId($r['verifiziert_von'], $maps),
            'letzter_check_am'                => $r['letzter_check_am'],
            'import_batch_id'                 => $r['import_batch_id'],
            'quelle_anhang'                   => $r['quelle_anhang'],
            'sistrix_sichtbar_seit'           => $r['sistrix_sichtbar_seit'],
            'impressum_url'                   => $r['impressum_url'],
            'weitere_quellen_urls'            => $r['weitere_quellen_urls'],
            'ki_kurzbeschreibung'             => $r['ki_kurzbeschreibung'],
            'ki_kurzbeschreibung_generiert_am'=> $r['ki_kurzbeschreibung_generiert_am'],
            'letzter_http_status'             => $r['letzter_http_status'] !== null ? (int) $r['letzter_http_status'] : null,
            'letzter_http_erreichbar'         => $r['letzter_http_erreichbar'] !== null ? (int) $r['letzter_http_erreichbar'] : null,
            'linkart'                         => $r['linkart'],
            'herkunft'                        => $r['herkunft'],
            'herkunft_customer_id'            => mapCustomerId($r['herkunft_kunde_kuerzel'], $maps),
            'erstellt_am'                     => $r['erstellt_am'],
            'geloescht_am'                    => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_domains', $rows);
}

function migriereKontakte(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM kontakte') as $r) {
        $rows[] = [
            'id'                      => $r['id'],
            'anbieter_id'             => $r['anbieter_id'],
            'vorname'                 => $r['vorname'],
            'nachname'                => $r['nachname'],
            'email'                   => $r['email'],
            'telefon'                 => $r['telefon'],
            'rolle'                   => $r['rolle'],
            'verifikation_status'     => $r['verifikation_status'],
            'verifiziert_am'          => $r['verifiziert_am'],
            'verifiziert_von_user_id' => mapUserId($r['verifiziert_von'], $maps),
            'import_batch_id'         => $r['import_batch_id'],
            'quelle_anhang'           => $r['quelle_anhang'],
            'prioritaet'              => (int) $r['prioritaet'],
            'erstellt_am'             => $r['erstellt_am'],
            'geloescht_am'            => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_kontakte', $rows);
}

function migriereKonditionen(PDO $sqlite, PDO $mysql, array $maps): int {
    $stmt = $sqlite->query('SELECT * FROM konditionen');
    $rows = [];
    $total = 0;
    foreach ($stmt as $r) {
        $rows[] = [
            'id'                      => $r['id'],
            'domain_id'               => $r['domain_id'],
            'buchungstyp'             => $r['buchungstyp'],
            'preis'                   => $r['preis'],
            'laufzeit_monate'         => (int) $r['laufzeit_monate'],
            'gekennzeichnet'          => (int) $r['gekennzeichnet'],
            'link_typ'                => $r['link_typ'],
            'inkl_text'               => (int) $r['inkl_text'],
            'wortzahl_min'            => $r['wortzahl_min'] !== null ? (int) $r['wortzahl_min'] : null,
            'themenausschluss'        => $r['themenausschluss'],
            'gueltig_ab'              => $r['gueltig_ab'],
            'verifikation_status'     => $r['verifikation_status'],
            'verifiziert_am'          => $r['verifiziert_am'],
            'verifiziert_von_user_id' => mapUserId($r['verifiziert_von'], $maps),
            'import_batch_id'         => $r['import_batch_id'],
            'quelle_anhang'           => $r['quelle_anhang'],
            'via_anbieter_id'         => $r['via_anbieter_id'],
            'erstellt_am'             => $r['erstellt_am'],
            'geloescht_am'            => $r['geloescht_am'],
        ];
        if (count($rows) >= 2000) {
            $total += bulkInsert($mysql, 'lam_konditionen', $rows);
            $rows = [];
        }
    }
    $total += bulkInsert($mysql, 'lam_konditionen', $rows);
    return $total;
}

function migriereDomainTag(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM domain_tag') as $r) {
        $rows[] = [
            'domain_id' => $r['domain_id'],
            'tag_id'    => (int) $r['tag_id'],
            'primaer'   => (int) $r['primaer'],
        ];
    }
    return bulkInsert($mysql, 'lam_domain_tag', $rows);
}

function migriereDomainAnbieter(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM domain_anbieter') as $r) {
        $rows[] = [
            'id'          => $r['id'],
            'domain_id'   => $r['domain_id'],
            'anbieter_id' => $r['anbieter_id'],
            'rolle'       => $r['rolle'],
            'erstellt_am' => $r['erstellt_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_domain_anbieter', $rows);
}

function migriereDomainLinks(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM domain_links') as $r) {
        $rows[] = [
            'id'           => $r['id'],
            'domain_id'    => $r['domain_id'],
            'typ'          => $r['typ'],
            'label'        => $r['label'],
            'url'          => $r['url'],
            'position'     => (int) $r['position'],
            'erstellt_am'  => $r['erstellt_am'],
            'geloescht_am' => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_domain_links', $rows);
}

function migriereKennzahlSnapshots(PDO $sqlite, PDO $mysql): int {
    $stmt = $sqlite->query('SELECT * FROM kennzahl_snapshots');
    $rows = [];
    $total = 0;
    foreach ($stmt as $r) {
        $rows[] = [
            'id'           => $r['id'],
            'domain_id'    => $r['domain_id'],
            'erfasst_am'   => $r['erfasst_am'],
            'si'           => $r['si'],
            'dp'           => $r['dp'] !== null ? (int) $r['dp'] : null,
            'domain_alter' => $r['domain_alter'] !== null ? (int) $r['domain_alter'] : null,
            'quelle'       => $r['quelle'],
            'roh'          => $r['roh'],
            'erstellt_am'  => $r['erstellt_am'],
        ];
        if (count($rows) >= 2000) {
            $total += bulkInsert($mysql, 'lam_kennzahl_snapshots', $rows);
            $rows = [];
        }
    }
    $total += bulkInsert($mysql, 'lam_kennzahl_snapshots', $rows);
    return $total;
}

function migriereKundenConfig(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM kunden') as $r) {
        $customerId = mapCustomerId($r['kuerzel'], $maps);
        if ($customerId === null) {
            logInfo("WARN: kein customer_id-Mapping fuer kunde '{$r['kuerzel']}' — uebersprungen");
            continue;
        }
        $rows[] = [
            'customer_id'        => $customerId,
            'budget_monat'       => $r['budget_monat'],
            'mix_strategie'      => $r['mix_strategie'],
            'brand_regel'        => $r['brand_regel'],
            'wissensdb_ordner'   => $r['wissensdb_ordner'],
            'asana_projekt_gid'  => $r['asana_projekt_gid'],
            'asana_projekt_name' => $r['asana_projekt_name'],
            'asana_section_gid'  => $r['asana_section_gid'],
            'asana_section_name' => $r['asana_section_name'],
            'erstellt_am'        => $r['erstellt_am'],
            'geaendert_am'       => null,
        ];
    }
    return bulkInsert($mysql, 'lam_kunden_config', $rows);
}

function migriereCustomerTags(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM kunde_tag') as $r) {
        $customerId = mapCustomerId($r['kuerzel'], $maps);
        if ($customerId === null) {
            continue;
        }
        $rows[] = [
            'customer_id' => $customerId,
            'tag_id'      => (int) $r['tag_id'],
            'gewichtung'  => (int) $r['gewichtung'],
        ];
    }
    return bulkInsert($mysql, 'lam_customer_tags', $rows);
}

function migriereLinkziele(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM linkziele') as $r) {
        $customerId = mapCustomerId($r['kuerzel'], $maps);
        if ($customerId === null) {
            continue;
        }
        $rows[] = [
            'id'                   => $r['id'],
            'customer_id'          => $customerId,
            'url'                  => $r['url'],
            'thema'                => $r['thema'],
            'bevorzugter_linktext' => $r['bevorzugter_linktext'],
            'status'               => $r['status'],
            'erstellt_am'          => $r['erstellt_am'],
            'geloescht_am'         => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_linkziele', $rows);
}

function migriereDomainCustomer(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM domain_kunde') as $r) {
        $customerId = mapCustomerId($r['kuerzel'], $maps);
        if ($customerId === null) {
            continue;
        }
        $rows[] = [
            'domain_id'   => $r['domain_id'],
            'customer_id' => $customerId,
            'erstellt_am' => $r['erstellt_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_domain_customer', $rows);
}

function migriereMassnahmen(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM massnahmen') as $r) {
        $customerId = mapCustomerId($r['kuerzel'], $maps);
        if ($customerId === null) {
            continue;
        }
        $rows[] = [
            'id'                              => $r['id'],
            'customer_id'                     => $customerId,
            'domain_id'                       => $r['domain_id'],
            'linkziel_id'                     => $r['linkziel_id'],
            'verantwortlich_user_id'          => mapUserId($r['verantwortlicher'], $maps),
            'status'                          => $r['status'],
            'vorgangstyp'                     => $r['vorgangstyp'],
            'buchungstyp'                     => $r['buchungstyp'],
            'buchungsweg_kondition_id'        => $r['buchungsweg_id'],
            'linktext'                        => $r['linktext'],
            'brand_integration'               => $r['brand_integration'],
            'geplant_am'                      => $r['geplant_am'],
            'veroeffentlicht_am'              => $r['veroeffentlicht_am'],
            'veroeffentlichungs_url'          => $r['veroeffentlichungs_url'],
            'sonderstatus'                    => $r['sonderstatus'],
            'plan_a_massnahme_id'             => $r['plan_a_massnahme'],
            'asana_task_gid'                  => $r['asana_task_gid'],
            'asana_zuletzt_synchronisiert_am' => $r['asana_zuletzt_synchronisiert_am'],
            'asana_task_cache'                => $r['asana_task_cache'],
            'erstellt_am'                     => $r['erstellt_am'],
            'geloescht_am'                    => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_massnahmen', $rows);
}

function migriereVorschlagslisten(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM vorschlagslisten') as $r) {
        $customerId = mapCustomerId($r['kuerzel'], $maps);
        if ($customerId === null) {
            continue;
        }
        $rows[] = [
            'id'           => $r['id'],
            'customer_id'  => $customerId,
            'name'         => $r['name'],
            'zielzahl'     => $r['zielzahl'] !== null ? (int) $r['zielzahl'] : null,
            'status'       => $r['status'],
            'notiz'        => $r['notiz'],
            'erstellt_am'  => $r['erstellt_am'],
            'geloescht_am' => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_vorschlagslisten', $rows);
}

function migriereVorschlagslisteEintraege(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM vorschlagsliste_eintraege') as $r) {
        $rows[] = [
            'id'                              => $r['id'],
            'vorschlagsliste_id'              => $r['vorschlagsliste_id'],
            'domain_id'                       => $r['domain_id'],
            'notiz'                           => $r['notiz'],
            'vorgeschlagener_linktext'        => $r['vorgeschlagener_linktext'],
            'position'                        => (int) $r['position'],
            'massnahme_id'                    => $r['massnahme_id'],
            'status'                          => $r['status'],
            'kontakt_am'                      => $r['kontakt_am'],
            'letzte_rueckmeldung_am'          => $r['letzte_rueckmeldung_am'],
            'letzte_rueckmeldung_typ'         => $r['letzte_rueckmeldung_typ'],
            'naechste_aktion_am'              => $r['naechste_aktion_am'],
            'naechste_aktion_notiz'           => $r['naechste_aktion_notiz'],
            'preis_kunde'                     => $r['preis_kunde'],
            'ziel_url'                        => $r['ziel_url'],
            'artikelthema'                    => $r['artikelthema'],
            'asana_task_gid'                  => $r['asana_task_gid'],
            'asana_task_cache'                => $r['asana_task_cache'],
            'asana_zuletzt_synchronisiert_am' => $r['asana_zuletzt_synchronisiert_am'],
            'erstellt_am'                     => $r['erstellt_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_vorschlagsliste_eintraege', $rows);
}

function migriereAuslagen(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM auslagen') as $r) {
        $rows[] = [
            'id'                    => $r['id'],
            'massnahme_id'          => $r['massnahme_id'],
            'externe_kosten'        => $r['externe_kosten'],
            'rechnung_eingang'      => $r['rechnung_eingang'],
            'weiterverrechnet'      => $r['weiterverrechnet'],
            'marge'                 => $r['marge'],
            'marge_grund'           => $r['marge_grund'],
            'thoxan_rechnung_nr'    => $r['thoxan_rechnung_nr'],
            'thoxan_rechnung_datum' => $r['thoxan_rechnung_datum'],
            'sonderfall'            => $r['sonderfall'],
            'abgerechnet_fuer'      => $r['abgerechnet_fuer'],
            'erstellt_am'           => $r['erstellt_am'],
            'aktualisiert_am'       => $r['aktualisiert_am'],
            'geloescht_am'          => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_auslagen', $rows);
}

function migriereMonitoringChecks(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM monitoring_checks') as $r) {
        $rows[] = [
            'id'               => $r['id'],
            'massnahme_id'     => $r['massnahme_id'],
            'zeitpunkt'        => $r['zeitpunkt'],
            'http_status'      => $r['http_status'] !== null ? (int) $r['http_status'] : null,
            'link_vorhanden'   => (int) $r['link_vorhanden'],
            'link_typ'         => $r['link_typ'],
            'alert_ausgeloest' => (int) $r['alert_ausgeloest'],
            'fehlermeldung'    => $r['fehlermeldung'],
            'erstellt_am'      => $r['erstellt_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_monitoring_checks', $rows);
}

function migriereKommunikation(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM kommunikation') as $r) {
        $rows[] = [
            'id'                          => $r['id'],
            'typ'                         => $r['typ'],
            'zeitpunkt'                   => $r['zeitpunkt'],
            'inhalt'                      => $r['inhalt'],
            'anbieter_id'                 => $r['anbieter_id'],
            'kontakt_id'                  => $r['kontakt_id'],
            'vorschlagsliste_eintrag_id'  => $r['linkoption_eintrag_id'],
            'massnahme_id'                => $r['massnahme_id'],
            'user_id'                     => mapUserId($r['mitarbeiter_kuerzel'], $maps),
            'anhang_pfad'                 => $r['anhang_pfad'],
            'anhang_originalname'         => $r['anhang_originalname'],
            'anhang_mime'                 => $r['anhang_mime'],
            'anhang_groesse'              => $r['anhang_groesse'] !== null ? (int) $r['anhang_groesse'] : null,
            'mail_id_extern'              => $r['mail_id_extern'],
            'absender_mail'               => $r['absender_mail'],
            'empfaenger_mail'             => $r['empfaenger_mail'],
            'betreff'                     => $r['betreff'],
            'ki_extrakt'                  => $r['ki_extrakt'],
            'vorlagen_id'                 => $r['vorlagen_id'],
            'versendet_am'                => $r['versendet_am'],
            'status'                      => $r['status'],
            'erstellt_am'                 => $r['erstellt_am'],
            'geloescht_am'                => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_kommunikation', $rows);
}

function migriereVerlinkungen(PDO $sqlite, PDO $mysql, array $maps): int {
    $stmt = $sqlite->query('SELECT * FROM verlinkungen');
    $rows = [];
    $total = 0;
    foreach ($stmt as $r) {
        $customerId = mapCustomerId($r['kunde_kuerzel'], $maps);
        if ($customerId === null) {
            continue;
        }
        $rows[] = [
            'id'                      => $r['id'],
            'customer_id'             => $customerId,
            'verlinkende_url'         => $r['verlinkende_url'],
            'url_hash'                => $r['url_hash'],
            'domain'                  => $r['domain'],
            'linktext'                => $r['linktext'],
            'linkart'                 => $r['linkart'],
            'empfehlung'              => $r['empfehlung'],
            'status'                  => $r['status'],
            'bemerkung'               => $r['bemerkung'],
            'ist_neu'                 => (int) $r['ist_neu'],
            'imported_from'           => $r['imported_from'],
            'aufraeum_status'         => $r['aufraeum_status'],
            'ziel_url'                => $r['ziel_url'],
            'letzter_http_status'     => $r['letzter_http_status'] !== null ? (int) $r['letzter_http_status'] : null,
            'letzter_http_erreichbar' => $r['letzter_http_erreichbar'] !== null ? (int) $r['letzter_http_erreichbar'] : null,
            'linkziel_gefunden'       => $r['linkziel_gefunden'] !== null ? (int) $r['linkziel_gefunden'] : null,
            'letzter_check_am'        => $r['letzter_check_am'],
            'is_follow'               => $r['is_follow'] !== null ? (int) $r['is_follow'] : null,
            'erstellt_am'             => $r['erstellt_am'],
            'aktualisiert_am'         => $r['aktualisiert_am'],
            'geloescht_am'            => $r['geloescht_am'],
        ];
        if (count($rows) >= 1000) {
            $total += bulkInsert($mysql, 'lam_verlinkungen', $rows);
            $rows = [];
        }
    }
    $total += bulkInsert($mysql, 'lam_verlinkungen', $rows);
    return $total;
}

function migriereDomainWissen(PDO $sqlite, PDO $mysql, array $maps): int {
    $stmt = $sqlite->query('SELECT * FROM domain_wissen');
    $rows = [];
    $total = 0;
    foreach ($stmt as $r) {
        $rows[] = [
            'id'                       => (int) $r['id'],
            'domain'                   => $r['domain'],
            'linkart'                  => $r['linkart'],
            'reduktionsstrategie'      => $r['reduktionsstrategie'],
            'confidence'               => $r['confidence'],
            'anzahl_klassifikationen'  => (int) $r['anzahl_klassifikationen'],
            'letzter_customer_id'      => mapCustomerId($r['letzter_kunde_kuerzel'], $maps),
            'notiz'                    => $r['notiz'],
            'empfehlung_default'       => $r['empfehlung_default'],
            'erstellt_am'              => $r['erstellt_am'],
            'aktualisiert_am'          => $r['aktualisiert_am'],
        ];
        if (count($rows) >= 1000) {
            $total += bulkInsert($mysql, 'lam_domain_wissen', $rows);
            $rows = [];
        }
    }
    $total += bulkInsert($mysql, 'lam_domain_wissen', $rows);
    return $total;
}

function migriereLinkprofilSnapshots(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM linkprofil_snapshots') as $r) {
        $customerId = mapCustomerId($r['kunde_kuerzel'], $maps);
        if ($customerId === null) {
            continue;
        }
        $rows[] = [
            'id'                  => $r['id'],
            'customer_id'         => $customerId,
            'snapshot_datum'      => $r['snapshot_datum'],
            'anzahl_verlinkungen' => (int) $r['anzahl_verlinkungen'],
            'auswertung_json'     => $r['auswertung_json'],
            'notiz'               => $r['notiz'],
            'erstellt_am'         => $r['erstellt_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_linkprofil_snapshots', $rows);
}

function migriereLinkprofilSnapshotVerlinkungen(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM linkprofil_snapshot_verlinkungen') as $r) {
        $rows[] = [
            'id'                     => (int) $r['id'],
            'snapshot_id'            => $r['snapshot_id'],
            'verlinkung_id'          => $r['verlinkung_id'],
            'linkart_at_snapshot'    => $r['linkart_at_snapshot'],
            'empfehlung_at_snapshot' => $r['empfehlung_at_snapshot'],
            'status_at_snapshot'     => $r['status_at_snapshot'],
            'war_neu'                => (int) $r['war_neu'],
        ];
    }
    return bulkInsert($mysql, 'lam_linkprofil_snapshot_verlinkungen', $rows);
}

function migriereLinkprofilTags(PDO $sqlite, PDO $mysql, array $maps): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM linkprofil_tags') as $r) {
        $rows[] = [
            'id'           => (int) $r['id'],
            'name'         => $r['name'],
            'slug'         => $r['slug'],
            'customer_id'  => mapCustomerId($r['kunde_kuerzel'], $maps),
            'erstellt_am'  => $r['erstellt_am'],
            'geloescht_am' => $r['geloescht_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_linkprofil_tags', $rows);
}

function migriereLinkprofilTagVerlinkung(PDO $sqlite, PDO $mysql): int {
    $rows = [];
    foreach ($sqlite->query('SELECT * FROM linkprofil_tag_verlinkung') as $r) {
        $rows[] = [
            'id'                => (int) $r['id'],
            'linkprofil_tag_id' => (int) $r['linkprofil_tag_id'],
            'verlinkung_id'     => $r['verlinkung_id'],
            'erstellt_am'       => $r['erstellt_am'],
        ];
    }
    return bulkInsert($mysql, 'lam_linkprofil_tag_verlinkung', $rows);
}

function migriereAuditLogs(PDO $sqlite, PDO $mysql, array $maps): int {
    $stmt = $sqlite->query('SELECT * FROM audit_logs ORDER BY id');
    $rows = [];
    $total = 0;
    foreach ($stmt as $r) {
        $rows[] = [
            'id'                => (int) $r['id'],
            'user_id'           => mapUserId($r['mitarbeiter_kuerzel'], $maps),
            'aktion'            => $r['aktion'],
            'entity_typ'        => $r['entity_typ'],
            'entity_id'         => $r['entity_id'],
            'payload'           => $r['payload'],
            'ist_bulk'          => (int) $r['ist_bulk'],
            'anzahl_betroffen'  => $r['anzahl_betroffen'] !== null ? (int) $r['anzahl_betroffen'] : null,
            'zeitpunkt'         => $r['zeitpunkt'],
        ];
        if (count($rows) >= 1000) {
            $total += bulkInsert($mysql, 'lam_audit_logs', $rows);
            $rows = [];
        }
    }
    $total += bulkInsert($mysql, 'lam_audit_logs', $rows);
    return $total;
}

// -----------------------------------------------------------------------------
// Hauptablauf
// -----------------------------------------------------------------------------

logSection('LAMS ETL — Start');
$start = microtime(true);

$sqlite = connectSqlite();
$mysql  = connectMysql();
$maps   = ladeMappings($mysql);
logInfo(sprintf(
    'Mappings geladen: %d Kunden, %d User',
    count($maps['customer']), count($maps['user'])
));

// Topologische Reihenfolge: zuerst Tabellen ohne FK-Abhaengigkeiten zu anderen
// lam_-Tabellen, dann die abhaengigen.
$schritte = [
    'lam_anbieter'                          => fn() => migriereAnbieter($sqlite, $mysql),
    'lam_import_batches'                    => fn() => migriereImportBatches($sqlite, $mysql, $maps),
    'lam_tags'                              => fn() => migriereTags($sqlite, $mysql),
    'lam_domains'                           => fn() => migriereDomains($sqlite, $mysql, $maps),
    'lam_kontakte'                          => fn() => migriereKontakte($sqlite, $mysql, $maps),
    'lam_konditionen'                       => fn() => migriereKonditionen($sqlite, $mysql, $maps),
    'lam_domain_tag'                        => fn() => migriereDomainTag($sqlite, $mysql),
    'lam_domain_anbieter'                   => fn() => migriereDomainAnbieter($sqlite, $mysql),
    'lam_domain_links'                      => fn() => migriereDomainLinks($sqlite, $mysql),
    'lam_kennzahl_snapshots'                => fn() => migriereKennzahlSnapshots($sqlite, $mysql),
    'lam_kunden_config'                     => fn() => migriereKundenConfig($sqlite, $mysql, $maps),
    'lam_customer_tags'                     => fn() => migriereCustomerTags($sqlite, $mysql, $maps),
    'lam_linkziele'                         => fn() => migriereLinkziele($sqlite, $mysql, $maps),
    'lam_domain_customer'                   => fn() => migriereDomainCustomer($sqlite, $mysql, $maps),
    'lam_massnahmen'                        => fn() => migriereMassnahmen($sqlite, $mysql, $maps),
    'lam_vorschlagslisten'                  => fn() => migriereVorschlagslisten($sqlite, $mysql, $maps),
    'lam_vorschlagsliste_eintraege'         => fn() => migriereVorschlagslisteEintraege($sqlite, $mysql),
    'lam_auslagen'                          => fn() => migriereAuslagen($sqlite, $mysql),
    'lam_monitoring_checks'                 => fn() => migriereMonitoringChecks($sqlite, $mysql),
    'lam_kommunikation'                     => fn() => migriereKommunikation($sqlite, $mysql, $maps),
    'lam_verlinkungen'                      => fn() => migriereVerlinkungen($sqlite, $mysql, $maps),
    'lam_domain_wissen'                     => fn() => migriereDomainWissen($sqlite, $mysql, $maps),
    'lam_linkprofil_snapshots'              => fn() => migriereLinkprofilSnapshots($sqlite, $mysql, $maps),
    'lam_linkprofil_snapshot_verlinkungen'  => fn() => migriereLinkprofilSnapshotVerlinkungen($sqlite, $mysql),
    'lam_linkprofil_tags'                   => fn() => migriereLinkprofilTags($sqlite, $mysql, $maps),
    'lam_linkprofil_tag_verlinkung'         => fn() => migriereLinkprofilTagVerlinkung($sqlite, $mysql),
    'lam_audit_logs'                        => fn() => migriereAuditLogs($sqlite, $mysql, $maps),
];

// Vor dem Lauf alles leeren (umgekehrte Reihenfolge fuer FK-Constraints)
logInfo('Leere Ziel-Tabellen ...');
$mysql->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (array_reverse(array_keys($schritte)) as $tbl) {
    truncate($mysql, $tbl);
}
$mysql->exec('SET FOREIGN_KEY_CHECKS = 1');

$summary = [];
foreach ($schritte as $tbl => $fn) {
    $t0 = microtime(true);
    try {
        $n = $fn();
        $dt = round((microtime(true) - $t0) * 1000);
        logInfo(sprintf('  %-40s %6d Zeilen   (%5d ms)', $tbl, $n, $dt));
        $summary[$tbl] = $n;
    } catch (Throwable $e) {
        logInfo(sprintf('  %-40s FEHLER: %s', $tbl, $e->getMessage()));
        $summary[$tbl] = 'FEHLER';
        throw $e;
    }
}

$dauer = round(microtime(true) - $start, 1);
logSection('Fertig in ' . $dauer . ' s');
echo PHP_EOL;
foreach ($summary as $tbl => $n) {
    printf("  %-40s %s\n", $tbl, is_int($n) ? number_format($n, 0, ',', '.') : $n);
}
echo PHP_EOL;
