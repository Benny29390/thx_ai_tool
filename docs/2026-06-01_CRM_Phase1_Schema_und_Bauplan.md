# CRM Phase 1 — Schema und Bauplan

**Stand:** 01.06.2026
**Basis:** [Lastenheft Eigenes CRM](2026-06-01_THX_Lastenheft_Eigenes_CRM_AI-Platform.md)
**Status:** Konzept zur Abstimmung vor Code-Start

Dieses Dokument fasst alle in den Klärungsrunden getroffenen Entscheidungen zusammen und legt das konkrete Phase-1-Bauwerk fest: DB-Schema, Services, API-Endpunkte, UI-Map, Cron-Jobs.

---

## 1. Getroffene Entscheidungen (Zusammenfassung)

| Thema | Entscheidung |
|---|---|
| Relationale Basis | MySQL der bestehenden AI-Platform (kein neuer Stack) |
| Tabellen-Prefix | `crm_*` (konsistent mit `lam_*`, `pp_*`, `mail_*`) |
| Sidebar-Label | „Kontakte (CRM)" als eigener Top-Level-Eintrag |
| Quelle der Migration | Nur Brevo (Zoho-Stand ist veraltet, wird nur als Struktur-Vorlage genutzt) |
| Brevo-Listen-Mapping | 1:1 alle übernehmen, Aufräumen später im CRM |
| Brevo-Sync | Realtime via Webhooks + nächtlicher Reconciliation-Cron |
| Brevo-Events | Alle Events (Open/Click/Bounce/Unsubscribe) als Zeitlinien-Einträge |
| DOI-Flow | Hybrid: CRM erfasst → Brevo schickt → Webhook zurück → CRM speichert Beleg |
| Phase-1 DOI | **komplett** in Phase 1 |
| Webformulare | Brevo bleibt Empfänger, CRM zieht via Webhook + Sync (kein eigener öffentlicher Lead-Endpunkt in Phase 1) |
| Foto/Avatar | Upload + Anzeige in Phase 1 |
| Opportunities | Minimal am Kontakt: Asana-Task-GID + Deal-Wert + Pipeline-Stufe (keine Bearbeitung im CRM) |
| Löschen | Soft als Default + manueller Hard-Delete-Button für DSGVO |
| Rollenmodell | Hybrid: Kunden-Zuordnung als Basis + Tag-Whitelisting für Cross-Customer-Sichtbarkeit |
| Firma am Kontakt | Optional (Privatpersonen erlaubt) |
| Branche | Hybrid: kuratiertes Vokabular + freie Erweiterung im Feld |
| Tags | Kontrolliertes Vokabular, neue nur über bewusste „Neu"-Aktion |
| Erwartetes Volumen | < 5.000 Kontakte → keine speziellen Performance-Maßnahmen nötig |

---

## 2. Datenmodell

### 2.1 Übersicht (Entitäten + Beziehungen)

```
crm_kontakte ──┬── crm_kontakt_tags ──── crm_tags
               ├── crm_kontakt_listen ── crm_listen
               ├── crm_adressen (n pro Kontakt)
               ├── crm_lead_magnet_events ── crm_lead_magneten
               ├── crm_aktivitaeten (Zeitlinie, append-only)
               ├── crm_opt_in_events (DOI-Belege)
               ├── crm_brevo_events (alle Brevo-Events)
               └── crm_kunden_zuordnung ── customers (Rechte)

crm_firmen ────┴── crm_branchen (Vokabular)

crm_segmente (gespeicherte Filter)
crm_tag_sichtbarkeit (welche Tags geben CRM-Lese-Zugriff)
crm_loesch_events (Tombstones für späteren Embedding-Sync)
crm_migration_audit (Import-Protokoll)
crm_brevo_sync_log (Audit der CRM-→Brevo-Pushes)
```

### 2.2 Tabellen-Details

#### `crm_kontakte` — Hauptentität

