# Technische Spezifikation: KI-Mitarbeiter-Builder im ERP

## 1. Ziel

Im ERP soll ein Modul entstehen, mit dem jedes Teammitglied im Sparring mit KI einen spezialisierten KI-Mitarbeiter entwerfen kann.

Der Benutzer füllt kein langes Formular aus. Ein KI-gestützter Wizard stellt abhängig von den bisherigen Antworten gezielte Fragen, schlägt Aufgaben, Fähigkeiten, Zugriffe und Sicherheitsgrenzen vor und erstellt schrittweise ein vollständiges Rollenprofil.

Ein KI-Mitarbeiter ist dabei nicht nur ein gespeicherter System-Prompt. Er besteht aus:

- Rolle und Verantwortungsbereich
- Aufgaben und Nicht-Aufgaben
- Arbeitsabläufen
- Wissen und Fähigkeiten
- benötigter Software und Berechtigungen
- Kommunikationsstil
- Freigabe- und Eskalationsregeln
- Qualitätskriterien und Testfällen
- Gedächtnis und Feedback-Historie
- Versionen und Status im Lebenszyklus

## 2. Produktprinzipien

1. **Bedarf vor Lösung:** Zuerst wird geprüft, welches Problem gelöst werden soll und ob dafür wirklich ein KI-Mitarbeiter benötigt wird.
2. **Spezialisierung:** Lieber klar abgegrenzte Spezialisten als ein KI-Mitarbeiter für alles.
3. **Least Privilege:** Jeder KI-Mitarbeiter bekommt nur die unbedingt erforderlichen Zugriffe.
4. **Entwurf vor Aktion:** Im MVP erstellt die KI vorzugsweise Entwürfe. Externe oder kritische Aktionen erfordern eine Freigabe.
5. **Menschliche Verantwortung:** Für jeden KI-Mitarbeiter ist ein menschlicher Verantwortlicher hinterlegt.
6. **Nachvollziehbarkeit:** Aktionen, Profiländerungen, Freigaben und Feedback werden protokolliert.
7. **Lernfähigkeit mit Kontrolle:** Feedback darf Verbesserungen vorschlagen, aber keine sicherheitsrelevanten Regeln selbstständig verändern.
8. **Versionierung:** Jede veröffentlichte Profiländerung erzeugt eine neue Version und kann zurückgerollt werden.

## 3. Begriffe

### KI-Mitarbeiter

Eine spezialisierte KI-Rolle mit eigenem Profil, Fähigkeiten, Wissen, Berechtigungen und Gedächtnis.

### Skill

Eine wiederverwendbare Fähigkeit oder Arbeitsanweisung, beispielsweise „Kundenanfrage analysieren“ oder „Projektstatus zusammenfassen“.

### Workflow

Ein konkreter Ablauf aus Auslöser, Arbeitsschritten, Entscheidungen, Freigaben und Ergebnis.

### Tool / Integration

Eine Software oder Schnittstelle, die ein KI-Mitarbeiter verwenden kann, beispielsweise ERP, E-Mail, Kalender oder Dateisystem.

### Verantwortlicher

Ein menschlicher Mitarbeiter, der den KI-Mitarbeiter fachlich führt, Ergebnisse kontrolliert und Änderungen freigibt.

## 4. Rollen und Rechte menschlicher Benutzer

### Teammitglied

- KI-Mitarbeiter als Entwurf anlegen
- Wizard durchführen
- Aufgaben, Beispiele und gewünschte Arbeitsweise beschreiben
- eigene Entwürfe bearbeiten
- Feedback zu Ergebnissen geben
- Aktivierung und neue Zugriffe beantragen

### Verantwortlicher / Owner

- fachliche Regeln freigeben
- Testaufgaben definieren
- Ergebnisse bewerten
- vorgeschlagene Profilverbesserungen annehmen oder ablehnen
- KI-Mitarbeiter pausieren

### Administrator

- Integrationen verwalten
- Berechtigungen genehmigen
- kritische Aktionen freischalten
- Profile aktivieren oder deaktivieren
- Audit-Logs einsehen
- Versionen zurückrollen

Ein Teammitglied darf niemals allein über den Wizard kritische Zugriffsrechte aktivieren.

## 5. Lebenszyklus eines KI-Mitarbeiters

