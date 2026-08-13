# ABGLEICH · LAMS → KI-Tool Migration

Dieses Dokument hält fest, was im ursprünglichen LAMS gefordert/entschieden war
und was im migrierten KI-Tool tatsächlich existiert. Pro Sinnabschnitt der
nummerierten Quelldateien (`01..08`) ein Eintrag nach dieser Vorlage.

```
Datei / Abschnitt: <01..08> · ▸ <abschnitt>
Anforderung:       <was Tom wollte, letzter Stand>
Ist im KI-Tool:    vorhanden | teilweise | fehlt — Fundstelle: <pfad:zeile>
Abweichung/offen:  <konkret>
Priorität:         hoch | mittel | niedrig
Kategorie:         Optik | Workflow | fehlende Funktion
```

Notation: Schwerpunkte vom User sind **Optik/Corporate Design** (Header/Navigation,
Tabellen — s. 07/08) und **Workflow** (ursprünglicher Ablauf). Stilvorgaben
beachten: Deutsch, keine Gedankenstriche, Höflichkeitsformen groß.

---

## Datei 01 · Spec-Zusammenfassung

### 01 · ▸ 1. Spezifikation verstehen, Stack-Entscheidung & Crawling-/Credit-Strategie

```
Anforderung:       Laravel-Stack (Benny arbeitet sonst mit Laravel),
                   Sistrix-Credits sparsam einsetzen (kein automatisches Crawling,
                   nur Auswahl in Chargen, Pool ohne Auto-Aktualisierung),
                   Bulk-Audit-Einträge zusammengefasst protokollieren,
                   Anleitung zur Spezifikation mit verarbeiten.
Ist im KI-Tool:    teilweise — Sistrix-Manuell + Bulk + Pre-Confirm:
                   services/SistrixService.php, api/v1/lam/sistrix-*.php,
                   views/lam/linkquellen.php:967 (Pre-Confirm-Modal),
                   Kontingent-Pre-Check vorhanden (LamService.php:5751).
                   Bulk-Audit konsolidiert (LamService.php:164 mit ist_bulk + anzahl_betroffen).
                   Pool ohne Auto-Aktualisierung gegeben (manuelle Bulk-Aktionen).
Abweichung/offen:  Stack-Wechsel Laravel → PHP/Vanilla bewusste Migrations-Entscheidung
                   (siehe 06+07), keine echte Anforderungs-Abweichung.
                   Erreichbarkeits-Cron läuft 1× täglich (siehe CLAUDE.md), passt
                   zur Sparsam-Strategie.
Priorität:         niedrig (alles Wesentliche abgedeckt)
Kategorie:         Workflow / fehlende Funktion
```

---

## Datei 02 · Phase A (Pool-Grundgerüst, Workflow, Sistrix, eml-Case, Briefings 01-03)

### 02 · ▸ 1. Phase-A-Planung (Mandantenfähigkeit, Soft-Delete, Audit-Log)

```
Anforderung:       Mandantenfähigkeit + Soft-Delete + Audit-Log gehören schon in
                   Phase A, nicht später nachgerüstet.
Ist im KI-Tool:    vorhanden — mandant_id in lam_* Tabellen, geloescht_am-Spalten
                   überall (Domains, Anbieter, Kontakte etc.), lam_audit_logs
                   mit Bulk-Konsolidierung (LamService.php:164).
Abweichung/offen:  keine
Priorität:         —
```

### 02 · ▸ 2. Tags statt fester Cluster, KI-Vorschlag

```
Anforderung:       Cluster global, KI-gestützt vorgeschlagen, mit Tags kombinierbar
                   statt fester Bezeichnungen.
Ist im KI-Tool:    teilweise — lam_tags + lam_domain_tag vorhanden, Mehrfach-Tags
                   pro Domain, Tag-Filter im Pool. KI-Vorschlag für Tags
                   („Cluster zuordnung und vorschlag neuer tags funktioniert"
                   laut P32) ist vorhanden im Linkprofil-Klassifikator
                   (LamService.php:4151 fragKiNachKlassifikation).
Abweichung/offen:  KI-Cluster-Vorschlag direkt aus Linkquellen-Detail heraus auf
                   Knopfdruck (vermutlich der „Cluster"-Button in Aktionen-Bar)
                   ist als Stub markiert in migration-status.md Punkt 7
                   (clusterStub → Alert).
Priorität:         mittel
Kategorie:         fehlende Funktion
```

### 02 · ▸ 3. Schriften 120% + Logo größer ohne Titel + Viewport 1920

```
Anforderung:       Schriften auf 120% global, voller Browser-Breite, größerer
                   Viewport (1920px), Logo oben groß, Titel weglassen
                   (User weiß wo er ist).
Ist im KI-Tool:    vorhanden — assets/css/thx-tokens.css:279 html { font-size:120% }
                   global; views/layouts/main.php:282-288 Logo via app_logo-Setting,
                   sonst Fallback auf app_name-Text. Wenn Logo gesetzt ist,
                   wird der Title unterdrückt.
Abweichung/offen:  Default-Fallback zeigt App-Name als Text-Logo. Wenn Logo nicht
                   konfiguriert, dann erscheint der Text. Prüfen ob das Thoxan-Logo
                   per Default geladen wird (CLAUDE.md: /assets/images/thoxan-logo.svg).
Priorität:         niedrig
Kategorie:         Optik
```

### 02 · ▸ 4. Dashboard-first, Kanban schiebbar, dickere Top-Bar mit Kunden/Einstellungen/User

```
Anforderung:       Dashboard als Einstieg (nicht Pool), Kanban mit Drag-and-Drop,
                   immer 100% Höhe horizontaler Scroll, Logo verlinkt auf
                   Dashboard, Top-Bar in Thoxan-Blau mit Kunden/Einstellungen/User.
Ist im KI-Tool:    vorhanden — Dashboard ist erster Modul-Tab. Top-Bar
                   (thx-topbar) vorhanden mit Kunden/Einstellungen/User/Abmelden.
                   Kanban auf /lam/massnahmen/kanban mit Drag-and-Drop (CLAUDE.md).
Abweichung/offen:  100%-Höhe-Scroll im Kanban war wiederkehrendes Thema (P28, P29,
                   P65). Aktueller Stand unklar — Kanban-View testen.
Priorität:         mittel
Kategorie:         Optik
```

### 02 · ▸ 5. Sistrix: SI mit 4 Nachkommastellen, DP, sichtbar-seit-Datum als Alter

```
Anforderung:       SI mit bis zu 4 Nachkommastellen, DP separat abrufbar,
                   „sichtbar seit Datum" als Alter-Wert (nicht Domain-Registrierung),
                   Credits sparsam, Pre-Confirm-Modal mit Kontingent-Check.
Ist im KI-Tool:    vorhanden — Sistrix-Service liefert si, dp, sichtbar_seit;
                   SI-Format 0.0000 in Linkquellen-Tabelle und Excel-Export;
                   Pre-Confirm-Modal mit Kontingent (linkquellen.php:967).
                   Bulk-Kosten-Aufschlüsselung SI/Alter/DP/Alles vorhanden.
Abweichung/offen:  keine
Priorität:         —
```

### 02 · ▸ 6. Pool vs. Akquise-Pipeline, Quartal-Filter, Auslagen Phase D

