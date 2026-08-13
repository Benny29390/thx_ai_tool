# 04 · Linkprofil-Analyse

> Zeitraum: 17.05.2026 · Herkunft: lokal /lam-prototyp (worktree) · 48 Prompts · 9 Sinnabschnitte
>
> Sinnabschnitte sind mit `## ▸` markiert. Pro Abschnitt: Anforderung gegen Ist-Stand im KI-Tool prüfen, offene/abweichende Punkte dokumentieren, dann nächster Abschnitt.

## ▸ 1. Verortung des Linkprofil-Moduls (main vs. Worktree)

**[P01 · 17.05. 08:05]**

Lies in dieser Reihenfolge:
1. CLAUDE.md (Stack, Stilvorgaben, Bauphasen, Sparring-Verhalten)
2. docs/lam-parallel-arbeit.md (Spielregeln für die Parallel-Arbeit)
3. docs/lam-arbeitsstand.md (aktueller Implementierungsstand)
4. docs/Briefing_Linkprofil-Analyse_Claude-Code.md (das neue Modul)

Wir bauen das Modul "Linkprofil-Analyse" gemäß Briefing. Beachte
strikt Abschnitt 1 "Schutz des bestehenden Systems":
- alle Migrationen additiv
- keine Umbenennungen, keine Datentyp-Änderungen
- kein Touch an bestehenden Models/Controllern/Routes, außer für die
  explizit erlaubte Navigations-Integration und für additive
  Beziehungs-Methoden an Domain/Kunde

Wir laufen parallel zu einem zweiten Chat im selben Ordner auf main.
Vor jedem Eingriff in eine bestehende Datei: git status checken,
im Zweifel beim User nachfragen.

Vorgehen: Erst Plan-Mode für Datenmodell + Migrationen. Phase für
Phase, jeder Schritt mit Commit. Nicht das ganze Modul auf einmal.

Beginn mit der Plan-Phase: lies das Briefing komplett und schlage
einen Bauplan in 4-6 Schritten vor, jeder Schritt mit eigenem Commit.

---

**[P02 · 17.05. 08:49]**

1. ich will im main arbeiten. Bevor du ausführst, kannst du dir den aktuellen commit holen, damit du nichts überschreibst. ich lasse nicht beide chats parallel ausführen, sondern immer nur nacheinander. verarbeite automatisch neue anpassungen in details. zumal du bis auf die navigation vermutlich ohnehin erst nur im bereich linkprofil arbeitest
2. Tags aus der linkquellen-liste und aus der linkprofil-analyse sind zwei paar schuhe. bitte als linkprofil_tags anlegen, weil sich die inhalte unterscheiden
3. um charts erstmal nicht kümmern. das briefing sollte klarstellen, dass du bei überschneidungen zum bestandsystem das nicht umschreiben sollst, sondern vorhandene komponenten, variablen, styles etc. nutzt, so dass es optimal zum look and feel passt und die vorhandenen funktionen nutzt. es gibt in der linkquellen-tabelle viele schöne funktionen wie sortierung, filterung, massenbearbeitung, abfrage sistrix und erreichbarkeit etc., die wir auch integrieren sollten und nicht neu bauen müssen. orientiere dich auch gestalterisch an den vorhandenen templates
4. ja

---

**[P03 · 17.05. 08:54]**

Ne, gibt es erkenntnisse her im chat, die für das tool wichtig sind? dann starte ich einen neuen chat

---

**[P04 · 17.05. 10:03]**

Klappt mit dem neuen main nicht. Lass uns das als sideprojekt in deinem worktree bauen und später mergen

---

## ▸ 2. Datenmodell-Vorlagen, Plan-Schärfung & Umsetzung Phase 1

**[P05 · 17.05. 10:07]**

Konkrete Vorlagen für seine Migrationen + Models:

ULID-Hauptentität mit HasMandant + Status:

Model-Vorlage: app/Models/Domain.php — ULID-PK, HasMandant, SoftDeletes, fillable, casts, alle Beziehungstypen
Migration-Vorlage: database/migrations/2026_05_13_193003_create_domains_table.php — $table->ulid('id')->primary(), $table->string('mandant_id'), $table->softDeletes('geloescht_am'), Indizes, unique(['mandant_id', 'url'])
Wichtig zu kopieren: const CREATED_AT = 'erstellt_am'; und const UPDATED_AT = ...; (oder weglassen für nur-erstellt-Tabellen) — Konvention im Projekt ist deutscher Spaltenname
Status-Enum-Muster:

app/Models/Massnahme.php hat const STATUS_PIPELINE als Source-of-Truth-Konstante
Im Briefing erwähnte Enums (linkart, empfehlung, status, imported_from) am besten als Klassenkonstanten pro Model + Validator-Helper, nicht als DB-Enums (Laravel-Konvention, leichter migrierbar)
Pivot mit mandant_id:

Pivot-Model: app/Models/DomainAnbieterPivot.php — sauberes Beispiel mit using()-Aufruf vom Hauptmodel
Pivot-Migration: database/migrations/2026_05_15_110000_create_domain_kunde_table.php — $table->string('mandant_id'), Foreign Keys, Composite Unique
Wichtig für Chat B — Standard, der oft vergessen wird:

Pivot-Inserts triggern den HasMandant-Trait NICHT. Bei ->attach() mit Pivot-Payload immer mandant_id explizit mitgeben (siehe bulkKunden in LinkquellenController).
Migrationen sollten Zeitstempel 2026_05_18_* oder später haben, damit sie nach allem main einsortieren.
Tabellennamen aus dem Briefing (verlinkungen, linkprofil_snapshots, linkprofil_snapshot_verlinkungen) sind deutsch — passt zur Projekt-Konvention.
Wenn Chat B mit dem Plan fertig ist und ihn Dir zeigt, kannst Du ihn mir gern hier vorlegen, ich werfe nochmal kritisch drauf, bevor er anfängt zu migrieren.

---

**[P06 · 17.05. 10:09]**

Solider Plan. Drei Anmerkungen zur Schärfung, dann grünes Licht.

Zu Chat B's drei Review-Fragen:

Pivot ohne mandant_id — einverstanden. domain_tag und kunde_tag haben auch keine, die Konvention ist „Affinitäts-Pivot = mit mandant_id (weil HasMandant bei attach() nicht triggert), Tag-/Tracking-Pivot = ohne". Chat B's Lesart passt.
url_hash char(40) — OK, aber URL muss vor Hashing normalisiert werden, sonst dedupt der Hash nicht zuverlässig. Sehr leicht zwei "gleiche" URLs zu kassieren, wenn nicht: https://example.com/seite vs https://example.com/seite/ vs https://www.example.com/seite#abschnitt vs https://example.com/seite?utm_source=.... Vorschlag: Verlinkung::normalisiereUrl(string)-statische Methode mit Regeln (lowercase Host, www-stripping wenn passend, trailing slash entfernen, Fragment raus, ggf. UTM-Parameter raus), und der Hash geht über das Ergebnis. Test dazu mit ein paar Eingabe-Varianten.
aktualisiert_am auf verlinkungen — OK, gerechtfertigt. Snapshot-Diff braucht den Timestamp, das ist genau die Ausnahme-Begründung, die CLAUDE.md vorsieht.
Zwei zusätzliche Punkte:

verwendungs_zahl auf linkprofil_tags — denormalisierte Cache-Spalte. Funktioniert nur, wenn Chat B bei jedem attach()/detach() an Tags die Zahl mitführt. Sehr leicht zu vergessen, läuft dann unbemerkt auseinander. Vorschlag: erstmal weglassen, ad-hoc withCount('verlinkungen') rechnen, bis Performance es erzwingt (in Phase 1a wäre das eh nicht messbar). Wenn Chat B sie behalten will, dann mit klarer Service-Klasse, die der einzige Weg ist Tags anzuhängen.
Vokabular-Labels als Array-Konstanten zusätzlich zu Methoden — Vokabular::LINKART_LABELS = [...], nicht nur linkartLabel($wert). Begründung: an Inertia-Props kann man Arrays direkt durchreichen für <select>-Dropdown-Befüllung im Frontend, Methoden müsste man dafür extra mappen. Die Methoden sollten dann dünn drauf liegen: linkartLabel($wert) { return self::LINKART_LABELS[$wert] ?? $wert; }.
Zu den Tests:

Solide. Ich würde noch einen 5. Case ergänzen, sobald Punkt 2 oben geklärt ist: Unique-Constraint (mandant_id, kunde_kuerzel, url_hash) weist Duplikat ab, auch wenn die verlinkende_url-Eingabe variiert (mit/ohne trailing slash etc.). Das ist genau der Test, der normalisiereUrl() rechtfertigt.

Zwei Nebenbemerkungen, kein Blocker:

herkunft_kunde_kuerzel auf domains löst nur „erste Quelle gewinnt". Bei einer Domain, die im Linkprofil mehrerer Kunden auftaucht, geht der Mehrfach-Kontext verloren. Das ist für Phase 1 OK (Briefing sagt „aus Linkprofil-Analyse Kunde X", impliziert singular), aber falls später wichtig wird, brauchst Du eine domain_linkprofil_quellen-Tabelle.
LinkprofilTag::firstOrCreate mit kunde_kuerzel => null im Seeder ist sauber. Stelle nur sicher, dass das unique (slug, kunde_kuerzel) mit null korrekt funktioniert — manche DBs (MySQL strict) sehen NULL-Werte als „nicht gleich" und erlauben mehrere Zeilen mit demselben Slug+NULL. SQLite handhabt es lockerer. Falls dort doppelt eintreten könnte: slug allein unique, oder mit COALESCE arbeiten.
Mein OK zum Implementieren, sobald Chat B Punkte 2 und 5 einbaut und Punkt 4 entweder weglässt oder explizit absichert. Die anderen Fragen sind beantwortet, kann er direkt machen.

---

**[P07 · 17.05. 10:15]**

Mach mal weiter

---

**[P08 · 17.05. 10:22]**

bau schon mal weiter

---

**[P09 · 17.05. 10:27]**

Wie komme ich drauf?

---

**[P10 · 17.05. 10:28]**

Sieht gut aus, kannst mit 2b weitermachen

---

## ▸ 3. Import-Erkennung, Briefings & Beispieldateien

**[P11 · 17.05. 10:41]**

Die Erkennung muss verbessert werden, das gab einen fehler beim import

---

**[P12 · 17.05. 10:45]**

@/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/Rohmaterial/gsc_smv-gmbh.de-Latest links-2026-05-17.csv @/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/Rohmaterial/gsc_smv-gmbh.de-More sample links-2026-05-17.csv @/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/Rohmaterial/gsc_smv-gmbh.de-Top target pages-2026-05-17.csv @/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/Rohmaterial/xovi_export-all_links-20260517082944.csv so sehen die csv von xovi und aus der google search console aus. ahrefs bietet in der kostenfreien version keinen export mehr an und war eh keine so lohnenswerte quelle. was musst du für die weiteren dateien ggf. noch anpassen?

---

**[P13 · 17.05. 10:46]**

müsste jetzt im ordner liegen

---

**[P14 · 17.05. 10:48]**

habs unter docs/linkexport hingelegt

---

**[P15 · 17.05. 10:52]**

Das hat geklappt, vielen Dank. Verarbeite das neue Briefing für Phase 2.5

---

## ▸ 4. Vorschlags-/Aufräum-Logik, Status „unsicher“, gemeinsame Funktionen

**[P16 · 17.05. 11:09]**

