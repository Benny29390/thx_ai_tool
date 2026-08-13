# Customer Portal — Projektplan (stufenweise Umsetzung)

Arbeits- und Tracking-Dokument. Basis: `docs/Customer_Portal_Konzept.md` (Stand 23.06.2026).
**Status-Legende:** ✗ offen · ◐ in Arbeit · ✓ fertig

---

## 1. Zusammenfassung des Konzepts

**Ziel:** Kund:innen bekommen eine **reduzierte, kuratierte** Ansicht dessen, was Thoxan für sie tut (laufende Projekte, Ergebnisse, eingesetzte Tools, aktueller Stand). Sie sehen **nur ihre eigenen** Projekte inkl. aller zugeordneten Websites, können **Fragen via Kommentar** stellen, aber **keine Daten verändern**.

**Vier Leitplanken:**
1. **Read-only auf Daten, nutzbar bei Tools.** Darstellende Inhalte (Status, Ergebnisse, Meilensteine, Dokumente) sind nur lesbar; einzige Schreibaktion = Kommentieren. **Tools** sind die Ausnahme: freigeschaltete Tools darf der Kunde im **definierten Funktionsumfang** aktiv benutzen. → Zwei Berechtigungsarten: *Lesen* vs. *Nutzen*.
2. **Kuratierte Sichtbarkeit (opt-in).** Nichts ist sichtbar/nutzbar, bis es **pro Kachel und pro Kunde** explizit freigeschaltet wird.
3. **Wiederverwendung bestehender Logik.** Keine Parallelwelt — die vorhandene Auth-/Rechtelogik wird um eine zusätzliche Benutzergruppe (ähnlich Gäste, mit abgestuften Rechten) erweitert.
4. **Datensouveränität.** Lokale Verarbeitung bleibt; KI-Glove-Pseudonymisierung greift vor jedem externen KI-Aufruf.

**Rollen:** Team (volle Rechte je interner Rolle, kann die Kundenansicht **sehen und gestalten**), **Customer** (Lesen+Kommentieren auf freigeschaltete Darstell-Kacheln, Nutzen freigeschalteter Tools des **eigenen** Kunden), Gäste (minimal). Mehrere Customer-User pro Kunde (n:1), gemeinsamer Sichtbarkeitsumfang und gemeinsame Kommentare. Mehrere Websites je Projekt (Website ist die feinere Ebene, **keine** eigene Zugriffsgrenze).

**Permission-Matrix:** verbindet `Kunde × Modul/Tool × Sichtbarkeit/Nutzung`. Tools zusätzlich mit Funktionsumfang.

**Module (Kacheln):** Projektstatus · Ergebnisse/Reporting · Tools & Techniken (nutzbar) · Meilensteine/Timeline · Dokumente/Downloads (optional) · Kommentare. Interne Notizen **nie** sichtbar.

**Zugriffsschicht (serverseitig):** Login → Kunde auflösen → freigeschaltete Module/Tools ermitteln → Daten aus den **bestehenden** Quellen (Kundenkacheln, Projektplaner) lesen, **interne Felder serverseitig herausfiltern**, read-only ausliefern; schreibend nur Kommentare + Tool-Nutzung. **Tenant-Isolation ist der kritischste Punkt** und wird serverseitig erzwungen.

---

## 2. Einordnung in die bestehende Architektur (was wird wiederverwendet)

| Konzept-Baustein | Vorhanden im KI-Tool | Lücke / Erweiterung |
|---|---|---|
| Benutzergruppe „Customer" | `users.role` ENUM (`admin,manager,user,guest`), `user_capabilities`, `role_capabilities`, `Auth::can()`, `Auth::isReadOnly()` (Gast read-only, Write-Block in `api/handler.php`) | Rolle `customer` ergänzen; eigener Caps-Satz; Guest-artiger Write-Block + Portal-Routing |
| Kunden-Zuordnung (n:1) | `user_customers` (+ `role_customers`), `Auth::canAccessCustomer()`, `Auth::loadUserCustomers()` | Customer-User = **genau 1** Kunde; serverseitig hart erzwingen |
| Kunden-Kacheln (Datenquelle) | `customer_cards` (+ `CustomerCardService`, `customer-steckbrief.php`), Typen, `target_tab`, `is_system`, Knowledge-Sync | **Sichtbarkeits-Flag pro Karte** für Kunden fehlt; „interne Notizen nie sichtbar" fehlt; serverseitige Feld-Filterung |
| Projekt-/Statusdaten | `pp_plans`, `pp_plan_rows` (Projektplanner), `risiko_*`, `plan_status` | Kundengerechte, gefilterte Lesesicht |
| Kommentare | **`pp_plan_feedback`** + externe Share-Sicht (`views/projektplanner/share.php`), `pp_plan_shares`/`pp_person_shares`/`pp_multi_shares` | Auf Kunden-Dashboard ausweiten, Sichtbarkeit auf Account-Ebene |
| Permission-Matrix | — | **Neu:** Tabelle `customer_portal_permissions` (Kunde × Modul/Tool × Flag [+ Funktionsumfang]) |
| Audit | `permission_audit_log`, `\Core\AuditLog::record()` | Kundenzugriffe + Kommentare protokollieren |
| Datensouveränität / KI-Glove | lokale LLM/Wissen V2 vorhanden | ⚠️ **„KI-Glove"-Pseudonymisierung im Code aktuell nicht auffindbar** — Annahme prüfen (siehe Risiken) |
| Mehrere Websites/Projekt | `customers.settings['domains']`, Website-Kacheln | bereits vorhanden, nur lesend einbinden |