```sql
CREATE TABLE crm_kontakte (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Identität
    anrede VARCHAR(20) NULL,
    titel VARCHAR(40) NULL,
    vorname VARCHAR(120) NULL,
    nachname VARCHAR(160) NOT NULL,
    -- berechnetes Feld 'kontakt_name' bauen wir als VIEW oder in PHP
    funktion VARCHAR(200) NULL,
    abteilung VARCHAR(120) NULL,
    geburtsdatum DATE NULL,
    -- Kommunikation
    email_primaer VARCHAR(255) NOT NULL,
    email_zweit   VARCHAR(255) NULL,
    telefon VARCHAR(80) NULL,
    telefon_alt VARCHAR(80) NULL,
    mobil VARCHAR(80) NULL,
    fax VARCHAR(80) NULL,
    website VARCHAR(255) NULL,
    -- Firma
    firma_id BIGINT UNSIGNED NULL, -- optional, Privatpersonen erlaubt
    -- Profil
    interessen TEXT NULL,
    merkmale TEXT NULL,
    beschreibung TEXT NULL,
    bevorzugtes_thema VARCHAR(255) NULL,
    -- Marketing-Status
    kontakt_status ENUM('lead','interessent','kunde','ehemaliger_kunde','partner','sonstiges') NULL,
    lead_quelle VARCHAR(120) NULL,
    opt_in_status ENUM('pending','double_opted_in','single_opted_in','unsubscribed','hard_bounce','invalid') NULL,
    thx_score INT NULL,
    -- Opportunity (minimal, Phase 1)
    asana_task_gid VARCHAR(40) NULL,
    deal_wert DECIMAL(10,2) NULL,
    deal_stufe VARCHAR(80) NULL,
    -- Avatar
    foto_path VARCHAR(255) NULL,
    -- Owner
    kontakt_besitzer_user_id INT NULL,
    -- Sync / Brevo
    brevo_id VARCHAR(40) NULL,
    brevo_zuletzt_gepusht_am DATETIME NULL,
    -- System
    erstellt_durch INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geaendert_durch INT NULL,
    geaendert_am DATETIME ON UPDATE CURRENT_TIMESTAMP,
    -- Soft-Delete
    geloescht_am DATETIME NULL,
    geloescht_durch INT NULL,
    -- Indizes
    UNIQUE KEY uniq_email (email_primaer),
    KEY idx_firma (firma_id),
    KEY idx_status (kontakt_status),
    KEY idx_opt_in (opt_in_status),
    KEY idx_brevo (brevo_id),
    KEY idx_geloescht (geloescht_am),
    FULLTEXT KEY ft_search (vorname, nachname, email_primaer, funktion, beschreibung)
);
```

**Hinweise:**
- `UNIQUE(email_primaer)` ist hart. Bei Migrations-Konflikt → Dedup-Dialog vor Insert.
- `FULLTEXT`-Index für Suche über mehrere Felder (Lastenheft fordert kompakte Pflege-UI).
- `geloescht_am IS NULL` ist der Standard-Filter für „aktive Kontakte".

#### `crm_firmen`

```sql
CREATE TABLE crm_firmen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firmenname VARCHAR(255) NOT NULL,
    website VARCHAR(255) NULL,
    branche VARCHAR(120) NULL, -- aus crm_branchen, aber frei erweiterbar
    geschaeftsadresse_id BIGINT UNSIGNED NULL,
    notizen TEXT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geaendert_am DATETIME ON UPDATE CURRENT_TIMESTAMP,
    geloescht_am DATETIME NULL,
    KEY idx_name (firmenname),
    KEY idx_branche (branche)
);
```

#### `crm_branchen` (kuratiertes Vokabular, hybrid)

```sql
CREATE TABLE crm_branchen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    anzahl_firmen INT UNSIGNED DEFAULT 0, -- Counter für UI-Häufigkeits-Sortierung
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
);
```
Pre-Seed mit den häufigsten Branchen aus dem Zoho-Export.

#### `crm_tags` (kontrolliertes Vokabular)

