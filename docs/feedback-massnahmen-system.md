# Feedback- und Maßnahmen-System — Funktionsweise & Handling

Übergabe-Doku (Stand 02.07.2026). Beschreibt das Feedback-Cockpit unter
`/admin/feedback` inklusive der Maßnahmen-Pipeline, so dass sich das Konzept
1:1 nachbauen lässt. App-Kontext: PHP ohne Framework, MySQL, Apache, Vanilla-JS/Alpine,
kein Build-Step.

## 1. Grundidee in einem Satz

Jeder eingeloggte Nutzer kann aus dem Tool heraus **Feedback** abgeben (mit Screenshot/Video).
Dieses Rohfeedback landet im Cockpit, wird dort per KI zu umsetzbaren **Maßnahmen** (To-dos)
gebündelt, und die Maßnahmen werden systematisch abgearbeitet. Es gibt also **zwei Ebenen**:

```
Rohfeedback (internal_feedback)  --->  Maßnahme / To-do (feedback_measures)
   "irgendwas stimmt hier nicht"        "Das und das ist konkret zu tun"
```

Verknüpft sind beide über eine n:m-Tabelle, weil mehrere Feedbacks zum selben Thema in EINE
Maßnahme fließen können.

## 2. Datenmodell

Vier Tabellen. DDL steht in `core/App.php` (Migrationen laufen dort automatisch mit
Duplicate-Column-Check).

### 2.1 `internal_feedback` — das Rohfeedback

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | INT PK | |
| `user_id` | INT FK→users | Wer das Feedback abgegeben hat |
| `page_url` | VARCHAR(500) | Auf welcher Seite es entstand (Kontext) |
| `feedback_type` | ENUM(`bug`,`feature`,`improvement`,`other`) | Art |
| `title` | VARCHAR(255) | Kurztitel (Pflicht beim Absenden) |
| `description` | TEXT | Beschreibung (optional) |
| `media_type` | ENUM(`screenshot`,`video`,`none`) | Legacy-Feld = erstes Medium |
| `media_path` | VARCHAR(500) | Legacy-Pfad = erstes Medium |
| `status` | ENUM(`new`,`in_progress`,`resolved`,`wont_fix`) | Bearbeitungsstand |
| `admin_notes` | TEXT | interne Notizen |
| `next_steps` | TEXT | nächste Schritte (von KI oder Hand) |
| `ai_suggestion` | TEXT (JSON) | gespeicherter KI-Vorschlag |
| `browser_info` | TEXT (JSON) | User-Agent, Bildschirmgröße |
| `created_at` / `updated_at` | TIMESTAMP | |
| `resolved_at` / `resolved_by` | | wird bei `resolved`/`wont_fix` gesetzt |

### 2.2 `feedback_media` — Anhänge (n:1 zum Feedback)

Ein Feedback kann Screenshot UND Video nebeneinander haben.

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | INT PK | |
| `feedback_id` | INT | zugehöriges Feedback |
| `media_type` | ENUM(`screenshot`,`video`) | |
| `media_path` | VARCHAR(500) | z.B. `/uploads/feedback/feedback_...png`. Datei liegt unter `ROOT_PATH + media_path` |

### 2.3 `feedback_measures` — die Maßnahme / das To-do

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | INT PK | |
| `title` | VARCHAR(255) | prägnanter Titel |
| `description` | TEXT | Umsetzungs-Vorschlag / nächste Schritte |
| `area` | VARCHAR(100) | Bereich, z.B. Chat, Wissen, CRM, Allgemein |
| `status` | ENUM(`offen`,`in_arbeit`,`erledigt`,`verworfen`) | Lifecycle |
| `priority` | ENUM(`hoch`,`mittel`,`niedrig`) | |
| `source` | ENUM(`ki`,`manuell`) | KI-Vorschlag oder von Hand angelegt |
| `created_by` | INT NULL | Ersteller (NULL = System/Cron) |
| `created_at` / `updated_at` | DATETIME | |