@/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/Rohmaterial/sistrix_links-smv-gmbh.de.csv Ich hatte den testimport gelöscht (Bulk) und wollte erneut einlesen, jetzt diese fehlermeldung. war das nur ein soft-löschen und die sind versteckt noch irgendwo?

---

**[P17 · 17.05. 11:16]**

Beim aufräumen passiert nix, außer dass ein ladebalken oben lädt. keine bestätigung, kein status, nix. das muss ux besser werden

---

**[P18 · 17.05. 11:28]**

Er soll immer ausgeklappt lassen beim vorschlagen. ich muss sonst alle einzeln anklicken

---

**[P19 · 17.05. 11:32]**

Auf 1 reduzieren ist ja der standard eigentlich, weil wir wissen wollen, auf welchen plattformen wir stattfinden. einen zweiten oder gar alle zu behalten, macht im grunde nur bei sehr hochwertigen topp-links sinn. das müsste eine generelle regel sein, so dass die KI eher die ausnahmen lernt als bei jeder einzelnen domain wissen zu sammeln. Andersrum konzipiert, verstehst du, was ich meine?

Zweiter Schritt ist ja die bewertung der links. Wir sollten auch in der linkprofil-tabelle die erreichbarkeit testen können, den sistrix-wert wie bei der linkquellen-liste  köannen (vier stufen) und die linkart auslesen sowie einen vorschlag für die empfehlung geben. dann kann ich das filtern, prüfen, per massenbearbeitung ggf. ändern und bin schneller durch

---

**[P20 · 17.05. 11:36]**

1. ja gute idee
2. grundsätzlich ja, ergänze noch status "unsicher" bzw. "klären", dann kann ich danach filtern. Auch hier sollte die KI lernen können

Ansonsten gerne mit 2.6 weitermachen

---

**[P21 · 17.05. 11:53]**

Warum nutzt Du nicht die gleichen Funktioenn für Erreichbarkeit und Sistrix, mit den vier varianten und fortschrittsbalken. schau dir das mal von der linkquellen-tabelle an und übernimm das 1:1

---

**[P22 · 17.05. 12:00]**

Sehr gut umgesetzt.

Wie kann ich jetzt die linkart und die empfehlungen ki-gestützt analysieren? denn "linkart aus wissen", ohne das konkretes wissen zur domain hinterlegt ist, bringt ja irgendwie nix. Die empfehlungen klappen auch nicht. weil spam-metriken nicht erkannt werden, beispiel https://a2zseoarticles.com/autonome-fahrzeuge-in-deutschland-zukunft-der-mobilitat-deutschland-und-die-digitale-souveranitat-europas/

---

## ▸ 5. Mehrere Kundenanalysen, Social-Media-Rubrik, Anpassungen anwenden

**[P23 · 17.05. 12:03]**

Was ich liefern kann, sind alle bisherigen linkprofil-analysen als excel, die hatten wir ja auch fürs briefing. und wenn KI die verarbeitet und aus den bisherigen entscheidungen "lernt", dann könnte das schon eine solide basis für die relevanzberechnung sein? mit jeder weiteren linkprofil-analyse sollte das system zukünftig immr besser darin werden, link detection korrekt auszuführen

---

**[P24 · 17.05. 12:05]**

du hast welche im ordner linkprofi-examples

---

**[P25 · 17.05. 12:27]**

Ich habe vier Linkprofil-Analysen von verschiedenen aktuellen Kunden eingespielt. Gibt es konflikte in der wissensdatenbank, die wir auflösen sollten? Braucht es dafür ein entscheidungs-tool?

---

**[P26 · 17.05. 12:29]**

Brauchen wir noch eine Rubrik Social Media für XING, LinkedIn etc.?

---

**[P27 · 17.05. 12:33]**

Wenn ich eine anpassung durchführe, beispielsweise trendkraft.io in der linkart auf presseportal ändern (inline), bleibt der konflikt stehen. Ich müsste das ja einmal entscheiden und dann soll es auf alle ausgerollt werden.

