# KI Text Tool — Projektdokumentation

## Was ist das?

Ein KI-gestuetztes Textwerkzeug mit drei Kernbereichen: **Chat**, **Artefakte** und **LAM-System**. Der Chat nutzt verschiedene LLMs (GPT, Claude) zum Schreiben. Artefakte sind die Wissensbasis — Regeln, Fakten, Profile, Stilprofile — die der KI als Kontext mitgegeben werden. Das LAM-System ist das Linkaufbau-Management der Thoxan Communications GmbH.

## Globale Design-Standards (seit 20.05.2026)

- **Schrift:** Frutiger LT Std (Roman + Bold), Webfont unter `/assets/fonts/lam/`
- **Basisgroesse:** `html { font-size: 120% }` — global, alle rem-Werte skalieren
- **Logo:** SVG `/assets/images/thoxan-logo.svg`, X-only `/assets/images/thoxan-x.svg` (collapsed Sidebar)
- **Farbpalette:** Thoxan-Palette in CSS-Vars (`--thoxan-50` bis `--thoxan-950`), plus Slate/Emerald/Amber/Rose/Indigo
- **Token-Datei:** `/assets/css/thx-tokens.css` — Custom Properties global, in `views/layouts/main.php` eingebunden
- **Komponenten-Klassen:** `/assets/css/thx-components.css` — `.thx-btn`, `.thx-card`, `.thx-table`, `.thx-chip`, `.thx-tabs`, `.thx-modal-*`, `.thx-page-header`, `.thx-topbar*`
- **LAM-spezifische Klassen** (uebergangsweise): `/assets/css/lam.css` — `.lam-filter-card`, `.lam-table`, `.lam-chip*`, etc. (wird schrittweise zu `.thx-*` migriert)

## Globales Layout

- **Top-Bar** (Thoxan-Blau, 44px hoch, ueber volle Breite): Hamburger-Toggle links, rechts Kunden/Einstellungen/User-Initialen/Abmelden
- **Sidebar links** (260px, oder 60px collapsed): Logo oben, Module darunter, User-Footer (legacy, ggf. spaeter aufraeumen)
- **Content-Bereich rechts**: Page-Header + (bei LAM:) Modul-Tab-Leiste + Inhalt

## Architektur

PHP-Backend (kein Framework), MySQL-DB, Apache, Vanilla-JS-Frontend (kein Build-Step).

```
/var/www/
  core/          — Auth, Database, Response, Router, Session
  api/handler.php — Zentraler API-Router (alle /api/v1/* Requests)
  api/v1/        — Endpunkte (chat-stream.php, admin/artifacts.php, ...)
  services/      — Business-Logik (AIService, ArtifactService, EmbeddingService, ...)
  views/         — PHP-Views mit Inline-JS (chat.php, admin/artifacts.php, ...)
  config/        — config.php, constants.php
  storage/vectors/ — SQLite-Dateien fuer Embeddings
```

## Die drei Kernbereiche

### 1. Chat (`/chat`, `views/chat.php`, `api/v1/chat-stream.php`)

- SSE-Streaming (Server-Sent Events)
- Modell-Auswahl: Automatik (klassifiziert beim 1. Msg, bleibt dann), oder manuell
- Dateien anhaengen (PDF, DOCX, TXT — wird als Text extrahiert)
- Feedback pro Konversation (Daumen hoch/runter + Freitext)
- Konversationen teilen, Ordner, Favoriten

### 2. Artefakte (`/admin/artifacts`, `views/admin/artifacts.php`)

Wissensbasis fuer die KI. 5 Typen:

| Typ       | Zweck                                    | Beispiel                           |
|-----------|------------------------------------------|------------------------------------|
| Regel     | Vorgaben, die die KI einhalten muss      | "Immer Du-Ansprache"               |
| Wissen    | Fakten und Informationen                 | "Firma XY, 2008 gegruendet"        |
| Profil    | Kunden-/Zielgruppenprofile               | "Entscheider 35-55, technikaffin"  |
| Autor     | Schreibstil und Tonalitaet               | "Locker, kompetent, auf Augenhoehe"|
| Namespace | Ordnet Artefakte einem Kunden/Projekt zu | "FRYKA", "cumpa", "Thoxan"         |

**Namespace-Konzept:** Namespace = Ordner fuer einen Kunden. Alle Artefakte eines Kunden haben denselben Namespace. Im Chat kann man einen ganzen Namespace auf einmal anhaengen.

