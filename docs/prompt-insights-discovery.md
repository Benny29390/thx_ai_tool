# Prompt-Insights — Discovery-Bericht

**Datum:** 2026-05-25
**Bezug:** [docs/Prompt-Insights_Briefing.md](Prompt-Insights_Briefing.md)
**Ziel:** Bestehendes System inventarisieren, bevor das neue Modul gebaut wird. Diskrepanzen zum Briefing benennen, statt sie eigenmächtig aufzulösen.

---

## 1. Stack & Konventionen

- **Backend:** PHP 8.4, kein Framework. Vanilla MVC-Struktur.
  - `core/` — Auth, Database, Response, Router, Session, Settings, Crypto, AuditLog
  - `services/` — Business-Logik (eine Service-Klasse pro Modul)
  - `api/handler.php` — zentraler API-Router (Prefix-Mapping)
  - `api/v1/admin/<modul>/` — Endpoints
  - `views/<modul>/` — PHP-Views mit Inline-JS
  - `scripts/` — Cron-Entrypoints
  - `config/config.php` — DB / App-Key / Sessions / AI-Provider
- **Frontend:** Vanilla JS, kein Build-Step, inline `<script>` in PHP-Views. Alpine.js per CDN nur in 2 Views (LAM-Module).
- **DB:** MySQL via PDO (Wrapper: `Core\Database`). Migrations idempotent in `core/App.php` mit Duplicate-Column-Catch.
- **Naming-Konventionen:**
  - Tabellen: snake_case, Modul-Prefix (`pp_*`, `lam_*`, `pm_*`)
  - Routes: kebab-case unter `/admin/<modul>/<aktion>`
  - JS-Funktionen: `<modulPrefix>...()` (z.B. `ppRender`, `pdLoad`, `lamFilter`)
  - CSS-Klassen: `<modulPrefix>-...` (z.B. `pp-`, `pd-`, `lam-`)
- **Domain:** `ai.thoxan-dev.de` (Dev), Apache + PHP-FPM

## 2. Styling-System

- **Design-Tokens:** `assets/css/thx-tokens.css` — globale Variablen für Farben, Schriften, Density (--d-fs-*, --d-card-pad, --d-tbl-pad-*, --d-control-* etc.)
- **Komponenten:** `assets/css/thx-components.css` — wiederverwendbare `.thx-*`-Klassen (Buttons, Cards, Tables, Chips, Modals, Page-Header, Tabs, Bulk-Bars)
- **Schrift:** Frutiger LT Std (Bold/Roman, Webfont in `/assets/fonts/lam/`)
- **Basisgröße:** `html { font-size: 120% }` global
- **Density-Profile:** „mini / kompakt / luftig" steuerbar im `/admin/settings?tab=design` — alle `.thx-*` und neue Module folgen automatisch
- **Layout:** Topbar (44px) + Sidebar (260/60 px) + Content-Bereich rechts
- **Konvention:** keine eigenen Designkomponenten neu bauen, nur `.thx-*` (oder neue `.pi-*` mit denselben Tokens)

## 3. Auth + Permissions

- **Rollen:** `admin`, `manager`, `user`, `guest` (in `users.role`)
- **Capabilities:** Tabelle `user_capabilities`. Bestehende Caps:
  `chat`, `artifacts`, `knowledge`, `coworker`, `lam`, `projektplanner`, `customers_view`, `customers_manage`, `users_manage`, `settings_manage`
- **Defaults pro Rolle:** Tabelle `role_capabilities`, pflegbar unter `/admin/users?tab=rollen`
- **Helper:** `\Core\Auth::can($cap)`, `Auth::isAdmin()`, `Auth::isManagerOrHigher()`, `Auth::user()`
- **Cap-Schutz auf API:** Prefix-Mapping in `api/handler.php`
- **Cap-Schutz auf Web-Routes:** `capMiddleware(CAP_X)`-Factory in `core/App.php`
- **Admin hat IMMER alle Caps** (server-seitig erzwungen, UI nur informativ)
- **Audit-Log:** Tabelle `permission_audit_log`, `\Core\AuditLog::record(...)`, Sicht unter `/admin/users?tab=audit`

## 4. Settings + Secrets

- **Tabelle `settings`** mit `setting_key` / `setting_value`
- **Secrets** (API-Keys, PATs, Passwörter) liegen AES-256-GCM-verschlüsselt mit `enc:v1:`-Präfix
- **Heuristik:** Key enthält `api_key|_pat|password|secret|_token` → automatisch verschlüsselt
- **App-Key:** `config.php → app.encryption_key` (64 hex chars)
- **Lesen:** `\Core\Settings::get($key)`
- **Schreiben:** `\Core\Settings::set($key, $value)`

## 5. LLM-Anbindung

- **Service:** `services/AIService.php` — kapselt OpenAI + Anthropic
- **Methoden:** Chat-Streaming (SSE), Single-Call, Embeddings
- **Modelle:** in Tabelle `ai_models` (provider, model_id, display_name, is_active, sort_order)
- **Konfig-Tab:** `/admin/settings?tab=ki`
- **Embeddings:** **bereits vorhanden** via OpenAI `text-embedding-3-small` (1536 Dimensionen), multilingual fähig
  - Service: `services/EmbeddingService.php` — erstellt, speichert, sucht Vektoren
  - Storage: **SQLite pro Kunde** unter `storage/vectors/{slug}.sqlite` + global `artifacts.sqlite`
  - Similarity: Cosine, bestehende `findSimilar()`-Methode

