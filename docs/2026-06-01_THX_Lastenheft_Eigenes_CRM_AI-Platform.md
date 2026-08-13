# Lastenheft: Eigenes CRM als Modul der Thoxan AI-Platform

**Stand:** 01.06.2026
**Auftraggeber:** Thomas Kilian (Thoxan Communications GmbH)
**Umsetzer:** Benny (über Claude Code / VS Code)
**Status:** Konzept zur Abstimmung, anschließend Verfeinerung in der Umsetzung

Dieses Dokument ist stack-agnostisch gehalten. Stellen, an denen eine konkrete technische Entscheidung (Datenbank, lokales Modell, Embedding-Store, Hosting) fällig ist, sind mit **[Stack-Entscheidung]** markiert.

## 1. Ausgangslage und Zielbild

Im Herbst wurde von Zoho CRM und ActiveCampaign auf Brevo umgestellt. Die Marketing-Automation in Brevo ist brauchbar, der CRM-Teil ist es aus Sicht der Datenpflege nicht: keine kompakte Kontaktansicht auf einem Blatt, keine klickbaren Felder, kein Auswahlfeld für Tags, schwache Sortierung und Filterung, keine vergleichbare Dublettenführung.

Zielbild: Thoxan baut die CRM-Funktionalität als eigenes Modul der bestehenden AI-Platform. Es gibt genau eine Datenbasis, dieselbe, aus der auch tallyr und der KI-Assistent schöpfen. Brevo wird auf seine Stärke reduziert (Versand) und vom eigenen CRM mit den minimal nötigen Feldern versorgt.

### Rollenverteilung der Systeme

- **Eigenes CRM (AI-Platform-Modul):** System of Record für alle Kontakt- und Firmendaten. Quelle der Wahrheit.
- **Brevo:** reine Versand-Engine. Erhält per Push nur Vorname, Nachname, E-Mail und die für die Segmentierung nötigen Tags/Listen. Keine Pflege in Brevo.
- **Asana:** bleibt für Lead-Pipeline und Vorgänge zuständig. Das CRM bildet keine Kanban-Pipeline nach.
- **Wissensdatenbank / KI-Assistent:** liest aus derselben Datenbasis (siehe Kapitel 5).

### Nicht-Ziele (bewusst ausgeklammert)

- Keine Lead-/Sales-Pipeline im CRM (läuft in Asana).
- Keine Migration der Brevo-Marketing-Automation in Phase 1 (siehe Phasenplan, Kapitel 7).
- Keine ActiveCampaign-Altlasten (AC-Sync-Felder entfallen).

## 2. Architektur-Überblick

### Datenfluss

1. **Eingang:** Webformulare und Lead-Magnete schreiben neue Kontakte und Events ins CRM.
2. **Pflege:** Datenpflege ausschließlich im CRM (Kontaktansicht, Kapitel 4).
3. **Ausgang Versand:** Das CRM pusht den nötigen Minimaldatensatz nach Brevo (einseitig, CRM gewinnt). Brevo-seitige Änderungen (z. B. Hardbounce, Abmeldung) fließen über einen Rückkanal nur als Status zurück, überschreiben aber keine Stammdaten.
4. **Wissen:** Jede Änderung erzeugt ein abgeleitetes Kontakt-Profil für den RAG-Layer (Kapitel 5).

### Schichtenmodell

- **Relationale Schicht (Source of Truth):** strukturierte Daten für exakte Filter, Dublettenführung und Pflege. Hier liegen Kontakte, Firmen, Tags, Listen, Events, Zeitlinie.
- **Embedding-Schicht (abgeleitet):** semantischer Index für die Suche und den Kontext des KI-Assistenten. Wird aus der relationalen Schicht erzeugt, ist nie eigene Wahrheit.

> Wichtig: Die Filter-, Dedup- und Pflegewünsche brauchen zwingend die relationale Schicht. Eine reine Vektorsuche reicht dafür nicht.

