<?php
/**
 * Authentifizierung und Rollenverwaltung
 * Unterstuetzt mehrere Kunden pro Benutzer
 */

namespace Core;

class Auth
{
    private static ?array $user = null;
    private static ?array $customers = null;
    private static ?int $activeCustomerId = null;
    /** @var string[]|null Capabilities des aktuellen Users (DB user_capabilities). */
    private static ?array $capabilities = null;
    private static Database $db;

    /**
     * Default-Capabilities pro Rolle. Wird beim User-Anlegen und Rollen-Wechsel
     * als Startwert verwendet — Admin kann pro User individuell ueberschreiben.
     */
    public const DEFAULT_CAPS = [
        ROLE_ADMIN => [
            CAP_CHAT, CAP_ARTIFACTS, CAP_KNOWLEDGE, CAP_COWORKER, CAP_LAM,
            CAP_PROJEKTPLANNER, CAP_MAIL, CAP_SITE_MONITOR, CAP_PROMPT_INSIGHTS, CAP_TRANSCRIPTION,
            CAP_CUSTOMERS_VIEW, CAP_CUSTOMERS_MANAGE,
            CAP_CRM, CAP_CRM_DSGVO, CAP_CRM_MIGRATION, CAP_CRM_VOKABULAR,
            CAP_USERS_MANAGE, CAP_SETTINGS_MANAGE, CAP_FIREWALL, CAP_STYLEGUIDE,
        ],
        ROLE_MANAGER => [
            CAP_CHAT, CAP_ARTIFACTS, CAP_KNOWLEDGE, CAP_COWORKER, CAP_LAM,
            CAP_PROJEKTPLANNER, CAP_MAIL, CAP_SITE_MONITOR, CAP_PROMPT_INSIGHTS, CAP_TRANSCRIPTION,
            CAP_CUSTOMERS_VIEW, CAP_CUSTOMERS_MANAGE,
            CAP_CRM, CAP_CRM_MIGRATION, CAP_CRM_VOKABULAR, CAP_STYLEGUIDE,
        ],
        ROLE_USER => [
            CAP_CHAT, CAP_ARTIFACTS, CAP_KNOWLEDGE, CAP_COWORKER,
            CAP_PROJEKTPLANNER, CAP_CUSTOMERS_VIEW,
            CAP_CRM,
        ],
        ROLE_GUEST => [
            CAP_CUSTOMERS_VIEW,
        ],
        // Customer-Portal-Nutzer: KEINE Team-Caps. Zugriff ausschliesslich ueber
        // die Portal-Permission-Matrix + serverseitige Tenant-Isolation.
        ROLE_CUSTOMER => [],
    ];

    /**
     * Alle bekannten Capabilities — Reihenfolge entspricht dem Sidebar-Menue,
     * damit die UI konsistent ist (Kunden → Tools → Planung → Verwaltung).
     */
    public const ALL_CAPS = [
        CAP_CUSTOMERS_VIEW, CAP_CUSTOMERS_MANAGE,
        CAP_CHAT, CAP_KNOWLEDGE, CAP_ARTIFACTS, CAP_COWORKER, CAP_TRANSCRIPTION,
        CAP_PROJEKTPLANNER, CAP_LAM, CAP_MAIL, CAP_SITE_MONITOR,
        CAP_CRM, CAP_CRM_VOKABULAR, CAP_CRM_MIGRATION, CAP_CRM_DSGVO,
        CAP_PROMPT_INSIGHTS,
        CAP_USERS_MANAGE, CAP_SETTINGS_MANAGE, CAP_FIREWALL, CAP_STYLEGUIDE,
    ];