## Wie Chat und Artefakte zusammenspielen

### RAG (Retrieval-Augmented Generation)

Wenn im Chat "Artefakte" aktiviert ist:

1. User schickt Nachricht
2. System erstellt Embedding der Nachricht (OpenAI `text-embedding-3-small`)
3. Cosine-Similarity-Suche gegen alle Artefakt-Embeddings (`storage/vectors/artifacts.sqlite`)
4. Relevante Artefakte (Score >= 0.55) werden in den System-Prompt injiziert
5. Manuell angehaengte Artefakte haben Vorrang, RAG fuellt den Rest (max 30.000 Zeichen)

### Manuelles Anhaengen

Ueber das Artifact-Panel im Chat (rechte Seitenleiste):
- Einzelne Artefakte suchen und anhaengen
- Ganzen Namespace anhaengen (= alle Artefakte eines Kunden)
- "Konversation analysieren" extrahiert neue Artefakte aus dem Chat-Verlauf

## Import-System

PDF/DOCX/TXT hochladen → KI analysiert → schlaegt Artefakte vor → User prueft/uebernimmt.

- Beim Upload kann ein **Kontext** angegeben werden (z.B. "Referenz von Fryka"), damit die KI den Inhalt richtig zuordnet
- Artefakte werden nach Uebernahme automatisch vektorisiert (Embedding erstellt)
- Aenderungen an Artefakten triggern ebenfalls automatische Re-Vektorisierung

## Embedding-System

- Modell: `text-embedding-3-small` (OpenAI, 1536 Dimensionen)
- Storage: SQLite pro Kunde (`storage/vectors/{slug}.sqlite`) + global `artifacts.sqlite`
- Service: `EmbeddingService.php` — erstellt, speichert, sucht Vektoren
- Entity-Tokenisierung (`EntityService.php`) ist was anderes — Wort-zu-ID-Mapping, NICHT semantisch

## Wichtige Services

| Service                    | Datei                              | Aufgabe                                              |
|----------------------------|------------------------------------|------------------------------------------------------|
| AIService                  | `services/AIService.php`           | OpenAI + Anthropic API (Chat, Streaming, Embeddings) |
| ArtifactService            | `services/ArtifactService.php`     | CRUD, Versionierung, Vectorize, Entity-Enrichment    |
| ArtifactImportService      | `services/ArtifactImportService.php` | Import-Workflow (Upload, AI-Analyse, Approve/Reject) |
| EmbeddingService           | `services/EmbeddingService.php`    | Vector-Storage + Similarity-Search (SQLite)          |
| EntityService              | `services/EntityService.php`       | Token-Aufloesung (Wort-IDs zu Text)                  |
| DocumentProcessor          | `services/DocumentProcessor.php`   | Text-Extraktion aus PDF/DOCX/HTML/TXT                |

## API-Routen (wichtigste)

```
POST /api/v1/chat/stream                  — Chat-Stream (SSE)
GET  /api/v1/admin/artifacts              — Artefakt-Liste
POST /api/v1/admin/artifacts              — Artefakt erstellen (auto-vectorize)
PUT  /api/v1/admin/artifacts/{id}         — Artefakt aktualisieren (auto-vectorize)
POST /api/v1/admin/artifact-import        — Dokument hochladen
POST /api/v1/admin/artifact-import/{id}/analyze — KI-Analyse starten (SSE)
POST /api/v1/admin/artifact-vectorize     — Artefakte vektorisieren (single/batch)
```

## Sicherheits- und Rechtesystem (seit 21.05.2026)

### Rollen + Capabilities

- 4 Rollen in `users.role`: `admin`, `manager`, `user`, `guest`
- Pro User in Tabelle `user_capabilities` ein Set aus Caps (`chat`, `artifacts`, `knowledge`, `coworker`, `lam`, `projektplanner`, `customers_view`, `customers_manage`, `users_manage`, `settings_manage`)
- Default-Caps pro Rolle zentral in Tabelle `role_capabilities` — pflegbar unter `/admin/users?tab=rollen`
- `Auth::can($cap)` für Checks; `Auth::isAdmin()`/`isManagerOrHigher()`/`isReadOnly()` für Rollen-Checks
- Admin hat IMMER alle Caps (Server-side erzwungen)
- Guest ist read-only — Schreibaktionen werden in `api/handler.php` für non-GET-Methoden generisch blockiert (Whitelist: `/auth/`, `/me`, `/feedback`)
- Cap-Schutz auf API-Endpunkten per Prefix-Mapping in `api/handler.php`
- Cap-Schutz auf Web-Routen über `capMiddleware(CAP_X)`-Factory in `core/App.php`