**[entschieden]** Relationale Basis ist die bestehende MySQL der AI-Platform. Embedding wird in Phase 1 nicht gebaut. Der spätere Embedding-Index (Phase 2) wird voraussichtlich ein separater Baustein, da MySQL je nach Version nur schwache native Vektorsuche bietet. Damit die Trennung später sauber bleibt, gelten ab Phase 1 zwei Auflagen: stabile Kontakt-IDs und ein Lösch-Event-Log (oder Tombstones), siehe Phase 1 und Kapitel 6.

## 3. Datenmodell und Feldschema

Das Zoho-Modell ist Ausgangspunkt, wird aber bereinigt. Zwei Grundsätze:

1. **Trennung von Stammdaten, Marketing-Status und Automations-Zustand.** Im Zoho-Datensatz waren Trigger-Felder und Sync-Flags in den Kontakt gewandert. Diese Automations-Plumbing-Felder gehören nicht ins Stammdatum.
2. **Tags und Listen werden zu echten Beziehungen, nicht zu zwanzig Ja/Nein-Spalten.** Das ist der Hebel gegen die unübersichtliche "alles untereinander"-Kachel.

### Entitäten

- **Kontakt (Person)**
- **Firma**
- **Tag** (kontrolliertes Vokabular, manuelle Vergabe)
- **Segment** (gespeicherter, dynamischer Filter, keine manuelle Mitgliedschaft)
- **Liste** (Marketing-Listen mit expliziter Mitgliedschaft, ersetzt die "Aktive Listen"-Booleans)
- **Lead-Magnet** und **Lead-Magnet-Event** (ersetzt die "Genutzte Lead-Magneten"-Booleans, mit Zeitpunkt)
- **Adresse** (Typ: geschäftlich oder privat, am Kontakt hängend)
- **Aktivität / Zeitlinie** (append-only Ereignisprotokoll)
- **Verkaufschance** (optional, leichtgewichtig, siehe offene Punkte)

### Kontakt: Felder (gruppiert)

**Identität**
- Anrede, Titel, Vorname, Nachname
- Kontakt-Name (berechnet aus Anrede/Titel/Vor-/Nachname)
- Funktion, Abteilung
- Geburtsdatum

**Kommunikation**
- E-Mail (primär), E-Mail (zweit)
- Telefon, Telefon alternativ, Mobil, Fax
- Website

**Firma**
- Verknüpfung zur Entität Firma (statt freier Firmenname-Text). Mehrfachzuordnung später möglich.

**Profil**
- Interessen, Merkmale, Beschreibung (Freitext)
- Bevorzugtes Thema

**Marketing-Status**
- Kontakt-Status (z. B. Lead, Kunde, ehemaliger Kunde)
- Lead-Quelle
- Opt-in-Status inklusive Nachweis (Zeitpunkt, Quelle, Double-Opt-in-Beleg)
- Opt-in-Mail-Variante
- THX-Score

**Tags und Listen (Beziehungen, keine Spalten)**
- Tags: n:m über kontrolliertes Vokabular (z. B. Weihnachtskarte, Akquise-Mastermind)
- Listen-Mitgliedschaften: n:m mit Status aktiv/inaktiv (z. B. THX Hauptliste, Follow-Up Sichtbarkeit, Wunschkunden-Podcast)

**Adressen (als Unter-Datensätze, Typ geschäftlich/privat)**
- Straße, PLZ, Stadt, Bundesland, Land
- Damit entfällt die doppelte "Straße / Straße privat"-Zeilenflut. In der Ansicht wird standardmäßig die primäre Adresse gezeigt, die zweite ist aufklappbar.

**Social Media (als Schlüssel-Wert-Liste)**
- LinkedIn, XING, Facebook, Instagram, Twitter/X, YouTube

**System / Meta**
- Erstellt durch, erstellt am, geändert von, geändert am
- Kontakt-Besitzer
- Letzte Aktivität (berechnet aus Zeitlinie)
- Stand Datensatz (Pflegehinweis)