```
Anforderung:       Klare Trennung Pool (alle Linkquellen) vs. Akquise (nur
                   ausgewählte Vorschlagslisten). Auslagen filterbar nach Jahr/Monat
                   (kein Quartal nötig), Kürzel statt Kundenname in Tabelle,
                   Domain + Ansprechpartner prominent.
Ist im KI-Tool:    vorhanden — Linkquellen (Pool) + Linkakquise + Linkoptionen +
                   Auslagen alle vorhanden. Auslagen mit Filter Jahr/Quartal
                   (CLAUDE.md: „inkl. Quartal").
Abweichung/offen:  Tom sagte ausdrücklich „Quartal nicht nötig". CLAUDE.md sagt
                   „inkl. Quartal" — sollte geprüft werden ob das stört oder ok.
                   Kürzel statt Kundenname in Tabelle: prüfen.
Priorität:         niedrig
Kategorie:         Workflow / Optik
```

### 02 · ▸ 7. eml-Case: Anbieter = Hauptkontakt-Person, Firma als Firmierung, Mehrfach-Ansprechpartner, Vermittler-Flag

```
Anforderung:       Anbieter = oberster Kontakt (Person), Firma getrennt als
                   Firmierung. Mehrere Ansprechpartner mit Prioritäts-Reihenfolge.
                   Wechsel des Hauptkontakts → Anbietername wechselt mit.
                   URL darf bei mehreren Anbietern hängen (v.a. Vermittler).
                   Vermittler vs. Betreiber als FLAGS, nicht globale Trennung.
                   Vermittler haben hunderte Domains → Filter im Pool, nicht
                   Liste. Suchfelder statt Dropdowns bei langen Listen.
                   Anbieter-Detail aufgeklappt, nicht nur Tabs/Dropdown.
Ist im KI-Tool:    teilweise — Datenmodell mit ist_betreiber + ist_vermittler-
                   Flags vorhanden (lam_anbieter), Junction lam_domain_anbieter
                   mit Junction-Flags (ist_betreiber + ist_vermittler + position)
                   ergänzt. Anbieter-Detail-View mit Kontakten und
                   ★-Primaer-Switch (anbieter-detail.php:170).
                   Kürzlich: crawleAnbieterAusImpressum legt Anbieter-Name als
                   Person an + Firma separat.
                   Anbieter-Picker als Suchfeld (linkquellen-detail.php) ist neu.
Abweichung/offen:  Wechsel des Hauptkontakts setzt nur prioritaet=1, aktualisiert
                   aber NICHT den Anbieter-Namen (LamService.php:1270
                   setzePrimaerKontakt). Anforderung war:
                   „wenn anna müller die 1. ansprechpartnerin ist, wird auch
                    der anbieter zu Anna Müller von Bantle Media".
                   Plus: Bei lam_kontakte erlaubt das aktuelle Modell keine
                   Mehrfach-Zuordnung eines Kontakts zu mehreren Anbietern.
Priorität:         hoch
Kategorie:         Workflow
```

### 02 · ▸ 8. Großes Umbenennen + Menüstruktur + Kontaktimport-Reihenfolge

```
Anforderung:       Pool → Linkquellen, Vorschlagslisten → Linkoptionen,
                   Akquise-Pipeline → Linkakquise, Alerts → Monitoring (jeweils
                   URL + Label). Anbieter ins Hauptmenü, Kontaktimport neben
                   „Neuer Anbieter". Kontakt-Import-Reihenfolge IMMER:
                   eml > Impressum > Signatur. Impressum auto-crawlen.
                   Suchfelder statt Dropdowns.
Ist im KI-Tool:    teilweise — alle Umbenennungen sind durch
                   (Linkquellen, Linkoptionen, Linkakquise, Monitoring jeweils
                   URL + UI-Label). Impressum-Crawl gerade frisch verdrahtet
                   (anbieter-aus-impressum + lq-detail Button funktioniert).
                   Anbieter-Picker und Asana-Picker als Suchfeld umgesetzt.
Abweichung/offen:  Reihenfolge eml > Impressum > Signatur als Default beim
                   Kontakt-Anlegen ist nicht konsequent durchgesetzt (manueller
                   Import-Flow hat keinen klaren Default-Pfad).
                   Weitere alte Dropdowns (Tag-Auswahl etc.) sollten auf
                   Type-Ahead umgestellt werden — Audit nötig.
Priorität:         mittel
Kategorie:         Workflow / Optik
```

### 02 · ▸ 9. Dashboard-Widgets, Kanban zu Maßnahmen, Briefing 01

```
Anforderung:       Dashboard-Widgets flexibel + editierbar (offene Aufgaben,
                   Links etc.). Kanban gehört zu „Maßnahmen" mit Toggle
                   Liste/Kanban, Default Kanban links, sticky letzte Ansicht
                   pro User. Excel-Export in beiden Ansichten.
Ist im KI-Tool:    teilweise — Dashboard-View existiert (views/lam/dashboard.php),
                   Kanban auf /lam/massnahmen/kanban + Liste auf
                   /lam/massnahmen vorhanden. Excel-Export für Maßnahmen
                   vorhanden (massnahmen-export.php).
Abweichung/offen:  „Editierbare Widgets" mit Drag/Resize/Remove → Customer-
                   Steckbrief hat layout-edit-toggle (cs-layout-edit-toggle,
                   customer-steckbrief.php:2194). Im LAM-Dashboard fehlt das
                   bislang. Toggle Liste/Kanban mit sticky-Memory: prüfen ob
                   localStorage drin.
Priorität:         mittel
Kategorie:         fehlende Funktion / Optik
```

### 02 · ▸ 10. Briefing 02 Linkquellen-Pool (feste Spalten, Inline-Edit, Filter horizontal)

```
Anforderung:       Feste Spalten: URL (klickbar extern + Detail), SI/DP/Alter
                   (mit letztem Check klein darunter), Anbieter/Firma (Betreiber
                   vor Vermittler, günstigster Vermittler), Preis (ab/niedrigster),
                   Status, Notiz. Schriften einheitlich, keine bunten SI/DP-Farben.
                   Inline-Bearbeitung in der Liste für Anbieter, Tags, SI/DP-Refresh,
                   Preis, Status. Filter horizontal oben, Sortierung der Spalten
                   aufsteigend/absteigend.
Ist im KI-Tool:    teilweise — Filter horizontal oben (lam-filter-card), Spalten
                   sortierbar nach erstellt_am, url, verifikation_status,
                   letzter_check_am, anbieter. Inline-Edit für Verifikation,
                   Anbieter, Linkart, Tags, Disqualifizieren (thx-inline-edit).
                   SI-Cell-Farbklassen (si-fresh/si-mid/si-old/si-stale) sind
                   verwendet — Tom wollte aber „mir wird das sonst zu bunt".
                   Spalten aktuell: URL, Anbieter, Tags, Linkart, SI/DP, Preis,
                   Status, Kunden. „Letzter Check klein darunter" als Tooltip
                   (linkquellen.php:363 :title), nicht als zweite Zeile sichtbar.
Abweichung/offen:  - SI/DP-Farbklassen sollten entfernt werden (Tom: keine
                     bunten Farben, einheitliche Schrift)
                   - „Letzter Check klein darunter" als sichtbare zweite Zeile
                     in der Tabelle, nicht nur Tooltip
                   - Anbieter/Firma als zweizeilige Spalte mit Betreiber-vor-
                     Vermittler-Sortierung
                   - Preis als „ab X €" wenn mehrere Konditionen
                   - Inline-SI/DP-Refresh-Button (gerader Symbol-Klick auf
                     Zelle, anstatt Bulk-Toolbar)
                   - Notiz-Spalte (aktuell nicht direkt sichtbar)
                   - Bleistift-Icon soll auf Detail/Notiz verweisen (P71)
Priorität:         hoch (Tabellen-Optik ist der Hauptpunkt aus 07/08)
Kategorie:         Optik / Workflow
```

