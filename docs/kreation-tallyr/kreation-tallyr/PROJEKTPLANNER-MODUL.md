# Tallyr Projektplanner — Vollstaendige Modulbeschreibung

## 1. Ueberblick

Der **Projektplanner** (auch Quartalsplanner) ist ein Excel-aehnliches Projektplanungstool innerhalb des WordPress Child-Themes "kreation-tallyr". Es ermoeglicht:

- Erstellung und Verwaltung von Projektplaenen mit Zeitraeumen
- Excel-aehnliche Inline-Bearbeitung (contenteditable Divs)
- Drag & Drop Sortierung
- Multi-Plan-Ansicht (mehrere Plaene gleichzeitig)
- Asana-Integration (Aufgaben erstellen/verknuepfen)
- Oeffentliche Sharelinks mit Feedback-System
- Dreistufiges Berechtigungssystem (read/edit/write)
- Dashboard mit 6 Tabs (KPIs, Personen, Plaene, Forecast, Erledigte, Soll/Ist)
- Kundenbasiertes Budget/Soll-Ist-System mit Tagessaetzen (1 TS = 8h)
- Uebertragssystem (Rueckstand/Ueberhang aus Vorperioden)
- Person-basierter Export (Sharelink + Excel)

---

## 2. Datenbank-Schema

### 2.1 `{prefix}_tallyr_projektplanner` — Plaene

```sql
CREATE TABLE {prefix}_tallyr_projektplanner (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid        BIGINT(20) UNSIGNED NOT NULL,
  client_id     BIGINT(20) UNSIGNED DEFAULT NULL,
  title         VARCHAR(255) NOT NULL,
  period_from   DATE DEFAULT NULL,
  period_to     DATE DEFAULT NULL,
  quarter       VARCHAR(10) DEFAULT NULL,
  asana_project_gid VARCHAR(255) DEFAULT NULL,
  asana_section_gid VARCHAR(255) DEFAULT NULL,
  share_hash    VARCHAR(64) DEFAULT NULL,
  share_password VARCHAR(255) DEFAULT NULL,
  plan_status   VARCHAR(50) DEFAULT 'entwurf',
  created       DATETIME DEFAULT CURRENT_TIMESTAMP,
  state         TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id)
);
```

**state-Werte:** 1 = aktiv, 2 = archiviert/geloescht
**plan_status-Werte:** `entwurf`, `aktiv`, `einzelprojekt`, `reporting`, `abgeschlossen`, `archiviert`

### 2.2 `{prefix}_tallyr_projektplanner_rows` — Planzeilen

```sql
CREATE TABLE {prefix}_tallyr_projektplanner_rows (
  id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id         BIGINT(20) UNSIGNED NOT NULL,
  type            VARCHAR(20) NOT NULL DEFAULT 'item',
  description     TEXT,
  date_from       DATE DEFAULT NULL,
  date_to         DATE DEFAULT NULL,
  timeframe       VARCHAR(100) DEFAULT NULL,
  ist_hours       DECIMAL(10,2) DEFAULT 0,
  planned_hours   DECIMAL(10,2) DEFAULT 0,
  responsible     TEXT,
  lead_responsible VARCHAR(255) DEFAULT NULL,
  deadline        VARCHAR(100) DEFAULT NULL,
  is_done         TINYINT(1) DEFAULT 0,
  is_placeholder  TINYINT(1) DEFAULT 0,
  is_focus        TINYINT(1) DEFAULT 0,
  no_ticket       TINYINT(1) DEFAULT 0,
  actual_hours    VARCHAR(100) DEFAULT NULL,
  notes           TEXT,
  asana_gid       VARCHAR(255) DEFAULT NULL,
  asana_url       VARCHAR(500) DEFAULT NULL,
  asana_task_name VARCHAR(500) DEFAULT NULL,
  position        INT DEFAULT 0,
  created         DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
```

**type-Werte:** `item` (Aufgabe), `section` (Sektion/Ueberschrift), `note` (Notiz), `spacer` (Abstandszeile), `plan_header` (nur in Multi-Plan-Ansicht, virtuell)
**responsible:** Kommagetrennte Liste von Personen, z.B. `"Max, Lisa, Tom"`
**lead_responsible:** Einzelperson (Hauptverantwortlicher)

### 2.3 `{prefix}_tallyr_projektplanner_shares` — Plan-Freigaben

```sql
CREATE TABLE {prefix}_tallyr_projektplanner_shares (
  id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id    BIGINT(20) UNSIGNED NOT NULL,
  user_id    BIGINT(20) UNSIGNED NOT NULL,
  permission VARCHAR(20) DEFAULT 'read',
  created    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY plan_user (plan_id, user_id)
);
```

**permission-Werte:** `read` (nur lesen), `edit` (Status/Ist/Notizen bearbeitbar), `write` (alles ausser Loeschen/Teilen)

### 2.4 `{prefix}_tallyr_projektplanner_revisions` — Revisionen

