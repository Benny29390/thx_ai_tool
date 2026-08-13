# LAM UI-Styleguide

Verbindlicher Styleguide für das LAM-System Frontend. Übergabe an die Migration ins KI-Tool. Quelle der Wahrheit ist der Laravel-Prototyp unter `lam-prototyp/`. Wenn diese Datei und der Originalcode auseinanderlaufen, gewinnt der Originalcode.

Zielgruppe: Entwickler oder KI-Assistent, der LAM-Bereiche (Linkprofil, Linkquellen, Anbieter, Linkakquise, Linkoptionen, Maßnahmen, Auslagen, Monitoring, Korrespondenz) in einer anderen Codebasis nachbaut.

## Stack-Annahme

Original: Laravel 11, Inertia.js, Vue 3, Tailwind CSS 3, @tailwindcss/forms. Wenn die Migration einen anderen Stack nutzt (z.B. React, Next.js, kein Tailwind), gelten die Klassen-Snippets weiterhin als CSS-Referenz und müssen 1:1 in den Ziel-Stack übersetzt werden.

## 1. Design-Tokens

### Farbpalette Thoxan

Eigene Tailwind-Farbgruppe `thoxan-*` (siehe `tailwind.config.js`). Diese Werte sind das Corporate-Design-Blau und müssen exakt übernommen werden.

```js
colors: {
  thoxan: {
    50:  '#e6f0f8',
    100: '#cce1f1',
    200: '#99c3e3',
    300: '#66a5d5',
    400: '#3387c7',
    500: '#006fb9',
    600: '#005da8',
    700: '#004a86',
    800: '#003864',
    900: '#002542',
    950: '#001329',
  },
}
```

**Verwendung:**

- `thoxan-700` ist die Admin-Top-Bar (dunkelblau).
- `thoxan-600` ist Primary-Button und aktiver Nav-Eintrag.
- `thoxan-50` ist Hintergrund für Hover und für ausgewählte Tabellenzeilen (mit Opacity).
- `thoxan-300/400` für Fokus-Ringe in Inputs.

Daneben werden Tailwind-Standardfarben genutzt:

- **Slate** für Text, Border, Hintergründe (`slate-50` bis `slate-900`).
- **Emerald** für Erfolg, "neu"-Badges, erreichbar-Indikator.
- **Amber** für Warnung, "ohne Empfehlung", Teil-Verfügbarkeit.
- **Rose** für Fehler, "nicht erreichbar", Lösch-Bestätigung.
- **Indigo** für Linkart-Pills (Linkquellen-Filter).

### Schrift

Hausschrift **Frutiger LT Std** (Webfonts liegen in `public/fonts/` als `.woff2`). Stack:

```css
font-family: 'Frutiger LT Std', 'Frutiger', system-ui, sans-serif;
```

Falls Frutiger im Ziel-System nicht lizenziert oder lieferbar ist: Fallback explizit als `system-ui, sans-serif` einsetzen, **nicht** auf Inter oder Roboto wechseln ohne Rücksprache.

Wichtige globale Regel aus `resources/css/app.css`:

```css
html { font-size: 120%; }
```

Das ist absichtlich. Alle `text-xs` bis `text-2xl`-Größen aus Tailwind sind dadurch effektiv 20% größer als Default. Wer das wegnimmt, bekommt sofort eine zu kleine UI.

### Layout-Grid

- Maximale Breite: `max-w-[1920px]` zentriert.
- Außen-Padding: `px-4 sm:px-6 lg:px-8`.
- Hintergrund Seite: `bg-slate-50`.
- Karten/Container: `bg-white rounded-lg border border-slate-200`.

## 2. Globale Komponentenklassen (Filter)

Aus `resources/css/app.css`. Bitte **identisch** ins Ziel-CSS übernehmen, weil die Listen-Seiten sie querbeet referenzieren.

```css
@layer components {
  .lam-filter-label {
    @apply block text-xs font-medium text-slate-600;
  }
  .lam-filter-input {
    @apply w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm
           text-slate-800 placeholder-slate-400
           focus:border-thoxan-400 focus:outline-none focus:ring-1 focus:ring-thoxan-300;
  }
  .lam-filter-chip {
    @apply inline-flex items-center rounded px-2.5 py-1 text-xs;
  }
}
```