```text
draft -> review -> onboarding -> probation -> active -> paused -> archived
```

### Status

- `draft`: Profil wird erstellt und ist noch unvollständig.
- `review`: Profil wartet auf fachliche und gegebenenfalls technische Freigabe.
- `onboarding`: Wissen, Skills, Beispiele und Testfälle werden hinterlegt.
- `probation`: KI-Mitarbeiter arbeitet im Testbetrieb mit eingeschränkten Rechten.
- `active`: freigegebener produktiver Betrieb.
- `paused`: vorübergehend deaktiviert; Profil und Historie bleiben erhalten.
- `archived`: dauerhaft aus dem aktiven Team entfernt.

### Erlaubte Übergänge

- `draft -> review`: Mindestanforderungen erfüllt
- `review -> onboarding`: Profil fachlich freigegeben
- `onboarding -> probation`: Zugriffe genehmigt und Tests vorhanden
- `probation -> active`: definierte Qualitätsgrenze erreicht
- `probation -> onboarding`: Tests oder Qualität nicht ausreichend
- `active -> paused`: manuell oder automatisch bei Sicherheitsereignis
- `paused -> active`: erneute Freigabe
- alle nicht gelöschten Status `-> archived`: durch Administrator

## 6. Wizard: geführte Erstellung

Der Wizard ist als Chat mit einer parallel sichtbaren Profilvorschau umzusetzen. Nach jeder Antwort aktualisiert die KI strukturierte Profilfelder.

### Phase 1: Bedarf und Problem

Beispielfragen:

- Was soll dir oder deinem Team konkret abgenommen werden?
- Wo entsteht momentan der größte Flaschenhals?
- Wer erledigt diese Arbeit aktuell?
- Wie häufig kommt die Aufgabe vor?
- Wie viel Zeit kostet sie ungefähr?
- Was passiert, wenn sie nicht oder zu spät erledigt wird?

Ergebnis:

- Problemstatement
- erwarteter Nutzen
- betroffene Abteilung
- menschlicher Verantwortlicher
- geschätzte Häufigkeit

Die KI klassifiziert den Bedarf als:

- klassische Automatisierung
- einzelner Skill
- KI-Assistent mit menschlicher Freigabe
- eigenständiger KI-Mitarbeiter
- ungeeignet für KI

Wenn kein eigener KI-Mitarbeiter nötig erscheint, weist die KI darauf hin. Der Benutzer darf dennoch begründet fortfahren.

### Phase 2: Rolle und Zuständigkeit

Beispielfragen:

- Welche Ergebnisse soll die Rolle liefern?
- Welche Aufgaben gehören ausdrücklich dazu?
- Welche Aufgaben gehören ausdrücklich nicht dazu?
- Für welche Entscheidungen braucht sie Freigaben?
- Wann muss sie an einen Menschen eskalieren?

Ergebnis:

- Name
- Rollenbezeichnung
- Kurzbeschreibung
- Ziele
- Aufgaben
- Nicht-Aufgaben
- Verantwortungsbereich
- Eskalationsregeln

### Phase 3: Arbeitsweise und Workflows

Für jede Hauptaufgabe werden erfasst:

- Auslöser
- benötigte Eingaben
- Arbeitsschritte
- Entscheidungsregeln
- erwartetes Ausgabeformat
- Empfänger oder Zielsystem
- Freigabepunkt
- Fehler- und Eskalationsfall

Die KI soll den Ablauf als strukturierten Workflow vorschlagen und vom Benutzer bestätigen lassen.

### Phase 4: Wissen und Fähigkeiten

Beispielfragen:

- Welches Fachwissen wird benötigt?
- Welche internen Vorgaben oder Dokumente müssen bekannt sein?
- Gibt es Beispiele für besonders gute Ergebnisse?
- Welche Formulierungen, Aussagen oder Vorgehensweisen sind verboten?

Ergebnis:

- benötigte Skills
- Wissensquellen
- Positivbeispiele
- Negativbeispiele
- Qualitätsregeln
- verbotene Inhalte oder Handlungen

### Phase 5: Software und Zugriffe

Für jedes Tool werden getrennt erfasst:

- kein Zugriff
- lesen
- Entwurf erstellen
- verändern
- ausführen / versenden
- löschen
- administrieren