### Kundenzuordnung

- Effektive Kundenliste eines Users = UNION aus `user_customers` (direkt zugewiesen) und `role_customers` (über die Rolle freigeschaltet)
- `Auth::loadUserCustomers()` macht die Berechnung; `Auth::customers()` / `Auth::canAccessCustomer()` greifen überall
- Verwaltung als Matrix unter `/admin/users?tab=kunden` — Kunden × (Rollen + User)
- Wirkt überall, wo `Auth::customers()` gefragt wird: Chat-Sidebar, RAG-Retrieval, Customer-Detection, Knowledge-Dokumente, Wissens-Graph, Customer-Steckbrief

### Verschlüsselung

- Settings-Secrets (API-Keys, PATs, SMTP-Passwort) liegen in `settings.setting_value` AES-256-GCM verschlüsselt mit `enc:v1:`-Präfix
- App-Key in `config.php → app.encryption_key` (64 hex chars = 32 Byte) — NIE ändern oder verlieren
- Heuristik „was ist Secret": Key enthält `api_key|_pat|password|secret|_token` (mit Ausnahme `max_tokens_per_request`)
- **Lesen:** `\Core\Settings::get($key)` (transparente Entschlüsselung) oder `Settings::decryptMap($settingsArray)` für Loop-Pattern
- **Schreiben:** `\Core\Settings::set($key, $value)` — Secrets werden automatisch verschlüsselt
- Migration der bestehenden Klartext-Werte einmalig per `scripts/encrypt-settings.php`
- User-Passwörter sind separat in `users.password_hash` bcrypt-gehasht (war schon vorher so)

### Auth-Schutz

- **Login-Rate-Limit**: 5 Fehlversuche pro E-Mail in 15 Min → 15-Min-Sperre (`Auth::LOGIN_MAX_FAILED/LOGIN_WINDOW_MIN`, Tabelle `login_attempts`)
- **2FA-Pflicht** für Admin/Manager: roter Banner unter der Topbar bis 2FA eingerichtet (`Auth::requires2FASetup()`)
- **Login-as** für Admin: `Auth::loginAs($userId)` → Topbar-Banner mit Rückweg via `Auth::switchBack()`
- **User löschen** ist zweistufig: erst deaktivieren (`is_active=0`), dann E-Mail zur Bestätigung eintippen — verhindert versehentliches Hartlöschen
- **Inaktive User automatisch deaktivieren**: Cron `/etc/cron.d/ki-tool-stale-users` ruft täglich 03:30 `scripts/deactivate-stale-users.php --days=30` auf — Admin nie betroffen, Audit-Log dokumentiert

### Private Sichtbarkeit im Wissen (seit 14.07.2026)

- `knowledge_documents.visibility` ENUM(`privat`,`team`,`kunde`, Default `kunde`) + `owner_user_id`
- `visibility='privat'` → Dokument wird **nur** an `owner_user_id` ausgeliefert
- **BEWUSST OHNE ADMIN-AUSNAHME** — `Auth::isAdmin()` hebelt diesen Filter NICHT aus.
  Das ist die einzige Stelle im Rechtesystem, an der Admin nicht alles sieht. Nicht „reparieren".
- **Fail-closed**: kein bekannter Betrachter (Cron/CLI) → private Dokumente raus
- Zentraler Filter: `KnowledgeRetrievalHybridTrait::visibilityClause()`; der Backstop
  `loadChunkDetails()` deckt alle Such-Beine ab (auch Qdrant)
- **Bei neuen Lesezugriffen auf `knowledge_documents` den Filter mitziehen!**
  Doku: [docs/mail-exchange-wissen-projektplan.md](docs/mail-exchange-wissen-projektplan.md)

### Audit-Log

- Tabelle `permission_audit_log` (actor_user_id, target_type, target_key, action, diff JSON)
- Service `\Core\AuditLog::record($targetType, $targetKey, $action, $diff)`
- Hooks an: User-Anlage, Rollen-Wechsel, Cap-Änderungen (User + Rolle), Kunden-Zuordnung (User + Rolle), User-Deaktivierung, Bulk-Aktionen
- Sicht unter `/admin/users?tab=audit` mit Filter

### Wichtigste Dateien