    /**
     * Zentrale Cap-Metadaten: Anzeige-Name, Beschreibung, Gruppe.
     * Single Source of Truth für Rollen-Verwaltung, Sidebar, User-Edit.
     * Neue Capability → einfach hier ergänzen (in derselben Reihenfolge wie ALL_CAPS).
     * Gruppe wird automatisch in der Rollen-Matrix als Sektion gerendert.
     */
    public const CAP_META = [
        CAP_CUSTOMERS_VIEW   => ['gruppe' => 'Kunden', 'label' => 'Kunden ansehen', 'beschreibung' => 'Eigene zugewiesene Kunden lesen (Steckbrief, Wissen)'],
        CAP_CUSTOMERS_MANAGE => ['gruppe' => 'Kunden', 'label' => 'Kunden verwalten', 'beschreibung' => 'Kunden anlegen, bearbeiten, deaktivieren'],

        CAP_CHAT      => ['gruppe' => 'Werkzeuge fürs Schreiben', 'label' => 'Chat', 'beschreibung' => 'Texte schreiben mit KI'],
        CAP_KNOWLEDGE => ['gruppe' => 'Werkzeuge fürs Schreiben', 'label' => 'Wissen', 'beschreibung' => 'Kundenspezifisches Wissen pflegen'],
        CAP_ARTIFACTS => ['gruppe' => 'Werkzeuge fürs Schreiben', 'label' => 'Artefakte', 'beschreibung' => 'Wissensbasis (Regeln, Profile, Autoren) pflegen'],
        CAP_COWORKER  => ['gruppe' => 'Werkzeuge fürs Schreiben', 'label' => 'Coworker', 'beschreibung' => 'KI-Agent mit Werkzeugen'],
        CAP_TRANSCRIPTION => ['gruppe' => 'Werkzeuge fürs Schreiben', 'label' => 'Transkription', 'beschreibung' => 'Audio/Video transkribieren, Protokolle ableiten, in Wissen einspeisen'],

        CAP_PROJEKTPLANNER => ['gruppe' => 'Aufbau & Planung', 'label' => 'Projektplanner', 'beschreibung' => 'Asana-Projekte einsehen und planen'],
        CAP_LAM            => ['gruppe' => 'Aufbau & Planung', 'label' => 'LAM-System', 'beschreibung' => 'Linkaufbau-Management'],
        CAP_MAIL           => ['gruppe' => 'Aufbau & Planung', 'label' => 'Mail-Tool', 'beschreibung' => 'Posteingang, KI-Klassifikation, Antwort-Editor'],
        CAP_SITE_MONITOR   => ['gruppe' => 'Aufbau & Planung', 'label' => 'Website-Monitor', 'beschreibung' => 'Website-Verfügbarkeit überwachen'],

        CAP_CRM            => ['gruppe' => 'CRM', 'label' => 'Kontakte (CRM)', 'beschreibung' => 'Kontakte und Firmen lesen + pflegen'],
        CAP_CRM_VOKABULAR  => ['gruppe' => 'CRM', 'label' => 'Vokabular pflegen', 'beschreibung' => 'Tags, Listen, Branchen, Lead-Magnete verwalten'],
        CAP_CRM_MIGRATION  => ['gruppe' => 'CRM', 'label' => 'Migration', 'beschreibung' => 'Import aus Brevo starten + Audit sehen'],
        CAP_CRM_DSGVO      => ['gruppe' => 'CRM', 'label' => 'DSGVO-Tools', 'beschreibung' => 'Datenauskunft erstellen, Hard-Delete ausführen'],

        CAP_PROMPT_INSIGHTS => ['gruppe' => 'KI & Modelle', 'label' => 'Prompt-Insights', 'beschreibung' => 'Eigene Chatverläufe importieren, Muster erkennen, Spielregeln ableiten'],

        CAP_USERS_MANAGE    => ['gruppe' => 'Administration', 'label' => 'Benutzer', 'beschreibung' => 'Rechte vergeben (de facto Admin-Funktion)'],
        CAP_SETTINGS_MANAGE => ['gruppe' => 'Administration', 'label' => 'Einstellungen', 'beschreibung' => 'API-Keys, SMTP, Sistrix etc.'],
        CAP_FIREWALL        => ['gruppe' => 'Administration', 'label' => 'Firewall / IP-Sperren', 'beschreibung' => 'Von fail2ban gesperrte IPs ansehen und entsperren'],
        CAP_STYLEGUIDE      => ['gruppe' => 'Administration', 'label' => 'Styleguide', 'beschreibung' => 'Thoxan Corporate-Design-Styleguide ansehen'],
    ];

    /**
     * Returnt Cap-Meta gruppiert für die Rollen-Matrix.
     * Falls eine in ALL_CAPS gelistete Capability NICHT in CAP_META steht
     * (z.B. neu hinzugefügt, aber noch kein Eintrag), bekommt sie eine
     * generische Default-Beschreibung — niemals fehlen sie still in der UI.
     */
    public static function capGroups(): array
    {
        $gruppen = [];
        foreach (self::ALL_CAPS as $cap) {
            $meta = self::CAP_META[$cap] ?? [
                'gruppe' => 'Sonstige',
                'label' => $cap,
                'beschreibung' => '(keine Beschreibung gepflegt — in Auth::CAP_META ergänzen)',
            ];
            $gruppen[$meta['gruppe']][$cap] = [$meta['label'], $meta['beschreibung']];
        }
        return $gruppen;
    }

    /**
     * Initialisieren
     */
    public static function init(Database $db): void
    {
        self::$db = $db;
        Session::start();

        // Benutzer aus Session laden
        $userId = Session::get('user_id');
        if ($userId) {
            self::loadUser($userId);
            // Aktiven Kunden aus Session laden
            self::$activeCustomerId = Session::get('active_customer_id');
            // Letzte Aktivitaet pflegen (gedrosselt: max. alle 5 Min ein DB-Write). Sessions bleiben
            // bestehen, daher misst 'last_login' NICHT, wie lange jemand wirklich weg war — dafuer
            // 'last_activity'. Speist den Inaktivitaets-Job.
            if (self::$user) {
                $lastWrite = (int) Session::get('last_activity_written', 0);
                if (time() - $lastWrite > 300) {
                    try { self::$db->execute("UPDATE users SET last_activity = NOW() WHERE id = ?", [(int)$userId]); } catch (\Throwable $e) {}
                    Session::set('last_activity_written', time());
                }
            }
        }
    }

    /**
     * Benutzer aus DB laden
     */
    /**
     * Wie loadUser(), aber oeffentlich — fuer Token-Auth-Pfade die ohne Session laufen.
     * Kein Session-Schreiben, nur In-Memory-Setup fuer den aktuellen Request.
     */
    public static function initFromUserId(int $userId): void
    {
        self::loadUser($userId);
    }

    private static function loadUser(int $userId): void
    {
        $user = self::$db->queryOne(
            "SELECT * FROM users WHERE id = ? AND is_active = 1",
            [$userId]
        );

        if ($user) {
            unset($user['password_hash']);
            self::$user = $user;

            // Kunden des Benutzers laden
            self::loadUserCustomers($userId);
            // Capabilities laden
            self::loadCapabilities($userId);
        } else {
            self::logout();
        }
    }