---

**[P28 · 17.05. 12:41]**

Anwenden hat anscheinedn keinen effekt. wenn ich etwas ändere, also linkart anpasse, dann verschwinden die konflikte. wenn ich nur anwenden klicke, passiert nix. 
PS: Das ist doch ein Tool, was ich da jetzt nutze  Du hattest vorhin gesagt, ein tool lohnt nicht ;-)

---

**[P29 · 17.05. 12:49]**

tut aber nix

---

**[P30 · 17.05. 12:56]**

@/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/Dokumentation 2023/MIS_Linkprofil-Analyse 2025_05.xlsx Ich habe vor über 5 Minuten die MIS als Kunden angelegt und Import gestartet, es hat sich aufgehängt. War ein neuer Kunde gar nicht möglich zu ergänzen?

---

## ▸ 6. Domain-Wissen: Sortierung, Tabellen-Schliff & globale Standards

**[P31 · 17.05. 13:03]**

Klappt. Ich würde hier http://127.0.0.1:8001/linkprofil/domain-wissen?confidence=&linkart=&nur_konflikte=true&suche=gerne noch direkt die URL aufrufen können mit einen pfeil-icon

---

**[P32 · 17.05. 13:04]**

Ah, und nach Spalte sortieren, so dass ich die nach linkarten sortieren kann

---

**[P33 · 17.05. 13:15]**

Hat geklappt, alle Konflikte aufgelöst.

Jetzt lass uns die Tabelle noch schick mache, vergleiche die Screenshots vom Linkprofil Tabelle und Linkquellen Tabelle, das sollte (von den konkreten spalten, die ja bezogen sind auf das jeweilige Thema) optisch identisch aussehen

---

**[P34 · 17.05. 13:36]**

Das hast Du sehr schön gemacht. Warum sind solche Standards nicht global im System definiert? Muss mich mal im Chat A beschweren bei Gelegenheit 😂

Wie gehts hier weiter, was ist vom Gesamtprojekt noch offen, nachdem wir ein kleines Interlude eingelegt haben?

---

**[P35 · 17.05. 13:40]**

Können wir so machen mit den Klassen hochziehen, und den nächsten Schritten