- `core/Auth.php` — Rollen/Caps/Login/2FA/Kunden-Logik
- `core/Crypto.php`, `core/Settings.php` — Settings-Verschlüsselung
- `core/AuditLog.php` — Audit-Service
- `api/handler.php` — Cap-Middleware-Mapping + Guest-WriteBlocker
- `api/v1/admin/users.php`, `api/v1/admin/users-bulk.php`, `api/v1/admin/roles.php`, `api/v1/admin/user-customer-mapping.php` — Admin-APIs
- `views/admin/users.php` — Tab-Hub (Benutzer / Rollen & Caps / Kundenzuordnung / Audit-Log)
- `views/admin/users/_tab_*.php` — die einzelnen Tabs
- `views/admin/user-edit.php` — Benutzer-Detailseite
- `docs/benutzer-rechte-roadmap.md` — Status aller Roadmap-Punkte 1–8

## Transkriptions-Modul (seit 25.05.2026)

Audio/Video lokal transkribieren via faster-whisper (CPU, On-Premise). Erreichbar
unter dem Tab „Transkripte" auf der **Wissen**-Seite (Sidebar `/wissen` → Tabs
`Wissensdatenbank | Transkripte`).

- Pipeline: Upload (verschluesselt at-rest) → Cron-Worker (`*/2 * * * *`,
  max 3 parallel) → ffmpeg WAV mono 16k → faster-whisper → tr_results
- Loom-URL-Import via `yt-dlp`
- Output-Vorlagen (Memo / Workshop-DOCX / Call / Tutorial / Raw) via LLM
- Korrektur-Dictionary (`tr_corrections`, `scope=user|global`)
- Wissens-DB-Integration via `KnowledgeIngestService` (Volltext + Embeddings + Entities)
- Cap: `CAP_TRANSCRIPTION` (Default fuer admin + manager)
- Volldoku: **[docs/transkription-modul.md](docs/transkription-modul.md)**

Wichtigste Dateien:
- `services/TranskriptionService.php`, `TranskriptionEditorService.php`, `TranskriptionOutputService.php`
- `scripts/transkription-worker.php`, `scripts/transkription-process-job.php`
- `/opt/ki-tool-whisper/whisper-runner.py` (Python venv mit faster-whisper)
- `views/admin/transkription/` (Tab-Hub mit 7 Tabs)

## Maßnahmen abarbeiten

Produkt-To-dos aus Nutzer-Feedback liegen in `feedback_measures` und werden im Feedback-Cockpit (`/admin/feedback`) verwaltet. Zum systematischen Abarbeiten gibt es ein Runbook: **[docs/massnahmen-abarbeiten.md](docs/massnahmen-abarbeiten.md)**. Auslöser in einer frischen Session: `Attacke /var/www/docs/massnahmen-abarbeiten.md` (oder nur der Pfad). Die Datei beschreibt Reihenfolge, Status-Fortschritt (offen → in_arbeit → erledigt) und Sicherheitsregeln.

## Dev-Hinweise

- Domain: `ai.thoxan-dev.de`
- Kein Build-Step — alle JS/CSS ist inline in den PHP-Views
- DB-Migrationen laufen automatisch in `core/App.php` (try/catch mit "Duplicate column"-Check)
- Inline-Scripts in Views laufen VOR `app.js` — `waitForApp()` Pattern nutzen wenn noetig
- Uploads: Verzeichnisse muessen `www-data:www-data` gehoeren
- `usage_logs.action_type` ist ENUM — neue Werte brauchen ALTER TABLE

## LAM-System (Linkaufbau-Management)

Migration des Laravel-Prototyps `/var/www/lams_modul_alt/lam-prototyp/` ins KI-Tool. Original-Doku: `/var/www/docs/lam-prototyp/lam-prototyp/docs/` (Spezifikation, Briefings, Arbeitsstand).

### Layout

LAM nutzt das **main.php-Layout** (keine eigene Layout-Datei mehr). In der Sidebar ist „LAM-System" ein einzelner Eintrag (kein Submenue). Klick darauf geht zur zuletzt besuchten LAM-Seite (localStorage `thx_lam_last_path`), Fallback `/lam`.

Im Content-Bereich wird oben der **Page-Header** (`.thx-page-header` mit `.thx-page-title` + `.thx-page-subtitle`) gerendert, darunter die **horizontale Modul-Tab-Leiste** (Partial `views/lam/_tabs.php` mit `.thx-tabs` + `.thx-tab`).

### Module (alle aktiv)

