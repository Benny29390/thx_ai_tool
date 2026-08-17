<?php
/**
 * modules.php — zentrale Modul-Registry (Single Source of Truth).
 *
 * Jedes Modul buendelt eine oder mehrere Capabilities (CAP_*) zu einer
 * installationsweit schaltbaren Einheit. Diese Datei ist rein deklarativ:
 * Sie beschreibt die Module, ohne Routen umzuschreiben. Das eigentliche Gate
 * sitzt in core/Modules.php und greift an genau zwei zentralen Stellen
 * (capMiddleware in core/App.php + capRules-Schleife in api/handler.php).
 *
 * Felder pro Modul:
 *   label        Anzeigename
 *   beschreibung Kurztext fuer die Admin-UI
 *   caps         Liste der zugehoerigen CAP_*-Konstanten (muessen eindeutig sein)
 *   gruppe       Sidebar-/UI-Gruppe (analog Auth::CAP_META)
 *   icon         Material-Symbol-Name (nur fuer die Admin-UI)
 *   externals    externe Abhaengigkeiten ('whisper','qdrant','ffmpeg','yt-dlp','cron:<datei>')
 *   deps         andere Modul-Keys, die aktiv sein muessen
 *   core         true => Kernmodul, NIE abschaltbar (Verwaltung/Grundbetrieb)
 *
 * RUECKWAERTSKOMPATIBILITAET: Ohne Eintrag in der Tabelle module_state gilt jedes
 * Modul als aktiv. core=true-Module sind IMMER aktiv, unabhaengig von Lizenz und
 * module_state — so kann sich niemand aus Verwaltung/Einstellungen aussperren.
 */

