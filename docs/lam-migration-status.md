# LAM-Migration: Status & Inventur

**Zweck:** Vollständige Auflistung aller Features aus dem Laravel-Prototyp und Status im KI-Tool.

**Status-Legende:** ✓ migriert · ◐ teilweise · ✗ fehlt · — nicht geplant für MVP

**Priorität:** ⓵ kritisch · ⓶ wichtig · ⓷ nice-to-have

**Letzter Stand:** 22.05.2026 (Nacht) — LAM-Migration ist FUNKTIONAL VOLLSTÄNDIG nach Prototyp-Spec. Out-of-Scope bleiben nur noch: Mail-Integration (IMAP/SMTP, separates globales Thema), Asana-Phase-2-Push (LAM→Asana, Webhooks), und externe Logins (laut Spec explizit nicht geplant).
**4 Wellen abgeschlossen:** (1) Grundlagen — Sistrix + KI-Klassifikation + Snapshots + HTTP-Monitoring + Historien-Skelett. (2) Polish — Tags/Linkziele/Kanban/Statistik/Domain-Wissen/Filter/Mute/Dashboard-Tiles. (3) Vokabular + KI-Welle — Status-Pipeline auf Spec angeglichen, 4 KI-Features (Cluster, Recherche, Mapping, Domain-Matching), XLSX-Import+Multi-Upload, Excel-Exports im BKK-Layout, Aufgaben-Tabelle, Settings-Tab. (4) Audit + Restbestand — systematisches Audit-Log mit eigener Sicht, Bulk-Erreichbarkeit, Aufräum-Wizard mit Klar/Unsicher-Split, Anbieter-aus-Impressum-Crawler, Charts (Donut+Sparkline) + PDF-Druck, Multi-Tenancy-Schema-Vorbereitung, Asana-Lese-Sync Phase 1+1b mit KI-Felder-Extraktion.

---

## Querschnitt-Patterns (alle Module)
- ✓ Inline-Edit-Pattern, Bulk-Auswahl, Rechtsklick-Kontextmenü, Drawer, Modal
- ✓ Service-Schicht (`services/LamService.php`) mit zentraler Logik
- ✓ Sistrix-Service mit Caching + Wochenkontingent
- ✓ Custom CSS-Bibliothek (`thx-components.css` + `lam.css`)

---

## 1. Anbieter — ✓ VOLLSTÄNDIG (inkl. Polish)

| Feature | Status |
|---|---|
| Liste + Filter + Inline-Edit + Drawer + Bulk + Kontextmenü | ✓ |
| Detail-Seite mit Domains, Kontakten, Korrespondenz | ✓ |
| Kontakte CRUD + Primär-Toggle | ✓ |
| **Dublettenprüfung beim Anlegen** | ✓ (Service prüft Name + Firma, UI erlaubt "trotzdem anlegen") |
| **Rollen-Farbgebung in Tabelle** | ✓ (Beides = amber, Vermittler = thoxan, Betreiber = emerald) |

---

## 2. Linkprofil — ✓ funktional komplett, KI-Module bewusst offen