---

## 3. Architektur-Entscheidungen (vor Phase 1 zu klären, mit Empfehlung)

1. **Customer als eigene Rolle vs. eigener Account-Typ.** → *Empfehlung:* `users.role = 'customer'` (ENUM erweitern) + Caps-Satz. Reuse Login/Session/2FA. Ein `customer`-User ist über `user_customers` an **genau einen** Kunden gebunden (DB-/Code-invariante).
2. **Wo lebt die Kunden-Sicht?** → *Empfehlung:* Eigene Portal-Routen unter z. B. `/portal/*` (eigenes schlankes Layout, **kein** Admin-Layout), die dieselben Datenquellen lesend nutzen. Team sieht dieselbe Sicht über einen „Als Kunde ansehen"-Modus im bestehenden Steckbrief.
3. **Kachel-Sichtbarkeit: pro Karte oder pro Modul?** Konzept = pro Kachel/Modul × Kunde. → *Empfehlung:* zweistufig — Modul-Ebene in `customer_portal_permissions` (welche Modultypen) **und** ein `customer_visible`-Flag pro `customer_cards`-Eintrag (welche konkrete Karte). Default beides „aus".
4. **Interne Felder.** → *Empfehlung:* Whitelist-Ansatz pro Modul (nur explizit freigegebene Felder gehen raus), nicht Blacklist. Serverseitige DTO-/Mapper-Schicht.
5. **Tools-Funktionsumfang.** → später am Prototyp; Datenmodell trägt ein `tool_scope` (JSON) im Permission-Eintrag.

> Diese 5 Punkte sind die einzigen echten Vorab-Entscheidungen. Alles Weitere wird laut Konzept iterativ am Prototyp geschärft.

### Getroffene Entscheidungen (23.06.2026)
1. **Customer-Modell:** Rolle `customer` (ENUM erweitern), Reuse Auth/Session/Caps. Customer-User an **genau 1 Kunden** gebunden.
2. **Portal-Ort:** Eigenes schlankes Layout unter `/portal/*`; Team-Vorschau über „Als Kunde ansehen".
3. **Sichtbarkeit:** Zweistufig — Modul-Freischaltung pro Kunde (Permission-Matrix) **und** `customer_visible`-Flag pro Steckbrief-Karte. Default überall „aus".
4. **Pilot-Kacheln (Phase 3):** Projektstatus, Ergebnisse/Reporting, Meilensteine/Timeline (**read-only**). Pilot-**Tool** verschoben auf Phase 5.
5. **Fixe Defaults:** Feld-Filterung per **Whitelist** (nur freigegebene Felder verlassen den Server); Tool-Funktionsumfang als `tool_scope` (JSON) im Permission-Eintrag (Phase 5).

---

## 4. Stufenplan

### Phase 0 — Freigabe & Fundament-Entscheidungen ✗
**Ziel:** Konzept + die 5 Architektur-Entscheidungen (Abschnitt 3) bestätigt. Offene Punkte als Backlog (Abschnitt 6).
**Ergebnis:** Go für Phase 1, Pilot-Kacheln festgelegt (Vorschlag: **Projektstatus, Ergebnisse, ein Tool**).
**Risiko:** gering. Kein Code.

### Phase 1 — Rollen- & Datenfundament ✗
**Ziel:** Customer-User existiert, ist hart an einen Kunden gebunden, kann sich einloggen und landet in einem (noch leeren) Portal; kommt **nirgends** in den Admin-Bereich.
**Bausteine:**
- DB: `users.role` ENUM um `customer` erweitern (Migration in `core/App.php`).
- DB: `customer_portal_permissions` (`customer_id`, `module_key`, `enabled`, `tool_scope` JSON NULL, Timestamps).
- DB: `customer_cards.customer_visible TINYINT DEFAULT 0`.
- `core/Auth.php`: `isCustomer()`, Bindung Customer-User → genau 1 Kunde; `customers()` liefert nur diesen.
- `api/handler.php` + `core/App.php`: Customer-User → nur `/portal/*` + `/auth/*`; alles andere geblockt (analog Guest-Write-Block, aber strenger: read-gating).
- User-Verwaltung: Customer-User anlegen/einladen (reuse Invite-Flow), Zuordnung zum Kunden.
**Ergebnis:** Login + leeres Portal-Gerüst + serverseitige Bereichs-Sperre.
**Risiko:** mittel — Routing-/Auth-Sperren müssen lückenlos sein (negativ testen!).

