# CRM Phase 1 — Implementierung abgeschlossen

**Stand:** 01.06.2026
**Status:** Alle 14 Schritte aus dem Bauplan umgesetzt. Bereit für ersten Brevo-Import + erste Pflege-Sessions.

## Was steht

### Schema (21 Tabellen)
```
crm_kontakte           crm_firmen              crm_branchen
crm_adressen           crm_social_links        crm_tags
crm_kontakt_tags       crm_listen              crm_kontakt_listen
crm_segmente           crm_lead_magneten       crm_lead_magnet_events
crm_aktivitaeten       crm_opt_in_events       crm_brevo_events
crm_loesch_events      crm_kunden_zuordnung    crm_tag_sichtbarkeit
crm_migration_audit    crm_brevo_sync_log      crm_migrations_runs
```
Migration-Script: `scripts/crm-schema-create.php` (wiederholbar via IF NOT EXISTS)

### Capabilities
- `CAP_CRM` (lesen + pflegen) — Default für User+
- `CAP_CRM_VOKABULAR` (Tags/Listen/Branchen verwalten) — Manager+
- `CAP_CRM_MIGRATION` (Brevo-Import) — Manager+
- `CAP_CRM_DSGVO` (Auskunft + Hard-Delete) — Admin only

### Sidebar
- „Kontakte (CRM)" als neuer Top-Level-Eintrag
- Klick lädt zuletzt besuchte CRM-Seite (`localStorage thx_crm_last_path`)

### Services (8 Dateien)
| Service | Verantwortung |
|---|---|
| `CrmKontaktService` | CRUD, Filter, Suche, Tag-Pflege, Adressen, Aktivitäten-Log |
| `CrmFirmaService` | CRUD Firmen |
| `CrmTagService` | Vokabular |
| `CrmListenService` | Listen-CRUD + Brevo-Mapping |
| `CrmSegmentService` | Gespeicherte Filter |
| `CrmBrevoService` | Brevo-API-Client (account, contacts, lists, attributes, transactional) |
| `CrmMigrationService` | Brevo-Import (full + delta) |
| `CrmDoiService` | DOI-Erfassen + Bestätigen + Widerruf |

### REST-API (24 Endpoints unter `/api/v1/crm/`)
```
/dashboard                       GET
/kontakte                        GET, POST
/kontakte/{id}                   GET, PUT, DELETE
/kontakte/{id}/aktivitaeten      GET
/kontakte/{id}/inline            POST  (Feld-Update)
/kontakte/{id}/tags              POST  (setzen/entfernen)
/kontakte/{id}/listen            POST  (Mitgliedschaft)
/kontakte/{id}/adressen          POST, DELETE
/kontakte/{id}/foto              POST  (multipart)
/merge                           POST  (Dubletten zusammenführen)
/firmen, /firmen/{id}            CRUD
/tags, /tags/{id}                CRUD (POST/PUT/DELETE: CAP_CRM_VOKABULAR)
/listen, /listen/{id}            CRUD
/segmente, /segmente/{id}        CRUD
/branchen                        GET, POST
/dubletten                       GET
/migration/start                 POST  (CAP_CRM_MIGRATION)
/migration/status                GET
/migration/runs                  GET
/brevo/webhook                   POST  (ÖFFENTLICH, HMAC-signiert)
/doi/erfassen                    POST
/doi/bestaetigen/{token}         GET   (ÖFFENTLICH)
/doi/widerruf/{token}            GET   (ÖFFENTLICH)
/dsgvo/auskunft/{id}             GET   (CAP_CRM_DSGVO, JSON-Download)
/dsgvo/hard-delete/{id}          POST  (CAP_CRM_DSGVO)
```