```sql
CREATE TABLE {prefix}_tallyr_projektplanner_revisions (
  id       BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id  BIGINT(20) UNSIGNED NOT NULL,
  user_id  BIGINT(20) UNSIGNED NOT NULL,
  snapshot LONGTEXT,
  label    VARCHAR(255) DEFAULT NULL,
  created  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
```

**snapshot:** JSON-String des kompletten Plan-Zustands (alle Rows)

### 2.5 `{prefix}_tallyr_projektplanner_feedback` — Kunden-Feedback

```sql
CREATE TABLE {prefix}_tallyr_projektplanner_feedback (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id       BIGINT(20) UNSIGNED NOT NULL,
  row_id        BIGINT(20) UNSIGNED DEFAULT NULL,
  author_name   VARCHAR(255) DEFAULT 'Anonym',
  feedback_type VARCHAR(20) DEFAULT 'comment',
  message       TEXT,
  created       DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
```

**feedback_type-Werte:** `like`, `dislike`, `comment`
Plus Spalte `read_at` (DATETIME) fuer Gelesen-Status.

### 2.6 `{prefix}_tallyr_projektplanner_budget` — Plan-Budget-Overrides

```sql
CREATE TABLE {prefix}_tallyr_projektplanner_budget (
  id      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id BIGINT(20) UNSIGNED NOT NULL,
  year    INT NOT NULL,
  month   INT NOT NULL,
  soll_ts DECIMAL(10,2) DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY plan_year_month (plan_id, year, month)
);
```

### 2.7 `{prefix}_tallyr_client_budget` — Kunden-Budget (Haupttabelle)

```sql
CREATE TABLE {prefix}_tallyr_client_budget (
  id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id    BIGINT(20) UNSIGNED NOT NULL,
  year         INT NOT NULL,
  month        INT NOT NULL,
  soll_ts      DECIMAL(10,2) DEFAULT 0,
  ist_override DECIMAL(10,2) DEFAULT NULL,
  ist_note     VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY client_year_month (client_id, year, month)
);
```

**ist_override:** Manuelle Ist-Ueberschreibung in Stunden (NULL = automatisch berechnet)
**ist_note:** Hinweistext zur manuellen Ueberschreibung

### 2.8 `{prefix}_tallyr_person_shares` — Personen-Sharelinks

```sql
CREATE TABLE {prefix}_tallyr_person_shares (
  id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  userid      BIGINT(20) UNSIGNED NOT NULL,
  person_name VARCHAR(255) NOT NULL,
  share_hash  VARCHAR(64) NOT NULL,
  created     DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY share_hash (share_hash)
);
```

### 2.9 `{prefix}_tallyr_clients` — Kunden (relevante Spalten)

```sql
-- Relevante Spalten fuer Projektplanner:
id               BIGINT(20) UNSIGNED AUTO_INCREMENT,
userid           BIGINT(20) UNSIGNED NOT NULL,
title            VARCHAR(255),
shortdesc        VARCHAR(255),       -- Kuerzel z.B. "TKI"
hexcolor         VARCHAR(7),         -- Farbe z.B. "#FF5733"
url              VARCHAR(500),
stundensatz      DECIMAL(10,2),      -- Stundensatz
uebertrag_ts     DECIMAL(10,2) DEFAULT 0,   -- Uebertrag in TS
uebertrag_notiz  VARCHAR(500) DEFAULT NULL,
abrechnungsmodus VARCHAR(50) DEFAULT 'quarterly',
state            TINYINT(1) DEFAULT 1
```

**abrechnungsmodus-Werte:** `monthly`, `bimonthly`, `quarterly`, `halfyear`, `yearly`

---

## 3. WordPress User Meta (Einstellungen)

| Meta Key | Typ | Beschreibung |
|----------|-----|-------------|
| `tallyr_kuerzel` | string | Kuerzel/Initialen des Nutzers (z.B. "BK") |
| `tallyr_zeit` | number | Arbeitsstunden pro Tag (Standard: 8) |
| `tallyr_capacity` | number | Kapazitaet Stunden/Monat |
| `tallyr_asana_token` | string | Asana Personal Access Token |
| `tallyr_pp_tags` | JSON | Team-Tags Array: `[{name, kuerzel, capacity}]` |
| `tallyr_pp_textbausteine_project` | string | Asana-Projekt-ID fuer Textbausteine |
| `tallyr_pp_fontsize` | number | Schriftgroesse (Standard: 13) |
| `tallyr_pp_colwidths` | JSON | Spaltenbreiten-Einstellungen |

### Team-Tags Struktur (`tallyr_pp_tags`)

```json
[
  {"name": "Max Mustermann", "kuerzel": "MM", "capacity": "160"},
  {"name": "Lisa Schmidt",  "kuerzel": "LS", "capacity": "120"}
]
```

Diese Tags sind die **einzige Quelle** fuer erlaubte Personen in den Feldern "Hauptverantwortlich" und "Umsetzung". WordPress-Nutzer erscheinen NICHT in diesen Dropdowns.

