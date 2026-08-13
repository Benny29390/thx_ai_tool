# Briefing: Transkription
## Modul für den Thoxan AI Assistant

---

## 1. Zweck

Das Modul **Transkription** automatisiert die Kette von Audio- oder Videoaufnahme bis hin zu strukturiertem Wissen. Eingehende Aufnahmen (Diktate, Workshop-Mitschnitte, Calls, Loom-Screencasts) werden lokal transkribiert, bei Bedarf mit Sprecher-Erkennung versehen, automatisch in ein passendes Format überführt (Protokoll, Memo, Aufgabenliste, Tutorial-Zusammenfassung) und in die bestehende Wissensdatenbank des AI Assistants eingespeist. Ziel ist die Eliminierung der bisherigen manuellen Zwischenschritte und der unmittelbare Zugriff auf alle Inhalte über die Suche der Wissensdatenbank.

---

## 2. Vorab-Analyse durch Claude Code

**Vor jeder Implementierung** prüft Claude Code das bestehende AI-Assistant-System und dokumentiert die Befunde in `docs/transkription-discovery.md`. Mindestens zu klären:

- Backend-Framework, Frontend-Stack, ORM, DB-System
- Styling-System und Komponenten-Bibliothek (Übernahme bestehender UI-Bausteine)
- **Schema der Wissensdatenbank**: Entitäten, Tagging, Kategorien, Kundenzuordnung, Berechtigungen, vorhandene Volltext- oder Vektor-Suche
- **Job-Queue**: Existiert ein asynchroner Worker-Mechanismus (z.B. Sidekiq, Laravel Horizon, Celery, BullMQ)? Falls nicht: Vorschlag in Discovery dokumentieren
- **Object Storage**: Wo werden Dateien aktuell gespeichert (lokal, S3-kompatibel, MinIO)?
- **LLM-Anbindung**: Welches Modell, welcher API-Wrapper, welche Token-Budgets pro Call
- **Auth- und Berechtigungssystem**: Wer darf welche Inhalte sehen
- **Hardware-Inventur**: Verfügbarkeit von GPU mit CUDA-Support für Whisper-Inference, sonst CPU-Fallback-Strategie

**Keine parallele Entwicklung.** Keine neuen Frameworks oder Designkomponenten, sofern im System schon Adäquates vorhanden ist. Notwendige neue Abhängigkeiten (WhisperX, pyannote.audio, ffmpeg, yt-dlp) werden vor Einbau dokumentiert und mit Thomas freigegeben.

---

## 3. Funktionsumfang MVP

Das Modul besitzt zwei Eingabe-Pipelines, eine gemeinsame Verarbeitungspipeline und mehrere Output-Vorlagen.

### 3.1 Eingabe-Pipelines

**Pipeline A: Datei-Upload (Diktate, Workshops, Calls)**
- Akzeptierte Formate: mp3, wav, m4a, aac, ogg (Audio); mp4, mov, mkv, webm (Video, Audiospur wird extrahiert)
- Maximale Dateigröße: konfigurierbar (Default 2 GB)
- Bulk-Upload möglich (mehrere Dateien gleichzeitig)
- Upload erfolgt über die bestehende File-Upload-Komponente des AI Assistants

**Pipeline B: Loom-Import**
- Eingabe über Loom-URL (öffentlich freigegebene Videos)
- Optional über Loom-API mit Account-Verknüpfung (in Discovery klären, ob im Scope MVP)
- Audio wird via yt-dlp oder offizielle Loom-API extrahiert
- Visueller Bildschirminhalt (Screen-OCR) ist im MVP **out of scope**

### 3.2 Verarbeitungs-Pipeline (asynchron, job-basiert)

1. **Persistenz Original**: Hochgeladene Datei wird unverändert in Object Storage abgelegt (dauerhaft archiviert, verschlüsselt at-rest)
2. **Job-Erstellung**: Eintrag in die Job-Queue mit Status "queued"
3. **Format-Normalisierung**: ffmpeg konvertiert in WAV mono 16 kHz
4. **Sprecherkonstellation**: User wählt vor Verarbeitung oder System bestimmt automatisch
   - Single-Speaker (Diktat, Loom): Whisper ohne Diarization
   - Multi-Speaker (Workshop, Call): WhisperX mit pyannote.audio
