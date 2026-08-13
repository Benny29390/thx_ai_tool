# 00 · Übersicht & Arbeitsanweisung — LAM/LAMS Migrationskontext

Diese Sammlung bündelt **alle von Tom (thomas.kilian@thoxan.com) eingegebenen Prompts** aus den Claude-Code-Chats, in denen das lokale LAM-System (LAMS) gebaut wurde, bevor es ins neue KI-Tool (AI Assistant, `/var/www`, https://ai.thoxan-dev.de) migriert wurde. Ziel: den ursprünglichen Stand und Workflow rekonstruieren, damit die Migration optik- und workflow-seitig sauber nachgezogen werden kann.

Quelle: lokale Claude-Code-Transkripte (`~/.claude/projects/**.jsonl`). Enthalten sind ausschließlich die Nutzer-Prompts, verbatim, ohne Antworten und ohne Tool-Ausgaben. Automatische Kontext-Zusammenfassungen (Compaction) wurden entfernt.

## Arbeitsauftrag (so abarbeiten)

1. Dateien **in nummerierter Reihenfolge** durchgehen (01 → 08), sie sind chronologisch nach Chat-Start sortiert.
2. Innerhalb einer Datei **Sinnabschnitt für Sinnabschnitt** (`## ▸`) vorgehen, nicht Prompt für Prompt.
3. Pro Sinnabschnitt:
   a. Die darin formulierte **Anforderung / Entscheidung** herausziehen (Tom prompted iterativ, oft mit Korrekturen — der *letzte* Stand im Abschnitt gilt).
   b. Mit dem **tatsächlichen Ist-Stand im KI-Tool** abgleichen (Code unter `/var/www`, migrierte LAM-Bereiche).
   c. **Offene oder abweichende Punkte dokumentieren** (Vorlage unten), dann erst zum nächsten Abschnitt.
4. Am Ende über alle Dateien hinweg aus den dokumentierten Abweichungen einen **priorisierten Umsetzungsplan** erstellen.

### Dokumentations-Vorlage je Abschnitt

```
Datei / Abschnitt:  03 · ▸ 7. Sistrix-Kosten ...
Anforderung:        <was Tom wollte, finaler Stand>
Ist im KI-Tool:     <vorhanden / teilweise / fehlt>  — Fundstelle: <pfad:zeile>
Abweichung/offen:   <konkret>
Priorität:          hoch | mittel | niedrig
```

## Dateien (chronologisch nach Chat-Start)

| # | Datei | Inhalt | Zeitraum | Herkunft | Prompts |
|---|-------|--------|----------|----------|--------:|
| 01 | [01-spec-zusammenfassung.md](01-spec-zusammenfassung.md) | Spec-Zusammenfassung | 13.05.2026 | lokal /Downloads/THX LAM-System | 6 |
| 02 | [02-phase-a.md](02-phase-a.md) | Phase A — Pool-Grundgerüst | 13.–15.05.2026 | lokal /lam-prototyp | 90 |
| 03 | [03-asana-und-pool.md](03-asana-und-pool.md) | Asana, Linkquellen-Pool & Linkprofil-Merge | 15.–19.05.2026 | lokal /lam-prototyp (worktree) | 111 |
| 04 | [04-linkprofil.md](04-linkprofil.md) | Linkprofil-Analyse | 17.05.2026 | lokal /lam-prototyp (worktree) | 48 |
| 05 | [05-goofy-borg-parallel.md](05-goofy-borg-parallel.md) | Parallel-Chat (goofy-borg) | 17.05.2026 | lokal /lam-prototyp (worktree) | 1 |
| 06 | [06-ki-tool-vorlauf.md](06-ki-tool-vorlauf.md) | THX KI-Tool (Migration, Vorlauf) | 19.05.2026 | SSH /var/www | 12 |
| 07 | [07-lams-migration.md](07-lams-migration.md) | LAMS-Migration ins KI-Tool | 19.–20.05.2026 | SSH /var/www | 9 |
| 08 | [08-visual-differences.md](08-visual-differences.md) | Visuelle Abweichungen nach Migration | 19.05.2026 | lokal /Downloads/THX LAM-System | 6 |

## Lesehinweise zum Kontext

- **Chat 01–04** entstanden lokal beim Bau des Prototyps (Phase A bis Linkprofil/Asana). Hier steckt der inhaltliche Soll-Zustand: Datenmodell, Module, UI-/Workflow-Entscheidungen, Umbenennungen, Sistrix-/KI-Logik.
- **Chat 05 (goofy-borg)** ist das Übergabe-Briefing für das Linkprofil-Modul (lief sequenziell parallel zu 04).
- **Chat 06–07** sind die eigentliche Migration ins KI-Tool über SSH (`/var/www`): erst Vorlauf/Kunden-Steckbrief, dann LAMS-Integration, Sidebar-Menüpunkt und Übernahme des UI-Styleguides.
- **Chat 08** dokumentiert den festgestellten **Optik-Gap** (Header/Navigation, Tabellen, Corporate Design) zwischen altem LAMS und migrierter Fassung — zentral für die noch offene gestalterische Angleichung.

Tom schreibt knapp und iterativ; viele Prompts sind Feedback-/Korrekturschleifen zum jeweils vorigen Stand. Beim Abgleich zählt der jeweils **letzte** Stand innerhalb eines Sinnabschnitts.