return [
    // ===== Werkzeuge fuers Schreiben =====
    'chat' => [
        'label' => 'Chat',
        'beschreibung' => 'Texte schreiben mit KI',
        'caps' => [CAP_CHAT],
        'gruppe' => 'Werkzeuge fürs Schreiben',
        'icon' => 'chat',
        'externals' => [],
        'deps' => [],
        'core' => false,
    ],
    'knowledge' => [
        'label' => 'Wissen',
        'beschreibung' => 'Kundenspezifisches Wissen pflegen (Wissensdatenbank, RAG)',
        'caps' => [CAP_KNOWLEDGE],
        'gruppe' => 'Werkzeuge fürs Schreiben',
        'icon' => 'menu_book',
        'externals' => [],
        'deps' => [],
        'core' => false,
    ],
    'artifacts' => [
        'label' => 'Artefakte',
        'beschreibung' => 'Wissensbasis (Regeln, Profile, Autoren) pflegen',
        'caps' => [CAP_ARTIFACTS],
        'gruppe' => 'Werkzeuge fürs Schreiben',
        'icon' => 'category',
        'externals' => [],
        'deps' => [],
        'core' => false,
    ],
    'coworker' => [
        'label' => 'Coworker',
        'beschreibung' => 'KI-Agent mit Werkzeugen',
        'caps' => [CAP_COWORKER],
        'gruppe' => 'Werkzeuge fürs Schreiben',
        'icon' => 'smart_toy',
        'externals' => [],
        'deps' => [],
        'core' => false,
    ],
    'ki_mitarbeiter' => [
        'label' => 'KI-Mitarbeiter',
        'beschreibung' => 'Spezialisierte KI-Mitarbeiter im Sparring entwerfen, testen und führen',
        'caps' => [CAP_KI_MITARBEITER],
        'gruppe' => 'Werkzeuge fürs Schreiben',
        'icon' => 'badge',
        'externals' => [],
        'deps' => [],
        'core' => false,
    ],
    'transcription' => [
        'label' => 'Transkription',
        'beschreibung' => 'Audio/Video transkribieren, Protokolle ableiten, in Wissen einspeisen',
        'caps' => [CAP_TRANSCRIPTION],
        'gruppe' => 'Werkzeuge fürs Schreiben',
        'icon' => 'graphic_eq',
        'externals' => ['whisper', 'ffmpeg', 'yt-dlp', 'cron:ki-tool-transkription'],
        'deps' => [],
        'core' => false,
    ],

    // ===== Aufbau & Planung =====
    'projektplanner' => [
        'label' => 'Projektplanner',
        'beschreibung' => 'Asana-Projekte einsehen und planen',
        'caps' => [CAP_PROJEKTPLANNER],
        'gruppe' => 'Aufbau & Planung',
        'icon' => 'account_tree',
        'externals' => ['cron:ki-tool-planner-sync', 'cron:ki-tool-pp-notifications'],
        'deps' => [],
        'core' => false,
    ],
    'lam' => [
        'label' => 'LAM-System',
        'beschreibung' => 'Linkaufbau-Management',
        'caps' => [CAP_LAM],
        'gruppe' => 'Aufbau & Planung',
        'icon' => 'link',
        'externals' => ['cron:ki-tool-lam-monitoring', 'cron:ki-tool-lam-erreichbarkeit'],
        'deps' => [],
        'core' => false,
    ],
    'mail' => [
        'label' => 'Mail-Tool',
        'beschreibung' => 'Posteingang, KI-Klassifikation, Antwort-Editor',
        'caps' => [CAP_MAIL],
        'gruppe' => 'Aufbau & Planung',
        'icon' => 'mail',
        'externals' => ['cron:ki-tool-mail-pull', 'cron:ki-tool-mail-ordner'],
        'deps' => [],
        'core' => false,
    ],
    'site_monitor' => [
        'label' => 'Website-Monitor',
        'beschreibung' => 'Website-Verfügbarkeit überwachen',
        'caps' => [CAP_SITE_MONITOR],
        'gruppe' => 'Aufbau & Planung',
        'icon' => 'monitor_heart',
        'externals' => ['cron:ki-tool-site-monitor'],
        'deps' => [],
        'core' => false,
    ],

    // ===== CRM =====
    'crm' => [
        'label' => 'Kontakte (CRM)',
        'beschreibung' => 'Kontakte, Firmen, Vokabular, Migration und DSGVO-Tools',
        'caps' => [CAP_CRM, CAP_CRM_VOKABULAR, CAP_CRM_MIGRATION, CAP_CRM_DSGVO],
        'gruppe' => 'CRM',
        'icon' => 'contacts',
        'externals' => ['cron:ki-tool-crm'],
        'deps' => [],
        'core' => false,
    ],

    // ===== KI & Modelle =====
    'prompt_insights' => [
        'label' => 'Prompt-Insights',
        'beschreibung' => 'Eigene Chatverläufe importieren, Muster erkennen, Spielregeln ableiten',
        'caps' => [CAP_PROMPT_INSIGHTS],
        'gruppe' => 'KI & Modelle',
        'icon' => 'insights',
        'externals' => [],
        'deps' => [],
        'core' => false,
    ],

    // ===== Administration (schaltbar, aber kein Kernbetrieb) =====
    'firewall' => [
        'label' => 'Firewall / IP-Sperren',
        'beschreibung' => 'Von fail2ban gesperrte IPs ansehen und entsperren',
        'caps' => [CAP_FIREWALL],
        'gruppe' => 'Administration',
        'icon' => 'shield',
        'externals' => ['cron:ki-tool-firewall'],
        'deps' => [],
        'core' => false,
    ],
    'styleguide' => [
        'label' => 'Styleguide',
        'beschreibung' => 'Corporate-Design-Styleguide ansehen',
        'caps' => [CAP_STYLEGUIDE],
        'gruppe' => 'Administration',
        'icon' => 'palette',
        'externals' => [],
        'deps' => [],
        'core' => false,
    ],

    // ===== KERNMODULE (nie abschaltbar) =====
    'customers' => [
        'label' => 'Kunden',
        'beschreibung' => 'Kundenverwaltung (Steckbrief, Zuordnung) — Grundbetrieb',
        'caps' => [CAP_CUSTOMERS_VIEW, CAP_CUSTOMERS_MANAGE],
        'gruppe' => 'Kunden',
        'icon' => 'groups',
        'externals' => [],
        'deps' => [],
        'core' => true,
    ],
    'users' => [
        'label' => 'Benutzer & Rechte',
        'beschreibung' => 'Benutzer, Rollen, Capabilities — Grundbetrieb',
        'caps' => [CAP_USERS_MANAGE],
        'gruppe' => 'Administration',
        'icon' => 'manage_accounts',
        'externals' => [],
        'deps' => [],
        'core' => true,
    ],
    'settings' => [
        'label' => 'Einstellungen',
        'beschreibung' => 'API-Keys, SMTP, System — Grundbetrieb',
        'caps' => [CAP_SETTINGS_MANAGE],
        'gruppe' => 'Administration',
        'icon' => 'settings',
        'externals' => [],
        'deps' => [],
        'core' => true,
    ],
];