5. **Transkription**: WhisperX mit Modell `large-v3` (oder `distil-large-v3` für Geschwindigkeit, konfigurierbar)
   - Sprache: Auto-Detect mit Deutsch als Default
   - Word-Level Timestamps
6. **Post-Processing**:
   - **Korrektur-Dictionary** anwenden: Gaby (nicht Gabi), Benny (nicht Benni), FRYKA (nicht Frika), Thoxan (nicht Toxan), erweiterbar pro User
   - Benannte Sprecher zuordnen (User kann Sprecher-Labels nach Transkription benennen, z.B. "Sprecher_1 → Thomas Kilian")
7. **Output-Generierung** (LLM-Call an AI Assistant):
   - Vorlage-Auswahl: User wählt vor oder nach Transkription (siehe 3.3)
   - Generierung des gewünschten Outputs aus dem Transkript
8. **Wissensdatenbank-Integration** (siehe 3.4)

### 3.3 Output-Vorlagen

Pro Aufnahmetyp gibt es Standard-Vorlagen, die der User per Klick auswählt oder die das System vorschlägt:

- **Memo** (für Diktate): Kurze Zusammenfassung, Stichpunkte, daraus abgeleitete Aufgaben
- **Workshop-Protokoll** (Thoxan-Format): Strukturierte Mitschrift nach Themen, Entscheidungen, offenen Punkten. Ausgabe als DOCX mit Styles ThoxanH1 (Dokumenttitel), ThoxanH2, ThoxanH3, Standard, Bullet numId=5, Decimal numId=1/3. Content-Type immer `document.main+xml`, nicht `template.main+xml`.
- **Gesprächsnotiz mit Aufgaben** (für Calls): Kurze Zusammenfassung des Gesprächs, vereinbarte nächste Schritte als Aufgabenliste mit Verantwortlichkeit und Frist (sofern im Transkript erwähnt)
- **Tutorial-Zusammenfassung** (für Loom): Was wird gezeigt, Schritt-für-Schritt-Beschreibung, ggf. eingebettete Zeitstempel
- **Freie Notiz**: Reines Transkript ohne weitere Verdichtung

Jede Vorlage ist als bearbeitbares Prompt-Template im Admin-Bereich hinterlegt, damit Thomas die Verdichtungslogik anpassen kann.

### 3.4 Wissensdatenbank-Integration

- Nach erfolgreicher Verarbeitung wird das Ergebnis als Datensatz in der bestehenden Wissensdatenbank angelegt
- Felder werden gemäß bestehendem Schema befüllt (CC ermittelt das in der Discovery-Phase)
- **Verlinkung mit Original**: Der Eintrag enthält einen Link zur archivierten Audio-/Videodatei
- **Tagging**: Automatischer Tag-Vorschlag (Kunde, Projekt, Thema), den der User vor Speicherung bestätigt oder anpasst
- **Volltextsuche**: Sowohl Transkript als auch generierter Output (Protokoll/Memo) sind volltextindiziert
- **Falls Vektor-Suche im AI Assistant vorhanden**: Automatisches Embedding und Indexierung

---

## 4. Datenmodell (Vorschlag, anzupassen)

Tabellennamen in der Konvention des bestehenden Systems wählen. Wenn bereits Tabellen für Datei-Uploads, Jobs oder Knowledge-Items existieren: erweitern statt parallel anlegen.