---

## 4. AJAX-Endpoints (Projektplanner-spezifisch)

Alle Endpoints nutzen WordPress AJAX (`admin-ajax.php`) mit Nonce `dhAu21hdaZA721!naj!hdf`.

### 4.1 Plaene

#### `uf_pp_get_plans`

Laedt alle zugaenglichen Plaene (eigene + freigegebene).

**Request:** `action=uf_pp_get_plans`
**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "userid": 5,
      "client_id": 12,
      "title": "Q2 2025",
      "period_from": "2025-04-01",
      "period_to": "2025-06-30",
      "quarter": "Q2/25",
      "plan_status": "aktiv",
      "share_hash": "abc123...",
      "state": 1,
      "client_title": "Kunde AG",
      "client_short": "KAG",
      "client_color": "#FF5733",
      "permission": "owner"
    }
  ]
}
```

#### `uf_pp_save_plan`

Erstellt oder aktualisiert einen Plan.

**Request:**

```
action=uf_pp_save_plan
plan_id=0 (0 = neu)
title=Q2 2025
client_id=12
period_from=2025-04-01
period_to=2025-06-30
plan_status=aktiv
asana_project_gid=12345 (optional)
asana_section_gid=67890 (optional)
```

**Response:** `{success: true, data: {id: 1}}`

#### `uf_pp_delete_plan`

**Request:** `action=uf_pp_delete_plan&plan_id=1`
Setzt `state=2` (Soft Delete).

#### `uf_pp_duplicate_plan`

Dupliziert Plan mit allen Rows (inkl. lead_responsible, no_ticket). Asana-Verknuepfungen werden NICHT kopiert.

**Request:** `action=uf_pp_duplicate_plan&plan_id=1`
**Response:** `{success: true, data: {id: 2}}`

### 4.2 Zeilen (Rows)

#### `uf_pp_get_rows`

**Request:** `action=uf_pp_get_rows&plan_id=1`
**Response:**

```json
{
  "success": true,
  "data": {
    "rows": [
      {
        "id": 101,
        "plan_id": 1,
        "type": "section",
        "description": "Design Phase",
        "position": 0,
        "planned_hours": "0.00",
        "ist_hours": "0.00",
        "responsible": "",
        "lead_responsible": "",
        "deadline": "",
        "timeframe": "",
        "is_done": 0,
        "is_placeholder": 0,
        "is_focus": 0,
        "no_ticket": 0,
        "actual_hours": "",
        "notes": "",
        "asana_gid": "",
        "asana_url": "",
        "asana_task_name": ""
      }
    ],
    "plan": {},
    "shares": [
      {"user_id": 3, "permission": "edit", "display_name": "Max"}
    ],
    "share_hash": "abc123...",
    "share_password": "",
    "feedback": [
      {
        "id": 1,
        "row_id": 101,
        "author_name": "Kunde",
        "feedback_type": "like",
        "message": "",
        "created": "2025-01-15 14:30:00"
      }
    ]
  }
}
```

#### `uf_pp_save_row`

Auto-Save mit 600ms Debounce pro Zeile.

**Request:**

```
action=uf_pp_save_row
plan_id=1
row_id=101 (0 = neue Zeile)
type=item
description=Logo Design
planned_hours=8
ist_hours=5.5
responsible=Max, Lisa
lead_responsible=Max
deadline=15.03.2025
timeframe=10.-15.03.
actual_hours=
notes=Entwurf fertig
is_done=0
is_placeholder=0
is_focus=0
no_ticket=0
position=3
```

**Response:** `{success: true, data: {id: 101}}`

#### `uf_pp_delete_row`

**Request:** `action=uf_pp_delete_row&row_id=101`

#### `uf_pp_reorder_rows`

**Request:**

```
action=uf_pp_reorder_rows
plan_id=1
order=[101,102,103,104]  // Array von Row-IDs in neuer Reihenfolge
```

#### `uf_pp_move_row`

Verschiebt eine Zeile in einen anderen Plan (Cross-Plan Drag & Drop).

**Request:**

```
action=uf_pp_move_row
row_id=101
target_plan_id=2
position=5
```

### 4.3 Freigaben (Shares)

Freigaben werden ueber `uf_pp_save_plan` mitgespeichert (shares-Array im Request) oder ueber die shares-Tabelle direkt.

#### `uf_get_wp_users_for_share`

Separater Endpoint um WordPress-Nutzer fuer das Freigabe-Dropdown zu laden (getrennt von Team-Tags).

**Request:** `action=uf_get_wp_users_for_share`
**Response:**

```json
{
  "success": true,
  "data": [
    {"id": 2, "display_name": "Max Mustermann"}
  ]
}
```

### 4.4 Feedback

#### `uf_pp_submit_feedback` (auch nopriv)

**Request:**

```
action=uf_pp_submit_feedback
share_hash=abc123
row_id=101
author_name=Kunde
feedback_type=like|dislike|comment
message=Sieht gut aus!
```

#### `uf_pp_delete_feedback_public` (auch nopriv)

**Request:** `action=uf_pp_delete_feedback_public&feedback_id=5&share_hash=abc123`

#### `uf_pp_edit_feedback_public` (auch nopriv)

**Request:** `action=uf_pp_edit_feedback_public&feedback_id=5&share_hash=abc123&message=Neuer Text`

#### `uf_pp_mark_feedback_read`

**Request:** `action=uf_pp_mark_feedback_read&feedback_id=5`

#### `uf_pp_get_unread_feedback_count`

**Request:** `action=uf_pp_get_unread_feedback_count`
**Response:** `{success: true, data: {count: 3}}`

#### `uf_pp_get_feedback`

**Request:** `action=uf_pp_get_feedback&plan_id=1`

### 4.5 Dashboard

#### `uf_pp_get_dashboard_stats`

**Request:**

```
action=uf_pp_get_dashboard_stats
date_from=2025-01-01
date_to=2025-03-31
status=aktiv (optional)
client_id=12 (optional)
```

**Response:**

```json
{
  "success": true,
  "data": {
    "totals": {
      "soll": 1200,
      "ist": 800,
      "done": 450,
      "open": 750,
      "total": 1200
    },
    "by_person": {
      "Max": {"soll": 400, "ist": 300, "done": 150, "open": 250},
      "Lisa": {"soll": 300, "ist": 200, "done": 100, "open": 200}
    },
    "by_plan": {
      "1": {
        "title": "Q2 KAG",
        "color": "#FF5733",
        "soll": 600,
        "ist": 400,
        "done": 200,
        "open": 400,
        "total": 600
      }
    },
    "forecast": {
      "2025-01": {
        "Max": {"soll": 130, "ist": 100},
        "Lisa": {"soll": 100, "ist": 70}
      },
      "2025-02": {
        "Max": {"soll": 140, "ist": 110}
      }
    },
    "done_tasks": [
      {
        "description": "Logo Design",
        "responsible": "Max",
        "soll": 8,
        "ist": 6,
        "plan": "Q2 KAG",
        "color": "#FF5733",
        "deadline": "15.03."
      }
    ],
    "person_tasks": {
      "Max": [
        {
          "description": "Logo Design",
          "soll": 8,
          "ist": 6,
          "role": "lead",
          "plan_title": "Q2 KAG",
          "is_done": 1
        }
      ]
    },
    "capacity": {
      "Max": 160,
      "Lisa": 120
    }
  }
}
```

**Logik fuer Stunden-Attribution:**

- `lead_responsible` bekommt die vollen Stunden
- Wenn kein Lead: Stunden werden gleichmaessig auf `responsible` aufgeteilt
- `no_ticket`-Aufgaben werden bei Dashboard-Totals NICHT gezaehlt

### 4.6 Budget/Soll-Ist

#### `uf_pp_get_budget`

**Request:**

```
action=uf_pp_get_budget
client_id=12
year=2025
```

**Response:**

```json
{
  "success": true,
  "data": {
    "client": {
      "id": 12,
      "title": "Kunde AG",
      "uebertrag_ts": 1.5,
      "uebertrag_notiz": "Aus Q4 2024",
      "abrechnungsmodus": "quarterly"
    },
    "months": [
      {
        "month": 1,
        "soll_ts": 5.0,
        "soll_h": 40.0,
        "ist_h": 35.5,
        "ist_calc": 35.5,
        "ist_manual": null,
        "ist_note": null,
        "diff_h": -4.5
      }
    ],
    "total_all": {
      "soll_ts": 60.0,
      "soll_h": 480.0,
      "ist_h": 350.0,
      "diff_h": -130.0
    },
    "hours_per_ts": 8,
    "years": [2024, 2025, 2026],
    "year": 2025,
    "client_id": 12
  }
}
```

**Ist-Berechnung:** Summiert `ist_hours` aus ALLEN Planzeilen des Kunden (state 1 UND 2), proportional auf Monate verteilt basierend auf Plan-Zeitraum.

**Diff-Berechnung:** `Ist - Soll` — positiv = Ueberhang (gruen), negativ = Rueckstand (rot)

**Kundenfreundliche TS-Rundung:**

```
Math.floor(hours / 8) = volle Tage
Rest < 4h -> abrunden (Kunde bekommt geschenkt)
Rest >= 4h -> +0.5 TS
Beispiel: 27.5h = 3 TS (3x8=24, Rest 3.5h < 4h -> abrunden)
Beispiel: 28h = 3.5 TS (3x8=24, Rest 4h >= 4h -> +0.5)
```

#### `uf_pp_save_client_budget`

**Request:**

```
action=uf_pp_save_client_budget
client_id=12
year=2025
month=3
soll_ts=5.0
```

#### `uf_pp_save_client_budget_batch`

**Request:**

```
action=uf_pp_save_client_budget_batch
client_id=12
year=2025
entries=[{"month":1,"soll_ts":5},{"month":2,"soll_ts":5},{"month":3,"soll_ts":5}]
```

#### `uf_pp_save_uebertrag`

**Request:**

```
action=uf_pp_save_uebertrag
client_id=12
uebertrag_ts=1.5
uebertrag_notiz=Aus Q4 2024
abrechnungsmodus=quarterly
```

#### `uf_pp_save_ist_override`

**Request:**

```
action=uf_pp_save_ist_override
client_id=12
year=2025
month=3
ist_override=42.5
ist_note=Manuell korrigiert wegen Sonderprojekt
```

### 4.7 Personen-Share

#### `uf_pp_generate_person_share`

**Request:**

```
action=uf_pp_generate_person_share
person_name=Max Mustermann
```

**Response:** `{success: true, data: {share_url: "https://example.com/personen-aufgaben/?id=xyz123"}}`

#### `uf_pp_get_person_tasks` (auch nopriv)

**Request:** `action=uf_pp_get_person_tasks&share_hash=xyz123`
**Response:** Alle Aufgaben der Person aus allen aktiven Plaenen, gruppiert nach Kunde.

### 4.8 Export

#### `uf_pp_export_report`

Server-seitiger Excel-Export via PhpSpreadsheet.

**Request:** `action=uf_pp_export_report&plan_id=1`
**Response:** XLSX-Datei als Download. Enthaelt lead_responsible als erstes Suffix, keine Duplikate.

### 4.9 Asana-Integration

#### `uf_create_asana_task`

**Request:**

```
action=uf_create_asana_task
name=Aufgabenname
project_gid=12345
section_gid=67890 (optional)
notes=Beschreibung
```

#### `uf_search_asana_tasks`

**Request:** `action=uf_search_asana_tasks&query=Logo&project_gid=12345`

#### `uf_get_asana_task_detail`

**Request:** `action=uf_get_asana_task_detail&task_gid=98765`

---

## 5. Frontend-Architektur

### 5.1 Globale JavaScript-Variablen

```javascript
var ppCurrentPlanId;   // number|null — aktuell gewaehlter Plan
var ppCurrentPlan;     // object|null — vollstaendiges Plan-Objekt
var ppPlans;           // array — alle geladenen Plaene
var ppRows;            // array — Zeilen des aktuellen Plans (flat, sortiert nach position)
var ppSaveTimers;      // object — Debounce-Timer pro Row-ID
var ppFeedbackByRow;   // object — Feedback indexiert nach Row-ID
var ppActiveFilters;   // array — aktive Status-Filter ["open", "done", "placeholder", ...]
var ppColFilters;      // object — Spaltenfilter {responsible: "Max"}
var ppBudgetCycle;     // string — "monthly"|"bimonthly"|"quarterly"|"halfyear"|"yearly"
var ppHoursPerTs;      // number — 8 (konstant)
var ppUsersData;       // array — Team-Tags [{name, kuerzel, capacity}]
var ppAllClients;      // array — Kunden [{id, title}]
```

### 5.2 ppRows-Objekt-Struktur

```javascript
{
  id: 101,
  plan_id: 1,
  _planId: 1,           // fuer Multi-Plan Zuordnung
  type: "item",          // "item"|"section"|"note"|"spacer"
  description: "Logo Design",
  responsible: "Max, Lisa",
  lead_responsible: "Max",
  planned_hours: 8,
  ist_hours: 5.5,
  actual_hours: "",
  deadline: "15.03.2025",
  timeframe: "10.-15.03.",
  notes: "Entwurf fertig",
  is_done: 0,
  is_placeholder: 0,
  is_focus: 0,
  no_ticket: 0,
  asana_gid: "123456",
  asana_url: "https://app.asana.com/0/...",
  asana_task_name: "KAG Logo Design",
  position: 3
}
```

### 5.3 Tabellen-Spalten (13 Spalten)

| # | Spalte | Feld | Editierbar | Breite |
|---|--------|------|-----------|--------|
| 1 | Drag Handle | — | — | 20px |
| 2 | Done | is_done | Checkbox | 30px |
| 3 | Beschreibung | description | contenteditable | flex |
| 4 | Zeitraum | timeframe | contenteditable | 90px |
| 5 | Ist (h) | ist_hours | contenteditable | 50px |
| 6 | Soll (h) | planned_hours | contenteditable | 50px |
| 7 | Hauptverantw. | lead_responsible | Autocomplete | 90px |
| 8 | Umsetzung | responsible | Multi-Person Tags | 120px |
| 9 | Umgesetzt bis | deadline | contenteditable | 90px |
| 10 | Aufwand | actual_hours | contenteditable | 60px |
| 11 | Bemerkungen | notes | contenteditable | flex |
| 12 | Asana | asana_url | 3 Buttons | 80px |
| 13 | Aktionen | — | Edit/Delete | 50px |

### 5.4 Filter-System

**Status-Filter (ppActiveFilters):**

- `all` — Alle anzeigen
- `open` — Nur offene (is_done=0, is_placeholder=0)
- `done` — Nur erledigte (is_done=1)
- `placeholder` — Nur Platzhalter (is_placeholder=1)
- `no-asana` — Ohne Asana-Verknuepfung
- `no-ticket` — "Kein Ticket notwendig" (no_ticket=1)
- `focus` — Fokus-Aufgaben (is_focus=1)

**Logik:** AND-Verknuepfung aller aktiven Filter.

**Personen-Filter:**

- `#pp-filter-lead` — Dropdown fuer Hauptverantwortlichen
- `#pp-filter-responsible` — Dropdown fuer Umsetzung
- Exact Match auf Komma-getrennte Liste

