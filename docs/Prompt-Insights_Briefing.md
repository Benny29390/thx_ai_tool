# Briefing: Prompt-Insights
## Modul für den Thoxan AI Assistant

---

## 1. Zweck

Das Modul **Prompt-Insights** analysiert exportierte Chatverläufe aus Claude.ai und ChatGPT, erkennt Muster im Prompting-Verhalten und leitet daraus konkrete Regeln und Prompt-Templates ab. Ziel ist die kontinuierliche Verbesserung der eigenen KI-Nutzung und die Definition verbindlicher "KI-Spielregeln" für den AI Assistant.

---

## 2. Vorab-Analyse durch Claude Code

**Vor jeder Implementierung** prüft Claude Code das bestehende AI-Assistant-System und dokumentiert die Befunde in einem kurzen Bericht (`docs/prompt-insights-discovery.md`). Mindestens zu klären:

- Programmiersprache und Framework (Backend und Frontend)
- Ordner- und Modul-Konventionen, Naming-Konventionen für Variablen, Routen, Tabellen
- Styling-System (CSS-Framework, Design-Tokens, vorhandene Komponenten-Bibliothek)
- Datenbank-Schema, ORM, Migration-Workflow
- Authentifizierungssystem und Berechtigungslogik
- Logging, Error-Handling, Konfigurationsmanagement (.env-Struktur)
- Bestehende Test-Infrastruktur
- LLM-Anbindung des AI Assistants (Provider, Modelle, API-Wrapper)
- Bereits vorhandene Datenmodelle für Konversationen, Nachrichten, Anhänge

**Keine parallele Entwicklung.** Es werden keine neuen Frameworks, Bibliotheken oder Designkomponenten eingeführt, die im System nicht bereits vorhanden sind. Wenn eine Funktion zwingend eine neue Abhängigkeit erfordert (z.B. ein lokales Embedding-Modell, siehe Layer 3), wird das vor dem Einbau mit Thomas geklärt.

**Diskrepanzen** zwischen diesem Briefing und dem bestehenden System werden dokumentiert und mit Thomas besprochen, nicht eigenmächtig aufgelöst.

---

## 3. Funktionsumfang MVP

Das Modul arbeitet in vier Verarbeitungsschichten. Layer 1 bis 3 sind deterministisch und benötigen keine LLM-Calls. Erst Layer 4 ruft den AI Assistant auf.

### Layer 1: Import & Parsing (deterministisch)

**Eingabe**: ZIP-Dateien aus dem offiziellen Export von Claude.ai und ChatGPT, hochgeladen durch den User über die bestehende File-Upload-Komponente des AI Assistants.

**Verarbeitung**:
- Erkennung der Quelle anhand der Dateistruktur (beide nutzen `conversations.json`, aber unterschiedliches Schema)
- Extraktion aller User-Prompts mit Metadaten pro Prompt:
  - Quelle (Claude / ChatGPT)
  - Externe Chat-ID, Chat-Titel, Erstellungsdatum, letztes Update
  - Position im Chat (Index, Initialprompt vs. Folgeprompt)
  - Wortzahl, Zeichenzahl
  - Anhang ja/nein, Anhangstyp
  - Zugehörige Assistant-Antwort (gekürzt auf 500 Zeichen, als Kontext)
  - Wochentag, Uhrzeit
- **Anonymisierung** (verpflichtend, vor jeder weiteren Verarbeitung und vor jedem DB-Insert):
  - Ersetzen aller E-Mail-Adressen durch `<EMAIL>`
  - Ersetzen von Telefonnummern, IBANs, URLs durch Platzhalter
  - Ersetzen bekannter Eigennamen aus einer userbezogenen Whitelist (Kunden, Mitarbeiter, Firmennamen)
- Speicherung als strukturierte Datensätze gemäß Datenmodell (Abschnitt 4)

**Ausgabe**: Eine durchsuchbare, filterbare Übersicht aller importierten Prompts in der UI.

### Layer 2: Statistik & Aggregation (deterministisch)

**Auswertungen**:
- Verteilung der Promptlängen (Median, P25, P75, Max)
- Top-Verben am Promptanfang (typische Eröffnungsmuster)
- Verhältnis Initialprompts zu Folgeprompts pro Chat
- Durchschnittliche Iterationszahl pro Chat
- Zeitliche Verteilung (Wochentag/Uhrzeit-Heatmap)
- Quellenvergleich (Claude vs. ChatGPT, Nutzungsverteilung über Zeit)
- Anteil Chats mit Anhängen