### Was bewusst entfällt oder wandert

- **AC-Sync, Layout:** entfallen (Altlasten).
- **Trigger-Felder (Trigger Kontaktformular, Strategie-Check, Terminbuchung, Lead-Magnet, Test-Trigger):** wandern in die Automations-Engine, nicht ins Kontakt-Stammdatum. In Phase 1 nicht nötig.
- **UTM-Felder (utm_source/medium/campaign/content/term, Herkunft/Referrer):** gehören konzeptionell zum Akquise-Event, nicht zur Person. Werden am Erst-Event (Lead-Magnet-Event oder Formulareintrag) gespeichert, nicht als feste Kontaktspalten.
- **Wunschkunden-Podcast-Felder (Titel, Subtitel, Release-Datum/URL/Mail):** das sind Kampagnen-Attribute, kein Personenmerkmal. Gehören an die Kampagne bzw. den Lead-Magneten, mit dem der Kontakt verknüpft ist.

### Firma: Felder

- Firmenname, Website, Branche
- Geschäftsadresse
- Verknüpfte Kontakte (1:n)
- Notizen

### Migration Zoho und Brevo

Beide Quellen liegen vor. Regeln:

**Match-Schlüssel (Reihenfolge):**
1. E-Mail (primär)
2. Mobil
3. Firmenname + Nachname

**Quellen-Priorität bei Konflikt:**
- Stammdaten (Name, Funktion, Adressen, Firma): Zoho gewinnt (gepflegter Datenbestand).
- Opt-in-Status und letzte Aktivität: Brevo gewinnt (aktueller).
- Tags und Listen: Vereinigung aus beiden Quellen.

**Bereinigung:**
- Test-Kontakte (z. B. Adressen auf test.de) markieren und vor Produktivnahme aussortieren.
- Dubletten nach dem Zoho-Muster: exakte Übereinstimmung automatisch mergen, Teil-Übereinstimmung über manuellen Merge-Dialog bestätigen (siehe Kapitel 4).

**Protokoll:** Die Migration schreibt einen Audit-Eintrag pro Datensatz (Quelle, gewählte Werte, gemergte Dubletten), damit nachvollziehbar bleibt, wie ein Kontakt entstanden ist.

## 4. Kontaktansicht und Usability

Das ist der Kern Deiner Wünsche. Leitbild: alle relevanten Infos kompakt auf einem Blatt, klickbar, schnell pflegbar.

### Kompaktansicht

- Foto, Name, Firma, Funktion
- Primäre E-Mail als `mailto:`-Link
- Mobil als `tel:`-Link
- Website als externer Link (neuer Tab)
- Tags als Chips, inline editierbar
- Status-Badge (Kontakt-Status) und THX-Score

### Erweiterte Ansicht

- Alle Felder in Gruppen-Kacheln (Identität, Kommunikation, Adressen, Social, Marketing-Status, Listen, Lead-Magnet-Events, Zeitlinie).
- Auf einem durchgehenden Blatt, ohne dass man für die Kern-Infos zwischen Reitern springen muss.
- Zweite Adresse und selten genutzte Felder aufklappbar statt dauerhaft sichtbar.

### Klickbarkeit (durchgängig)

- E-Mail: öffnet Mail-Programm.
- Telefon/Mobil: `tel:`-Aktion.
- Website und Social-Links: extern in neuem Tab.

### Tag-Pflege

- Auswahlfeld mit Autocomplete aus dem kontrollierten Vokabular.
- Neue Tags nur über eine bewusste "Neuen Tag anlegen"-Aktion, damit kein Tippfehler-Wildwuchs entsteht (das war der konkrete Brevo-Schmerz).
- Inline-Vergabe und -Entfernung direkt am Kontakt.

### Filter und Segmente

- Tag-Filter "ist" und "ist nicht" (z. B. alle ohne Tag Weihnachtskarte).
- Multifilter über mehrere Felder mit UND/ODER-Logik.
- Filter als Segment speicherbar und wiederverwendbar.