    /**
     * Capabilities des Users aus DB laden. Admin bekommt automatisch
     * ALLE Caps (auch wenn nichts in user_capabilities steht) — Sicherheitsnetz,
     * damit ein Admin nie versehentlich ausgesperrt wird.
     */
    private static function loadCapabilities(int $userId): void
    {
        $rows = self::$db->query(
            "SELECT capability FROM user_capabilities WHERE user_id = ?",
            [$userId]
        );
        $caps = [];
        foreach ($rows as $r) $caps[] = $r['capability'];

        // Admin-Schutzschicht: Admins haben implizit alle Caps
        if ((self::$user['role'] ?? '') === ROLE_ADMIN) {
            $caps = array_unique(array_merge($caps, self::ALL_CAPS));
        }
        self::$capabilities = $caps;
    }

    /**
     * Kunden des Benutzers laden
     */
    private static function loadUserCustomers(int $userId): void
    {
        // Effektive Kundenliste eines Users = UNION aus
        //   1) direkter Zuweisung (user_customers)
        //   2) Rollen-basierter Freischaltung (role_customers fuer die Rolle des Users)
        // is_default bleibt erhalten, wenn der User direkt zugewiesen ist.
        $role = self::$user['role'] ?? '';
        self::$customers = self::$db->query(
            "SELECT c.*, COALESCE(MAX(uc.is_default), 0) AS is_default
             FROM customers c
             LEFT JOIN user_customers uc ON uc.customer_id = c.id AND uc.user_id = ?
             LEFT JOIN role_customers  rc ON rc.customer_id = c.id AND rc.role    = ?
             WHERE (uc.user_id IS NOT NULL OR rc.role IS NOT NULL)
               AND c.is_active = 1
             GROUP BY c.id
             ORDER BY is_default DESC, c.name",
            [$userId, $role]
        );

        // Falls kein aktiver Kunde gesetzt, Standard-Kunde waehlen
        if (self::$activeCustomerId === null && !empty(self::$customers)) {
            // Erst Default-Kunde, dann erster in der Liste
            foreach (self::$customers as $c) {
                if ($c['is_default']) {
                    self::$activeCustomerId = (int) $c['id'];
                    break;
                }
            }
            if (self::$activeCustomerId === null) {
                self::$activeCustomerId = (int) self::$customers[0]['id'];
            }
            Session::set('active_customer_id', self::$activeCustomerId);
        }

        // Fuer Abwaertskompatibilitaet: customer_id im User-Array setzen
        if (self::$activeCustomerId && self::$user) {
            self::$user['customer_id'] = self::$activeCustomerId;
            // Kunden-Info hinzufuegen
            foreach (self::$customers as $c) {
                if ((int) $c['id'] === self::$activeCustomerId) {
                    self::$user['customer_name'] = $c['name'];
                    self::$user['customer_slug'] = $c['slug'];
                    break;
                }
            }
        }
    }

    /**
     * Login mit Brute-Force-Schutz.
     * Ab 5 fehlgeschlagenen Versuchen pro E-Mail in den letzten 15 Min → 15-Min-Sperre.
     */
    public const LOGIN_MAX_FAILED   = 5;
    public const LOGIN_WINDOW_MIN   = 15;

    public static function login(string $email, string $password): array
    {
        $email = trim((string)$email);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // === Rate-Limit-Pruefung ===
        try {
            $failed = (int)self::$db->queryValue(
                "SELECT COUNT(*) FROM login_attempts
                 WHERE email = ? AND success = 0
                   AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$email, self::LOGIN_WINDOW_MIN]
            );
            if ($failed >= self::LOGIN_MAX_FAILED) {
                // Naechster erlaubter Versuch berechnen
                $oldest = self::$db->queryValue(
                    "SELECT MIN(occurred_at) FROM login_attempts
                     WHERE email = ? AND success = 0
                       AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                    [$email, self::LOGIN_WINDOW_MIN]
                );
                $waitMin = max(1, (int)ceil(self::LOGIN_WINDOW_MIN - ((time() - strtotime((string)$oldest)) / 60)));
                return [
                    'success' => false,
                    'rate_limited' => true,
                    'wait_minutes' => $waitMin,
                    'message' => "Zu viele Fehlversuche. Bitte in {$waitMin} Min erneut probieren.",
                ];
            }
        } catch (\Throwable $e) {
            // Tabelle existiert evtl. nicht — Login nicht blockieren
        }

        $logAttempt = function (bool $success) use ($email, $ip) {
            try {
                self::$db->insert('login_attempts', [
                    'email'   => $email,
                    'ip'      => $ip,
                    'success' => $success ? 1 : 0,
                ]);
            } catch (\Throwable $e) {}
        };