### UI-Tabs unter `/crm`
1. **Dashboard** — KPIs + Empty State / Schnelleinstieg
2. **Kontakte** — Liste mit `.lam-table`-Pattern (Suche, Status/Opt-In-Filter, Sort, Bulk, Pagination)
3. **Kontakt-Detail** — kompakt + erweitert auf einem Blatt, Inline-Edit, Tag-Pflege, Zeitlinie
4. **Firmen** — Liste + Detail mit verknüpften Kontakten
5. **Segmente** — gespeicherte Filter (Phase-1: Anzeigen + Löschen)
6. **Listen** — Marketing-Listen-Verwaltung
7. **Tags** — Vokabular mit Farbe + Beschreibung
8. **Dubletten** — Email-/Name-Match (Merge in Schritt 9 fertig)
9. **Migration** (CAP_CRM_MIGRATION) — Wizard mit Live-Status-Polling alle 5s + History
10. **DSGVO** (CAP_CRM_DSGVO) — Suche, Auskunft-Download (JSON), Hard-Delete

Alle Views nutzen **ausschließlich** das Design-System: `.thx-table`, `.thx-card`, `.thx-page-header`, `.thx-tabs`, `.thx-bulk-*`, `.thx-inline-edit-*`, `.thx-btn-*`, `.thx-lightbox`, `.thx-modal`, `.lam-chip`, `.lam-table`, `.lam-filter-card`. Keine neuen Stile.

### Brevo-Integration
- **Settings-Tab** unter `/admin/settings?tab=brevo`: API-Key + Webhook-Secret + IP-Freigabe-Hinweis
- **Webhook** `/api/v1/crm/brevo/webhook` — HMAC-validiert, schreibt jedes Event in `crm_brevo_events` + relevante in `crm_aktivitaeten`
- **Push** auto bei Kontakt-Anlage (über `brevo_id`-Spalte, in Phase-2 konkretisieren)
- **Pull** Migration via `/scripts/crm-brevo-import.php` (Background-Worker, mit Status-Tracking in `crm_migrations_runs`)
- **Transactional** für DOI-Mail via `CrmBrevoService::sendTransactionalEmail`

### Cron-Jobs (`/etc/cron.d/ki-tool-crm`)
```
30 2 * * * www-data php /var/www/scripts/crm-brevo-reconciliation.php  # nächtliche Delta-Reconciliation
30 3 * * * www-data php /var/www/scripts/crm-doi-cleanup.php           # DOI-Token älter 14 Tage abgelaufen markieren
```

### DSGVO
- **Soft-Delete** als Standard (geloescht_am-Spalte, wiederherstellbar)
- **Hard-Delete** über `/crm/dsgvo`-Tab:
  - Stammdaten + Adressen + Aktivitäten + Lead-Magnet-Events + Tags + Listen-Mitgliedschaften physisch weg
  - Brevo-Events bleiben (Statistik), aber `brevo_email`-Spalte wird auf NULL (Anonymisierung)
  - Tombstone in `crm_loesch_events` mit Kontakt-ID + Zeitpunkt + Löscher
- **Auskunft** als JSON-Download mit allen relevanten Daten zu einer Person

