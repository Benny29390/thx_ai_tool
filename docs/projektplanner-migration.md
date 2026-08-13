# Projektplanner-Migration: Status & Inventur

**Zweck:** Vollständige Auflistung aller Features aus dem Tallyr-Original (WordPress, `docs/kreation-tallyr/`) und Status im KI-Tool.

**Status-Legende:** ✓ migriert · ◐ teilweise · ✗ fehlt · — nicht geplant

**Priorität:** ⓵ kritisch · ⓶ wichtig · ⓷ nice-to-have

**Letzter Stand:** 22.05.2026 — **Phasen 1-5 abgeschlossen — Migration vollständig und gehärtet.**
Editor + Settings + Dashboard + Public-Sharelink + Personen-Sharelink + Permissions per Plan + Feedback-Viewer + Revisions + Excel-Export + JSON-Backup + Multi-Plan mit Plan-Header-Aggregator + Plan-Archiv + Personen-Rename + Sektion-Features + Right-Click-Kontextmenüs + Keyboard-Subtilitäten + Undo-Toast + Schriftgröße + Sidebar-Collapse + Filter-Persistierung + Like/Dislike + Asana-Status-Sync-Cron + Asana-Templates-Autocomplete + Asana-Task-Detail-Modal + Spalten-Filter-Icons + Duplikat-Detection + Auto-Snapshot bei strukturellen Änderungen.

**Was bewusst NICHT übernommen:** WordPress-spezifische Hooks, Spaltenbreiten-Resize per Mausdrag (zu viel Aufwand für wenig Nutzen, Schriftgröße deckt das ab), einige sehr Tallyr-spezifische Click-Subtilitäten in Personen-Tags.

---

## Querschnitt