Im Ziel-Stack ohne Tailwind als reines CSS ausschreiben.

## 3. Seitenaufbau

Jede Listen-Seite hat **dreigeteilten Kopf** plus Content. Quelle: `resources/js/Layouts/LamLayout.vue`.

```
┌────────────────────────────────────────────────────────────────┐
│ Admin-Top-Bar (dunkelblau, h-11)                               │  thoxan-700
│   rechtsbündig: Kunden · Einstellungen · [TKI] Thomas Kilian   │
├────────────────────────────────────────────────────────────────┤
│ Haupt-Navigation (weiß, h-20)                                  │  weiß
│   links: Thoxan-Logo · rechts: Modul-Tabs                      │
├────────────────────────────────────────────────────────────────┤
│ Page-Header (weiß, py-5)                                       │
│   links: H1 + Untertitel · rechts: Action-Button-Reihe         │
├────────────────────────────────────────────────────────────────┤
│ Main (slate-50, py-6)                                          │
│   Filter-Card · Tabellen-Card · Pagination                     │
└────────────────────────────────────────────────────────────────┘
```

### Admin-Top-Bar

```html
<div class="bg-thoxan-700 text-white">
  <div class="mx-auto max-w-[1920px] px-4 sm:px-6 lg:px-8">
    <div class="flex h-11 items-center justify-end gap-1 text-sm">
      <!-- Nav-Links rechtsbündig -->
      <a href="..." class="rounded px-3 py-1.5 text-sm font-bold transition
                          text-thoxan-50 hover:bg-thoxan-600">Kunden</a>
      <a href="..." class="rounded px-3 py-1.5 text-sm font-bold transition
                          bg-white text-thoxan-700">Einstellungen</a>
      <!-- aktiver Tab: bg-white text-thoxan-700 -->

      <span class="mx-2 h-5 border-l border-thoxan-500"></span>

      <span class="flex items-center gap-2">
        <span class="rounded bg-thoxan-600 px-2 py-0.5 font-mono text-xs font-bold text-white">TKI</span>
        <span class="text-thoxan-50">Thomas Kilian</span>
      </span>
    </div>
  </div>
</div>
```

### Haupt-Navigation

```html
<nav class="border-b border-slate-200 bg-white">
  <div class="mx-auto max-w-[1920px] px-4 sm:px-6 lg:px-8">
    <div class="flex h-20 items-center justify-between">
      <a href="/" class="flex items-center">
        <img src="/assets/thoxan-logo.svg" alt="Thoxan" class="h-12 w-auto" />
      </a>

      <div class="hidden md:flex md:items-center md:gap-1">
        <!-- normaler Tab -->
        <a href="..." class="rounded-md px-3 py-2 text-sm font-bold transition
                             text-slate-700 hover:bg-thoxan-50 hover:text-thoxan-700">
          Dashboard
        </a>
        <!-- aktiver Tab -->
        <a href="..." class="rounded-md px-3 py-2 text-sm font-bold transition
                             bg-thoxan-600 text-white">
          Linkprofil
        </a>
      </div>
    </div>
  </div>
</nav>
```

Reihenfolge der Modul-Tabs (links nach rechts, folgt dem operativen Prozess):

`Dashboard · Linkprofil · Linkquellen · Anbieter · Linkakquise · Linkoptionen · Maßnahmen · Auslagen · Monitoring · Korrespondenz`

**Wichtig:** Es gibt **keine** Sidebar im LAM. Auch keine Tabs innerhalb eines Moduls. Wenn die Migration LAM unter eine globale Sidebar packt, soll das Modul intern trotzdem den hier beschriebenen Header reproduzieren.

### Page-Header

