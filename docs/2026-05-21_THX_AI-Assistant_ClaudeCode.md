# AI Assistant: Erkenntnisse und Arbeitsaufträge aus KI-Coworking 21.05.2026

**Kontext:** Diese Notiz dient Claude Code als Arbeitsgrundlage für die Weiterentwicklung des AI Assistant (früher KI Text-Tool mit Wissensdatenbank). Sie fasst alle technischen Details, architektonischen Festlegungen und konkreten Anforderungen aus dem Coworking-Termin zusammen.

**Teilnehmer:** Thomas Kilian, Benny Köhler, Ralf Bohnert

**Arbeitsumgebung:** Der AI Assistant wird über SSH auf dem Hetzner-Server bearbeitet, Claude Code arbeitet ausschließlich dort. Lokaler Zugriff auf den Server-Code ist nicht möglich, Eingaben gehen über den Docs-Ordner.

---

## 1. Aktueller Stand und Beobachtung aus dem Termin

**Workflow erfolgreich umgestellt:** Thomas nutzt mittlerweile die Claude Desktop-App und arbeitet mit aktiviertem Automodus. Der Automodus läuft ohne ständige Bestätigungsrückfragen, was die Bearbeitung großer Aufgabenpakete erst praktikabel macht. Benny bestätigt, dass er den Workflow nachvollziehen will und ähnlich aufsetzen will.

**Tool-Erlebnis:** Thomas berichtet positiv über die intuitive Arbeit mit Claude Code im AI Assistant: schnelle Iteration, sinnvolle Nachbauten aus Vorlagen (zum Beispiel Übernahme des Einstellungs-Tabs aus dem WITTEKIND-Tool ins AI-Assistant-Tool). Diese Art der Übertragung von UI-Patterns soll fortgesetzt werden.

## 2. Architektur-Grundsatzentscheidung: Trennung der Datenwelten

**Drei Schichten, sauber getrennt:**

**Schicht 1: ERP-Daten je Unternehmung.** Strukturelle Daten (Kontakte, Artikel, Angebote, Rechnungen) gehören in eine SQL-Datenbank, nicht in eine Vektordatenbank. Bei strukturellem Material ist eine SQL-Datenbank viel klarer und einfacher. Benny ist hier sehr klar: „Da braucht man keine Vektordatenbank."

**Schicht 2: Wissensdatenbank als Vektor-Schicht.** Die Vektordatenbank im AI Assistant nimmt Dokumente, Transkripte, Word-Dateien, Tickets und so weiter auf, also unstrukturiertes oder semi-strukturiertes Material. Aktuell läuft das auf MariaDB Vektor. Karl Kratzsch hatte davon zwischenzeitlich abgeraten und Qdrant sowie eine zweite Alternative empfohlen, beide extern. Benny bewertet: aktuell läuft MariaDB Vektor bei uns gut, wir bleiben vorerst dabei.

**Schicht 3: ERP-Daten werden in die Vektordatenbank kopiert (read-only).** Damit der AI Assistant kontextualisieren kann, wandern ERP-Daten als Kopie in die Vektordatenbank. Der ERP basiert aber nicht auf der Vektordatenbank. Sonst entsteht ein Mischsystem, in dem die KI sich selbst schwer tut. Klare Richtung von Benny: kopieren, nicht referenzieren.

**Konsequenz für das WITTEKIND-Beispiel:** WITTEKIND wird perspektivisch ein eigenes ERP bekommen. Dessen Daten dürfen in eine eigene Quelle der Wissensdatenbank fließen, jedoch nur mit Zugriffsrechten so geregelt, dass diese Quelle nur bestimmten Nutzern angezeigt wird. Beim hypothetischen Verkauf von WITTEKIND ist eine saubere Trennung möglich.

## 3. Quellen-basierte Wissens-Verarbeitung

Thomas formuliert das Prinzip im Termin: Jede Quelle in der Wissensdatenbank wird ausgezeichnet, ähnlich wie bei Schema.org. Die Verarbeitung erfolgt dann typabhängig.

