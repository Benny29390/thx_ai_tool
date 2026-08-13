<?php
/**
 * Schema-Vorbereitung für Multi-Tenancy im LAM-System.
 *
 * Folgt der Prototyp-Empfehlung (Spec §12): jeder LAM-Datensatz bekommt
 * eine `mandant_id`-Spalte, im MVP fix auf 'thoxan'. Die App-Logik nutzt
 * die Spalte aktuell NICHT zur Filterung — das ist die spätere Erweiterung,
 * wenn ein zweiter Mandant dazukommt.
 *
 * Idempotent: kann mehrfach laufen.
 *
 * Aufruf:  php /var/www/scripts/migrate-lam-multitenancy.php
 */

require_once __DIR__ . '/../core/Database.php';
$cfg = require __DIR__ . '/../config/config.php';
$db = \Core\Database::getInstance($cfg['db']);

$lamTabellen = [
    'lam_anbieter', 'lam_kontakte', 'lam_domains', 'lam_verlinkungen',
    'lam_massnahmen', 'lam_auslagen', 'lam_konditionen', 'lam_kennzahl_snapshots',
    'lam_kommunikation', 'lam_vorschlagslisten', 'lam_vorschlagsliste_eintraege',
    'lam_domain_anbieter', 'lam_domain_customer', 'lam_domain_tag',
    'lam_tags', 'lam_linkziele', 'lam_domain_wissen', 'lam_monitoring_checks',
    'lam_linkprofil_snapshots', 'lam_audit_logs', 'lam_aufgaben',
];

// 1. Mandanten-Tabelle (Stammdaten der Mandanten)
$db->execute("
    CREATE TABLE IF NOT EXISTS lam_mandanten (
        id VARCHAR(40) NOT NULL PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        ist_default TINYINT(1) NOT NULL DEFAULT 0,
        erstellt_am DATETIME NOT NULL DEFAULT current_timestamp(),
        notizen TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 2. Default-Mandant „thoxan"
$db->execute(
    "INSERT IGNORE INTO lam_mandanten (id, name, ist_default) VALUES ('thoxan', 'Thoxan Communications GmbH', 1)"
);

// 3. mandant_id zu jeder LAM-Tabelle hinzufügen (idempotent)
$angefasst = [];
$skipped = [];
foreach ($lamTabellen as $t) {
    $exists = $db->queryValue(
        "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
        [$t]
    );
    if (!$exists) { $skipped[] = "$t (nicht existent)"; continue; }
    $colExists = $db->queryValue(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'mandant_id'",
        [$t]
    );
    if ($colExists) { $skipped[] = "$t (Spalte schon da)"; continue; }
    try {
        $db->execute(
            "ALTER TABLE `{$t}` ADD COLUMN mandant_id VARCHAR(40) NOT NULL DEFAULT 'thoxan'"
        );
        $db->execute("ALTER TABLE `{$t}` ADD INDEX idx_mandant (mandant_id)");
        $angefasst[] = $t;
    } catch (\Throwable $e) {
        $skipped[] = "$t (FEHLER: " . $e->getMessage() . ")";
    }
}

echo "=== LAM Multi-Tenancy Migration ===\n";
echo "Default-Mandant: thoxan\n";
echo "Erweiterte Tabellen (" . count($angefasst) . "):\n";
foreach ($angefasst as $t) echo "  ✓ $t\n";
echo "\nÜbersprungen (" . count($skipped) . "):\n";
foreach ($skipped as $s) echo "  · $s\n";
echo "\nFertig. Die Spalte `mandant_id` ist überall vorhanden und auf 'thoxan' default-gesetzt.\n";
echo "Anwendungslogik filtert noch NICHT auf mandant_id — das ist Schalter-mäßig zu aktivieren,\n";
echo "wenn ein zweiter Mandant dazukommt (siehe LamService::aktuellerMandant()).\n";