| Feature | Status |
|---|---|
| Liste mit allen Spalten, Filter, Sortierung, Inline-Edit, Bulk, Kontextmenü | ✓ |
| **Topp-Link-Kennzeichen** (★) | ✓ seit 14.06.2026: Spalte `lam_verlinkungen.ist_topp`, Stern-Spalte (klickbar pro Zeile), Bulk-Aktionen „Als Topp markieren/entfernen" (`topp_setzen`), Kontextmenü-Eintrag, Filter-Chip „★ nur Topp", sortierbar. |
| **„Wie oft"-Spalte** (Count pro Domain/Kunde) | ✓ |
| **URLs kopieren-Button** | ✓ |
| **CSV-Import** (Sistrix, AHREFs, generisch) | ✓ Auto-Format-Erkennung |
| **CSV-Export** | ✓ |
| Tags-Spalte + Tag-Modal | ✓ Multi-Select-Popover in Linkquellen-Liste, eigene `/lam/tags`-Stammdaten-Sicht (CRUD + Merge) |
| SI-Spalte mit Alters-Färbung | ◐ in Linkquellen ja, in Linkprofil nicht direkt sichtbar (Datensatz kommt aus Backlink-Analyse, nicht aus Sistrix-Snapshots) |
| f/nf-Badge im Linktext | ✓ |
| Google-Deep-URL-Lupe | ✓ (Lupen-Icon pro Zeile, öffnet `site:domain "linktext"` auf Google) |
| Historie importieren | ✓ `/lam/historien-import` mit Spalten-Mapping + Vorschau + Schreib-Pfade für alle vier Ziele (Maßnahmen, Auslagen, Korrespondenz, Linkprofil). Top-Bar-Button im Linkprofil verlinkt seit 28.05.2026. |
| **Aufräum-Modus (Sitewide-Cluster)** | ✓ `/lam/linkprofil/aufraeumen` mit Schwellwert-Filter (Default 5), Cluster-Detail-Modal, Bulk-Empfehlung pro Cluster setzen |
| **KI-Klassifikation Linkart + Empfehlung** | ✓ Claude Haiku, Bulk-Buttons im Linkprofil (schnell max 200, tief mit Crawl max 50), Retry mit Backoff bei 529/429, Konfidenz + Begründung in Datenbank gespeichert |
| Domain-Wissensbasis | ✓ manuell pflegbar (Detail-Box im Linkquellen-Detail mit Branche/Thema/Tonalität/Risikofaktoren). **Standalone-Übersicht seit 28.05.2026**: `/lam/linkprofil/domain-wissen` — Tabelle mit Filter (Suche/Linkart/Confidence/Konflikte), Bulk-Aktionen „Anwenden" (Force-Modus, überschreibt) + „Löschen". KI-Klassifikation kommt zusätzlich später. |
| **Hintergrund-Erreichbarkeits-Worker** | ✓ seit 28.05.2026: Cron `/etc/cron.d/ki-tool-lam-erreichbarkeit` alle 5 Min, 250 URLs pro Lauf via `scripts/lam-erreichbarkeit-worker.php` (Lockfile, Stale-Detect). UI-Pille im Linkprofil-Header zeigt Queue-Status. So bleibt `letzter_http_erreichbar` automatisch frisch nach jedem Import. |
| **Cluster-Aufräum-Modus mit Erreichbarkeit** | ✓ seit 28.05.2026: Cluster-Übersicht zeigt erreichbar/tot/ungeprüft-Counts; Cluster-Detail sortiert erreichbare zuerst; selektive Bulk-Empfehlung möglich (z.B. „nur tote auf disavow"). Konservativ: keine Auto-Löschung. |
| Statistik-Sub-Seite | ✓ `/lam/linkprofil/statistik` (Top-Domains, Linkart-Verteilung, Follow-Anteil, Empfehlungs-Verteilung) |
| Snapshots + Diff | ✓ bei jedem CSV-Import automatisch erzeugt, eigene `/lam/linkprofil/snapshots`-Sicht mit Diff-Modal (neu/verschwunden seit letztem Import) |

Backend:
- `POST /api/v1/lam/verlinkung-inline`, `verlinkung-bulk`
- `POST /api/v1/lam/linkprofil-import` (Multi-Format CSV/TSV)
- `GET  /api/v1/lam/linkprofil-export?customer_id=X` (CSV mit UTF-8 BOM)

---

## 3. Linkquellen — ✓ VOLLSTÄNDIG (inkl. Sistrix-Bulk)

| Feature | Status |
|---|---|
| Liste + Filter + Inline-Edit + Drawer + Bulk + Kontextmenü | ✓ |
| Detail-Seite mit allem Drum und Dran (server-rendered) | ✓ |
| Konditionen-CRUD im Detail | ✓ Drawer |
| **Sistrix pro Domain im Detail** (SI/Alter/DP/Alles) | ✓ echte API-Calls |
| **Sistrix-Bulk in Liste** (4 Buttons mit Live-Credits-Anzeige) | ✓ pre-flight-Check, Cache-Hit-Reporting |
| **URL-Copy-Button** | ✓ |
| **SI-Spalte mit Alters-Färbung** | ✓ frisch=grün, mid=neutral, alt=amber, stale=rose |
| Inline-Edit Tags + Preis | ✓ Tags als Multi-Select-Popover pro Zeile, Preise via Konditionen-Drawer |
| Kunden-Filter als Chips | ✗ ⓷ (Anbieter-Select + Sistrix-Bulk decken den Hauptbedarf ab) |
| Linkart-Multi-Select-Filter | ✓ |
| „Weitere Filter" (SI/Preis-Bereich) | ✓ Range-Inputs SI + Preis |
| Anbieter aus Impressum (Crawl) | ✗ ⓷ (Benny) |

Backend:
- `POST /api/v1/lam/sistrix-bulk` (mit Kontingent-Pre-Check)
- `POST /api/v1/lam/sistrix-abruf` (einzeln, Cache-Layer)

---

## 4. Linkoptionen — ✓ VOLLSTÄNDIG (inkl. Vorschlagslisten + Maßnahme-Workflow)

| Feature | Status |
|---|---|
| Pool-Liste, Inline-Status, Bulk, Kontextmenü | ✓ |
| Detail-Seite mit Status-Pipeline | ✓ |
| **Vorschlagslisten-Übersicht** (`/lam/vorschlagslisten`) | ✓ |
| **Listen-Detail mit allen Einträgen** (`/lam/vorschlagslisten/{id}`) | ✓ |
| **Liste anlegen/bearbeiten/löschen** (Drawer) | ✓ |
| **Pool/Auswahl-Tab-Switch** | ✓ Sekundärer Tab + Link |
| **„Eintrag zu Maßnahme machen"** | ✓ Service + API + UI |

Backend:
- `GET  /api/v1/lam/vorschlagslisten`, `vorschlagsliste-detail`
- `POST /api/v1/lam/vorschlagsliste-save`, `vorschlagsliste-loeschen`
- `POST /api/v1/lam/linkoption-zu-massnahme`
- `POST /api/v1/lam/linkoption-rueckmeldung`

---

## 5. Maßnahmen — ✓ VOLLSTÄNDIG (inkl. CRUD + CSV-Export)

| Feature | Status |
|---|---|
| Liste + Filter + Inline-Status + Bulk + Kontextmenü | ✓ |
| Detail-Seite mit Pipeline + Auslagen-Editor + Monitoring + Korrespondenz | ✓ |
| **„+ Neue Maßnahme" Drawer** | ✓ mit Domain-Picker (Suche) |
| **Auslage im Detail anlegen/bearbeiten** | ✓ existierte schon |
| **CSV-Export** | ✓ |
| **Linkziel im Anlege-Drawer** (Quick-Add, fuellt Linktext) | ✓ |
| Kanban-Ansicht | ✓ `/lam/massnahmen/kanban` mit Drag-and-Drop und optimistic Status-Update |
| HistorienImport mit KI/EML/XLSX | ◐ Skelett mit CSV-Mapping + Vorschau gebaut, eigentlicher Import noch zu verdrahten |

Backend:
- `POST /api/v1/lam/massnahme-save` (Anlegen+Bearbeiten)
- `POST /api/v1/lam/massnahme-inline`, `massnahme-bulk`
- `GET  /api/v1/lam/massnahmen-export`

---

## 6. Linkakquise — ✓ VOLLSTÄNDIG

| Feature | Status |
|---|---|
| Liste mit Filter + Suche | ✓ |
| **Anbieter-Spalte mit Link zum Anbieter-Detail** | ✓ |
| **Listen-Spalte mit Link zur Vorschlagsliste** | ✓ |
| **„Rückmeldung erfassen"-Modal** (Datum/Typ/Preis/nächste Aktion) | ✓ |
| **„Kontaktieren"-Button** (mailto an Primärkontakt + kontakt_am setzen) | ✓ |

---

## 7. Auslagen — ✓ VOLLSTÄNDIG

| Feature | Status |
|---|---|
| Liste mit Filter (Sonderfall, Jahr, **Quartal**) | ✓ |
| **Summen-Kachel-Reihe** (Anzahl, Kosten, Weiterverr., Marge) | ✓ |
| **Verlinkung zur Maßnahme** | ✓ |
| **Sonderfall-Badges + farblicher Akzent für Storno/neg. Marge/intern** | ✓ |
| **CSV-Export** | ✓ |

---

## 8. Monitoring — ✓ VOLLSTÄNDIG

| Feature | Status |
|---|---|
| Liste mit Filter „nur Alerts" | ✓ |
| Bulk: Alerts quittieren | ✓ |
| **Erneut prüfen pro Eintrag** (HTTP-Check via cURL + Linktext/Ziel-URL-Suche) | ✓ |
| **Bulk-Prüfen** (alle ausgewählten) | ✓ |
| **Verlinkung zur Maßnahme** | ✓ |
| Mute/Unmute pro Maßnahme | ✓ Toggle-Button pro Zeile + Filter "ohne stumm-geschaltete" + Ausschluss aus Dashboard-Alerts |
| **Auto-Monitoring-Cron** | ✓ täglich 03:00 für alle live + nicht muted Maßnahmen mit veroeffentlichungs_url. Cron unter `/etc/cron.d/ki-tool-lam-monitoring`, Log unter `/var/log/ki-tool-lam-monitoring.log` |
| Email-Benachrichtigung | ✗ ⓷ |

Backend:
- `POST /api/v1/lam/monitoring-check` (einzeln oder bulk)
- `POST /api/v1/lam/monitoring-aktion` (Alerts quittieren)

Logik: HTTP-Status 200-399 = ok, sucht Linkziel-URL bzw. Linktext im HTML, erkennt follow/nofollow per Regex.

---

## 9. Korrespondenz — ✓ VOLLSTÄNDIG

| Feature | Status |
|---|---|
| Liste mit Filter (Typ-Chips, Suche) | ✓ |
| Anhang-Download | ✓ |
| **Neuer Eintrag anlegen (Drawer)** | ✓ Typ/Zeitpunkt/Anbieter/Betreff/Inhalt |
| **Anhang-Upload** (PDF, Office, Bilder, EML, max 25 MB) | ✓ MIME-Validation, sicheres Verzeichnis |
| **Verlinkung zu Anbieter + Maßnahme** | ✓ |
| Mehr/Weniger-Toggle für langen Inhalt | ✓ |
| IMAP-Empfang / SMTP-Versand | — out-of-scope |

Backend:
- `POST /api/v1/lam/korrespondenz-save` (multipart, mit optionalem Anhang)
- `GET  /api/v1/lam/korrespondenz-anhang` (Path-Traversal-geschützt)
- Storage: `/var/www/storage/lam-attachments/` (`www-data:www-data`)

---

## 10. Dashboard — ✓ VOLLSTÄNDIG

| Feature | Status |
|---|---|
| KPIs + Pro-Kunde-Tabelle + Top-Anbieter | ✓ |
| Widgets: Anstehende Maßnahmen, Monitoring-Alerts, Linkakquise, Auslagen-Monat | ✓ |
| **Widget: Sistrix-Kontingent** (Progress-Bar + Reset Mo) | ✓ |
| **Widget: Letzte Aktivitäten** (Maßnahmen / Linkoptionen / Korrespondenz, Union) | ✓ |
| Schnellzugriffe (Quick-Action-Tiles) | ✓ 6 Tiles oben im Dashboard (Linkquellen, Akquise, Kanban, Monitoring, Auslagen, Korrespondenz) |

Backend:
- `GET /api/v1/lam/dashboard`
- `GET /api/v1/lam/dashboard-widgets` (mit `sistrix` + `letzte_aktivitaeten`)

---

## 11. Stammdaten

| Modul | Status |
|---|---|
| Kunden | ✓ (über `/admin/customers`) |
| Kontakte | ✓ (im Anbieter-Detail integriert) |
| Konditionen anlegen/bearbeiten | ✓ (im Linkquellen-Detail) |
| **Vorschlagslisten** | ✓ (eigene Sicht `/lam/vorschlagslisten`) |
| **Tags-Verwaltung als eigene Stammdaten-Sicht** | ✓ `/lam/tags` mit CRUD, Verwendungs-Counter, Merge (zwei Tags zusammenführen) |
| **Linkziele pro Kunde** | ✓ `/lam/linkziele` (URL/Thema/bevorzugter Linktext/Status) + Quick-Add im Maßnahmen-Drawer |
| **Domain-Wissen (manuell)** | ✓ Detail-Box im Linkquellen-Detail mit Branche/Thema/Tonalität/Risikofaktoren/Notiz |

---

## 12. Status: was läuft, was fehlt

1. ~~Sistrix-API-Integration~~ ✓ Thomas+Claude
2. ~~HTTP-Erreichbarkeits-Prüfung als Backend-Job~~ ✓ synchron + Cron (Intervall konfigurierbar)
3. ~~KI-Klassifikation Linkart/Empfehlung~~ ✓ Claude Haiku schnell + on-demand tief mit Crawl
4. ~~Sitewide-Cluster-Detection~~ ✓ Aufräum-Modus mit Schwelle Default 5
5. ~~HistorienImport~~ ✓ CSV + XLSX, KI-Spalten-Mapping, alle vier Ziele

Email-Workflows (IMAP/SMTP) bleiben out-of-scope (separates Thema, global anzugehen).

---

## 14. Welle 22.05.2026-Abend (Vokabular + KI + Excel + Aufgaben)

**Vokabular auf Spec angepasst:**
- Maßnahmen-Status auf 7 Werte (Briefing 01b): `idee/akquise/bei_kunde/beauftragt/bei_anbieter/live/archiv` — Kanban + Liste + Drawer + Whitelist alle umgestellt
- Vorgangstyp: `erstveroeffentlichung/re_veroeffentlichung/sammelbuchung/nachbuchung`
- Buchungstyp: Dropdown mit 6 Spec-Werten (`gastartikel/advertorial/pressemitteilung/interview/verzeichnis/startseite`)
- Linkart: 17 + `social_media` (= 18) — KI-Prompt + Whitelist + Hard-Rule `spam → disavow`
- Empfehlung: 5 Spec-Werte (`lassen/aendern/loeschen/disavow/geloescht` + `unsicher`) — KI-Prompt + Aufräum-Modal
- Auslagen-Sonderfall: `normal/storno_mit_weiterberechnung/intern/sammelposten/jahresueberhang` — Filter + Editor
- Beziehungsstatus auf Anbieter: `abgekuehlt` schon vorhanden
- Zentrale Konstanten in [`LamService.php`](services/LamService.php) als Single Source of Truth

**Workflow-Lücken geschlossen:**
- Verifikations-Workflow auf **Kontakte + Konditionen** exponiert (Dropdown pro Zeile in Anbieter-Detail + Linkquellen-Detail)
- **„inkl. Text"-Hinweis** im Maßnahmen-Drawer (lädt Kondition der Domain, zeigt Hinweis bei `inkl_text=1`)
- **Plan-B-Workflow** im Maßnahmen-Detail: Button „📋 Plan B anlegen" → öffnet Maßnahmen-Drawer mit vorbelegtem `plan_a_massnahme_id` + `sonderstatus='plan_b'` + `vorgangstyp='re_veroeffentlichung'`
- **Sammelposten-Widget** im Dashboard + Filter `?sonderstatus=sammelposten` in Maßnahmen-Liste
- **„Veraltet markieren" → Aufgabe** in neuer Tabelle `lam_aufgaben`, eigene `/lam/aufgaben`-Sicht (Liste mit Filter offen/in_arbeit/erledigt + Typ-Filter)
- **wartezeit_bis** auf Domain pflegbar (Inline-Box im Linkquellen-Detail)
- **Mute mit Ablaufdatum** (`monitoring_stumm_bis` auf `lam_massnahmen`) — Monitoring-Cron deaktiviert Mute automatisch, wenn Datum verstrichen
- **Domain-Wissen „Anwenden"-Button** rollt Linkart + Empfehlung auf alle Verlinkungen der Domain aus (kundenübergreifend)
- Bug gefixt: **Auslagen-Filter `negative_marge`** filtert nun korrekt (`marge < 0` statt String-Match)

**KI-Welle:**
- **KI-Cluster-Vorschlag** (`/lam/ki-tags-vorschlag`): Stub `clusterStub()` durch echten Claude-Aufruf ersetzt. KI darf nur existierende Tag-Slugs vorschlagen, Vorschläge werden vor dem Setzen vom Mensch bestätigt.
- **KI-Recherche** (`/lam/ki-recherche-domain`): Crawlt Startseite + Impressum (probiert `/impressum`, `/imprint` etc.), schickt an Claude. Speichert Kurzbeschreibung in `lam_domains.ki_kurzbeschreibung` + setzt `impressum_url` automatisch.
- **KI-Spalten-Mapping beim Import** (`/lam/ki-spalten-mapping`): Button im Historien-Import-Wizard, fragt Claude nach Mapping anhand Header + Beispielwerten (Heuristik-Fallback ohne API-Key).
- **KI-Domain-Matching** (`/lam/ki-domain-matching`): Neue Sicht `/lam/ki-vorschlaege` mit „Welche Pool-Domain passt zu Kunde X?". Basis: Linkziele + Tags + SI. Heuristik-Fallback ohne API-Key.

**Imports erweitert:**
- **XLSX-Upload** zusätzlich zu CSV (Historien-Import) — eigener Parser [`services/XlsxReader.php`](services/XlsxReader.php) ohne externe Library (XLSX = ZIP+XML, mit SharedStrings + Datum-Erkennung)
- **Multi-Upload** für Linkprofil-CSV (bis 20 Dateien gleichzeitig mit Sammelbericht)

**Excel-Exports:**
- **Linkprofil-Excel im BKK-Layout** (`/lam/linkprofil-excel?customer_id=X`): 11 Datenspalten + Statistik-Block rechts + AutoFilter + FreezePane. Eigener [`services/XlsxWriter.php`](services/XlsxWriter.php) ohne externe Library.
- **Quartals-Auslagen-Excel** (`/lam/auslagen-excel?customer_id=X&jahr=Y&quartal=Q`): Posten + Summen-Block mit Σ extern/weiterverrechnet/Marge.

**Settings:**
- Neuer Tab **„LAM"** unter `/admin/settings?tab=lam`: Monitoring-Intervall (15/30/60/120/360/720/1440 Min), Sistrix-Veraltet-Schwelle, Linkakquise-Ohne-Antwort-Tage, Sitewide-Cluster-Schwelle.
- **Sistrix-Credits-Manuelle-Korrektur** im Sistrix-Tab: JSON `{wert, wochenstart, gesetzt_am}` wird automatisch am Montag verworfen. `SistrixService::wochenStatus()` respektiert die Korrektur.
- **Monitoring-Cron** läuft jetzt alle 5 Min (Tick) statt täglich 03:00; das eigentliche Intervall wird aus Settings gelesen und nur Maßnahmen mit `letzter_check < NOW() - INTERVAL X MINUTE` werden geprüft (max 200 pro Tick).

**Schema-Migrationen:**
- `lam_massnahmen.monitoring_stumm_bis DATE NULL`
- Neue Tabelle `lam_aufgaben` (id ULID, typ, bezug_typ, bezug_id, titel, beschreibung, faellig_am, zustaendig_user_id, status, erledigt_am)
- Settings: `lam_monitoring_intervall_minuten`, `lam_sistrix_wochenkontingent`, `lam_sistrix_veraltet_monate`, `lam_ohne_antwort_tage`, `lam_sitewide_schwelle`, `sistrix_credits_korrektur`

**Welle 22.05.-Spätabend ist komplett durch:** alle 8 offenen Punkte abgearbeitet.

---

## 15. Welle 22.05.2026-Spätabend (Audit + Bulk + Crawler + Asana)

**Audit-Log systematisch:** zentrale Helfer `LamService::audit()` + `auditBulk()`. Hooks an:
- `aktualisiereDomainFeld` → `domain.update`
- `aktualisiereMassnahmeFeld` → `massnahme.update`
- `loescheMassnahme` → `massnahme.delete`
- `aktualisiereKontaktVerifikation`, `aktualisiereKonditionVerifikation` → `*.verifikation`
- Alle vier Bulk-Methoden (`bulkAktualisiereDomains/Anbieter/Verlinkungen/Massnahmen` + `bulkAktualisiereLinkoptionen`) → `*.bulk_*` (konsolidierter Eintrag mit `ist_bulk=1` + `anzahl_betroffen`)
- `setzeClusterEmpfehlung` → `cluster.empfehlung_setzen`
- `klassifiziereVerlinkungenBulk` → `ki.klassifikation_bulk`
- `crawleAnbieterAusImpressum` → `domain.impressum_crawl`
- Asana-Aktionen → `massnahme.asana_*`
- Dashboard-„Letzte Aktivitäten" liest jetzt primär aus Audit-Log (Fallback bleibt für Leer-Tabelle)
- Eigene Sicht [`/lam/audit-log`](views/lam/audit-log.php) als neuer Tab mit Filter (Entität, Aktion, Datum, Bulk-only) + Tab-Eintrag in `_tabs.php`

**Bulk-Erreichbarkeit:** `pruefeDomainErreichbarkeitBulk()` + Endpoint `/lam/domain-erreichbarkeit-bulk` + Button „🩺 Erreichbarkeit" in Linkquellen-Bulk-Toolbar (max 500, ~200ms pro Domain, 5-Min-Timeout-Cap).

**Aufräum-Wizard Klar/Unsicher-Split:** `/lam/linkprofil/aufraeumen` zeigt jetzt zwei Sektionen — „✓ Klar klassifiziert" (≥80% Verlinkungen bereits markiert, einklappbar, mit „Alle übernehmen"-Bulk) und „⚠ Unsicher" (Standard-Tabelle für die echte Arbeit). Bulk-Aktion setzt eine Empfehlung gleichzeitig auf alle klar klassifizierten Cluster.