### 02 · ▸ 11. Import mit Kontext + KI-Anreicherung + Duplikatsbehandlung

```
Anforderung:       Beim Upload Kontext in Stichworten eingeben → KI macht
                   Vorschlag zur Anreicherung (Tags, Anbieter-Zuordnung, Status).
                   Duplikate beim Re-Import nicht als Fehler abweisen, sondern
                   vorhandene Linkquellen um die mitgeteilten Infos
                   anreichern (extranotiz/historie).
Ist im KI-Tool:    teilweise — Portfolio-Importer hat user_context + KI-Analyse +
                   Vorschlag (PortfolioImportService.php). Beim Linkquellen-
                   Import (linkquellen-import-commit) werden Dubletten aber
                   noch ÜBERSPRUNGEN, nicht angereichert.
                   Beim Artefakt-Import gibt es user_context, das passt für
                   ChatGPT-Kontext, ist aber nicht der LAM-Import.
Abweichung/offen:  Duplikat-Anreicherung beim Linkquellen-Import fehlt
                   (LamService::importiereLinkquellen, aktuell nur dubletten++).
                   Kontextfeld + KI-Anreicherung beim Linkquellen-Import fehlt.
Priorität:         mittel
Kategorie:         fehlende Funktion
```

### 02 · ▸ 12. Korrespondenz-Modul + Einstellungen + SMTP-Postfach

```
Anforderung:       Korrespondenz als eigener Menüpunkt mit zentraler Verwaltung
                   + E-Mail-Postfach via SMTP. Einstellungen oben in Top-Bar.
                   Überlegt: Korrespondenz + Linkakquise zusammenfassen.
Ist im KI-Tool:    teilweise — /lam/korrespondenz existiert als Menüpunkt mit
                   Liste von lam_kommunikation (laut CLAUDE.md). Einstellungen
                   sind in Top-Bar.
Abweichung/offen:  SMTP-Postfach-Integration FEHLT — CLAUDE.md: „Mail-Integration
                   (IMAP/SMTP) — separates globales Thema außerhalb LAM".
                   Tom hat das aber als zentral für Korrespondenz formuliert.
                   Zusammenführen Korrespondenz + Linkakquise: bewusst nicht
                   passiert, sind getrennte Tabs.
Priorität:         mittel
Kategorie:         fehlende Funktion
```

### 02 · ▸ 13. Historien-Import (eml/xlsx, kein PDF) + Maßnahme-Details + Asana

```
Anforderung:       Upload-Tool für eml/xlsx (PDF nicht nötig, copy/paste reicht)
                   für historische Veröffentlichungen mit Feldern URL, Anbieter,
                   Linktext, Linkziel, online ja/nein, Preis (Cent-genau),
                   Notiz/Kontext. Plus „abgerechnet für" Detail bei Auslagen.
                   Asana-Anbindung kommt noch.
Ist im KI-Tool:    teilweise — Portfolio-Importer macht eml/msg/xlsx/csv und
                   jetzt auch pdf (frisch ergänzt, Tom wollte das eigentlich
                   nicht — pdf neu hinzugefügt war richtig für Mediadaten-PDFs).
                   Maßnahmen-Detail mit Auslage, Asana-Konfig pro Kunde frisch
                   verdrahtet.
                   Preis cent-genau: lam_konditionen.preis ist DECIMAL.
Abweichung/offen:  „Abgerechnet für"-Feld bei Auslagen: prüfen ob in
                   lam_auslagen vorhanden + im Anlege-Formular.
                   Historischer Bulk-Upload für „alte Veröffentlichungen" als
                   schneller Massen-Importer ist nicht erkennbar als eigener
                   Pfad — der Portfolio-Importer ist für laufende Mediadaten
                   gedacht.
Priorität:         mittel
Kategorie:         fehlende Funktion
```

---

## Datei 03 · Asana, Linkquellen-Pool & Linkprofil-Merge (14 Sinnabschnitte)

### 03 · ▸ 1. Asana-Anbindung (Briefing 05): KI-Felder, Asana-Ticket fix

```
Anforderung:       KI verarbeitet Asana-Task-Inhalte → in passende Felder
                   (Linkquelle, Anbieter, Preis weiterberechnet an Kunde,
                   Linkziel, Linktext, Thema, Notizen). Veröffentlichungs-URL
                   kommt erst später in Kommentaren. Asana-Ticket ist FIX
                   (nicht ändern), nur verbinden + auslesen. Erledigt-Spalten-
                   Tickets auch zugänglich.
Ist im KI-Tool:    teilweise — asana-extrahieren.php + asana-uebernehmen.php
                   vorhanden, KI-Extraktion läuft, mapping auf Maßnahmen-
                   Felder. Asana-Sync zieht inkl. erledigt-Spalte.
                   Sync-Job vorhanden (siehe Customer-Steckbrief Asana-Block).
Abweichung/offen:  CLAUDE.md: „Phase 2 (Push LAM → Asana, Webhook-Rück-Sync,
                   Kommentar-Spiegelung) bewusst offen". Tom wollte: Asana-
                   Ticket bleibt fix, nur auslesen + Felder befüllen. Das passt
                   zur Phase-1-Implementierung. Push (Ändern) war nicht gewollt.
                   → keine echte Abweichung.
Priorität:         niedrig
```

### 03 · ▸ 2. API-Keys in Einstellungen + Asana-Board-Auswahl als Suchfeld

```
Anforderung:       API-Keys per Settings-UI statt .env. Asana-Board-Auswahl
                   pro Kunde als Suchfeld-Dropdown (nicht statisches Dropdown).
Ist im KI-Tool:    vorhanden — Settings-UI für API-Keys (admin/settings),
                   alle Secrets AES-256-GCM verschlüsselt (CLAUDE.md).
                   Asana-Board-Auswahl pro Kunde im Customer-Steckbrief
                   („LAM-Sync"-Button → Modal mit Such-Dropdown, frisch gebaut).
Abweichung/offen:  keine
Priorität:         —
```

### 03 · ▸ 3. Linkquellen: Massenbearbeitung, Filter mit Shift/Ctrl, Tabellen-Layout

