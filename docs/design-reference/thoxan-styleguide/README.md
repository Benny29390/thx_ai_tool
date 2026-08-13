# Thoxan Styleguide — Anbindung & Aktualisierung

Der **Styleguide** im KI-Tool (`/admin/styleguide`, Sidebar-Eintrag „Styleguide",
sichtbar für Admin + Manager) wird aus dem **Claude-Design-Projekt** generiert:

- Projekt: **Thoxan Styleguide Entwicklung**
- Projekt-ID: `bdf61d56-eefb-4d2b-8a29-11cac7e7a94a`
- Leit-Datei: `Thoxan Styleguide.dc.html`

Es gibt **keine Live-Verbindung** zwischen KI-Tool und claude.ai — das Tool kann
das Design nicht selbst abrufen. Aktualisiert wird **auf Zuruf** über einen
reproduzierbaren Import.

## Seitenaufbau (drei Reiter)

`/admin/styleguide` ist ein Hub mit drei Reitern (`?tab=`):

- **Corporate Design** (`?tab=corporate`, Default) — der aus Claude Design generierte
  Marken-Guide (read-only).
- **Tokens & Live-Tuning** (`?tab=tokens`) — der frühere Design-Tab aus
  `settings?tab=design` (Dichte-Profile, Token-Stepper, Farb-Paletten, Live-Vorschau;
  Persistenz nur per localStorage). Nutzt lokales Alpine (`/assets/js/vendor/alpine.min.js`).
- **Soll/Ist** (`?tab=vergleich`) — `views/admin/styleguide/_vergleich.php`: dokumentiert
  die Abweichungen zwischen Marken-Standard und tatsächlichen Tool-Tokens.

## Schrift global