Die KI schlägt nur notwendige Zugriffe vor und begründet jeden Vorschlag.

Beispiel:

> Für das Analysieren von Kundenanfragen wird Lesezugriff auf Kunden und Projekte benötigt. Für E-Mails genügt im ersten Schritt „Entwurf erstellen“. Ein selbstständiger Versand ist nicht erforderlich.

### Phase 6: Persönlichkeit und Kommunikation

Zu erfassen sind:

- Tonalität
- gewünschte Kürze oder Detailtiefe
- Anrede
- erlaubte Sprachen
- Verhalten bei Unsicherheit
- gewünschte Rückfragen
- verbotene Floskeln oder Formulierungen

Persönlichkeit darf niemals fachliche oder sicherheitsrelevante Regeln überschreiben.

### Phase 7: Qualität und Testfälle

Die KI erstellt gemeinsam mit dem Benutzer mindestens drei Testfälle:

- normaler Standardfall
- unvollständige oder widersprüchliche Eingabe
- kritischer Grenz- oder Sicherheitsfall

Für jeden Testfall:

- Eingabe
- erwartetes Verhalten
- Muss-Kriterien
- unerlaubtes Verhalten
- Mindestscore

### Phase 8: Zusammenfassung und Freigabe

Vor Abschluss zeigt das System:

- Vollständigkeit in Prozent
- offene Fragen
- erkannte Risiken
- beantragte Zugriffe
- erforderliche Freigaben
- vorgeschlagene Testdauer

Der Benutzer kann das Profil als Entwurf speichern oder zur Prüfung einreichen.

## 7. Profilansicht im ERP

Die Detailansicht eines KI-Mitarbeiters erhält folgende Tabs:

1. Übersicht
2. Stellenbeschreibung
3. Workflows
4. Skills
5. Wissen
6. Tools und Berechtigungen
7. Persönlichkeit
8. Gedächtnis
9. Tests und Qualität
10. Feedback und Entwicklung
11. Aktivität / Audit-Log
12. Versionen

Auf der Übersicht:

- Name, Avatar, Rolle und Status
- Abteilung und Verantwortlicher
- letzte Aktivität
- aktuelle Qualitätsbewertung
- Anzahl offener Freigaben
- aktive Warnungen
- Buttons: testen, pausieren, bearbeiten, neue Version erstellen

## 8. Datenmodell

Die Bezeichnungen sind Vorschläge und können an die bestehende ERP-Struktur angepasst werden.

### `ai_employees`

```sql
id                  UUID PRIMARY KEY
tenant_id           UUID NOT NULL
name                VARCHAR(100) NOT NULL
role_title          VARCHAR(150) NOT NULL
short_description   TEXT
department_id       UUID NULL
owner_user_id       UUID NOT NULL
status              VARCHAR(30) NOT NULL DEFAULT 'draft'
avatar_url          TEXT NULL
problem_statement   TEXT
expected_benefit    TEXT
personality_config  JSON
memory_policy       JSON
current_version_id  UUID NULL
created_by          UUID NOT NULL
created_at          TIMESTAMP NOT NULL
updated_at          TIMESTAMP NOT NULL
archived_at         TIMESTAMP NULL
```

### `ai_employee_versions`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
version_number      INT NOT NULL
profile_snapshot    JSON NOT NULL
change_summary      TEXT
created_by          UUID NOT NULL
approved_by         UUID NULL
created_at          TIMESTAMP NOT NULL
approved_at         TIMESTAMP NULL
```

### `ai_employee_tasks`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
title               VARCHAR(200) NOT NULL
description         TEXT
included            BOOLEAN NOT NULL DEFAULT TRUE
frequency           VARCHAR(50) NULL
priority            INT NOT NULL DEFAULT 0
success_criteria    JSON
created_at          TIMESTAMP NOT NULL
updated_at          TIMESTAMP NOT NULL
```