### Dublettenführung

- Übersicht potenzieller Dubletten nach E-Mail, Telefon oder Firmenname.
- Exakte Treffer automatisch zusammenführbar.
- Teil-Treffer über Merge-Dialog: Eintrag 1 gegen Eintrag 2, Feld für Feld auswählen, dann vereinen.

### Bearbeitung

- Inline-Bearbeitung der Felder, Enter speichert (Verhalten wie in Zoho).
- Jede Änderung erzeugt einen Zeitlinien-Eintrag (Kapitel 6).

## 5. Wissensdatenbank und RAG-Anbindung

Da das CRM ein Modul derselben Datenbasis ist, ist die Kontakttabelle automatisch Quelle für den KI-Assistenten.

### Zwei-Schichten-Prinzip

- **Relationale Schicht:** Wahrheit für Filter, Dedup, Pflege (Kapitel 2 und 3).
- **Embedding-Index:** abgeleitet, für semantische Suche und KI-Kontext.

### Was indexiert wird

Nicht alle Rohfelder als Vektor, sondern ein kompaktes, generiertes **Kontakt-Profil-Dokument** pro Kontakt: Name, Firma, Funktion, Status, Tags, Beschreibung, jüngste Aktivitäten und, sofern vorhanden, Verknüpfungen zu Projekten oder Transkripten. Das hält den Index sauber und die Treffer relevant.

### Sync-Logik

- Änderungs-getrieben: Wird ein Kontakt geändert, wird sein Profil-Dokument neu erzeugt und neu eingebettet.
- Auslöser ist derselbe Änderungs-Hook, der auch die Zeitlinie schreibt.

### Hybride Suche

- Strukturierte Metadaten-Filter (z. B. Status = Kunde, Tag = Wunschkunden-Podcast) kombiniert mit semantischer Ähnlichkeit. So bekommt der KI-Assistent präzisen, gefilterten Kontext statt unscharfer Vektortreffer.

**[Stack-Entscheidung, Phase 2]** Embedding-Modell und Index. Da die relationale Basis MySQL ist, wird der Index voraussichtlich ein separater Baustein neben der MySQL. Wichtig ist dann die Sync- und Lösch-Kopplung über die in Phase 1 vergebenen stabilen IDs und das Lösch-Event-Log.

## 6. DSGVO und lokale KI

Eigenes CRM heißt mehr Verantwortung, nicht weniger. Mit Zoho/Brevo gab es Auftragsverarbeiter samt AVV. Selbst betrieben trägt Thoxan die Pflichten vollständig.

### Hosting und Zugriff

- DB und Embedding-Store auf EU-Infrastruktur. **[Stack-Entscheidung]** Ort verknüpft mit der laufenden Cloud-Storage-Entscheidung (pCloud EU bzw. Nextcloud DE).
- Rollen- und Sichtbarkeitskonzept fürs Freelancer-Netz: differenzierte Rechte (z. B. Vollzugriff für Thomas und Benny, eingeschränkter Zugriff für Freelancer auf die für sie relevanten Kontakte).

### Betroffenenrechte und Löschung

- **Auskunft/Export:** Funktion "Datenauskunft erzeugen", die alle zu einer Person gespeicherten Daten zusammenstellt.
- **Berichtigung und Löschung:** mit **Lösch-Kaskade** über relationale Schicht und Embedding-Index. Eine Löschung muss die zugehörigen Vektoren mit entfernen.
- **Aufbewahrung:** Fristen pro Datenart, automatische Lösch- oder Anonymisierungsroutine.
- **Einwilligung:** Opt-in-Status revisionssicher mit Zeitpunkt, Quelle und Double-Opt-in-Beleg.

### Lokale KI: Aufgaben und Leitplanken

