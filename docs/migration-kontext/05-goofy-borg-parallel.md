# 05 · Parallel-Chat (goofy-borg)

> Zeitraum: 17.05.2026 · Herkunft: lokal /lam-prototyp (worktree) · 1 Prompts · 1 Sinnabschnitte
>
> Sinnabschnitte sind mit `## ▸` markiert. Pro Abschnitt: Anforderung gegen Ist-Stand im KI-Tool prüfen, offene/abweichende Punkte dokumentieren, dann nächster Abschnitt.

## ▸ 1. Übergabe-Briefing Linkprofil-Modul (sequenzielle Parallel-Arbeit)

**[P01 · 17.05. 08:55]**

Lies in dieser Reihenfolge:
1. CLAUDE.md
2. docs/lam-parallel-arbeit.md
3. docs/lam-arbeitsstand.md
4. docs/Briefing_Linkprofil-Analyse_Claude-Code.md
5. MEMORY-Eintrag "Linkprofil-Analyse Briefing-Anpassungen"
   (linkprofil_analyse_entscheidungen.md) — enthaelt die im Vor-Chat
   getroffenen Entscheidungen zu Kunde-FK, eigener linkprofil_tags-
   Tabelle, Charts, herkunft-Spalten und UI-Wiederverwendung.

Wir arbeiten sequenziell, nicht parallel. Trotzdem vor jedem
Eingriff git pull / git log -5 checken, ob Chat A zwischenzeitlich
committet hat.

Bauplan steht im Vor-Chat in 6 Schritten:
  1. Migrationen + Models (additiv: verlinkungen, linkprofil_snapshots,
     linkprofil_snapshot_verlinkungen, linkprofil_tags,
     linkprofil_tag_verlinkung; +linkart/herkunft/herkunft_kunde_kuerzel
     an domains)
  2. Navigation (eine Zeile in LamLayout.vue), Route-Group am Ende
     routes/web.php, leere Linkprofil/Index.vue, DomainNormalisierer
  3. CSV-Import (Sistrix/AHREFs/XOVI/GSC) + Verlinkungs-Tabelle mit
     Filtern, gestalterisch und funktional an Linkquellen/Index.vue
     orientiert
  4. Einzel- + Massenbearbeitung, Tag-Autocomplete auf linkprofil_tags
  5. Snapshot-Mechanik mit Diff-Vergleich, Neu/Alt-Markierung
  6. Pool-Uebernahme + Excel-Export + Statistik (ohne Charts)

Jeder Schritt ein Commit. Beginn mit Plan-Mode fuer Schritt 1
(Datenmodell + Migrationen).

Reuse-Hinweise:
- app/Support/ExcelExport.php als Basis fuer LinkprofilExcelExport
- app/Support/Import/MappingService.php + ExcelLeser.php als Vorbild
  fuer CSV-Heuristik
- Linkquellen-Liste hat seit kurzem Pro-Seite-Dropdown, Fortschritts-
  Modal fuer Bulks, Filter "ohne SI" - alle Bausteine fuer die
  Verlinkungs-Tabelle wiederverwenden
- AuditLog::record() / recordBulk() fuer alle Aktionen

Wenn der Folge-Chat erneut in einem Worktree landet: settings.json
(global ~/.claude und projekt .claude) auf worktree-/isolation-
Eintrag pruefen, das war im Vor-Chat die Ursache.

---