        $user = self::$db->queryOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            $logAttempt(false);
            return ['success' => false, 'message' => 'E-Mail oder Passwort falsch'];
        }

        if (!$user['is_active']) {
            $logAttempt(false);
            return ['success' => false, 'message' => 'Benutzer ist deaktiviert'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $logAttempt(false);
            return ['success' => false, 'message' => 'E-Mail oder Passwort falsch'];
        }

        // Login-Daten valide → fail-Counts dieser E-Mail loeschen
        try {
            self::$db->execute(
                "DELETE FROM login_attempts WHERE email = ? AND success = 0",
                [$email]
            );
            $logAttempt(true);
        } catch (\Throwable $e) {}

        // Kunden-Check entfernt — Manager koennen ohne Kunden-Zuordnung arbeiten

        // 2FA pruefen
        if (!empty($user['two_factor_enabled']) && !empty($user['two_factor_confirmed_at'])) {
            // 2FA aktiv - noch nicht einloggen, sondern 2FA-Verifizierung anfordern
            Session::regenerate();
            Session::set('pending_2fa_user_id', $user['id']);
            return [
                'success' => true,
                'requires_2fa' => true,
                'message' => 'Bitte 2FA-Code eingeben'
            ];
        }

        // Login erfolgreich (ohne 2FA)
        return self::completeLogin($user);
    }

    /**
     * Login abschliessen (nach erfolgreicher Passwort- und ggf. 2FA-Pruefung)
     */
    private static function completeLogin(array $user): array
    {
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::remove('pending_2fa_user_id');

        // Last Login aktualisieren
        self::$db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );

        unset($user['password_hash']);
        self::$user = $user;

        // Kunden laden
        self::loadUserCustomers($user['id']);

        return ['success' => true, 'user' => self::$user];
    }

    /**
     * 2FA-Code verifizieren und Login abschliessen
     */
    public static function verify2FA(string $code): array
    {
        $userId = Session::get('pending_2fa_user_id');
        if (!$userId) {
            return ['success' => false, 'message' => 'Keine 2FA-Verifizierung ausstehend'];
        }

        $user = self::$db->queryOne(
            "SELECT * FROM users WHERE id = ? AND is_active = 1",
            [$userId]
        );

        if (!$user) {
            Session::remove('pending_2fa_user_id');
            return ['success' => false, 'message' => 'Benutzer nicht gefunden'];
        }

        // TOTP-Code pruefen
        if (!TwoFactorAuth::verifyCode($user['two_factor_secret'], $code)) {
            return ['success' => false, 'message' => 'Ungueltiger Code'];
        }

        // Login abschliessen
        return self::completeLogin($user);
    }

    /**
     * Backup-Code verifizieren und verbrauchen
     */
    public static function verifyBackupCode(string $code): array
    {
        $userId = Session::get('pending_2fa_user_id');
        if (!$userId) {
            return ['success' => false, 'message' => 'Keine 2FA-Verifizierung ausstehend'];
        }

        $user = self::$db->queryOne(
            "SELECT * FROM users WHERE id = ? AND is_active = 1",
            [$userId]
        );

        if (!$user) {
            Session::remove('pending_2fa_user_id');
            return ['success' => false, 'message' => 'Benutzer nicht gefunden'];
        }

        // Backup-Codes laden
        $backupCodes = json_decode($user['two_factor_backup_codes'] ?? '[]', true);
        if (empty($backupCodes)) {
            return ['success' => false, 'message' => 'Keine Backup-Codes vorhanden'];
        }

        // Code pruefen
        $usedIndex = TwoFactorAuth::verifyBackupCode($code, $backupCodes);
        if ($usedIndex < 0) {
            return ['success' => false, 'message' => 'Ungueltiger Backup-Code'];
        }

        // Code als verbraucht markieren (auf null setzen)
        $backupCodes[$usedIndex] = null;
        self::$db->execute(
            "UPDATE users SET two_factor_backup_codes = ? WHERE id = ?",
            [json_encode($backupCodes), $userId]
        );

        // Login abschliessen
        return self::completeLogin($user);
    }

    /**
     * Hat 2FA ausstehend?
     */
    public static function hasPending2FA(): bool
    {
        return Session::get('pending_2fa_user_id') !== null;
    }

    /**
     * Hat Benutzer 2FA aktiviert?
     */
    public static function has2FAEnabled(?int $userId = null): bool
    {
        if ($userId === null) {
            $user = self::$user;
        } else {
            $user = self::$db->queryOne("SELECT two_factor_enabled, two_factor_confirmed_at FROM users WHERE id = ?", [$userId]);
        }

        return !empty($user['two_factor_enabled']) && !empty($user['two_factor_confirmed_at']);
    }

    /**
     * 2FA-Setup starten
     */
    public static function setup2FA(): array
    {
        if (!self::$user) {
            return ['success' => false, 'message' => 'Nicht eingeloggt'];
        }

        // Secret generieren
        $secret = TwoFactorAuth::generateSecret();

        // Secret temporaer in Session speichern (noch nicht in DB)
        Session::set('2fa_setup_secret', $secret);

        // QR-Code URL generieren
        $otpauthUrl = TwoFactorAuth::getQRCodeUrl($secret, self::$user['email']);
        $qrCodeUrl = TwoFactorAuth::getQRCodeImageUrl($otpauthUrl);

        return [
            'success' => true,
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'otpauth_url' => $otpauthUrl
        ];
    }

    /**
     * 2FA mit Code bestaetigen und aktivieren
     */
    public static function confirm2FA(string $code): array
    {
        if (!self::$user) {
            return ['success' => false, 'message' => 'Nicht eingeloggt'];
        }

        $secret = Session::get('2fa_setup_secret');
        if (!$secret) {
            return ['success' => false, 'message' => 'Kein 2FA-Setup gestartet'];
        }

        // Code verifizieren
        if (!TwoFactorAuth::verifyCode($secret, $code)) {
            return ['success' => false, 'message' => 'Ungueltiger Code'];
        }

        // Backup-Codes generieren
        $backupCodes = TwoFactorAuth::generateBackupCodes();
        $hashedCodes = TwoFactorAuth::hashBackupCodes($backupCodes);

        // 2FA aktivieren
        self::$db->execute(
            "UPDATE users SET
                two_factor_secret = ?,
                two_factor_enabled = 1,
                two_factor_backup_codes = ?,
                two_factor_confirmed_at = NOW()
             WHERE id = ?",
            [$secret, json_encode($hashedCodes), self::$user['id']]
        );

        // Session aufraumen
        Session::remove('2fa_setup_secret');

        // User-Objekt aktualisieren
        self::$user['two_factor_enabled'] = 1;
        self::$user['two_factor_confirmed_at'] = date('Y-m-d H:i:s');

        return [
            'success' => true,
            'message' => '2FA erfolgreich aktiviert',
            'backup_codes' => $backupCodes
        ];
    }

    /**
     * 2FA deaktivieren
     */
    public static function disable2FA(string $password): array
    {
        if (!self::$user) {
            return ['success' => false, 'message' => 'Nicht eingeloggt'];
        }

        // Passwort pruefen
        $user = self::$db->queryOne(
            "SELECT password_hash FROM users WHERE id = ?",
            [self::$user['id']]
        );

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Falsches Passwort'];
        }

        // 2FA deaktivieren
        self::$db->execute(
            "UPDATE users SET
                two_factor_secret = NULL,
                two_factor_enabled = 0,
                two_factor_backup_codes = NULL,
                two_factor_confirmed_at = NULL
             WHERE id = ?",
            [self::$user['id']]
        );

        // User-Objekt aktualisieren
        self::$user['two_factor_enabled'] = 0;
        self::$user['two_factor_confirmed_at'] = null;

        return ['success' => true, 'message' => '2FA deaktiviert'];
    }

    /**
     * Anzahl verbleibender Backup-Codes
     */
    public static function getRemainingBackupCodes(): int
    {
        if (!self::$user) {
            return 0;
        }

        $user = self::$db->queryOne(
            "SELECT two_factor_backup_codes FROM users WHERE id = ?",
            [self::$user['id']]
        );

        $codes = json_decode($user['two_factor_backup_codes'] ?? '[]', true);
        return count(array_filter($codes, fn($c) => $c !== null));
    }

    /**
     * Aktiven Kunden wechseln
     */
    public static function switchCustomer(int $customerId): bool
    {
        // Pruefen ob Benutzer Zugriff auf diesen Kunden hat
        if (!self::canAccessCustomer($customerId)) {
            return false;
        }

        self::$activeCustomerId = $customerId;
        Session::set('active_customer_id', $customerId);

        // User-Array aktualisieren
        if (self::$user) {
            self::$user['customer_id'] = $customerId;
            foreach (self::$customers ?? [] as $c) {
                if ((int) $c['id'] === $customerId) {
                    self::$user['customer_name'] = $c['name'];
                    self::$user['customer_slug'] = $c['slug'];
                    break;
                }
            }
        }

        return true;
    }

    /**
     * Logout
     */
    public static function logout(): void
    {
        self::$user = null;
        self::$customers = null;
        self::$activeCustomerId = null;
        self::$capabilities = null;
        Session::destroy();
    }

    /**
     * Admin-Feature: in die Sicht eines anderen Users wechseln.
     * Die echte Admin-ID wird in der Session als 'original_admin_id' festgehalten,
     * damit `switchBack()` wieder zurueckspringen kann.
     *
     * Schreibaktionen wirken danach wie ein normaler Login als der Ziel-User —
     * z.B. neu angelegte Konversationen gehoeren dem Ziel-User. Das ist
     * gewollt, damit man einen Test-Account sauber befuellen kann.
     */
    public static function loginAs(int $targetUserId): array
    {
        if (!self::isAdmin()) {
            return ['success' => false, 'message' => 'Nur Admins duerfen die Sicht wechseln.'];
        }
        $target = self::$db->queryOne(
            "SELECT id, email, name, role FROM users WHERE id = ? AND is_active = 1",
            [$targetUserId]
        );
        if (!$target) {
            return ['success' => false, 'message' => 'Ziel-User nicht gefunden oder inaktiv.'];
        }
        if ((int)$target['id'] === (int)self::id()) {
            return ['success' => false, 'message' => 'Du bist bereits dieser User.'];
        }

        // Original-Admin-ID merken (nur, wenn noch nicht in einer Simulation)
        if (!Session::get('original_admin_id')) {
            Session::set('original_admin_id', (int)self::id());
        }
        // Aktiven Kunden zuruecksetzen, damit der Ziel-User seinen Default bekommt
        Session::remove('active_customer_id');
        Session::set('user_id', (int)$target['id']);

        // State neu laden
        self::$user = null;
        self::$customers = null;
        self::$activeCustomerId = null;
        self::$capabilities = null;
        self::loadUser((int)$target['id']);

        return ['success' => true, 'user' => $target];
    }

    /**
     * Zurueck zur echten Admin-Sicht.
     */
    public static function switchBack(): array
    {
        $originalId = (int)(Session::get('original_admin_id') ?? 0);
        if ($originalId <= 0) {
            return ['success' => false, 'message' => 'Keine aktive Simulation.'];
        }
        Session::remove('original_admin_id');
        Session::remove('active_customer_id');
        Session::set('user_id', $originalId);

        self::$user = null;
        self::$customers = null;
        self::$activeCustomerId = null;
        self::$capabilities = null;
        self::loadUser($originalId);

        return ['success' => true, 'user' => self::$user];
    }

    /**
     * Laeuft gerade eine Admin-Simulation? Liefert die echte Admin-ID
     * zurueck, sonst null.
     */
    public static function originalAdminId(): ?int
    {
        $id = Session::get('original_admin_id');
        return $id ? (int)$id : null;
    }

    /**
     * Helper fuer Views: TRUE wenn der aktuell sichtbare User durch
     * eine Admin-Simulation erreicht wurde.
     */
    public static function isSimulating(): bool
    {
        return self::originalAdminId() !== null;
    }

    /**
     * Eingeloggt?
     */
    public static function check(): bool
    {
        return self::$user !== null;
    }

    /**
     * Aktueller Benutzer
     */
    public static function user(): ?array
    {
        return self::$user;
    }

    /**
     * Benutzer-ID
     */
    public static function id(): ?int
    {
        return self::$user['id'] ?? null;
    }

    /**
     * Rolle des Benutzers
     */
    public static function role(): ?string
    {
        return self::$user['role'] ?? null;
    }

    /**
     * Aktive Kunden-ID des Benutzers
     */
    public static function customerId(): ?int
    {
        // Admins ohne zugewiesene Kunden haben keine customer_id
        if (self::isAdmin() && empty(self::$customers)) {
            return null;
        }
        return self::$activeCustomerId;
    }

    /**
     * Alle Kunden des Benutzers
     */
    public static function customers(): array
    {
        return self::$customers ?? [];
    }

    /**
     * Hat Benutzer mehrere Kunden?
     */
    public static function hasMultipleCustomers(): bool
    {
        return count(self::$customers ?? []) > 1;
    }

    /**
     * Aktiver Kunde
     */
    public static function activeCustomer(): ?array
    {
        if (!self::$activeCustomerId) {
            return null;
        }
        foreach (self::$customers ?? [] as $c) {
            if ((int) $c['id'] === self::$activeCustomerId) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Ist Admin?
     */
    public static function isAdmin(): bool
    {
        return (self::$user['role'] ?? '') === ROLE_ADMIN;
    }

    /**
     * Muss der aktuelle User 2FA noch einrichten? Admin/Manager-Pflicht.
     */
    public static function requires2FASetup(): bool
    {
        if (self::$user === null) return false;
        $role = self::$user['role'] ?? '';
        if ($role !== ROLE_ADMIN && $role !== ROLE_MANAGER) return false;
        $confirmed = !empty(self::$user['two_factor_enabled']) && !empty(self::$user['two_factor_confirmed_at']);
        return !$confirmed;
    }

    /**
     * Ist Manager?
     */
    public static function isManager(): bool
    {
        return (self::$user['role'] ?? '') === ROLE_MANAGER;
    }

    /**
     * Ist User? (Standard-Mitarbeiter)
     */
    public static function isUser(): bool
    {
        return (self::$user['role'] ?? '') === ROLE_USER;
    }

    /**
     * Ist Guest? (Lesemodus)
     */
    public static function isGuest(): bool
    {
        return (self::$user['role'] ?? '') === ROLE_GUEST;
    }

    /**
     * Ist Customer-Portal-Nutzer? (externer Kunde, an genau 1 Kunden gebunden)
     */
    public static function isCustomer(): bool
    {
        return (self::$user['role'] ?? '') === ROLE_CUSTOMER;
    }

    /**
     * Der eine Kunde, an den ein Customer-Portal-Nutzer gebunden ist.
     * Invariante: ein 'customer'-User hat genau 1 zugeordneten Kunden.
     * Liefert null, wenn der aktuelle User kein Customer ist oder (fehlerhaft) keine Zuordnung hat.
     */
    public static function portalCustomerId(): ?int
    {
        if (!self::isCustomer()) return null;
        $list = self::customers();
        if (empty($list)) return null;
        return (int) $list[0]['id'];
    }

    /**
     * Legacy: Editor-Stufe — entspricht heute 'user'.
     */
    public static function isEditor(): bool
    {
        return self::isUser();
    }

    /**
     * Hat mindestens Rolle X? Hierarchie: guest < user < manager < admin
     */
    public static function hasRole(string $minRole): bool
    {
        $roles = [ROLE_GUEST => 1, ROLE_USER => 2, ROLE_MANAGER => 3, ROLE_ADMIN => 4];
        $userLevel = $roles[self::role()] ?? 0;
        $minLevel = $roles[$minRole] ?? 0;
        return $userLevel >= $minLevel;
    }

    /**
     * Ist Manager oder hoeher? (Kernteam-Marker fuer „darf auch fremde Inhalte aufraeumen").
     */
    public static function isManagerOrHigher(): bool
    {
        return self::hasRole(ROLE_MANAGER);
    }

    /**
     * Soll der User als read-only behandelt werden? (Guest)
     * Schreibaktionen (POST/PUT/DELETE) sollten dann generisch blockiert werden.
     */
    public static function isReadOnly(): bool
    {
        return self::isGuest();
    }

    /**
     * Hat der User eine bestimmte Capability? Admin = immer true.
     */
    public static function can(string $capability): bool
    {
        if (self::$user === null) return false;
        if ((self::$user['role'] ?? '') === ROLE_ADMIN) return true;
        return in_array($capability, self::$capabilities ?? [], true);
    }

    /**
     * Capabilities des aktuellen Users als Liste.
     * @return string[]
     */
    public static function capabilities(): array
    {
        return self::$capabilities ?? [];
    }

    /**
     * Capabilities eines beliebigen Users abfragen (z.B. fuer Admin-UI).
     * @return string[]
     */
    public static function capabilitiesOf(int $userId): array
    {
        $rows = self::$db->query(
            "SELECT capability FROM user_capabilities WHERE user_id = ?",
            [$userId]
        );
        $out = [];
        foreach ($rows as $r) $out[] = $r['capability'];
        return $out;
    }

    /**
     * Capabilities eines Users komplett neu setzen (loescht alte, schreibt neue).
     * Loggt die Aenderung im Audit-Log.
     * @param string[] $caps
     */
    public static function setCapabilities(int $userId, array $caps, ?int $grantedBy = null): void
    {
        $before = self::capabilitiesOf($userId);
        self::$db->execute("DELETE FROM user_capabilities WHERE user_id = ?", [$userId]);
        $caps = array_values(array_unique(array_intersect($caps, self::ALL_CAPS)));
        foreach ($caps as $cap) {
            self::$db->insert('user_capabilities', [
                'user_id' => $userId,
                'capability' => $cap,
                'granted_by' => $grantedBy,
            ]);
        }
        sort($before); sort($caps);
        if ($before !== $caps) {
            AuditLog::record(AuditLog::TARGET_USER, (string)$userId, AuditLog::ACTION_CAPS_CHANGED, [
                'before' => $before, 'after' => $caps,
            ], $grantedBy);
        }
    }

    /**
     * Default-Capabilities einer Rolle als Liste.
     * Liest primaer aus der DB-Tabelle role_capabilities; falls leer/fehlt,
     * faellt zurueck auf die in der Konstante hinterlegten Defaults.
     * Admin bekommt IMMER alle in ALL_CAPS gelisteten Caps — neue Caps werden
     * still in der DB nachgetragen.
     * @return string[]
     */
    public static function defaultCapsFor(string $role): array
    {
        if ($role === ROLE_ADMIN) {
            self::synchronisiereAdminCaps();
            return self::ALL_CAPS;
        }
        try {
            $rows = self::$db->query(
                "SELECT capability FROM role_capabilities WHERE role = ?",
                [$role]
            );
            if (!empty($rows)) {
                return array_column($rows, 'capability');
            }
        } catch (\Throwable $e) {
            // Tabelle existiert moeglicherweise (noch) nicht — Fallback
        }
        return self::DEFAULT_CAPS[$role] ?? [];
    }

    /**
     * Default-Capabilities einer Rolle setzen — ueberschreibt komplett.
     * Admin: Input wird ignoriert, immer ALL_CAPS persistiert.
     * Loggt die Aenderung im Audit-Log.
     * @param string[] $caps
     */
    public static function setRoleDefaults(string $role, array $caps): void
    {
        $before = self::defaultCapsFor($role);
        if ($role === ROLE_ADMIN) {
            // Admin ist serverseitig immer voll berechtigt — Input ignorieren
            $caps = self::ALL_CAPS;
        } else {
            $caps = array_values(array_unique(array_intersect($caps, self::ALL_CAPS)));
        }
        self::$db->execute("DELETE FROM role_capabilities WHERE role = ?", [$role]);
        foreach ($caps as $cap) {
            self::$db->insert('role_capabilities', ['role' => $role, 'capability' => $cap]);
        }
        sort($before); sort($caps);
        if ($before !== $caps) {
            AuditLog::record(AuditLog::TARGET_ROLE, $role, AuditLog::ACTION_ROLE_CAPS_CHANGED, [
                'before' => $before, 'after' => $caps,
            ]);
        }
    }

    /**
     * Alle Rollen-Defaults auf einmal abrufen (fuer Admin-UI).
     * Admin bekommt IMMER ALL_CAPS — fehlende Caps werden in role_capabilities
     * nachgetragen, damit neue Tools (Mail, Site-Monitor, ...) automatisch
     * in der Rollen-Matrix erscheinen.
     * @return array<string,string[]>
     */
    public static function allRoleDefaults(): array
    {
        self::synchronisiereAdminCaps();
        $out = [ROLE_ADMIN => [], ROLE_MANAGER => [], ROLE_USER => [], ROLE_GUEST => []];
        try {
            $rows = self::$db->query("SELECT role, capability FROM role_capabilities");
            foreach ($rows as $r) {
                $out[$r['role']][] = $r['capability'];
            }
        } catch (\Throwable $e) {
            foreach ($out as $role => $_) {
                $out[$role] = self::DEFAULT_CAPS[$role] ?? [];
            }
        }
        // Admin defensiv: garantiert alle Caps, falls Sync fehlschlug
        $out[ROLE_ADMIN] = self::ALL_CAPS;
        return $out;
    }

    /**
     * Sorgt dafuer, dass die role_capabilities-Tabelle fuer Admin alle in
     * ALL_CAPS gelisteten Caps enthaelt. Idempotent — wird bei jedem Lesen
     * der Rollen-Defaults aufgerufen, fuegt fehlende Eintraege ein.
     * Andere Rollen werden nicht angefasst.
     */
    private static function synchronisiereAdminCaps(): void
    {
        try {
            $vorhanden = self::$db->query(
                "SELECT capability FROM role_capabilities WHERE role = ?",
                [ROLE_ADMIN]
            );
            $vorhandenSet = array_flip(array_column($vorhanden, 'capability'));
            foreach (self::ALL_CAPS as $cap) {
                if (!isset($vorhandenSet[$cap])) {
                    self::$db->insert('role_capabilities', ['role' => ROLE_ADMIN, 'capability' => $cap]);
                }
            }
        } catch (\Throwable $e) {
            // Tabelle existiert vielleicht nicht — egal, allRoleDefaults() liefert ALL_CAPS sowieso
        }
    }

    // ========================================================================
    //   Kunden-Zuweisungen — direkt (user_customers) + rollen-basiert (role_customers)
    // ========================================================================

    /**
     * Customer-IDs, die fuer eine Rolle pauschal freigeschaltet sind.
     * @return int[]
     */
    public static function roleCustomerIds(string $role): array
    {
        try {
            $rows = self::$db->query(
                "SELECT customer_id FROM role_customers WHERE role = ?",
                [$role]
            );
            return array_map('intval', array_column($rows, 'customer_id'));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Komplette Rollen→Kunden-Matrix als Map (fuer Admin-UI).
     * @return array<string,int[]>
     */
    public static function allRoleCustomers(): array
    {
        $out = [ROLE_ADMIN => [], ROLE_MANAGER => [], ROLE_USER => [], ROLE_GUEST => []];
        try {
            $rows = self::$db->query("SELECT role, customer_id FROM role_customers");
            foreach ($rows as $r) {
                $out[$r['role']][] = (int)$r['customer_id'];
            }
        } catch (\Throwable $e) {
            // Tabelle gibts noch nicht — leere Maps zurueck
        }
        return $out;
    }

    /**
     * Kunden einer Rolle setzen — komplett ersetzen.
     * Loggt die Aenderung im Audit-Log.
     * @param int[] $customerIds
     */
    public static function setRoleCustomers(string $role, array $customerIds, ?int $grantedBy = null): void
    {
        $before = self::roleCustomerIds($role);
        $clean = array_values(array_unique(array_map('intval', $customerIds)));
        self::$db->execute("DELETE FROM role_customers WHERE role = ?", [$role]);
        foreach ($clean as $cid) {
            if ($cid <= 0) continue;
            self::$db->insert('role_customers', [
                'role' => $role,
                'customer_id' => $cid,
                'granted_by' => $grantedBy,
            ]);
        }
        sort($before); sort($clean);
        if ($before !== $clean) {
            AuditLog::record(AuditLog::TARGET_ROLE, $role, AuditLog::ACTION_ROLE_CUSTOMERS_CHANGED, [
                'before' => $before, 'after' => $clean,
            ], $grantedBy);
        }
    }

    /**
     * Direkt zugewiesene Customer-IDs eines Users (ohne Rollen-Vererbung).
     * @return int[]
     */
    public static function directCustomerIdsOf(int $userId): array
    {
        try {
            $rows = self::$db->query(
                "SELECT customer_id FROM user_customers WHERE user_id = ?",
                [$userId]
            );
            return array_map('intval', array_column($rows, 'customer_id'));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Direkt-Zuordnungen aller User als Map (fuer Admin-UI).
     * @return array<int,int[]>
     */
    public static function allDirectUserCustomers(): array
    {
        $out = [];
        try {
            $rows = self::$db->query("SELECT user_id, customer_id FROM user_customers");
            foreach ($rows as $r) {
                $uid = (int)$r['user_id'];
                $out[$uid] = $out[$uid] ?? [];
                $out[$uid][] = (int)$r['customer_id'];
            }
        } catch (\Throwable $e) {}
        return $out;
    }

    /**
     * Effektive Customer-IDs eines Users — direkt + via Rolle.
     * Admin sieht alle aktiven Kunden (Sicherheitsnetz).
     * @return int[]
     */
    public static function effectiveCustomerIdsOf(int $userId): array
    {
        $u = self::$db->queryOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if (!$u) return [];
        if ($u['role'] === ROLE_ADMIN) {
            $rows = self::$db->query("SELECT id FROM customers WHERE is_active = 1");
            return array_map('intval', array_column($rows, 'id'));
        }
        $rows = self::$db->query(
            "SELECT DISTINCT c.id
             FROM customers c
             LEFT JOIN user_customers uc ON uc.customer_id = c.id AND uc.user_id = ?
             LEFT JOIN role_customers  rc ON rc.customer_id = c.id AND rc.role    = ?
             WHERE (uc.user_id IS NOT NULL OR rc.role IS NOT NULL)
               AND c.is_active = 1",
            [$userId, $u['role']]
        );
        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * Kann auf Kunden zugreifen?
     */
    public static function canAccessCustomer(int $customerId): bool
    {
        // Admins sehen alles
        if (self::isAdmin()) {
            return true;
        }

        // Pruefen ob Kunde in der Liste des Benutzers ist
        foreach (self::$customers ?? [] as $c) {
            if ((int) $c['id'] === $customerId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Passwort hashen
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Passwort aendern
     */
    public static function changePassword(int $userId, string $newPassword): bool
    {
        $hash = self::hashPassword($newPassword);
        return self::$db->execute(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [$hash, $userId]
        ) > 0;
    }

    /**
     * Benutzer erstellen
     */
    public static function createUser(array $data): int
    {
        $data['password_hash'] = self::hashPassword($data['password']);
        unset($data['password']);

        return self::$db->insert('users', $data);
    }

    /**
     * Passwort-Staerke pruefen
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Passwort muss mindestens einen Grossbuchstaben enthalten';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Passwort muss mindestens einen Kleinbuchstaben enthalten';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Passwort muss mindestens eine Zahl enthalten';
        }

        return $errors;
    }
}