**Spalten-Filter:**

- Per Klick auf Spaltenheader-Icon
- Filtert nach dem exakten Wert in der Spalte

**Aktive Filter-Anzeige:**

- Banner zeigt Anzahl der gefilterten Aufgaben
- Zeigt Summe der gefilterten Soll/Ist-Stunden

### 5.5 Drag & Drop

- HTML5 Drag & Drop API auf `<tr draggable="true">`
- Half-Detection: `e.clientY < rect.top + rect.height / 2` — darüber/darunter
- CSS-Klassen: `pp-drag-above` / `pp-drag-below` fuer visuelle Indikatoren
- Cross-Plan Move: Aendert `data-plan` Attribut, ruft `uf_pp_move_row` auf
- Nach Drop: `ppRows.sort()` (Single-Plan) oder DOM-Rebuild (Multi-Plan)
- Reorder wird via `uf_pp_reorder_rows` persistiert

### 5.6 Berechtigungssystem (CSS-basiert)

**Body-Klassen:**

- `pp-perm-read` — Nur Lesen
- `pp-perm-edit` — Eingeschraenkt bearbeitbar

**Read-Permission versteckt:** Edit Plan, Delete Plan, Share, Duplicate, Add Rows, alle contenteditable Felder

**Edit-Permission erlaubt:** `is_done`, `ist_hours`, `actual_hours`, `notes`
**Edit-Permission versteckt:** Edit Plan, Delete, Share, Duplicate, Add Rows, description, responsible, Asana-Buttons