```
Anforderung:       Mehrfachauswahl + Bulk-Aktionen + Kontextmenü Rechtsklick.
                   Filter mit Shift/Ctrl statt einzelnem An/Ab. Bearbeitungs-
                   Felder einheitlich, alles linksbündig, Bearbeitungs-Popups
                   immer rechts öffnen (URL/Vermittler/DP fett). Linkquellen
                   pro Kunde zuordbar (Tags wie BKK/SMV/VID). Status-Spalte
                   schmaler. Preis-Unterzeile mit Gastartikel/via... raus.
                   Status automatisch in_arbeit beim Setzen eines Kunden
                   (bzw. verifiziert bleibt grün).
Ist im KI-Tool:    teilweise — Bulk-Toolbar + Kontextmenü Rechtsklick
                   (linkquellen.php). Filter mit Shift/Ctrl umgesetzt
                   (Linkart-/Verifikation-Chip-Reihen, „Klick = nur dieser ·
                   Shift = mehrere"). Kunden-Zuordnung über Linkpool
                   (lam_domain_customer). Inline-Edit für Status/Anbieter/Tags.
Abweichung/offen:  - „Status automatisch in_arbeit beim Setzen eines Kunden"
                     teilweise vorhanden (LamService::toggleKundeFuerDomain
                     setzt neu/veraltet → in_arbeit). Aber: bei manuellem
                     Hinzufügen zum Linkpool via UI/Import nicht garantiert.
                   - URL/Anbieter/DP fett: prüfen ob konsistent
                   - Preis-Unterzeile: prüfen
                   - Linkpool als Filter ist da, Kunden-Spalte ist sortierbar
                     (frisch) — Tom wollte alphabetisch innerhalb des Kürzels.
Priorität:         mittel
Kategorie:         Optik / Workflow
```

### 03 · ▸ 4. Linkquellen-Detail-Layout (Hauptanforderung!)

```
Anforderung:       OBEN LINKS: wichtigste Infos + Tags. Darunter Buttons für
                   alle wichtigen Funktionen (verifizieren/veraltet/verwerfen
                   mit drin = ALLE Knöpfe an einer Stelle).
                   OBEN RECHTS: Kurzbeschreibung (KI-generierbar aus
                   Startseite/Über-uns).
                   Darunter Notizenbereich (1-spaltig, weiter nach unten).
                   LINKS UNTEN (Grid): Anbieter + Kontakte + Ermitteln (Block).
                   RECHTS DANEBEN: Konditionen (verschiedene Preise/Modelle,
                   Mediadaten-Anhang).
                   UNTERHALB LINKS: Kunden (zuweisen!), RECHTS: Aktivitäten
                   (Maßnahmen neu anlegen!).
                   „Neu"-Button überall für flexible Verknüpfung.
                   Domains optisch separieren. Mediadaten als Beispiellinks /
                   Mediadaten-Überschriften mit weiteren Linkfeldern.
Ist im KI-Tool:    teilweise — linkquellen-detail.php existiert mit:
                   ✓ Aktionen-Bar (Aktionen-Buttons)
                   ✓ Anbieter+Kontakte-Block mit Impressum-Crawl
                   ✓ Konditionen-Block mit „neu"
                   ✓ Externe Links (für Beispiellinks/Mediadaten)
                   ✓ Kurzbeschreibung mit KI-Generierung
                   ✓ Notiz-Feld
                   ✓ Kunden-Block (Kunden zuweisen)
                   teilweise vorhanden:
                   ◐ Aktivitäten/Maßnahmen-Block direkt aus Detail
                     („Maßnahme neu anlegen"-Knopf war Stub
                     neueMassnahmeStub in linkquellen-detail.php:1279)
Abweichung/offen:  - neueMassnahmeStub() ist noch ein Alert — direkte
                     Maßnahme-Anlage aus Linkquellen-Detail fehlt
                   - aufnehmenLinkoptionStub() ähnlich — Aufnahme in
                     Linkoption-Liste aus Detail fehlt
                   - Layout-Reihenfolge gegen Briefing (User-Anforderung war
                     ganz klar das oben skizzierte Layout)
                   - Mediadaten-Slot prüfen (lam_domain_links existiert,
                     UI vermutlich da)
Priorität:         hoch
Kategorie:         Workflow / fehlende Funktion / Optik
```

### 03 · ▸ 5. Briefing 01b Pipeline-Status (Neu/In Arbeit/Geprüft/Veraltet/Gelöscht)

```
Anforderung:       Verifikations-Status auf 5 Werte:
                   Neu / In Arbeit / Geprüft / Veraltet / Gelöscht.
                   Mehrfachfilter (z.B. ohne Kunden UND in_arbeit UND SI-Range).
                   Filter sticky pro User, Filterbereich offen bleibt bis User
                   ihn schließt. Anbieter/Vermittler-Trennung weg (redundant).
                   Tags mehrzeilig (2-3) bei vielen Tags.
                   Felder/Checkboxen/Dropdowns/Buttons einheitlich groß +
                   gleiche Schriftart.
Ist im KI-Tool:    teilweise — Frontend nutzt
                   ['neu', 'in_arbeit', 'geprueft', 'veraltet', 'geloescht']
                   (linkquellen.php) ENTSPRICHT Anforderung.
                   ABER: PHP-Service-Konstante VERIFIKATION_STATUS hat noch
                   ['neu', 'in_arbeit', 'verifiziert', 'veraltet', 'verworfen']
                   (LamService.php:31) — Inkonsistenz Frontend ↔ Backend.
                   Mehrfachfilter: ja (verifikation_status[] Array).
                   Sticky-Filter über localStorage: vorhanden für customer_id,
                   tab — andere Filter nicht.
                   Anbieter/Vermittler-Trennung: bewusst geblieben als Flag,
                   passt zur Anforderung (Flags statt globaler Trennung).
                   Tags 2-3-zeilig: prüfen Layout in linkquellen.php.
Abweichung/offen:  - Backend-Konstante VERIFIKATION_STATUS dringend angleichen
                     an Frontend-Vokabular ('geprueft', 'geloescht'). Sonst
                     droht Invalid-Argument-Exception beim Inline-Edit.
                   - Sticky-Filter für komplette Filter-Konfig in Linkquellen
                     fehlt (nur customer_id ist sticky)
                   - Filter-Card immer offen halten (collapse-Memory)
Priorität:         hoch
Kategorie:         Workflow / fehlende Funktion
```

### 03 · ▸ 6. Projekt-Vollsicherung

```
Anforderung:       Komplettsicherung als tar.gz im Root.
Ist im KI-Tool:    n/a — Backup-Aufgabe, kein Migrationspunkt.
Priorität:         —
```

### 03 · ▸ 7. Sistrix-Kosten: 4 Varianten, Bulk, Credits, Rate-Limit, Erreichbarkeit-Bulk

```
Anforderung:       Sistrix in 4 Varianten: nur SI / nur DP / nur Alter / Alles.
                   Bulk-Variante für alle 4 (Massenprüfung 100/500 Domains).
                   Wöchentlicher Credit-Reset Montag 00:00 auf 20.000.
                   Erreichbarkeits-Bulk als billige Vorprüfung (1 Cent/Stk).
                   Pagination 50/100/250/500.
                   Filter „ohne SI" (auch „ohne DP" mit Range).
                   Fortschritts-Modal mit Live-Progress.
                   Rate-Limit 300 Abfragen/Min mit 300ms Pause, 429-Handling.
Ist im KI-Tool:    vorhanden — sistrix-bulk.php mit 4 Varianten
                   (si/alter/dp/alles, siehe linkquellen.php:147ff). Wochen-
                   kontingent in Settings. Rate-Limit 300ms umgesetzt
                   (SistrixService.php:347). 429-Handling
                   (SistrixService.php:410). Erreichbarkeits-Bulk vorhanden.
                   Pre-Confirm-Modal mit Live-Progress (linkquellen.php:967).
                   Filter „nur_ohne_si" / „nur_ungeprueft" vorhanden.
Abweichung/offen:  - Credit-Reset Montag 00:00 als CRON: prüfen
                     (CLAUDE.md erwähnt nur Erreichbarkeits-Cron und
                     Stale-User-Cron). Reset-Cron für Sistrix-Kontingent
                     scheint nicht definiert — manueller Reset?
                   - „Ohne DP mit Range"-AND-Filter
                     (also ohne_dp UND si_range): prüfen ob kombinierbar
                     (sollte trivial sein wenn beide Filter unabhängig)
Priorität:         mittel
Kategorie:         fehlende Funktion
```

