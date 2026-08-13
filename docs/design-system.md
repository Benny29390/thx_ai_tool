# Design-System — KI-Tool

**Stand:** 2026-05-22
**Single Source of Truth.** Live-Showcase unter [/admin/settings?tab=design](http://ai.thoxan-dev.de/admin/settings?tab=design).

## Grundprinzipien

1. **Konsistenz vor Hübsch.** Jede neue Seite nutzt vorhandene Tokens + Klassen. Inline-Styles nur, wenn keine Komponente passt — dann lieber neue Komponente bauen.
2. **Dicht, aber atmend.** Default-Dichte ist „comfortable" (Linear-Style). Compact = Notion, Spacious = Stripe. Umschaltbar im User-Setting.
3. **Klassen statt Inline.** `class="thx-btn thx-btn-primary"` statt `style="background:..."`. Inline-Styles erschweren globale Anpassungen.
4. **Density-Tokens nutzen.** Wenn eine Komponente Padding/Spacing braucht, das mit Dichte skaliert, nutzt sie `var(--d-card-pad)` etc., nicht `var(--space-4)`.

## Dateien (Ladereihenfolge in [layouts/main.php](views/layouts/main.php))

```
1. style.css           — Basis (legacy, schrittweise abbauen)
2. thx-tokens.css      — Farben, Schriftgrößen, Spacing, Density-Profile
3. thx-components.css  — wiederverwendbare .thx-*-Komponenten
4. lam.css             — übergangsweise LAM-spezifisch, wird zu .thx-* migriert
```

## Density-System

Drei Profile, umschaltbar via `<html data-density="...">`. Persistiert im `localStorage.thx_density`. Default = `comfortable`.

### Master-Skala (`--d-scale`)

Ein einzelner Faktor, der alle „freien" Schriftgrößen gemeinsam skaliert (Card-Titel, Stat-Values, Body-Tags, Section-Header, alles was nicht eigenen Token hat). Compact = 0.85, Comfortable = 1.0, Spacious = 1.15. Im Design-Tab als erster Stepper sichtbar.

Funktionsweise: `--d-fs-xs := calc(var(--fs-xs) * var(--d-scale))` usw. — Komponenten nutzen `var(--d-fs-*)` statt `var(--fs-*)`, so wirkt der Master-Hebel automatisch überall.

### Profil-Defaults

| Profil       | Scale | Card-Pad | Row-Pad   | Page-Title | Control-H | Empfohlen für         |
|--------------|-------|----------|-----------|------------|-----------|----------------------|
| `compact`    | 0.75  | 0.375rem | 2px / 7px | 1.125 rem  | 1.5 rem   | Power-User, viele Daten |
| `comfortable`| 0.85  | 0.5 rem  | 5px / 10px| 1.25 rem   | 1.625 rem | **Default**, alle Module |
| `spacious`   | 1.1   | 1 rem    | 10px / 17px| 1.625 rem | 2 rem     | Präsentationen, Demo  |

User stellt es unter `/admin/settings?tab=design` ein. Wirkt sofort auf alle Komponenten, die `--d-*`-Tokens nutzen.

### Comfortable (Default) — finaler Stand 22.05.2026

Festgelegte Token-Konfiguration aus dem Design-Tab-Tuning:

| Token                  | Wert       | Stufe | Engere Variante | Weitere Variante |
|------------------------|------------|-------|-----------------|------------------|
| `--d-scale`            | `0.85`     | S     | `0.75` (XS)     | `1.0` (M)        |
| `--d-page-title-fs`    | `1.25rem`  | L     | `1.125rem` (M)  | `1.5rem` (XL)    |
| `--d-page-sub-fs`      | `0.875rem` | L     | `0.8125rem` (M) | `1rem` (XL)      |
| `--d-control-h`        | `1.625rem` | S     | `1.5rem` (XS)   | `1.75rem` (M)    |
| `--d-control-pad-x`    | `0.5rem`   | S     | `0.375rem` (XS) | `0.625rem` (M)   |
| `--d-control-fs`       | `0.6875rem`| XS    | —               | `0.75rem` (S)    |
| `--d-card-pad`         | `0.5rem`   | S     | `0.375rem` (XS) | `0.625rem` (M)   |
| `--d-row-pad-y`        | `0.25rem`  | S     | `0.125rem` (XS) | `0.375rem` (M)   |
| `--d-row-pad-x`        | `0.5rem`   | S     | `0.375rem` (XS) | `0.625rem` (M)   |
| `--d-tbl-pad-y`        | `0.25rem`  | S     | `0.125rem` (XS) | `0.375rem` (M)   |
| `--d-page-header-mb`   | `0.5rem`   | S     | `0.375rem` (XS) | `0.75rem` (M)    |
| `--d-section-gap`      | `0.5rem`   | S     | `0.375rem` (XS) | `0.75rem` (M)    |
| `--d-card-radius`      | `0`        | XS    | —               | `3px` (S)        |
| `--d-control-radius`   | `5px`      | M     | `3px` (S)       | `8px` (L)        |

## Farben

### Marken-Palette (Thoxan-Blau)
- `--thoxan-50` bis `--thoxan-950` — alle 11 Stufen verfügbar
- Primary-Button: `--thoxan-600`, Hover `--thoxan-700`
- Soft-Bg: `--thoxan-50`, Active-Bg: `--thoxan-100`

### Neutral (Slate)
- `--slate-50` bis `--slate-900`
- Body-Text: `--slate-900`, Sekundär: `--slate-600`, Muted: `--slate-400`
- Borders: `--slate-200` (default), `--slate-100` (soft)
- Hintergründe: `--slate-50` (subtle), `--slate-100` (alternierend)

### Semantische Farben
- **Erfolg**: `--emerald-600` (Action), `--emerald-100` (Bg), `--emerald-800` (Text auf Bg)
- **Warnung**: `--amber-500` (Action), `--amber-100` (Bg), `--amber-800` (Text)
- **Gefahr**: `--rose-600` (Action), `--rose-100` (Bg), `--rose-800` (Text)
- **Info**: `--indigo-600` / `--indigo-100`

**Regel:** Nutze immer die Token-Variable, nie Hex direkt. Wenn ein Farbton fehlt, ergänze ihn in `thx-tokens.css`.

## Typografie

Frutiger LT Std (Roman 400 + Bold 700). Basis: `html { font-size: 120% }` — alle rem-Werte skalieren automatisch.

| Token        | rem   | Pixel @120% | Verwendung               |
|--------------|-------|-------------|--------------------------|
| `--fs-xs`    | 0.75  | 14.4 px     | Labels, Micro-Copy       |
| `--fs-sm`    | 0.875 | 16.8 px     | Body-Default             |
| `--fs-base`  | 1.0   | 19.2 px     | Sub-Heading, Buttons-Lg  |
| `--fs-lg`    | 1.125 | 21.6 px     | H3                       |
| `--fs-xl`    | 1.25  | 24 px       | H2                       |
| `--fs-2xl`   | 1.5   | 28.8 px     | H1 (comfortable default) |
| `--fs-3xl`   | 1.875 | 36 px       | Hero-Numbers (selten)    |

### Hierarchie
- **H1 / Page-Title** (`.thx-page-title`): `--d-page-title-fs`, weight 700, slate-900
- **H2 / Card-Heading** (`.thx-card-title`): `--fs-lg`, weight 700
- **H3 / Section**: `--fs-base`, weight 600
- **Body**: `--fs-sm`, weight 400, slate-900
- **Sub / Meta**: `--fs-xs`, weight 400, slate-500
- **Caps-Label** (Section-Header): `--fs-xs`, weight 700, slate-500, `text-transform: uppercase`, `letter-spacing: 0.06em`

## Spacing-Skala

Strikt 4-Pixel-Grid, in rem:

| Token         | rem   | Pixel @120% | Typisch für              |
|---------------|-------|-------------|--------------------------|
| `--space-1`   | 0.25  | 4.8 px      | Mikro (Icon-Gap)         |
| `--space-2`   | 0.5   | 9.6 px      | Item-Gap                 |
| `--space-3`   | 0.75  | 14.4 px     | Element-Gap              |
| `--space-4`   | 1.0   | 19.2 px     | Section-Gap              |
| `--space-5`   | 1.25  | 24 px       | Card-Pad (spacious)      |
| `--space-6`   | 1.5   | 28.8 px     | Page-Section             |
| `--space-8`   | 2.0   | 38.4 px     | Major-Section            |
| `--space-10`  | 2.5   | 48 px       | Hero                     |

**Regel:** Keine willkürlichen Pixelwerte. Immer `--space-N` oder `--d-*` (für density-abhängig).

## Layout-Konstanten

| Token                 | Wert       | Bedeutung           |
|-----------------------|------------|---------------------|
| `--sidebar-w`         | 260 px     | Sidebar-Breite      |
| `--sidebar-w-collapsed` | 60 px    | Sidebar kollabiert  |
| `--topbar-h`          | 44 px      | Top-Bar-Höhe        |
| `--container-max`     | 1920 px    | Max-Content-Breite  |

## Border-Radius

| Token         | Wert  | Verwendung                |
|---------------|-------|---------------------------|
| `--radius-sm` | 4 px  | Chips, Tags               |
| `--radius-md` | 6 px  | Buttons, Inputs (default) |
| `--radius-lg` | 8 px  | Cards, Modals             |
| `--radius-full` | 9999 | Pills, Avatare           |

## Schatten

| Token         | Verwendung                            |
|---------------|--------------------------------------|
| `--shadow-sm` | Subtle: Liste-Items, Cards (default) |
| `--shadow-md` | Lift: Hover-Cards, Popovers          |
| `--shadow-lg` | Modal, Drawer                        |

## Komponenten

### Page-Header (`.thx-page-header`)
```html
<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Titel</h1>
        <div class="thx-page-subtitle">Optionaler Untertitel</div>
    </div>
    <div class="thx-page-actions">
        <button class="thx-btn thx-btn-secondary">…</button>
        <button class="thx-btn thx-btn-primary">+ Neu</button>
    </div>
</div>
```
**Pflicht:** Jede Modul-Hauptseite startet mit diesem Header. Keine Hero-Banner.

### Buttons (`.thx-btn` + Variante)
| Klasse            | Wann nutzen                            |
|-------------------|----------------------------------------|
| `.thx-btn-primary` | Hauptaktion pro Sicht (1× pro Seite)  |
| `.thx-btn-secondary` | Sekundär (mehrere ok)                |
| `.thx-btn-accent` | Hervorhebung ohne primary-Status       |
| `.thx-btn-danger` | Löschen, Verwerfen                     |
| `.thx-btn-success` | Bestätigen, Veröffentlichen           |

Größen: `.thx-btn-small`, default, `.thx-btn-large`. Höhe via `--d-control-h` (density-abhängig).

### Form-Elemente (`.thx-input`, `.thx-select`, `.thx-textarea`)
Alle nutzen `--d-control-h`, `--d-control-pad-x`, `--d-control-fs`. Focus-Ring in Thoxan-300.

### Chips & Badges
- `.thx-chip` — kompakter Filter/Tag (kleine Pillen)
- `.lam-badge` — farbiger Status-Marker (TODO: nach `.thx-badge` migrieren)

### Cards (TODO — derzeit `.lam-card`)
Kommende `.thx-card` mit Density-Tokens (`--d-card-pad`, `--d-card-radius`).

### Empty-States
Pattern: Icon-Bubble + Titel + Text. Beispiel siehe Mail-Inbox `.mail-empty`.

## Schrift-Disziplin

**Regel:** Auf jeder Seite dürfen nur die 6 Token-Stufen vorkommen.

| Token          | rem    | Typische Verwendung                                |
|----------------|--------|----------------------------------------------------|
| `--d-fs-xs`    | 0.75   | Meta, Labels, Caps-Header, Mini-Badges, Counts     |
| `--d-fs-sm`    | 0.875  | **Body-Default**, Listen, Form-Fields              |
| `--d-fs-base`  | 1.0    | Sub-Heading, Card-Titel, größere Buttons           |
| `--d-fs-lg`    | 1.125  | H3                                                 |
| `--d-fs-xl`    | 1.25   | H2 / Detail-Subject / Page-Title (per Density-Token) |
| `--d-fs-2xl`   | 1.5    | H1 / Stat-Value / Hero-Number                      |

**Sonderfälle, kein `font-size: NNpx`:**
- Material-Symbols haben eigene `font-size` (Icon-Größe, nicht Text)
- Code-Inline: `.thx-mono`-Klasse nutzen (relativ via `em`)

**Tote/verbotene Tokens:**
- ~~`--fs-md`~~ — existiert nicht, wurde überall auf `--d-fs-base` umgebogen
- ~~`var(--fs-*)`~~ (statisch ohne `--d-`) — reagiert nicht auf Density. Immer `var(--d-fs-*)` verwenden.

## Inline-Style-Regeln

✅ **Erlaubt:**
- One-off-Positionierung (`style="transform:..."`)
- Dynamische Werte aus JS (`:style="..."` in Alpine)
- Density-unabhängige Maße (z.B. ein konkretes Bild-Crop)

❌ **Nicht erlaubt:**
- `style="padding: 16px"` → stattdessen Klasse oder Token
- `style="background: #006fb9"` → `var(--thoxan-600)`
- `style="font-size: 14px"` → `var(--fs-sm)`

## Migrations-Status

**Stand 22.05.2026 nach globalem Bulk-Sweep:**

Alle 38 View-Dateien wurden migriert. Konkret:
- **Phase 1**: Alle `var(--fs-*)` (statisch) → `var(--d-fs-*)` (density-bewusst) — ~280 Stellen
- **Phase 2**: Alle hardcoded `rem`-Werte → entsprechende Tokens — ~700 Stellen über 27 verschiedene rem-Werte (0.5–4 rem)
- **Phase 3**: Verbleibende `px`-Werte sind mehrheitlich Material-Symbol-Größen (Icons). `!important` nur noch in Icon-Override-Selektoren und Print-CSS — legitim.

| Modul / Datei                | Status      | Hinweis                                       |
|------------------------------|-------------|----------------------------------------------|
| **Settings (Hub + 10 Tabs)** | ✅ fertig   | 100 % Token, Tab „Design" + Token-Tuner live |
| **Mail** ([inbox.php](views/mail/inbox.php)) | ✅ Bulk durch | Inline-Styles teils noch zu konsolidieren |
| **Kunden** ([customers.php](views/admin/customers.php), [customer-edit.php](views/admin/customer-edit.php), [customer-steckbrief.php](views/admin/customer-steckbrief.php), [customer-wizard.php](views/admin/customer-wizard.php)) | ✅ Bulk durch | px-Icons verbleibend |
| **Chat** ([chat.php](views/chat.php)) | ✅ Bulk durch | 255 px-Stellen größtenteils Icon-bezogen |
| **Wissen** ([knowledge/rag/edit.php](views/knowledge/rag/edit.php)) | ✅ Bulk durch | - |
| **Guidelines** ([contexts/edit.php](views/contexts/edit.php)) | ✅ Bulk durch | - |
| **KI-Kompass** ([canvas/board.php](views/canvas/board.php)) | ✅ Bulk durch | - |
| **Projektplanner** ([projektplanner/](views/projektplanner/)) | ✅ Bulk durch | Tabellen-Dichte über `--d-tbl-*` |
| **Website-Monitor** ([site-monitor.php](views/admin/site-monitor.php)) | ✅ Bulk durch | - |
| **LAM** ([lam/](views/lam/)) | ✅ Bulk durch | `.lam-*` → `.thx-*` migration steht noch aus (separate Aufgabe) |
| **Benutzer** ([_tab_benutzer.php](views/admin/users/_tab_benutzer.php), [_tab_rollen.php](views/admin/users/_tab_rollen.php), [_tab_kunden.php](views/admin/users/_tab_kunden.php), [_tab_audit.php](views/admin/users/_tab_audit.php)) | ✅ Bulk durch | - |
| **Coworker** ([coworker.php](views/admin/coworker.php)) | ✅ Bulk durch | - |
| **Orders** ([orders/workspace.php](views/orders/workspace.php)) | ✅ Bulk durch | - |
| **Editor** ([modules/text/editor.php](views/modules/text/editor.php)) | ✅ Bulk durch | - |

### Offene Folge-Arbeiten

- **`.lam-*` → `.thx-*`-Migration**: LAM-spezifische Klassen schrittweise auf globale `.thx-*` umstellen, dann `lam.css` löschen.
- **Inline-Style-Säuberung**: Wo möglich, Inline-`style="..."`-Sammelsurium durch `.thx-*`-Klassen ersetzen. Insbesondere `chat.php` und `customer-steckbrief.php` sind Inline-schwer.
- **Material-Icon-Größen-Tokens**: Die häufigen 14/16/18/20/24 px-Werte könnten als `--icon-xs/sm/md/lg/xl` standardisiert werden.
