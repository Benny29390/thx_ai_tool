# Transkriptions-Modul

Audio/Video transkribieren, Sprecher erkennen, Protokolle ableiten, ins Wissen einspeisen.
Lokal mit faster-whisper (CPU, On-Premise) — keine Cloud, keine Audio verlaesst den Server.

Briefing: [Transkription_Briefing.md](Transkription_Briefing.md)
Discovery: [transkription-discovery.md](transkription-discovery.md)

## Bedienung (User-Sicht)

1. **Sidebar → „Wissen"** oeffnet die Wissensdatenbank. Die Tab-Leiste oben
   wechselt zu **„Transkripte"**.
2. **Upload-Tab**: Datei waehlen oder per Drag & Drop, optional Kunde / Modell /
   Sprache / Sprecher-Modus / Einwilligungs-Notiz. Alternativ
   **Loom-URL** einfuegen — yt-dlp holt das Audio automatisch.
3. **Jobs-Tab**: Live-Liste mit Status (queued / running / done / failed),
   Fortschrittsbalken. Polling alle 5 s solange aktive Jobs laufen.
4. **Editor-Tab**: fertige Jobs auswaehlen, Sprecher umbenennen
   (SPEAKER_00 → „Benny"), Segmente inline editieren, Korrektur-Dictionary
   anwenden.
5. **Vorlagen-Tab**: per Klick einen Output erzeugen (Memo / Workshop-Protokoll
   / Call-Notiz / Tutorial / Rohtext). Workshop-Protokoll wird als DOCX
   bereitgestellt (pandoc oder HTML-Fallback).
6. **Wissen-Tab**: Transkripte mit einem Klick in die Wissensdatenbank
   einspeisen (Chunks, Embeddings, Entities, RAG-faehig).
7. **Korrekturen-Tab**: Wortliste pflegen („Frika" → „FRYKA"). Scope `user`
   = nur fuer mich, `global` = fuer alle (nur Admin).
8. **Admin-Tab** (nur Admin): die System-Prompts der Output-Vorlagen pflegen.

## Architektur

```
Upload (Browser)
   │  multipart/form-data ODER {"loom_url": "..."}
   ▼
api/v1/admin/transkription/jobs.php
   │  → TranskriptionService::ingestUpload() / ingestLoomUrl()
   │     - move_uploaded_file / yt-dlp
   │     - Core\Crypto::encrypt (AES-256-GCM) -> storage/transkription/uploads/*.enc
   │     - INSERT tr_uploads + tr_jobs (status=queued)
   ▼
Cron alle 2 Min: /etc/cron.d/ki-tool-transkription
   │  → scripts/transkription-worker.php (Dispatcher, max 3 parallel)
   │     - stale-Lock-Cleanup (>30 Min hangende running-Jobs → failed)
   │     - atomic UPDATE status=queued→running
   │     - nohup php transkription-process-job.php <id> &
   ▼
scripts/transkription-process-job.php (pro Job)
   │  1) Storage entschluesseln in Tempdir
   │  2) ffmpeg → WAV mono 16 kHz nach storage/transkription/audio/job-<id>.wav
   │  3) /opt/ki-tool-whisper/venv/bin/python whisper-runner.py
   │       └─ faster-whisper (compute_type=int8, CPU, VAD)
   │       └─ optional pyannote/speaker-diarization-3.1 (braucht HF_TOKEN)
   │  4) INSERT tr_results + tr_speakers, UPDATE tr_jobs status=done
   ▼
Editor / Vorlagen / Wissen
```

## Datenmodell

| Tabelle           | Inhalt                                                            |
|-------------------|-------------------------------------------------------------------|
| `tr_uploads`      | verschluesselte Quelldatei + Kunde + Source (`upload` / `loom`)   |
| `tr_jobs`         | queued / running / done / failed, Modell, Sprache, Sprecher-Modus |
| `tr_results`      | Volltext, segments_json, Sprecher-Count, Sprache, Wortanzahl      |
| `tr_speakers`     | label_internal (`SPEAKER_00` …) + name_custom („Benny")           |
| `tr_outputs`      | LLM-Output pro (result, template_type), output_format, docx_path  |
| `tr_corrections`  | original → correction, scope = `user` \| `global`                 |
| `tr_templates`    | Prompt-Vorlage je `template_type` (memo / workshop / call / …)    |

`knowledge_documents.source_type` ENUM um `transcript` erweitert.

## API-Endpunkte

```
GET  /api/v1/admin/transkription/jobs                       Liste
POST /api/v1/admin/transkription/jobs                       Upload (multipart) ODER {loom_url}
DELETE /api/v1/admin/transkription/jobs/{id}                Job + Quelle loeschen

GET  /api/v1/admin/transkription/jobs/{id}/result           Transkript + Segmente + Sprecher
PUT  /api/v1/admin/transkription/jobs/{id}/result           {transcript_text?, segments?}
PUT  /api/v1/admin/transkription/jobs/{id}/speakers         {speakers: [{id, name_custom}]}
POST /api/v1/admin/transkription/jobs/{id}/apply-corrections

GET  /api/v1/admin/transkription/corrections
POST /api/v1/admin/transkription/corrections                {original, correction, scope}
DELETE /api/v1/admin/transkription/corrections/{id}

GET  /api/v1/admin/transkription/jobs/{id}/outputs
POST /api/v1/admin/transkription/jobs/{id}/outputs          {template_type}
GET  /api/v1/admin/transkription/outputs/{id}/download      DOCX-Download
POST /api/v1/admin/transkription/jobs/{id}/to-knowledge     {output_id?}

GET  /api/v1/admin/transkription/templates
PUT  /api/v1/admin/transkription/templates/{id}             nur Admin
```

Alle Routen brauchen `CAP_TRANSCRIPTION` (Default-Cap fuer admin + manager).

## Wichtige Dateien

| Datei                                                              | Aufgabe                                       |
|--------------------------------------------------------------------|-----------------------------------------------|
| `services/TranskriptionService.php`                                | Upload, Loom, Job-Listing, Loeschen           |
| `services/TranskriptionEditorService.php`                          | Volltext-Edit, Sprecher, Korrektur-Dict       |
| `services/TranskriptionOutputService.php`                          | LLM-Vorlagen + DOCX + Wissens-Anbindung       |
| `scripts/transkription-worker.php`                                 | Cron-Dispatcher (max 3 parallel)              |
| `scripts/transkription-process-job.php`                            | Pro-Job-Pipeline                              |
| `/opt/ki-tool-whisper/whisper-runner.py`                           | faster-whisper Wrapper, JSON-Output           |
| `views/admin/transkription/index.php`                              | Tab-Hub                                       |
| `views/admin/transkription/_tab_*.php`                             | 7 Tabs (Upload / Jobs / Editor / Vorlagen / … |
| `views/wissen/_wissen_tabs.php`                                    | Gemeinsame Top-Tabs Wissensdatenbank ↔ Tx     |
| `/etc/cron.d/ki-tool-transkription`                                | `*/2 * * * *`                                 |

## Akzeptanz-Tests (8 aus Briefing)

| # | Kriterium                                                          | Status |
|---|--------------------------------------------------------------------|--------|
| 1 | 60-Min Workshop mit Diarization                                    | ◐ — Pipeline da, in Praxis testen (pyannote+HF_TOKEN noetig fuer „echte" Sprecher) |
| 2 | 5-Min Diktat < 5 Min Verarbeitung                                  | ✓ — auf 16 Kernen mit tiny/base unter 1 Min |
| 3 | Loom-Import                                                        | ✓ — yt-dlp integriert |
| 4 | Korrektur-Dictionary                                               | ✓ — global + user, per Wort-Grenze, im Editor anwendbar |
| 5 | DOCX-Generierung (Workshop-Format)                                 | ✓ — pandoc installiert, sonst HTML-Fallback |
| 6 | Wissens-DB-Integration                                             | ✓ — Volltext oder gewaehlter Output via KnowledgeIngestService |
| 7 | Volltext-Suche                                                     | ✓ — implizit ueber knowledge_chunks (FULLTEXT bestehend) |
| 8 | DSGVO-Loeschung                                                    | ✓ — Job-Delete entfernt Storage-File + WAV + DB-Rows |

Zusatz-Akzeptanz:

- **Sprache-Erkennung**: faster-whisper liefert `language_detected` automatisch, wird angezeigt.
- **3 parallele Jobs**: Cron-Dispatcher limitiert auf `MAX_PARALLEL=3`, atomar via UPDATE.
- **Verschluesselung at-rest**: jede hochgeladene Datei AES-256-GCM (`enc:v1:`-Praefix) bis zur Transkription.
- **Polling**: Jobs-Tab pollt alle 5 s solange queued/running, danach passiv.

## Konfiguration / Secrets

| Setting / Env             | Wo                                | Zweck                                                |
|---------------------------|-----------------------------------|------------------------------------------------------|
| `app.encryption_key`      | `config/config.php`               | Key fuer Upload-Verschluesselung (32 Byte hex)       |
| `openai_api_key`          | `settings` (verschluesselt)       | LLM-Calls fuer Output-Vorlagen + Knowledge-Ingest    |
| `default_model`           | `settings`                        | LLM-Modell fuer Vorlagen-Generierung                 |
| `huggingface_token`       | `settings` (optional)             | Aktiviert pyannote-Diarization (multi-speaker)       |

## Modell-Hinweise

| Modell     | Speicher | Geschwindigkeit | Qualitaet | Empfehlung                |
|------------|----------|-----------------|-----------|---------------------------|
| `tiny`     | ~75 MB   | sehr schnell    | basic     | Kurzes Diktat, Smoke-Test |
| `base`     | ~150 MB  | schnell         | ok        | Stichproben               |
| `small`    | ~500 MB  | mittel          | gut       | Standard-Memos            |
| `medium`   | ~1.5 GB  | langsam         | sehr gut  | **Empfohlen** fuer 60-Min |
| `large-v3` | ~3 GB    | sehr langsam    | exzellent | wenn jedes Wort zaehlt    |

Modell-Cache liegt unter `/opt/ki-tool-whisper/models/`, wird beim ersten Job
automatisch von HuggingFace geladen.

## Bekannte Limits

- **Synchrone Verschluesselung**: Upload wird komplett in RAM verschluesselt
  (`Crypto::encrypt`). Bei 500 MB-Files merklich, ueber 1 GB nicht ratsam.
  Wenn Bedarf entsteht: Chunked-Mode in `Crypto` ergaenzen.
- **Pyannote optional**: ohne `huggingface_token` faellt das Skript still auf
  einen einzelnen Sprecher zurueck.
- **Pandoc optional**: wenn nicht installiert, ist die „DOCX"-Datei in
  Wirklichkeit eine HTML-Datei mit `.docx`-Endung (Word oeffnet das trotzdem).
- **Concurrency 3**: konservativ wegen 16 GB RAM. Kann in
  `scripts/transkription-worker.php` per `MAX_PARALLEL` hochgesetzt werden.
- **Stale-Locks**: Jobs mit `running` und ohne Lebenszeichen > 30 Min werden
  automatisch auf `failed` gesetzt.

## Logs

- Dispatcher: `storage/logs/transkription.log`
- Pro Job: `storage/logs/transkription-job-<id>.log`