**Anbieter-aus-Impressum-Crawler:** `crawleAnbieterAusImpressum()` crawlt Impressum (probiert `/impressum`, `/impressum/`, `/impressum.html`, `/imprint`, `/about-us`), schickt Klartext an Claude für strukturierte Extraktion (Firma, Rechtsform, Adresse, Mail, Telefon, Geschäftsführer, UStID, Konfidenz). Bei bestehender Firma: Notiz anhängen statt Dublette. Bei neuer Firma: Anbieter + Haupt-Kontakt (mit Vor-/Nachname-Split) anlegen + an Domain hängen. Button „🤖 aus Impressum" im Anbieter-Block des Linkquellen-Details (nur wenn noch kein Anbieter gesetzt).

**Charts + PDF-Reports:**
- **Donut-SVG** auf Linkprofil-Statistik-Seite (follow/nofollow/unbekannt-Verteilung mit %-Mittel-Label)
- **Marge-Trend-Sparkline** im Dashboard-Auslagen-Widget (12 Monate, SVG-Polyline, grün bei positiv / rot bei negativ)
- **PDF-Druck-Button** „🖨️ Als PDF drucken" auf Statistik-Seite mit Print-CSS (`@media print` versteckt Sidebar/Topbar/Filter/Buttons, A4-Format, Arial 10pt, page-break-inside: avoid für Cards)