- ✓ DB-Schema: **9 `pp_*`-Tabellen** in [core/App.php:1326-1430](../core/App.php#L1326-L1430)
- ✓ Service-Schicht: `ProjektplannerService`, `PpBudgetService`, `PpTeamService`, `PpDashboardService`, `PpImportService`, `PpExportService`
- ✓ Permission-Helper: `api/v1/admin/projektplanner/_pp_perm.php` mit `pp_require(planId, 'read'|'edit'|'write')`
- ✓ API unter `/api/v1/admin/projektplanner/*` + Public unter `/api/v1/public/projektplan/{hash}`
- ✓ THX-Design durchgängig (alle 6 Views)
- ✓ Cap-Schutz `CAP_PROJEKTPLANNER`
- ✓ **Server-side Permission-Check pro Endpoint** (plans + rows + export); Manager/Admin haben Pseudo-Owner-Status

---

## 1. Plan-Verwaltung (Sidebar) — ✓

| Feature | Status |
|---|---|
| Pläne-Liste in Sidebar (280px, collapsible) | ✓ |
| Status-Filter Alle/Aktiv/Entwurf/Einzelprojekt/**Reporting**/Fertig/**Archiv** | ✓ |
| Suche (Titel + Kundenname + Kürzel) | ✓ |
| Plan anlegen, duplizieren, soft-delete, **restore** (Archiv) | ✓ |
| Plan-Cards: Unread-Badge, Customer-Color-Dot, **Permission-Badge** wenn nicht Owner | ✓ |
| **Multi-Select-Modus** mit Cmd/Ctrl/Shift+Klick → öffnet Multi-Plan-View | ✓ |
| **„Mir zugewiesene Pläne"** über `pp_plan_shares` + Permission-Badge | ✓ |
| **Right-Click-Kontextmenü auf Plan-Card** (Status direkt setzen, Öffnen, Löschen) | ✓ |

---

## 2. Editor-Toolbar (Plan-Header) — ✓

| Feature | Status |
|---|---|
| 11 Action-Buttons: Sidebar-Toggle, A−/A+, Budget, Asana, Verlauf, Export, Duplizieren, Feedback (mit Unread-Badge), Teilen, Löschen/Wiederherstellen | ✓ |
| Save-Indicator (speichert/gespeichert/Fehler) | ✓ |
| **Schriftgröße A−/A+ Buttons** mit localStorage-Persistierung | ✓ |
| **Sidebar-Toggle** mit localStorage-Persistierung | ✓ |
| **Excel-Export-Button** (Plan als .xls) | ✓ |
| Sticky-Header beim Scrollen | ✓ |
| Permission-abhängige Button-Sichtbarkeit (Read/Edit/Write via Body-Klassen) | ✓ |
| Asana-Cache-Refresh-Button | ✗ ⓷ |
| Spalten-Resize per Mousedrag | ✗ ⓷ |

---

## 3. Zeilen-Editor — ✓

| Feature | Status |
|---|---|
| 12 Spalten + Inline-Edit + 600ms Auto-Save-Debounce | ✓ |
| Row-Typen item/section/note/spacer + virtueller plan_header (Multi-Plan) | ✓ |
| Done: grünes Häkchen + sanfter grüner Hintergrund (KEIN line-through) | ✓ |
| Row-Flags Fokus/Platzhalter/Kein Ticket | ✓ |
| Drag&Drop innerhalb Plan | ✓ |
| **Cross-Plan Drag&Drop** im Multi-Plan-View | ✓ |
| Zeile löschen mit **Undo-Toast (8 Sek.)** | ✓ |
| Sticky Header | ✓ |
| **Esc-Key revert per Cell** | ✓ |
| **Tab/Shift+Tab Cell-Navigation** | ✓ |
| **Paste = Plain-Text-only** | ✓ |
| **Komma → Punkt automatisch** in numerischen Feldern | ✓ |
| **Single-Click = Select-All** bei kurzen Feldern | ✓ |
| Enter im Cell = Blur | ✓ |
| **Sektion-Subtotals** in Sektions-Zeile | ✓ |
| **Sektion-Buttons** (↑/↓/⏳/👁) bei Hover | ✓ |
| **Sektion-Collapse-State persistent** über Sessions (localStorage) | ✓ |
| **Sektion-Border-Left-Color-Zyklus** (6 Farben) | ✓ |
| **Insert-Line zwischen Rows** mit 4 Buttons (Hover) | ✓ |
| **Right-Click-Kontextmenü auf Row**: Duplizieren, Toggle-Flags, „In anderen Plan", Löschen | ✓ |
| **Right-Click-Kontextmenü auf Resp-Tag**: Als Lead setzen, Entfernen | ✓ |
| **Feedback-Indicator pro Row** (amber=unread, grün=read) | ✓ |
| **Note-URL-Detection** (Link-Icon hinter URL) | ✓ |
| **Filter-Persistierung pro Plan** in localStorage | ✓ |
| Spalten-Filter-Icons im Thead | ✗ ⓷ |
| Komma im Resp-Input = neuer Tag | ✗ ⓷ |
| Backspace im leeren Resp-Input = letzten Tag weg | ✗ ⓷ |

---

## 4. Personen-Felder — ✓

| Feature | Status |
|---|---|
| Lead-Chip + Umsetzung-Chip-Liste, Kürzel + Farbe aus `pp_team_members` | ✓ |
| Autocomplete-Popover mit Pfeil/Enter/Tab/ESC | ✓ |
| Chip-Remove (×) per Hover | ✓ |
| Frei-Eingabe erlaubt | ✓ |
| **Right-Click auf Resp-Tag = „Als Lead setzen"** | ✓ |

---

## 5. Stats-Bar — ✓

| Feature | Status |
|---|---|
| 5 Karten: Ist / Geplant / Budget Soll / Noch verplanbar / Erledigt | ✓ |
| **TS-Umrechnung** als Subzeile hinter h-Werten | ✓ |
| **„+X h PH"** wenn Platzhalter | ✓ |
| Budget-Ampel + Gap-Ampel | ✓ |
| **Erledigt-Progress-Bar** mit Ampel (≥75% grün, ≥40% amber, sonst rot) | ✓ |
| Budget-Soll lädt für Plan-Zeitraum (auch Multi-Jahr) | ✓ |
| Stats über mehrere Pläne (Multi-Plan) | ◐ Multi-Plan ist live, eigener Stats-Aggregator fehlt |

---

## 6. Editor-Filter-Leiste — ✓

| Feature | Status |
|---|---|
| Status-Chips multi-select | ✓ |
| Lead-Select + Umsetzung-Select | ✓ |
| Freitext-Suche | ✓ |
| Aktive-Filter-Banner mit Anzahl + Ist/Soll-Summen | ✓ |
| Sektionen bleiben sichtbar bei ≥ 1 passenden Item | ✓ |
| **Filter-Persistierung pro Plan in localStorage** | ✓ |
| **Spalten-Filter-Icons im Thead** (Klick zeigt Top-20 Unique-Values mit Count, persistiert in localStorage) | ✓ |

---

## 7. Asana-Integration — ✓ vollständig

| Feature | Status |
|---|---|
| Plan ↔ Asana-Projekt (+ Section) verknüpfen | ✓ |
| Projekte/Sections/Tasks laden, suchen, erstellen, verknüpfen | ✓ |
| Token-Storage verschlüsselt | ✓ |
| Workspace-Listing, Pagination, Rate-Limit-Handling (429), 401-Handling, Manual-URL-Paste | ✓ |
| **Asana-Status-Sync-Cron** (alle 15 min, syncht `completed` → `is_done`) — `scripts/pp-asana-sync.php` | ✓ |
| **Status-Sync-Button im Editor-Header** (sofortiger Sync für aktuellen Plan) | ✓ |
| **Asana-Templates** aus konfigurierbarem Project — Autocomplete in Description ab 2 Zeichen + Stunden-Extract (`// 3.5 Std`) → planned_hours | ✓ |
| **Asana-Task-Detail-Modal** bei Klick auf Asana-Icon: Name, Status, Assignee, Notes, Kommentare (Stories) | ✓ |
| **Asana-Cache-Refresh-Button** in Settings (Templates-Cache leeren) | ✓ |
| **Verwaiste Asana-Refs aufräumen** in Settings → Asana: Scan-Button findet Plan-Zeilen mit nicht-mehr-existierenden Asana-Tasks + Bulk-Unlink | ✓ |

---

## 8. Budget pro Kunde — ✓

12-Monats-Tabelle mit Jahr, Übertrag, Modus, Ist-Override, TS-Rundung — alles aus Phase 1.

---

## 9. Team-Mitglieder (Settings) — ✓

| Feature | Status |
|---|---|
| Liste + Inline-Edit + Farbe + Aktiv/Inaktiv + Auto-Sync | ✓ |
| Pflicht-User bei Neuanlage, Externe behalten Bestand | ✓ |
| Tab-Hub (Team / Personen-Sharelinks) | ✓ |
| **Personen-Umbenennen über alle Pläne** (Confirm-Dialog beim Name-Edit) | ✓ |
| **Duplikat-Detection bei ähnlichen Namen** (Substring + Levenshtein-Distanz im Dashboard-Personen-Tab als Warning-Banner) | ✓ |

---

## 10. Dashboard (6 Tabs) — ✓

Alle 6 Tabs THX, Datums-Presets, Personen-Filter, Forecast-Heatmap mit Farbcodes, Capacity/Verfügbar-Zeilen, Gesamtzeilen, Detail-Task-Liste mit Rolle-Badge bei Personen-Filter.

---

## 11. Permissions per Plan — ✓ vollständig gehärtet

| Feature | Status |
|---|---|
| Tabelle `pp_plan_shares` mit ENUM(read/edit/write) | ✓ |
| Service-Methoden + API-Endpoints + UI (Sharing-Modal) | ✓ |
| Drei Permission-Stufen mit Erklärtexten | ✓ |
| Link aufheben + Kopieren-Button | ✓ |
| **Sharelink-Passwort setzen/entfernen-UI** im Sharing-Modal (bcrypt-gehashed) | ✓ |
| **Server-side Permission-Check** via `pp_require()` für **alle** Plan-Endpoints: plans, rows, export, **feedback**, **revisions**, **asana (create/link/sync-status/unlink-orphans)** | ✓ |
| **Body-Klassen `pp-perm-read/edit/write/owner`** auf `<body>` | ✓ |
| **Plan-Share-Banner unter Header** für read/edit-User | ✓ |
| **„edit"-Permission filtert Whitelist-Felder** (is_done, ist_hours, actual_hours, notes) auf API-Ebene | ✓ |
| **Edit-Permission Frontend-Polish**: gesperrte Felder mit Streifen-Muster + 🔒 + Cursor not-allowed; erlaubte Felder amber-tönen | ✓ |
| **Multi-Plan-Permissions**: niedrigste Permission über alle geöffneten Pläne wird angewandt; plan-spezifische Aktionen (Budget/Asana/Verlauf/Löschen) versteckt | ✓ |
| **Read-Only versteckt schreibende Aktionen** per CSS | ✓ |

---

## 12. Public-Sharelink-Page — ✓

| Feature | Status |
|---|---|
| Plan-Anzeige im THX-Design | ✓ |
| **Passwort-Schutz** mit Session + Pw-Gate | ✓ |
| Header + Empty-Section-Hiding + Placeholder/Spacer-Filter | ✓ |
| **Personen-Suffix** `(KW12, TKI) (THO)` ohne Duplikate | ✓ |
| **Sektion-Subtotal** + **Total-Zeile** + **TS-Zeile** mit kundenfreundlicher Rundung | ✓ |
| Feedback-Icons + Liste + Edit/Delete durch Author | ✓ |
| **Like/Dislike-Buttons** mit Toggle (Backend hat Toggle-Logik) | ✓ |
| Name in localStorage merken | ✓ |
| Print-Styles | ✓ |
| Note-URL-Detection (klickbar) | ✓ |

---

## 13. Personen-Sharelink-Page — ✓

| Feature | Status |
|---|---|
| THX-Design | ✓ |
| Sharelink anlegen/wiederfinden/löschen | ✓ |
| Person-Header + KPIs + Gruppierung nach Kunde + Summenzeile | ✓ |
| HV-Badge vs. Umsetzung-Badge | ✓ |
| **Admin-Verwaltung im Settings-Tab** mit URL-Kopieren + **Excel-Export-Button** pro Eintrag | ✓ |

---

## 14. Feedback-System — ✓

| Feature | Status |
|---|---|
| Public + Admin CRUD + Mark-Read + Read-All | ✓ |
| Unread-Count pro Plan in Sidebar | ✓ |
| Feedback-Viewer-Modal im Editor (gruppiert pro Row) | ✓ |
| Header-Button mit Unread-Badge | ✓ |
| Feedback-Indicator pro Row (mit Read/Unread-Farbe) | ✓ |

---

## 15. Revisionen / Undo — ✓

| Feature | Status |
|---|---|
| `pp_plan_revisions` + Service + API + Modal | ✓ |
| Max 50 pro Plan mit Auto-Purge | ✓ |
| Sicherheits-Snapshot vor jedem Restore | ✓ |
| **Undo-Delete-Toast (8 Sek.)** mit „Rückgängig"-Button | ✓ |
| **Auto-Snapshot bei strukturellen Änderungen** (Title/Customer/Periode/Status/Reorder) — max 1× pro Stunde | ✓ |

---

## 16. Import / Export — ✓

| Feature | Status |
|---|---|
| **JSON-Import aus Tallyr** (Service + API + THX-UI) | ✓ |
| Excel-Import Backend (`PpImportService`) | ✓ |
| Excel-Import UI im Editor-Header | ✗ ⓷ (Eigene Seite gibt es) |
| **Excel-Export pro Plan** (HTML mit application/vnd.ms-excel — Excel öffnet nativ) | ✓ |
| **Person-basierter Excel-Export** über Settings-UI | ✓ |
| **JSON-Export (Komplett-Backup)** über Settings → Asana-Tab → „JSON-Backup herunterladen" | ✓ |

---

## 17. Multi-Plan-Ansicht — ✓

| Feature | Status |
|---|---|
| Cmd/Ctrl/Shift+Klick in Sidebar öffnet mehrere Pläne gleichzeitig | ✓ |
| **Virtuelle `plan_header`-Rows** mit Plan-Color + „nur diesen Plan zeigen →" Link | ✓ |
| Cross-Plan Drag&Drop (Drag-Erkennung über `_planId`-Vergleich) | ✓ |
| Per-Plan Reorder (separate API-Calls pro Plan beim Drop) | ✓ |
| Per-Row API-Calls nutzen `_planId` statt globaler `activePlanId` | ✓ |
| **Plan-Header-Aggregator** im Multi-Plan-Modus (pro Plan: N Aufgaben · Ist X · Soll Y · Done/N) | ✓ |

---

## 18. Keyboard / UX — ✓

| Feature | Status |
|---|---|
| Tab/Shift+Tab Cell-Navigation, Esc revert, Enter=Blur, Paste-Plain, Komma↔Punkt | ✓ |
| Right-Click-Kontextmenüs (Row + Resp-Tag) | ✓ |
| Single-Click-Select-All | ✓ |
| Undo-Toast bei Delete | ✓ |
| Tooltip-Texte konsistent | ✓ |
| Cmd+A für Plan-Multi-Select | ✗ ⓷ (Cmd/Ctrl+Klick reicht) |

---

## 19. User-Persistierung in localStorage — ✓

| Setting | Status |
|---|---|
| Sidebar-Collapse-State | ✓ |
| Schriftgröße | ✓ |
| Filter pro Plan | ✓ |
| Sektion-Collapse-State pro Plan | ✓ |
| Sharelink-Author-Name | ✓ |

---

## 20. Konventionen / Look & Feel

- ✓ THX-Design durchgängig in **allen 6 Views** (Editor, Settings, Dashboard, Import, Public-Share, Personen-Sharelink)
- ✓ Schreibkonventionen (Du/Dir groß, keine em-dashes, „Umsetzer")
- ✓ Modals via `.thx-modal-*`, Buttons via `.thx-btn`
- ✓ Wiederverwendbare Patterns: Right-Click-Menü, Undo-Toast, Sticky-Save-Indicator

---

## 21. Backend-Endpoints — Übersicht (alle implementiert)

**Pläne** (`/admin/projektplanner/plans*`):
- GET / POST / PUT / DELETE / duplicate / share / **budget-soll** / **restore** / **export**

**Permissions** (`/admin/projektplanner/plans/{id}/shares*` + `users-for-share`):
- GET / POST / DELETE Plan-Shares + GET verfügbare User

**Rows** (`/admin/projektplanner/plans/{id}/rows*`):
- POST / PUT (mit Field-Filter bei edit-Permission) / DELETE / reorder / move

**Team** (`/admin/projektplanner/team*`):
- GET / POST / PUT (mit `rename_in_plans`-Flag) / DELETE + `/users`

**Budget** (`/admin/projektplanner/budget/{cust_id}*`):
- GET / POST / override / uebertrag + Batch

**Dashboard** (`/admin/projektplanner/dashboard`):
- GET mit Filter (date_from/to, status, customer_id)

**Asana** (`/admin/projektplanner/asana/*`):
- projects / sections / search / task / create / link

**Import/Export**:
- `/admin/projektplanner/import` + `/import/preview` (JSON+Excel)
- `/admin/projektplanner/plans/{id}/export` (Plan-XLS)
- `/admin/projektplanner/export-person?name=` (Person-XLS)

**Person-Shares** (`/admin/projektplanner/person-shares*`):
- GET / POST / DELETE

**Feedback** (`/admin/projektplanner/plans/{id}/feedback*`):
- GET / read-all / read / unread / DELETE

**Revisions** (`/admin/projektplanner/plans/{id}/revisions*`):
- GET / POST / restore

**Public** (`/public/projektplan/{hash}*` + `/personen-aufgaben/{hash}`):
- GET / Feedback POST/PUT/DELETE / auth (Pw)

---

## 22. Dateistruktur (Stand)

```
core/App.php                # 9 pp_*-Tabellen + Routen
api/handler.php             # Routing /admin/projektplanner/* + /public/projektplan/*
api/v1/admin/projektplanner/
  ├── _pp_perm.php          # NEU: Permission-Helper pp_require()
  ├── plans.php             # +restore, +budget-soll
  ├── rows.php              # +Permission-Check + Field-Whitelist
  ├── team.php              # +rename_in_plans
  ├── budget.php
  ├── dashboard.php
  ├── asana.php             # Service hardened (Pagination, Rate-Limit)
  ├── import.php
  ├── person-shares.php
  ├── shares.php            # Plan-Permissions
  ├── feedback.php          # Feedback-Admin
  ├── revisions.php         # Snapshots
  └── export.php            # NEU: Excel-Export
api/v1/public/
  ├── projektplan.php       # +Like/Dislike + Pw-Check
  └── projektplan-person.php
services/
  ├── ProjektplannerService.php  # +Permissions, +Revisionen, +restorePlan
  ├── PpBudgetService.php        # +getPlanBudgetSoll
  ├── PpTeamService.php          # +renamePerson
  ├── PpDashboardService.php
  ├── PpImportService.php
  └── PpExportService.php   # NEU
views/projektplanner/
  ├── index.php       # Editor (THX, alle Phase-1-3-Features)
  ├── settings.php    # Team + Personen-Sharelinks (Tab-Hub, Rename-Confirm, Excel-Export-Link)
  ├── dashboard.php   # 6 Tabs (THX, Forecast-Heatmap)
  ├── import.php      # JSON-Import (THX)
  ├── share.php       # Public-Plan (THX, Pw, Like/Dislike, Feedback inline)
  └── person.php      # Public-Personen-Aufgaben (THX, Gruppierung)
```

---

## 22a. Website-Monitor (Zusatz-Modul) — ✓ neu in Phase 6

Aus dem Tallyr-Export `inc/monitor-cron.php` (638 Z.) + `inc/ajaxuser.php` (12 Monitor-Endpoints) + `blocks/tallyrmonitor.php` (241 Z.) komplett ins KI-Tool gehoben — eigenständiges Modul unter `/admin/site-monitor`.

| Feature | Status |
|---|---|
| **3 neue Tabellen**: `pm_monitors`, `pm_monitor_log`, `pm_monitor_incidents` | ✓ |
| **Service** [PageMonitorService.php](../services/PageMonitorService.php) — CRUD, Stats, Cron-Check, Mail-Versand | ✓ |
| **API** [api/v1/admin/site-monitor.php](../api/v1/admin/site-monitor.php) — Liste, Save, Delete, Toggle-Pause, Stats, Log, Incidents, Check-now, Batch-Import, Bulk-Category, Test-Report, Fetch-Title, Cleanup-Logs, Categories | ✓ |
| **View** [views/admin/site-monitor.php](../views/admin/site-monitor.php) — Cards-Grid + Listen-Ansicht im THX-Design | ✓ |
| **Cron** [scripts/pm-check.php](../scripts/pm-check.php) + [/etc/cron.d/ki-tool-site-monitor](/etc/cron.d/ki-tool-site-monitor) (alle 2 Min.) | ✓ |
| **Cap** `CAP_SITE_MONITOR` (Admin + Manager default, in [config/constants.php](../config/constants.php) + [core/Auth.php](../core/Auth.php)) | ✓ |
| **Sidebar-Eintrag** mit `monitoring`-Icon (zwischen Projektplanner und LAM) | ✓ |
| **HTTP-Check**: cURL mit 15s timeout, SSL-Verify aus, UserAgent „KI-Tool-SiteMonitor/1.0", Status 200-399 = up | ✓ |
| **WP-Body-Erkennung** (200 OK aber DB-Connection-Fehler / Maintenance) → down | ✓ |
| **Sub-URLs pro Monitor** (zusätzliche URLs werden mit-geprüft, Logs pro URL) | ✓ |
| **Alert-Mail** bei 2× consecutive Fail (genau 1×, nicht wiederholt) | ✓ |
| **Recovery-Mail** bei down→up (nur wenn Alert raus war) | ✓ |
| **Incidents-Tracking** mit started_at/ended_at/duration_minutes/notified | ✓ |
| **Wöchentliche + monatliche Reports** (Mo / 1. des Monats) mit HTML-Tabelle, Uptime/Ausfälle/Downtime/Response | ✓ |
| **Auto-Title-Fetch** beim Add: zieht `<title>` aus der Seite | ✓ |
| **Batch-Import**: URLs als Textarea, Title wird je Zeile gezogen | ✓ |
| **Bulk-Kategorie-Zuweisung** in der Liste (Multi-Select) | ✓ |
| **Cards-Grid mit Status-Dot, 24h-Uptime, Response-Time, Status-Code, „letzter Check vor X"** | ✓ |
| **Statistik-Modal pro Monitor** (30 Tage) mit Summary + URLs + Letzte Ausfälle | ✓ |
| **Filter**: Suche, Kunde, Status, Kategorie (als Chips) | ✓ |
| **Pause/Aktivieren-Toggle** (status = paused → Cron überspringt) | ✓ |
| **Sofort-Check** (manueller Trigger pro Monitor) | ✓ |
| **Auto-Cleanup** Logs > 90 Tage (alle 6h beim Cron-Run) | ✓ |
| **Test-Report-Button** sendet Sofort-Report mit 7-Tage-Daten an angegebene Mail | ✓ |
| **Per-Monitor Alert-E-Mail** (überschreibt globalen Default) | ✓ |
| **Report-Schedule** pro Monitor (none/weekly/monthly/both) | ✓ |
| **Kunden-Verknüpfung** über `customer_id` → `customers` (gleicher Mandant wie Projektplanner) | ✓ |

**Bewusst nicht migriert** (waren WP-Eigenheiten):
- WordPress-eigene Cron-URL mit Secret-Key — wir nutzen System-Cron (sauberer + zuverlässiger)
- WP-User-spezifische Pausierung — alle Monitore sind team-shared
- E-Mail-Log als WP-Option (statt einfaches Log-File + Mail-Log-Modal-Viewer)

### Mail-Templates (alle 1:1 übernommen)

| Template | Wann | Im KI-Tool |
|---|---|---|
| **Alert „DOWN"** (roter Header) — Subject `DOWN: <label> (<url>)` | bei 2× consecutive Fail | `PageMonitorService::sendAlertMail('down')` |
| **Recovery** (grüner Header) — Subject `RECOVERY: <label> ist wieder online` | bei down→up wenn Alert raus war | `PageMonitorService::sendAlertMail('recovery')` |
| **Wöchentlicher Report** (jeden Montag) — Subject `Wöchentlicher Uptime-Report — <von> – <bis>` | Cron Mo automatisch | `PageMonitorService::sendReports()` → `buildReportHtml(7, weekly)` |
| **Monatlicher Report** (1. des Monats) — Subject `Monatlicher Uptime-Report — <von> – <bis>` | Cron 1. automatisch | `PageMonitorService::sendReports()` → `buildReportHtml(30, monthly)` |
| **Test-Report** — Subject `TEST Uptime-Report — <von> – <bis>` | manueller Button | `PageMonitorService::testReport()` |

Alle Templates inline-HTML mit Tabellen, Farbcodes (grün ≥99%, orange ≥95%, rot <95%), Detail-Sektion pro Website inkl. Sub-URLs (eingerückt, leichter Hintergrund).

### Mail-Log

- File-basiert: `/var/log/ki-tool-pm-mail.log` (eine Zeile pro Mail mit Type + Empfänger + Subject + Label)
- UI-Viewer im Header-Button „Mail-Log" — zeigt letzte 100 Einträge

### Site-Monitor Cron-Setup

```
*/2 * * * * www-data php /var/www/scripts/pm-check.php >> /var/log/ki-tool-pm-check.log 2>&1
```

In `/etc/cron.d/ki-tool-site-monitor` installiert. Manueller Test:
```
sudo -u www-data php /var/www/scripts/pm-check.php --verbose
```

---

## 22b. Site-Monitor: JSON-Import aus Tallyr — ✓

**Setup im Tallyr (einmalig, 15 Zeilen Code):**
1. Im KI-Tool unter `/admin/site-monitor` → Button **„Tallyr-JSON"** → **„Snippet kopieren"** klicken
2. Snippet (15 Zeilen, [docs/tallyr-site-monitor-export-mini.php](tallyr-site-monitor-export-mini.php)) in die `functions.php` des Tallyr-Childthemes pasten — oder als kleines Plugin anlegen
3. Im Browser aufrufen: `https://<tallyr-host>/?tallyr_export=monitor&key=<SECRET>` (Secret = `tallyr_monitor_cron_key` aus wp_options, gleicher Key wie beim Cron)
4. JSON-Datei herunterladen — danach gleicher Workflow wie beim Projektplanner

**Alternative für die volle Datei-Variante:** [docs/tallyr-site-monitor-export.php](tallyr-site-monitor-export.php) (60 Zeilen, mit Doc-Block) als separate Datei nach `kreation-tallyr/inc/export-monitor.php` und in `functions.php` `require_once`.

**Import im KI-Tool:**
1. `/admin/site-monitor` → Button „Tallyr-JSON" oben rechts
2. JSON-Datei auswählen → Preview erscheint
3. Pro Tallyr-Kunde entscheiden:
   - **Auf KI-Tool-Kunden mappen** (Auto-Match via Name versucht, sonst Dropdown)
   - **„kein Kunde"** — Monitor wird ohne Kunden-Verknüpfung importiert
   - **„Überspringen"** — Monitor wird nicht importiert
4. „Import starten" — Duplikate (URL existiert schon) werden automatisch übersprungen

**Endpoint-Schema:**
```json
{
  "export_version": "1.0",
  "monitors": [
    {
      "id": 123, "client_id": 12, "url": "https://...", "label": "...",
      "category": "Kundenprojekte", "alert_email": "...",
      "report_schedule": "both", "sub_urls": ["..."],
      "status": "up"
    }
  ],
  "clients": [{"id": 12, "title": "...", "shortdesc": "..."}]
}
```

**Backend-Endpoints:**
- `POST /api/v1/admin/site-monitor/import-preview` — Datei lesen + Mapping-Vorschlag
- `POST /api/v1/admin/site-monitor/import` — Echter Import mit `_customer_mapping`
- `GET /api/v1/admin/site-monitor/mail-log` — letzte 100 Mail-Log-Einträge

---

## 23. Was bewusst nicht migriert wurde

Bewusste Entscheidungen — wenn nötig später nachholen:

- **Spaltenbreiten-Resize per Mausdrag**: zu viel UI-Engineering für wenig Nutzen — Schriftgröße A−/A+ deckt den gleichen Use Case ab.
- **Komma/Backspace im Resp-Input direkt**: das Autocomplete-Popover bietet die gleiche Funktionalität (mit Pfeil-Navigation + Enter + Tab).
- **WordPress-spezifische Hooks und Shortcodes**: Tallyr nutzt die WP-Ökosphäre für viele Hilfsfunktionen, die in unserer Architektur entweder schon vorhanden (Auth, Cron) oder bewusst weggelassen sind (z.B. WP-User-Profil-Synchronisierung).
- **Excel-Import-UI im Editor-Header**: eigene Seite `/admin/projektplanner/import` existiert; ein extra Header-Link wäre redundant.
- **Force-Cron-Trigger-UI für Asana-Sync**: läuft alle 15 min automatisch + Manuell-Sync-Button für aktuellen Plan im Header — reicht.

---

**Konvention:** Bei jeder PP-Arbeit zuerst hier reinschauen und nach erledigtem Punkt den Status aktualisieren.

---

## 25. Großer Backlog-Sprint (22.05.2026)

Alle bis dahin offenen Backlog-Punkte in einem Block durchgezogen. Status:

### Erledigt
- **Asana-Cache-Refresh-Button** im ⋯ Mehr-Menü des Editor-Headers
- **Tab-Navigation skip** in Read-/Edit-Only-Modi via JS-Hook nach Render (`ppApplyTabSkip`)
- **Tote JS-Funktionen** im Benutzer-Tab entfernt (`startKuerzelEdit` / `speichereKuerzel`)
- **Plan-Status-Trend Widget** im Dashboard (`/admin/projektplanner/dashboard?tab=status`) — Card-Grid mit Counts pro Status + Liste aktiver Pläne
- **„Meine offenen Aufgaben über alle Pläne"** — Dashboard-Tab + Service-Methode `PpDashboardService::getOpenTasksFor(string $identifier)` + API `/admin/projektplanner/my-open-tasks`
- **Personen-Sharelinks Sortierung** — alle Spalten-Header klickbar
- **Personen-Sharelinks Filter** — Alle/Offen/Erledigt + Suche
- **Personen-Sharelinks Erledigt-Toggle ohne Login** — Public-API `POST /public/personen-aufgaben/{hash}/toggle-done`, prüft serverseitig dass Person zugeordnet ist
- **Inline-Edit-Fehler-Feedback in Zelle** — `.pp-cell-saving` / `.pp-cell-saved` / `.pp-cell-error`-Klassen mit Pulse + Bestätigung + Fehler-Rahmen
- **Lazy-Loading kollabierter Sektionen** — bereits funktional (collapsed sections werden in `ppFilteredRows` komplett aus dem DOM gelassen)
- **Cross-Plan-Bulk-Aktionen** — bereits funktional (`ppBulkPatch` nutzt `row._planId || activePlanId` pro Zeile)
- **Plan-Templates / Wiederkehrende Aufgaben** — `duplicatePlan` erweitert um `{title, period_from, period_to, shift_dates, reset_ist, reset_done}`; Modal schlägt Folge-Quartal vor + verschiebt Deadlines proportional
- **PDF-Export** — Print-Stylesheet + ⋯ Mehr → „Drucken / PDF" (Browser-Drucken liefert PDF via System-Druckdialog)
- **Deadline-Reminder per Mail** — Cron `/etc/cron.d/ki-tool-pp-notifications` 08:00 täglich, Tabelle `pp_notification_log` für Idempotenz
- **Feedback-Notification per Mail** — gleiches Script, schickt Plan-Owner ungelesenes Feedback
- **Tablet-Responsivität** — Breakpoints @1024px (Sidebar hover-collapse) + @640px (Drawer-Sidebar mit Mobile-Toggle-Button, Spalten reduziert)

### Bewusst übersprungen
- **Asana-Auto-Task-Erzeugung beim Plan-Speichern** — widerspricht expliziter Asana-Policy „Wir schreiben niemals zu Asana"

---

## 26. Design-Token-Sprint (25.05.2026)

Alle 5 Projektplanner-Views (`index.php`, `dashboard.php`, `person.php`, `import.php`, `share.php`) auf die zentralen Design-Tokens aus `/admin/settings?tab=design` umgestellt. So folgt der Projektplanner ab jetzt automatisch dem im Design-Tab eingestellten Dichte-Profil + Feintuning.

### Was getauscht wurde (~270 Replacements gesamt)
- **Schriftgrößen** (hardcoded 9–14 px) → `var(--d-fs-xs)` (9–11px) bzw. `var(--d-fs-sm)` (12–13px) bzw. `var(--d-fs-base)` (14px)
- **Border-Radius — Controls** (Buttons, Inputs, Filter-Chips: 4/5/6 px) → `var(--d-control-radius)`
- **Border-Radius — Cards** (Sidebar, Sticky-Head, KPI-Cards, Modal-Bodies: 8/10/12 px) → `var(--d-card-radius)`

### Was NICHT getauscht wurde (bewusst)
- **Pillen-Radien 11/14/9999px** (Status-Pills, Bulk-Bar-Pills, Avatar-Kreise) — Pille soll immer rund bleiben, unabhängig vom Card-Radius
- **Pixel-präzise Icon-Größen** (z.B. `font-size:14px` für material-symbols, `width:8px` für Color-Dots) — Pixel-Snapping wichtig
- **Layout-Maße** (z.B. `min-width:1100px` für Tabelle, `width:220px` für Sidebar) — keine Token im System dafür

### Live-Steuerung
Wer jetzt unter `/admin/settings?tab=design` das Dichte-Profil auf „luftig" (`--d-scale=1.1`) oder „kompakt" (`--d-scale=0.85`) stellt, sieht den Effekt sofort im Projektplanner (Schriften skalieren, KPI-Cards bekommen Radius oder werden rechteckig, etc.).

### Limitation
Die Top-Action-Buttons im Editor-Header sowie die ⋯-Mehr-Menü-Buttons nutzen schon `.thx-btn`-Klassen, sind damit automatisch in `--d-control-h` / `--d-control-pad-x` integriert.

Inline-Styles in JS-Templates (z.B. `style="font-size:11px"` in einer dynamisch gebauten Tabellen-Zelle) wurden NICHT angefasst — dafür müsste man den JS-Code refaktorisieren. Stehen lassen, bei Bedarf später nachziehen.

### Nachzug 25.05.2026 — Paddings, Spacings, Tabellen-Zellen
- **Container-Paddings**: Sidebar-Head / Editor-Head / Stats-Bar / Filter-Bar / Bulk-Bar / Perm-Banner / Sort-Banner / Sidebar-Foot → `var(--d-card-pad)` (große Container) oder `var(--d-row-pad-y) var(--d-card-pad)` (schlanke Bars)
- **Tabellen-Zellen**: `.pp-table th/td`, `.pd-table th/td`, `.pd-forecast-table th/td`, `.ps-table th/td`, `.pp-task-table th/td` → `var(--d-tbl-pad-y) var(--d-tbl-pad-x)`
- **Row-Container** (Plan-Items, Context-Menu, Mehr-Menu, Sidebar-Selection): `var(--d-row-pad-y) var(--d-row-pad-x)`
- **Controls** (Inputs, Selects, Search, Filter-Chips, Bulk-Select): `var(--d-row-pad-y) var(--d-control-pad-x)` + `font-size: var(--d-control-fs)` für Inputs/Selects
- **Gaps**: Section-Gap (`--d-section-gap`) zwischen Block-Containern, Row-Gap (`--d-row-gap`) zwischen Inline-Elementen
- **Pillen-Radius (Chips, Stats, Pills)**: explizit `999px` (vollrund) statt Token — sollen IMMER rund bleiben, unabhängig vom Card-Radius

Damit folgt der gesamte Projektplanner jetzt vollständig den Density-Profilen aus `/admin/settings?tab=design` — sowohl Schriften als auch Container-Paddings + Tabellen-Spacings.

---

## 24. Backlog — offene Punkte (Stand 25.05.2026 nach Sprints §25 + §26)

Die ursprünglichen 17 Backlog-Punkte aus dem Audit-Sprint wurden in §25 alle abgearbeitet — bis auf einen bewussten Skip:

### Bewusst zurückgestellt (Policy-Konflikt)
- **Asana-Tasks automatisch erzeugen beim Plan-Speichern** — Würde Schreibzugriff auf Asana erfordern, widerspricht der expliziten Policy in [/admin/settings?tab=asana](/admin/settings?tab=asana): *„Wir schreiben niemals zu Asana"*. Falls die Policy sich ändert, wieder hier eintragen.

### Daten-Cleanup-Backlog
Aus den Sprints kamen folgende Datenqualitäts-Themen, die nicht im Code-Backlog stehen aber bei Bedarf abgearbeitet werden müssen:

- **Unbekannte Tokens in `pp_plan_rows.responsible`** — Restwerte wie „jst jdr tki" (1×) oder ähnliche Tippfehler sind unverändert geblieben (siehe §25 — normalizeRowName mappt nur Voll-Tokens, keine Substring-Fragmente). Manuell beheben oder neue Mapping-Tabelle pflegen.
- **Inaktive User reaktivieren** — Benny (User-ID 5) und Thomas Kilian WITTEKIND (User-ID 11) wurden vom Stale-User-Cron deaktiviert. Sind aus den PP-Dropdowns raus. Falls jemand davon noch aktiv arbeiten soll → in [/admin/users](/admin/users) reaktivieren.
