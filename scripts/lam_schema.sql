-- =============================================================================
-- LAMS-Schema fuer KI-Tool (Linkaufbau-Management-System)
-- =============================================================================
-- Portierung des Laravel-Prototyps /var/www/lams_modul_alt/lam-prototyp/ in das
-- Vanilla-PHP-KI-Tool unter /var/www/. Alle Tabellen mit Prefix `lam_`.
--
-- Konventionen:
--   * ULIDs bleiben als VARCHAR(26) erhalten (keine ID-Remapping)
--   * LAMS-Spaltennamen (deutsch, `erstellt_am`/`geloescht_am`) beibehalten
--   * `customer_id INT FK customers(id)` ersetzt LAMS-`kuerzel`-FKs
--   * `user_id INT FK users(id)` ersetzt LAMS-`mitarbeiter_kuerzel`-FKs
--   * Charset utf8mb4, Collation utf8mb4_unicode_ci, Engine InnoDB
--   * JSON-Spalten nutzen MySQL-Native-JSON-Typ
--
-- Reihenfolge: Stammdaten -> Pool -> Kunden-Bezug -> Auslagen/Monitoring
--   -> Korrespondenz -> Linkprofil -> Import/Audit
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. STAMMDATEN
-- =============================================================================

-- LAM-Tags (themenspezifisch: Energie, Familie, Hosting, ...). Eigenstaendig,
-- nicht mit rule_categories des KI-Tools vermischen.
CREATE TABLE IF NOT EXISTS `lam_tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(150) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `beschreibung` TEXT NULL,
  `verwendungs_zahl` INT UNSIGNED NOT NULL DEFAULT 0,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_tags_slug_unique` (`slug`),
  KEY `lam_tags_geloescht_am_idx` (`geloescht_am`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. POOL (Anbieter, Domains, Kontakte, Konditionen)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `lam_anbieter` (
  `id` VARCHAR(26) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `firma` VARCHAR(255) NULL,
  `beziehungsstatus` VARCHAR(40) NOT NULL DEFAULT 'neu',
  `ist_betreiber` TINYINT(1) NOT NULL DEFAULT 1,
  `ist_vermittler` TINYINT(1) NOT NULL DEFAULT 0,
  `notizen` TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_anbieter_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_anbieter_name_idx` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_import_batches` (
  `id` VARCHAR(26) NOT NULL,
  `dateiname` VARCHAR(255) NOT NULL,
  `datei_datum` DATE NULL,
  `importiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `importiert_von_user_id` INT NULL,
  `anzahl_neu` INT UNSIGNED NOT NULL DEFAULT 0,
  `anzahl_dublette` INT UNSIGNED NOT NULL DEFAULT 0,
  `anzahl_fehler` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(40) NOT NULL DEFAULT 'erfolgreich',
  `notizen` TEXT NULL,
  `mapping` JSON NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_import_batches_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_import_batches_importiert_am_idx` (`importiert_am`),
  CONSTRAINT `lam_import_batches_user_fk` FOREIGN KEY (`importiert_von_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_domains` (
  `id` VARCHAR(26) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `anbieter_id` VARCHAR(26) NULL,
  `quelle_recherche` VARCHAR(255) NULL,
  `buchbar_via` VARCHAR(40) NOT NULL DEFAULT 'unbekannt',
  `disqualifiziert` TINYINT(1) NOT NULL DEFAULT 0,
  `disqualifikations_grund` VARCHAR(255) NULL,
  `notizen` TEXT NULL,
  `wartezeit_bis` DATE NULL,
  `verifikation_status` VARCHAR(40) NOT NULL DEFAULT 'neu',
  `verifiziert_am` DATE NULL,
  `verifiziert_von_user_id` INT NULL,
  `letzter_check_am` DATETIME NULL,
  `import_batch_id` VARCHAR(26) NULL,
  `quelle_anhang` TEXT NULL,
  `sistrix_sichtbar_seit` DATE NULL,
  `impressum_url` VARCHAR(500) NULL,
  `weitere_quellen_urls` TEXT NULL,
  `ki_kurzbeschreibung` TEXT NULL,
  `ki_kurzbeschreibung_generiert_am` DATETIME NULL,
  `letzter_http_status` INT NULL,
  `letzter_http_erreichbar` TINYINT(1) NULL,
  `linkart` VARCHAR(40) NULL,
  `herkunft` VARCHAR(40) NULL,
  `herkunft_customer_id` INT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_domains_url_unique` (`url`),
  KEY `lam_domains_anbieter_id_idx` (`anbieter_id`),
  KEY `lam_domains_import_batch_id_idx` (`import_batch_id`),
  KEY `lam_domains_letzter_check_am_idx` (`letzter_check_am`),
  KEY `lam_domains_letzter_http_erreichbar_idx` (`letzter_http_erreichbar`),
  KEY `lam_domains_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_domains_sistrix_sichtbar_seit_idx` (`sistrix_sichtbar_seit`),
  KEY `lam_domains_verifikation_status_idx` (`verifikation_status`),
  KEY `lam_domains_linkart_idx` (`linkart`),
  KEY `lam_domains_herkunft_idx` (`herkunft`),
  CONSTRAINT `lam_domains_anbieter_fk` FOREIGN KEY (`anbieter_id`)
    REFERENCES `lam_anbieter` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_domains_user_fk` FOREIGN KEY (`verifiziert_von_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_domains_import_batch_fk` FOREIGN KEY (`import_batch_id`)
    REFERENCES `lam_import_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_domains_customer_fk` FOREIGN KEY (`herkunft_customer_id`)
    REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_kontakte` (
  `id` VARCHAR(26) NOT NULL,
  `anbieter_id` VARCHAR(26) NULL,
  `vorname` VARCHAR(100) NULL,
  `nachname` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NULL,
  `telefon` VARCHAR(80) NULL,
  `rolle` VARCHAR(100) NULL,
  `verifikation_status` VARCHAR(40) NOT NULL DEFAULT 'neu',
  `verifiziert_am` DATE NULL,
  `verifiziert_von_user_id` INT NULL,
  `import_batch_id` VARCHAR(26) NULL,
  `quelle_anhang` TEXT NULL,
  `prioritaet` INT NOT NULL DEFAULT 1,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_kontakte_anbieter_id_idx` (`anbieter_id`),
  KEY `lam_kontakte_anbieter_id_prioritaet_idx` (`anbieter_id`, `prioritaet`),
  KEY `lam_kontakte_import_batch_id_idx` (`import_batch_id`),
  KEY `lam_kontakte_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_kontakte_verifikation_status_idx` (`verifikation_status`),
  CONSTRAINT `lam_kontakte_anbieter_fk` FOREIGN KEY (`anbieter_id`)
    REFERENCES `lam_anbieter` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_kontakte_user_fk` FOREIGN KEY (`verifiziert_von_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_kontakte_import_batch_fk` FOREIGN KEY (`import_batch_id`)
    REFERENCES `lam_import_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_konditionen` (
  `id` VARCHAR(26) NOT NULL,
  `domain_id` VARCHAR(26) NOT NULL,
  `buchungstyp` VARCHAR(60) NOT NULL,
  `preis` DECIMAL(10,2) NOT NULL,
  `laufzeit_monate` INT NOT NULL DEFAULT 0,
  `gekennzeichnet` TINYINT(1) NOT NULL DEFAULT 0,
  `link_typ` VARCHAR(40) NOT NULL DEFAULT 'follow',
  `inkl_text` TINYINT(1) NOT NULL DEFAULT 0,
  `wortzahl_min` INT NULL,
  `themenausschluss` VARCHAR(255) NULL,
  `gueltig_ab` DATE NULL,
  `verifikation_status` VARCHAR(40) NOT NULL DEFAULT 'neu',
  `verifiziert_am` DATE NULL,
  `verifiziert_von_user_id` INT NULL,
  `import_batch_id` VARCHAR(26) NULL,
  `quelle_anhang` TEXT NULL,
  `via_anbieter_id` VARCHAR(26) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_konditionen_domain_id_idx` (`domain_id`),
  KEY `lam_konditionen_domain_id_geloescht_am_idx` (`domain_id`, `geloescht_am`),
  KEY `lam_konditionen_import_batch_id_idx` (`import_batch_id`),
  KEY `lam_konditionen_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_konditionen_verifikation_status_idx` (`verifikation_status`),
  KEY `lam_konditionen_via_anbieter_id_idx` (`via_anbieter_id`),
  CONSTRAINT `lam_konditionen_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_konditionen_via_anbieter_fk` FOREIGN KEY (`via_anbieter_id`)
    REFERENCES `lam_anbieter` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_konditionen_user_fk` FOREIGN KEY (`verifiziert_von_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_konditionen_import_batch_fk` FOREIGN KEY (`import_batch_id`)
    REFERENCES `lam_import_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot Domain <-> Tag (mit primaer-Flag)
CREATE TABLE IF NOT EXISTS `lam_domain_tag` (
  `domain_id` VARCHAR(26) NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  `primaer` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`domain_id`, `tag_id`),
  KEY `lam_domain_tag_tag_id_idx` (`tag_id`),
  CONSTRAINT `lam_domain_tag_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_domain_tag_tag_fk` FOREIGN KEY (`tag_id`)
    REFERENCES `lam_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot Domain <-> Anbieter (Mehrfach-Rollen-Zuordnung)
CREATE TABLE IF NOT EXISTS `lam_domain_anbieter` (
  `id` VARCHAR(26) NOT NULL,
  `domain_id` VARCHAR(26) NOT NULL,
  `anbieter_id` VARCHAR(26) NOT NULL,
  `rolle` VARCHAR(40) NOT NULL DEFAULT 'betreiber',
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_domain_anbieter_unique` (`domain_id`, `anbieter_id`, `rolle`),
  KEY `lam_domain_anbieter_anbieter_id_rolle_idx` (`anbieter_id`, `rolle`),
  CONSTRAINT `lam_domain_anbieter_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_domain_anbieter_anbieter_fk` FOREIGN KEY (`anbieter_id`)
    REFERENCES `lam_anbieter` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Externe Links pro Domain (Beispielartikel, Mediadaten, Preisliste, ...)
CREATE TABLE IF NOT EXISTS `lam_domain_links` (
  `id` VARCHAR(26) NOT NULL,
  `domain_id` VARCHAR(26) NOT NULL,
  `typ` VARCHAR(40) NOT NULL,
  `label` VARCHAR(255) NULL,
  `url` VARCHAR(500) NOT NULL,
  `position` INT NOT NULL DEFAULT 0,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_domain_links_domain_id_typ_idx` (`domain_id`, `typ`),
  CONSTRAINT `lam_domain_links_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sistrix-Kennzahl-Snapshots pro Domain (SI, DP, Domain-Alter)
CREATE TABLE IF NOT EXISTS `lam_kennzahl_snapshots` (
  `id` VARCHAR(26) NOT NULL,
  `domain_id` VARCHAR(26) NOT NULL,
  `erfasst_am` DATE NOT NULL,
  `si` DECIMAL(10,4) NULL,
  `dp` INT NULL,
  `domain_alter` INT NULL,
  `quelle` VARCHAR(40) NOT NULL DEFAULT 'sistrix_api',
  `roh` JSON NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_kennzahl_snapshots_unique`
    (`domain_id`, `erfasst_am`, `quelle`),
  KEY `lam_kennzahl_snapshots_domain_id_erfasst_am_idx`
    (`domain_id`, `erfasst_am`),
  CONSTRAINT `lam_kennzahl_snapshots_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. KUNDEN-BEZUG (Linkziele, Maszrahmen, Vorschlagslisten)
--    customers.id (KI-Tool) ersetzt LAMS-`kuerzel`
-- =============================================================================

-- Kunden-spezifische Konfiguration zusaetzlich zum KI-Tool-customers-Eintrag
-- (LAMS-only-Felder, die nicht in customers gehoeren)
CREATE TABLE IF NOT EXISTS `lam_kunden_config` (
  `customer_id` INT NOT NULL,
  `budget_monat` DECIMAL(10,2) NULL,
  `mix_strategie` TEXT NULL,
  `brand_regel` VARCHAR(255) NULL,
  `wissensdb_ordner` VARCHAR(255) NULL,
  `asana_projekt_gid` VARCHAR(50) NULL,
  `asana_projekt_name` VARCHAR(255) NULL,
  `asana_section_gid` VARCHAR(50) NULL,
  `asana_section_name` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NULL,
  PRIMARY KEY (`customer_id`),
  CONSTRAINT `lam_kunden_config_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_customer_tags` (
  `customer_id` INT NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  `gewichtung` INT NOT NULL DEFAULT 3,
  PRIMARY KEY (`customer_id`, `tag_id`),
  KEY `lam_customer_tags_tag_id_idx` (`tag_id`),
  CONSTRAINT `lam_customer_tags_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_customer_tags_tag_fk` FOREIGN KEY (`tag_id`)
    REFERENCES `lam_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_linkziele` (
  `id` VARCHAR(26) NOT NULL,
  `customer_id` INT NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `thema` VARCHAR(255) NULL,
  `bevorzugter_linktext` VARCHAR(255) NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'aktiv',
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_linkziele_customer_id_idx` (`customer_id`),
  KEY `lam_linkziele_geloescht_am_idx` (`geloescht_am`),
  CONSTRAINT `lam_linkziele_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot Domain <-> Customer (welche Pool-Domain ist welchem Kunden bekannt)
CREATE TABLE IF NOT EXISTS `lam_domain_customer` (
  `domain_id` VARCHAR(26) NOT NULL,
  `customer_id` INT NOT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`domain_id`, `customer_id`),
  KEY `lam_domain_customer_customer_id_idx` (`customer_id`),
  CONSTRAINT `lam_domain_customer_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_domain_customer_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_massnahmen` (
  `id` VARCHAR(26) NOT NULL,
  `customer_id` INT NOT NULL,
  `domain_id` VARCHAR(26) NOT NULL,
  `linkziel_id` VARCHAR(26) NULL,
  `verantwortlich_user_id` INT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'vorgeschlagen',
  `vorgangstyp` VARCHAR(40) NOT NULL DEFAULT 'erstveroeffentlichung',
  `buchungstyp` VARCHAR(60) NULL,
  `buchungsweg_kondition_id` VARCHAR(26) NULL,
  `linktext` VARCHAR(255) NULL,
  `brand_integration` VARCHAR(40) NULL,
  `geplant_am` DATE NULL,
  `veroeffentlicht_am` DATE NULL,
  `veroeffentlichungs_url` VARCHAR(500) NULL,
  `sonderstatus` VARCHAR(40) NOT NULL DEFAULT 'normal',
  `plan_a_massnahme_id` VARCHAR(26) NULL,
  `asana_task_gid` VARCHAR(50) NULL,
  `asana_zuletzt_synchronisiert_am` DATETIME NULL,
  `asana_task_cache` JSON NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_massnahmen_customer_id_idx` (`customer_id`),
  KEY `lam_massnahmen_domain_id_idx` (`domain_id`),
  KEY `lam_massnahmen_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_massnahmen_status_idx` (`status`),
  KEY `lam_massnahmen_customer_status_idx` (`customer_id`, `status`),
  KEY `lam_massnahmen_customer_sonderstatus_idx` (`customer_id`, `sonderstatus`),
  KEY `lam_massnahmen_veroeffentlicht_am_idx` (`veroeffentlicht_am`),
  KEY `lam_massnahmen_asana_task_gid_idx` (`asana_task_gid`),
  CONSTRAINT `lam_massnahmen_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_massnahmen_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`),
  CONSTRAINT `lam_massnahmen_linkziel_fk` FOREIGN KEY (`linkziel_id`)
    REFERENCES `lam_linkziele` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_massnahmen_user_fk` FOREIGN KEY (`verantwortlich_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_massnahmen_kondition_fk` FOREIGN KEY (`buchungsweg_kondition_id`)
    REFERENCES `lam_konditionen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_massnahmen_plan_a_fk` FOREIGN KEY (`plan_a_massnahme_id`)
    REFERENCES `lam_massnahmen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_vorschlagslisten` (
  `id` VARCHAR(26) NOT NULL,
  `customer_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `zielzahl` INT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'entwurf',
  `notiz` TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_vorschlagslisten_customer_id_idx` (`customer_id`),
  KEY `lam_vorschlagslisten_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_vorschlagslisten_status_idx` (`status`),
  CONSTRAINT `lam_vorschlagslisten_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_vorschlagsliste_eintraege` (
  `id` VARCHAR(26) NOT NULL,
  `vorschlagsliste_id` VARCHAR(26) NOT NULL,
  `domain_id` VARCHAR(26) NOT NULL,
  `notiz` TEXT NULL,
  `vorgeschlagener_linktext` VARCHAR(255) NULL,
  `position` INT NOT NULL DEFAULT 0,
  `massnahme_id` VARCHAR(26) NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'vorgeschlagen',
  `kontakt_am` DATE NULL,
  `letzte_rueckmeldung_am` DATE NULL,
  `letzte_rueckmeldung_typ` VARCHAR(40) NULL,
  `naechste_aktion_am` DATE NULL,
  `naechste_aktion_notiz` VARCHAR(255) NULL,
  `preis_kunde` DECIMAL(10,2) NULL,
  `ziel_url` VARCHAR(500) NULL,
  `artikelthema` VARCHAR(255) NULL,
  `asana_task_gid` VARCHAR(50) NULL,
  `asana_task_cache` JSON NULL,
  `asana_zuletzt_synchronisiert_am` DATETIME NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_vorschlagsliste_eintraege_unique`
    (`vorschlagsliste_id`, `domain_id`),
  KEY `lam_vorschlagsliste_eintraege_status_idx` (`status`),
  KEY `lam_vorschlagsliste_eintraege_asana_task_gid_idx` (`asana_task_gid`),
  CONSTRAINT `lam_vorschlagsliste_eintraege_liste_fk` FOREIGN KEY (`vorschlagsliste_id`)
    REFERENCES `lam_vorschlagslisten` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_vorschlagsliste_eintraege_domain_fk` FOREIGN KEY (`domain_id`)
    REFERENCES `lam_domains` (`id`),
  CONSTRAINT `lam_vorschlagsliste_eintraege_massnahme_fk` FOREIGN KEY (`massnahme_id`)
    REFERENCES `lam_massnahmen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. AUSLAGEN & MONITORING
-- =============================================================================

CREATE TABLE IF NOT EXISTS `lam_auslagen` (
  `id` VARCHAR(26) NOT NULL,
  `massnahme_id` VARCHAR(26) NOT NULL,
  `externe_kosten` DECIMAL(10,2) NULL,
  `rechnung_eingang` DATE NULL,
  `weiterverrechnet` DECIMAL(10,2) NULL,
  `marge` DECIMAL(10,2) NULL,
  `marge_grund` TEXT NULL,
  `thoxan_rechnung_nr` VARCHAR(50) NULL,
  `thoxan_rechnung_datum` DATE NULL,
  `sonderfall` VARCHAR(40) NOT NULL DEFAULT 'normal',
  `abgerechnet_fuer` VARCHAR(255) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` DATETIME NULL,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_auslagen_massnahme_id_unique` (`massnahme_id`),
  KEY `lam_auslagen_rechnung_eingang_idx` (`rechnung_eingang`),
  KEY `lam_auslagen_thoxan_rechnung_datum_idx` (`thoxan_rechnung_datum`),
  KEY `lam_auslagen_sonderfall_idx` (`sonderfall`),
  KEY `lam_auslagen_geloescht_am_idx` (`geloescht_am`),
  CONSTRAINT `lam_auslagen_massnahme_fk` FOREIGN KEY (`massnahme_id`)
    REFERENCES `lam_massnahmen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_monitoring_checks` (
  `id` VARCHAR(26) NOT NULL,
  `massnahme_id` VARCHAR(26) NOT NULL,
  `zeitpunkt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `http_status` INT NULL,
  `link_vorhanden` TINYINT(1) NOT NULL DEFAULT 0,
  `link_typ` VARCHAR(40) NULL,
  `alert_ausgeloest` TINYINT(1) NOT NULL DEFAULT 0,
  `fehlermeldung` TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lam_monitoring_checks_massnahme_zeitpunkt_idx`
    (`massnahme_id`, `zeitpunkt`),
  KEY `lam_monitoring_checks_alert_idx` (`alert_ausgeloest`),
  CONSTRAINT `lam_monitoring_checks_massnahme_fk` FOREIGN KEY (`massnahme_id`)
    REFERENCES `lam_massnahmen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. KORRESPONDENZ
-- =============================================================================

CREATE TABLE IF NOT EXISTS `lam_kommunikation` (
  `id` VARCHAR(26) NOT NULL,
  `typ` VARCHAR(40) NOT NULL,
  `zeitpunkt` DATETIME NOT NULL,
  `inhalt` TEXT NULL,
  `anbieter_id` VARCHAR(26) NOT NULL,
  `kontakt_id` VARCHAR(26) NULL,
  `vorschlagsliste_eintrag_id` VARCHAR(26) NULL,
  `massnahme_id` VARCHAR(26) NULL,
  `user_id` INT NULL,
  `anhang_pfad` VARCHAR(500) NULL,
  `anhang_originalname` VARCHAR(255) NULL,
  `anhang_mime` VARCHAR(100) NULL,
  `anhang_groesse` INT NULL,
  `mail_id_extern` VARCHAR(255) NULL,
  `absender_mail` VARCHAR(255) NULL,
  `empfaenger_mail` VARCHAR(255) NULL,
  `betreff` VARCHAR(255) NULL,
  `ki_extrakt` TEXT NULL,
  `vorlagen_id` VARCHAR(26) NULL,
  `versendet_am` DATETIME NULL,
  `status` VARCHAR(40) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `lam_kommunikation_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_kommunikation_anbieter_zeitpunkt_idx` (`anbieter_id`, `zeitpunkt`),
  KEY `lam_kommunikation_eintrag_zeitpunkt_idx`
    (`vorschlagsliste_eintrag_id`, `zeitpunkt`),
  KEY `lam_kommunikation_massnahme_zeitpunkt_idx`
    (`massnahme_id`, `zeitpunkt`),
  KEY `lam_kommunikation_zeitpunkt_idx` (`zeitpunkt`),
  KEY `lam_kommunikation_mail_id_extern_idx` (`mail_id_extern`),
  CONSTRAINT `lam_kommunikation_anbieter_fk` FOREIGN KEY (`anbieter_id`)
    REFERENCES `lam_anbieter` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_kommunikation_kontakt_fk` FOREIGN KEY (`kontakt_id`)
    REFERENCES `lam_kontakte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_kommunikation_eintrag_fk` FOREIGN KEY (`vorschlagsliste_eintrag_id`)
    REFERENCES `lam_vorschlagsliste_eintraege` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_kommunikation_massnahme_fk` FOREIGN KEY (`massnahme_id`)
    REFERENCES `lam_massnahmen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lam_kommunikation_user_fk` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. LINKPROFIL
-- =============================================================================

CREATE TABLE IF NOT EXISTS `lam_verlinkungen` (
  `id` VARCHAR(26) NOT NULL,
  `customer_id` INT NOT NULL,
  `verlinkende_url` TEXT NOT NULL,
  `url_hash` VARCHAR(40) NOT NULL,
  `domain` VARCHAR(255) NOT NULL,
  `linktext` VARCHAR(500) NULL,
  `linkart` VARCHAR(40) NULL,
  `empfehlung` VARCHAR(40) NULL,
  `status` VARCHAR(40) NULL,
  `bemerkung` TEXT NULL,
  `ist_neu` TINYINT(1) NOT NULL DEFAULT 1,
  `imported_from` VARCHAR(40) NULL,
  `aufraeum_status` VARCHAR(40) NOT NULL DEFAULT 'offen',
  `ziel_url` TEXT NULL,
  `letzter_http_status` INT NULL,
  `letzter_http_erreichbar` TINYINT(1) NULL,
  `linkziel_gefunden` TINYINT(1) NULL,
  `letzter_check_am` DATETIME NULL,
  `is_follow` TINYINT(1) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` DATETIME NULL,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_verlinkungen_unique`
    (`customer_id`, `url_hash`),
  KEY `lam_verlinkungen_geloescht_am_idx` (`geloescht_am`),
  KEY `lam_verlinkungen_customer_id_idx` (`customer_id`),
  KEY `lam_verlinkungen_domain_idx` (`domain`),
  KEY `lam_verlinkungen_linkart_idx` (`linkart`),
  KEY `lam_verlinkungen_empfehlung_idx` (`empfehlung`),
  KEY `lam_verlinkungen_status_idx` (`status`),
  KEY `lam_verlinkungen_aufraeum_status_idx` (`aufraeum_status`),
  KEY `lam_verlinkungen_letzter_http_erreichbar_idx` (`letzter_http_erreichbar`),
  KEY `lam_verlinkungen_linkziel_gefunden_idx` (`linkziel_gefunden`),
  KEY `lam_verlinkungen_is_follow_idx` (`is_follow`),
  CONSTRAINT `lam_verlinkungen_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kundenuebergreifende Wissensbasis: was wissen wir ueber eine Domain?
CREATE TABLE IF NOT EXISTS `lam_domain_wissen` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` VARCHAR(255) NOT NULL,
  `linkart` VARCHAR(40) NOT NULL,
  `reduktionsstrategie` VARCHAR(40) NOT NULL,
  `confidence` VARCHAR(40) NOT NULL,
  `anzahl_klassifikationen` INT UNSIGNED NOT NULL DEFAULT 1,
  `letzter_customer_id` INT NULL,
  `notiz` TEXT NULL,
  `empfehlung_default` VARCHAR(40) NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_domain_wissen_domain_unique` (`domain`),
  KEY `lam_domain_wissen_linkart_idx` (`linkart`),
  KEY `lam_domain_wissen_confidence_idx` (`confidence`),
  CONSTRAINT `lam_domain_wissen_customer_fk` FOREIGN KEY (`letzter_customer_id`)
    REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_linkprofil_snapshots` (
  `id` VARCHAR(26) NOT NULL,
  `customer_id` INT NOT NULL,
  `snapshot_datum` DATE NOT NULL,
  `anzahl_verlinkungen` INT UNSIGNED NOT NULL DEFAULT 0,
  `auswertung_json` JSON NULL,
  `notiz` TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lam_linkprofil_snapshots_customer_datum_idx`
    (`customer_id`, `snapshot_datum`),
  CONSTRAINT `lam_linkprofil_snapshots_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_linkprofil_snapshot_verlinkungen` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `snapshot_id` VARCHAR(26) NOT NULL,
  `verlinkung_id` VARCHAR(26) NOT NULL,
  `linkart_at_snapshot` VARCHAR(40) NULL,
  `empfehlung_at_snapshot` VARCHAR(40) NULL,
  `status_at_snapshot` VARCHAR(40) NULL,
  `war_neu` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_linkprofil_snapshot_verlinkungen_unique`
    (`snapshot_id`, `verlinkung_id`),
  KEY `lam_linkprofil_snapshot_verlinkungen_snapshot_idx` (`snapshot_id`),
  CONSTRAINT `lam_linkprofil_snapshot_verlinkungen_snapshot_fk`
    FOREIGN KEY (`snapshot_id`)
    REFERENCES `lam_linkprofil_snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_linkprofil_snapshot_verlinkungen_verlinkung_fk`
    FOREIGN KEY (`verlinkung_id`)
    REFERENCES `lam_verlinkungen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_linkprofil_tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `customer_id` INT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geloescht_am` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_linkprofil_tags_slug_customer_unique` (`slug`, `customer_id`),
  KEY `lam_linkprofil_tags_customer_id_idx` (`customer_id`),
  CONSTRAINT `lam_linkprofil_tags_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_linkprofil_tag_verlinkung` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `linkprofil_tag_id` INT UNSIGNED NOT NULL,
  `verlinkung_id` VARCHAR(26) NOT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lam_linkprofil_tag_verlinkung_unique`
    (`linkprofil_tag_id`, `verlinkung_id`),
  KEY `lam_linkprofil_tag_verlinkung_verlinkung_idx` (`verlinkung_id`),
  CONSTRAINT `lam_linkprofil_tag_verlinkung_tag_fk` FOREIGN KEY (`linkprofil_tag_id`)
    REFERENCES `lam_linkprofil_tags` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lam_linkprofil_tag_verlinkung_verlinkung_fk` FOREIGN KEY (`verlinkung_id`)
    REFERENCES `lam_verlinkungen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. AUDIT-LOG
-- =============================================================================

CREATE TABLE IF NOT EXISTS `lam_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `aktion` VARCHAR(80) NOT NULL,
  `entity_typ` VARCHAR(80) NOT NULL,
  `entity_id` VARCHAR(26) NULL,
  `payload` JSON NULL,
  `ist_bulk` TINYINT(1) NOT NULL DEFAULT 0,
  `anzahl_betroffen` INT NULL,
  `zeitpunkt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lam_audit_logs_zeitpunkt_idx` (`zeitpunkt`),
  KEY `lam_audit_logs_entity_idx` (`entity_typ`, `entity_id`),
  KEY `lam_audit_logs_user_idx` (`user_id`),
  CONSTRAINT `lam_audit_logs_user_fk` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8. ID-MAPPING (temporaer waehrend ETL)
-- =============================================================================
-- Wird vom lam_import.php-Skript befuellt, um LAMS-kuerzel auf customers.id
-- aufzuloesen. Nach erfolgreicher Migration droppen.
CREATE TABLE IF NOT EXISTS `lam_id_map_customer` (
  `kuerzel` VARCHAR(20) NOT NULL,
  `customer_id` INT NOT NULL,
  `lams_name` VARCHAR(255) NOT NULL,
  `notiz` VARCHAR(255) NULL,
  PRIMARY KEY (`kuerzel`),
  CONSTRAINT `lam_id_map_customer_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lam_id_map_user` (
  `mitarbeiter_kuerzel` VARCHAR(20) NOT NULL,
  `user_id` INT NOT NULL,
  `lams_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`mitarbeiter_kuerzel`),
  CONSTRAINT `lam_id_map_user_user_fk` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- ENDE
-- =============================================================================