**Mehrmandantenfähigkeit (Vorbereitung):** Migrations-Skript [`scripts/migrate-lam-multitenancy.php`](scripts/migrate-lam-multitenancy.php) (idempotent) fügt `mandant_id VARCHAR(40) DEFAULT 'thoxan'` zu allen 21 LAM-Tabellen + neue Stammdaten-Tabelle `lam_mandanten` mit Default-Eintrag „Thoxan Communications GmbH". `LamService::aktuellerMandant()` als Hook für später (Filter-Logik noch nicht aktiv, gemäß Spec §12 „im MVP fix thoxan"). Migration gelaufen, alle 21 Tabellen sind erweitert.

**Asana-Anbindung Phase 1 + 1b:** Der bestehende `AsanaService.php` (war schon umfangreich da, READ-ONLY + `createTask`) wurde um `listTasksInSection()` ergänzt. LAM-spezifische Layer in `LamService::asana*()`:
- **Phase 1:**
  - `asanaTasksFuerMassnahme(id, suche?)` — sucht Tasks in der Kunden-Section
  - `asanaVerknuepfeMassnahme(id, taskGid)` — setzt `asana_task_gid` + cacht Task-JSON
  - `asanaEntkoppleMassnahme(id)`
  - `asanaAktualisiereMassnahme(id)` — refresh des Caches
  - `setzeAsanaKundenKonfig(customer_id, projekt+section)` — Endpoint vorhanden, UI-Pflege wird über Customer-Edit gemacht