### Phase 2 — Server-seitige Zugriffsschicht & Tenant-Isolation ✗ (kritisch)
**Ziel:** Eine zentrale Schicht, die für einen Customer-User auflöst: Kunde → freigeschaltete Module → gefilterte Daten. Interne Felder erreichen den Client nie.
**Bausteine:**
- `services/CustomerPortalService.php`: `resolveCustomer(user)`, `enabledModules(customerId)`, pro Modul ein **Mapper** (DTO) der aus `customer_cards`/`pp_plans` nur freigegebene, kundengerechte Felder liefert.
- Tenant-Guard: jede Portal-Leseroute prüft `customer_id == user.customer_id` **serverseitig** (nicht im Frontend).
- Feld-Whitelists je Modul; „interne Notizen"/Kalkulation/Tagessätze nie ausliefern.
- `permission_audit_log`: Kundenzugriffe loggen.
**Ergebnis:** belastbare, getestete Isolation (inkl. negativer Tests: fremder Kunde, nicht freigeschaltetes Modul, internes Feld).
**Risiko:** **hoch** — der sicherheitskritischste Teil. Vor Phase 3 nicht überspringen; Security-Review einplanen.

### Phase 3 — Kunden-Dashboard (Read-only Pilot-Kacheln) ✗
**Ziel:** Sichtbares Portal mit 2–3 kuratierten, read-only Kacheln + „Als Kunde ansehen" fürs Team.
**Bausteine:**
- `views/portal/dashboard.php` (schlankes Layout, Thoxan-Design-Tokens), Kachel-Rendering wie Team-Dashboard, aber nur freigeschaltete.
- Pilot-Module: **Projektstatus** (aus `pp_plans`), **Ergebnisse/Reporting** (aus `customer_cards` KPI/Reporting), optional **Meilensteine** (aus `pp_plan_rows`).
- Team-Steuerung im Steckbrief: pro Karte `customer_visible`-Toggle + Modul-Freischaltung pro Kunde (Permission-Matrix-UI), + „Als Kunde ansehen"-Vorschau.
**Ergebnis:** erster benutzbarer Kundenbereich; Team kuratiert sichtbar.
**Risiko:** mittel.

### Phase 4 — Kommentarfunktion ✗
**Ziel:** Kunden stellen Fragen pro Kachel oder projektweit; Team wird benachrichtigt.
**Bausteine:**
- Kommentar-Objekt wiederverwenden/erweitern (Basis: `pp_plan_feedback` + externe Projektplaner-Sicht). Sichtbarkeit auf **Account-Ebene** (alle Customer-User des Kunden).
- Benachrichtigung des Teams bei neuem Kundenkommentar (E-Mail/In-App — Mechanik aus dem Chat-Zugriffsanfrage-Feature wiederverwendbar).
- Entscheidung: pro Kachel verortet vs. projektweit (Konzept offen).
**Ergebnis:** Rückkanal Kunde → Team, ohne Datenänderung.
**Risiko:** gering–mittel.

### Phase 5 — Tool-Nutzung (kuratiert) ✗
**Ziel:** Ein Pilot-Tool für Kunden aktiv nutzbar im definierten Funktionsumfang, account-scoped.
**Bausteine:**
- `tool_scope` in `customer_portal_permissions` definiert erlaubte Funktionen.
- Serverseitige Durchsetzung des Funktionsumfangs (nicht nur UI-Ausblendung); keine Stammdaten-/Fremddaten-Änderung.
- Tool + Pilotumfang am Prototyp wählen.
**Ergebnis:** erste echte Tool-Interaktion durch Kunden.
**Risiko:** hoch (Wirkungsgrenzen müssen serverseitig dicht sein).

### Phase 6 — Härtung, Audit, Rollout ✗
**Ziel:** produktionsreif.
**Bausteine:** vollständiges Audit (Zugriffe + Kommentare + Tool-Nutzung), Security-Review/Pentest der Tenant-Isolation, KI-Glove-Pfad falls Kunden-Chat/KI später kommt (Annahme klären), Customer-Onboarding (Invite, Passwort, optional 2FA), Doku.
**Risiko:** mittel.

---

## 5. Reihenfolge & Abhängigkeiten

