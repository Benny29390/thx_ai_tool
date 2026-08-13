# Customer Portal: Konzeptionelles Modell

AI Assistant KI-Tool, Kundenansicht. Stand: 23.06.2026

## Zielbild

Kundinnen und Kunden bekommen einen schnellen, verständlichen Überblick über das, was Thoxan aktuell für Sie tut: laufende Projekte, erzielte Ergebnisse, eingesetzte Tools und Techniken sowie den aktuellen Stand. Sie sehen ausschließlich Ihre eigenen Projekte (inklusive aller diesem Projekt zugeordneten Websites), können Fragen über Kommentare stellen, aber keine Daten bearbeiten oder verändern. Die Ansicht ist bewusst reduziert und kuratiert: Wir entscheiden pro Kachel, was freigeschaltet wird.

## Grundprinzipien

Vier Leitplanken bestimmen das Design.

Read-only auf Daten, nutzbar bei Tools. Bei darstellenden Inhalten (Ergebnisse, Meilensteine, Dokumente, Projektstatus) haben Kundennutzer nur Leserechte; die einzige schreibende Interaktion ist hier das Kommentieren. Tools sind die bewusste Ausnahme: Wird einem Kunden ein Tool freigeschaltet, darf er es im definierten Funktionsumfang auch tatsächlich benutzen. Lesen von Ergebnissen und Benutzen von Tools sind also zwei unterschiedliche Berechtigungsarten.

Kuratierte Sichtbarkeit. Nichts ist für Kunden sichtbar oder nutzbar, solange wir es nicht explizit pro Kachel und pro Kunde freischalten. Sichtbarkeit und Nutzbarkeit sind also opt-in, nicht opt-out. Das schützt vor versehentlicher Offenlegung interner Informationen.

Wiederverwendung der bestehenden Logik. Wir bauen kein paralleles System, sondern erweitern die vorhandene Auth- und Rechtelogik um eine zusätzliche Benutzergruppe mit abgestuften Rechten, vergleichbar mit Gästen. Das reduziert Komplexität bei Login, Session-Management und Audit.

Datensouveränität bleibt gewahrt. Da ohnehin lokal verarbeitet wird (Chunking, Wissensdatenbank, Chat über lokale LLMs), entsteht durch den Kundenzugriff kein neuer Risikopfad nach außen. Die KI-Glove-Pseudonymisierungsschicht greift auch hier vor jedem externen KI-Aufruf.

## Rollen und Berechtigungen

### Benutzergruppen

Wir führen die Gruppe Customer als zusätzliche Rolle in der bestehenden Logik ein. Die Hierarchie sieht damit so aus: Team-Nutzer mit vollem Zugriff je nach interner Rolle, Customer-Nutzer mit Lesen plus Kommentieren auf freigeschaltete darstellende Inhalte und Nutzungsrecht auf freigeschaltete Tools des eigenen Kunden, sowie weiterhin Gäste mit minimalem oder temporärem Zugriff.

### Team-Zugriff auf die Kundenansicht

Die Kundenansicht ist keine reine Außenansicht, sondern für das Team voll zugänglich und gestaltbar. Teammitglieder, die für einen Kunden die entsprechenden Rechte haben, können genau das sehen, was der Kunde sieht, und können diese Ansicht auch bearbeiten und beeinflussen. Das Team steuert also aktiv, was in den Kundenkacheln erscheint, welche Ergebnisse dargestellt werden und welche Module freigeschaltet sind. Der Umfang dieser Bearbeitungsrechte richtet sich nach der internen Rolle des jeweiligen Teammitglieds und nach seiner Zuordnung zum betreffenden Kunden. Ein Teammitglied ohne Zuordnung zu einem Kunden sieht dessen Kundenansicht nicht.

### Multi-User pro Kunde

Ein Kunde (Unternehmen) kann mehrere Customer-Nutzer haben. Jeder Nutzer hat einen eigenen Login. Alle Nutzer eines Kunden teilen sich denselben Sichtbarkeitsumfang und sehen dieselben Kommentare. Es gibt also eine n:1-Beziehung von Customer-Nutzern zu einem Kunden-Account und damit zu dessen Projekten.

### Mehrere Websites pro Projekt

Ein Kundenprojekt kann mehrere Websites umfassen. Schaltet ein Kunde Zugriff auf das Projekt frei, sieht er alle zugeordneten Websites mit ihren jeweiligen Daten. Die Website ist die feinere Ebene unterhalb des Projekts, nicht eine eigene Zugriffsgrenze.

### Freischaltung pro Kachel