| Tab | URL | Tabelle(n) | Daten (Stand 20.05.) |
|---|---|---|---|
| Dashboard | `/lam` | (aggregiert) | KPIs |
| Linkprofil | `/lam/linkprofil` | `lam_verlinkungen` | 9.259 |
| Linkquellen | `/lam/linkquellen` | `lam_domains` | 7.683 |
| Anbieter | `/lam/anbieter` | `lam_anbieter` | 31 |
| Linkakquise | `/lam/linkakquise` | `lam_vorschlagsliste_eintraege` (status=in_akquise) | — |
| Linkoptionen | `/lam/linkoptionen` | `lam_vorschlagsliste_eintraege` | 16 |
| Maßnahmen | `/lam/massnahmen` | `lam_massnahmen` | 10 |
| Auslagen | `/lam/auslagen` | `lam_auslagen` | 2 |
| Monitoring | `/lam/monitoring` | `lam_monitoring_checks` | 2 |
| Korrespondenz | `/lam/korrespondenz` | `lam_kommunikation` | 1 |

### API-Endpunkte

Alle unter `/api/v1/lam/`, alle Admin/Manager-only. Lese-Endpunkte sind GET, Schreib-Endpunkte POST. Implementiert in `api/v1/lam/*.php`, geroutet in `api/handler.php`:

**Lesen (GET):**
```
/lam/dashboard                  (KPI-Block)
/lam/dashboard-widgets          (Widgets: Anstehende Massnahmen, Alerts, Akquise, Auslagen)
/lam/linkquellen                (Liste mit Filter+Sortierung+Pagination)
/lam/domain-detail              (Konditionen, Kennzahlen, Tags, Kunden)
/lam/anbieter                   (Liste mit Filter)
/lam/anbieter-detail            (Notizen, Kontakte, Domains)
/lam/anbieter-kurz              (Kurzliste fuer Filter-Selects)
/lam/linkprofil/kunden          (Kundenliste mit Counts)
/lam/verlinkungen               (Linkprofil-Eintraege pro Kunde, Multi-Select-Filter)
/lam/linkakquise
/lam/linkoptionen
/lam/massnahmen
/lam/massnahme-detail           (Kerndaten, Auslage, Monitoring, Korrespondenz)
/lam/auslagen
/lam/monitoring
/lam/korrespondenz
/lam/korrespondenz-anhang       (File-Download, Path-Traversal-geschuetzt)
```

**Schreiben (POST, alle akzeptieren JSON-Body):**
```
Anbieter:
  /lam/anbieter-save        Body: { id?, name, firma?, rolle, beziehungsstatus, notizen? }
  /lam/anbieter-inline      Body: { id, feld: 'name'|'firma'|'rolle'|'beziehungsstatus'|'notizen', wert }
  /lam/anbieter-bulk        Body: { ids: [], aktion: 'beziehung_setzen'|'rolle_setzen'|'loeschen', wert? }

Kontakte (zu Anbietern):
  /lam/kontakt-save         Body: { id?, anbieter_id, vorname?, nachname, email?, telefon?, rolle? }
  /lam/kontakt-aktion       Body: { id, aktion: 'loeschen'|'primaer_setzen' }

Linkquellen (Domains):
  /lam/domain-save          Body: { id?, url, anbieter_id?, verifikation_status, linkart?, buchbar_via?, notizen?, disqualifiziert? }
  /lam/domain-inline        Body: { id, feld, wert }  (erlaubt: verifikation_status, disqualifiziert, notizen, anbieter_id, linkart, buchbar_via, herkunft)
  /lam/domain-bulk          Body: { ids: [], aktion: 'verifikation_setzen'|'anbieter_setzen'|'disqualifizieren'|'rehabilitieren'|'loeschen', wert? }

Linkprofil-Verlinkungen:
  /lam/verlinkung-inline    Body: { id, feld: 'linkart'|'empfehlung'|'bemerkung'|'status', wert }
  /lam/verlinkung-bulk      Body: { ids: [], aktion: 'linkart_setzen'|'empfehlung_setzen'|'loeschen', wert? }

Linkoptionen (Vorschlagslisten-Eintraege):
  /lam/linkoption-inline    Body: { id, feld: 'status'|'notiz'|'preis_kunde'|... , wert }
  /lam/linkoption-bulk      Body: { ids: [], aktion: 'status_setzen'|'loeschen', wert? }

Massnahmen:
  /lam/massnahme-inline     Body: { id, feld, wert }  (erlaubt: status, vorgangstyp, buchungstyp, linktext, brand_integration, geplant_am, veroeffentlicht_am, veroeffentlichungs_url, sonderstatus)
  /lam/massnahme-bulk       Body: { ids: [], aktion: 'status_setzen'|'loeschen', wert? }

Monitoring:
  /lam/monitoring-aktion    Body: { ids: [], aktion: 'alerts_quittieren' }
```