```html
<header class="border-b border-slate-200 bg-white">
  <div class="mx-auto max-w-[1920px] px-4 py-5 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-slate-800">Linkprofil</h1>
        <div class="mt-1 text-sm text-slate-500">
          1.840 Verlinkungen
          <span class="text-emerald-700"> · 422 neu</span>
          <span class="text-amber-700"> · 148 ohne Empfehlung</span>
        </div>
      </div>
      <div class="flex gap-2">
        <!-- Secondary-Buttons -->
        <button class="inline-flex items-center gap-1.5 rounded border border-slate-300
                       bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
          Domain-Wissen
        </button>
        <!-- Accent-Button (z.B. "Aufräumen") -->
        <button class="inline-flex items-center gap-1.5 rounded border border-thoxan-300
                       bg-white px-4 py-2 text-sm font-medium text-thoxan-700 hover:bg-thoxan-50">
          Aufräumen
        </button>
        <!-- Primary-Button (immer ganz rechts, immer mit + Präfix für "neu") -->
        <button class="rounded bg-thoxan-600 px-4 py-2 text-sm font-bold text-white hover:bg-thoxan-700
                       disabled:cursor-not-allowed disabled:bg-slate-400">
          + CSV importieren
        </button>
      </div>
    </div>
  </div>
</header>
```

**Header-Aktionen Linkprofil (exakte Reihenfolge):**

`Domain-Wissen · Snapshots · Statistik · Excel · URLs kopieren · Aufräumen · Historie importieren · + CSV importieren`

**Header-Aktionen Linkquellen (exakte Reihenfolge):**

`Tags · Linklisten-Import · + Neue Domain`

## 4. Filter-Bereich

Pattern in jeder Listen-Seite (Linkprofil, Linkquellen, Linkoptionen):

```html
<section class="rounded-lg border border-slate-200 bg-white p-4">
  <div class="flex items-center justify-between">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filter</h2>
    <button class="text-sm text-thoxan-700 hover:underline">▾ Weitere Filter</button>
  </div>

  <!-- Schnellfilter-Zeile: Suche | Multiselect Kunden | Dropdown -->
  <div class="mt-3 grid grid-cols-12 gap-4">
    <div class="col-span-3">
      <label class="lam-filter-label">Volltext URL / Linktext</label>
      <input type="text" class="lam-filter-input mt-1" placeholder="z.B. spam" />
    </div>
    <div class="col-span-6">
      <label class="lam-filter-label flex items-baseline gap-1.5">
        Kunde <span class="text-[10px] text-slate-400">Klick = wechseln</span>
      </label>
      <div class="mt-1 flex flex-wrap gap-1.5">
        <!-- Kunden-Chips, aktiver Kunde = bg-thoxan-600 text-white -->
        <button class="lam-filter-chip font-medium bg-thoxan-600 text-white">BKK</button>
        <button class="lam-filter-chip font-medium bg-thoxan-50 text-thoxan-700 ring-1 ring-thoxan-200 hover:bg-thoxan-100">CAY</button>
      </div>
    </div>
    <div class="col-span-3">
      <label class="lam-filter-label">Erreichbarkeit</label>
      <select class="lam-filter-input mt-1 bg-white">…</select>
    </div>
  </div>

  <!-- Chip-Reihen (Linkart, Empfehlung, Status, Quelle) -->
  <div class="mt-3">
    <label class="lam-filter-label flex items-baseline gap-1.5">
      Linkart <span class="text-[10px] text-slate-400">Klick = nur diese · Shift/Ctrl = mehrere</span>
    </label>
    <div class="mt-1 flex flex-wrap gap-1.5">
      <!-- aktiver Chip -->
      <button class="lam-filter-chip transition bg-thoxan-600 text-white">Spam</button>
      <!-- inaktiver Chip (Linkprofil-Variante: thoxan-blau) -->
      <button class="lam-filter-chip transition bg-slate-100 text-slate-600 hover:bg-slate-200">Branchenverzeichnis</button>
    </div>
  </div>
</section>
```

### Chip-Farbschemata

Je nach Filtergruppe ein anderes Akzentschema:

| Gruppe                       | Aktiv                              | Inaktiv                                                  |
| ---                          | ---                                | ---                                                      |
| Kunde                        | `bg-thoxan-600 text-white`         | `bg-thoxan-50 text-thoxan-700 ring-1 ring-thoxan-200`    |
| Linkart (Linkprofil)         | `bg-thoxan-600 text-white`         | `bg-slate-100 text-slate-600`                            |
| Linkart (Linkquellen)        | `bg-indigo-600 text-white`         | `bg-slate-100 text-slate-600`                            |
| Empfehlung / Status / Quelle | `bg-thoxan-600 text-white`         | `bg-slate-100 text-slate-600`                            |
| Verifikation (Linkquellen)   | individuell pro Wert (siehe unten) | individuell pro Wert                                     |
| Sonder-Chip "ohne X"         | `bg-amber-500 text-white`          | `bg-amber-50 text-amber-800 ring-1 ring-amber-200`       |
| "alle"-Chip (Reset)          | -                                  | `border border-dashed border-slate-300 text-slate-500`   |