Sichtbarkeit wird pro Kachel (Modul) und pro Kunde gesteuert. Wir können für Kunde A das Reporting und den Projektstatus freischalten, für Kunde B zusätzlich die Tool-Übersicht. Eine Permission-Matrix verbindet Kunde, Modul und Sichtbarkeit miteinander.

### Permission-Matrix (Beispielstruktur)

| Modul / Kachel | Team (mit Kundenrechten) | Customer (freigeschaltet) | Customer (nicht freigeschaltet) |
|---|---|---|---|
| Projektstatus | Lesen + Bearbeiten | Lesen + Kommentieren | nicht sichtbar |
| Ergebnisse / Reporting | Lesen + Bearbeiten | Lesen + Kommentieren | nicht sichtbar |
| Tools und Techniken | Lesen + Bearbeiten + Nutzen | Nutzen (definierter Funktionsumfang) | nicht sichtbar |
| Meilensteine / Timeline | Lesen + Bearbeiten | Lesen + Kommentieren | nicht sichtbar |
| Dokumente / Downloads | Lesen + Bearbeiten | Lesen (kuratiert) | nicht sichtbar |
| Interne Notizen | Lesen + Bearbeiten | nie sichtbar | nie sichtbar |

## Modulstruktur des Kunden-Dashboards

Das Kunden-Dashboard übernimmt die Kachel-Logik des Team-Dashboards, zeigt aber nur freigeschaltete und kuratierte Inhalte. Mögliche Kacheln:

Projektstatus. Aktueller Stand des laufenden Projekts in verständlicher Sprache. Statusindikator (z. B. on track, in Klärung, wartet auf Kundenrückmeldung), kurze Zusammenfassung der aktuellen Aktivität, nächster Meilenstein.

Ergebnisse und Reporting. Die wichtigsten Kennzahlen und Erfolge in kundengerechter Aufbereitung. Hier entscheiden wir später gemeinsam die Granularität und welche KPIs sinnvoll dargestellt werden.

Tools und Techniken. Welche Werkzeuge und Methoden wir für den Kunden einsetzen, und in ausgewählten Fällen Tools, die der Kunde selbst bedienen darf. Anders als bei den darstellenden Kacheln ist hier nicht nur Lesen vorgesehen, sondern die aktive Nutzung im definierten Funktionsumfang. Granularität und die genaue Freischaltung einzelner Funktionen sind eine spätere Detailfrage; die Inhalte kommen aus einer Datenquelle, nicht hardcoded.

Meilensteine und Timeline. Zeitlicher Verlauf des Projekts mit erreichten und anstehenden Meilensteinen. Zugriff auf historische Daten ist vorgesehen, die Details klären wir später.

Dokumente und Downloads (optional). Kuratierte Freigabe einzelner Dokumente, die der Kunde einsehen oder herunterladen darf.

Kommentare und Rückfragen. Übergreifende oder pro Kachel verortete Kommentarfunktion, analog zur bereits bestehenden externen Ansicht des Projektplaners.

## Kommentarfunktion

Die Kommentarfunktion existiert bereits in der externen Ansicht des Projektplaners und wird konzeptionell übernommen. Kunden stellen Fragen, das Team reagiert darauf. Kommentare sind für alle Customer-Nutzer desselben Kunden-Accounts sichtbar. Kommentare verändern keine Projekt- oder Stammdaten, sondern sind eigenständige Objekte. Offen für später: ob Kommentare pro Kachel verortet oder projektweit geführt werden, und wie die Benachrichtigung des Teams bei neuen Kundenkommentaren erfolgt.

## Tool-Nutzung durch Kunden

Tools unterscheiden sich grundlegend von den darstellenden Kacheln. Während Ergebnisse, Meilensteine und Dokumente reine Leseinhalte sind, sind freigeschaltete Tools für Kunden aktiv benutzbar. Drei Regeln gelten dabei:

Freischaltung auf Tool-Ebene. Ein Tool wird einem Kunden bewusst freigeschaltet, analog zur Kachel-Freischaltung. Ohne Freischaltung ist das Tool für den Kunden nicht vorhanden.

Definierter Funktionsumfang pro Tool. Innerhalb eines freigeschalteten Tools muss klar geregelt sein, welche Funktionen für Kunden nutzbar sind und welche nicht. Ein Kunde nutzt also nicht zwangsläufig den vollen Funktionsumfang, den ein Teammitglied hat, sondern einen kuratierten Ausschnitt. Diese Funktionsabgrenzung legen wir pro Tool fest.

Wirkungsgrenzen. Auch bei aktiver Nutzung dürfen Kunden keine Stammdaten, fremde Projektdaten oder interne Konfigurationen verändern. Die Nutzung bleibt auf den eigenen Kunden-Account und den freigegebenen Funktionsumfang beschränkt.

