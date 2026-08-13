# RAG-/Chat-Optimierung — Maßnahmen aus dem Beratungsgespräch

Stand: 11.06.2026. Grundlage ist ein Beratungsgespräch zur Chat-Qualität (lokale vs.
Cloud-Modelle, Halluzinationen, Auffinden der richtigen Wissens-Chunks).

## Leitgedanke des Beraters

Erst die ganze Kette **vom Dateneingang bis zum Prompt** ausreizen, **danach erst** ein
größeres Sprachmodell kaufen ("schwäbisch sparsam"). Reihenfolge:

> Datenquelle → Chunking → Embedding → Vektor-DB → **Retrieval/Reranking** → Query → **Prompt** → erst dann größeres Modell

Kernbefund: Erfundene Inhalte ("Schiffsrückstände") und fehlende korrekte Infos
("Cabrio/Bootsbau") liegen **nicht am Modell**, sondern am Auffinden und Sortieren der
richtigen Textstellen. Größter Hebel: **Reranking**.

## Maßnahmen + Status

| # | Maßnahme | Status |
|---|----------|--------|
| 1 | **Vollständiges Logging** je Anfrage: finaler System-Prompt, User-Frage, RAG-Chunks (inkl. Inhalt) + Antwort, zusätzlich zu Tokens/Dauer/Modell. 90-Tage-Rotation. Admin-Detailansicht. | ✅ erledigt |
| 2 | **Reranking** im Retrieval-Pfad: breit holen → Rerank-Modell sortiert neu → Top-K ans LLM. | ✅ **aktiv seit 30.06.2026** (lokaler Endpoint von Benny, scharfgeschaltet — siehe unten) |
| 7 | **Ganzdokument-Laden für Projektpläne**: Plan-Treffer in Top-3 → kompletter Plan statt Einzel-Chunks. | ✅ erledigt 24.06.2026 (siehe unten) |
| 3 | **Globaler System-Prompt** zentral pflegbar (`/admin/settings?tab=chat`) + harte Faktentreue-Regel. | ✅ erledigt |
| 4 | **Gold-Standard-Schleife**: 10 Top-Fragen, perfekte Antworten definieren, Prompt automatisch dagegen tunen. | offen (setzt #1+#3 voraus) |
| 5 | **Chunking** an echten Beispielen prüfen (Sinnabschnitte vs. hart abgeschnitten). | ✅ Diagnose erledigt (siehe unten) |
| 6 | **Embedding** prüfen / später auf eigenem Korpus feintunen. | offen |
| – | Chunks im Chat anzeigen | war bereits vorhanden |
| – | Hybrid-Suche (Dense + Sparse + Graph, RRF) | war bereits vorhanden |

## Schritt 1 — Logging (erledigt)

- Neue Tabelle `llm_request_detail` (separat von `llm_request_log`, damit die schlanke
  Performance-Tabelle schnell bleibt). Spalten: system_prompt, user_message,
  response_text, rag_chunks (JSON inkl. Chunk-Inhalt), conversation_id, message_id.
- Geschrieben über `Services\LlmRequestLog::record([... 'detail' => [...]])`
  (`services/LlmRequestLog.php`). Aufruf in `api/v1/chat-stream.php` (Erfolg + Fehlerpfad).
- **90-Tage-Rotation**: opportunistisch (~1 % der Schreibvorgänge räumen alte Details ab),
  kein Cron nötig.
- Ansicht: `/admin/llm-performance` → Liste "Letzte Anfragen (mit Detail)" → Klick öffnet
  `/admin/llm-request-detail?id=…` (System-Prompt, Frage, Chunks mit Score/Inhalt, Antwort).

## Schritt 3 — Globaler System-Prompt → zentrale Prompt-Verwaltung (erledigt)

Zunächst als Setting `chat_system_prompt` + Tab `tab=chat` gebaut, dann ausgebaut zur
**zentralen Prompt-Verwaltung** (siehe unten). Der alte `tab=chat` wurde durch `tab=prompts`
ersetzt; der frühere Wert wurde nach `system_prompts.chat_default` migriert.

## Zentrale Prompt-Verwaltung (erledigt 11.06.2026)

- `services/SystemPromptService.php`: Code-Standards in `defaults()`, optionale Overrides in
  Tabelle `system_prompts`. `get($key)` = Override falls gesetzt, sonst Code-Standard
  (sicherer Rückfall, funktioniert auch ohne Migration).
- Verwaltung: **`/admin/settings?tab=prompts`** (`views/admin/settings/_tab_prompts.php`),
  je Prompt editierbar, „Standard wiederherstellen" via `api/v1/admin/system-prompts.php`.
- Angebunden (schrittweise erweiterbar): `chat_default`, `auto_classifier`,
  `customer_detection`, `knowledge_grounding`, `dual_compare`. Weitere Prompts: Key in
  `defaults()` ergänzen + Aufrufstelle auf `SystemPromptService::get('<key>')` umstellen.
- **Versionierung**: Tabelle `system_prompt_versions`; `set()`/`reset()` schreiben jede
  Änderung mit Zeitstempel + Nutzer + Notiz. UI: „Verlauf"-Button je Prompt mit
  „Wiederherstellen". API-Aktionen `history` / `restore`. Grundlage für Nachvollziehbarkeit
  und Gold-Standard-Optimierung.

## Info-Panel pro Chat-Nachricht (erledigt 11.06.2026)

- Info-Icon in den Aktions-Icons jeder Assistant-Nachricht (`views/chat.php`).
- Klick → rechtes Drawer `#msg-info-panel` mit Modell, Tokens, Dauer, Wissens-Chunks
  (Quelle-Badge: CRM rot) und vollständigem System-Prompt.
- Backend: `GET /chat/message-debug?id=<message_id>` (`api/v1/chat-message-debug.php`),
  liest `llm_request_detail` per `message_id`; nur Owner/Admin. Alte Nachrichten ohne Detail
  bekommen einen freundlichen Hinweis.
- **Dual-Chat**: Info-Icon bei beiden Antworten (slot a/b, message_id aus `groupMessagesForDisplay`)
  und am Vergleich. Der Vergleichs-Call (`chat-dual-compare.php`) loggt jetzt ebenfalls ins
  `llm_request_detail` (use_case `dual_compare`, message_id) — gleiche Nachvollziehbarkeit wie
  normale Antworten. Vergleich wird weiterhin als Nachricht gespeichert (fließt in den Chat ein).

## Schritt 5 — Chunking-Diagnose (erledigt 11.06.2026)

Chunking-Code: `KnowledgeIngestService::chunkText()` (absatz-basiert, kombiniert bis
1200 Zeichen, splittet lange Absätze an Satzgrenzen, fügt letzten Satz als Overlap voran)
plus zweiter Pfad `prepareFromChunks()` (vorstrukturierte Chunks, z.B. CRM/Projektplan).

**Befund (96.500 synchronisierte Chunks gesamt):**
- Das Prosa-Chunking selbst ist **solide**: gesunde Größen, saubere Satzenden, Overlap
  funktioniert. Wittekind-Daten gesund (Asana ø142 W, Web ø159 W). Kein „Metzger".
- **Hauptproblem: CRM-Mini-Chunks dominieren die Vektor-DB.** crm_kontakt (34.914 Chunks,
  ø53 W) + crm_firma (11.395 Chunks, ø14 W) = **~48 % aller Chunks** im aktiven
  Chat-Retrieval (Qdrant `knowledge_bge_m3`). Winzige, oft fast identische Datensätze →
  Rauschen / „hard negatives", die die Top-Treffer verstopfen und relevante Chunks verdrängen.
- `prepareFromChunks()` umgeht die Mindestlänge (CHUNK_MIN_CHARS=100), die `chunkText()`
  anwendet → daher die Mini-Chunks. 12 komplett leere Chunks (word_count=0) existieren.
- Qdrant-Sync (`QdrantSyncService`) filtert **nicht** nach source_type oder Mindestlänge —
  alles wird embedded.

**Empfehlungen (nach Hebel):**
1. CRM-Mini-Chunks aus dem Chat-Retrieval heraushalten (Filter nach source_type oder
   Mindest-Wortzahl beim Retrieve, oder eigene Collection). Größte Rausch-Reduktion, wenig Aufwand.
2. Mindestlänge auch im `prepareFromChunks()`-Pfad erzwingen + leere Chunks (word_count=0) verwerfen.
3. Prosa-Chunking selbst: kein dringender Änderungsbedarf.

**Entscheidung (11.06.2026): Erst messen, nicht filtern.** Bevor wir CRM-Chunks
ausschließen, mit dem neuen Logging an echten Fragen beobachten, wie oft CRM-Mini-Chunks
relevante Treffer verdrängen. Dafür loggt das Detail-Log jetzt pro Chunk zusätzlich
`source_type` + `word_count`; die Detailansicht (`/admin/llm-request-detail`) zeigt CRM-Chunks
mit rotem Badge. Auswerten, dann über Filter/Segregation entscheiden.

## Schritt 2 — Reranking (erledigt 11.06.2026, aus bis konfiguriert)

- `services/RerankService.php`: einheitliche Cohere-/Jina-/Voyage-kompatible API
  (`POST {model, query, documents, top_n}` → `{results|data:[{index, relevance_score|score}]}`).
  Deckt **lokal** (Infinity/TEI mit Cohere-kompatiblem `/rerank`, z.B. `bge-reranker-v2-m3`)
  **und Cloud** (Cohere `rerank-v3.5`, Jina `jina-reranker-v2-base-multilingual`,
  Voyage `rerank-2.5`) über denselben Code-Pfad ab. Parsing per `parseResults()` unit-getestet.
- Integration in `api/v1/chat-stream.php`: wenn aktiv, `rerank_candidates` (Default 40) breit
  holen → dedup → rerank → `rerank_top_n` (Default 8). Reranked Chunks erhalten Quelle `rerank`
  (im Info-Panel sichtbar). Sauberer Fallback: schlägt der Reranker fehl, wird auf Top-N gekürzt.
- Einstellungen: `/admin/settings?tab=ki` → Card „Reranking" (an/aus, Anbieter, Modell, URL,
  API-Key, Kandidaten, Top-N) + **„Verbindung testen"**-Button (Settings-Action `test_rerank`).
  Settings-Keys `rerank_enabled|provider|url|model|api_key|candidates|top_n` (Prefix `rerank_`).