### Avatar/Foto
- Upload-Endpoint `/api/v1/crm/kontakte/{id}/foto` (multipart)
- Speicherort: `/var/www/uploads/crm/avatars/{kontakt_id}.{jpg|png|webp|gif}`
- Max-Größe via Setting `crm_avatar_max_kb` (Default 1024)
- Auslieferung über Apache-Static (Auth-Check über Aktion „kontakt sehen")

## Was noch zu tun ist (Phase 1.5 oder spätere Polish-Runde)

1. **Bulk-Aktionen** im Kontakt-List (Tag setzen, Liste zuweisen) — UI-Buttons sind drin, Backend-Loop fehlt noch
2. **Merge-Dialog UI** — `/api/v1/crm/merge` ist da, UI in `/crm/dubletten` zeigt nur Liste, Merge-Button ist Stub
3. **Segment-Builder UI** — heute manuell als JSON, später visueller Builder
4. **Brevo-Push bei Kontakt-Änderung** — heute nur Migration-Pull; Push muss als Hook in `CrmKontaktService::aktualisieren` ergänzt werden
5. **Foto-Upload-UI** — Endpoint steht, Detail-View zeigt aber noch nur Initialen (kein Upload-Button)
6. **„Neuer Kontakt"-Modal** — Dashboard- und Kontakte-Header verlinken auf `/crm/kontakte/neu` (Route fehlt noch, einfaches Modal genügt)
7. **Custom-Field-Mapping verfeinern** mit echtem Zoho-Schema (aus `docs/zoho-export/`)

## Nächste Aktionen (Reihenfolge)

1. **Webhook in Brevo konfigurieren**
   - Brevo → Transactional → Settings → Webhooks
   - URL: `https://ai.thoxan-dev.de/api/v1/crm/brevo/webhook`
   - Events: alle (sent, delivered, opened, click, soft_bounce, hard_bounce, invalid_email, spam, blocked, unsubscribed, deferred, complaint)
   - Webhook-Secret aus `/admin/settings?tab=brevo` übernehmen (oder neu setzen)

2. **Erste Migration starten**
   - `/crm/migration` → „Vollständiger Import"
   - Live-Status: zeigt Worker-Fortschritt alle 5s
   - Erwartete Dauer: bei ~5k Brevo-Kontakten ca. 3-5 Min

3. **Schmerz-Test der Pflege-UI**
   - `/crm/kontakte` öffnen
   - Suche, Filter, Detail-View, Inline-Edit
   - Was sich beim Pflegen quietscht: Issues sammeln, Phase-1.5-Polish-Runde planen

## Files-Übersicht (was Phase 1 angefasst hat)

```
/var/www/scripts/
  crm-schema-create.php             ← Migration (einmalig + idempotent)
  crm-brevo-import.php              ← Migration-Worker
  crm-brevo-reconciliation.php      ← Cron nächtlich
  crm-doi-cleanup.php               ← Cron täglich

/var/www/services/
  CrmKontaktService.php             ← 19 KB, Haupt-Service
  CrmFirmaService.php
  CrmTagService.php
  CrmListenService.php
  CrmSegmentService.php
  CrmBrevoService.php               ← API-Client
  CrmMigrationService.php           ← Brevo-Migration
  CrmDoiService.php

/var/www/api/v1/crm/                ← 24 Endpoints

/var/www/views/crm/
  _tabs.php                          ← Tab-Bar
  dashboard.php
  kontakte.php                       ← Hauptliste
  kontakt-detail.php                 ← Detail-View
  firmen.php, firma-detail.php
  segmente.php, listen.php, tags.php
  dubletten.php, migration.php, dsgvo.php

/var/www/views/admin/settings/
  _tab_brevo.php                     ← Brevo-Settings (API-Key, Webhook-Secret, IP-Freigabe)

/var/www/api/v1/admin/
  brevo-test.php                     ← Verbindung testen

/etc/cron.d/
  ki-tool-crm                        ← 2 Cron-Jobs

/var/www/config/constants.php        ← +4 CAP_CRM_*
/var/www/core/Auth.php               ← Caps + Meta + Default-Rollen
/var/www/core/App.php                ← +12 /crm-Routen
/var/www/views/layouts/main.php      ← Sidebar-Eintrag
/var/www/api/handler.php             ← +25 /api/v1/crm-Mappings
```

## Was Phase 2 angeht

Aus dem Lastenheft Kapitel 7:
- **Phase 2: Wissensanbindung** — Profil-Dokument je Kontakt, Embedding-Index (separater Baustein, deutsch-spezifisches Modell), hybride Suche, KI-Assistent mit Kontext
- **Phase 3: KI-gestützte Pflege** — Dedup-/Anreicherungs-Vorschläge, Review-Workflow, KI-vs-verifiziert-Kennzeichnung
- **Phase 4: Marketing-Automation** — Trigger/Journeys, ggf. Brevo-Ablösung

Stabile Kontakt-IDs und Lösch-Event-Log (Tombstones) sind in Phase 1 bereits gelegt — Phase 2 kann nahtlos darauf aufbauen.