```sql
CREATE TABLE crm_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(80) NOT NULL UNIQUE,
    farbe VARCHAR(7) NULL, -- optionaler Hex für Chip-Darstellung
    beschreibung VARCHAR(255) NULL,
    erstellt_durch INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE crm_kontakt_tags (
    kontakt_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    vergeben_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    vergeben_durch INT NULL,
    PRIMARY KEY (kontakt_id, tag_id),
    KEY idx_tag (tag_id),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES crm_tags(id) ON DELETE CASCADE
);
```

#### `crm_listen` (Marketing-Listen, 1:1 aus Brevo)

```sql
CREATE TABLE crm_listen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    brevo_list_id INT NULL UNIQUE, -- Mapping zur Brevo-Liste
    beschreibung TEXT NULL,
    archiviert TINYINT(1) DEFAULT 0,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE crm_kontakt_listen (
    kontakt_id BIGINT UNSIGNED NOT NULL,
    listen_id INT UNSIGNED NOT NULL,
    status ENUM('aktiv','inaktiv','pending','unsubscribed') DEFAULT 'aktiv',
    beigetreten_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    verlassen_am DATETIME NULL,
    PRIMARY KEY (kontakt_id, listen_id),
    KEY idx_listen (listen_id, status),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    FOREIGN KEY (listen_id) REFERENCES crm_listen(id) ON DELETE CASCADE
);
```

#### `crm_segmente` (gespeicherte dynamische Filter)

```sql
CREATE TABLE crm_segmente (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    beschreibung TEXT NULL,
    filter_json JSON NOT NULL, -- strukturierter Filter (Feld + Operator + Wert, UND/ODER)
    erstellt_durch INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geaendert_am DATETIME ON UPDATE CURRENT_TIMESTAMP,
    sichtbarkeit ENUM('privat','team','global') DEFAULT 'privat',
    KEY idx_sichtbarkeit (sichtbarkeit)
);
```

#### `crm_adressen`

```sql
CREATE TABLE crm_adressen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NULL,
    firma_id BIGINT UNSIGNED NULL, -- entweder kontakt_id ODER firma_id gesetzt
    typ ENUM('geschaeftlich','privat','sonstige') NOT NULL,
    ist_primaer TINYINT(1) DEFAULT 0,
    strasse VARCHAR(255) NULL,
    plz VARCHAR(20) NULL,
    stadt VARCHAR(120) NULL,
    bundesland VARCHAR(120) NULL,
    land VARCHAR(80) NULL DEFAULT 'Deutschland',
    KEY idx_kontakt (kontakt_id),
    KEY idx_firma (firma_id),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    FOREIGN KEY (firma_id) REFERENCES crm_firmen(id) ON DELETE CASCADE
);
```

#### `crm_social_links` (Key-Value-Liste)

```sql
CREATE TABLE crm_social_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    plattform ENUM('linkedin','xing','facebook','instagram','twitter_x','youtube','sonstiges') NOT NULL,
    url VARCHAR(500) NOT NULL,
    UNIQUE KEY uniq_kontakt_plattform (kontakt_id, plattform),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE
);
```

#### `crm_lead_magneten` + `crm_lead_magnet_events`

```sql
CREATE TABLE crm_lead_magneten (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    beschreibung TEXT NULL,
    aktiv TINYINT(1) DEFAULT 1,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE crm_lead_magnet_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    lead_magnet_id INT UNSIGNED NOT NULL,
    -- UTM-Daten gehören ans Event, nicht an die Person (Lastenheft)
    utm_source VARCHAR(120) NULL,
    utm_medium VARCHAR(120) NULL,
    utm_campaign VARCHAR(120) NULL,
    utm_content VARCHAR(120) NULL,
    utm_term VARCHAR(120) NULL,
    referrer VARCHAR(500) NULL,
    eingegangen_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt (kontakt_id),
    KEY idx_lead_magnet (lead_magnet_id),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_magnet_id) REFERENCES crm_lead_magneten(id)
);
```

#### `crm_aktivitaeten` (Zeitlinie, append-only)