Beispiele:
- Asana-Ticket: bestimmte Kriterien gelten, Status kann sich ändern.
- Word-Dokument 10 Seiten: anders zu verarbeiten als ein Ticket.
- Kontakt: besteht immer aus Name, Adresse, Postleitzahl, E-Mail. Andere Verarbeitung als ein Gastartikel.

**Aufgabe für Claude Code:** Die Quellen-Typisierung soll als strukturelles Merkmal je Wissensdatensatz hinterlegt sein. Beim Embedding und Retrieval wird der Quellentyp berücksichtigt. Verarbeitung pro Quellentyp ist anpassbar (Prompt-Templates, Kontext-Fenster, Retrieval-Strategie).

## 4. Rechtemanagement und Mandanten-Trennung

**Anforderung Thomas:** Das System braucht ein feinkörniges Rechtemanagement.

**Drei Dimensionen:**
- Pro Nutzer: welche Module sieht er?
- Pro Nutzer: welche Kunden sieht er?
- Pro Nutzer: auf welche Wissens-Quellen hat er Zugriff?

**Konkrete Beispiele aus dem Termin:**
- Im LAM-System sind Bärbel Ellermann (Bauverlag) und andere Anbieter hinterlegt. Diese Info ist für Thomas im Linkaufbau relevant. Sie darf aber nicht Michi (Michaela) angezeigt werden, wenn diese gerade einen Text für Cayas schreibt. Trennung nach Modul und nach Wissensquelle.
- Wenn Thomas eine BWA ins System lädt zur Auswertung in einem Dashboard, dürfen nicht alle Team-Mitglieder die Geschäftszahlen einsehen oder zufällig im Chat darauf stoßen. Sensitive Quellen brauchen Sonderrechte.

**Vorbild aus WITTEKIND-Tool:** Die Benutzerverwaltung im WITTEKIND-Tool (Rollen Admin / Manager / User / Gast) sowie der View-Switch (Augen-Icon oben rechts, in eine andere User-Sicht wechseln zum Testen) sollen ins AI-Assistant-Tool übernommen werden.

**Hinweis Benny:** Die Standard-Benutzerverwaltung muss er üblicherweise extra einfordern, Claude legt sie nicht von selbst an. Für den AI Assistant ist sie aber Voraussetzung.

## 5. Multi-Tenant-Trennung der Unternehmungen

**Strategischer Punkt von Benny:** Die einzelnen Unternehmungen sollen technisch getrennt bleiben. Wenn WITTEKIND in fünf Jahren als verkaufsreife Marke an einen Möbelhersteller geht (SMV oder andere), darf die Wittekind-Welt sich sauber abtrennen lassen. Eine zu enge Verflechtung im AI Assistant würde das blockieren.

**Konsequenz für Claude Code:**
- Pro Unternehmung eine eigene Wissens-Mandantenstruktur.
- Verflechtungen erfolgen über das Rechte- und Quellenmodell, nicht über das Schema.
- Wissensquellen tragen ein Mandanten-Tag (Wittekind, Thoxan, Kundenname). Der Retrieval beachtet den Tag.

**Skalierungsbedenken Benny:** Wenn alle ERP-Daten und alle Wissensquellen in einer Software liegen, wird die Software riesig und langsam. Das hieße Enterprise-Setup mit deutlich mehr Ressourcen. Trennung bleibt damit auch infrastrukturell sinnvoll.

## 6. Mehrstufiges KI-Konzept (Privacy by Design)

Thomas formuliert die Anforderung, die Benny und Ralf bestätigen:

**Stufe 1: Öffentliche Texte.** Für Blogartikel und vergleichbare Inhalte ohne personenbezogene Daten reicht externe KI (Claude, ChatGPT). Datenbedenken hier minimal.

**Stufe 2: Personenbezogene Daten.** Für Verarbeitung mit personenbezogenen Daten kommt die lokale KI zum Einsatz. Hetzner-Server, EU/DSGVO-konform. Keine Übertragung an externe Sprachmodelle.