Die drei re-serialisierten woff2 (`assets/fonts/styleguide/frutiger-45-light|65-bold|95-ultrablack.woff2`)
sind seit 24.06.2026 **auch die globalen Tool-Schriften** — eingebunden als `@font-face`
`'Frutiger LT Std'` in `assets/css/thx-tokens.css` (400 → 45 Light, 700 → 65 Bold,
900 → 95 UltraBlack). Damit ist die Schrift im ganzen Tool markenkonsistent und der
Fließtext leichter (45 Light statt des schwereren „Roman"). Preloads in
`views/layouts/main.php` zeigen ebenfalls auf diese Dateien.

## Dateien

| Datei | Rolle |
|---|---|
| `Thoxan Styleguide.dc.html` (hier) | Roh-Export aus Claude Design = **Quelle der Wahrheit** |
| `scripts/import-styleguide.php` | Transformer: Export → Partial |
| `views/admin/styleguide.php` | **Hand-geschriebener** Hub-Wrapper (Reiter-Logik) |
| `views/admin/styleguide/_corporate.php` | **Generiert** vom Importer — nicht von Hand bearbeiten |
| `views/admin/styleguide/_tokens.php` | Tokens & Live-Tuning (verschoben aus settings) |
| `assets/images/thoxan-logo-white.svg`, `thoxan-x-white.svg` | aus dem Design gezogene weiße Logo-Varianten |

## Was der Import macht

`scripts/import-styleguide.php` wandelt den Claude-Design-Export in eine
eigenständige App-View um:

- **Datengetrieben:** liest Menüpunkte (`nav`), Sektions-Mapping, Farb-/Spacing-Listen
  direkt aus dem Export. Neue oder entfallene Menüpunkte werden automatisch übernommen
  (z. B. Wechsel von 12 auf 14 Punkte am 25.06.2026 mit „Geschäftsausstattung" +
  „Markenentwicklung") — **das feste Layout (Sidebar links / Hauptbereich rechts) bleibt
  dabei immer unverändert**, nur Inhalt und Anzahl der Punkte ändern sich.
- löst die Template-Direktiven auf (`sc-if` → Sektionen, `sc-for` → Listen)
- ersetzt die Editier-Komponenten (`<image-slot>`) durch statische Platzhalter
- biegt Asset-Pfade auf `/assets/images/*` um
- bindet die **Original-Brand-Schriften** (45 Light / 65 Bold / 95 UltraBlack)
  seitenlokal als **woff2** ein. Jede Inline-`font-family` bekommt zusätzlich den
  Fallback `'Frutiger LT Std', sans-serif` — so erscheint nie eine Serifenschrift,
  falls eine Brand-Schrift mal nicht lädt. Scoped auf diese Seite; die globale
  App-Webfont bleibt unangetastet.

  **Wichtig (woff2-Konvertierung):** Die gelieferten TTFs sind alte Adobe-Dateien
  (1988–1994) und werden vom Browser-Font-Sanitizer (OTS) abgelehnt → würden als
  Serife fallbacken. Deshalb werden sie einmalig zu woff2 re-serialisiert (das
  bereinigt die Tabellen). TTFs liegen als Quelle in `assets/fonts/styleguide/*.ttf`,
  ausgeliefert werden `frutiger-45-light.woff2`, `frutiger-65-bold.woff2`,
  `frutiger-95-ultrablack.woff2`. Konvertierung (nur nötig, wenn neue Schriftdateien kommen):
  ```bash
  python3 -m venv venv && ./venv/bin/pip install fonttools brotli
  ./venv/bin/python -c "from fontTools.ttLib import TTFont; \
import sys; f=TTFont(sys.argv[1]); [f.__delitem__(t) for t in ('DSIG','FFTM') if t in f]; \
f.flavor='woff2'; f.save(sys.argv[2])" IN.ttf OUT.woff2
  ```
- baut eine eigene linke Kapitel-Navigation (12 Kapitel, Umschalten ohne Reload,
  Deep-Link via `#kapitel`, z. B. `/admin/styleguide#farben`)

## Aktualisieren (auf Zuruf)

Wenn das Design in Claude weitergebaut wurde:

1. **Neuen Export holen** und hier ablegen (überschreibt die Quelle):
   `docs/design-reference/thoxan-styleguide/Thoxan Styleguide.dc.html`
   — das übernimmt normalerweise Claude über die Design-Anbindung (DesignSync-MCP,
   `get_file` auf die Projekt-ID oben). Sag dazu einfach: **„Styleguide aktualisieren"**.
2. **Importer laufen lassen:**
   ```bash
   php scripts/import-styleguide.php
   ```
   regeneriert `views/admin/styleguide.php`. Kein Build-Step, sofort wirksam.

### Wenn neue Bild-Assets dazukommen

Verweist ein neuer Export auf zusätzliche Dateien (z. B. weitere SVGs/Logos),
müssen die einmalig nach `assets/images/` kopiert werden. Die generische
Pfad-Umschreibung (`assets/…` → `/assets/images/…`) deckt jeden Dateityp ab
(svg/png/jpg). Aktuell in `assets/images/`: Logos `thoxan-logo[-white].svg`,
`thoxan-x[-white].svg`, `logo-communications[-white].png`,
`logo-ecommerce[-white].svg`; Fotos `photo-team.jpg`, `photo-meeting.jpg`,
`photo-consult.jpg`, `photo-workshop.jpg`.

> **Hinweis `photo-workshop.jpg`:** Das Original (1400×1052) überschreitet das
> 256-KB-Limit der Design-API und ließ sich nicht vollständig übertragen
> (Serve-URL ist Cross-Origin-/Cloudflare-geschützt). Aktuell liegt dort ein
> **markenkonformer Platzhalter** (gleiches Seitenverhältnis). Original bitte
> manuell nach `assets/images/photo-workshop.jpg` legen.

## Kapitel (Stand des aktuellen Exports, 14 Punkte)

00 Übersicht · 01 Logo & Bildmarke · 02 Farben · 03 Typografie ·
04 Gestaltungselemente · 05 Buttons & UI · 06 Bildsprache · 07 Icons ·
08 Layout & Grid · 09 Tonalität · 10 Werbemittel & Creatives ·
11 Geschäftsausstattung · 12 Anwendung · 13 Markenentwicklung