```
Phase 0 ─▶ Phase 1 ─▶ Phase 2 (kritisch) ─▶ Phase 3 ─▶ Phase 4
                                         └▶ Phase 5 ─▶ Phase 6
```
- **Phase 2 ist das Nadelöhr** — erst wenn Isolation/Filterung steht, dürfen sichtbare Kacheln (3) und Tools (5) folgen.
- Phasen 3–5 sind iterativ pro Kachel/Tool erweiterbar (Konzept: „Stück für Stück").

---

## 6. Offene Punkte (Backlog aus dem Konzept — bewusst iterativ)

- Konkrete Datenfelder pro Kachel.
- Granularität bei Tools und Reporting; welche KPIs.
- Welche Tools mit welchem Funktionsumfang zuerst.
- Umfang/Aufbereitung historischer Daten (Timeline).
- Kommentare pro Kachel vs. projektweit.
- Benachrichtigungsverhalten bei neuen Kundenkommentaren.
- Ob Kunden Zugriff auf den lokalen Chat/KI erhalten.

---

## 7. Kritische Risiken

1. **Tenant-Isolation (höchste Priorität).** Serverseitig erzwingen, negativ testen. Ein Leck = Offenlegung fremder Kundendaten.
2. **Interne Felder.** Whitelist statt Blacklist; nie auf Frontend-Filterung verlassen.
3. **KI-Glove-Annahme.** Die im Konzept genannte Pseudonymisierungsschicht ist im Code aktuell **nicht auffindbar** — vor jeder kundenseitigen KI-Funktion klären, ob/wo sie existiert oder gebaut werden muss.
4. **Rollen-/Routing-Sperren.** Customer-User darf keine Admin-/Team-Route erreichen; lückenlos absichern.

---

## 8. Status-Tracking

| Phase | Inhalt | Status |
|---|---|---|
| 0 | Freigabe & Architektur-Entscheidungen | ✓ (23.06.2026, Abschnitt 3) |
| 1 | Rollen- & Datenfundament | ✓ Rolle/ENUM/Auth/Schema/Routing-Sperre (web+API default-deny). Team-User-Verwaltungs-UI offen (Testkunde per Script) |
| 2 | Zugriffsschicht & Tenant-Isolation | ✓ Basis: `CustomerPortalService` (serverseitige Filterung, sichere Karten-Typen, kein Zugangsdaten-Leak). End-to-End per curl verifiziert (403 auf fremde Routen/APIs) |
| 3 | Kunden-Dashboard (Pilot-Kacheln) | ✓ Read-only Kacheln + volles `main.php`-Gerüst (Sidebar/Topbar, Customer-Menü) + Team-UI `/admin/customers/{id}/portal` (Modul-Freischaltung, `customer_visible`-Toggle, „Als Kunde ansehen", Customer-User anlegen) |
| 4 | Kommentarfunktion | ✓ Rückfragen Kunde↔Team (`customer_portal_comments`), Portal- + Team-Thread, E-Mail an Team bei Kundenkommentar. End-to-end per curl verifiziert |
| 5 | Tool-Nutzung (kuratiert) | ✗ braucht Tool-Wahl + Funktionsumfang (Eingabe nötig) |
| 6 | Härtung, Audit, Rollout | ◐ Tenant-Isolation + Routing-Sperren live & getestet; Audit für User-Anlage; offen: Security-Review, Onboarding/Invite-Mail, KI-Glove-Klärung |

**Stand 23.06.2026 — live & nutzbar (Pilot):** Testzugang BKK (`kunde.bkk@thoxan-dev.de` / `Portal1234!`). Team-Steuerung: Steckbrief-Button „Kundenportal" → `/admin/customers/{id}/portal`. Offen für Folge-Iteration: Tool-Modul (Phase 5), Feinschliff Kachel-Inhalte/Texte, Invite-Mail statt Einmal-Passwort.

**Layout + KI-Chat (23.06.2026):** Portal ist 2-spaltig — links festes 360px-Chatpanel, rechts Steckbrief-Optik (Logo-Badge-Header + Tabs + 3-Spalten-Kanban `.sb-card`). Der Chat ist ein **KI-Assistent mit Team-Übernahme**: Kunde fragt → KI antwortet automatisch, **ausschließlich aus dem kuratierten Portal-Kontext** (`CustomerPortalService::aiContext()` — Status/Meilensteine/freigegebene Karten, nie Zugänge/Links/Dokumente). Antwortet das Team im Thread, pausiert die KI (`ki_active=0`, Toggle reaktiviert). Browser-verifiziert: KI-Antwort grundiert + kein Daten-Leak. Rollen in `customer_portal_comments`: team/customer/ki.

> Bei jeder Customer-Portal-Arbeit zuerst hier den Status prüfen und nach erledigtem Schritt aktualisieren.