### View-Patterns (zentral in `assets/css/thx-components.css`)

- **`.thx-inline-edit`** + `.thx-inline-edit-frame` + `.thx-inline-edit-input/select` für Inline-Edit pro Zelle
- **`.thx-bulk-toolbar`** + `.thx-bulk-col` + `.thx-bulk-checkbox` für Bulk-Auswahl
- **`.thx-contextmenu`** + `.thx-contextmenu-item` fuer Rechtsklick-Menue
- **`.thx-drawer-backdrop`** + `.thx-drawer` + `.thx-drawer-header/body/footer` + `.thx-form-field` fuer Anlege-/Bearbeiten-Formulare
- **`.thx-modal-backdrop`** + `.thx-modal` fuer Detail-Anzeigen

Konvention in jeder LAM-View:
```html
<div x-data="lamModul()" x-init="laden()" @click="ctxMenu.offen = false">
  <!-- Page-Header + Tabs -->
  <!-- Bulk-Toolbar (x-show="auswahl.size > 0") -->
  <!-- Tabelle mit @contextmenu.prevent + Inline-Edits -->
  <!-- Contextmenu (x-show="ctxMenu.offen") -->
  <!-- Drawer (x-show="drawerOffen") -->
  <!-- Detail-Modal (x-show="detailOffen") -->
</div>
```

### Migrations-Status

Die laufende Inventur aller offenen Features steht in **[docs/lam-migration-status.md](docs/lam-migration-status.md)**. Pro Modul eine Tabelle mit Status (✓/◐/✗) und Priorität. **Bei jeder LAM-Arbeit zuerst dort schauen** und nach erledigter Migration den Status aktualisieren.

### Service-Schicht

Alle Methoden in `services/LamService.php`. Pro Modul typischerweise `liste{X}(filter)` + `get{X}Detail(id)`. Service kennt nur lesende Methoden — Inline-Edit, Bulk-Aktionen, CRUD sind noch nicht implementiert.

### Filter-Konventionen

- **Multi-Select-Chips:** Klick = exklusiv (nur dieser), Shift/Strg/Cmd = additiv. Backend nimmt `feld[]=a&feld[]=b` als Array-Parameter entgegen (`linkart`, `empfehlung`, `importquelle`, `verifikation_status`).
- **Bool-Filter** als Chip („nur neu", „ohne Empfehlung", etc.) — Backend prueft `!empty($filter['nur_x'])`.
- **Suche** ist immer LIKE über mehrere Felder mit `%suche%`.

### View-Konventionen

- Alpine.js (CDN) fuer Reaktivitaet
- `$activeModul = 'slug'` vor `_tabs.php`-Include
- Detail-Modal mit `.thx-modal-backdrop` + `.thx-modal*`, Klick auf Tabellenzeile (`.thx-row-clickable`) oeffnet
- `[x-cloak]` Style direkt im View fuer Flicker-Prevention

### Was noch fehlt (Stand 20.05.2026)

Bewusst weggelassen, noch zu bauen:
- **CRUD** (Anlegen, Bearbeiten, Löschen) für alle Module
- **Inline-Edit** in Tabellen (Klick auf Zelle → bearbeiten)
- **Bulk-Aktionen** mit Checkbox-Spalte
- **CSV-/Excel-Import** pro Modul
- **Sortierbare Header** (außer Linkquellen)
- **Aktionen im Page-Header** (Aufräumen, Sistrix-Abfragen, KI-Workflows)
- **Sistrix-/KI-Integration**

### Konventionen aus dem Prototyp (Stilvorgaben)

- **Höflichkeitsformen** Du, Dich, Dir, Dein, Ihr, Euch, Euer immer groß
- **Keine Gedankenstriche** (em-dash)
- **Anglizismen vermeiden:** „Umsetzer" statt „Implementer", „Vorgang" statt „Process", „Maßnahme" statt „Campaign"
- **Feste Schreibweisen:** Thoxan, Benny, Thomas, Gaby, Michi, FRYKA, BKK Gildemeister Seidensticker
- **Empfehlungs-/Statuszellen:** Klartext, keine farbigen Badges (siehe `docs/design-reference/lam-styleguide/`)