```sql
CREATE TABLE crm_aktivitaeten (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    typ ENUM(
        'kontakt_angelegt','kontakt_geaendert','tag_hinzugefuegt','tag_entfernt',
        'liste_beigetreten','liste_verlassen','opt_in_erfasst','doi_bestaetigt',
        'lead_magnet','mail_open','mail_click','mail_bounce','mail_unsubscribe',
        'notiz','telefonat','meeting','sonstiges'
    ) NOT NULL,
    titel VARCHAR(255) NULL,
    inhalt TEXT NULL,
    metadata_json JSON NULL,
    -- Herkunft: Mensch oder KI (Lastenheft DSGVO-Kapitel)
    quelle ENUM('manuell','migration','brevo_webhook','brevo_sync','system','ki_vorschlag','ki_uebernommen') DEFAULT 'manuell',
    actor_user_id INT NULL,
    erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt_zeit (kontakt_id, erstellt_am DESC),
    KEY idx_typ (typ),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE
);
```

#### `crm_opt_in_events` (DOI-Belege)

```sql
CREATE TABLE crm_opt_in_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NOT NULL,
    typ ENUM('erfasst','doi_mail_gesendet','doi_bestaetigt','unsubscribe','revoke') NOT NULL,
    doi_token VARCHAR(64) NULL, -- für Bestätigungs-Link (NULL bei Brevo-DOI)
    quelle VARCHAR(120) NULL, -- Webformular-URL, Brevo-Liste, manuell, etc.
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    text_einwilligung TEXT NULL, -- der angeklickte Einwilligungs-Text (revisionssicher)
    erfolgt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt (kontakt_id),
    KEY idx_token (doi_token),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE
);
```

#### `crm_brevo_events` (alle Brevo-Events als Zeilen)

```sql
CREATE TABLE crm_brevo_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id BIGINT UNSIGNED NULL, -- NULL wenn Kontakt unbekannt, aufzulösen
    brevo_email VARCHAR(255) NULL, -- für nachträgliches Matchen; bei DSGVO-Hard-Delete auf NULL gesetzt (Anonymisierung)
    event_typ ENUM(
        'sent','delivered','open','click','soft_bounce','hard_bounce',
        'invalid','spam','blocked','unsubscribed','deferred','complaint'
    ) NOT NULL,
    campaign_id INT NULL,
    campaign_name VARCHAR(200) NULL,
    link_url TEXT NULL, -- bei click
    user_agent VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    raw_json JSON NULL,
    empfangen_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt_zeit (kontakt_id, empfangen_am DESC),
    KEY idx_email (brevo_email),
    KEY idx_typ (event_typ),
    KEY idx_campaign (campaign_id)
);
```
Bei jedem Brevo-Event wird zusätzlich ein `crm_aktivitaeten`-Eintrag erzeugt (zur einheitlichen Zeitlinien-Sicht).

#### `crm_loesch_events` (Tombstones für Embedding-Sync, Phase 2)

```sql
CREATE TABLE crm_loesch_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_typ ENUM('kontakt','firma') NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    geloescht_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    geloescht_durch INT NULL,
    art ENUM('soft','hard','dsgvo_anonymisiert') NOT NULL,
    grund VARCHAR(255) NULL,
    KEY idx_entity (entity_typ, entity_id),
    KEY idx_zeit (geloescht_am)
);
```
Wird auch bei Soft-Delete geschrieben, damit der spätere Embedding-Index informiert ist.

#### `crm_kunden_zuordnung` (Kontakt → Thoxan-Kunde, für Rechte)