### 03 · ▸ 8. Herd/Server + Worktree-Setup für Linkprofil-Modul

```
Anforderung:       Linkprofil-Modul wird parallel in separatem Chat gebaut,
                   gleiche URL, gleicher Code-Tree.
Ist im KI-Tool:    n/a — Setup-Thema im alten Projekt.
Priorität:         —
```

### 03 · ▸ 9. Standabgleich + Phase D Monitoring-Politur

```
Anforderung:       Filter „nur ungesichtete (übertragen aus Linkprofil)" prüfen,
                   warum so langsam.
Ist im KI-Tool:    vorhanden — Monitoring-Modul mit Filter, Cron täglich 03:00
                   (CLAUDE.md). „Übertragen aus Linkprofil" Bulk-Logik wohl im
                   Linkprofil-Aufräum-Workflow.
Priorität:         niedrig
```

### 03 · ▸ 10-11. Linkprofil-Import (xovi/GSC/Sistrix) + Tabellen + „ohne SI/DP"-Filter + Spam-Linkart

```
Anforderung:       Linkprofil-Verarbeitungs-Pipeline als Bulk-Kette/Pfad:
                   Erreichbarkeit → nicht-erreichbar = gelöscht → SI →
                   Linkart aus Wissen → KI-Linkart → KI-Empfehlung.
                   Multifilter-Ergebnisse alle URLs in Zwischenablage.
                   „Fehlende Felder"-Filter (Deeplink fehlt, Ankertext fehlt).
                   Sortierung nach allen Spalten (Linktext, Erreichbarkeit,
                   Sistrix, Tags, Bemerkung, Neu, Quelle).
                   AND-Filter „ohne SI" + „ohne DP" mit Range.
                   Spam auch als Linkart übernehmbar in Linkquellen.
                   Wissensdatenbank für Linkquellen verfügbar.
Ist im KI-Tool:    teilweise — Linkprofil-Modul vorhanden, Linkart-Vokabular
                   17+1 Werte (LamService.php:40), Bulk-Workflow vorhanden
                   (CLAUDE.md erwähnt KI-Klassifikation Bulk + Audit).
                   Linkprofil-Aufräum-Sicht (linkprofil-aufraeumen.php) gebaut.
Abweichung/offen:  - „Multifilter-Ergebnisse als URLs in Zwischenablage":
                     prüfen ob Copy-Button da ist
                   - „Fehlende Felder"-Filter: prüfen
                   - „Alle Spalten sortierbar": teilweise (vermutlich nicht
                     für alle gleich)
                   - „Bulk-Pfad als Kette/Pipeline": Kette von 5-6 Schritten
                     als 1-Klick-Workflow scheint nicht zu existieren —
                     einzelne Schritte ja, aber kein Kombi-Knopf
                   - Status laut docs/lam-migration-status.md Punkt 4:
                     Linkart-Vokabular war auf alte 9 Werte, ist auf 17+1
                     umgestellt — laut CLAUDE.md erledigt
                   - Status Punkt 5: Empfehlungs-Vokabular auf 5 Spec-Werte
                     (lassen/ändern/löschen/disavow/gelöscht): laut
                     lam-migration-status.md NOCH OFFEN
Priorität:         mittel-hoch
Kategorie:         Workflow / fehlende Funktion
```

### 03 · ▸ 12. Linkoptionen: zwei Ansichten Liste/Auswahl + Sparmodus (Hauptanforderung!)

```
Anforderung:       Linkoptionen = Linkquellen, die einem Kunden zugeordnet sind.
                   Zwei Ansichten:
                   - „Liste" = Vorauswahl = Linkpool pro Kunde
                     (6000 Quellen → 150 in SMV-Pool)
                   - „Auswahl" = Snapshot = konkrete Vorschlagsliste pro Periode
                     (25 davon im Mai 2026 als „Linkoptionen Mai 2026")
                   Sparmodus mit bekannten Linkquellen-Spalten.
                   EINHEITLICHE Filter-Rahmen zwischen Linkprofil/Linkquellen/
                   Linkoptionen (nur Details abweichend).
                   Bestehender Auswahl-Liste zuordnen (nicht nur neu anlegen).
                   Sticky letzter Tab pro Seite.
Ist im KI-Tool:    teilweise — gerade frisch umgebaut:
                   ✓ Pool-Tab + Auswahl-Tab in linkoptionen.php
                   ✓ Linkpool pro Kunde (lam_domain_customer)
                   ✓ Vorschlagslisten als Snapshots
                   ✓ „Auf Vorschlagsliste"-Modal mit Bestehende/Neu-Tabs
                   ✓ Sticky Tab + Kunde via localStorage
                   ◐ Pool-Spalten als „bekannte Linkquellen-Spalten":
                     teilweise (gerade aufgehübscht)
Abweichung/offen:  - Einheitlicher Filter-Rahmen Linkprofil / Linkquellen /
                     Linkoptionen-Pool: unterschiedlich gestaltet, sollte
                     harmonisiert werden (Tom: „nicht aus einem Guss")
                   - „Auswahl"-Tab in Linkoptionen war im Konzept
                     „Snapshot Mai 2026" → aktuell ist es
                     „Vorschlagslisten-Einträge im Status-Lebenszyklus"
                     (sehr ähnlich, aber Sprache anders)
Priorität:         mittel
Kategorie:         Optik / Workflow
```

### 03 · ▸ 13. Excel-Export + Linkoptionen-Detail-Felder