### `ai_workflows`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
name                VARCHAR(200) NOT NULL
trigger_type        VARCHAR(50) NOT NULL
trigger_config      JSON
input_schema        JSON
steps               JSON NOT NULL
output_schema       JSON
approval_rules      JSON
escalation_rules    JSON
is_active           BOOLEAN NOT NULL DEFAULT FALSE
created_at          TIMESTAMP NOT NULL
updated_at          TIMESTAMP NOT NULL
```

### `ai_skills`

```sql
id                  UUID PRIMARY KEY
tenant_id           UUID NOT NULL
name                VARCHAR(150) NOT NULL
description         TEXT
instructions        TEXT NOT NULL
input_schema        JSON
output_schema       JSON
version             INT NOT NULL DEFAULT 1
status              VARCHAR(30) NOT NULL DEFAULT 'draft'
created_by          UUID NOT NULL
created_at          TIMESTAMP NOT NULL
updated_at          TIMESTAMP NOT NULL
```

Zuordnung über `ai_employee_skills(ai_employee_id, skill_id, config, priority)`.

### `ai_tool_permissions`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
tool_key            VARCHAR(100) NOT NULL
resource_scope      JSON NOT NULL
permission_level    VARCHAR(30) NOT NULL
status              VARCHAR(30) NOT NULL DEFAULT 'requested'
requested_by        UUID NOT NULL
approved_by         UUID NULL
requested_at        TIMESTAMP NOT NULL
approved_at         TIMESTAMP NULL
expires_at          TIMESTAMP NULL
```

Mögliche `permission_level`-Werte:

```text
none, read, draft, write, execute, delete, admin
```

### `ai_knowledge_sources`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
source_type         VARCHAR(50) NOT NULL
source_reference    TEXT NOT NULL
title               VARCHAR(200)
access_scope        JSON
sync_status         VARCHAR(30)
last_synced_at      TIMESTAMP NULL
created_at          TIMESTAMP NOT NULL
```

### `ai_test_cases`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
name                VARCHAR(200) NOT NULL
category            VARCHAR(50) NOT NULL
input_data          JSON NOT NULL
expected_behavior   TEXT NOT NULL
must_have           JSON
must_not_have       JSON
minimum_score       DECIMAL(5,2)
created_at          TIMESTAMP NOT NULL
updated_at          TIMESTAMP NOT NULL
```

