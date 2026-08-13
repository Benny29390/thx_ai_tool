# Priorisierter Umsetzungsplan — LAMS → KI-Tool

Vorlage: Tom legt Vorrang auf
1. **Optik / Corporate Design** (Header/Navigation, Tabellen)
2. **Workflow** (ursprünglicher Ablauf)
3. **Fehlende Funktionen**

Stilvorgaben: Deutsch, keine Gedankenstriche im UI-Text (em-Dash nur als
Platzhalter in Tabellen erlaubt), Höflichkeitsformen groß, feste Schreibweisen
(Thoxan, Sistrix, FRYKA, BKK Gildemeister Seidensticker).

---

## A · Optik / Corporate Design

### A1 — kritisch

| Nr | Aufgabe | Quellen | Aufwand |
|---|---|---|---|
| A1.1 | **Verifikations-Status-Konstante im Backend angleichen** an Frontend-Vokabular: `verifiziert` → `geprueft`, `verworfen` → `geloescht` in `LamService::VERIFIKATION_STATUS`. Sonst Inline-Edit löst InvalidArgument-Exception aus. | 03 ▸ 5, LamService.php:31 | S |
| A1.2 | **Farbige Status-Chips in Linkoptionen-Auswahl-Tab raus** — Anti-Muster 3. Status als Klartext-Button (Inline-Edit) mit unauffälligem Hover, statt grünen/roten Hintergrund. | 07, linkoptionen.php:421 | M |
| A1.3 | **SI-Cell-Farbklassen entfernen** (`si-fresh/si-mid/si-old/si-stale`). Tom explizit: „mir wird das sonst zu bunt". Einheitliche Schriftfarbe (slate-700), Schriftgröße. Letzter-Check-Datum klein als zweite Zeile, nicht als Tooltip. | 02 ▸ 10, 07 Anti-Muster 3, linkquellen.php:365 | M |
| A1.4 | **Linkquellen-Tabellen-Spalten** vereinheitlichen nach Briefing 02 + Styleguide: URL · Anbieter · Tags · SI/DP · Preis · Status · Kunden. Notiz-Bleistift verweist auf Detail. Schrift einheitlich groß. Spalten-Trenner subtil dezent (Styleguide-Pattern). | 02 ▸ 10, 03 ▸ 3, 07 Tabelle | L |
| A1.5 | **LAM-Footer einbauen**: „LAM · Linkaufbau-Management der Thoxan Communications GmbH · frischer wind im netz." | 07 Styleguide §8 | S |

### A2 — wichtig