```
Anforderung:       Excel-Export Felder in dieser Reihenfolge:
                   URL / Beispiellink / SI / DP / Preis / Linkziel / Linktext /
                   Artikelthema / Bemerkungen.
                   Anbieter NICHT (geht Kunden nichts an).
                   Preis = abgerechneter Preis (nicht Vereinbart-Preis).
                   Pflichtfelder nur 1, 3, 4, 5 (URL/SI/DP/Preis).
                   Linkoption-Detail: AUFGEKLAPPT, volle Breite,
                   gleiche Spalten-Reihenfolge wie oben, Box rechts weg.
                   Status-Wechsel nicht nur vorwärts — ganze Kette wählbar.
                   Linkoption mit Asana-Ticket: neu anlegen ODER vorhandenes
                   verbinden.
                   Fortschritts-Modal bei Crawl/Extrahieren.
                   Kontakt: immer Person + Firma als Ergänzung (Person primär).
Ist im KI-Tool:    teilweise — vorschlagsliste-excel.php hat KOMPLETT andere
                   Spalten:
                   Cluster|Themengebiet|URL|Impressum|Anmerkung|SI|DP|Preis|
                   Preis min|Preis max|Anbieter-Anzahl|Günstigster|Alle
                   Angebote|Quelle (orientiert sich an VID_Linkquellen_Final.xlsx
                   was vermutlich ein anderer Sales-Export war).
                   Anbieter IST drin — sollte raus laut Anforderung.
                   Beispiellink fehlt als eigene Spalte.
                   Status-Wechsel in Linkoption-Detail: Pipeline-Buttons
                   eingebaut, alle Stufen wählbar.
                   Asana-Ticket aus Linkoption: linkoption-zu-massnahme.php
                   vorhanden — Push-Asana-Logik aber nicht via Direkt-Verknüpfen.
                   Person + Firma: gerade frisch im Anbieter-Crawl umgesetzt.
Abweichung/offen:  - Excel-Export-Felder nochmal abstimmen:
                     Tom-Anforderung (Datei 03): URL/Beispiellink/SI/DP/Preis/
                     Linkziel/Linktext/Artikelthema/Bemerkungen — ohne Anbieter.
                     Aktuell: Cluster/Themengebiet/Anbieter sind drin.
                     → Inkonsistenz: Du hattest mir gesagt „Spalten exakt wie
                     VID_Linkquellen_Final.xlsx", was anderes ist als die
                     Briefing-Anforderung aus 03. Diskussion nötig.
                   - „Beispiellink" als eigene Spalte fehlt im Export
                   - Asana-Ticket „vorhandenes verbinden" aus Linkoption-Detail
                     prüfen (Verknüpfungs-Workflow)
Priorität:         hoch (Workflow + Tom hat das mehrfach betont)
Kategorie:         Workflow / fehlende Funktion
```

### 03 · ▸ 14. „Name (Firma)"-Logik zurückrollen + Backups + MySQL

```
Anforderung:       „Name (Firma)" doppelt gemoppelt — zurückrollen.
                   Person primär, Firma optional getrennt.
Ist im KI-Tool:    vorhanden — Anbieter.name (Person) und Anbieter.firma sind
                   getrennte Spalten. Detail-Anzeige zeigt Person fett,
                   Firma als „business"-Subtext. Picker/Listen zeigen Person.
                   Kürzlich frisch umgesetzt im Impressum-Crawl + UI-Refactor.
Abweichung/offen:  keine offene
Priorität:         —
```




---

## Datei 04 · Linkprofil-Analyse (9 Sinnabschnitte)

### 04 · ▸ 1-2. Verortung + Datenmodell

```
Anforderung:       Schutz des Bestandssystems (additive Migrations, keine
                   Umbenennungen). linkprofil_tags getrennt von Linkquellen-Tags.
                   url_hash mit Normalisierung (lowercase, www-strip, trailing-
                   slash, Fragment, UTM weg). Vokabular als Array-Konstanten.
Ist im KI-Tool:    vorhanden — lam_verlinkungen mit url_hash + normalisierter
                   verlinkende_url. Verlinkung::normalisiereUrl-Logik migriert.
                   Tags lam_tags (geteilt) vs. linkprofil_tags (getrennt) —
                   letzte Architektur prüfen.
                   LamService.php Konstanten (VERLINKUNG_LINKART,
                   VERLINKUNG_EMPFEHLUNG).
Abweichung/offen:  - linkprofil_tags vs. lam_tags: prüfen ob aktuell getrennt
                     oder zusammengeführt
Priorität:         niedrig
```

### 04 · ▸ 3. Import-Erkennung (CSV xovi/GSC/Sistrix)

```
Anforderung:       CSV-Erkennung für xovi, GSC, Sistrix, optional Ahrefs.
                   Mehrere Dateien auf einmal hochladen.
Ist im KI-Tool:    vorhanden — linkprofil.php hat Quelle-Optionen
                   sistrix/ahrefs/xovi/gsc.
Abweichung/offen:  - Multi-Upload prüfen (P39 verlangt das)
Priorität:         niedrig
```

### 04 · ▸ 4. Vorschlags-/Aufräum-Logik + Status „unsicher" + Bulk-Funktionen identisch zu Linkquellen

```
Anforderung:       Aufräum-Vorschlag bleibt offen (kein einzelnes Aufklappen).
                   Default: auf 1 reduzieren, Mehrfach-Behalten nur als Ausnahme
                   bei Top-Links (KI lernt die Ausnahmen).
                   Status „unsicher"/„klären" als Empfehlungs-Wert.
                   Erreichbarkeits-Bulk + Sistrix-4-Varianten + Linkart-Auslesen
                   + Empfehlungs-Vorschlag in Linkprofil-Tabelle — 1:1 wie in
                   Linkquellen.
Ist im KI-Tool:    teilweise — Empfehlungs-Wert „unsicher" vorhanden
                   (VERLINKUNG_EMPFEHLUNG, LamService.php:48). Erreichbarkeits-
                   Bulk und Sistrix-Bulk in Linkprofil-Bulk-Toolbar vorhanden
                   (linkprofil.php:246-251).
Abweichung/offen:  - Inline-Anwenden („Tom: Bulk-Anwenden tut nix, manuelle
                     Änderung verschwindet Konflikt"): prüfen ob aktuell sauber
                   - Empfehlungs-Vokabular 5+1 (lassen/aendern/loeschen/disavow/
                     geloescht/unsicher): aktuell drin (5 Spec + unsicher).
                     migration-status.md Punkt 5 sagt aber „noch offen" —
                     vielleicht ist das veraltet
Priorität:         mittel
Kategorie:         Workflow / fehlende Funktion
```

### 04 · ▸ 5. Mehrere Kundenanalysen + Social-Media-Rubrik + „Anwenden" für Konflikte

```
Anforderung:       Mehrere Linkprofil-Analysen pro Kunde verarbeiten, KI lernt
                   aus den Entscheidungen.
                   Eigene Rubrik „Social Media" für XING/LinkedIn etc.
                   Wenn man eine Anpassung inline durchführt, soll diese auf
                   alle Konflikte angewendet werden (nicht nur lokal).
                   Domain-Wissen-Konfliktauflösung im Tool.
Ist im KI-Tool:    teilweise — social_media als 18. Linkart-Wert vorhanden
                   (LamService.php:45). Domain-Wissen-Tool existiert
                   (domain-wissensbasis.php, domain-wissen-*-API).
                   Mehrkunden-Analysen mit gemeinsamen Lernschritten: prüfen
                   wie KI da nachzieht.
Abweichung/offen:  - „Anwenden"-Knopf wirkt laut Briefing nicht — prüfen ob
                     domain-wissen-anwenden.php sauber durchgreift
                   - KI-Lernschleife aus historischen Linkprofil-Excels:
                     existiert kein Importer für „bisherige Linkprofil-Analysen"
                     als Trainingsmaterial
Priorität:         mittel
Kategorie:         fehlende Funktion
```

### 04 · ▸ 6. Domain-Wissen + globale Standards (Tabellen-Schliff)

