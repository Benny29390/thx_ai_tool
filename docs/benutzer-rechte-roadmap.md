# Benutzer & Rechte — Status

Stand: 21.05.2026 — alle ursprünglich offenen Punkte umgesetzt.

## 1. ✓ 2FA-Pflicht für Admin- und Manager-Konten

- `Auth::requires2FASetup()` in [core/Auth.php](../core/Auth.php) liefert `true`, wenn ein Admin oder Manager noch kein 2FA eingerichtet hat
- Roter Pflicht-Banner unterhalb der Topbar in [views/layouts/main.php](../views/layouts/main.php) mit „Jetzt einrichten"-CTA
- Banner ist auf der `/settings/security`-Seite selbst ausgeblendet (sonst doppelt)
- Sidebar + Main-Content rutschen automatisch um die Banner-Höhe nach unten

## 2. ✓ Login-Rate-Limit (Brute-Force-Schutz)

- Neue Tabelle `login_attempts` (email, ip, success, occurred_at)
- `Auth::login()` zählt fehlgeschlagene Versuche pro E-Mail in den letzten 15 Min
- Ab **5 Fehlversuchen** → HTTP-Response mit `rate_limited: true` und `wait_minutes`
- Erfolgreicher Login löscht alle Fail-Counts dieser E-Mail
- Konstanten: `Auth::LOGIN_MAX_FAILED = 5`, `Auth::LOGIN_WINDOW_MIN = 15`

## 3. ✓ Audit-Log für Rechte-Änderungen

- Neue Tabelle `permission_audit_log` (id, occurred_at, actor_user_id, target_type, target_key, action, diff JSON)
- Zentraler Service `Core\AuditLog` mit `record()`, `list()`, `count()`
- Hooks in:
  - `Auth::setCapabilities()` — `caps_changed`
  - `Auth::setRoleDefaults()` — `role_caps_changed`
  - `Auth::setRoleCustomers()` — `role_customers_changed`
  - `api/v1/admin/users.php` — `role_changed`
  - `api/v1/admin/users.php::saveUserCustomers()` + `user-customer-mapping.php` — `customers_changed`
  - `api/v1/admin/users-bulk.php` — alle Aktionen
  - `scripts/deactivate-stale-users.php` — `user_deactivated`
- Admin-View als 4. Tab unter `/admin/users?tab=audit` mit Filter (Aktion/Ziel-Typ) und Diff-Visualisierung (grüne/rote Pillen für hinzu/entfernt)

## 4. ~~Konversations-Sichtbarkeit~~ — bewusst nicht angefasst

Design-Entscheidung Thomas: Allgemeine Chats team-übergreifend, kundenspezifische nur mit Rechten, keine private Sicht im Tool.

## 5. ✓ Wissens-Schreibrechte pro Kunde

- Neuer Helper `knowledgeAssertWriteAccess(?int $customerId)` in [api/v1/knowledge/_helpers.php](../api/v1/knowledge/_helpers.php)
- Aufruf an allen Schreib-Endpoints:
  - `upload.php`, `url.php`, `website.php`, `text.php`, `commit.php`
- `documents.php` (GET/PUT/DELETE) prüft jetzt gegen die **effektive Kundenliste** (`Auth::customers()`) statt nur gegen den aktiven Kunden
- Globales Wissen (`customer_id IS NULL`) darf nur Admin anlegen

## 6. ✓ Bulk-Aktionen in der User-Liste

- Checkbox-Spalte ganz links in der Benutzer-Tabelle
- Bulk-Toolbar (Amber, erscheint bei Selektion) mit Aktionen:
  - **Rolle ändern** → Prompt, dann POST mit `set_role` (setzt automatisch die Rollen-Default-Caps)
  - **Kunden zuweisen** → Modus (set/add/remove) + Kunden-IDs
  - **Caps auf Rolle-Default** zurücksetzen
  - **Aktivieren / Deaktivieren** (Self-Lock: eigener Account wird bei Aktivieren/Deaktivieren automatisch ausgenommen)
- Header-Checkbox: „Alle sichtbaren markieren" (respektiert Filter)
- Backend: [api/v1/admin/users-bulk.php](../api/v1/admin/users-bulk.php), Route in handler.php
- Alle Bulk-Änderungen werden im Audit-Log dokumentiert

## 7. ✓ Welcome-State für User ohne Caps/Kunden

- Im Dashboard ([views/admin/dashboard.php](../views/admin/dashboard.php)) wird vor den Tiles eine Welcome-Card eingeblendet, wenn:
  - Non-Admin **und** (`capabilities == []` oder `customers == []`)
- Card erklärt die Lage je nach Fall (keine Caps / keine Kunden / beides) und bietet einen vorausgefüllten `mailto:`-Link an den ersten aktiven Admin

## 8. ✓ Inaktivitäts-Cleanup-Job

- Standalone-Skript [scripts/deactivate-stale-users.php](../scripts/deactivate-stale-users.php)
- Default: 30 Tage Schwelle (per `--days=N` änderbar)
- Deaktiviert non-Admin-User, die seit X Tagen nicht eingeloggt waren
- Berücksichtigt auch User mit `last_login IS NULL` (nicht eingelöste Einladungen älter als X Tage)
- `--dry-run` für Vorab-Check
- Jede Deaktivierung wird im Audit-Log geloggt (`user_deactivated`, mit Anzahl Tage als Grund)
- **Cron-Aufruf empfohlen:** `0 3 * * * php /var/www/scripts/deactivate-stale-users.php`

## DB-Migrationen

Beide Skripte sind idempotent — können mehrfach laufen:

```
php scripts/migrate-security-roadmap.php   # legt login_attempts + permission_audit_log an
```

Bereits gelaufen am 21.05.2026.

## Was NICHT umgesetzt wurde (bewusst)

- Sub-Roles / Hierarchien innerhalb der 4 Rollen — würde Über-Engineering
- Permission-Vererbung zwischen Kunden — selten gebraucht
- Time-based Caps — kein Use-Case bekannt
- 2FA für `user`/`guest` — optional, nicht zwingend