```sql
CREATE TABLE crm_kunden_zuordnung (
    kontakt_id BIGINT UNSIGNED NOT NULL,
    customer_id INT NOT NULL,
    rolle VARCHAR(80) NULL, -- z.B. 'ansprechpartner','entscheider','dienstleister'
    PRIMARY KEY (kontakt_id, customer_id),
    KEY idx_customer (customer_id),
    FOREIGN KEY (kontakt_id) REFERENCES crm_kontakte(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

#### `crm_tag_sichtbarkeit` (Tag-Whitelisting für Cross-Customer-Zugriff)

```sql
CREATE TABLE crm_tag_sichtbarkeit (
    tag_id INT UNSIGNED NOT NULL PRIMARY KEY,
    fuer_alle_crm_user TINYINT(1) DEFAULT 0, -- wenn 1: jeder mit CAP_CRM darf Kontakte mit diesem Tag sehen
    beschreibung VARCHAR(255) NULL,
    FOREIGN KEY (tag_id) REFERENCES crm_tags(id) ON DELETE CASCADE
);
```

#### `crm_migration_audit` + `crm_brevo_sync_log`

```sql
CREATE TABLE crm_migration_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quelle ENUM('brevo','zoho','manuell') NOT NULL,
    quelle_id VARCHAR(80) NULL, -- z.B. Brevo-Contact-ID
    aktion ENUM('insert','update','merge','skip','error') NOT NULL,
    kontakt_id BIGINT UNSIGNED NULL,
    details_json JSON NULL,
    erfolgt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_quelle (quelle, quelle_id),
    KEY idx_kontakt (kontakt_id)
);

CREATE TABLE crm_brevo_sync_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    richtung ENUM('crm_to_brevo','brevo_to_crm') NOT NULL,
    kontakt_id BIGINT UNSIGNED NULL,
    aktion VARCHAR(80) NOT NULL, -- z.B. 'contact.upsert', 'list.add', 'webhook.event'
    status ENUM('ok','retry','error') NOT NULL,
    fehler_text TEXT NULL,
    request_json JSON NULL,
    response_json JSON NULL,
    erfolgt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kontakt_zeit (kontakt_id, erfolgt_am DESC),
    KEY idx_status (status)
);
```

---

## 3. Service-Schicht (PHP)

| Service | Verantwortung |
|---|---|
| `CrmKontaktService` | CRUD, Suche, Filter-Auflösung, Tag-Pflege, Adressen-Pflege, Aktivitäten-Log |
| `CrmFirmaService` | CRUD Firmen, Verknüpfung Kontakte |
| `CrmDedupService` | Match-Logik (E-Mail/Mobil/Name+Firma), Merge-Operationen |
| `CrmTagService` | Vokabular-Pflege, Autocomplete, Tag-Sichtbarkeits-Whitelist |
| `CrmListenService` | Listen-CRUD, Brevo-Mapping |
| `CrmSegmentService` | Filter-JSON parsen + zu SQL kompilieren, ausführen |
| `CrmBrevoApiService` | Brevo-Client (HTTP-Wrapper für /v3/contacts, /v3/lists, etc.) |
| `CrmBrevoWebhookService` | Webhook-Empfang, Signatur-Validierung, Event-Verarbeitung |
| `CrmBrevoSyncService` | Push CRM → Brevo, Pull Brevo → CRM, Reconciliation-Cron |
| `CrmDoiService` | DOI-Token erzeugen, Mail-Versand triggern, Bestätigung verarbeiten |
| `CrmMigrationService` | Brevo-Import mit Audit, Dedup-Resolution |
| `CrmDsgvoService` | Auskunfts-Export, Hard-Delete-Workflow, Tombstone-Pflege |
| `CrmAktivitaetService` | Zentrale Methode zum Loggen aller Änderungen + Brevo-Events |

---

## 4. API-Endpunkte

### Pflege
- `GET    /api/v1/crm/kontakte` — Liste mit Filter + Pagination
- `GET    /api/v1/crm/kontakte/{id}` — Detail
- `POST   /api/v1/crm/kontakte` — Anlegen
- `PUT    /api/v1/crm/kontakte/{id}` — Update
- `DELETE /api/v1/crm/kontakte/{id}` — Soft-Delete
- `POST   /api/v1/crm/kontakte/{id}/inline` — Inline-Edit eines Feldes
- `POST   /api/v1/crm/kontakte/{id}/foto` — Avatar-Upload
- `GET    /api/v1/crm/kontakte/{id}/aktivitaeten` — Zeitlinie
- `POST   /api/v1/crm/kontakte/{id}/aktivitaet` — Notiz/Telefonat etc. manuell hinzufügen
- `POST   /api/v1/crm/kontakte/{id}/tags` — Tag setzen/entfernen
- `POST   /api/v1/crm/kontakte/{id}/listen` — Liste hinzufügen/entfernen
- `POST   /api/v1/crm/kontakte/merge` — zwei Kontakte zusammenführen
- `GET    /api/v1/crm/kontakte/dedup-kandidaten` — vermutete Dubletten

### Firmen, Tags, Listen, Segmente, Branchen
- analog zu Kontakte (GET/POST/PUT/DELETE)

### Brevo
- `POST   /api/v1/crm/brevo/webhook` — **öffentlich**, signaturbasiert (HMAC)
- `POST   /api/v1/crm/brevo/sync-now` — manueller Pull (Admin)
- `GET    /api/v1/crm/brevo/sync-log` — Audit

### DOI
- `POST   /api/v1/crm/doi/erfassen` — vom Webformular oder manuell
- `GET    /api/v1/crm/doi/bestaetigen/{token}` — **öffentlich**, vom DOI-Link in der Mail
- `GET    /api/v1/crm/doi/widerruf/{token}` — **öffentlich**, Abmelde-Link

### Migration
- `POST   /api/v1/crm/migration/brevo-import` — startet Import (Admin)
- `GET    /api/v1/crm/migration/status` — Fortschritt
- `GET    /api/v1/crm/migration/audit` — Audit-Log

### DSGVO
- `GET    /api/v1/crm/dsgvo/auskunft/{kontakt_id}` — vollständiger Datenexport pro Person
- `POST   /api/v1/crm/dsgvo/hard-delete/{kontakt_id}` — physisches Löschen mit Tombstone

---

## 5. Capabilities (Auth)

Neue Caps im bestehenden `core/Auth`-System:

- `CAP_CRM` — CRM lesen + Kontakte pflegen
- `CAP_CRM_DSGVO` — Auskünfte erzeugen + Hard-Deletes (Admin-only standardmäßig)
- `CAP_CRM_MIGRATION` — Migration starten + Audit sehen (Admin-only)
- `CAP_CRM_VOKABULAR` — Tags/Branchen/Listen verwalten (Admin/Manager)

**Rechte-Logik bei Kontakt-Anzeige (Hybrid-Modell):**
```
darf_sehen($kontakt, $user) :=
    Auth::isAdmin($user)
    OR Auth::isManager($user)
    OR (
        Auth::can($user, CAP_CRM)
        AND (
            $kontakt.kontakt_besitzer_user_id == $user.id
            OR ANY($kontakt.kunden_zuordnung.customer_id IN Auth::customers($user))
            OR ANY($kontakt.tags.tag_id IN crm_tag_sichtbarkeit WHERE fuer_alle_crm_user=1)
        )
    )