Welche Tools mit welchem Funktionsumfang zuerst für Kunden geöffnet werden, klären wir am konkreten Prototyp.



Die Kunden-Kacheln greifen lesend auf dieselben Datenquellen zu wie das Team, nämlich die Kundenkacheln und den Projektplaner. Es entsteht keine zweite Datenhaltung. Stattdessen filtert eine Zugriffsschicht, was ein bestimmter Customer-Nutzer sehen darf:

1. Customer-Nutzer meldet sich an, Zuordnung zum Kunden-Account wird aufgelöst.
2. System ermittelt die für diesen Kunden freigeschalteten Module und Tools (Permission-Matrix).
3. Für jedes freigeschaltete darstellende Modul werden die Daten aus der jeweiligen Quelle (Kundenkachel, Projektplaner) gelesen und kundengerecht aufbereitet.
4. Für jedes freigeschaltete Tool wird der definierte Funktionsumfang aktiviert, alle übrigen Funktionen bleiben für den Kunden gesperrt.
5. Interne Felder werden serverseitig herausgefiltert, bevor sie das Frontend erreichen.
6. Darstellende Inhalte werden read-only angezeigt; schreibend ist das Anlegen von Kommentar-Objekten sowie die Nutzung freigeschalteter Tools im definierten Umfang möglich.

Wichtig für die Sauberkeit: Die Filterung passiert serverseitig, nicht erst im Frontend. So gelangen interne Felder gar nicht erst zum Client.

## Datenmodell-Erweiterungen (Skizze)

Folgende Erweiterungen sind voraussichtlich nötig. Konkrete Felder klären wir bei der Umsetzung.

Customer-Account. Repräsentiert das Kundenunternehmen, verknüpft mit einem oder mehreren Projekten.

Customer-User. Einzelner Login, zugeordnet zu genau einem Customer-Account, mit eigener Authentifizierung über die bestehende Logik.

Permission-Eintrag. Verbindet Customer-Account und Modul beziehungsweise Tool mit einem Sichtbarkeits- oder Nutzungs-Flag (freigeschaltet ja/nein). Bei Tools zusätzlich die Angabe des freigegebenen Funktionsumfangs.

Kommentar. Bereits vorhanden in der externen Projektplaner-Ansicht, wird auf das Kunden-Dashboard ausgeweitet, mit Sichtbarkeit auf Account-Ebene.

Audit-Log. Protokolliert Kundenzugriffe und Kommentare, idealerweise über das bestehende Logging.

## Sicherheits- und Datenschutzaspekte

Tenant-Isolation. Strikte Trennung sicherstellen, dass ein Customer-Nutzer ausschließlich Daten des eigenen Kunden-Accounts erhält. Das ist der kritischste Punkt und gehört serverseitig erzwungen, nicht nur über UI-Filterung.

Kuratierung statt Vollzugriff. Default ist Nicht-Sichtbarkeit; nur explizit freigeschaltete Kacheln erscheinen und nur explizit freigeschaltete Tools sind nutzbar, jeweils im definierten Umfang.

Interne Felder. Notizen, Kalkulationen, Tagessätze, interne Kommunikation bleiben grundsätzlich unsichtbar und werden serverseitig entfernt.

Lokale Verarbeitung. Sollten Kunden den Chat oder KI-gestützte Auswertungen nutzen können, greift die bestehende lokale Verarbeitung plus KI-Glove vor externen Aufrufen.

## Offene Punkte für die nächste Iteration

Diese Punkte gestalten wir bewusst iterativ, sobald ein erster Prototyp des Kundenbereichs vorliegt: welche konkreten Datenfelder pro Kachel angezeigt werden, die Granularität bei Tools und Reporting, welche Tools mit welchem Funktionsumfang zuerst für Kunden geöffnet werden, der genaue Umfang und die Aufbereitung historischer Daten, ob Kommentare pro Kachel oder projektweit geführt werden, das Benachrichtigungsverhalten bei neuen Kundenkommentaren sowie die Frage, ob Kunden Zugriff auf den lokalen Chat erhalten sollen.

## Vorgeschlagene nächste Schritte

1. Freigabe dieses Grundkonzepts.
2. Briefing von Claude Code auf Basis dieses Konzepts: zusätzliche Benutzergruppe Customer, Team-Zugriff auf die Kundenansicht, Permission-Matrix für Module und Tools, serverseitige Tenant-Isolation und Filterung.
3. Aufbau eines ersten Prototyps für den Kundenbereich mit wenigen Pilotkacheln (Vorschlag: Projektstatus, Ergebnisse, ein nutzbares Tool).
4. Iterative Ausgestaltung aller Detailfragen am laufenden Prototyp, Stück für Stück.