## 6. Existing Models für Konversationen

- **Tabellen:** `chat_conversations`, `chat_messages` — bestehender Chat-Bereich
  - Felder: id, user_id, title, model_used, created_at, updated_at, deleted_at, feedback usw.
- **Artefakte:** Tabelle `artifacts` (5 Typen: Regel, Wissen, Profil, Autor, Namespace) + `artifact_imports` für Upload-Workflow
- **DocumentProcessor:** `services/DocumentProcessor.php` — PDF/DOCX/HTML/TXT Text-Extraktion

## 7. Sidebar + Menü-Struktur

Aktuelle Sidebar (in `views/layouts/main.php`):
- Dashboard
- Kunden
- Chat
- Wissen
- Guidelines
- KI Kompass
- Projektplanner
- Website-Monitor
- LAM-System
- Mail
- ─ Administration ─
- Benutzer
- **KI & Modelle** ← darunter soll Prompt-Insights laut User
  - KI-Modelle
  - Verbrauch
- System
  - Einstellungen
  - Feedback
  - Backups
  - System-Log
  - Jobs

## 8. Logging + Error-Handling

- **PHP-Errors:** Apache-Error-Log (`/var/log/apache2/ai.thoxan-dev.de-error.log`)
- **Modul-spezifische Logs:** `/var/log/ki-tool-<modul>.log` (z.B. `ki-tool-pp-asana-sync.log`, `ki-tool-pm-mail.log`, `ki-tool-pp-notifications.log`)
- **Convention:** Modul schreibt selbst per `file_put_contents(..., FILE_APPEND)` ins Log
- **Cron-Logs:** über `>> /var/log/ki-tool-*.log 2>&1` in den Cron-Definitionen

## 9. Test-Infrastruktur

- **Keine vorhandene** automatisierte Test-Suite (kein PHPUnit, kein Jest)
- Tests bisher manuell (Browser + Playwright-MCP)
- → Neues Modul folgt diesem Pattern: visuelle/manuelle Verifikation, kein Test-Framework anschaffen

## 10. Briefing-Diskrepanzen + Empfehlungen

### 10.1 Embeddings (§3 Layer 3)
- **Briefing:** sentence-transformers lokal (`paraphrase-multilingual-MiniLM-L12-v2`)
- **Befund:** OpenAI-Embeddings via API bereits voll integriert (`EmbeddingService`, SQLite-Storage, Cosine-Similarity). Modell `text-embedding-3-small` ist multilingual.
- **Empfehlung:** **bestehende OpenAI-API nutzen.** Neue Python-Dependency wäre Architektur-Bruch (kein Python im System) und Doppel-Vorhalten von Embeddings-Infrastruktur.

### 10.2 Clustering (§3 Layer 3)
- **Briefing:** HDBSCAN bevorzugt, sonst KMeans
- **Befund:** HDBSCAN ist Python-only (sklearn / hdbscan-lib). PHP hat keine vergleichbare Lib. Optionen:
  1. **Cosine-Similarity-basiertes hierarchisches Clustering in PHP** (eigene Implementation, ~50 Zeilen): Iterativ ähnlichste Prompts mergen bis Threshold erreicht. Schnell, deterministisch, gut genug für ein paar hundert Prompts.
  2. **KMeans in PHP** — Liblegit aber benötigt vorgegebene k.
  3. **Python-Subprocess** — neue Dependency, Architektur-Bruch.
- **Empfehlung:** **Option 1** (eigenes Cosine-basiertes Clustering). Threshold tunable, Beispiel-Größen passen (User wird hunderte bis wenige tausend Prompts haben).

### 10.3 LLM für Layer 4 (§9)
- **Empfehlung:** bestehende `AIService`-Integration nutzen, Standard-Modell aus `/admin/settings?tab=ki`. Im Settings ggf. ein eigenes Modell pro Modul wählbar.

### 10.4 Whitelist (§9)
- **Befund:** Es gibt schon `customers` (Name + Slug + Abbreviation) und `pp_team_members` JOIN `users` (Name + Nickname + Abbreviation).
- **Empfehlung:** Initiale Whitelist beim ersten Aufruf aus diesen Quellen vorschlagen, User akzeptiert/erweitert.

### 10.5 Capability + Sidebar
- **Vorschlag:** Neue Capability `prompt_insights`. Default-Caps: nur Admin + Manager.
- **Vorschlag Sidebar:** Untergruppe „KI & Modelle" enthält dann: `KI-Modelle`, `Verbrauch`, **`Prompt-Insights`**.

### 10.6 Naming
- **Vorschlag:** „Prompt-Insights" bleibt — passt in den Stil der anderen Module (alle englisch-deutsch-Mix).

### 10.7 Anonymisierung-Regex
- **Briefing:** Mail, Tel, IBAN, URL Pattern + Whitelist
- **Empfehlung:** Vor jedem Outbound-LLM-Call doppelter Check (DB-Insert + Layer-4-Sendung).

## 11. Vorgehen

Reihenfolge wie im Briefing §10. Vor Implementierungsstart: AskUserQuestion mit den 4 entscheidenden offenen Punkten aus §9 (Naming, Embeddings, Cluster, LLM).