```

---

## 6. UI-Map

Sidebar: **„Kontakte (CRM)"** als neuer Top-Level-Eintrag (Icon: people/account_box).

Tab-Hub `/crm` mit folgenden Tabs:

| Tab | URL | Funktion |
|---|---|---|
| Kontakte | `/crm/kontakte` | Liste mit Quick-Filter-Chips, Segment-Auswahl, Suche, Bulk-Aktionen |
| Kontakt-Detail | `/crm/kontakte/{id}` | Kompakt + erweiterte Ansicht, Inline-Edit, Zeitlinie |
| Firmen | `/crm/firmen` | Liste + Detail |
| Segmente | `/crm/segmente` | Gespeicherte Filter, anlegen/bearbeiten |
| Listen | `/crm/listen` | Listen-Verwaltung + Brevo-Sync-Status |
| Tags | `/crm/tags` | Vokabular-Pflege + Sichtbarkeits-Whitelist |
| Branchen | `/crm/branchen` | Vokabular |
| Lead-Magneten | `/crm/lead-magneten` | Katalog |
| Dubletten | `/crm/dubletten` | Dedup-Übersicht + Merge-Dialog |
| Brevo-Status | `/crm/brevo` | Sync-Log + Manueller Pull + Webhook-Status |
| Migration | `/crm/migration` | Brevo-Import-Wizard |
| DSGVO | `/crm/dsgvo` | Auskunfts-/Löschwerkzeug |

**Kontakt-Detailseite** (Kern-Wunsch, alles auf einem Blatt):
- Header-Kachel: Foto + Name + Firma + Status-Badge + THX-Score + Mail/Tel als klickbare Links
- Tag-Chips inline editierbar
- Tabs ODER aufklappbare Sektionen für: Identität · Kommunikation · Adressen · Social · Marketing-Status · Listen · Lead-Magnet-Events · Aktivitäten/Zeitlinie · Migration-Audit
- Zweite Adresse/selten genutzte Felder aufklappbar

**Pattern wiederverwenden:**
- Inline-Edit (`thx-inline-edit-*` Klassen, schon im LAM eingesetzt)
- Lightboxen (`thx-lightbox`-Klasse, schon vorhanden)
- Sidebar-Logik (Sidebar-Pattern wie LAM-System mit `localStorage thx_crm_last_path`)

---

## 7. Cron-Jobs

| Cron | Frequenz | Zweck |
|---|---|---|
| `/var/www/scripts/crm-brevo-reconciliation.php` | nachts 02:30 | Brevo-Pull aller seit letztem Sync geänderten Kontakte/Events (Webhook-Fallback) |
| `/var/www/scripts/crm-brevo-push-queue.php` | alle 5 Min | Sendet anstehende Kontakt-Änderungen an Brevo (out-Queue) |
| `/var/www/scripts/crm-doi-cleanup.php` | täglich | DOI-Tokens älter als 14 Tage ohne Bestätigung als „abgelaufen" markieren |

---

## 8. Settings

Neue Settings (verschlüsselt wo nötig):

- `brevo_api_key` (encrypted)
- `brevo_webhook_secret` (encrypted) — für HMAC-Validierung
- `crm_doi_text_default` (HTML-Vorlage für die Einwilligungs-Mail)
- `crm_doi_aufbewahrung_tage` (default 14)
- `crm_avatar_max_kb` (default 1024)

---

## 9. Phasen-Schnitt (was ist Phase 1, was kommt später)

**Phase 1 — was wir jetzt bauen:**

✓ Schema (alle Tabellen oben)
✓ Migration aus Brevo (Kontakte, Listen, Custom-Fields-Bestmögliche-Übersetzung)
✓ Kontakt-Pflege-UI (kompakt + erweitert, Inline-Edit, Klickbarkeit)
✓ Tag-Pflege mit kontrolliertem Vokabular
✓ Filter + Segmente speichern
✓ Dubletten-Übersicht + Merge-Dialog
✓ Aktivitäten/Zeitlinie
✓ Brevo-Webhook-Endpoint + alle Events in `crm_brevo_events`
✓ Brevo-Push (Minimaldatensatz) bei Kontakt-Änderung
✓ DOI-Flow Hybrid (CRM erfasst → Brevo schickt → Webhook zurück)
✓ Foto-Upload + Anzeige
✓ Opportunity-Felder am Kontakt (Asana-Task-GID + Wert + Stufe als read/edit)
✓ Rollenmodell Hybrid (Kunden-Zuordnung + Tag-Whitelisting)
✓ DSGVO-Grundlagen: Auskunfts-Export, Soft-Delete + manueller Hard-Delete, Lösch-Event-Log
✓ Audit-Log (über bestehenden `Core\AuditLog` + Aktivitäten-Tabelle)
✓ Capabilities-System integriert
✓ Sidebar-Eintrag „Kontakte (CRM)"

**Was bleibt für Phase 2 (Wissensanbindung):**

- Profil-Dokument je Kontakt + Embedding-Generierung
- Embedding-Store (separater Baustein, wird mit der jetzt anstehenden Cloud-/Local-Model-Entscheidung verknüpft)
- Hybride Suche
- KI-Assistent liest Kontakt-Kontext

**Was bleibt für Phase 3 (KI-Pflege):**

- Dedup-Vorschläge per KI
- Anreicherungs-Vorschläge mit Review-Workflow
- KI-vs-verifiziert-Kennzeichnung durchgängig

**Was bleibt für Phase 4 (Marketing-Automation):**

- Eigene Trigger/Journeys
- Ggf. Ablösung von Brevo-Automationen
- Eigener Versand vs Brevo separat entscheiden

---

## 10. Offene technische Restpunkte — alle geklärt (Stand 01.06.2026)

1. **Brevo-API-Key + Webhook-Secret** ✅
   - API-Key gesetzt (verschlüsselt in `settings.brevo_api_key`), Connection getestet
   - Webhook-Secret kommt beim konkreten Webhook-Setup (Schritt 7), frei wählbar

2. **Brevo-Account-Plan** ✅
   - Subscription · 19.994 Credits · Transaktionsmail aktiv → reicht für Phase 1

3. **Webhook-URL** ✅
   - `https://ai.thoxan-dev.de/api/v1/crm/brevo/webhook` — Setup bei Schritt 7
   - Server-IPs für Brevo-Allowlist: IPv4 `46.225.85.168`, IPv6 `2a01:4f8:1c19:9f7f::1`