- `transcription_uploads`: id, user_id, dateiname, storage_pfad, dateigroesse, mime_type, quelle (upload/loom), original_url (bei Loom), upload_datum, verschluesselt_at_rest, einwilligung_dokumentiert (bool), einwilligung_referenz
- `transcription_jobs`: id, upload_id, status (queued/running/done/failed), modell, sprache, sprecher_modus (single/multi), gestartet_at, beendet_at, fehlermeldung
- `transcription_results`: id, job_id, transkript_text, transkript_segmente_json (mit Word-Level Timestamps), sprecher_anzahl, sprache_erkannt
- `transcription_speakers`: id, result_id, sprecher_label_intern (z.B. SPEAKER_00), sprecher_name_benutzerdefiniert
- `transcription_outputs`: id, result_id, vorlage_typ, output_text, output_format (markdown/docx), knowledge_db_eintrag_id
- `transcription_correction_dictionary`: id, user_id, original, korrektur (globales Dict + user-spezifische Einträge)
- `transcription_template_prompts`: id, vorlage_typ, prompt_text, version, aktiv

Verknüpfung mit der bestehenden Wissensdatenbank über Fremdschlüssel `knowledge_db_eintrag_id`.

---

## 5. Datenschutz und Compliance

Da Roh-Audios dauerhaft archiviert werden, gelten verschärfte Anforderungen:

- **Verschlüsselung at-rest**: Alle archivierten Dateien sind verschlüsselt gespeichert (Schlüsselverwaltung über bestehende System-Konfiguration)
- **Verschlüsselung in-transit**: HTTPS für Upload, TLS für interne Zugriffe
- **Berechtigungslogik**: Wer darf welche Aufnahmen abrufen, anhören, transkribieren? Übernahme aus bestehendem AI-Assistant-Rechtemodell
- **DSGVO-Löschung**: Vollständiger Lösch-Workflow auf User-Anforderung, der Original-Datei, Transkript, Output und Wissensdatenbank-Eintrag konsistent entfernt
- **Einwilligungs-Dokumentation**: Bei Kundenaufnahmen kann der User beim Upload eine Einwilligungs-Referenz hinterlegen (Freitext oder Dateiupload). Pflicht-Kennzeichnung konfigurierbar.
- **Auftragsverarbeitung**: Da Transkription lokal läuft, fließen keine Audio-Daten an externe Provider. Der LLM-Call für Output-Generierung läuft über den bestehenden Provider des AI Assistants (im DPA bereits abgedeckt).
- **Audit-Log**: Zugriffe auf Originaldateien werden geloggt

---

## 6. UI-Anforderungen

Vollständige Übernahme der bestehenden Designkomponenten des AI Assistants. **Keine neuen UI-Bausteine**.

Vorgesehene Views:
1. **Upload-Ansicht**: Drag-and-Drop für Dateien, Loom-URL-Eingabe, Bulk-Upload, Auswahl Sprecher-Modus, optionale Einwilligungs-Referenz
2. **Job-Übersicht**: Liste aller laufenden und abgeschlossenen Jobs, Status, geschätzte Restzeit
3. **Transkript-Editor**: Anzeige des Transkripts mit Zeitstempeln, abspielbare Originaldatei, manuelle Korrekturen, Sprecher-Benennung
4. **Vorlage-Auswahl und Output-Vorschau**: User wählt Vorlage, sieht Vorschau des generierten Outputs, kann nachbessern lassen oder manuell editieren
5. **Wissensdatenbank-Verknüpfung**: Tag-Vorschläge, Bestätigung, Speicherung
6. **Korrektur-Dictionary-Verwaltung**: Pflege eigener Schreibweisen-Korrekturen
7. **Admin-Bereich für Vorlage-Prompts**: Bearbeitung der LLM-Prompts pro Vorlage

---

## 7. Out of Scope (MVP)

- Screen-OCR bei Loom-Videos (visueller Bildschirminhalt wird nicht ausgewertet)
- Echtzeit-Transkription während der Aufnahme
- Direkter Import aus Zoom-, Teams-, Google-Meet-Cloud
- Sentiment-Analyse oder automatische Emotionserkennung
- Mehrsprachige Transkripte in einer Datei (Code-Switching wird transkribiert, aber nicht separiert)
- Automatischer Versand des Protokolls an Workshop-Teilnehmer
- Versionierung von Output-Dokumenten
- Mobile App (Web-Responsiveness reicht)

---

## 8. Akzeptanzkriterien

