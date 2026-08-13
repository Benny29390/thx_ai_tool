<?php
/**
 * CRM-Migration 002: Zoho-Felder, die bei der Brevo-Migration „futsch" gingen.
 *
 * Erweitert crm_kontakte um:
 *   - UTM/Analyse (5 Felder)
 *   - Lead-Magnet-Infos (2 Felder)
 *   - Wunschkunden-Podcast (5 Felder)
 *   - Meta/Trigger (8 Felder)
 *
 * Seedet zudem System-Listen für die typischen Zoho-„Aktive Listen"
 * und „Genutzte Lead-Magneten", damit die Inhalte ab sofort wieder
 * gepflegt werden können (per Listen-Mitgliedschaft).
 *
 * Idempotent: jeder ALTER ist in try/catch eingepackt; bereits
 * vorhandene Spalten werfen „Duplicate column" und werden geschluckt.
 */
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/config/constants.php';
require BASE_PATH . '/core/Database.php';
$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

echo "─── CRM-Migration 002 ───\n";

function addColumn(\Core\Database $db, string $table, string $col, string $def): void {
    try {
        $db->execute("ALTER TABLE $table ADD COLUMN $col $def");
        echo "  + $table.$col\n";
    } catch (\Throwable $e) {
        if (stripos($e->getMessage(), 'Duplicate column') === false) {
            echo "  ! $table.$col → " . $e->getMessage() . "\n";
        } else {
            echo "  · $table.$col (existiert bereits)\n";
        }
    }
}

// ─── UTM/Analyse ────────────────────────────────────────────────────────
addColumn($db, 'crm_kontakte', 'utm_source',     'VARCHAR(120) NULL AFTER lead_quelle');
addColumn($db, 'crm_kontakte', 'utm_medium',     'VARCHAR(120) NULL AFTER utm_source');
addColumn($db, 'crm_kontakte', 'utm_campaign',   'VARCHAR(200) NULL AFTER utm_medium');
addColumn($db, 'crm_kontakte', 'utm_content',    'VARCHAR(200) NULL AFTER utm_campaign');
addColumn($db, 'crm_kontakte', 'utm_term',       'VARCHAR(200) NULL AFTER utm_content');
addColumn($db, 'crm_kontakte', 'herkunft_referrer', 'VARCHAR(500) NULL AFTER utm_term');

// ─── Lead-Magnet-Infos (eines pro Kontakt — wird bei jedem neuen Lead-Magnet überschrieben) ─────
addColumn($db, 'crm_kontakte', 'lead_magnet_name', 'VARCHAR(200) NULL AFTER herkunft_referrer');
addColumn($db, 'crm_kontakte', 'lead_magnet_url',  'VARCHAR(500) NULL AFTER lead_magnet_name');

// ─── Wunschkunden-Podcast ───────────────────────────────────────────────
addColumn($db, 'crm_kontakte', 'podcast_titel',         'VARCHAR(255) NULL AFTER lead_magnet_url');
addColumn($db, 'crm_kontakte', 'podcast_subtitel',      'VARCHAR(255) NULL AFTER podcast_titel');
addColumn($db, 'crm_kontakte', 'podcast_release_datum', 'DATE NULL AFTER podcast_subtitel');
addColumn($db, 'crm_kontakte', 'podcast_release_url',   'VARCHAR(500) NULL AFTER podcast_release_datum');
addColumn($db, 'crm_kontakte', 'podcast_release_mail',  'VARCHAR(255) NULL AFTER podcast_release_url');

// ─── Meta / Trigger / Sync ──────────────────────────────────────────────
addColumn($db, 'crm_kontakte', 'ac_sync',                     'TINYINT(1) DEFAULT 0 AFTER podcast_release_mail');
addColumn($db, 'crm_kontakte', 'kuendigungsoption',           'VARCHAR(80) NULL AFTER ac_sync');
addColumn($db, 'crm_kontakte', 'stand_datensatz',             'VARCHAR(200) NULL AFTER kuendigungsoption');
addColumn($db, 'crm_kontakte', 'layout_name',                 'VARCHAR(80) NULL DEFAULT \'Thoxan\' AFTER stand_datensatz');
addColumn($db, 'crm_kontakte', 'trigger_kontaktformular',     'TINYINT(1) DEFAULT 0 AFTER layout_name');
addColumn($db, 'crm_kontakte', 'trigger_terminbuchung',       'TINYINT(1) DEFAULT 0 AFTER trigger_kontaktformular');
addColumn($db, 'crm_kontakte', 'trigger_strategie_check',     'TINYINT(1) DEFAULT 0 AFTER trigger_terminbuchung');
addColumn($db, 'crm_kontakte', 'trigger_lead_magnet',         'TINYINT(1) DEFAULT 0 AFTER trigger_strategie_check');
addColumn($db, 'crm_kontakte', 'trigger_test',                'TINYINT(1) DEFAULT 0 AFTER trigger_lead_magnet');

