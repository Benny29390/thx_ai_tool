# Kontext-Zusammenfassung — KI Text Tool (Stand: 2026-05-19)

## Projekt

**Name:** KI Text Tool (Domain: `ai.thoxan-dev.de`)
**Stack:** PHP 8.4 (kein Composer), MariaDB (native VECTOR(1536) + HNSW), Apache mod_php, Vanilla-JS Frontend, **kein Build-Step**
**Root:** `/var/www`
**Admin-Login:** `admin@thoxan-dev.de` / `Test1234!` (NICHT ändern ohne den User zu informieren)

## Architektur

```
/var/www/
  core/          Auth, Database, Response, Router, Session, App
  api/handler.php   Zentraler API-Router (alle /api/v1/*)
  api/v1/        Endpunkte
  services/      Business-Logik
  views/         PHP-Views mit Inline-JS/CSS
  config/        config.php, constants.php
  storage/vectors/  SQLite-Embeddings
  uploads/       www-data-owned
  cli/           Worker, Sync-Scripts, backup.sh
```

**Kernbereiche** (aus CLAUDE.md):
1. **Chat** (`views/chat.php`, `api/v1/chat-stream.php`) — SSE-Streaming, Modell-Auswahl, RAG mit Artefakten
2. **Artefakte** (`views/admin/artifacts.php`, `services/ArtifactService.php`) — Wissensbasis (Regel/Wissen/Profil/Autor/Namespace) mit Vector-Suche

## Wichtige User-Präferenzen (gespeichert in `/root/.claude/projects/-var-www/memory/`)

- **Sprache:** Deutsch, echte Umlaute (ä ö ü ß), NIEMALS ae/oe/ue/ss
- **Style:** Kein Auto-Loop bei wiederholten Fehlern, FAIL-FAST
- **Kein WordPress** in der Codebase — sauber integriert
- **Bestehender Kundenstamm** wird wiederverwendet (`customers`-Tabelle)
- Antwort-Stil: knapp, keine Trailing-Summaries, keine überflüssige Erkundung

## In aktuellen Sessions gebaut

### KI-Coworker (Chat-Modus, jetzt Deprecated)
- `/admin/coworker` mit `services/CoworkerService.php` + `services/AdminCodeToolsTrait.php`
- 4 Tabellen: `coworker_sessions`, `coworker_messages`, `coworker_snapshots`, `coworker_bash_log`
- Frei-Dialog mit Claude (Anthropic API), Tool-Use: read_file/write_file/search_code/list_files/bash/ask_user/done
- Bash-Blacklist (22 Patterns), Auto-Snapshot via `find -mmin`, Cost-Cap, Live-Token-Tracking
- Snapshot-Rollback pro Message + Session
- Nav-Eintrag: jetzt in **Deprecated**-Gruppe

### Auftrags-Modus (jetzt Deprecated)
- `/admin/tasks` mit `services/AdminTaskService.php` — älterer Single-Shot Code-Editor
- Tabellen: `admin_tasks`, `admin_task_messages`, `admin_task_snapshots`
- Nav-Eintrag: jetzt in **Deprecated**-Gruppe

### Backup-System
- `cli/backup.sh` läuft täglich 03:00 als root (Cron unter `/etc/cron.d/ki-tool-backup`)
- DB-Dump + `/var/www`-Tar (ohne vendor/cache/.git) → `/var/backups/ki-tool/`
- 14 Tage Rotation
- Status-JSON unter `/var/www/storage/backup-status.json` (Apache liest das, weil `open_basedir`)
- Admin-UI: `/admin/backups` (Nav → System → Backups)
- Manueller Trigger im Frontend scheitert mangels sudoers — Cron-Lauf als root klappt

### Customer Logos
- Spalte `customers.logo_path` (VARCHAR 500)
- API: `POST/DELETE /api/v1/admin/customers/{id}/logo` (`api/v1/admin/customer-logo.php`)
- Upload-Verzeichnis: `/var/www/uploads/customers/logos/`
- UI-Integration: `views/admin/customer-steckbrief.php` (Kamera-Button am Badge) + `views/admin/customers.php` (Card- und Listen-View)

### Projektplanner (vollständig integriert, alle 8 Phasen)

Tallyr-WordPress-Modul (Doku unter `/var/www/projektplanner/PROJEKTPLANNER-MODUL.md`) in das SaaS-Tool sauber integriert.

**User-Entscheidungen:**
- Personen-Modell: **Hybrid** (Users aus `users`-Tabelle + freie Personen)
- Plan-Ownership: **team-shared** (alle Admins sehen alle)
- Asana: pro Plan (Token aus `settings.asana_pat`)
- Import: JSON-Upload aus Tallyr-Export v1.0

**DB-Erweiterungen** (`customers` + 8 neue `pp_*`-Tabellen):
- `customers`: `+hex_color, +stundensatz, +uebertrag_ts, +uebertrag_notiz, +abrechnungsmodus`
- `pp_team_members` (Hybrid User+Extern), `pp_plans`, `pp_plan_rows`, `pp_plan_revisions`,
  `pp_plan_feedback`, `pp_plan_budget`, `pp_customer_budget`, `pp_person_shares`