### `ai_runs`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
workflow_id         UUID NULL
initiated_by        UUID NULL
status              VARCHAR(30) NOT NULL
input_data          JSON
output_data         JSON
model_info          JSON
permission_events   JSON
requires_approval   BOOLEAN NOT NULL DEFAULT FALSE
approved_by         UUID NULL
started_at          TIMESTAMP NOT NULL
finished_at         TIMESTAMP NULL
error_message       TEXT NULL
```

### `ai_feedback`

```sql
id                  UUID PRIMARY KEY
ai_employee_id      UUID NOT NULL
run_id              UUID NULL
user_id             UUID NOT NULL
rating              SMALLINT NULL
feedback_type       VARCHAR(50) NOT NULL
comment             TEXT
suggested_change    JSON NULL
status              VARCHAR(30) NOT NULL DEFAULT 'open'
created_at          TIMESTAMP NOT NULL
resolved_at         TIMESTAMP NULL
```

### `ai_audit_log`

```sql
id                  UUID PRIMARY KEY
tenant_id           UUID NOT NULL
ai_employee_id      UUID NULL
actor_type          VARCHAR(20) NOT NULL
actor_id            UUID NULL
event_type          VARCHAR(100) NOT NULL
event_data          JSON
created_at          TIMESTAMP NOT NULL
```

Audit-Logs dürfen nicht durch normale Benutzer oder KI-Mitarbeiter verändert werden.

## 9. Strukturierte Ausgabe des Wizard-Modells

Die KI darf Profilfelder nicht als unstrukturierten Fließtext zurückgeben. Jede Antwort des Modells soll validierbares JSON enthalten.

Beispiel:

```json
{
  "assistant_message": "Für diese Rolle fehlen noch die Eskalationsregeln.",
  "profile_patch": {
    "role_title": "Projektassistenz für Websites",
    "goals": ["Neue Kundenanfragen vollständig für die Bearbeitung vorbereiten"],
    "tasks": [
      {
        "title": "Kundenanfrage analysieren",
        "included": true
      }
    ]
  },
  "next_questions": [
    {
      "id": "escalation_missing_customer",
      "question": "Was soll Mara tun, wenn kein passender Kunde gefunden wird?",
      "type": "single_choice",
      "options": [
        "Neuen Kundenentwurf anlegen",
        "An Verantwortlichen eskalieren",
        "Vorgang abbrechen"
      ]
    }
  ],
  "completion": {
    "percentage": 64,
    "missing_sections": ["escalation_rules", "test_cases"]
  },
  "risk_flags": []
}
```

Der Server validiert den Patch gegen ein JSON-Schema. Unbekannte Felder und nicht erlaubte Status- oder Rechteänderungen werden verworfen.

## 10. KI-Orchestrierung

### Trennung der Kontexte

Zur Laufzeit werden getrennt geladen:

1. unveränderliche Sicherheits- und Mandantenregeln
2. freigegebene Version des Rollenprofils
3. relevante Skills
4. für die Aufgabe erforderliche Wissensquellen
5. auf Benutzer und Rolle begrenztes Gedächtnis
6. aktuelle Aufgabe und Eingabedaten

Sicherheitsregeln und Berechtigungen stehen außerhalb des durch Benutzer bearbeitbaren Prompts.

### Modellwahl

Die Modellwahl soll nicht fest im Profiltext stehen, sondern über eine technische Konfiguration erfolgen:

- Standardmodell
- optionales Modell je Skill oder Workflow
- Kostenlimit
- maximale Laufzeit
- maximale Tool-Aufrufe
- Fallback-Modell

Im MVP kann zunächst ein zentrales Modell verwendet werden. Die Struktur soll spätere Modellwahl erlauben.

### Gedächtnis

Zu unterscheiden sind:

- Session-Gedächtnis: nur für einen Vorgang
- Rollen-Gedächtnis: Erkenntnisse zur allgemeinen Arbeitsweise
- Benutzerbezogenes Gedächtnis: Präferenzen eines bestimmten Mitarbeiters
- explizite Regeln: freigegebene, versionierte Vorgaben

Gedächtniseinträge dürfen keine Berechtigungen erweitern und keine freigegebenen Regeln überschreiben. Sensible Daten müssen entsprechend Mandant, Rolle und Benutzer isoliert werden.

## 11. Sicherheits- und Freigabekonzept

### Risikoklassen

#### Niedrig

- Daten lesen
- Inhalte analysieren
- Zusammenfassungen erstellen
- interne Entwürfe erzeugen

#### Mittel

- Datensätze erstellen oder verändern
- interne Aufgaben zuweisen
- Dokumente zur Freigabe vorbereiten

#### Hoch

- Nachrichten extern versenden
- Termine verbindlich buchen
- Verträge, Angebote oder Preise kommunizieren
- Finanzdaten verändern
- Daten löschen
- Benutzer oder Rechte verwalten

Aktionen der hohen Risikoklasse benötigen im MVP immer eine menschliche Einzel-Freigabe.

### Technische Regeln

- Tool-Aufrufe serverseitig gegen Berechtigungen prüfen.
- Die KI erhält keine direkten Datenbank-Zugangsdaten.
- Jede Integration wird über definierte, eng begrenzte Funktionen angesprochen.
- Mandantenfilter werden serverseitig erzwungen.
- Schreibende Aktionen sind idempotent auszuführen, soweit möglich.
- Kritische Aktionen benötigen eine Vorschau vor Ausführung.
- Eingaben aus E-Mails, Dokumenten oder Websites gelten als nicht vertrauenswürdig.
- Prompt-Injection darf keine Systemregeln, Berechtigungen oder Freigaben umgehen.
- Bei Unsicherheit oder widersprüchlichen Daten wird eskaliert, nicht geraten.
- Kosten-, Laufzeit- und Aufruflimits pro Run definieren.
- Not-Aus zum sofortigen Pausieren aller KI-Mitarbeiter eines Mandanten vorsehen.

## 12. Feedback und Weiterentwicklung

Nach einem Run kann der Benutzer bewerten:

- gut
- unvollständig
- fachlich falsch
- falscher Ton
- unnötige Rückfrage
- Halluzination / erfundene Information
- Grenze oder Berechtigung überschritten
- sonstiges

Die KI darf aus Feedback Änderungsvorschläge erstellen, beispielsweise:

- neue Negativregel
- zusätzliches Positivbeispiel
- Anpassung eines Workflow-Schritts
- neuer Skill
- ergänzende Wissensquelle

Der Vorschlag wird als Diff angezeigt. Erst nach menschlicher Freigabe entsteht eine neue Profilversion.

## 13. Probezeit und Qualitätsbewertung

Während `probation` gelten:

- keine hochriskanten Aktionen ohne Einzel-Freigabe
- jeder Run wird bewertet oder stichprobenartig geprüft
- definierte Testfälle werden regelmäßig ausgeführt
- Fehlerquote, Freigabequote und Korrekturaufwand werden gemessen

Mögliche Kennzahlen:

- fachliche Korrektheit
- Vollständigkeit
- Einhaltung des Ausgabeformats
- Anzahl menschlicher Korrekturen
- Zahl unnötiger Eskalationen
- Zahl übersehener Eskalationen
- durchschnittliche Bearbeitungszeit
- Kosten pro Vorgang

Eine Aktivierung darf nur erfolgen, wenn alle Pflicht-Testfälle bestanden und alle benötigten Zugriffe freigegeben sind.

## 14. Vorgeschlagene API-Endpunkte

Die genaue Benennung an die vorhandene API-Konvention anpassen.

```text
POST   /api/ai-employees
GET    /api/ai-employees
GET    /api/ai-employees/{id}
PATCH  /api/ai-employees/{id}
POST   /api/ai-employees/{id}/submit-review
POST   /api/ai-employees/{id}/approve
POST   /api/ai-employees/{id}/pause
POST   /api/ai-employees/{id}/archive

