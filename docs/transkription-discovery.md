# Transkription — Discovery-Bericht

**Datum:** 2026-05-25
**Bezug:** [docs/Transkription_Briefing.md](Transkription_Briefing.md)

## 1. Stack & Konventionen
Wie im [Prompt-Insights-Discovery §1-§4](prompt-insights-discovery.md) dokumentiert: PHP 8.4 ohne Framework, MySQL, vanilla JS, Tab-Hub-Pattern, `--d-*`-Tokens, Capability-System.

## 2. Bestehende Wissensdatenbank — voll nutzbar

Schema bereits da und sehr passend ([App.php:2062+](../core/App.php)):

| Tabelle | Inhalt |
|---|---|
| `knowledge_documents` | Container mit `source_type` ENUM (`upload`/`url`/`text`/`chat`) + `customer_id` + `category` + `tags` JSON + FULLTEXT-Index |
| `knowledge_chunks` | Volltext-Suche pro Chunk |
| `knowledge_embeddings` | 1536d-Vector pro Chunk, OpenAI |
| `knowledge_entities` + `_relations` + `_chunk_entities` | Wissens-Graph |
| `knowledge_usage` | Tracking welche Chunks im Chat retrieved wurden |

**Services**: `KnowledgeService`, `KnowledgeIngestService`, `KnowledgeEmbeddingService`, `KnowledgeExtractionService`, `KnowledgeRetrievalService` — Pipeline „Dokument rein → chunken → embedden → entities extrahieren → suchbar" ist komplett.

**Plan**: Transkription erweitert `source_type` um `'transcript'` und nutzt `KnowledgeIngestService` für die Übergabe. Wir bauen KEIN paralleles Storage-Modell.

## 3. Job-Queue (fehlt — Cron-Pattern bewährt)

Kein Sidekiq/Horizon/etc. **Bestehendes Pattern**: Cron-Skripte alle N Minuten (pp-asana-sync, pm-check, pp-notifications).

**Vorschlag**: Status-basierte Queue in `tr_jobs`-Tabelle (queued → running → done/failed). Worker-Skript `scripts/tr-worker.php` läuft alle 2 Min, pickt sich `queued`-Jobs, setzt auf `running`, verarbeitet, schreibt Ergebnis. Pro Cron-Lauf max. 1 Job (kein Parallelismus — bei Bedarf erweitern).

## 4. Object Storage

Aktuell: lokale Disk (`/var/www/uploads/...`). 43 GB frei.

**Plan**: Originale unter `/var/www/uploads/transcription/{user_id}/{job_id}/...`, verschlüsselt at-rest via OpenSSL (Wiederverwendung `\Core\Crypto`).

## 5. Hardware-Realität

| Komponente | Status |
|---|---|
| **GPU/CUDA** | **Keine** (kein nvidia-smi) |
| CPU-Cores | 16 |
| RAM | 30 GB (12 GB frei) |
| Disk | 75 GB total, 43 GB frei |
| **ffmpeg** | **Nicht installiert** — würde gebraucht |
| **Python-Whisper** | **Nicht installiert** |
| **yt-dlp** | **Nicht installiert** (Loom-Import) |

**Konsequenz**: Lokales WhisperX-large-v3 auf CPU schafft ca. 2–4× Realzeit (60 min Audio = 15–30 min Verarbeitung) — machbar, aber Python-Setup nötig + 3 GB Modell-Download. Diarization (pyannote.audio) braucht zusätzlich HuggingFace-Token + ist auf CPU spürbar langsamer.

## 6. Transkriptions-Engine — drei Optionen

| Option | Kosten | Diarization | Setup-Aufwand | Latenz |
|---|---|---|---|---|
| **A. OpenAI Whisper API** | $0.006/min ($0.36 für 60 min) | ❌ nein | minimal (curl) | ~30s für 60 min |
| **B. Lokales WhisperX** | gratis | ✓ via pyannote (HF-Token) | hoch (Python, 3 GB Modell, ffmpeg, ggf. CUDA) | 15–30 min CPU |
| **C. Deepgram Nova-3** | $0.0043/min ($0.26 für 60 min) | ✓ inkl. | minimal (curl) + Account | ~10s für 60 min |
| **D. AssemblyAI** | $0.0042/min ($0.25 für 60 min) | ✓ inkl. | minimal + Account | ~30s für 60 min |

**Empfehlung MVP**: **Option C (Deepgram Nova-3)** — günstiger als OpenAI, Diarization inkludiert, schnellste Latenz, einfachste Integration (REST). Bei Datenschutzbedenken: später auf Option B umstellbar, dieselbe Service-Schicht.

Im Briefing §5 steht „Da Transkription lokal läuft, fließen keine Audio-Daten an externe Provider" — Diskrepanz. Cloud-Engine bedeutet Daten gehen an Deepgram (DPA prüfen) oder OpenAI. Bei strenger Anforderung → Option B (lokal).

## 7. LLM-Anbindung
[Wie Prompt-Insights]: `AIService` mit Standard-Modell aus `/admin/settings?tab=ki` für Output-Generierung (Memo/Protokoll/etc.).

## 8. Auth + Berechtigung
Neue Capability `transcription`. Pro User eigene Aufnahmen (Default). Optionale Team-Sichtbarkeit pro Kunde — siehe offene Frage §10.4.

## 9. DOCX-Generierung (Workshop-Protokoll im Thoxan-Format)
Bestehender Code: `XlsxWriter.php` (Excel), `chat-export-docx.php` (Chat-Export als DOCX). Letzteres nutzt `ZipArchive` direkt — Pattern vorhanden, Thoxan-Styles + `document.main+xml`-Content-Type sind übertragbar.

## 10. Diskrepanzen + offene Punkte
- §5 „lokale Transkription" vs Cloud-Engine-Empfehlung — User-Entscheidung
- yt-dlp + Loom-API: nur falls Loom-Import im MVP
- Korrektur-Dictionary: bewusst getrennt von Prompt-Insights-Whitelist (andere Logik: Schreibfehler-Fix vs Anonymisierung)

## 11. Vorgeschlagene Module-Struktur
- Sidebar: **„Wissen" → „Transkripte"** (Unterpunkt, analog zu Prompt-Insights unter KI&Modelle)
- Route: `/admin/transkription` (Tab-Hub mit Upload / Jobs / Transkript-Editor / Vorlagen / Korrekturen / Admin-Prompts)
- Tabellen: `tr_uploads`, `tr_jobs`, `tr_results`, `tr_speakers`, `tr_outputs`, `tr_corrections`, `tr_templates`