- **Phase 1b (KI-Felder-Extraktion):**
  - `asanaExtrahiereFelder(id)` — Claude extrahiert linkquelle_url, anbieter_name, preis, linkziel_url, linktext, thema, buchungstyp, geplant_am, notiz (Heuristik-Fallback ohne API-Key: Regex auf URLs + Preis + Datum)
  - `asanaUebernehmeFelder(id, vorschlaege)` — schreibt nicht-leere Felder in Maßnahme, OHNE bestehende Werte zu überschreiben (Schutzregel)
- **UI:** Asana-Banner mit 3 Zuständen im Maßnahmen-Detail:
  - Nicht konfiguriert → Link auf Einstellungen
  - Suchen → Inline-Input für URL/GID + Modus „aus Asana-Section wählen" mit Suche
  - Verknüpft → Card mit Task-Name (Permalink), Status, Fälligkeit, Assignee + Buttons „↻ aktualisieren / 🤖 Felder extrahieren / entkoppeln"
- **7 neue API-Endpoints** unter `/api/v1/lam/asana-*.php`
- **Schema-Migration:** `customers` um `asana_projekt_gid/name + asana_section_gid/name` erweitert (`lam_massnahmen.asana_task_gid + asana_zuletzt_synchronisiert_am + asana_task_cache` waren schon da).
- **Phase 2 (Push LAM → Asana, Webhook-Rück-Sync, Kommentar-Spiegelung)** bewusst offen — Phase 1 + 1b reichen für den Lese-Workflow.

**Was wirklich noch offen ist (bewusst out-of-scope):**
- ✗ Mail-Integration (IMAP/SMTP) — separates globales Thema außerhalb LAM
- ✗ Asana Phase 2 (Push, Webhooks) — kommt mit konkretem Bedarf
- ✗ Externe Logins für Kunden/Texter/Vermittler — laut Spec explizit nicht geplant
- ✗ Aktive Mandant-Filterung (Schema-Vorbereitung steht) — wartet auf zweiten Mandant

**Migrations-Marker dieser Welle:**
- `customers` + `asana_projekt_*` / `asana_section_*`
- 21 × LAM-Tabelle + `mandant_id VARCHAR(40) DEFAULT 'thoxan'`
- Neue Tabelle `lam_mandanten`

---

## 13. Audit gegen Prototyp-Doku (22.05.2026)

Nach gründlicher Sichtung von [`lam-spezifikation.md`](lam-prototyp/lam-prototyp/docs/lam-spezifikation.md), [`lam-arbeitsstand.md`](lam-prototyp/lam-prototyp/docs/lam-arbeitsstand.md), [`lam-briefing-01b-pipeline-status.md`](lam-prototyp/lam-prototyp/docs/lam-briefing-01b-pipeline-status.md), [`Briefing_Linkprofil-Analyse_Claude-Code.md`](lam-prototyp/lam-prototyp/docs/Briefing_Linkprofil-Analyse_Claude-Code.md) und [`lam-briefing-05-asana.md`](lam-prototyp/lam-prototyp/docs/lam-briefing-05-asana.md).

### A) Echte Lücken — fehlen für „vollständige LAM-Migration"

Diese Punkte stehen explizit in der Prototyp-Spec oder den umgesetzten Briefings, sind im KI-Tool aber NICHT umgesetzt:

| # | Lücke | Quelle | Größenordnung |
|---|---|---|---|
| 1 | **Maßnahmen-Status-Pipeline auf 7 Status umstellen** (`idee → akquise → bei_kunde → beauftragt → bei_anbieter → live → archiv`). Aktuell: 6 alte Werte (vorgeschlagen/geplant/beauftragt/live/storniert/wiederkehrend). Kanban-Spalten und Whitelist in `aktualisiereMassnahmeFeld()` müssen angepasst, Daten-Migration alter Werte. | Briefing 01b S.6, Übersetzungstabelle | M (Service-Whitelist + Kanban-Spalten + Datenmigration ca. 2h) |
| 2 | **Vorgangstyp-Vokabular angleichen** auf Spec-Werte (`erstveroeffentlichung`, `re_veroeffentlichung`, `sammelbuchung`, `nachbuchung`). Aktuell falsche Werte im Drawer (`nachbestueckung`, `austausch`, `verlaengerung`, `aktualisierung`). | Spec §3.2 MASSNAHME | S (Dropdown ersetzen, 15 Min) |
| 3 | **Buchungstyp als Dropdown** mit 6 Spec-Werten (`gastartikel`, `advertorial`, `pressemitteilung`, `interview`, `verzeichnis`, `startseite`). Aktuell Free-Text-Input. | Spec §3.2 KONDITION | S (Dropdown statt Text, 15 Min) |
| 4 | **Linkart-Vokabular auf 17 Werte erweitern** (`Spam`, `Branchenverzeichnis`, `Fachverzeichnis`, `Online-Magazin`, `Portal`, `Blog`, `Presseportal`, `Forum`, `Referenzprojekt`, `Partner`, `Sponsoring`, `Stellenbörse`, `Veranstaltung`, `Kommentarlink`, `Podcast`, `Weiterleitung`, `Sonstiges`). Aktuell nur 9 generische Werte, KI-Prompt nutzt die alten 9. | Linkprofil-Briefing §5.1 | M (Whitelist + KI-Prompt + UI-Dropdowns) |
| 5 | **Empfehlungs-Vokabular auf 5 Spec-Werte angleichen** (`lassen`, `ändern`, `löschen`, `disavow`, `gelöscht`). Aktuell 5 Eigenadditionen (`behalten`, `behalten_aendern`, `nofollow_setzen`, `abbauen`, `beobachten`). Speziell `disavow` fehlt. | Linkprofil-Briefing §5.2 | M (Datenmigration + UI + KI-Prompt) |
| 6 | **Verifikations-Workflow auf KONTAKT + KONDITION exponieren.** DB-Spalten `verifikation_status` existieren auf `lam_kontakte` und `lam_konditionen`, aber UI bietet nur für Domains den Workflow (verifizieren/veraltet-markieren/verwerfen). | Spec §3.2, §4.2 | M (Inline-Edit + Buttons in beiden Sub-Tabellen) |
| 7 | **KI-Cluster-Vorschlag** pro Domain (Button im Detail, schlägt passende Tags vor). Aktuell: Stub `clusterStub()` zeigt Alert. | Spec §5.4, §9 Domain-Detail | M (Service + Endpoint + UI; ähnlich KI-Klassifikation) |
| 8 | **KI-Recherche** pro Domain (Button im Detail, recherchiert Eigentümer/Impressum/Themenschwerpunkt). Aktuell: Stub `kiRechercheStub()`. | Spec §5.4 | M (Crawl + Claude-Prompt + Notiz-Update) |
| 9 | **KI-Spalten-Mapping beim Import** statt Heuristik (Spec §5.3 Flow 1). Aktuell: Historien-Import nutzt nur fixe Spaltennamen-Heuristik. Briefing 04 sah Claude-Mapping bei API-Key-Vorhandensein vor. | Spec §5.3, Briefing 04 | M (Claude-Aufruf vor Mapping-UI) |
| 10 | **Excel-Upload (.xlsx) zusätzlich zu CSV** — Spec §5.3 verlangt explizit Excel-Import. Aktuell akzeptieren beide Imports (Linkprofil + Historien) nur CSV/TSV. | Spec §5.3, Briefing 04 §3 | M (PhpSpreadsheet-Anbindung, ggf. nur Convert-to-CSV) |
| 11 | **Auslagen-Sonderfälle `sammelposten` und `jahresueberhang` in UI** — DB hat die Werte vorgesehen, Filter-Chips kennen nur normal/storno/negative_marge/intern. Akzeptanztest 3 (Jahresübergang Q4→Q1) und Test 8 (Sammelposten) sind so nicht abbildbar. | Spec §4.3, §7 Test 3+8 | S (zwei zusätzliche Chips + Sonderfall-Logik im Service) |
| 12 | **„inkl. Text"-Hinweis bei Maßnahmen-Anlage** prominent zeigen, wenn die gewählte Kondition `inkl_text=true` hat (Spalte existiert in `lam_konditionen`). | Spec §7 Test 5 | S (Drawer-Erweiterung) |
| 13 | **Plan-B-Workflow** im Anlegen-Drawer: aus bestehender Maßnahme einen Plan-B erzeugen, `plan_a_massnahme` automatisch setzen. Aktuell ist das Feld in DB+Detail-View vorhanden, aber kein UI-Trigger. | Spec §3.2, §7 Test 6 | S (Button im Maßnahmen-Detail + Drawer-Vorbelegung) |
| 14 | **Sammelposten-Widget im Dashboard** — Spec §7 Test 8 fordert eigene Übersicht, damit Sammelposten die reguläre Maßnahmen-Liste nicht verschmieren. Fehlt. | Spec §7 Test 8 | S (Filter-Variante der Maßnahmen-Liste) |
| 15 | **„Veraltet markieren" legt Update-Aufgabe an** — aktuell setzt der Button nur den Status. Spec sieht Aufgaben-Tracking vor. | Spec §6 Flow 2 | M (Aufgaben-Tabelle oder Korrespondenz-Eintrag mit Typ `update_faellig`) |
| 16 | **Excel-Export im historischen BKK/SMV-Layout** für Linkprofil (12 Datenspalten + Statistik-Block, Arial 10, AutoFilter, FreezePane, hellgrauer Header). Aktuell: generischer CSV. | Prototyp Briefing 07 + §11 | M (PhpSpreadsheet + Template-Datei) |

### B) Verbesserungen aus dem Prototyp, die noch fehlen (Komfort/Polish)