Lokales Modell statt Cloud-LLM für personenbezogene CRM-Daten. **[Stack-Entscheidung]** Modellwahl und Laufzeit (z. B. lokales Modell über eine lokale Laufzeitumgebung), inklusive deutschsprachiger Eignung.

Aufgaben der lokalen KI:
- Kontakt-Profil-Zusammenfassung für den RAG-Layer.
- Semantische Suche und Kontext für den Assistenten.
- Vorschläge zur Datenpflege (Dublettenkandidaten, fehlende Felder).
- Entwürfe für Mails oder Notizen.

Leitplanken (für "verlässlich bei CRM-Einträgen"):
- Die KI **schreibt nicht eigenmächtig** in Stammdaten. Sie erzeugt Vorschläge, ein Mensch bestätigt (Vorschlag, Review, Übernahme).
- KI-generierte und verifizierte Inhalte sind klar gekennzeichnet.
- **Audit-Log/Zeitlinie:** append-only, protokolliert jede Änderung mit wer, was, wann und Herkunft (Mensch oder KI). Das ist die Zoho-Zeitlinie, erweitert um KI-Provenienz.

### TOM (Stichpunkte zur Dokumentation)

- Verschlüsselung at rest und in transit
- Zugriffskontrolle und Rollen
- Backup-Konzept (z. B. NAS-Layer wie geplant)
- Logging und Nachvollziehbarkeit
- Pseudonymisierung, wo möglich

## 7. Phasenplan (Reifegrade)

**Phase 1: Fundament und Pflege**
- Datenmodell und Migration Zoho/Brevo inklusive Dedup
- **Saubere relationale Modellierung verbindlich:** Tags, Listen und Lead-Magnet-Events als echte Beziehungen, Zeitlinie als Ereignis-Log. Keine flache Breit-Tabelle mit den Zoho-Feldern 1:1 als Spalten. Das ist das Fundament für die spätere Mustererkennung und nicht verschiebbar.
- **Stabile Kontakt-IDs und Lösch-Event-Log (oder Tombstones) ab Phase 1**, damit ein späterer externer Embedding-Index synchron gehalten und bei einer Löschung mitbereinigt werden kann.
- Kontaktansicht (kompakt und erweitert), Klickbarkeit, Tag-Auswahlfeld, Filter, Merge
- Audit-Log/Zeitlinie
- Brevo-Push (Minimaldatensatz)
- DSGVO-Grundlagen: Rollen, Löschfunktion, Auskunft, Opt-in-Nachweis

**Phase 2: Wissensanbindung**
- Profil-Dokument je Kontakt, Embedding, hybride Suche
- KI-Assistent liest Kontakt-Kontext

**Phase 3: KI-gestützte Pflege**
- Vorschläge für Dedup und Anreicherung mit Review-Workflow
- Kennzeichnung KI vs. verifiziert durchgängig

**Phase 4: Marketing-Automation im eigenen System (optional, später)**
- Trigger und Journeys im CRM, schrittweise Ablösung der Brevo-Automationen
- Versand-Strategie (weiter über Brevo oder eigener Versand) separat entscheiden

## 8. Offene Entscheidungen für Benny

- **[entschieden]** Relationale Basis: bestehende MySQL der AI-Platform.
- **[Stack-Entscheidung, Phase 2]** Embedding-Index als separater Baustein neben der MySQL, mit Sync und Löschung über stabile IDs und Lösch-Event-Log.
- **[Stack-Entscheidung]** Lokales Modell und Embedding-Modell, inklusive deutschsprachiger Eignung und Performance.
- **[Stack-Entscheidung]** Finaler Hosting-Ort (verknüpft mit der Cloud-Entscheidung).
- Detailtiefe des Rollen- und Rechtemodells fürs Freelancer-Netz.
- Umgang mit Verkaufschancen: minimal im CRM (nur Wert und Stufe als Feld) oder vollständig in Asana belassen.
- Rückkanal Brevo: welche Status (Bounce, Abmeldung) fließen zurück und wie werden sie behandelt, ohne Stammdaten zu überschreiben.
