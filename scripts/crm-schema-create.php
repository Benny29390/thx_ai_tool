<?php
/**
 * CRM Phase 1 — Schema-Migration.
 *
 * Erzeugt alle Tabellen + Seed-Werte fuers CRM-Modul.
 * Wiederholbar (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
 *
 * Usage: php /var/www/scripts/crm-schema-create.php
 */

define('BASE_PATH', __DIR__ . '/..');
define('CONFIG_PATH', BASE_PATH . '/config');
require BASE_PATH . '/core/Database.php';

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$tables = [];

// ─── 1. crm_branchen (kuratiertes Vokabular, hybrid) ─────────────────────
$tables['crm_branchen'] = "
CREATE TABLE IF NOT EXISTS crm_branchen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    anzahl_firmen INT UNSIGNED DEFAULT 0,
    sort_order INT DEFAULT 0,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 2. crm_firmen ───────────────────────────────────────────────────────
$tables['crm_firmen'] = "
CREATE TABLE IF NOT EXISTS crm_firmen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firmenname VARCHAR(255) NOT NULL,
    website VARCHAR(255) NULL,
    branche VARCHAR(120) NULL,
    parent_firma_id BIGINT UNSIGNED NULL,
    firmen_typ VARCHAR(80) NULL,
    bewertung TINYINT NULL,
    beschaeftigte INT NULL,
    jahreseinnahmen DECIMAL(15,2) NULL,
    telefon VARCHAR(80) NULL,
    fax VARCHAR(80) NULL,
    email VARCHAR(255) NULL,
    notizen TEXT NULL,
    -- Legacy Zoho
    legacy_zoho_id VARCHAR(40) NULL,
    legacy_zoho_json JSON NULL,
    -- System
    erstellt_durch INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geaendert_durch INT NULL,
    geaendert_am DATETIME NULL,
    geloescht_am DATETIME NULL,
    geloescht_durch INT NULL,
    KEY idx_name (firmenname),
    KEY idx_branche (branche),
    KEY idx_parent (parent_firma_id),
    KEY idx_legacy_zoho (legacy_zoho_id),
    KEY idx_geloescht (geloescht_am),
    FULLTEXT KEY ft_firmen (firmenname, branche, website)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 3. crm_kontakte (Hauptentitaet) ─────────────────────────────────────