| # | Polish-Punkt | Quelle | Größenordnung |
|---|---|---|---|
| 17 | **LAM-Audit-Log systematisch füllen** — Tabelle `lam_audit_logs` existiert, wird nirgendwo geschrieben. Prototyp protokolliert Status-Wechsel, Verifikation, Bulk-Aktionen (mit `recordBulk` konsolidiert). | Spec §12, Arbeitsstand §Audit-Log | M (Hooks an alle Schreib-APIs) |
| 18 | **Bulk-Erreichbarkeitsprüfen für Linkquellen-Pool** — Sistrix-Bulk existiert (SI/Alter/DP/Alles), HTTP-Erreichbarkeits-Bulk fehlt. | Arbeitsstand Briefing 08 | S (analog Sistrix-Bulk) |
| 19 | **Monitoring-Intervall konfigurierbar** (15/30/60/120/360/720/1440 Min) statt Cron fix auf 03:00 täglich. Wert in Settings, IntervallHelper + dynamischer Cron. | Arbeitsstand Briefing 08 Sprint 1 | M (Settings-Eintrag + Cron-Refactor) |
| 20 | **Mute pro Maßnahme mit Ablaufdatum** statt nur ein/aus. Feld `monitoring_stumm_bis` (Datum), nach Ablauf automatisch aktivieren. | Arbeitsstand §Diskussionspunkt 3 | S (Schema-Migration + UI-Datepicker) |
| 21 | **Domain-Wissen „Anwenden"-Button** — alle Verlinkungen einer Domain auf den manuell gepflegten Wert ausrollen. Aktuell: Wissen wird gespeichert, ist aber nicht idempotent verteilt. | Arbeitsstand §Domain-Wissensbasis | S (Bulk-Update-Endpoint) |
| 22 | **Aufräum-Wizard mit Klar/Unsicher-Split + Bulk-Annahme bei Confidence ≥ 0.8** — aktuell schlichte Cluster-Liste. | Linkprofil-Briefing Phase 2.5 | M (Wizard-UI mit Phasen) |
| 23 | **Sistrix-Credits-Manuelle-Korrektur** über UI (Settings-Wert + Wochenstart-Timestamp). Heute ist Kontingent nur aus Snapshot-Count berechnet. | Arbeitsstand Briefing 06 | S (Settings-Form-Erweiterung) |
| 24 | **„Wartezeit_bis" auf Domain pflegen** — Feld existiert (Spalte ist da), UI kennt es nicht. Spec sieht „temporär ausgesetzt nach Buchung Mindestabstand" vor. | Spec §3.2 DOMAIN | S (Inline-Edit + Filter „verfügbar ab heute") |
| 25 | **Asana-Lese-Sync (Briefing 05 Phase 1)** — im Prototyp komplett gebaut: Personal-Access-Token in Settings, Kunde-Zuordnung zu Projekt+Section, Banner im Maßnahmen-Detail, KI-Felder-Extraktion. Im KI-Tool: gar nicht migriert. | Arbeitsstand Briefing 05, Briefing-Datei | L (komplettes Modul, 1–2 Tage) |
| 26 | **Anbieter-Beziehungsstatus `abgekuehlt`** als Wert ergänzen (DB hat nur neu/etabliert/vertrauensvoll, Spec verlangt zusätzlich `abgekuehlt`). | Spec §3.2 ANBIETER | XS (Dropdown-Wert ergänzen) |
| 27 | **Multi-Upload im Linkprofil-CSV-Import** (bis zu 20 Dateien gleichzeitig mit Sammelbericht). Prototyp hat das, wir nicht. | Arbeitsstand Briefing 08 Nach-Korrekturen | S (Frontend + Loop im Service) |
| 28 | **Quartals-Auslagen-Excel-Export pro Kunde** statt generischem CSV (Spec §5.5: Excel mit Sonderfall-Highlighting). | Spec §5.5 + §9 Auslagen | M (PhpSpreadsheet + farbige Sonderfälle) |

### C) Wunschthemen jenseits des Prototyps

| # | Wunschthema | Quelle | Begründung |
|---|---|---|---|
| 29 | **IMAP/SMTP Mail-Integration** (Empfang + Versand) für Korrespondenz | Out-of-Scope MVP, Diskussionspunkt 2 | Tom hat „Empfang + Versand" entschieden, ca. 4 Sprints |
| 30 | **Vorlagen-Bibliothek** für Anschreiben | Out-of-Scope MVP | Teil der Mail-Phase |
| 31 | **KI-Domain-Matching** (welche Pool-Domain passt zu welchem Kunden) | Spec §10 out-of-scope, Phase 2/3 | Pendant zu KI-Klassifikation, Recommender |
| 32 | **Asana-Phase 1b+** (Push LAM→Asana, Webhook-Rück-Sync, Kommentar-Spiegelung als Korrespondenz) | Arbeitsstand Briefing 05 offen | Aufbauend auf Phase 1 |
| 33 | **Charts/Verlaufsdiagramme** statt Tailwind-Bars | Spec §10 out-of-scope | Reporting-Komfort |
| 34 | **Aktive Mehrmandantenfähigkeit** — mandant_id-Spalten gibt es noch nicht überall, Multi-Tenant nicht eingespielt | Spec §12 | Erst relevant, wenn weiterer Mandant kommt |
| 35 | **Crawl-basierte Pool-Discovery** (alte Domains automatisch nach neuen Backlinks crawlen) | Nicht-im-MVP-Liste Arbeitsstand | KI/Automation-Wunsch |
| 36 | **Anbieter-aus-Impressum-Crawler** | Spec §9 Domain-Detail erwähnt es als Aktion | Wäre KI-Recherche-Variante |
| 37 | **PDF-Reports für Kunden** (Quartals-Linkprofil-Audit als PDF) | Spec §1 (statt Excel/PDF an Externe) | Externe Kommunikation |
| 38 | **Mail-Tracking** (Pixel, Klicks) für Versand | Nicht-im-MVP-Liste | Out-of-scope, ggf. nie relevant |
| 39 | **Externe Logins** (Kunde/Texter/Vermittler) | Spec §10 nicht geplant | Bleibt out-of-scope |
| 40 | **Automatische Re-Anfragen** an Anbieter nach X Tagen | Nicht-im-MVP-Liste | Korrespondenz-Komfort, später |

### Inkonsistenz-Befunde

- ~~**`negative_marge`** Filter funktioniert aktuell nicht.~~ ✓ **In `listeAuslagen()` als `(marge < 0)` umgesetzt** (Service-Code prüft `$filter['sonderfall'] === 'negative_marge'` und ersetzt durch Aggregation).
- ~~Vorgangstyp-Werte in Maßnahmen-Drawer sind selbst erfunden.~~ ✓ **Auf Spec-Vokabular angeglichen** (`erstveroeffentlichung`, `re_veroeffentlichung`, `sammelbuchung`, `nachbuchung`) — als `LamService::MASSNAHME_VORGANGSTYP` zentral.
- ~~`lam_audit_logs` wird nirgendwo geschrieben.~~ ✓ **30 Audit-Hooks** in `LamService` verteilt: Status-Wechsel, Verifikation, Bulk-Aktionen (über `auditBulk`), CRUD-Operationen.

---

## 16. Welle 11.06.2026 (Vermittler-Logik + Asana-Integration + Tracker-Verifikation)

### A) Datenseite — globale Bereinigung

- **Vermittler-Flag** auf `lam_anbieter.ist_vermittler` korrigiert: NUR performanceliebe, RRDS, eology (sonst 0). 28 betroffene Stamm-Datensätze.
- **`lam_anbieter.ist_betreiber = 0`** bei allen Vermittlern erzwungen (war 14× falsch auf 1).
- **`lam_domain_anbieter`** Junction-Tabelle: 6.540 Verbindungen mit Vermittler-Anbieter auf `rolle='vermittler'` + `ist_vermittler=1` + `ist_betreiber=0` umgestellt.
- **2.363 Domains** mit Vermittler als direktem `anbieter_id` auf den echten Betreiber aus `lam_konditionen.via_anbieter_id` umgehängt. Snapshot in `lam_anbieter_umhaengung_log` (Rollback möglich).
- **Anbieter-Dubletten konsolidiert**: performanceliebe ×14, eology ×12, RRDS ×2 → je 1 Master, Rest soft-deleted.
- **Anbieter = Mensch**, Firma als Subzeile: Cleanup für 4 Bestandsanbieter (Kriterio UG → Elliot Aghedo, ARKM Online Verlag UG → Sven Oliver Rüsche, etc.). Plus Hook in `speichereKontakt()` + Impressums-Crawl: bei firmenartigem Anbieter-Namen + neuem Kontakt → Name auf Person, Firma in `firma`-Spalte gesichert.

### B) Anzeige-Layer abgesichert

- Linkquellen-Liste + Vorschlagslisten-Detail: `anbieter_name` über COALESCE — Vermittler wird im UI durch echten Betreiber aus Konditionen ersetzt; `anbieter_ist_vermittler`-Flag bezieht sich auf den **angezeigten** Anbieter.
- **Vermittler-Badge** in beiden Listen — kleine Amber-Pill „Vermittler" wenn nur Vermittler bekannt.
- Vorschlagslisten-Detail-Anbieter-Sort: `ORDER BY ist_vermittler ASC, rolle='betreiber' DESC` — Betreiber gewinnt grundsätzlich.

### C) Asana-Integration (LAM ↔ Asana, Phase 1c)

- **Beim Verknüpfen eines bestehenden Tickets**: Section-Picker mit Default-Section vom Kunden + Task-Suche → Klick verknüpft, **Felder aus Ticket-Beschreibung automatisch in leere Linkoption-Felder importiert** (Parser für Linkziel, Linktext, Kontext, Artikelthema, Bemerkungen, Beispielartikel, Preis).
- **html_notes** statt plain notes beim Anlegen: Linktext mit `<u>…</u>` unterstrichen (im gesamten Kontext-Absatz mit Wortgrenzen), URLs als `<a href>` klickbar, Block-Labels fett.
- Asana-Ticket-Reihenfolge: **Linkziel → Linktext → Kontext für Linkeinbau → Artikelthema → Bemerkungen** (Bemerkungen-Label immer, auch wenn leer).
- DP-Anzeige im Ticket mit Tausender-Trennzeichen (`SI 0,358, DP 1.708`).
- Kanonische Domain-URL (mit/ohne www) per `<link rel="canonical">`-Lookup vor Ticket-Anlage.

### D) Linkoption-Erweiterungen

- **Neues Feld** `kontext_einbau TEXT` (parallel zu `artikelthema`) — zwei separate Textareas im UI, jeweils mit eigenem Block im Asana-Ticket.
- **`preis_anbieter DECIMAL(10,2)`** — interner Einkaufspreis, NIEMALS in Asana/Excel-Kunde.
- **`beispielartikel_url VARCHAR(500)`** — optionaler Link zu Beispielartikel/Kategorie der Zieldomain.
- **`mit_anbieternennung TINYINT(1)`** — Flag für KI-Kontext: Kundenname darf im Kontext-Absatz genannt werden ja/nein.
- **KI-Vorschläge** (`✨`-Button am Artikelthema-Feld) — 3 Vorschläge auf Basis von Linkziel-Meta + Domain-Meta, mit/ohne Markennennung je nach Flag. Modell: Claude Sonnet 4.6.
- **Filter+Sort-Sidebar** (360 px, Stil chat-sidebar) auf Vorschlagslisten-Detail mit Status-Chips, Counter, Sort-Optionen.
- **Drag & Drop** zur manuellen Reihenfolge-Anpassung (Position-Spalte) mit Persistenz via `/lam/linkoption-reorder`.
- Status-Pipeline: `in_planung` als erster Status vor `vorgeschlagen` ergänzt.

### E) Linkquellen-Polish

- **wartezeit_bis** Inline-Pflege mit Datepicker (war schon da) + **2 neue Filter-Chips**: „in Wartezeit" und „verfügbar" — Service-Query nutzt `CURDATE()` als Schwellenwert.

---

## 13. Globale Patterns dieser Migration

- **`assets/css/thx-components.css`** — Stilbibliothek
- **`views/lam/_tabs.php`** — Modul-Navigation
- **`services/LamService.php`** — gesammelte Service-Methoden
- **API-Routen** — alle unter `/api/v1/lam/`, alle Manager-only via `Auth::hasRole(ROLE_MANAGER)`
- **Eigene Detail-Seiten** statt Modals: `/lam/linkquellen/{id}`, `/lam/anbieter/{id}`, `/lam/linkoptionen/{id}`, `/lam/massnahmen/{id}`, `/lam/vorschlagslisten/{id}`
- **Sistrix-Service** zentral mit Caching (1× pro Domain+Teil+Tag), Rate-Limit (300ms), Wochenkontingent-Check
- **Verschlüsselte API-Keys** via `Core\Settings::get('sistrix_api_key')` — AES-256-GCM
- **Cap-Schutz** auf allen LAM-Routen über `capMiddleware(CAP_LAM)`

Konvention: Bei jedem neuen Feld/Eintrag im Modul → Service-Methode mit Whitelist erlaubter Felder → API-Endpunkt → View-Anbindung.