**Stufe 3: Sensible Daten.** Für besonders sensible Daten (BWAs, Geschäftskennzahlen, vertrauliche Kundenprojekte) ist eine noch strengere Verarbeitung vorzusehen, voraussichtlich ebenfalls lokal mit zusätzlichen Zugriffsbeschränkungen.

**Status der lokalen KI:** Hetzner-Server steht, lokale KI ist seit letzter Woche Prio. Benny bewertet: Diese Priorisierung hat das Projekt sehr vorangebracht.

**Argument für Kundenkommunikation:** Thomas möchte gegenüber Kunden mit gutem Gefühl sagen können, „wir verarbeiten lokal". Aktuell läuft fast alles über öffentliche Tools (auch Microsoft 365 schickt E-Mails nach USA), aber für unsere Verarbeitungsschritte soll lokal die Default-Option für sensible Daten werden.

**Bezug Whisper:** Thomas verarbeitet Transkripte bereits lokal über Whisper mit lokalem LLM. Dieses Pattern soll sich im AI Assistant fortsetzen.

## 7. KI-Glove als Pseudonymisierungs-Schicht

**Technischer Mechanismus (von Thomas und Benny umrissen):** Vor dem Übergang an externe KI werden personenbezogene Felder verschlüsselt oder pseudonymisiert, beispielsweise „Ansprechpartner: Nadine Klug" wird zu „Ansprechpartner: XXXY" oder vergleichbaren Platzhaltern. Die Kontext-Information „Es handelt sich um einen Ansprechpartner" bleibt erhalten, damit die KI sinnvoll arbeiten kann. Die konkrete Identität wird nach dem KI-Antwort-Rücklauf wieder eingesetzt.

**Mehrwert:** Wir können weiterhin echtes Claude (großes Modell) nutzen, weil der entwickelte Code nicht mit lokaler KI gebaut werden soll. Die Daten, die das Modell sieht, sind aber sensibel-armer.

**Umsetzungsweg:** Benny hält das für relativ zügig machbar. Er möchte den Prompt schreiben und in einer gemeinsamen Session mit Thomas die Implementation durchziehen.

**Aufgabe Claude Code:** Skript vor jedem KI-Call, das aus der SQL-Datenbank stammende Felder erkennt und durch Platzhalter ersetzt. Nach dem KI-Rücklauf Rückübersetzung. Felder, die pseudonymisiert werden müssen, sind je Quellentyp zu definieren.

## 8. Lokale KI als Default für ERP-Operationen

**Ergänzende Festlegung von Benny:** Für ERP-Operationen (zum Beispiel im künftigen WITTEKIND-ERP) soll ausschließlich das lokale Sprachmodell zum Einsatz kommen. Begründung: Personenbezogene Bestandsdaten von Kunden sollen die EU-Infrastruktur nicht verlassen.

**Konsequenz:** Der AI Assistant muss pro Anwendungsfall ein Routing-Modul haben: Welche KI bedient den Request? Externe (Claude) für Code und unkritische Texte, lokale KI für personenbezogene und ERP-relevante Operationen.

**Vision (mittelfristig):** Eigene Hardware mit lokal installiertem Modell. Benny verweist auf einen Bericht (Cardscore), in dem jemand einen Eigenbau-Rechner aus 10 Jahre alter Hardware mit Qwen-Modell aufgesetzt hat: Stromkosten plus Einmalinvestition, keine Tokens mehr. Die kommerziellen Hardware-Lösungen kommen, das ist absehbar.

## 9. UI-Bausteine zur Übernahme

**Aus dem WITTEKIND-Tool zu übernehmen:**
- Tab-Layout für Einstellungen, wie im WITTEKIND-Tool umgesetzt.
- View-Switch mit Augen-Icon zum schnellen Wechsel der Nutzer-Perspektive (für Admins).
- Feedback-Funktion mit Screenshot-Möglichkeit pro Maßnahme.
- Export der Feedbacks als Markdown für die Verarbeitung durch Claude Code.

**Workflow bei Feedback:** Daniel-ähnliche Power-User können Feedback und neue Ideen direkt im Tool eingeben. Export als MD. Claude Code prüft beim nächsten Lauf, ob neues Feedback da ist, und arbeitet es ab. Bewusst nicht über Slack, sondern als sauberer Kanal direkt im Tool.