Verifikations-Chips (Linkquellen) haben eigene Farben pro Wert: `neu` = amber, `in_arbeit` = thoxan-blau, `geprueft` = emerald, `veraltet` = orange, `geloescht` = rose. Beim Hover dunkler.

### Range-Inputs

Zwei Inputs nebeneinander, beide `lam-filter-input`, Placeholder `min` / `max`:

```html
<div class="flex gap-1.5">
  <input type="number" placeholder="min" class="lam-filter-input" />
  <input type="number" placeholder="max" class="lam-filter-input" />
</div>
```

## 5. Tabellen

Pattern aus Linkprofil und Linkquellen, identisch:

```html
<section class="rounded-lg border border-slate-200 bg-white">
  <table class="w-full table-auto divide-y divide-slate-200
                [&_tr>*:not(:first-child)]:border-l [&_tr>*:not(:first-child)]:border-slate-100">
    <thead class="bg-slate-50">
      <tr>
        <!-- Checkbox-Spalte -->
        <th class="px-2 py-2 text-center">
          <input type="checkbox" class="rounded border-slate-300 text-thoxan-700 focus:ring-thoxan-500" />
        </th>

        <!-- Sortierbare Spalte -->
        <th class="cursor-pointer px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide
                   text-slate-500 hover:bg-slate-100">
          Domain ↑
        </th>

        <!-- Rechtsbündig (Zahlen) -->
        <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide
                   text-slate-500 hover:bg-slate-100">
          SI
        </th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 bg-white text-sm">
      <tr class="hover:bg-slate-50">
        <td class="px-2 py-2 text-center">
          <input type="checkbox" class="rounded border-slate-300 text-thoxan-700 focus:ring-thoxan-500" />
        </td>
        <td class="px-3 py-2 align-top">
          <a href="…" target="_blank" rel="noopener"
             class="truncate text-thoxan-700 hover:underline">…</a>
        </td>
        <td class="px-3 py-2 align-top text-right">0.0061</td>
      </tr>
      <!-- ausgewählte Zeile -->
      <tr class="hover:bg-slate-50 bg-thoxan-50/40">…</tr>
    </tbody>
  </table>

  <!-- Pagination-Footer -->
  <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200
              bg-slate-50 px-3 py-2 text-sm text-slate-600">
    <div>Zeige <span class="font-medium text-slate-900">1–50</span>
         von <span class="font-medium text-slate-900">1.840</span></div>
    <div class="flex items-center gap-3">…Pro Seite-Dropdown + Seitenblättern…</div>
  </div>
</section>
```

### Wichtige Regeln Tabelle

- **Spalten-Header:** `text-xs font-semibold uppercase tracking-wide text-slate-500`. Sortierbar = Cursor-Pointer plus Hover-Hintergrund.
- **Zeilen-Hover:** `hover:bg-slate-50`. Ausgewählte Zeile zusätzlich `bg-thoxan-50/40`.
- **Spalten-Trenner:** Subtile linke Border ab zweiter Spalte: `[&_tr>*:not(:first-child)]:border-l [&_tr>*:not(:first-child)]:border-slate-100`.
- **Zeilen-Trenner:** `divide-y divide-slate-100` im `tbody`, `divide-y divide-slate-200` zwischen `thead` und `tbody`.
- **Padding:** Standard `px-3 py-2`. Checkbox-Spalte enger: `px-2 py-2`.
- **Schrift im Body:** `text-sm` (durch `html { font-size: 120% }` effektiv größer als Default).
- **URLs:** `text-thoxan-700 hover:underline`. Externe Links mit `target="_blank" rel="noopener"`.
- **Leerwerte:** `—` (em-Dash als Geviert) in `text-slate-300` oder `text-slate-400`. Auch in Tabellen ist der em-Dash erlaubt, im Fließtext der UI nicht.