// ─── System-Listen seeden (idempotent über UNIQUE name) ─────────────────
echo "\n─── Seede System-Listen ───\n";
$listen = [
    ['THX Partner',                  'Partner-Status (Zoho-Legacy)'],
    ['THX Hauptliste',               'Aktive Hauptliste (Zoho-Legacy)'],
    ['Follow-Up Profilierung',       'Follow-Up: Profilierungs-Sequenz'],
    ['Follow-Up Sichtbarkeit',       'Follow-Up: Sichtbarkeits-Sequenz'],
    ['Follow-Up Verkauf',            'Follow-Up: Verkaufs-Sequenz'],
    ['Follow-Up Kontinuität',        'Follow-Up: Kontinuitäts-Sequenz'],
    ['Follow-Up Branding-Check',     'Follow-Up: Branding-Check-Sequenz'],
    ['Akquise-Mastermind Onboarding','Onboarding-Sequenz für Akquise-Mastermind-Teilnehmer'],
    ['Wunschkunden-Konferenz',       'Teilnehmer Wunschkunden-Konferenz'],
    ['Wunschkunden-Podcast',         'Wunschkunden-Podcast-Hörer'],
    ['IHK',                          'IHK-Workshop-Teilnehmer'],
];
foreach ($listen as [$name, $beschreibung]) {
    try {
        $vorhanden = $db->queryValue("SELECT id FROM crm_listen WHERE name = ?", [$name]);
        if ($vorhanden) {
            echo "  · \u201e$name\u201c (id $vorhanden) existiert bereits\n";
            continue;
        }
        $id = $db->insert('crm_listen', [
            'name' => $name,
            'beschreibung' => $beschreibung,
            'anzahl_aktive' => 0,
            'archiviert' => 0,
        ]);
        echo '  + "' . $name . '" angelegt (id ' . $id . ")\n";
    } catch (\Throwable $e) {
        echo '  ! "' . $name . '" -> ' . $e->getMessage() . "\n";
    }
}

// ─── Lead-Magnet-Listen seeden ─────
echo "\n─── Seede Lead-Magnet-Listen ───\n";
$leadmagnete = [
    ['Lead-Magnet: Kontaktformular',  'Hat das Kontaktformular ausgefüllt'],
    ['Lead-Magnet: Strategie-Check',  'Hat den Strategie-Check angefragt'],
    ['Lead-Magnet: Terminbuchung',    'Hat einen Termin gebucht'],
    ['Lead-Magnet: Portal-Videos',    'Hat Portal-Videos angeschaut'],
    ['Lead-Magnet: Podcast-Alarm',    'Hat Podcast-Alarm abonniert'],
    ['Lead-Magnet: Konferenz-Infos',  'Hat Konferenz-Infos angefragt'],
    ['Lead-Magnet: Branding-Check',   'Hat den Branding-Check angefragt'],
    ['Lead-Magnet: IHK-Workshop',     'Hat sich für IHK-Workshop angemeldet'],
];
foreach ($leadmagnete as [$name, $beschreibung]) {
    try {
        $vorhanden = $db->queryValue("SELECT id FROM crm_listen WHERE name = ?", [$name]);
        if ($vorhanden) {
            echo '  . "' . $name . "\" existiert bereits\n";
            continue;
        }
        $id = $db->insert('crm_listen', [
            'name' => $name,
            'beschreibung' => $beschreibung,
            'anzahl_aktive' => 0,
            'archiviert' => 0,
        ]);
        echo '  + "' . $name . "\" angelegt\n";
    } catch (\Throwable $e) {
        echo '  ! "' . $name . '" -> ' . $e->getMessage() . "\n";
    }
}

echo "\n─── Migration 002 abgeschlossen ───\n";