$tables['crm_kontakte'] = "
CREATE TABLE IF NOT EXISTS crm_kontakte (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Identitaet
    anrede VARCHAR(20) NULL,
    titel VARCHAR(40) NULL,
    vorname VARCHAR(120) NULL,
    nachname VARCHAR(160) NOT NULL,
    funktion VARCHAR(200) NULL,
    abteilung VARCHAR(120) NULL,
    geburtsdatum DATE NULL,
    -- Kommunikation
    email_primaer VARCHAR(255) NOT NULL,
    email_zweit   VARCHAR(255) NULL,
    telefon VARCHAR(80) NULL,
    telefon_alt VARCHAR(80) NULL,
    mobil VARCHAR(80) NULL,
    fax VARCHAR(80) NULL,
    website VARCHAR(255) NULL,
    -- Firma (optional)
    firma_id BIGINT UNSIGNED NULL,
    -- Profil
    interessen TEXT NULL,
    merkmale TEXT NULL,
    beschreibung TEXT NULL,
    bevorzugtes_thema VARCHAR(255) NULL,
    -- Marketing-Status (ENUM bewusst weit, deckt Zoho-Realitaet)
    kontakt_status ENUM('lead','interessent','kunde','ehemaliger_kunde','partner','wunschkunde','dienstleister','sonstiges') NULL,
    lead_quelle VARCHAR(120) NULL,
    opt_in_status ENUM('pending','double_opted_in','single_opted_in','unsubscribed','hard_bounce','invalid') NULL,
    thx_score INT NULL,
    -- Opportunity (minimal)
    asana_task_gid VARCHAR(40) NULL,
    deal_wert DECIMAL(10,2) NULL,
    deal_stufe VARCHAR(80) NULL,
    -- Avatar
    foto_path VARCHAR(255) NULL,
    -- Owner
    kontakt_besitzer_user_id INT NULL,
    -- Brevo-Sync
    brevo_id VARCHAR(40) NULL,
    brevo_zuletzt_gepusht_am DATETIME NULL,
    -- Legacy Zoho
    legacy_zoho_id VARCHAR(40) NULL,
    legacy_zoho_json JSON NULL,
    -- System
    erstellt_durch INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geaendert_durch INT NULL,
    geaendert_am DATETIME NULL,
    geloescht_am DATETIME NULL,
    geloescht_durch INT NULL,
    -- Indizes
    UNIQUE KEY uniq_email (email_primaer),
    KEY idx_firma (firma_id),
    KEY idx_status (kontakt_status),
    KEY idx_opt_in (opt_in_status),
    KEY idx_brevo (brevo_id),
    KEY idx_legacy_zoho (legacy_zoho_id),
    KEY idx_geloescht (geloescht_am),
    KEY idx_besitzer (kontakt_besitzer_user_id),
    FULLTEXT KEY ft_search (vorname, nachname, email_primaer, funktion, beschreibung),
    CONSTRAINT fk_kontakt_firma FOREIGN KEY (firma_id) REFERENCES crm_firmen(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 4. crm_adressen ─────────────────────────────────────────────────────
$tables['crm_adressen'] = "
CREATE TABLE IF NOT EXISTS crm_adressen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NULL,
    firma_id BIGINT UNSIGNED NULL,
    typ ENUM('geschaeftlich','privat','rechnung','versand','sonstige') NOT NULL,
    ist_primaer TINYINT(1) DEFAULT 0,
    strasse VARCHAR(255) NULL,
    plz VARCHAR(20) NULL,
    stadt VARCHAR(120) NULL,
    bundesland VARCHAR(120) NULL,
    land VARCHAR(80) NULL DEFAULT 'Deutschland',
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt (kontakt_id),
    KEY idx_firma (firma_id),
    CONSTRAINT fk_adr_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    CONSTRAINT fk_adr_firma FOREIGN KEY (firma_id) REFERENCES crm_firmen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 5. crm_tags (Vokabular) ─────────────────────────────────────────────
$tables['crm_tags'] = "
CREATE TABLE IF NOT EXISTS crm_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(80) NOT NULL UNIQUE,
    farbe VARCHAR(7) NULL,
    beschreibung VARCHAR(255) NULL,
    anzahl_kontakte INT UNSIGNED DEFAULT 0,
    erstellt_durch INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 6. crm_kontakt_tags (n:m) ───────────────────────────────────────────
$tables['crm_kontakt_tags'] = "
CREATE TABLE IF NOT EXISTS crm_kontakt_tags (
    kontakt_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    vergeben_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    vergeben_durch INT NULL,
    PRIMARY KEY (kontakt_id, tag_id),
    KEY idx_tag (tag_id),
    CONSTRAINT fk_kt_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    CONSTRAINT fk_kt_tag FOREIGN KEY (tag_id) REFERENCES crm_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 7. crm_listen (Marketing-Listen, Brevo-1:1) ─────────────────────────
$tables['crm_listen'] = "
CREATE TABLE IF NOT EXISTS crm_listen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    brevo_list_id INT NULL UNIQUE,
    beschreibung TEXT NULL,
    anzahl_aktive INT UNSIGNED DEFAULT 0,
    archiviert TINYINT(1) DEFAULT 0,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 8. crm_kontakt_listen (n:m) ─────────────────────────────────────────
$tables['crm_kontakt_listen'] = "
CREATE TABLE IF NOT EXISTS crm_kontakt_listen (
    kontakt_id BIGINT UNSIGNED NOT NULL,
    listen_id INT UNSIGNED NOT NULL,
    status ENUM('aktiv','inaktiv','pending','unsubscribed') DEFAULT 'aktiv',
    beigetreten_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    verlassen_am DATETIME NULL,
    PRIMARY KEY (kontakt_id, listen_id),
    KEY idx_listen (listen_id, status),
    CONSTRAINT fk_kl_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    CONSTRAINT fk_kl_liste FOREIGN KEY (listen_id) REFERENCES crm_listen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 9. crm_segmente (gespeicherte Filter) ───────────────────────────────
$tables['crm_segmente'] = "
CREATE TABLE IF NOT EXISTS crm_segmente (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    beschreibung TEXT NULL,
    filter_json JSON NOT NULL,
    erstellt_durch INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geaendert_am DATETIME NULL,
    sichtbarkeit ENUM('privat','team','global') DEFAULT 'privat',
    KEY idx_sichtbarkeit (sichtbarkeit),
    KEY idx_erstellt_von (erstellt_durch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 10. crm_social_links (Key-Value pro Kontakt) ────────────────────────
$tables['crm_social_links'] = "
CREATE TABLE IF NOT EXISTS crm_social_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    plattform ENUM('linkedin','xing','facebook','instagram','twitter_x','youtube','tiktok','website','sonstiges') NOT NULL,
    url VARCHAR(500) NOT NULL,
    UNIQUE KEY uniq_kontakt_plattform (kontakt_id, plattform),
    CONSTRAINT fk_social_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 11. crm_lead_magneten ───────────────────────────────────────────────
$tables['crm_lead_magneten'] = "
CREATE TABLE IF NOT EXISTS crm_lead_magneten (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    beschreibung TEXT NULL,
    aktiv TINYINT(1) DEFAULT 1,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 12. crm_lead_magnet_events ──────────────────────────────────────────
$tables['crm_lead_magnet_events'] = "
CREATE TABLE IF NOT EXISTS crm_lead_magnet_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    lead_magnet_id INT UNSIGNED NOT NULL,
    utm_source VARCHAR(120) NULL,
    utm_medium VARCHAR(120) NULL,
    utm_campaign VARCHAR(120) NULL,
    utm_content VARCHAR(120) NULL,
    utm_term VARCHAR(120) NULL,
    referrer VARCHAR(500) NULL,
    eingegangen_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt (kontakt_id),
    KEY idx_lead_magnet (lead_magnet_id),
    CONSTRAINT fk_lme_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    CONSTRAINT fk_lme_lm FOREIGN KEY (lead_magnet_id) REFERENCES crm_lead_magneten(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 13. crm_aktivitaeten (Zeitlinie, append-only) ───────────────────────
$tables['crm_aktivitaeten'] = "
CREATE TABLE IF NOT EXISTS crm_aktivitaeten (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    typ ENUM(
        'kontakt_angelegt','kontakt_geaendert','tag_hinzugefuegt','tag_entfernt',
        'liste_beigetreten','liste_verlassen','opt_in_erfasst','doi_bestaetigt',
        'lead_magnet','mail_open','mail_click','mail_bounce','mail_unsubscribe',
        'notiz','telefonat','meeting','sonstiges'
    ) NOT NULL,
    titel VARCHAR(255) NULL,
    inhalt TEXT NULL,
    metadata_json JSON NULL,
    quelle ENUM('manuell','migration','brevo_webhook','brevo_sync','system','ki_vorschlag','ki_uebernommen') DEFAULT 'manuell',
    actor_user_id INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt_zeit (kontakt_id, erstellt_am DESC),
    KEY idx_typ (typ),
    CONSTRAINT fk_aktiv_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 14. crm_opt_in_events (DOI-Belege) ──────────────────────────────────
$tables['crm_opt_in_events'] = "
CREATE TABLE IF NOT EXISTS crm_opt_in_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    typ ENUM('erfasst','doi_mail_gesendet','doi_bestaetigt','unsubscribe','revoke') NOT NULL,
    doi_token VARCHAR(64) NULL,
    quelle VARCHAR(120) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    text_einwilligung TEXT NULL,
    abgelaufen_am DATETIME NULL,
    erfolgt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt (kontakt_id),
    KEY idx_token (doi_token),
    CONSTRAINT fk_doi_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 15. crm_brevo_events (alle Events) ──────────────────────────────────
$tables['crm_brevo_events'] = "
CREATE TABLE IF NOT EXISTS crm_brevo_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NULL,
    brevo_email VARCHAR(255) NULL,
    event_typ ENUM(
        'sent','delivered','open','click','soft_bounce','hard_bounce',
        'invalid','spam','blocked','unsubscribed','deferred','complaint'
    ) NOT NULL,
    campaign_id INT NULL,
    campaign_name VARCHAR(200) NULL,
    link_url TEXT NULL,
    user_agent VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    raw_json JSON NULL,
    empfangen_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt_zeit (kontakt_id, empfangen_am DESC),
    KEY idx_email (brevo_email),
    KEY idx_typ (event_typ),
    KEY idx_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 16. crm_loesch_events (Tombstones) ──────────────────────────────────
$tables['crm_loesch_events'] = "
CREATE TABLE IF NOT EXISTS crm_loesch_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_typ ENUM('kontakt','firma') NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    geloescht_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geloescht_durch INT NULL,
    art ENUM('soft','hard','dsgvo_anonymisiert') NOT NULL,
    grund VARCHAR(255) NULL,
    KEY idx_entity (entity_typ, entity_id),
    KEY idx_zeit (geloescht_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 17. crm_kunden_zuordnung (Kontakt → Customer, Rechte) ───────────────
$tables['crm_kunden_zuordnung'] = "
CREATE TABLE IF NOT EXISTS crm_kunden_zuordnung (
    kontakt_id BIGINT UNSIGNED NOT NULL,
    customer_id INT NOT NULL,
    rolle VARCHAR(80) NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (kontakt_id, customer_id),
    KEY idx_customer (customer_id),
    CONSTRAINT fk_kz_kontakt FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    CONSTRAINT fk_kz_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 18. crm_tag_sichtbarkeit (Tag-Whitelisting) ─────────────────────────
$tables['crm_tag_sichtbarkeit'] = "
CREATE TABLE IF NOT EXISTS crm_tag_sichtbarkeit (
    tag_id INT UNSIGNED NOT NULL PRIMARY KEY,
    fuer_alle_crm_user TINYINT(1) DEFAULT 0,
    beschreibung VARCHAR(255) NULL,
    CONSTRAINT fk_ts_tag FOREIGN KEY (tag_id) REFERENCES crm_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 19. crm_migration_audit ─────────────────────────────────────────────
$tables['crm_migration_audit'] = "
CREATE TABLE IF NOT EXISTS crm_migration_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quelle ENUM('brevo','zoho','manuell') NOT NULL,
    quelle_id VARCHAR(80) NULL,
    aktion ENUM('insert','update','merge','skip','error') NOT NULL,
    kontakt_id BIGINT UNSIGNED NULL,
    details_json JSON NULL,
    erfolgt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_quelle (quelle, quelle_id),
    KEY idx_kontakt (kontakt_id),
    KEY idx_zeit (erfolgt_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 20. crm_brevo_sync_log ──────────────────────────────────────────────
$tables['crm_brevo_sync_log'] = "
CREATE TABLE IF NOT EXISTS crm_brevo_sync_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    richtung ENUM('crm_to_brevo','brevo_to_crm') NOT NULL,
    kontakt_id BIGINT UNSIGNED NULL,
    aktion VARCHAR(80) NOT NULL,
    status ENUM('ok','retry','error') NOT NULL,
    fehler_text TEXT NULL,
    request_json JSON NULL,
    response_json JSON NULL,
    erfolgt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt_zeit (kontakt_id, erfolgt_am DESC),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── 21. crm_migrations_runs (fuer wiederholbare Imports) ────────────────
$tables['crm_migrations_runs'] = "
CREATE TABLE IF NOT EXISTS crm_migrations_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quelle ENUM('brevo','zoho') NOT NULL,
    modus ENUM('full','delta') NOT NULL,
    gestartet_durch INT NULL,
    gestartet_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    beendet_am DATETIME NULL,
    status ENUM('running','ok','error','abgebrochen') DEFAULT 'running',
    anzahl_geprueft INT UNSIGNED DEFAULT 0,
    anzahl_insert INT UNSIGNED DEFAULT 0,
    anzahl_update INT UNSIGNED DEFAULT 0,
    anzahl_skip INT UNSIGNED DEFAULT 0,
    anzahl_error INT UNSIGNED DEFAULT 0,
    fehler_text TEXT NULL,
    KEY idx_quelle_zeit (quelle, gestartet_am DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ═══════════════════════════════════════════════════════════════════════════
// Ausfuehrung
// ═══════════════════════════════════════════════════════════════════════════

echo "CRM Phase 1 — Schema-Migration\n";
echo str_repeat("=", 60) . "\n\n";

$ok = 0; $skip = 0; $err = 0;
foreach ($tables as $name => $ddl) {
    try {
        // Pruefe ob existiert
        $exists = $db->queryValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$name]);
        if ($exists > 0) {
            echo "  ⏭  $name (existiert bereits)\n";
            $skip++;
            continue;
        }
        $db->execute($ddl);
        echo "  ✓  $name\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "  ✗  $name — " . $e->getMessage() . "\n";
        $err++;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Tabellen: $ok angelegt, $skip uebersprungen, $err Fehler\n";

// ═══════════════════════════════════════════════════════════════════════════
// Seed: kontrollierte Tag- und Branchen-Werte (kommen mit Brevo-Import auch nach)
// ═══════════════════════════════════════════════════════════════════════════

echo "\nSeed-Daten:\n";

$standardBranchen = [
    'IT / Software', 'Marketing / Werbung', 'Beratung', 'Bildung / Schulung',
    'E-Commerce', 'Handel', 'Handwerk', 'Industrie / Fertigung',
    'Medien', 'Verlag', 'Gesundheit', 'Immobilien', 'Finanzen',
    'Verein / Stiftung', 'Behörde', 'Sonstiges'
];
foreach ($standardBranchen as $i => $b) {
    try {
        $db->execute("INSERT IGNORE INTO crm_branchen (name, sort_order) VALUES (?, ?)", [$b, $i]);
    } catch (\Throwable $e) { /* ignore */ }
}
$branchenCount = $db->queryValue("SELECT COUNT(*) FROM crm_branchen");
echo "  ✓  crm_branchen — $branchenCount Eintraege\n";

// Standard-Tag-Set (typische Use-Cases)
$standardTags = [
    ['Weihnachtskarte', '#ef4444'],
    ['Newsletter', '#0369a1'],
    ['Akquise-Mastermind', '#a855f7'],
    ['Wunschkunde', '#16a34a'],
    ['Ehemaliger Kunde', '#94a3b8'],
    ['VIP', '#f59e0b'],
    ['Schwarze Liste', '#dc2626'],
];
foreach ($standardTags as $t) {
    $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($t[0]));
    $slug = trim($slug, '-');
    try {
        $db->execute("INSERT IGNORE INTO crm_tags (name, slug, farbe) VALUES (?, ?, ?)", [$t[0], $slug, $t[1]]);
    } catch (\Throwable $e) { /* ignore */ }
}
$tagsCount = $db->queryValue("SELECT COUNT(*) FROM crm_tags");
echo "  ✓  crm_tags — $tagsCount Eintraege\n";

echo "\nFertig.\n";