### Spalten-Definition Linkprofil

Reihenfolge in `Pages/Linkprofil/Index.vue`:

`☐ · Domain · URL · Wie oft · Linktext · Linkart · Empfehlung · Status · ⏵ (Erreichbarkeit) · SI · Tags · Bemerkung · Neu · Quelle`

### Spalten-Definition Linkquellen

`☐ · URL · Anbieter · Tags · SI/DP · Preis · Status · Kunden`

### Statuszellen und Empfehlungszellen sind **nicht** bunte Badges

Im Original ist `Empfehlung` und `Status` **Klartext**, optional klickbar zum Inline-Editieren, kein farbiger Badge.

```html
<td class="px-3 py-2 align-top">
  <button class="w-full whitespace-nowrap rounded px-1 py-0.5 text-left hover:bg-thoxan-50">
    lassen
  </button>
</td>
```

Falls die Migration farbige Badges einführen will, vorher mit Thomas Kilian abstimmen. Die Migration-Screenshots zeigen Badges in Rot, Grün, Gelb. Das ist **nicht** der Original-Stil und macht die Tabelle bei 50+ Zeilen unruhig.

### Echte Badges (kleine, sparsam)

Es gibt nur drei Badge-Typen im Original:

```html
<!-- "neu"-Badge in der "Neu"-Spalte -->
<span class="inline-flex items-center rounded-full bg-emerald-100 px-2 text-xs font-semibold text-emerald-800">neu</span>

<!-- "Wie oft >1"-Counter -->
<span class="inline-flex items-center rounded-full bg-amber-100 px-2 text-xs font-semibold text-amber-800">3</span>

<!-- Tags in der Tags-Spalte -->
<span class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Topp-Link</span>
```

### Erreichbarkeits-Indikator

Kein Text, sondern ein farbiger Punkt:

```html
<!-- erreichbar, Link gefunden -->
<span class="inline-block h-3 w-3 rounded-full bg-emerald-500" title="Erreichbar (HTTP 200)"></span>
<!-- erreichbar, Link entfernt -->
<span class="inline-block h-3 w-3 rounded-full bg-amber-500" title="Linkziel nicht mehr im HTML"></span>
<!-- nicht erreichbar -->
<span class="inline-block h-3 w-3 rounded-full bg-rose-500" title="Nicht erreichbar"></span>
<!-- noch nicht geprüft -->
<span class="inline-block h-3 w-3 rounded-full bg-slate-200" title="noch nicht geprüft"></span>
```

## 6. Buttons (Übersicht)

| Typ                  | Klassen                                                                                       |
| ---                  | ---                                                                                           |
| Primary (Aktion)     | `rounded bg-thoxan-600 px-4 py-2 text-sm font-bold text-white hover:bg-thoxan-700`            |
| Secondary (Standard) | `rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50` |
| Accent (Thoxan)      | `rounded border border-thoxan-300 bg-white px-4 py-2 text-sm font-medium text-thoxan-700 hover:bg-thoxan-50` |
| Danger               | `rounded bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-700`          |
| Success (Submit)     | `rounded bg-emerald-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-emerald-700`        |
| Klein (Bulk-Toolbar) | `rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50` |

Disabled für alle: `disabled:cursor-not-allowed disabled:opacity-50` oder bei Primary `disabled:bg-slate-400`.

## 7. Flash-Messages

Erfolg oben in Main, mit Slide-in-Animation:

```html
<div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
  Status aktualisiert.
</div>
```

Fehler analog mit `border-red-200 bg-red-50 text-red-800`.

## 8. Footer

Eine schmale Zeile mit Branding und Claim:

```html
<footer class="mt-auto border-t border-slate-200 bg-white">
  <div class="mx-auto max-w-[1920px] px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex flex-col items-start gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <span class="font-bold text-thoxan-700">LAM</span>
        <span class="text-slate-400">Linkaufbau-Management der Thoxan Communications GmbH</span>
      </div>
      <span class="italic text-thoxan-600">frischer wind im netz.</span>
    </div>
  </div>
</footer>
```

## 9. Anti-Muster aus der Migration

