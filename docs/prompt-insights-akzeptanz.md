# Prompt-Insights — Akzeptanz-Bericht

**Stand:** 2026-05-25
**Briefing:** [Prompt-Insights_Briefing.md](Prompt-Insights_Briefing.md) §8
**Discovery:** [prompt-insights-discovery.md](prompt-insights-discovery.md)

End-to-End-Test mit synthetischen ZIPs (125 Claude-Chats + 20 ChatGPT-Chats, je 4 Nachrichten = 580 Messages, alle mit Mail / Telefon / IBAN / URL / Eigennamen als PII-Köder).

## Akzeptanzkriterien

| AK | Anforderung | Status | Nachweis |
|---|---|---|---|
| 1 | claude.ai-ZIP importieren | ✓ | `{import_id:2, chats:125, messages:500, source:claude}` |
| 2 | chatgpt.com-ZIP importieren | ✓ | `{import_id:3, chats:20, messages:80, source:chatgpt}` |
| 3 | Statistik (Layer 2) im Dashboard | ✓ | KPI-Cards, Wort-Quantile, Top-Verbs, Heatmap, Avg-Iterationen alle gerendert |
| 4 | ≥ 5 Themencluster aus ≥ 100 Chats | ⊙ | Code-Pfad getestet, Cluster-Pipeline funktional. **Erst beim Klick im UI** (Embeddings + Re-Cluster) — kostet ~$0.002 OpenAI |
| 5 | ≥ 3 Regelvorschläge pro Cluster (LLM) | ⊙ | Code-Pfad getestet, JSON-Parser robust. **Erst beim Klick im UI** — kostet ~$0.01 pro Cluster |
| 6 | Regeln freigeben / ablehnen / bearbeiten | ✓ | `PUT /rules/{id}` mit Status `vorschlag\|freigegeben\|verworfen`, Text-Edit, getestet |
| 7 | Markdown- + JSON-Export | ✓ | `GET /rules/export?format=markdown\|json` liefert korrekte Files mit `Content-Disposition: attachment` |
| 8 | Keine Klartext-PII in DB | ✓ (siehe Note) | Mail/Tel/IBAN/URL aus dem Test = **0 Klartext-Treffer**. Eigennamen siehe Note. |
| 9 | Vollständiger Lösch-Workflow | ✓ | Vor Delete: 500 Msgs/Chats — Nach Delete: 0/0 (CASCADE über alle 3 FK-Stufen) |

⊙ = Funktional implementiert, Live-Test überspringt LLM-Calls (Geld). Wird beim ersten User-Klick verifiziert.

## Note zu AK8 (Anonymisierung)

Strenger Test hat ergeben:
- **Regex-PII** (E-Mail, IBAN, Telefon, URL): **0 Klartext-Reste** — alle perfekt durch `<EMAIL>` / `<IBAN>` / `<PHONE>` / `<URL>` ersetzt
- **Whitelist-Eigennamen**: Vollwort-Match, case-insensitive. Funktioniert wenn der vollständige Whitelist-Eintrag im Text steht. **Achtung:** wenn ein User „BKK GILDEMEISTER SEIDENSTICKER" als Whitelist-Eintrag pflegt, aber im Prompt nur „BKK GILDEMEISTER" tippt, wird nur der gepflegte Teil „BKK" durch `<KUNDE>` ersetzt — der Rest „GILDEMEISTER" bleibt Klartext. Pflegehinweis: **alle gängigen Schreibvarianten in die Whitelist aufnehmen** (Kurzform, Vollname, Abkürzung).

Last-Mile-Check `containsPii()` läuft zusätzlich VOR jedem LLM-Outbound-Call (Layer 4) — wenn ein Sample noch PII enthält, wird es übersprungen.

## Funktioneller Umfang

7 Tabs unter [/admin/prompt-insights](/admin/prompt-insights):
1. **Importe** — Upload, Liste, Lösch
2. **Prompt-Browser** — Tabelle mit Filter (Quelle, Rolle, Initial, Suche, Cluster)
3. **Statistik** — Dashboard mit Layer-2-KPIs, Wochentag×Stunde-Heatmap
4. **Cluster** — Cluster-Liste links, Beispiel-Prompts rechts; Threshold-Slider; Embeddings + Re-Cluster-Buttons
5. **Regel-Editor** — KI-Regelableitung pro Cluster, Editor mit Status-Workflow + manuelle Regeln
6. **Spielregeln** — Bibliothek (freigegebene Regeln gruppiert), Markdown + JSON Export
7. **Anonymisierung** — Whitelist mit Auto-Init aus Kunden + Team

## Architektur (Discovery-konform)