1. Eine 60-minütige Workshop-Aufnahme (mp3, mehrere Sprecher) kann hochgeladen, transkribiert und mit Sprecher-Erkennung versehen werden
2. Ein 5-minütiges Diktat wird in unter 5 Minuten transkribiert (bei vorhandener GPU)
3. Ein Loom-Video kann per URL importiert und transkribiert werden
4. Die Korrektur-Dictionary-Einträge (Gaby, Benny, FRYKA, Thoxan) werden im Transkript korrekt angewandt
5. Aus einem Workshop-Transkript wird ein DOCX-Protokoll in Thoxan-Format generiert, das in Word geöffnet werden kann (Content-Type `document.main+xml`)
6. Der Wissensdatenbank-Eintrag enthält Transkript, generierten Output, Original-Link und User-Tags
7. Volltextsuche in der Wissensdatenbank findet einen Begriff aus einem Transkript
8. DSGVO-Löschung eines Uploads entfernt Originaldatei, Transkript, Output und Wissensdatenbank-Eintrag vollständig
9. Bei einem Audio mit nicht-deutscher Sprache wird die Sprache korrekt erkannt und transkribiert
10. Das System verarbeitet mindestens drei Jobs parallel ohne Fehler

---

## 9. Offene Punkte für Thomas (vor Implementierungsstart)

- **Hardware**: Welche GPU steht für Whisper zur Verfügung? Eigener Server, Hetzner, vorhandene AI-Assistant-Infra? Falls nur CPU: Modell-Größe und Erwartungshaltung an Verarbeitungszeit klären
- **Volumen pro Monat**: Wie viele Stunden Material werden erwartet? Steuert Hardware-Dimensionierung und Storage-Planung
- **Loom-Integration**: Reicht URL-basierter Import (für öffentliche Loom-Videos) oder soll auch Account-Anbindung über Loom-API umgesetzt werden? Letzteres ist deutlich mehr Aufwand
- **Vorlage-Vorauswahl**: Soll das System anhand der Aufnahme-Eigenschaften (Länge, Sprecheranzahl) eine Vorlage **vorschlagen** oder ist die Auswahl immer manuell?
- **Tagging-Logik**: Automatischer Tag-Vorschlag mit User-Bestätigung (empfohlen) oder vollautomatisch ohne Bestätigung?
- **Berechtigungs-Granularität**: Sieht jeder User nur eigene Aufnahmen, oder gibt es Team-Sichtbarkeit (z.B. alle Aufnahmen zum Kunden SMV)?
- **Einwilligungs-Pflicht**: Soll bei Kundenaufnahmen die Einwilligungs-Referenz **verpflichtend** sein, oder optional?
- **Synergie mit Prompt-Insights-Modul**: Soll das Anonymisierungs- und Korrektur-Dictionary aus Prompt-Insights gemeinsam genutzt werden, oder bewusst getrennt halten?

---

## 10. Vorgehensreihenfolge für Claude Code

1. Discovery-Phase abschließen, Befunde in `docs/transkription-discovery.md` dokumentieren
2. Offene Punkte aus Abschnitt 9 mit Thomas klären
3. Hardware- und Stack-Entscheidung (WhisperX-Setup, Job-Queue, Object Storage) finalisieren
4. Datenmodell finalisieren, Migrationen anlegen
5. Pipeline A (Datei-Upload) inklusive Verschlüsselung und Job-Queue
6. Verarbeitungspipeline (ffmpeg, WhisperX, Diarization, Post-Processing)
7. Transkript-Editor und Sprecher-Benennung
8. Output-Vorlagen und LLM-Anbindung (zunächst Memo und Workshop-Protokoll)
9. Wissensdatenbank-Integration inklusive Tagging
10. Pipeline B (Loom-URL-Import)
11. Restliche Output-Vorlagen (Call, Tutorial, freie Notiz)
12. Korrektur-Dictionary-Verwaltung und Admin-Bereich für Template-Prompts
13. DSGVO-Löschworkflow und Audit-Log
14. Abnahme gegen Akzeptanzkriterien (Abschnitt 8)