4. **DOI-Mail-Versand** ✅
   - **Brevo Transactional API** (Transaktionsmail ist im Brevo-Account aktiv)
   - Konsistentes Tracking, Bounce-Handling über denselben Webhook

5. **Avatar-Speicherort** ✅
   - `/var/www/uploads/crm/avatars/{kontakt_id}.{ext}`
   - Permission `www-data:www-data`
   - Auslieferung über authentifizierten Endpunkt (kein direkter Apache-Alias)

6. **Hard-Delete-Tiefe (DSGVO)** ✅
   - **Stammdaten + Adressen physisch weg** (Kontakt, Adressen, Lead-Magnet-Events, Aktivitäten)
   - **Brevo-Events bleiben** für Statistik, aber `brevo_email`-Spalte wird auf `NULL` gesetzt (anonymisiert)
   - Tombstone bleibt mit Kontakt-ID + Zeitpunkt + Löscher
   - → DDL-Anpassung: `crm_brevo_events.brevo_email` muss `NULLABLE` sein (ist schon `VARCHAR(255) NOT NULL` → ändern auf `NULL`)

7. **Migration aus Brevo** ✅
   - **Wiederholbar mit „nur Neue/Geänderte"-Modus**
   - Button nur für Admin/Manager (Cap `CAP_CRM_MIGRATION`)
   - UI zeigt „letzter Lauf vor X Tagen" als Default
   - Initial-Import + Delta-Imports möglich
   - Tracking via `crm_migration_audit` (welcher Brevo-Contact-ID wann importiert/aktualisiert)