**Write/Owner:** Alles sichtbar und bearbeitbar

### 5.7 Auto-Save

- 600ms Debounce pro Zeile
- Bei jeder Aenderung in contenteditable: Timer zuruecksetzen, nach 600ms `uf_pp_save_row` aufrufen
- `ppSaveTimers[rowId]` speichert den Timer-Handle

---

## 6. Seitenvorlagen

### 6.1 `page-projektplan.php` — Oeffentlicher Share-Link

**URL:** `/projektplan/?id={share_hash}`

**Validierung:**

1. Prueft `share_hash` gegen `tallyr_projektplanner.share_hash`
2. Prueft `state = 1`
3. Optional: Passwort-Check via Session (`pp_access_{plan_id}`)

**Darstellung:**

- Kompakte Tabelle: Beschreibung | Aufwand (h) | Umsetzung | Status-Icons
- Sektionen als Ueberschriften, leere Sektionen ausgeblendet
- Personen-Suffix: Lead zuerst, dann Umsetzung (ohne Duplikate), Format: `(XX.-XX.02., TKI) (THO)`
- Feedback-System: Like/Dislike/Kommentar pro Zeile
- Name wird in `localStorage` gespeichert

### 6.2 `page-projektplan-person.php` — Personen-Aufgabenliste

**URL:** `/personen-aufgaben/?id={share_hash}`