```
Anforderung:       Pfeil-Icon zur URL in Domain-Wissen-Tabelle. Spalten-
                   Sortierung. Tabelle global einheitlich zu Linkquellen-Stil.
Ist im KI-Tool:    teilweise — domain-wissensbasis.php existiert.
                   Tom selbst sagt P34: „Standards nicht global". Aktuelle Lage:
                   thx-components.css + thx-tokens.css existieren, aber LAM-
                   spezifische CSS in lam.css läuft parallel (CLAUDE.md:
                   „wird schrittweise zu .thx-* migriert").
Abweichung/offen:  - Migration lam.css → thx-* noch nicht abgeschlossen
                     (siehe CLAUDE.md)
Priorität:         mittel
Kategorie:         Optik
```

### 04 · ▸ 7. „Aufräumen" vs. „Re-Run"

```
Anforderung:       Aufräumen jederzeit neu anstoßbar. Fortschrittsbalken +
                   Weiterleitung auf Ergebnisseite. Sortierung „klare Sachen
                   zuerst akzeptieren, unsichere prüfen". Tabellen abgeschnitten
                   → Spalten umbrechen damit alles passt.
Ist im KI-Tool:    teilweise — linkprofil-aufraeumen.php gebaut, Re-Run
                   theoretisch möglich. Tabellen-Layout: laut Tom Tabellen
                   teils abgeschnitten — prüfen.
Abweichung/offen:  - „Klare zuerst" Sortier-Strategie prüfen
                   - Fortschritts-Modal sauber + Auto-Weiterleitung
Priorität:         mittel
```

### 04 · ▸ 8. Excel-Export-Formatierung + Linkquelle-Übertrag

```
Anforderung:       Excel-Export Vorlage 1:1 wie SMV_Linkprofil-Analyse-Excel.
                   Fehler abstellen.
                   Wenn Verlinkung als Linkquelle übertragen wird, dann:
                   - Status „noch ungesichtet" filterbar
                   - Mit Kunde verknüpfen (Quell-Projekt)
                   - Deeplink zur Veröffentlichung erhalten / Monitoring-fähig
Ist im KI-Tool:    teilweise — linkprofil-excel.php existiert. Tom hat das
                   Thema später mit Benny abgestimmt (Vorlage statt KI-Excel).
                   „Noch ungesichtete" als Verifikations-Status „neu" filterbar.
                   Bei Linkquellen-Übertrag aus Linkprofil: prüfen ob Kunden-
                   Verknüpfung automatisch + Deeplink erhalten bleibt.
Abweichung/offen:  - Filter „ungesichtet" als eigener UI-Filter prüfen
                   - Deeplink-Erhalt + Monitoring-Übergang prüfen
Priorität:         mittel
Kategorie:         Workflow
```

### 04 · ▸ 9. Linkprofil als fester Menüpunkt zwischen Dashboard und Linkquellen

```
Anforderung:       Menüpunkt „Linkprofil" zwischen Dashboard und Linkquellen.
Ist im KI-Tool:    vorhanden — _tabs.php:13 Linkprofil an Position 2
                   (zwischen dashboard und linkquellen).
Abweichung/offen:  keine
Priorität:         —
```

---

## Datei 05 · Parallel-Chat goofy-borg (1 Sinnabschnitt)

### 05 · ▸ 1. Übergabe-Briefing Linkprofil-Modul

```
Anforderung:       Briefing für den Linkprofil-Parallel-Chat. Wesentliche
                   Architektur-Entscheidungen: additive Migrations,
                   eigene linkprofil_tags-Tabelle, Wiederverwendung der
                   Linkquellen-Bausteine (Pro-Seite-Dropdown, Fortschritts-Modal,
                   Filter „ohne SI", AuditLog::recordBulk, ExcelExport-Base,
                   MappingService-Heuristik).
Ist im KI-Tool:    teilweise — siehe 04 für Linkprofil-Status.
                   Wiederverwendung: thx-* + lam.css teilen Bausteine,
                   Fortschritts-Modal-Komponente vorhanden,
                   Audit-Log mit recordBulk vorhanden.
Abweichung/offen:  siehe 04
Priorität:         —
```


---

## Datei 06 · KI-Tool Vorlauf (3 Sinnabschnitte)

### 06 · ▸ 1. Standabgleich + CONTEXT.md + Projektplanner

```
Anforderung:       Projektplanner optimieren, Benny portiert Code.
Ist im KI-Tool:    vorhanden — views/projektplanner/{dashboard,import,index}.php
                   existieren. Asana-Integration im Projektplanner ist die
                   Referenz-Implementierung für die LAM-Asana-Konfig
                   (siehe Datei 03 ▸ 2).
Abweichung/offen:  keine LAM-Migrationspunkte
Priorität:         —
```

### 06 · ▸ 2. Kunden-Steckbrief: Logo-Upload + Favicon-Auslesen

```
Anforderung:       Logo-Upload pro Kunde. Favicon automatisch aus Kunden-
                   Website auslesen (größtes), Fallback Upload.
                   Massenbearbeitung für Favicon-Fetch.
                   IDs von gelöschten Kunden: aufräumen oder lassen.
Ist im KI-Tool:    vorhanden — customers.php:75 cuBulkFetchFavicons-Button,
                   API customers/bulk-favicons.
Abweichung/offen:  - „Bei vielen lädt leeres Bild" (P08): wahrscheinlich
                     Heuristik-Problem mit nicht standardkonformen Favicons.
                     Aktueller Erfolg/Misserfolg-Stand unklar — wird in der
                     Pflege-Center-Mechanik vermutlich abgefangen
                   - IDs aufräumen: bewusst nicht erfolgt, Lücken bleiben
                     (siehe CLAUDE.md „User-Inactiv-Cron"); für LAM irrelevant
Priorität:         niedrig
Kategorie:         fehlende Funktion / Optik
```

### 06 · ▸ 3. Terminal/SSH-Anbindung

```
Anforderung:       Setup-Frage, kein Migrationspunkt.
Ist im KI-Tool:    n/a
Priorität:         —
```


---

## Datei 07 · LAMS-Migration ins KI-Tool (Schwerpunkt: gestalterischer Gap)

### 07 · ▸ 1. LAMS-Daten verarbeiten + Sidebar-Menüpunkt „LAM-System"

```
Anforderung:       Daten aus lams_modul_alt verarbeiten. Sidebar-Menüpunkt
                   „LAM-System" links anlegen.
Ist im KI-Tool:    vorhanden — Sidebar hat „LAM-System" als einzelner Eintrag
                   (CLAUDE.md). Klick führt zur zuletzt besuchten LAM-Seite
                   (localStorage thx_lam_last_path).
Abweichung/offen:  keine
Priorität:         —
```

### 07 · ▸ 2. Optik-Abgleich alt/neu (Screenshots)

```
Anforderung:       Tom wollte Screenshot-Vergleich vorher/nachher.
Ist im KI-Tool:    n/a — wurde mit Datei 08 explizit dokumentiert
Priorität:         siehe 08
```

### 07 · ▸ 3. UI-Styleguide übernehmen (HAUPTAUFGABE Gestalt)