### 2.4 `feedback_measure_links` — Verknüpfung Maßnahme ↔ Feedback (n:m)

`(measure_id, feedback_id)` als Primärschlüssel. „Verarbeitet" heißt schlicht: das
Feedback taucht in dieser Tabelle auf. Es braucht dafür kein zusätzliches Flag am Feedback.

## 3. Feedback-Eingang (wie kommt Feedback rein?)

- **Widget** global im Layout `views/layouts/main.php` (`#feedback-widget`). Trigger sitzt in der
  Sidebar (`data-feedback-trigger`, „Feedback senden"). Öffnet ein Panel mit: Art-Auswahl, Titel,
  Beschreibung und Buttons zum **Screenshot** (html2canvas) bzw. **Bildschirmaufnahme** (MediaRecorder,
  WebM).
- Absenden geht an **`POST /api/v1/feedback/internal`** (Handler in `api/handler.php`, Case
  `/feedback/internal`). Ablauf dort:
  1. Titel ist Pflicht, Beschreibung optional.
  2. Medien kommen als Daten-URLs (`data:image/...;base64,` bzw. `data:video/webm`). Jedes wird
     dekodiert und nach `uploads/feedback/` geschrieben (Dateiname
     `feedback_<ts>_<userId>_<rand>.<ext>`).
  3. Insert in `internal_feedback` (Legacy-Felder = erstes Medium), zusätzlich jedes Medium in
     `feedback_media`.
  4. `browser_info` (User-Agent + Bildschirmmaße) wird mitgeloggt.
- Der Eingang ist für **alle eingeloggten Nutzer** erlaubt (auch Gäste dürfen schreiben; steht in
  der Guest-Write-Allowlist in `api/handler.php`).
- **Upload-Verzeichnis** `uploads/feedback/` muss `www-data:www-data` gehören.

## 4. Das Cockpit `/admin/feedback`

- View: `views/admin/feedback.php` (~950 Zeilen, Alpine-Komponente `fbCockpit()`), inline JS/CSS.
- Web-Route registriert in `core/App.php` (`$router->get('/admin/feedback', ...)`), ebenso
  `/admin/measures` (nur die API, das UI ist im selben Cockpit).
- **Berechtigung: `CAP_USERS_MANAGE`** (Cap-Prefix-Mapping in `api/handler.php`; Admin hat sie immer).
  Das ist bewusst eng gehalten, weil hier interne Produktsteuerung passiert.
- **Menü-Badge**: Die Sidebar zeigt die Anzahl `internal_feedback` mit `status='new'` als rote Zahl.

### Aufbau: 3 Spalten (ein Cockpit für BEIDE Ebenen)

```
┌ Filter-Sidebar ┬ Liste ───────────┬ Ticket / Detail ──────────┐
│ Feedback nach  │ Feedback-Items    │ Ausgewähltes Feedback:    │
│ Status         │  ODER             │  - Beschreibung, Medien   │
│ Maßnahmen nach │ Maßnahmen-Liste   │  - KI-Analyse-Block       │
│ Status         │ (je nach Modus)   │  - Angelegte Maßnahmen    │
└────────────────┴───────────────────┴───────────────────────────┘
```

Man schaltet in der Sidebar zwischen dem **Feedback-Modus** und dem **Maßnahmen-Modus** um
(`enterMeasures(status)`); die rechte Spalte zeigt entsprechend ein Feedback-Ticket oder eine
Maßnahmen-Detailansicht mit den verknüpften Ursprungs-Feedbacks.

## 5. Statuslogik

**Feedback** (`internal_feedback.status`):
`new` → `in_progress` → `resolved` (oder `wont_fix`). Beim Umwandeln in eine Maßnahme oder beim
Verknüpfen durch die KI-Analyse springt das Feedback automatisch auf `in_progress`, damit es aus
der „neu"-Ansicht verschwindet.

**Maßnahme** (`feedback_measures.status`):
`offen` → `in_arbeit` → `erledigt` (oder `verworfen`). Sortierung im Cockpit: offen zuerst, dann
`in_arbeit`, dann nach Priorität (`hoch` > `mittel` > `niedrig`), dann Datum.

## 6. Die KI-Funktionen (Kern des Systems)

Zentral in `services/FeedbackMeasureService.php`. Dieselbe Logik nutzen sowohl die API (Cockpit)
als auch das Cron-Skript.

### 6.1 Einzel-Analyse: `analyzeOne(feedbackId, settings)`

- Ausgelöst im Ticket über „Maßnahme vorschlagen" bzw. „KI-Analyse".
- Nimmt EIN Feedback, schickt es ans LLM (System-Prompt: „Du bist Produkt-Managerin …").
- Erwartet JSON zurück: `summary`, `measure {title, area, priority}`, `next_steps[]`.
- Speichert das Ergebnis in `internal_feedback.ai_suggestion`, befüllt `next_steps` (nur wenn der
  Mensch dort noch nichts eingetragen hat) und setzt den Titel, falls leer.
- API: `POST /api/v1/admin/feedback/{id}/analyze` (`api/v1/admin/feedback.php`, `action=analyze`).

### 6.2 Bündel-Analyse: `analyze(settings, userId)`

- Holt alle **offenen, noch nicht verknüpften** Feedbacks (`unprocessedFeedback()`:
  `status='new'` UND nicht in `feedback_measure_links`).
- Schickt die Liste kompakt ans LLM mit der Anweisung, thematisch gleiche Feedbacks in **EINE**
  Maßnahme zu bündeln. Erwartetes JSON: `{ measures: [{title, area, priority, description,
  feedback_ids[]}] }`.
- Legt je Vorschlag eine Maßnahme an (`source='ki'`, `status='offen'`), verknüpft die
  Ursprungs-Feedbacks und setzt sie auf `in_progress`.
- Nur Feedback-IDs, die tatsächlich in dieser Analyse enthalten waren, werden verlinkt (Schutz
  gegen halluzinierte IDs).
- API: `POST /api/v1/admin/measures/analyze`.

### 6.3 Modellwahl `pickModel()`

Priorität: **Anthropic** (`claude-sonnet-4-5`) vor **OpenAI** (`gpt-5`). API-Keys kommen aus
`settings` und werden per `Core\Settings::decryptMap()` entschlüsselt. Ohne Key wirft die Analyse
einen klaren Fehler.

> Hinweis beim Nachbau: Die Stilregeln stecken direkt im System-Prompt (Du/Dich groß, keine
> Anglizismen wo deutsche Begriffe passen, keine Gedankenstriche). Die KI antwortet strikt als
> JSON; geparst wird mit einem `\{[\s\S]*\}`-Regex plus `json_decode`.

## 7. Wochen-Routine (Cron) + Admin-Ping

- Skript: `scripts/feedback-analyze.php`.
- Cron: `/etc/cron.d/ki-tool-feedback-analyze` → **montags 08:00**
  (`0 8 * * 1 root /usr/bin/php /var/www/scripts/feedback-analyze.php >> /var/log/ki-tool-feedback-analyze.log 2>&1`).
- Ablauf: offene unverarbeitete Feedbacks holen → `analyze()` (created_by = NULL = System) →
  bei neuen Maßnahmen eine **E-Mail an alle aktiven Admins** (`EmailService::fromSettings`), mit
  Tabelle (Titel/Bereich/Priorität) und Button „Maßnahmen öffnen" → `/admin/feedback?ms=offen`.
- Flags: `--dry-run` (nur zählen, kein LLM, kein Schreiben), `--quiet` (Maßnahmen anlegen, keine Mail).
- Die Routine **legt nur Vorschläge an** (status `offen`), sie ändert oder löscht nie
  Feedback-Inhalte. Ist kein SMTP konfiguriert, werden die Maßnahmen trotzdem angelegt (nur kein Ping).

## 8. API-Übersicht

Alle unter `/api/v1`, geroutet in `api/handler.php`. Cockpit-Endpunkte brauchen `CAP_USERS_MANAGE`.

**Feedback-Eingang (alle eingeloggten Nutzer):**
```
POST /feedback/internal          Feedback anlegen (Titel, Beschreibung, media[], page_url)
```

**Feedback verwalten (`api/v1/admin/feedback.php`):**
```
GET    /admin/feedback[?status=]      Liste bzw. ?id= für Einzel
PUT    /admin/feedback/{id}           status | admin_notes | next_steps | title
DELETE /admin/feedback/{id}           löscht Feedback + Mediendatei
POST   /admin/feedback/{id}/analyze   KI-Einzel-Analyse (analyzeOne)
```

**Maßnahmen verwalten (`api/v1/admin/measures.php`):**
```
GET    /admin/measures[?status=]      Liste (offen|in_arbeit|erledigt|verworfen|all) inkl. feedback_count
GET    /admin/measures/{id}           Einzeln inkl. verknüpfter Feedbacks
POST   /admin/measures                Maßnahme manuell anlegen
PUT    /admin/measures/{id}           status | priority | title | description | area
DELETE /admin/measures/{id}           löschen (samt Links)
POST   /admin/measures/from-feedback  Body {feedback_id} → 1 Feedback in 1 Maßnahme umwandeln
POST   /admin/measures/analyze        KI-Bündel-Analyse (analyze)
```

## 9. Handling im Alltag

1. **Feedback kommt rein** über das Widget (Nutzer klickt „Feedback senden", macht Screenshot,
   tippt Titel). Landet als `new` im Cockpit, Sidebar-Badge zählt hoch.
2. **Admin sichtet** unter `/admin/feedback`. Pro Ticket: Medien anschauen (Lightbox), optional
   „Maßnahme vorschlagen" (KI erkennt To-do + nächste Schritte).
3. **Bündeln**: Entweder wöchentlich automatisch (Cron-Mail mit Vorschlägen) oder manuell per
   „KI-Analyse" über alle offenen Feedbacks. Ergebnis sind Maßnahmen im Status `offen`.
4. **Abarbeiten**: Maßnahmen nach Priorität durchgehen, Status `offen` → `in_arbeit` → `erledigt`.
   Verworfene Ideen auf `verworfen`. Für das systematische Abarbeiten durch eine Claude-Code-Session
   gibt es ein eigenes Runbook: **`docs/massnahmen-abarbeiten.md`** (inkl. DB-Bootstrap-Snippet und
   der Regel, verknüpfte Screenshots mit dem Read-Tool zu öffnen und die Markierungen anzuschauen).

## 10. Beteiligte Dateien (Landkarte)

| Datei | Aufgabe |
|---|---|
| `views/layouts/main.php` | Feedback-Widget (Eingang) + Sidebar-Badge |
| `api/handler.php` (Case `/feedback/internal`) | Feedback-Eingang, Medien speichern |
| `views/admin/feedback.php` | Cockpit-UI (Feedback + Maßnahmen, Alpine `fbCockpit()`) |
| `api/v1/admin/feedback.php` | Feedback-CRUD + Einzel-KI-Analyse |
| `api/v1/admin/measures.php` | Maßnahmen-CRUD + Bündel-Analyse + from-feedback |
| `services/FeedbackMeasureService.php` | gesamte Logik (CRUD, `fromFeedback`, `analyze`, `analyzeOne`, `pickModel`) |
| `scripts/feedback-analyze.php` | Wochen-Routine + Admin-Ping |
| `/etc/cron.d/ki-tool-feedback-analyze` | Cron (Mo 08:00) |
| `core/App.php` | DDL der vier Tabellen (Auto-Migration) |
| `docs/massnahmen-abarbeiten.md` | Runbook zum Abarbeiten der Maßnahmen |

> Achtung, nicht verwechseln: `services/FeedbackAnalyzer.php` ist ein **anderes, älteres**
> System (Artikel-Sektions-Feedback `section_feedback` → `rule_suggestions`, Textqualitäts-Regeln).
> Es hat mit dem Feedback-Cockpit nichts zu tun.