**Darstellung:**

- KPIs: Total Soll (h), Total Ist (h), Erledigt, Offen, Gesamt
- Gruppiert nach Kunde mit farbiger Badge
- Tabelle pro Kunde: Plan | Aufgabe | Soll | Ist | Zeitraum | Deadline | Status | HV
- "HV" Badge bei Aufgaben wo Person Hauptverantwortlich ist
- Summenzeile pro Kunde

---

## 7. Plan-Stats Bar (im Planeditor)

Zeigt 5 Werte wenn ein Plan geladen ist:

| Stat | Quelle | Farbe |
|------|--------|-------|
| **Ist** | Summe `ist_hours` | Blau |
| **Geplant** | Summe `planned_hours` | Lila |
| **Budget (Soll)** | Client Budget fuer Plan-Zeitraum | Ampel (gruen/gelb/rot) |
| **Gap** | Soll - Geplant | Orange (nicht verplant) / Rot (ueberplant) |
| **Erledigt** | Count done/total | Grau |

Budget-Soll wird NUR fuer den Zeitraum des Plans geladen (nicht kumuliert).

---

## 8. Dashboard (6 Tabs)

### Tab 1: KPIs / Auslastung

5 Karten: Soll | Ist | Differenz | Erledigt | Fortschritt

### Tab 2: Personen

