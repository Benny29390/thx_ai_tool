# LAM-Design-Referenz

Referenzdateien für den UI-Look des LAM-Systems der Thoxan Communications GmbH.
Diese Sammlung dient als Vorlage beim Nachbau der LAM-Bereiche
(Linkprofil, Linkquellen, Anbieter, Linkakquise, Linkoptionen, Maßnahmen,
Auslagen, Monitoring, Korrespondenz) in der Migration ins KI-Tool.

## Wichtigste Datei

**`lam-ui-styleguide.md`** ist die verbindliche Spezifikation. Dort stehen
Farben, Schriften, Komponentenklassen, Header/Nav-Markup, Tabellen-Pattern
und Anti-Muster aus der Migration. Bei Widersprüchen zwischen dieser
Spezifikation und dem Originalcode in diesem Paket gewinnt der Originalcode.

## Inhalt

| Pfad                             | Zweck                                                                    |
| ---                              | ---                                                                      |
| `lam-ui-styleguide.md`           | Verbindliche UI-Spezifikation                                            |
| `tailwind.config.js`             | Thoxan-Farbpalette und Schrift-Stack                                     |
| `css/app.css`                    | Globale CSS-Komponenten (`lam-filter-input`, `lam-filter-chip`, ...)     |
| `layouts/LamLayout.vue`          | Header, Nav, Footer (Source of Truth für Seitenrahmen)                   |
| `pages/Linkprofil-Index.vue`     | Beispielseite mit Filter-Chips, Bulk-Toolbar, breiter Tabelle            |
| `pages/Linkquellen-Liste.vue`    | Beispielseite mit Multiselect-Filter und Range-Inputs                    |
| `components/PrimaryButton.vue`   | Primary-Button                                                           |
| `components/SecondaryButton.vue` | Secondary-Button                                                         |
| `components/DangerButton.vue`    | Danger-Button (Rose)                                                     |
| `components/TextInput.vue`       | Standard-Texteingabe                                                     |
| `components/InputLabel.vue`      | Standard-Label                                                           |
| `components/Checkbox.vue`        | Standard-Checkbox                                                        |
| `components/Modal.vue`           | Dialog/Modal-Komponente                                                  |
| `assets/thoxan-logo.svg`         | Thoxan-Logo für die Hauptnavigation                                      |
| `fonts/frutiger-lt-std-*.woff2`  | Hausschrift Frutiger LT Std (Roman + Bold)                               |

## Vorgehen für die Migration

1. **`lam-ui-styleguide.md` zuerst lesen.** Sie ordnet alle anderen Dateien ein.
2. **Farben aus `tailwind.config.js` übernehmen** (Thoxan-Palette als eigene Farbgruppe).
3. **Globale Klassen aus `css/app.css` übernehmen** (`@layer components`-Block).
4. **`LamLayout.vue` als Vorlage für den dreigeteilten Header** (dunkelblauer
   Admin-Streifen + weiße Modul-Nav + Page-Header).
5. **Pages und Components als konkrete Tabellen- und Filter-Vorlagen** nutzen.
6. **Logo und Webfonts ausliefern**, sonst sieht das LAM nicht nach LAM aus.

## Hinweise

- Stack im Original: Laravel 11 + Inertia.js + Vue 3 + Tailwind 3. Wenn die
  Migration einen anderen Stack nutzt, die Klassen-Snippets als CSS-Referenz
  übersetzen, nicht als Drop-in.
- Schrift-Regel `html { font-size: 120% }` aus `css/app.css` ist absichtlich.
  Beim Übernehmen mitnehmen oder bewusst weglassen, nicht versehentlich vergessen.
- Empfehlungs- und Statuszellen sind im Original **Klartext, keine farbigen
  Badges**. Vor Einführung farbiger Badges mit Thomas Kilian abstimmen.