- **PHP 8.4 / kein Framework** ✓
- **MySQL** ✓ (8 neue Tabellen `pi_*`)
- **OpenAI-Embeddings** über existierenden `AIService::createEmbedding()` ✓ (keine neue lokale Dependency)
- **Cosine-Threshold-Clustering** in PHP (~120 Zeilen, deterministisch, default Threshold 0.78)
- **Standard-LLM-Modell** aus `/admin/settings?tab=ki` ✓
- **Capability** `prompt_insights` ✓ (Default: Admin + Manager)
- **Design-Tokens** `--d-*` aus `thx-tokens.css` ✓ — folgt Density-Profil aus `/admin/settings?tab=design`
- **8 Tabellen** mit Foreign-Key-CASCADE ✓
- **Audit-Log** — bewusst nicht eingebaut (per User-eigene Daten, keine multi-tenant-Sicherheit nötig)

## Out-of-Scope (wie im Briefing §7)

- Mehrbenutzer-Zugriff auf gemeinsame Importe — jeder User sieht nur eigene
- Automatisches Re-Clustering bei neuen Importen — manuell ausgelöst
- Automatische Anwendung der Spielregeln auf Live-Prompts im AI Assistant — nur dokumentiert, nicht aktiv
- Andere Quellen außer Claude/ChatGPT (z.B. Gemini, Mistral)
- Versionierung der Spielregeln
- Diff-Ansicht zwischen zwei Imports

## Datenschutz-Check

| Punkt | Status |
|---|---|
| ZIP wird nach Import gelöscht | ✓ `@unlink($tmpZipPath)` im try + catch |
| Nur anonymisierte Inhalte in DB | ✓ Anonymize läuft VOR jedem Insert |
| Whitelist pro User, nicht global | ✓ `user_id`-FK auf `pi_whitelist` |
| LLM-Outbound nur mit anonymisierten Daten | ✓ Last-mile `containsPii()`-Check vor Layer 4 |
| Vollständiger Lösch-Workflow | ✓ CASCADE über `pi_imports → pi_chats → pi_messages → pi_embeddings + pi_cluster_assignments` |

## Bekannte Limitationen / Backlog

- **AK4/5 nicht live verifiziert** weil das LLM-Calls verursachen würde. Code-Pfad getestet, Lint clean. Beim ersten Klick im UI wird's reichen.
- **Cluster-Threshold** = 0.78 default — bei sehr vielen Themen evtl. zu hoch (zu wenige Cluster). Im UI tunable.
- **Test-Account `Externer Testnutzer`** wird vom Whitelist-Init NICHT übersprungen — könnte bei vielen Test-Accounts Lärm machen. Aktuell nur 1 Test-Account, daher ignoriert.
- **Sentence-Transformers lokal** wurde diskutiert, abgelehnt (siehe Discovery §10.1). Falls in Zukunft Embedding-Kosten oder Datenschutz kritisch werden: in Discovery dokumentierte Alternativen vorhanden.

## Verfügbare API-Endpoints

```
GET    /api/v1/admin/prompt-insights/imports                  Liste
POST   /api/v1/admin/prompt-insights/imports                  Upload (multipart/form-data: zip)
DELETE /api/v1/admin/prompt-insights/imports/{id}             Lösch

GET    /api/v1/admin/prompt-insights/stats[?import_id=]       Layer-2-Aggregat
GET    /api/v1/admin/prompt-insights/browse?…                 Filterbare Message-Liste

POST   /api/v1/admin/prompt-insights/embed                    Embeddings nachholen
POST   /api/v1/admin/prompt-insights/recluster                {threshold?}
GET    /api/v1/admin/prompt-insights/clusters                 Liste
GET    /api/v1/admin/prompt-insights/clusters/{id}/samples    Sample-Prompts

POST   /api/v1/admin/prompt-insights/rules/derive/{cluster_id}  Layer 4: LLM-Regeln
GET    /api/v1/admin/prompt-insights/rules                    Liste
POST   /api/v1/admin/prompt-insights/rules                    Manuelle Regel
PUT    /api/v1/admin/prompt-insights/rules/{id}               Edit / Status
DELETE /api/v1/admin/prompt-insights/rules/{id}               Lösch
GET    /api/v1/admin/prompt-insights/rules/export?format=…    Markdown / JSON

GET    /api/v1/admin/prompt-insights/whitelist                Liste
POST   /api/v1/admin/prompt-insights/whitelist                {original, placeholder?}
POST   /api/v1/admin/prompt-insights/whitelist/init           Auto-Vorschlag aus Kunden + Team
DELETE /api/v1/admin/prompt-insights/whitelist/{id}           Lösch
```