- Tabelle: Person | Soll | Ist | Kapazitaet | Verfuegbar | Auslastung% | Aufgaben
- Bei Personen-Filter: Detail-Liste aller Aufgaben mit Rollen (Lead/Resp)
- Duplikaterkennung: Warnung bei aehnlichen Namen

### Tab 3: Plaene

- Tabelle: Plan | Soll | Ist | Diff | Fortschritt% | Aufgaben

### Tab 4: Forecast

- Heatmap: Person x Monate
- Zeigt: Soll-Stunden pro Person pro Monat
- Kapazitaetszeile: Team-Kapazitaet pro Monat
- Verfuegbar-Zeile: Kapazitaet - Soll (gruen/rot)

### Tab 5: Erledigte Aufgaben

- Tabelle: Aufgabe | Plan | Verantwortlich | Soll | Ist | Diff

### Tab 6: Soll/Ist (Budget)

- Budget-Tabelle pro Kunde
- Jahreswechsel, Billing-Cycle, Uebertrag
- Nicht editierbar im Dashboard

---

## 9. Daten-Export/Import fuer 1:1 Transfer

### 9.1 Vollstaendiger Datenexport (SQL)

Fuer einen 1:1 Transfer muessen folgende Tabellen exportiert werden:

```sql
-- 1. Plaene
SELECT * FROM {prefix}_tallyr_projektplanner WHERE userid = {user_id};

-- 2. Plan-Zeilen
SELECT r.* FROM {prefix}_tallyr_projektplanner_rows r
JOIN {prefix}_tallyr_projektplanner p ON p.id = r.plan_id
WHERE p.userid = {user_id};

-- 3. Freigaben
SELECT s.* FROM {prefix}_tallyr_projektplanner_shares s
JOIN {prefix}_tallyr_projektplanner p ON p.id = s.plan_id
WHERE p.userid = {user_id};

-- 4. Revisionen
SELECT rev.* FROM {prefix}_tallyr_projektplanner_revisions rev
JOIN {prefix}_tallyr_projektplanner p ON p.id = rev.plan_id
WHERE p.userid = {user_id};

-- 5. Feedback
SELECT f.* FROM {prefix}_tallyr_projektplanner_feedback f
JOIN {prefix}_tallyr_projektplanner p ON p.id = f.plan_id
WHERE p.userid = {user_id};

-- 6. Plan-Budget
SELECT b.* FROM {prefix}_tallyr_projektplanner_budget b
JOIN {prefix}_tallyr_projektplanner p ON p.id = b.plan_id
WHERE p.userid = {user_id};

-- 7. Kunden-Budget
SELECT cb.* FROM {prefix}_tallyr_client_budget cb
JOIN {prefix}_tallyr_clients c ON c.id = cb.client_id
WHERE c.userid = {user_id};

-- 8. Personen-Shares
SELECT * FROM {prefix}_tallyr_person_shares WHERE userid = {user_id};

-- 9. Kunden (mit Uebertrag-Feldern)
SELECT id, title, shortdesc, hexcolor, url, stundensatz,
       uebertrag_ts, uebertrag_notiz, abrechnungsmodus
FROM {prefix}_tallyr_clients WHERE userid = {user_id};
```

### 9.2 JSON-Exportformat

Fuer API-basierten Transfer:

```json
{
  "export_version": "1.0",
  "exported_at": "2025-06-01T12:00:00Z",
  "hours_per_ts": 8,
  "team_tags": [
    {"name": "Max Mustermann", "kuerzel": "MM", "capacity": "160"}
  ],
  "clients": [
    {
      "id": 12,
      "title": "Kunde AG",
      "shortdesc": "KAG",
      "hexcolor": "#FF5733",
      "url": "https://kunde.de",
      "stundensatz": 120.00,
      "uebertrag_ts": 1.5,
      "uebertrag_notiz": "Aus Q4 2024",
      "abrechnungsmodus": "quarterly"
    }
  ],
  "plans": [
    {
      "id": 1,
      "client_id": 12,
      "title": "Q2 2025",
      "period_from": "2025-04-01",
      "period_to": "2025-06-30",
      "quarter": "Q2/25",
      "plan_status": "aktiv",
      "share_hash": "abc123",
      "state": 1,
      "rows": [
        {
          "id": 101,
          "type": "section",
          "description": "Design Phase",
          "position": 0,
          "planned_hours": 0,
          "ist_hours": 0,
          "responsible": "",
          "lead_responsible": "",
          "deadline": "",
          "timeframe": "",
          "is_done": 0,
          "is_placeholder": 0,
          "is_focus": 0,
          "no_ticket": 0,
          "actual_hours": "",
          "notes": "",
          "asana_gid": "",
          "asana_url": "",
          "asana_task_name": ""
        }
      ],
      "shares": [
        {"user_id": 3, "permission": "edit"}
      ],
      "feedback": [
        {
          "row_id": 101,
          "author_name": "Kunde",
          "feedback_type": "like",
          "message": "",
          "created": "2025-01-15 14:30:00"
        }
      ],
      "revisions": [
        {
          "label": "Vor Aenderung",
          "snapshot": "{...}",
          "created": "2025-01-10 10:00:00"
        }
      ],
      "budget_overrides": [
        {"year": 2025, "month": 4, "soll_ts": 5.0}
      ]
    }
  ],
  "client_budgets": [
    {
      "client_id": 12,
      "year": 2025,
      "month": 1,
      "soll_ts": 5.0,
      "ist_override": null,
      "ist_note": null
    }
  ],
  "person_shares": [
    {"person_name": "Max Mustermann", "share_hash": "xyz123"}
  ]
}
```