**Ausgabe**: Dashboard mit den Basis-Kennzahlen, eingebettet in das bestehende Dashboard-Layout des AI Assistants.

### Layer 3: Clustering (deterministisch, lokal)

**Verarbeitung**:
- Embeddings für alle Initialprompts (multilinguales Modell, z.B. `paraphrase-multilingual-MiniLM-L12-v2` via sentence-transformers, lokal)
- Clustering via HDBSCAN (bevorzugt, da keine fixe Cluster-Zahl nötig) oder KMeans
- Automatische Cluster-Beschriftung über häufigste Begriffe (TF-IDF auf Cluster-Ebene)
- Identifikation von "Noise-Prompts" (HDBSCAN-Cluster -1), separate Anzeige

**Ausgabe**: Übersicht der Themencluster mit Größe, Beispiel-Prompts und Top-Begriffen.

**Hinweis**: Falls sentence-transformers nicht ins bestehende System passt, alternativ Embeddings über die bereits angebundene LLM-API. Entscheidung in der Discovery-Phase.

### Layer 4: Regelableitung (LLM-gestützt)

**Eingabe an den AI Assistant** pro Cluster:
- Cluster-Beschreibung und -Größe
- Statistik aus Layer 2 für diesen Cluster
- Stichprobe von 20 bis 30 anonymisierten Initialprompts plus deren Folgeprompts

**Prompt an den Assistant** (anpassbar im Admin-Bereich):

> Analysiere die folgende Prompt-Stichprobe. Identifiziere:
> 1. die typische Struktur der Initialprompts in diesem Cluster,
> 2. wiederkehrende Lücken im Initialprompt, die später nachgefragt oder nachgebessert wurden,
> 3. wiederkehrende Korrektur-Patterns in den Folgeprompts (z.B. "kürzer", "förmlicher", "kein em-dash"),
> 4. einen "Idealen Prompt"-Bauplan für diesen Cluster,
> 5. drei bis fünf konkrete Spielregeln, die in jeden Initialprompt dieses Clusters einfließen sollten.

**Ausgabe**: Pro Cluster ein Regel-Set, das der User kuratieren, freigeben, ablehnen oder bearbeiten kann. Freigegebene Regeln landen in der zentralen **Spielregel-Bibliothek**.

---

## 4. Datenmodell (Vorschlag, anzupassen)

Tabellennamen in der Konvention des bestehenden Systems wählen. Falls bereits Tabellen für Konversationen oder Nachrichten existieren, prüfen, ob diese erweitert werden können statt parallele Strukturen aufzubauen.

- `prompt_imports`: id, user_id, dateiname, quelle, importdatum, anzahl_chats, status, fehlermeldung
- `prompt_chats`: id, import_id, externe_chat_id, titel, quelle, erstellungsdatum, anzahl_prompts
- `prompt_messages`: id, chat_id, position, rolle (user/assistant), inhalt_anonymisiert, wortzahl, hat_anhang, anhang_typ, timestamp
- `prompt_clusters`: id, import_id (oder global), label, beschreibung, anzahl_prompts, top_begriffe
- `prompt_cluster_assignments`: prompt_message_id, cluster_id, distanz
- `prompt_rules`: id, user_id, cluster_id (nullable für globale Regeln), regeltext, status (vorschlag/freigegeben/verworfen), quelle (auto/manuell), erstellungsdatum
- `prompt_anonymization_whitelist`: id, user_id, original, platzhalter

---

## 5. Datenschutz & Anonymisierung

- Die hochgeladenen ZIPs werden nach erfolgreicher Verarbeitung **gelöscht**, nicht dauerhaft gespeichert.
- In der Datenbank stehen ausschließlich anonymisierte Inhalte.
- Die Anonymisierungs-Whitelist wird pro User gepflegt, nicht global.
- LLM-Calls in Layer 4 erfolgen ausschließlich mit anonymisierten Daten. Vor jedem Outbound-Call findet eine letzte Regex-Prüfung statt (Mail-Pattern, IBAN-Pattern, deutsche Telefonnummern).
- Export der Spielregel-Bibliothek durch den User möglich (JSON, Markdown).
- Vollständiger Löschworkflow: User kann einen Import inklusive aller abgeleiteten Daten löschen.

---

## 6. UI-Anforderungen

Das Modul integriert sich nahtlos in die bestehende Oberfläche. **Es werden keine eigenen Designkomponenten neu gebaut**, sondern ausschließlich vorhandene Bausteine verwendet (Buttons, Formularelemente, Tabellen, Modals, Dashboard-Kacheln, Toast-Notifications).