Konkret beobachtete Abweichungen, die korrigiert werden müssen:

1. **Linke Sidebar statt horizontaler Hauptnavigation.** Wenn das KI-Tool global eine Sidebar hat, akzeptabel, aber das LAM-Modul muss innerhalb seines Bereichs den hier beschriebenen weißen Nav-Header mit Logo und Modul-Tabs reproduzieren.
2. **Fehlende Admin-Top-Bar (dunkelblauer Streifen).** Kunden- und Einstellungen-Link plus Mitarbeiterkürzel gehören dort hin, nicht in die Sidebar.
3. **Farbige Empfehlungs-Badges (rot/grün/gelb).** Original = Klartext. Wer Badges braucht, klärt vorher.
4. **Filter als Dropdowns statt Chip-Pills.** Multiselect-Chips sind das LAM-Filter-Idiom. Dropdowns sind nur für Single-Select-Felder (Erreichbarkeit, Letzter Check, Herkunft, Sortier-Modus).
5. **Reduzierte Tabellenspalten.** Spaltenliste oben einhalten. Wenn Platz fehlt, lieber horizontal scrollen lassen als Spalten weglassen.
6. **Fehlende Action-Buttons (Excel-Export, CSV-Import, URLs kopieren, Aufräumen, Historie, Snapshots, Statistik).** Header-Aktionen oben einhalten.
7. **Kein `max-w-[1920px]`.** Das LAM ist explizit für breite Monitore (Linkprofil mit 14 Spalten). Auf 1200px gequetscht funktioniert es nicht.
8. **Frutiger fehlt.** Wenn das Ziel-System Inter oder einen anderen Default nutzt, sieht das LAM "fremd" aus. Frutiger einbinden oder explizit auf `system-ui` zurückfallen, kein anderer Webfont.
9. **Tabs innerhalb des LAM (Dashboard / Linkquellen-Pool / Linkprofil).** Im Original sind das eigenständige Top-Nav-Einträge, keine Tabs.

## 10. Referenz-Dateien im Original-Repo

Wenn diese Datei eine Frage nicht beantwortet, sind diese Dateien die maßgebliche Quelle:

| Was                             | Datei                                                                |
| ---                             | ---                                                                  |
| Farben, Schrift                 | `tailwind.config.js`, `resources/css/app.css`                        |
| Layout, Header, Nav, Footer     | `resources/js/Layouts/LamLayout.vue`                                 |
| Linkprofil-Seite (Filter+Tabelle) | `resources/js/Pages/Linkprofil/Index.vue`                          |
| Linkquellen-Liste               | `resources/js/Pages/Linkquellen/Liste.vue`                           |
| Modal, Buttons, Inputs (atomic) | `resources/js/Components/*.vue`                                      |
| Webfonts                        | `public/fonts/frutiger-lt-std-*.woff2`                               |
| Logo                            | `public/assets/thoxan-logo.svg`                                      |
| Corporate-Design-PDF (historisch) | `docs/styleguide/2010-07 Corporate Design Styleguide.pdf`          |

## 11. Stil der Texte (kein UI-Stil, aber Pflicht)

Aus dem Projekt-CLAUDE.md:

- Sprache **Deutsch**.
- Höflichkeitsformen `Du, Dich, Dir, Dein, Ihr, Euch, Euer` immer groß.
- Im UI-Text **keine Gedankenstriche** (em-dash). In Tabellen als Platzhalter `—` erlaubt.
- Anglizismen vermeiden, wenn ein gängiges deutsches Wort existiert: `Maßnahme` statt `Campaign`, `Umsetzer` statt `Implementer`, `Vorgang` statt `Process`. API, Dashboard, Pool, Stack dürfen bleiben.
- Feste Schreibweisen: `Thoxan`, `BKK Gildemeister Seidensticker`, `FRYKA`, `Sistrix`.

## 12. Was nicht zum LAM-Look gehört

- Kein Material Design, kein Bootstrap-Look, keine Antd-Defaults.
- Keine Rounded-XL-Cards mit dicken Schatten.
- Keine Emoji-Icons in Tabellen.
- Keine alternierenden Zeilen (zebra striping). Nur Hover und Selection.
- Keine Avatar-Bilder.