### 9.3 Import-Strategie

Fuer den Import in ein neues System:

1. **Kunden zuerst** — `clients` importieren, alte->neue ID-Mapping erstellen
2. **Plaene** — `plans` importieren mit neuen `client_id`s, ID-Mapping erstellen
3. **Zeilen** — `rows` importieren mit neuen `plan_id`s
4. **Budget** — `client_budgets` mit neuen `client_id`s, `budget_overrides` mit neuen `plan_id`s
5. **Shares** — `shares` mit neuen `plan_id`s (User-IDs muessen im Zielsystem existieren)
6. **Feedback/Revisionen** — mit neuen `plan_id`s und `row_id`s
7. **Personen-Shares** — mit neuem `userid`
8. **Team-Tags** — in User Meta `tallyr_pp_tags` speichern

**Wichtige Constraints:**

- `client_id` in Plans muss auf existierenden Kunden zeigen
- `plan_id` in Rows/Shares/Feedback muss auf existierenden Plan zeigen
- `row_id` in Feedback muss auf existierende Row zeigen
- `share_hash` muss unique sein (bei Kollision: neu generieren)
- Position-Werte muessen innerhalb eines Plans unique sein

---

## 10. Technische Besonderheiten

| Thema | Detail |
|-------|--------|
| **Nonce** | `dhAu21hdaZA721!naj!hdf` fuer alle AJAX-Calls |
| **Auto-Save** | 600ms Debounce per Row via `setTimeout` |
| **Sticky Header** | JS-basiert (CSS `position: sticky` funktioniert nicht wegen `overflow: clip` im Parent Theme) |
| **Excel Import** | Client-seitig via SheetJS (xlsx-js-style) |
| **Excel Export** | Server-seitig via PhpSpreadsheet (aus Parent Theme `/vendor/`) |
| **TS-Rundung** | Kundenfreundlich: `floor(h/8)`, Rest < 4h = abrunden, Rest >= 4h = +0.5 |
| **Ist-Berechnung** | Aus ALLEN Kunden-Plaenen (state 1 + 2), proportional auf Monate verteilt |
| **Zukuenftige Perioden** | Werden in Soll/Ist nicht berechnet (Jahr/Monat-Vergleich mit aktuellem Datum) |
| **Soft Delete** | Plaene/Kunden: `state=2` statt echtem DELETE |
| **Oeffentliche Endpoints** | Feedback + Personen-Tasks: `wp_ajax_nopriv_` (ohne Login) |
| **Eingeschraenkte Endpoints** | Alle anderen: nur `wp_ajax_` (Login erforderlich) |
| **HTML-Entity-Decoding** | Via temporaeres `<textarea>` Element im Frontend |
| **`wp_unslash()`** | VOR Sanitization anwenden, um Backslash-Akkumulation zu verhindern |

---

## 11. Dateistruktur

```
kreation-tallyr/
├── functions.php                    # Enqueues, AJAX includes, localize script
├── blocks/
│   ├── tallyrprojektplanner.php     # ACF Block: Hauptinterface mit Dashboard
│   └── tallyreinstellungen.php      # ACF Block: Einstellungen + Team-Tags
├── inc/
│   ├── ajaxuser.php                 # Alle AJAX-Endpoints (nur eingeloggt)
│   ├── ajax-public-feedback.php     # Oeffentliche Feedback-Endpoints (nopriv)
│   └── asana-api.php                # Asana API Helper
├── js/users/
│   └── userfunctions.js             # Komplettes Frontend-JS (~9300 Zeilen)
├── sass/generals/
│   └── users.scss                   # Alle Styles
├── page-projektplan.php             # Oeffentliche Plan-Ansicht (Share)
└── page-projektplan-person.php      # Oeffentliche Personen-Aufgabenliste
```

### Abhaengigkeiten

- **jQuery** — Basis fuer alle DOM-Operationen und AJAX
- **SweetAlert2 (Swal)** — Modale Dialoge
- **Toastr** — Toast-Benachrichtigungen
- **SheetJS (xlsx-js-style)** — Client-seitiger Excel Import/Export
- **PhpSpreadsheet** — Server-seitiger Excel Export (Parent Theme `/vendor/`)
- **WordPress AJAX API** — `admin-ajax.php` mit `wp_ajax_` / `wp_ajax_nopriv_` Hooks
- **ACF (Advanced Custom Fields)** — Block-Registrierung