| Nr | Aufgabe | Quellen | Aufwand |
|---|---|---|---|
| A2.1 | **Action-Buttons im Linkprofil-Header** auf vollständig prüfen: `Domain-Wissen · Snapshots · Statistik · Excel · URLs kopieren · Aufräumen · Historie importieren · + CSV importieren`. „URLs kopieren" + „Excel" fehlen aktuell. | 07 Styleguide §3.3 | M |
| A2.2 | **Linkquellen-Tabelle**: Anbieter-Spalte als „Betreiber-zuerst, dann günstigster Vermittler"-Logik (kompakte 2. Zeile mit Firma). | 02 ▸ 10, 03 ▸ 3 | M |
| A2.3 | **Tag-Auswahl in Linkquellen** als Standalone-Header-Button (neben „Linklisten-Import" und „+ Neue Domain"), nicht nur im Filterbereich versteckt. | 02 ▸ 8 | S |
| A2.4 | **lam.css → thx-\* Klassen-Migration** weiterführen, damit globale Standards greifen (Tom Datei 04 ▸ 6: „Standards nicht global"). | CLAUDE.md, 04 ▸ 6 | L |
| A2.5 | **Linkquellen-Detail-Layout** nach Briefing 03 ▸ 4 umbauen: Aktionen direkt unter dem Header (verifizieren/veraltet/verwerfen + Cluster + KI-Recherche), darunter Block Anbieter-Kontakte-Konditionen (3-spaltig), darunter Kunden + Aktivitäten in Grid. „Neu"-Knöpfe überall für flexibles Verlinken. | 03 ▸ 4 | L |
| A2.6 | **Default Thoxan-Logo** sicherstellen: Wenn kein app_logo gesetzt, das Original-Logo aus `/assets/images/thoxan-logo.svg` ausliefern statt Text-Fallback. | 02 ▸ 3 | S |

### A3 — nice-to-have

| Nr | Aufgabe | Quellen | Aufwand |
|---|---|---|---|
| A3.1 | Kanban-Höhe 100% mit horizontalem Scroll bis zum Footer durchziehen | 02 ▸ 4 | S |
| A3.2 | Tabellen in Linkprofil-Aufräumen prüfen ob Spalten umbrechen oder abgeschnitten | 04 ▸ 7 | S |
| A3.3 | Anbieter-Detail-View Layout: ist-Stand passt — Polierung nach Tom-Feedback | 03 ▸ 4 | S |
| A3.4 | UI-Fließtext stichprobenartig prüfen ob Gedankenstriche reingerutscht sind | Stilvorgabe | S |

---

## B · Workflow

### B1 — kritisch

| Nr | Aufgabe | Quellen | Aufwand |
|---|---|---|---|
| B1.1 | **Hauptkontakt-Wechsel propagiert Anbieter-Namen** (Anforderung 02 ▸ 7): Wenn `prioritaet=1` auf Kontakt B wechselt, Anbieter-Name auf „Vorname Nachname" von B aktualisieren. Aktuell nur `prioritaet`-Tausch in `setzePrimaerKontakt` (LamService.php:1270). | 02 ▸ 7 | M |
| B1.2 | **Linkquellen-Detail Aktivitäten-Block** aktivieren: `neueMassnahmeStub()` und `aufnehmenLinkoptionStub()` durch echte Funktionen ersetzen (Modal mit Kunde+Linkziel-Auswahl). | 03 ▸ 4, linkquellen-detail.php:1279 | M |
| B1.3 | **Linkpool-Auto-In-Arbeit-Status**: Beim Hinzufügen einer Domain zum Linkpool (`lam_domain_customer`) Status automatisch auf `in_arbeit` setzen, falls aktuell `neu`/`veraltet`. Aktuell nur über `toggleKundeFuerDomain`, nicht über frische `linkpool-add`. | 03 ▸ 3 | S |

### B2 — wichtig

| Nr | Aufgabe | Quellen | Aufwand |
|---|---|---|---|
| B2.1 | **Sticky-Filter pro Modul** komplett (nicht nur Kunde + Tab): Linkquellen + Linkprofil + Linkoptionen merken Filter-Konfig in localStorage. Filter-Card immer offen / offen-Memory. | 03 ▸ 5, 03 ▸ 12 | M |
| B2.2 | **Linkprofil-Bulk-Pipeline als Kette**: 1-Klick-Workflow Erreichbarkeit → SI → Linkart-Wissen → KI-Linkart → KI-Empfehlung als Kombi-Button mit Fortschritts-Modal. | 03 ▸ 10, 04 ▸ 4 | L |
| B2.3 | **Einheitliche Filter-Rahmen** über Linkprofil/Linkquellen/Linkoptionen (Tom: „nicht aus einem Guss"). Gemeinsame `lam-filter-card`-Komponente mit harmonisierten Inputs/Chips/Range-Inputs. | 03 ▸ 12 | M |
| B2.4 | **Duplikat-Anreicherung beim Linkquellen-Import** statt Skip: wenn URL existiert, Notiz/Historie ergänzen statt verwerfen. | 02 ▸ 11, LamService::importiereLinkquellen | M |
| B2.5 | **Wechsel des Hauptkontakts mit Re-Naming** (B1.1) plus UI-Feedback („Anbieter-Name aktualisiert auf …"). | 02 ▸ 7 | siehe B1.1 |
| B2.6 | **Linkquelle-aus-Linkprofil-Übertrag** mit Kunden-Verknüpfung + Deeplink-Erhalt (auch für Monitoring-Slot). | 04 ▸ 8 | M |
| B2.7 | **Linkprofil-Aufräumen Default „klare zuerst akzeptieren, unsichere prüfen"**-Sortierung. | 04 ▸ 7 | S |
| B2.8 | **„Anwenden"-Button im Domain-Wissen** muss alle Konflikt-Vorkommen aktualisieren, nicht nur das lokale. | 04 ▸ 5 | M |

### B3 — nice-to-have

| Nr | Aufgabe | Quellen | Aufwand |
|---|---|---|---|
| B3.1 | „Multifilter-Ergebnisse als URL-Liste in Zwischenablage" — Copy-Button im Linkprofil-Header | 03 ▸ 10 | S |
| B3.2 | Auslagen-Filter: prüfen ob Quartal-Filter stört (Tom: „kein Quartal nötig, Jahr+Monat reicht") | 02 ▸ 6 | S |
| B3.3 | Excel-Export-Felder mit Tom abstimmen: aktueller Header weicht von Briefing 02 ▸ 13 ab (Cluster/Anbieter/Themengebiet drin → laut Anforderung sollen weg) | 03 ▸ 13 | S Diskussion |
| B3.4 | Asana-Ticket aus Linkoption: „vorhandenes verbinden"-Flow prüfen (neben „neu anlegen") | 03 ▸ 13 | S |

---

## C · Fehlende Funktionen

### C1 — kritisch

| Nr | Funktion | Quellen | Aufwand |
|---|---|---|---|
| C1.1 | **KI-Cluster-Vorschlag-Button** (`clusterStub`) durch echte Implementierung ersetzen (Top-Tags aus Domain-Inhalt). | 02 ▸ 2, migration-status.md #7 | M |

### C2 — wichtig

| Nr | Funktion | Quellen | Aufwand |
|---|---|---|---|
| C2.1 | **SMTP-Postfach-Integration für Korrespondenz** (Inbound + Outbound, Anbindung an `lam_kommunikation`). Tom explizit als zentral formuliert (Datei 02 ▸ 12). | 02 ▸ 12 | XL (eigenes Projekt) |
| C2.2 | **KI-Lernschleife aus historischen Linkprofil-Excels**: Importer für „bisherige Linkprofil-Analysen" als Trainingsmaterial fürs Domain-Wissen. | 04 ▸ 5 | L |
| C2.3 | **Wöchentlicher Sistrix-Credit-Reset-Cron** (Montag 00:00 auf 20.000). Aktuell scheinbar nur manuell. | 03 ▸ 7 | S |
| C2.4 | **Multi-Upload für Linkprofil-CSV** (xovi + GSC + Sistrix in einem Schritt). | 04 ▸ 3 | S |
| C2.5 | **Historien-Importer** für alte Veröffentlichungen (eml/xlsx) als eigener Pfad, getrennt vom Portfolio-Importer. | 02 ▸ 13 | M |
| C2.6 | **Empfehlungs-Vokabular-Status** verifizieren: laut migration-status.md noch offen, im Code aber drin. Status-Dokumentation aktualisieren oder lookup-Konsistenz prüfen. | 04 ▸ 4 | S |

### C3 — nice-to-have

| Nr | Funktion | Quellen | Aufwand |
|---|---|---|---|
| C3.1 | „Fehlende Felder"-Filter im Linkprofil (Deeplink fehlt, Ankertext fehlt) | 03 ▸ 10 | S |
| C3.2 | „Abgerechnet für"-Detail bei Auslagen prüfen + ggf. ergänzen | 02 ▸ 13 | S |
| C3.3 | Inline-SI/DP-Refresh-Button direkt in Linkquellen-Zelle (statt nur über Bulk) | 02 ▸ 10 | S |

---

## Vorgeschlagene Reihenfolge

**Sprint 1 — Optik-Kern (Tom's Hauptanliegen):**
A1.1, A1.2, A1.3, A1.5, A2.1, A2.6 — ergibt das einheitliche LAM-Gefühl wieder.

**Sprint 2 — Workflow-Kerne:**
B1.1, B1.2, B1.3, B2.4 — ursprünglicher Workflow lückenlos.

**Sprint 3 — Tabellen + Detail-View:**
A1.4, A2.2, A2.3, A2.5 — Tabellen-Optik und Linkquellen-Detail nach Briefing.

**Sprint 4 — Workflow-Polierung:**
B2.1, B2.3, B2.6, B2.7, B2.8 — Sticky-Filter, einheitlicher Rahmen, Aufräumen-Sortierung, Konflikt-Anwenden.

**Sprint 5 — Pipeline + KI:**
B2.2, C1.1, C2.2 — Linkprofil-Pipeline-Workflow + Cluster-Vorschlag + Lernschleife.

**Sprint 6 — Lücken:**
C2.3, C2.4, C2.5, B3.1-B3.4, A3.1-A3.4 — Restpunkte.

**Eigenes Projekt:** C2.1 (SMTP-Postfach) — Größenordnung und Risiko rechtfertigen separaten Sprint.

---

## Konsolidierte Befundliste (Top-Punkte zur Diskussion)

1. **A1.1 ist ein Bug**: Backend lehnt aktuelle Frontend-Status-Werte ab → Inline-Edit kann crashen.
2. **A1.2 + A1.3 Anti-Muster aus Styleguide**: Bunte Status-Chips/SI-Cell sind explizit nicht das LAM-Idiom.
3. **B1.1 ist ein konkretes Workflow-Loch**: Hauptkontakt-Wechsel propagiert nicht ins Anbieter-Profil.
4. **B1.2 sind Stubs**: „Neue Maßnahme aus Linkquelle" und „Auf Linkoption-Liste" sind nur `alert()`-Platzhalter.
5. **C2.1 ist eine eigene Größenordnung**: SMTP-Postfach fehlt komplett, müsste als separates Projekt geplant werden.
6. **B3.3 ist ein Konflikt zwischen Anforderungen**: Excel-Export-Layout für Vorschlagslisten orientiert sich an `VID_Linkquellen_Final.xlsx` (Diskussion mit Tom), aber Briefing 03 ▸ 13 fordert reduzierte Spalten ohne Anbieter. → Klären welches gilt.