---

## 11. Empfohlener Build-Reihenfolge-Plan

1. **Schema-Migration** (alle Tabellen oben) → CLI-Script + Auto-Apply in `core/App.php`
2. **Auth-Caps** + Sidebar-Eintrag (UI sichtbar mit Empty State)
3. **`CrmKontaktService` + `CrmFirmaService` + REST-Endpunkte** (CRUD)
4. **Kontakt-Liste + Detail-UI** (mit Mock-Daten erstmal, dann live)
5. **Tag/Listen/Segment-Vokabular + UI**
6. **Brevo-Client + Migration-Wizard** (Test mit Sandbox-Account, dann echter Import)
7. **Brevo-Webhook + Event-Stream**
8. **DOI-Flow** (Token, Mail-Versand, Webhook-Roundtrip)
9. **Dedup + Merge**
10. **DSGVO-Tools** (Auskunft, Hard-Delete)
11. **Foto-Upload**
12. **Audit-Log-Hooks + Aktivitäten-Anzeige**
13. **Cron-Jobs** (Reconciliation + Push-Queue)
14. **Phase-1-Abschlusstest** + Doku-Update

---

**Nächster Schritt:** Wenn dieses Dokument so passt, fange ich mit Schritt 1 (Schema-Migration) an. Sag Bescheid, wenn ich am Schema noch was ändern soll — Schema-Änderungen nach Live-Gang sind teuer.