**Services** (`services/`):
- `ProjektplannerService.php` — Plan/Row-CRUD, Reorder, Cross-Plan-Move
- `PpTeamService.php` — Auto-Sync mit `users`, freie Personen
- `PpBudgetService.php` — TS-Rundung (kundenfreundlich), Soll/Ist pro Monat, Forecast
- `PpDashboardService.php` — Aggregation: Totals, by_person, by_plan, forecast, done_tasks, capacity
- `PpImportService.php` — Tallyr-JSON-Import mit ID-Mapping (Slug → customer.id, Name → team_member.id)
- `AsanaService.php` (modify) — `createTask`, `searchTasks`, `listSections`, `getTask` ergänzt

**API** (`api/v1/admin/projektplanner/`):
- `plans.php`, `rows.php`, `team.php`, `budget.php`, `dashboard.php`, `import.php`, `person-shares.php`, `asana.php`
- Public (kein Auth, `/public/`-Prefix): `api/v1/public/projektplan.php` + `projektplan-person.php`
- Routing in `api/handler.php` (Auth-Bypass für `/public/`-Prefix)

**Views** (`views/projektplanner/`):
- `index.php` — Plan-Editor (Sidebar + 13-Spalten-Tabelle, contenteditable, Auto-Save 600ms-Debounce, Drag&Drop, Plan-Stats-Bar, Budget-Modal, Asana-Modals)
- `dashboard.php` — 6 Tabs (KPIs, Personen, Pläne, Forecast-Heatmap, Erledigte, Soll/Ist)
- `settings.php` — Team-Mitglieder (Add User / Add Externe, inline-edit, Color-Picker)
- `import.php` — JSON-Drag&Drop, Preview-Diff, Import-Log
- `share.php` — öffentliche Kunden-Vorschau (no auth, Like/Dislike/Comment-Feedback)
- `person.php` — öffentliche Aufgabenliste pro Person (no auth)

**Routen** (`core/App.php`):
- `/admin/projektplanner` + `/dashboard` + `/settings` + `/import`
- `/projektplan/{hash}` + `/personen-aufgaben/{hash}` (public)

**Nav-Eintrag:** „Projektplanner" zwischen „KI Kompass" und „Administration" (alle Admins+Manager)

## System-Constraints / Patterns

### Apache PHP — `disable_functions`
`/etc/php/8.4/apache2/php.ini` blockiert: `shell_exec, system, passthru, proc_open, popen, pcntl_exec, pcntl_fork`
**→ Statt dessen `exec(...)` nutzen** (ist erlaubt). Im Trait + CoworkerService gefixt.

### `open_basedir`
`/var/www:/tmp:/usr/share/php` — Apache PHP kann **nicht** in `/var/backups` oder `/etc` lesen.
**→ Backup-Status liegt in `/var/www/storage/backup-status.json`** statt direkt aus Backup-Dir.

### File-Permissions
Tree gehört jetzt komplett `www-data:www-data` (`chown -R` ausgeführt) — KI-Coworker und alle write-Endpoints können wirklich schreiben.

### DB-Konventionen
- Migrations laufen via `/admin/migrate` (Duplicate-Column-Pattern in `core/App.php`)
- Soft-Delete häufig via `state=2`
- ENUMs mit ALTER TABLE erweitern (`usage_logs.action_type`)

### Frontend-Patterns
- Kein Build-Step — JS/CSS inline in PHP-Views
- Inline-Scripts laufen VOR `app.js` → `waitForApp()` wenn nötig
- `App.showNotification(msg, type)` für Toasts
- `App.csrfToken` für POST-Header
- CSRF-Check ist global "Temporär deaktiviert für API-Entwicklung" (in `api/handler.php`)

### Test-Cookies (für Self-Tests)
Cookie-Jar unter `/tmp/cw_cookies.txt` aus früherer Curl-Login-Session

## Offene Punkte / Vorhaben

- **Excel-Export** (XLSX) — braucht PhpSpreadsheet, nicht im Repo. Aktuell CSV-fähig nachrüstbar
- **Excel-Import** (XLSX) im Projektplanner-Import-View — SheetJS via CDN
- **Sharelink-Passwort-Schutz** — Endpoint vorhanden, UI fehlt
- **Plan-Revisionen wiederherstellen** — Tabelle + Insert vorhanden, UI fehlt
- **Sticky Tabellen-Header** im Plan-Editor bei langen Plänen
- **KI-Coworker bei wiederholten Permission-Fails** — bei `disable_functions`-betroffenen Calls fail-fast (geht jetzt einigermaßen via System-Prompt)

## Letzte kleine Änderung (gerade eben)

`views/admin/dashboard.php` Zeile 10: Greeting wieder auf „Guten Tag" zurückgesetzt (war kurz „Guten Tagchen" als Test der Coworker-Live-Edit-Funktionalität).