Vorgesehene Views:
1. **Import-Übersicht**: Liste der bisherigen Imports, Upload-Button, Status pro Import, Löschen-Aktion
2. **Prompt-Browser**: Tabelle aller importierten Prompts mit Filtern (Quelle, Cluster, Zeitraum, Initialprompt vs. Folgeprompt, Volltextsuche)
3. **Statistik-Dashboard**: Kennzahlen aus Layer 2, eingebettet ins bestehende Dashboard-Layout
4. **Cluster-Ansicht**: Liste der Cluster mit Größe, Sample-Prompts und Top-Begriffen
5. **Regel-Editor**: Freigabe/Ablehnung von Regelvorschlägen aus Layer 4, manuelles Hinzufügen eigener Regeln
6. **Spielregel-Bibliothek**: Zentrale Liste freigegebener Regeln, exportierbar
7. **Anonymisierungs-Whitelist**: Verwaltung der eigenen Eigennamen-Liste

---

## 7. Out of Scope (MVP)

- Mehrbenutzer-Zugriff auf gemeinsame Importe (jeder User sieht nur eigene Daten)
- Automatisches Re-Clustering bei neuen Importen (manuell ausgelöst)
- Automatische Anwendung der Spielregeln auf Live-Prompts im AI Assistant (zunächst nur dokumentiert)
- Andere Quellen außer Claude und ChatGPT (z.B. Gemini, Mistral, Perplexity)
- Versionierung der Spielregeln
- Diff-Ansicht zwischen zwei Imports ("Wie hat sich mein Prompting in den letzten 6 Monaten verändert?")

---

## 8. Akzeptanzkriterien

1. Ein ZIP-Export aus claude.ai kann hochgeladen, geparst und anonymisiert in die DB überführt werden.
2. Ein ZIP-Export aus chat.openai.com kann ebenso hochgeladen werden.
3. Die Basis-Statistik (Layer 2) ist im Dashboard sichtbar.
4. Mindestens fünf Themencluster werden für einen Datensatz von mindestens 100 Chats automatisch erkannt.
5. Layer 4 liefert pro Cluster mindestens drei kuratierbare Regelvorschläge.
6. Der User kann Regeln freigeben, ablehnen oder bearbeiten.
7. Die freigegebenen Regeln sind als Markdown und JSON exportierbar.
8. Keine Klartext-Mailadressen oder Eigennamen aus der Whitelist sind in der DB sichtbar (Prüfung über SQL-Stichprobe).
9. Ein vollständiger Lösch-Workflow funktioniert für einen Import inklusive aller abhängigen Datensätze.

---

## 9. Offene Punkte für Thomas (vor Implementierungsstart)

- **Naming**: Bleibt es bei "Prompt-Insights" oder ein anderer Name (z.B. Promptarium, Mustermacher)?
- **Mehrsprachigkeit**: Liegen die Prompts überwiegend auf Deutsch vor, oder auch nennenswert auf Englisch? Steuert die Wahl des Embedding-Modells.
- **Whitelist-Pflege**: Soll die Eigennamen-Whitelist initial aus dem bestehenden Kunden-/Mitarbeiter-Modell des AI Assistants befüllt werden, sofern vorhanden?
- **Spielregel-Anwendung**: Sollen freigegebene Regeln später automatisch in System-Prompts des AI Assistants einfließen? (Out of Scope MVP, aber relevant für die Architekturentscheidung jetzt — z.B. ob `prompt_rules` an Module/Use-Cases gekoppelt wird.)
- **LLM-Modell für Layer 4**: Das im AI Assistant ohnehin angebundene Modell verwenden, oder ein separates für die Analyse?
- **Embeddings**: Lokales Modell (sentence-transformers) als neue Abhängigkeit akzeptabel, oder über die bestehende LLM-API laufen?

---

## 10. Vorgehensreihenfolge für Claude Code

1. Discovery-Phase abschließen, Befunde in `docs/prompt-insights-discovery.md` dokumentieren
2. Offene Punkte aus Abschnitt 9 mit Thomas klären
3. Datenmodell finalisieren, Migration anlegen
4. Layer 1 (Import & Anonymisierung) inklusive Tests
5. Layer 2 (Statistik) inklusive Dashboard-Integration
6. Layer 3 (Clustering)
7. Layer 4 (Regelableitung) und Regel-Editor
8. Spielregel-Bibliothek und Export
9. Whitelist-Verwaltung
10. Abnahme gegen Akzeptanzkriterien (Abschnitt 8)