POST   /api/ai-employees/{id}/wizard/messages
GET    /api/ai-employees/{id}/wizard/state

POST   /api/ai-employees/{id}/permissions/request
POST   /api/ai-permissions/{id}/approve
POST   /api/ai-permissions/{id}/reject

POST   /api/ai-employees/{id}/test-runs
POST   /api/ai-employees/{id}/runs
GET    /api/ai-runs/{id}
POST   /api/ai-runs/{id}/approve-action
POST   /api/ai-runs/{id}/feedback

GET    /api/ai-employees/{id}/versions
POST   /api/ai-employees/{id}/versions/{version}/restore
GET    /api/ai-employees/{id}/audit-log
```

## 15. MVP-Abgrenzung

### Im MVP umsetzen

- Liste und Detailansicht der KI-Mitarbeiter
- KI-geführter Erstellungs-Wizard
- strukturierte Profilfelder
- menschlicher Verantwortlicher
- Aufgaben und Nicht-Aufgaben
- einfache Workflow-Beschreibung
- Skills als textbasierte Arbeitsanweisungen
- Tool-Berechtigungen zunächst als beantragte/freigegebene Konfiguration
- Status `draft`, `review`, `probation`, `active`, `paused`
- Test-Chat mit KI-Mitarbeiter
- mindestens drei Testfälle
- Feedback pro Testlauf
- Profilversionen
- Audit-Log
- manuelle Freigabe vor jeder externen oder schreibenden Aktion

### Später umsetzen

- vollautomatische zeit- oder eventbasierte Workflows
- Kommunikation zwischen mehreren KI-Mitarbeitern
- KI-Teamleiter
- automatische Skill-Inferenz aus Feedback
- automatische Modellwahl je Aufgabe
- komplexe Langzeitgedächtnisse
- Kosten- und Performance-Optimierung über mehrere Modelle
- automatische Assessment-Center mit Security-, Halluzinations- und PR-Tests
- Marktplatz oder Vorlagenbibliothek für Rollen und Skills

## 16. MVP-Akzeptanzkriterien

1. Ein normales Teammitglied kann einen Entwurf ohne technische Prompt-Kenntnisse erstellen.
2. Der Wizard stellt kontextabhängige Fragen und aktualisiert eine strukturierte Profilvorschau.
3. Ein Profil kann erst zur Prüfung eingereicht werden, wenn Rolle, Aufgaben, Grenzen, Verantwortlicher und mindestens drei Testfälle vorhanden sind.
4. Beantragte Zugriffe sind einzeln sichtbar und begründet.
5. Nur ein Administrator kann kritische Zugriffe aktivieren.
6. Der KI-Mitarbeiter kann in einer isolierten Testansicht ausprobiert werden.
7. Testergebnisse können bewertet und kommentiert werden.
8. Feedback führt nur zu einem Änderungsvorschlag, nicht zu einer unkontrollierten Profiländerung.
9. Jede veröffentlichte Profiländerung ist versioniert.
10. Jeder Testlauf, Tool-Aufruf, Freigabevorgang und Statuswechsel erscheint im Audit-Log.
11. Ein pausierter KI-Mitarbeiter kann keine neuen Runs oder Tool-Aufrufe starten.
12. Alle Daten und Zugriffe sind strikt mandantenbezogen.

## 17. Beispielprofil

### Mara – Projektassistenz für Websites

**Abteilung:** WYLD.studio  
**Verantwortlicher:** Benny  
**Ziel:** Neue Kundenanfragen vollständig und strukturiert zur Bearbeitung vorbereiten.

#### Aufgaben

- neue Kundenanfragen analysieren
- bestehenden Kunden und Projekten zuordnen
- fehlende Informationen erkennen
- Rückfragen als Entwurf formulieren
- Aufgabenentwürfe im ERP erstellen
- nächsten sinnvollen Bearbeitungsschritt vorschlagen

#### Nicht-Aufgaben

- Preise oder Termine verbindlich zusagen
- Nachrichten selbstständig versenden
- Angebote oder Rechnungen verändern
- Datensätze löschen
- Zugangsdaten verarbeiten

#### Zugriffe

- Kunden: lesen
- Projekte: lesen
- Aufgaben: lesen und Entwurf erstellen
- E-Mail: Entwurf erstellen
- Angebote: kein Zugriff
- Rechnungen: kein Zugriff
- Passwörter: kein Zugriff

#### Standardworkflow

1. Neue Anfrage entgegennehmen.
2. Absender, Unternehmen und Inhalt extrahieren.
3. Bestehende Kunden und Projekte suchen.
4. Angaben auf Vollständigkeit prüfen.
5. Fehlende Informationen markieren.
6. Rückfragen und nächste Schritte als Entwurf erstellen.
7. Ergebnis dem Verantwortlichen zur Freigabe vorlegen.

#### Eskalation

- Kein eindeutiger Kunde gefunden
- Widersprüchliche Projektinformationen
- Preis-, Vertrags- oder Rechtsfrage
- sensible Daten in der Anfrage
- Verdacht auf schädliche oder manipulative Anweisung

#### Erfolgskriterien

- keine erfundenen Informationen
- klare Trennung zwischen vorhandenen Angaben und Vermutungen
- kurze, verständliche Rückfragen
- bestehende Kundendaten werden berücksichtigt
- keine externe Aktion ohne Freigabe

## 18. Umsetzungsreihenfolge

1. Vorhandene Benutzer-, Mandanten-, Rollen- und Audit-Struktur analysieren.
2. Datenmodell und Migrationen erstellen.
3. CRUD für KI-Mitarbeiter und Versionen implementieren.
4. Wizard-Oberfläche mit Chat und Profilvorschau bauen.
5. JSON-Schema für strukturierte Wizard-Antworten implementieren.
6. Review-, Freigabe- und Statuslogik ergänzen.
7. Test-Chat und Run-Protokollierung umsetzen.
8. Berechtigungsanträge und serverseitige Tool-Guards implementieren.
9. Feedback und Änderungsvorschläge ergänzen.
10. Sicherheits-, Mandanten- und Statusprüfungen automatisiert testen.

## 19. Arbeitsauftrag für Claude

> Analysiere zuerst die bestehende Architektur des ERP, insbesondere Authentifizierung, Mandantenfähigkeit, Benutzerrollen, Datenbankkonventionen, API-Struktur, UI-Komponenten und Audit-Logging. Erstelle danach einen konkreten Implementierungsplan, der diese Spezifikation in die vorhandene Architektur integriert. Nimm keine zweite parallele Rechte-, Benutzer- oder Audit-Struktur auf, wenn bereits passende Systeme vorhanden sind. Stelle Rückfragen, falls zentrale Architekturentscheidungen fehlen. Implementiere anschließend ausschließlich das definierte MVP in kleinen, überprüfbaren Schritten. Nach jedem Schritt sind Migrationen, Validierung, Rechteprüfungen und relevante Tests auszuführen. Kritische Tool-Aktionen dürfen niemals allein durch Modell- oder Prompt-Ausgaben autorisiert werden; die Berechtigungsentscheidung muss deterministisch und serverseitig erfolgen.