Vorher noch einen Fehler prüfen. Im "standard-modus" (kein "filter zurücksetzen) werden keine Verlinkungen ausgegeben, bei verschiedenen kunden, beispielsweise http://127.0.0.1:8001/linkprofil?erreichbarkeit=&kunde=SPE&neu_alt=&nur_link_verloren=false&ohne_empfehlung=false&pro_seite=25&sortierung=domain_asc&suche=

Und unten bei den Zeilen pro Seite ist ein darstellungsfehler mit dem pfeil-icon, das bitte auch global korrigieren (linkquellen)

---

**[P36 · 17.05. 13:43]**

Genau, bitte die nächsten schritte umsetzen bis zum ende

---

## ▸ 7. „Aufräumen“ vs. „Re-Run“ verstehen & Logik klären

**[P37 · 17.05. 14:07]**

http://127.0.0.1:8001/linkprofil?erreichbarkeit=&kunde=SMV&neu_alt=&nur_link_verloren=false&ohne_empfehlung=false&pro_seite=25&sortierung=domain_asc&suche=

Ich habe alle neuen Quellen bei SMV hochgeladen und anschließend "aufgeräumt". Ich würde gerne die duplikate noch mal durchschauen, also das "Aufräumen" jederzeit neu anstoßen können. Sie geht das?

---

**[P38 · 17.05. 14:19]**

Wenn man auf Aufräumen oder Re-Run klickt, passiert erstmal gar nichts. HIer wäre ein Fortschrittsbalken sinnvoll und dann weiterleitung auf die unterseite, wo die ergebnisse sind, die ich prüfen kann. Ehrlicherweise war ich von 200 zu prüfenden ergebnissen so erschlagen, dass ich auf "alle annehmen" geklickt habe. Es müsste eine Sortierung her, dass die klaren sachen direkt akzeptiert werden und nur unsichere geprüft werden sollen.

Zweites Thema: Die Tabellen sind zum Teil nicht vollständig einzusehen. siehe screenshots wird rechts immer abgeschnitten. die spalten müssten so fest sein und umbrechen, dass immer alles reinpasst

---

**[P39 · 17.05. 14:26]**

@/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/Rohmaterial/gsc_smv-gmbh.de-Top linking sites-2026-05-17.csv Ich habe in der Search Console noch eine weitere Download-Datei gefunden, die ich aber nicht importieren kann. Analysiere und repariere sie. Kann ich auch mehrere Dateien auf einmal hochladen?

---

**[P40 · 17.05. 14:28]**

Liegt drin

---

**[P41 · 17.05. 14:48]**

Ja hat geklappt. 

Ich checke noch nicht, dass es Aufräumen und "Re-Run" geben muss., wo dann noch mal weitere Domains verarbeitet werden. wieso schafft das system das nicht in einem rutsch? Gibts hier ein Limit?

---

**[P42 · 17.05. 14:49]**

Ja bitte das klingt sinnvoller

---

**[P43 · 17.05. 14:56]**

Ok, das zweite "fertig abgehandelt" wäre dann ja nur für die wenigen bewusst gesetzten duplikate (2 spiegel artikel" von vor zwei Jahren), wo nichts neues hinzugekommen ist und trotzdem noch ein duplikat sitzt?

---

## ▸ 8. Excel-Export-Formatierung & Abstimmung mit Benny

**[P44 · 17.05. 14:58]**

@/Users/tkilian/Library/CloudStorage/ZohoWorkDriveTrueSync-thoxan/THOXAN GMBH/PROJEKTE AKTUELL/SMV Sitz & Objektmöbel GmbH/26.0002 2. Quartal 2026/Linkprofil-Analyse 2026/SMV_Linkprofil-Analyse_2023-03-29.xlsx wir müssen über den export sprechen

Ich habe in excel ein gewünschtes zielformat definiert, kannst du das auslesen und fest hinterlegen für den export? Außerdem gibt es fehlermeldungen, ungünstig!

---

**[P45 · 17.05. 15:06]**

Leider nach wie vor fehler und layout unterscheidet sich nach wie vor deutlich

---

**[P46 · 17.05. 15:11]**

Benny sagte mir mal,. dass ki excel und word schlecht formatieren kann, dass man das anders als vorlage anlegen müsste. kann das sein und wie wäre hier die sinnvolle vorgehensweise? Fehler nach wie vor da

---

**[P47 · 17.05. 15:19]**

Ne, ich kläre das Thema mit Benny, ich kann Deinen Export öffnen und die Inhalte per Copy & Paste in meine Vorlage packen erstmal.

Ich habe noch ein Thema, wenn man eine Verlinkung als Linkquelle überträgt, dann steht dort "noch ungesichtet", ich kann aber nicht danach filtern. Außerdem kommt die verlinkung ja aus dem projekt SMV (beispielsweise) und sollte auch mit dem kunden verknüpft sein. der deeplink zur veröffentlichung ist futsch, oder lässt der sich auch ins monitoring packen? Wäre das eine zusätzlihce funktion?

---

## ▸ 9. Linkprofil als fester Menüpunkt

**[P48 · 17.05. 15:30]**

Ich will linkprofil-analyse als "Linkprofil" im Menü an zweiter Stelle haben, zwischen Dashboard und Linkquellen. Da gehört es vom Prozess her hin. Anschließend kannst du alles committen und dann mergen wir es mit "main", so dass ich an Chat A übergeben und dort weitermachen kann. Fasse alles zusammen, was zur dokumentation nötig sein könnte

---