## 10. Workflow-Optimierung Claude Code

**Erkenntnisse aus dem Termin, die für die Tool-Architektur relevant sind:**

- Automodus mit großen Aufgabenpaketen funktioniert. Markdown-Datei mit allen Anforderungen reingeben, Claude arbeitet ab und meldet Status mit Priorisierung zurück.
- Während der Bearbeitung können neue Messages reingeschoben werden.
- Mobile-Steuerung über `/mobile` oder ähnliches (genaue Bezeichnung noch zu prüfen) erlaubt Rückfragen zu beantworten, ohne am Rechner zu sein.

**Konsequenz für den AI Assistant:** Das Tool selbst soll perspektivisch ähnlich autonom arbeiten können. Aufgabenstellungen im Tool ablegen, Verarbeitung im Hintergrund, Statusrückmeldung. Diese Architektur passt zur strategischen Stoßrichtung „Agentic Workflows".

## 11. Sicherheits-Scope für Claude Code im AI Assistant

**Scope-Beschränkung:** Claude Code arbeitet ausschließlich auf dem Hetzner-Server, über SSH. Kein Zugriff auf den lokalen Rechner von Thomas. Eingaben (zum Beispiel WITTEKIND-Code als Referenz) gehen über den Docs-Ordner.

**Beispiel aus dem Termin:** Thomas hat den lokalen WITTEKIND-Ordner gezippt und im Docs-Ordner des AI Assistants abgelegt, damit Claude Code die Tally-Funktionalität als Referenz nutzen kann (rechte Maustaste, Multi-Auswahl, Filter, Export-Link).

**Tally als Beispiel:** Thomas möchte einen Projektplaner ähnlich Tally aufbauen. Wichtige Festlegung von Benny: Claude muss klar wissen, dass das Tally-System vorher in WordPress lief, nicht auf der Autobahn (also nicht in einem anderen System). Sonst rekonstruiert er Bauteile auf der falschen Annahme.

## 12. Konkrete nächste Aufgaben für Claude Code

Reihenfolge nach Priorität:

1. **Lokale KI aktivieren und Routing einrichten**: Welcher Request geht an welche KI? Anhand von Quellentyp und Datenklasse routen.
2. **KI-Glove implementieren**: Pseudonymisierung vor externer KI, Rückübersetzung nach Response. Prompt und gemeinsame Session mit Benny.
3. **Quellen-Typisierung in der Wissensdatenbank**: Jede Quelle bekommt einen Typ-Tag, Verarbeitung je Typ adaptiv.
4. **Mandanten-Tag pro Quelle**: Wittekind, Thoxan, Kundenname. Retrieval beachtet Mandant.
5. **Rechtemanagement dreidimensional**: Modul × Kunde × Quelle. Vorbild aus WITTEKIND-Tool übernehmen.
6. **UI-Bausteine aus WITTEKIND-Tool übernehmen**: Tab-Layout, View-Switch, Feedback-Tool mit MD-Export.
7. **ERP-Daten-Sync-Pipeline vorbereiten**: ERP-Daten als Kopie in die Vektordatenbank ziehen, ohne dass der ERP von der Vektordatenbank abhängt.

## 13. Offene Punkte für die Folgesession

- Mobile-Steuerung von Claude Code: genaue Bezeichnung der Funktion ermitteln und testen.
- API-Keys verschlüsselt speichern (gleicher Punkt wie im WITTEKIND-Tool, gilt hier auch).
- WeClapp-Custom-Field-Logik in eine wiederverwendbare Komponente bringen, falls der AI Assistant später auf CRM-Daten zugreift.
- Vergleich Qdrant vs. MariaDB Vektor: Karls Empfehlung dokumentieren, Performance bei wachsender Datenmenge prüfen.

## 14. Folgetermin

Dienstag, 26.05.2026, 14:00 bis 16:00 / 17:00 Uhr mit Benny (Ralf optional). KI-Glove-Implementierung und lokale KI sind die beiden Kern-Themen.
