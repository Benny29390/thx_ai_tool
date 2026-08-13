<?php
/**
 * Schema-Migration für das Mail-Modul (siehe docs/mail-modul-briefing.md §3).
 * Idempotent: kann mehrfach laufen.
 *
 * Aufruf: php /var/www/scripts/migrate-mail-modul.php
 */

require_once __DIR__ . '/../core/Database.php';
$cfg = require __DIR__ . '/../config/config.php';
$db = \Core\Database::getInstance($cfg['db']);

$migrations = [
    'mail_konten' => "
        CREATE TABLE IF NOT EXISTS mail_konten (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email_adresse VARCHAR(255) NOT NULL,
            aktiv TINYINT(1) NOT NULL DEFAULT 1,
            imap_host VARCHAR(120) NULL,
            imap_port INT(11) NOT NULL DEFAULT 993,
            imap_username VARCHAR(255) NULL,
            imap_password_enc TEXT NULL,
            imap_encryption ENUM('ssl','tls','starttls','none') NOT NULL DEFAULT 'ssl',
            imap_folder_inbox VARCHAR(80) NOT NULL DEFAULT 'INBOX',
            imap_folder_verarbeitet VARCHAR(80) NOT NULL DEFAULT 'INBOX.Verarbeitet',
            imap_folder_fehler VARCHAR(80) NOT NULL DEFAULT 'INBOX.Fehler',
            smtp_host VARCHAR(120) NULL,
            smtp_port INT(11) NOT NULL DEFAULT 587,
            smtp_username VARCHAR(255) NULL,
            smtp_password_enc TEXT NULL,
            smtp_encryption ENUM('ssl','tls','starttls','none') NOT NULL DEFAULT 'starttls',
            signatur TEXT NULL,
            auto_antwort_aktiv TINYINT(1) NOT NULL DEFAULT 0,
            auto_antwort_konfidenz_min DECIMAL(3,2) NOT NULL DEFAULT 0.95,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (email_adresse)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_nachrichten' => "
        CREATE TABLE IF NOT EXISTS mail_nachrichten (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            konto_id INT(10) UNSIGNED NOT NULL,
            richtung ENUM('eingang','ausgang') NOT NULL,
            message_id VARCHAR(255) NULL,
            in_reply_to VARCHAR(255) NULL,
            absender_email VARCHAR(255) NOT NULL,
            absender_name VARCHAR(120) NULL,
            empfaenger_email VARCHAR(255) NOT NULL,
            cc_emails TEXT NULL,
            betreff VARCHAR(500) NULL,
            body_plain LONGTEXT NULL,
            body_html LONGTEXT NULL,
            empfangen_am DATETIME NOT NULL,
            roh_eml_pfad VARCHAR(500) NULL,
            quelle ENUM('imap','eml_upload','manuell','versand') NOT NULL DEFAULT 'imap',
            anhaenge_anzahl INT(10) UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('eingang','klassifiziert','beantwortet','archiviert','ignoriert','fehler') NOT NULL DEFAULT 'eingang',
            gelesen TINYINT(1) NOT NULL DEFAULT 0,
            markiert TINYINT(1) NOT NULL DEFAULT 0,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            aktualisiert_am DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            geloescht_am DATETIME NULL,
            INDEX (konto_id, empfangen_am),
            INDEX (status),
            INDEX (gelesen),
            INDEX (message_id),
            CONSTRAINT mail_n_konto_fk FOREIGN KEY (konto_id) REFERENCES mail_konten(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_anhaenge' => "
        CREATE TABLE IF NOT EXISTS mail_anhaenge (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            mail_id INT(10) UNSIGNED NOT NULL,
            dateiname VARCHAR(255) NOT NULL,
            mime_typ VARCHAR(120) NULL,
            groesse_bytes INT(10) UNSIGNED NOT NULL,
            pfad VARCHAR(500) NOT NULL,
            INDEX (mail_id),
            CONSTRAINT mail_a_mail_fk FOREIGN KEY (mail_id) REFERENCES mail_nachrichten(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_klassifikationen' => "
        CREATE TABLE IF NOT EXISTS mail_klassifikationen (
            mail_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            kategorie VARCHAR(60) NOT NULL,
            kategorie_konfidenz DECIMAL(3,2) NULL,
            absicht VARCHAR(200) NULL,
            sprache VARCHAR(8) NULL,
            dringlichkeit ENUM('niedrig','mittel','hoch') NULL,
            vorgeschlagene_antwort LONGTEXT NULL,
            vorlage_id INT(10) UNSIGNED NULL,
            folgeaktion VARCHAR(80) NULL,
            folgeaktion_konfidenz DECIMAL(3,2) NULL,
            ki_meta LONGTEXT NULL,
            klassifiziert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ki_modell VARCHAR(60) NULL,
            INDEX (kategorie),
            CONSTRAINT mail_k_mail_fk FOREIGN KEY (mail_id) REFERENCES mail_nachrichten(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_vorlagen' => "
        CREATE TABLE IF NOT EXISTS mail_vorlagen (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            kategorie VARCHAR(60) NULL,
            betreff_template VARCHAR(500) NULL,
            body_template LONGTEXT NOT NULL,
            platzhalter JSON NULL,
            aktiv TINYINT(1) NOT NULL DEFAULT 1,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (kategorie),
            INDEX (aktiv)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_antworten' => "
        CREATE TABLE IF NOT EXISTS mail_antworten (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            eingang_mail_id INT(10) UNSIGNED NOT NULL,
            ausgang_mail_id INT(10) UNSIGNED NULL,
            vorlage_id INT(10) UNSIGNED NULL,
            ki_vorschlag LONGTEXT NULL,
            finaler_text LONGTEXT NOT NULL,
            wurde_editiert TINYINT(1) NOT NULL DEFAULT 0,
            versendet_am DATETIME NULL,
            versendet_von_user_id INT(11) NULL,
            auto_versendet TINYINT(1) NOT NULL DEFAULT 0,
            INDEX (eingang_mail_id),
            INDEX (ausgang_mail_id),
            CONSTRAINT mail_ant_in_fk FOREIGN KEY (eingang_mail_id) REFERENCES mail_nachrichten(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_regeln' => "
        CREATE TABLE IF NOT EXISTS mail_regeln (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            absender_pattern VARCHAR(255) NULL,
            betreff_pattern VARCHAR(255) NULL,
            body_pattern VARCHAR(255) NULL,
            kategorie VARCHAR(60) NULL,
            folgeaktion VARCHAR(80) NULL,
            vorlage_id INT(10) UNSIGNED NULL,
            prioritaet INT(11) NOT NULL DEFAULT 10,
            aktiv TINYINT(1) NOT NULL DEFAULT 1,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (aktiv, prioritaet)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_lam_verknuepfung' => "
        CREATE TABLE IF NOT EXISTS mail_lam_verknuepfung (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            mail_id INT(10) UNSIGNED NOT NULL,
            typ ENUM('anbieter','massnahme','korrespondenz','aufgabe') NOT NULL,
            ziel_id VARCHAR(64) NOT NULL,
            automatisch TINYINT(1) NOT NULL DEFAULT 1,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (mail_id),
            INDEX (typ, ziel_id),
            CONSTRAINT mail_lv_mail_fk FOREIGN KEY (mail_id) REFERENCES mail_nachrichten(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_pull_logs' => "
        CREATE TABLE IF NOT EXISTS mail_pull_logs (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            konto_id INT(10) UNSIGNED NULL,
            gestartet_am DATETIME NOT NULL,
            dauer_ms INT(10) UNSIGNED NULL,
            trigger_typ ENUM('cron','manuell') NOT NULL DEFAULT 'cron',
            erfolg_count INT(11) NOT NULL DEFAULT 0,
            dublette_count INT(11) NOT NULL DEFAULT 0,
            fehler_count INT(11) NOT NULL DEFAULT 0,
            uebersprungen_count INT(11) NOT NULL DEFAULT 0,
            verbindungs_fehler TEXT NULL,
            details_json LONGTEXT NULL,
            INDEX (konto_id, gestartet_am)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'mail_ordner' => "
        CREATE TABLE IF NOT EXISTS mail_ordner (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            konto_id INT(10) UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            parent_id INT(10) UNSIGNED NULL,
            farbe VARCHAR(20) NULL,
            sortierung INT NOT NULL DEFAULT 0,
            erstellt_von_user_id INT NULL,
            erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (konto_id),
            INDEX (parent_id),
            CONSTRAINT mail_o_konto_fk FOREIGN KEY (konto_id) REFERENCES mail_konten(id) ON DELETE CASCADE,
            CONSTRAINT mail_o_parent_fk FOREIGN KEY (parent_id) REFERENCES mail_ordner(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
];

// Spalte ordner_id in mail_nachrichten (nicht im CREATE TABLE oben, weil mail_ordner danach kommt)
$ordnerIdAlter = [
    "ALTER TABLE mail_nachrichten ADD COLUMN ordner_id INT(10) UNSIGNED NULL AFTER status",
    "ALTER TABLE mail_nachrichten ADD INDEX idx_ordner_id (ordner_id)",
];

$angefasst = [];
$skipped = [];

foreach ($migrations as $tabelle => $sql) {
    $exists = $db->queryValue(
        "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
        [$tabelle]
    );
    try {
        $db->execute($sql);
        if ($exists) $skipped[] = $tabelle . ' (bereits vorhanden)';
        else $angefasst[] = $tabelle;
    } catch (\Throwable $e) {
        $skipped[] = $tabelle . ' (FEHLER: ' . $e->getMessage() . ')';
    }
}

// Spalten-Migrationen (für bestehende Tabellen)
foreach ($ordnerIdAlter as $alterSql) {
    try {
        $db->execute($alterSql);
        $angefasst[] = "ALTER: " . substr($alterSql, 0, 60) . '…';
    } catch (\Throwable $e) {
        // Duplicate column / Duplicate key → ok, schon migriert
        if (stripos($e->getMessage(), 'duplicate') === false) {
            $skipped[] = "ALTER ($alterSql): " . $e->getMessage();
        }
    }
}

// Globale App-Settings vorbelegen
$settingsDefaults = [
    'mail_auto_versand_global_aktiv' => '0',
    'mail_pull_intervall_minuten' => '10',
    'mail_anhang_max_mb' => '25',
    'mail_stop_woerter' => 'Anwalt,Klage,Datenschutz,GDPR,Abmahnung,Reklamation,Beschwerde,Inkasso',
];
foreach ($settingsDefaults as $key => $value) {
    try {
        $exists = $db->queryValue("SELECT 1 FROM settings WHERE setting_key = ?", [$key]);
        if (!$exists) {
            $db->execute(
                "INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'string')",
                [$key, $value]
            );
            $angefasst[] = "Setting: $key=$value";
        }
    } catch (\Throwable $e) { $skipped[] = "Setting $key: " . $e->getMessage(); }
}

// Storage-Ordner für EML + Anhänge anlegen
$storage = '/var/www/storage/mail';
foreach ([$storage, $storage . '/eml', $storage . '/anhaenge'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @chown($dir, 'www-data');
        @chgrp($dir, 'www-data');
        $angefasst[] = "Verzeichnis: $dir";
    }
}

echo "=== Mail-Modul Migration ===\n";
echo "Angelegt/Aktualisiert (" . count($angefasst) . "):\n";
foreach ($angefasst as $t) echo "  ✓ $t\n";
echo "\nÜbersprungen (" . count($skipped) . "):\n";
foreach ($skipped as $s) echo "  · $s\n";
echo "\nFertig. Settings-UI: /admin/settings?tab=mail, Inbox: /mail (kommt mit Phase-1-UI).\n";