```
Anforderung:       lam-design-reference.zip nach docs/design-reference/ entpacken.
                   Thoxan-Farbpalette aus tailwind.config.js (thoxan-50..950).
                   Frutiger-Schrift einbinden.
                   DREITEILIGER HEADER:
                     1. Admin-Top-Bar in thoxan-700 dunkelblau, h-11,
                        rechtsbündig Kunden/Einstellungen/[TKI]
                     2. Haupt-Navigation in weiß h-20, Logo links,
                        Modul-Tabs rechts: Dashboard/Linkprofil/Linkquellen/
                        Anbieter/Linkakquise/Linkoptionen/Maßnahmen/Auslagen/
                        Monitoring/Korrespondenz
                     3. Page-Header in weiß py-5: H1 + Untertitel links,
                        Action-Button-Reihe rechts
                   Filter-Pattern: Chip-Pills (Kunden, Linkart, Empfehlung),
                   keine Dropdowns für Multiselect.
                   Tabellen-Pattern: dezent (kein Zebra, keine bunten Badges),
                   Klartext für Empfehlung/Status, drei Echte Badge-Typen
                   (neu emerald, Wie-oft-Counter amber, Tags slate-200).
                   Erreichbarkeit als Farbpunkt (grün/amber/rose/slate).
                   max-w-[1920px] für breite Monitore (Linkprofil 14 Spalten).
                   Footer mit „LAM · Linkaufbau-Management der Thoxan
                   Communications GmbH · frischer wind im netz."
                   Stilvorgaben: Deutsch, Höflichkeitsformen groß, keine
                   Gedankenstriche im UI-Text (em-Dash nur als Platzhalter in
                   Tabellen erlaubt).

                   ANTI-MUSTER (laut Styleguide):
                   1. Linke Sidebar statt horizontaler Hauptnav
                   2. Fehlende Admin-Top-Bar
                   3. Farbige Empfehlungs-Badges (rot/grün/gelb)
                   4. Filter als Dropdowns statt Chip-Pills
                   5. Reduzierte Tabellenspalten
                   6. Fehlende Action-Buttons in Header
                   7. Kein max-w-[1920px]
                   8. Frutiger fehlt
                   9. Tabs INNERHALB eines LAM-Moduls
Ist im KI-Tool:    teilweise — viele Anforderungen erfüllt:
                   ✓ Thoxan-Palette in thx-tokens.css
                   ✓ Frutiger LT Std unter /assets/fonts/lam/
                     (frutiger-lt-std-roman.woff2 + bold.woff2)
                   ✓ thx-tokens.css: --container-max: 1920px definiert
                   ✓ Top-Bar (thx-topbar) Thoxan-Blau,
                     Kunden+Einstellungen+User+Abmelden rechts
                   ✓ Modul-Tab-Leiste horizontal (_tabs.php) in der richtigen
                     Reihenfolge
                   ✓ Linkprofil-Header-Actions vollständig:
                     Domain-Wissen + Snapshots + Statistik + Aufräumen +
                     Historie importieren + CSV importieren
                   ✓ Linkquellen-Header-Actions: Linkquellen-Import (neu) +
                     Portfolio importieren + Neue Linkquelle. Tags-Filter
                     ist als Chip-Reihe im Filter-Bereich
                   ✓ html { font-size: 120% }
                   ✓ Chip-Pills für Linkart/Verifikation in Linkquellen
                   teilweise:
                   ◐ max-w-1920 in thx-tokens.css als Variable, aber
                     CSS-Anwendung an .main-content / Tabelle prüfen
                     (Tom hatte „voller Browser-Breite" gewollt)
                   ◐ Footer: kein dezidierter LAM-Footer mit „frischer
                     wind im netz" gefunden — der Original-Brandingfooter
                     fehlt offenbar
                   ◐ Header-Aktion „URLs kopieren" + „Excel" im Linkprofil:
                     prüfen (Styleguide nennt sie als Pflicht)
                   ◐ Tabs INNERHALB eines Moduls:
                     - linkoptionen.php hat „Pool"/„Auswahl"-Tabs
                       (Anti-Muster #9)
                     - Tom hat das in Datei 03 ▸ 12 ausdrücklich gewollt!
                       (P87: „Zwei Ansichten 'Liste / Auswahl' finde ich gut
                       unter 'Linkoptionen'")
                     → Styleguide-Anti-Muster ist hier durch späteren
                       Wunsch überstimmt — OK
                   - „lam-chip-status-*" im Linkoptionen-View
                     (lam-chip-status-vorgeschlagen etc.) sind FARBIGE
                     STATUS-CHIPS — laut Styleguide soll Status Klartext sein
                     (linkoptionen.php:421)
                   - Verifikations-Chips haben in Linkquellen eigene Farben
                     pro Wert (neu=amber, in_arbeit=thoxan, geprueft=emerald,
                     veraltet=orange, geloescht=rose) — passt zum Styleguide
                     (P280 sagt: „Verifikations-Chips haben eigene Farben pro
                     Wert")
                   - Si-Cell-Farbklassen si-fresh/si-mid/si-old/si-stale —
                     Tom wollte NICHT bunt (Datei 02 ▸ 10), Styleguide auch
                     nicht
Abweichung/offen:  HOHE PRIO Optik:
                   - Footer mit Branding fehlt
                   - Farbige Status-Chips in Linkoptionen-Auswahl-Tab
                     (Anti-Muster 3)
                   - SI-Cell-Farbklassen (Si-fresh/mid/old/stale) raus,
                     einheitliche Schrift (Anti-Muster 3 + Tom Datei 02)
                   - Empfehlung/Status in Linkprofil-Tabelle als Klartext
                     statt Badges prüfen
                   - max-w-1920 konsequent durchziehen (testen ob auf 1920er
                     Monitor wirklich breit genug)
                   - „URLs kopieren"-Button im Linkprofil-Header
                   - „Excel"-Button im Linkprofil-Header explizit
                   - Tag-Auswahl in Linkquellen-Header noch nicht als
                     Standalone-Button (im Filter, statt im Header)

                   MITTEL Optik:
                   - Anbieter-Dropdown in Linkquellen-Detail nutzt mittlerweile
                     Type-Ahead (gut) — aber andere Dropdowns prüfen
                   - Material-Icons konsistent mit „nur sparsam"-Prinzip
                     (Styleguide hat Material-Icons nicht erwähnt)

                   STILVORGABE Text:
                   - Stichproben im Code zeigen viele gerade „—" als Strich
                     in Tabellen — passt. Gedankenstriche im UI-Fließtext:
                     stichprobenartig prüfen
Priorität:         hoch (Tom's expliziter Schwerpunkt 07/08)
Kategorie:         Optik
```


---

## Datei 08 · Visuelle Abweichungen nach Migration (2 Sinnabschnitte)

### 08 · ▸ 1. Screenshot-Vergleich: Layout/CD-Gap

```
Anforderung:       Tom zeigt explizit den Gap zwischen alter LAMS-Optik
                   und neuer KI-Tool-Optik. Schwerpunkt: Header/Navigation
                   und Tabellen.
Ist im KI-Tool:    siehe 07 für komplette Bewertung des Styleguide-Compliance.
                   Datei 08 ist im wesentlichen die Begleit-Notiz zu 07.
Abweichung/offen:  siehe 07 — Optik-Lücken konsolidiert dort
Priorität:         hoch (Tom's expliziter Schwerpunkt)
Kategorie:         Optik
```

### 08 · ▸ 2. Styleguide-Übergabe via Upload + Terminal

```
Anforderung:       Übergabe-Modalität (Datei-Upload via Terminal weil
                   Claude Code im SSH keine Screenshots interpretieren konnte).
Ist im KI-Tool:    n/a — Setup-Thema, Lieferung erfolgte (siehe 07)
Priorität:         —
```