- **Infra (Benny):** für „Lokal" einen Cohere-kompatiblen `/rerank`-Endpoint mit
  `bge-reranker-v2-m3` bereitstellen (z.B. Infinity), URL in den Einstellungen eintragen, testen,
  aktivieren. Für Cloud nur Anbieter + API-Key wählen.
- **Logging pro Nachricht**: Spalte `llm_request_detail.rerank_meta` (JSON) hält je Nachricht
  Anbieter, Modell, Kandidatenzahl, behalten, Dauer und die **vollständige Rangliste mit Scores**
  (auch aussortierte Kandidaten, mit `kept`-Flag). Dafür werden ALLE Kandidaten gescored, dann auf
  Top-N gekürzt. Sichtbar im Chat-Info-Panel (ℹ️) und auf `/admin/llm-request-detail`.

### Deploy-Rezept lokaler Reranker (für Benny, On-Prem)

Ziel: ein Cohere-kompatibler `/rerank`-Endpoint mit `bge-reranker-v2-m3` auf der
Inferenz-Infra (gleiche Box wie der bge-m3-Embedding-Server `ki.thoxan.com`).
Empfehlung: **Infinity** (https://github.com/michaelfeil/infinity), liefert ein
Cohere-kompatibles `/rerank` out of the box.

```bash
# Variante Docker (CPU; mit GPU --gpus all und das CUDA-Image verwenden)
docker run -d --restart unless-stopped --name infinity-rerank \
  -p 7997:7997 \
  michaelfeil/infinity:latest \
  v2 --model-id BAAI/bge-reranker-v2-m3 --port 7997
# -> Endpoint dann: http://<host>:7997/rerank
```

Hinter denselben Reverse-Proxy wie die Embeddings hängen (analog
`https://ki.thoxan.com/embeddings/...`), z.B. als `https://ki.thoxan.com/rerank/rerank`,
und mit demselben Inference-Key absichern (die App fällt für `provider=local` ohne eigenen
`rerank_api_key` automatisch auf `local_api_key` zurück, siehe chat-stream.php).

Danach in der App (`/admin/settings?tab=ki`, Card „Reranking"):
1. Anbieter `local`, Modell `bge-reranker-v2-m3`, URL = die `/rerank`-URL von oben.
2. **„Verbindung testen"** → muss grün sein (Settings-Action `test_rerank`).
3. `rerank_enabled` einschalten. Kandidaten 40 / Top-N 8 als Start.

> ⚠️ Bis der Endpoint steht, `rerank_enabled` auf 0 lassen — sonst kostet jeder Chat
> den 8s-Connect-Timeout, bevor der Fallback greift.

### Live-Konfiguration (scharfgeschaltet 30.06.2026)

Benny hat den Endpoint als eigenen Compose-Service (Image `michaelf34/infinity`) hinter
denselben Reverse-Proxy wie die Embeddings gehängt (nicht das docker-run-Snippet oben — das
`michaelfeil/infinity`-Image samt Netzwerk-/GPU-Flags lief auf der Box nicht). `restart:
unless-stopped`, reboot-fest. Auth über denselben Bearer-Key wie `/embeddings/` (401 ohne/falschen Key).

Eingetragen unter `/admin/settings?tab=ki`, Card „Reranking", und getestet (grün):

- `rerank_provider` = `local`
- `rerank_url` = `https://ki.thoxan.com/rerank/rerank`
- `rerank_model` = **`BAAI/bge-reranker-v2-m3`** (volle ID! Infinity serviert das Modell unter
  der vollen ID, analog zum Embedding-Server `BAAI/bge-m3`. Mit dem Kurznamen
  `bge-reranker-v2-m3` bleibt „Verbindung testen" rot.)
- Key: kein eigener `rerank_api_key` → Fallback auf `local_api_key`
- `rerank_candidates` = 40, `rerank_top_n` = 8, `rerank_enabled` = 1

Verbindungstest: top_index=0, top_score≈0.9997 (relevantester Treffer zuerst). Bennys
Gegenprobe: „FRYKA Q2 Reporting Gesamtplan" 0,77 vs. Einzelzeile/Rauschen ~0,0001.

## Schritt 7 — Ganzdokument-Laden für Projektpläne (erledigt 24.06.2026)

**Problem:** Ein Projektplan ist in viele kleine Item-Chunks zerlegt (z.B. FRYKA Q2 2026 =
24 Chunks). Das Top-N-Retrieval liefert aber pro Nachricht nur ~8–10 Chunks ans Modell.
Damit ist ein **vollständiges Reporting aus dem ganzen Plan strukturell unmöglich** (8 < 24) —
selbst bei perfektem Ranking. Beobachtet in Chat 277: Mail-Vervollständigung mit erfundenen
Tagessätzen, weil die KI nie alle Plan-Zeilen sah.

**Lösung** (`api/v1/chat-stream.php`, im Knowledge-V2-Block nach dem Rerank/Slice):
Taucht in den **Top-3-Treffern** ein Chunk mit `source_type='projektplan'` auf, wird dessen
Dokument komplett geladen: die Einzel-Chunks dieses Plans fliegen aus der Liste, stattdessen
kommt der **vollständige `original_content` als ein Block nach vorne**. Das Kontextbudget wird
für diesen Fall auf `min(34000, max(20000, Planlänge+4000))` angehoben; XXL-Pläne werden bei
30.000 Zeichen gekappt. Nur für Cloud-/große Modelle (lokale Modelle vertragen den langen
Kontext nicht → unverändert). Greift **unabhängig vom Reranking** schon jetzt; mit aktivem
Reranking landet der Plan auch bei Reporting-artigen Fragen zuverlässig in den Top 3.
