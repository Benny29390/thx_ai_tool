# Mail-Modul für das KI-Tool — Briefing

**Stand:** 22.05.2026 — Erstfassung, Arbeitsgrundlage für die Umsetzung.

## 1. Auftrag

Ein eigenständiges Posteingang-Modul ins KI-Tool integrieren, mit dem Mails per IMAP oder EML-Upload verarbeitet werden. Pro Mail: links Original-Vorschau, rechts KI-generierte Antwort und Daten-Korrektur, ähnlich dem WITTEKIND-Katalogtool (Posteingang links / Bereinigung rechts).

Drei Ausbaustufen:

1. **Lesen + KI-Vorschlag + manuelle Antwort** — Postfach abrufen, klassifizieren, Antwort vorschlagen, von Hand senden
2. **Standardmails halb-automatisch beantworten** — Vorlagen + Regel-Engine, Mensch bestätigt
3. **Folgeprozesse im LAM-System aktivieren** — Mail von Anbieter erkennen, Korrespondenz-Eintrag automatisch anlegen, Maßnahme verknüpfen, ggf. Konditionen extrahieren

**Test-Postfach:** `pr@thoxan.com` — bewusst getrennt von Toms persönlichem Konto, damit nichts versehentlich automatisiert versendet wird, was nicht soll.

**Wichtig: das Modul ist globaler Charakter**, nicht LAM-spezifisch. LAM ist nur EIN Konsument; weitere Anwendungen kommen später (z.B. Kundenanfragen aus dem Chat, Mahnungs-Bearbeitung, etc.).

## 2. Vorbild & Erkenntnisse aus dem WITTEKIND-Tool

Im `/var/www/docs/wittekind-tools/` läuft bereits ein funktionierender IMAP-Pull-Mechanismus (`ImapIngestionService.php`, `ImapClientFactory.php`, Console-Command `katalog:imap-pull`). Wir übertragen die Konzepte, schreiben den Code aber neu in Vanilla-PHP-Stil (kein Laravel, kein Livewire).

**Was wir 1:1 übernehmen:**

- **Postfach-Konzept mit drei Ordnern:** `INBOX` (Eingang), `Verarbeitet` (erfolgreich + Dubletten), `Fehler` (Parser-Fehler) — Mails werden NACH der Verarbeitung verschoben, damit man sieht, was offen ist und was nicht
- **Dubletten-Schutz** über `Message-ID` + Content-Hash — gleiche Mail zweimal (per IMAP + per EML-Upload) führt nicht zu zwei Einträgen
- **EML-Upload als zweite Eingangsquelle** — gleiche Pipeline wie IMAP, andere Eintrittsstelle
- **Pull-Log-Tabelle** mit max 200 Einträgen Auto-Rotation für Cron-Diagnose
- **Manueller Pull-Button** in der UI zusätzlich zum Cron, mit Inline-Feedback
- **Verbindungs-Test** für die Konfiguration (analog Sistrix-Test im Settings-Hub)
- **Aktiv-Schalter** pro Konto, damit man IMAP temporär abschalten kann

**Was wir anders / generischer machen:**

- **Mehrere Konten** statt nur eines fixen Postfachs
- **Generische Klassifikation** (Kategorien sind konfigurierbar, nicht hartcodiert wie „Katalogbestellung")
- **Antwort-Generierung statt PDF-Generierung** — KI schlägt Antwort-Text vor, SMTP-Versand statt PDF
- **Vorlagen-System mit Platzhaltern** (Standardantworten für wiederkehrende Anfragen)
- **LAM-Folgeprozesse statt weclapp-Sync** — wenn Mail aus Anbieter-Domain kommt, automatisch Korrespondenz-Eintrag

**PHP-Library für IMAP:** WITTEKIND nutzt `webklex/php-imap` über Composer. Wir haben keinen Composer — Option A: Composer für DIESE eine Library nachinstallieren. Option B: PHP-eigenes `imap`-Modul (`imap_open`, `imap_fetch_overview`) — funktional ausreichend, etwas hakeliger bei Multipart-MIME. **Empfehlung: A**, weil webklex stabil und ausgereift ist; alternativ direkt das Symfony-Mime-Modul (auch via Composer). Klärung mit Tom: ist Composer auf dem Server (ai.thoxan-dev.de) verfügbar oder müssen wir das ohne lösen?

## 3. Datenmodell

### `mail_konten`

Pro IMAP-/SMTP-Konto eine Zeile. In Phase 1 nur ein Eintrag (pr@thoxan.com), Schema ist aber Multi-Account-fähig.

| Feld | Typ | Hinweise |
|---|---|---|
| id | int PK | |
| name | varchar(120) | Anzeigename, z.B. „PR Thoxan" |
| email_adresse | varchar(255) | z.B. pr@thoxan.com |
| aktiv | tinyint(1) | Polling pausierbar |
| imap_host | varchar(120) | |
| imap_port | int | Default 993 |
| imap_username | varchar(255) | |
| imap_password_enc | text | AES-256-GCM verschlüsselt |
| imap_encryption | enum('ssl','tls','starttls') | |
| imap_folder_inbox | varchar(80) | Default `INBOX` |
| imap_folder_verarbeitet | varchar(80) | Default `INBOX.Verarbeitet` |
| imap_folder_fehler | varchar(80) | Default `INBOX.Fehler` |
| smtp_host | varchar(120) | |
| smtp_port | int | Default 587 |
| smtp_username | varchar(255) | |
| smtp_password_enc | text | |
| smtp_encryption | enum('ssl','tls','starttls') | |
| signatur | text | HTML/Plain-Text-Signatur, wird beim Versand angehängt |
| auto_antwort_aktiv | tinyint(1) | Master-Schalter Phase 3 |
| auto_antwort_konfidenz_min | decimal(3,2) | Default 0.90 — erst ab dieser KI-Konfidenz automatisch senden |
| erstellt_am, aktualisiert_am | datetime | |

### `mail_nachrichten`

Eine Zeile pro empfangene Mail.

| Feld | Typ | Hinweise |
|---|---|---|
| id | int PK | |
| konto_id | int FK | |
| richtung | enum('eingang','ausgang') | |
| message_id | varchar(255) | Mail-Header für Dubletten-Check |
| in_reply_to | varchar(255) | Mail-Header, für Thread-Erkennung |
| absender_email | varchar(255) | |
| absender_name | varchar(120) | |
| empfaenger_email | varchar(255) | |
| cc_emails | text | komma-getrennt |
| betreff | varchar(500) | |
| body_plain | longtext | Klartext-Body (für KI) |
| body_html | longtext | HTML-Body (für Anzeige) |
| empfangen_am | datetime | |
| roh_eml_pfad | varchar(500) | Pfad zur abgelegten .eml für späteres Re-Parsing |
| quelle | enum('imap','eml_upload','manuell') | |
| anhaenge_anzahl | int | |
| status | enum('eingang','klassifiziert','beantwortet','archiviert','ignoriert','fehler') | |
| gelesen | tinyint(1) | |
| markiert | tinyint(1) | „Star" für wichtige Mails |
| erstellt_am, aktualisiert_am | datetime | |
| geloescht_am | datetime nullable | Soft-Delete |

### `mail_anhaenge`

| Feld | Typ |
|---|---|
| id | int PK |
| mail_id | int FK |
| dateiname | varchar(255) |
| mime_typ | varchar(120) |
| groesse_bytes | int |
| pfad | varchar(500) |

### `mail_klassifikationen`

Eine Zeile pro klassifizierte Mail (1:1 zu `mail_nachrichten`).

| Feld | Typ | Hinweise |
|---|---|---|
| mail_id | int PK | |
| kategorie | varchar(60) | z.B. anbieter_antwort, standardfrage, kundenanfrage, spam, info, sonstiges |
| kategorie_konfidenz | decimal(3,2) | |
| absicht | varchar(120) | KI-Stichwort, z.B. „Preis anfragen", „Beschwerde", „Anbieter-Konditionen" |
| sprache | varchar(8) | de/en |
| dringlichkeit | enum('niedrig','mittel','hoch') | |
| vorgeschlagene_antwort | longtext | KI-Output |
| vorlage_id | int nullable FK | Wenn aus Vorlage generiert |
| folgeaktion | varchar(80) | z.B. `auto_antworten`, `lam_korrespondenz`, `lam_anbieter_zuordnen`, `keine` |
| folgeaktion_konfidenz | decimal(3,2) | |
| ki_meta | json | Vollständige KI-Antwort + Begründung |
| klassifiziert_am | datetime | |
| ki_modell | varchar(60) | z.B. `claude-haiku-4-5` |

### `mail_antworten`

Versandte Antworten (auch manuell, mit Korrektur-Tracking).

| Feld | Typ |
|---|---|
| id | int PK |
| eingang_mail_id | int FK | Auf welche Mail wurde geantwortet |
| ausgang_mail_id | int FK | Neuer Eintrag in `mail_nachrichten` (richtung=ausgang) |
| vorlage_id | int nullable | |
| ki_vorschlag | longtext | Original-KI-Text, vor Edit |
| finaler_text | longtext | Was tatsächlich gesendet wurde |
| wurde_editiert | tinyint(1) | KI-Vorschlag vs. finaler Text unterschiedlich? |
| versendet_am | datetime | |
| versendet_von_user_id | int FK | Wer hat freigegeben |
| auto_versendet | tinyint(1) | War das ein voll-automatischer Versand? |

### `mail_vorlagen`

| Feld | Typ |
|---|---|
| id | int PK |
| name | varchar(120) |
| kategorie | varchar(60) | Match-Kriterium für Auto-Anwendung |
| betreff_template | varchar(500) | z.B. `Re: {{original_betreff}}` |
| body_template | longtext | mit Platzhaltern wie `{{vorname}}`, `{{firma}}` |
| platzhalter | json | Liste der Platzhalter, mit Pflicht/Optional + KI-Extraktions-Hinweis |
| aktiv | tinyint(1) | |
| erstellt_am | datetime | |

### `mail_regeln`

Manuelle Klassifikations-Regeln (höhere Priorität als KI).

| Feld | Typ |
|---|---|
| id | int PK |
| name | varchar(120) |
| absender_pattern | varchar(255) nullable | Regex auf E-Mail-Adresse |
| betreff_pattern | varchar(255) nullable | Regex auf Subject |
| body_pattern | varchar(255) nullable | Regex auf Body |
| kategorie | varchar(60) | Wird gesetzt, wenn Match |
| folgeaktion | varchar(80) | |
| vorlage_id | int nullable | Auto-Vorlage |
| prioritaet | int | 1=hoch, höhere Nummer wird zuerst getestet |
| aktiv | tinyint(1) | |

### `mail_lam_verknuepfung`

Hilfstabelle: Mail → LAM-Entität (Anbieter / Maßnahme / Korrespondenz-Eintrag).

| Feld | Typ |
|---|---|
| mail_id | int FK |
| typ | enum('anbieter','massnahme','korrespondenz') |
| ziel_id | varchar(64) | ULID |
| automatisch | tinyint(1) | KI-erstellt oder manuell |

### `mail_pull_logs`

Diagnose-Tabelle wie WITTEKIND, max 200 Einträge.

| Feld | Typ |
|---|---|
| id, konto_id, gestartet_am, dauer_ms, trigger (cron/manuell), erfolg_count, dublette_count, fehler_count, uebersprungen_count, verbindungs_fehler, details_json |

## 4. Architektur & Service-Schicht

### Services

- **`MailKontoService`** — CRUD für Konten + Verbindungs-Test (IMAP+SMTP)
- **`MailImapService`** — Poll-Pipeline analog WITTEKIND
- **`MailEmlService`** — Upload-Parser (gemeinsame Methode `verarbeiteRohEml($content, $quelle, $kontoId)` mit dem Imap-Service)
- **`MailKlassifikationService`** — Regeln zuerst, dann KI; speichert in `mail_klassifikationen`
- **`MailAntwortService`** — Vorschlag generieren, Vorlage anwenden, manuell editieren, SMTP-Versand
- **`MailSmtpService`** — Versand via PHP-`mail()` oder PHPMailer (analog Composer-Frage)
- **`MailLamAdapter`** — alle LAM-Folgeaktionen gebündelt (Anbieter-Erkennung, Korrespondenz-Eintrag, Maßnahme-Verknüpfung)

### KI-Klassifikation (Claude Haiku)

System-Prompt strukturiert das Antwort-JSON:

```json
{
  "kategorie": "anbieter_antwort|standardfrage|kundenanfrage|spam|info|sonstiges",
  "kategorie_konfidenz": 0.0-1.0,
  "absicht": "kurze Beschreibung in 1 Satz",
  "sprache": "de|en",
  "dringlichkeit": "niedrig|mittel|hoch",
  "folgeaktion": "auto_antworten|vorlage_vorschlagen|lam_korrespondenz_anlegen|lam_anbieter_zuordnen|ignorieren",
  "folgeaktion_konfidenz": 0.0-1.0,
  "vorgeschlagene_antwort": "...",
  "begruendung": "warum diese Klassifikation",
  "anbieter_kandidat": "wenn aus Email-Domain ableitbar, sonst null",
  "extrahierte_felder": {
    "preis": null, "linktext": null, "veroeffentlichungs_url": null,
    "linkziel": null, "anbieter_kontakt_name": null
  }
}
```

Bei Klassifikation `anbieter_antwort` + Konfidenz ≥ 0.8 wird automatisch:
- Anbieter via E-Mail-Domain in `lam_anbieter` gesucht (Lookup: alle `lam_kontakte.email` LIKE `%@domain.tld`)
- Wenn gefunden: `lam_kommunikation`-Eintrag angelegt mit Typ `mail_eingang`, `anbieter_id` + ggf. `massnahme_id` (falls in Subject/Body Linktext oder Domain einer offenen Maßnahme erkannt)
- Wenn nicht gefunden: Hinweis in UI „Anbieter unklar — manuell zuordnen?" mit Quick-Action

### Folgeaktions-Logik (`MailLamAdapter`)

| Folgeaktion | Was passiert |
|---|---|
| `lam_anbieter_zuordnen` | Lookup über Mail-Domain, wenn eindeutig → automatisch verknüpfen, sonst Quick-Action-Vorschlag |
| `lam_korrespondenz_anlegen` | Eintrag in `lam_kommunikation` mit Anhang (falls Mail Anhänge hat) + Anbieter-/Maßnahmen-Referenz |
| `lam_konditionen_extrahieren` | (Phase 4) KI extrahiert Preis/Buchungstyp aus Mail → `lam_konditionen` vorbefüllt |
| `lam_status_setzen` | (Phase 4) bei Anbieter-Zusage automatisch Maßnahme-Status auf `bei_anbieter` |
| `auto_antworten` | Vorlage + Platzhalter ausfüllen → SMTP senden → in `mail_antworten` loggen |
| `ignorieren` | nur als bearbeitet markieren, kein weiterer Schritt |

## 5. UI-Konzept

### Hauptansicht `/mail`

Inbox-Pattern analog WITTEKIND + Chat-Sidebar:

- **Sidebar links (320px):**
  - Konto-Switch oben (Dropdown, Phase 1 nur „PR Thoxan")
  - Filter-Chips: alle / ungelesen / klassifiziert / beantwortet / mit Anhang / markiert
  - Suchfeld (Absender, Betreff, Volltext)
  - Liste der Mails mit: Absender-Initial, Betreff, Snippet (50 Zeichen), Datum, Anhang-Icon, Kategorie-Badge, Konfidenz-Stern
  - „IMAP jetzt abrufen"-Button (manueller Trigger) mit Inline-Status
  - „📥 EML hochladen"-Button (Drag-and-Drop-Modal)

- **Detail rechts:**
  - Header: Absender, Betreff, Datum, Anhänge, LAM-Verknüpfungs-Badges („→ Anbieter X" / „→ Maßnahme Y")
  - **Original-Mail** (collapsible, HTML-gerendert mit sanitiziertem CSS)
  - **KI-Klassifikation** (Kategorie, Konfidenz, Begründung, Quick-Action-Buttons)
  - **Antwort-Editor**:
    - Vorlage-Dropdown (Auto-Wahl wenn Regel matcht)
    - Editor mit KI-Vorschlag, voll editierbar
    - Vorschau-Toggle (Markdown / HTML)
    - Empfänger-Felder (To/CC/BCC, default = Reply-To)
    - Anhänge dranhängen
    - Buttons: **Senden** / **Entwurf speichern** / **Als beantwortet markieren ohne Senden**
  - **LAM-Aktionen** (Quick-Buttons):
    - „→ Korrespondenz-Eintrag anlegen" (mit Anbieter+Maßnahmen-Picker)
    - „→ Konditionen aus Mail anlegen" (Phase 4)
    - „→ Maßnahme-Status updaten" (Phase 4)
  - **Audit-Trail** unter dem Detail: was wurde wann mit dieser Mail gemacht (klassifiziert, beantwortet, verknüpft)

### Settings-Tab `/admin/settings?tab=mail`

- Konten-Liste mit „+ neues Konto"-Button
- Pro Konto: Edit-Modal mit IMAP + SMTP-Feldern, „Verbindung testen", Signatur, Auto-Antwort-Schalter + Konfidenz-Schwelle
- Vorlagen-Bibliothek: Liste + CRUD
- Regel-Engine: Liste der Pattern-Regeln, Prioritäts-Reihenfolge

### Eigener Tab im LAM für „eingegangene Mails dieses Anbieters"

Im Anbieter-Detail unter Korrespondenz: alle verknüpften Mail-Threads sichtbar.

## 6. Sicherheit & Datenschutz

- **Passwörter verschlüsselt:** IMAP + SMTP-Passwörter via `Core\Settings`/`Core\Crypto` (AES-256-GCM, existierender Layer)
- **Rate-Limit Auto-Versand:** max 50 Mails pro Stunde pro Konto (verhindert Spam-Eskalation bei KI-Fehlern)
- **„Reply-All-Schutz":** Auto-Versand niemals auf Mailinglisten (Listen-Header erkennen: `List-Id`, `List-Unsubscribe`)
- **Stop-Wörter:** Wenn Mail bestimmte Begriffe enthält (Beschwerde, Anwalt, Klage, Datenschutz, GDPR) → NIE auto-antworten, immer als manuell markieren
- **DSGVO:**
  - Klar dokumentieren: was wird auf unseren Servern gespeichert
  - Soft-Delete im LAM, harter Delete auf Knopf möglich
  - Anhänge in dediziertem Storage-Ordner (`storage/mail-attachments/{konto_id}/{jahr}-{monat}/`)
  - Speicher-Mengen-Limit pro Konto (z.B. 5 GB) mit Auto-Cleanup ältester Mails > 90 Tage (optional, Konto-Setting)
- **Test-Konto-Schutz:** In Phase 1 ist Auto-Versand SYSTEMWEIT DEAKTIVIERT (App-Setting `mail_auto_versand_global_aktiv=0`); erst nach Daniel-Style-Review-Phase einschalten

## 7. Phasen-Plan

### Phase 0 — Vorklärung (Du + Ich)

- Composer-Frage: webklex/php-imap nutzen oder eigene IMAP-Implementierung?
- Bestätigung: pr@thoxan.com bereits eingerichtet, IMAP-Zugangsdaten existieren?
- Welcher Provider hostet pr@thoxan.com? (IONOS, M365, Google Workspace, eigener Server?)
- Welche Kategorien sind initial sinnvoll? (Vorschlag: anbieter_antwort, kundenanfrage, presseanfrage, spam, info, sonstiges)
- Welche Vorlagen brauchen wir initial? (z.B. „Danke für Anfrage, Daniel kommt zurück", „Pressekit-Anforderung", „Hinweis falsche Adresse")
- Sollen ausgehende Mails einen festen Footer/Disclaimer/Impressum-Link haben?

### Phase 1 — IMAP-Lese-Sync + KI-Vorschlag + manuelle Antwort (Sprint 1)

- Schema-Migrationen
- `MailKontoService` + Settings-UI
- `MailImapService` mit Cron (`/etc/cron.d/ki-tool-mail`) alle 10 Minuten
- EML-Upload-Endpoint
- KI-Klassifikation pro Mail
- Inbox-UI mit Detail-Sicht
- SMTP-Versand manuell
- IMAP-Pull-Log + Diagnose-Anzeige in Settings

**Akzeptanztests Phase 1:**
- A1.1: IMAP-Konfig speichern, Verbindungs-Test grün
- A1.2: Cron-Pull holt 3 Test-Mails, alle landen in DB + Verarbeitet-Ordner
- A1.3: EML-Upload einer manuell exportierten Mail führt zu identischem DB-Eintrag (Dubletten-Schutz greift)
- A1.4: KI klassifiziert eine Test-Mail in eine der 6 Kategorien mit Konfidenz
- A1.5: Antwort über UI senden landet beim Empfänger + Eintrag in `mail_antworten`

### Phase 2 — Vorlagen + Regeln + halbautomatische Antworten (Sprint 2)

- Vorlagen-CRUD + Platzhalter-System
- Regel-Engine mit Prioritäten
- „Vorlage anwenden"-Workflow (Regel matcht → Vorlage automatisch vorgeschlagen, KI füllt Platzhalter)
- Audit-Trail in Mail-Detail
- Rate-Limit Auto-Versand
- Stop-Wort-Liste

**Akzeptanztests Phase 2:**
- A2.1: Regel „Absender enthält `formulare@wittekind` → Kategorie `katalogbestellung`" matcht
- A2.2: Vorlage mit Platzhaltern `{{vorname}}` wird durch KI-Extraktion gefüllt
- A2.3: Stop-Wort „Anwalt" verhindert Vorlagen-Vorschlag, zeigt Warnung

### Phase 3 — Vollautomatische Beantwortung mit Konfidenz-Gate (Sprint 3)

- Auto-Versand-Schalter pro Konto + globaler Master-Switch
- Konfidenz-Schwelle (default 0.90) konfigurierbar
- Erste 4 Wochen: Auto-Vorschlag, aber Versand muss von Mensch freigegeben werden (Audit-Modus)
- Statistik-Dashboard: wie oft hat die KI richtig gelegen (Korrektur-Rate)
- Erst ab Korrektur-Rate < 10% wird Auto-Versand aktiviert

### Phase 4 — Tiefe LAM-Integration (Sprint 4)

- `MailLamAdapter` mit allen Folgeaktionen
- Anbieter-Erkennung automatisch
- Korrespondenz-Eintrag automatisch bei Anbieter-Match
- Konditionen-Extraktion aus Mail (KI: „aus dieser Mail Preis + Buchungstyp extrahieren")
- Maßnahmen-Status-Update bei Anbieter-Zusage
- Anbieter-Detail-Seite zeigt Mail-Thread-Historie

## 8. Out-of-Scope (vorerst)

- Multi-Account-Login (User = Konto-Trennung) — Phase 1 hat 1 Konto pro User, alle sehen alles
- Vollständiger Mail-Client mit Ordner-Navigation — wir haben nur 3 Ordner (Eingang/Verarbeitet/Fehler)
- Outlook/Gmail-OAuth statt IMAP/SMTP-Passwort — kommt mit M365/Google-Anbindung, ist eigenes Projekt
- HTML-Mail-Composer mit WYSIWYG — Phase 1 ist Plain-Text + Signatur, HTML kommt später
- Kalender/Termin-Integration aus Mail — nicht geplant
- Verschlüsselte Mails (PGP/S-MIME) — nicht geplant

## 9. Drei konkrete Anwendungsfälle, die das System können muss

**1. Pressekit-Anfrage (Standard)**
- Eingang: „Bitte schicken Sie mir Ihr Pressekit für einen Artikel über Linkbuilding"
- KI: Kategorie `presseanfrage` (0.95), Folgeaktion `vorlage_vorschlagen` (Vorlage „Pressekit-Versand")
- Aktion: Vorlage rendert PDF-Link + Standard-Disclaimer, Mensch sendet

**2. Anbieter-Konditionen-Antwort**
- Eingang: „Anbei unsere aktuellen Konditionen für gesund-magazin.de: Gastartikel 850 EUR netto, …"
- KI: Kategorie `anbieter_antwort` (0.92), Folgeaktion `lam_korrespondenz_anlegen`, Extraktion: `preis=850, linkart=gastartikel, domain=gesund-magazin.de`
- Aktion: Anbieter erkannt (Mail-Domain `xxx@gesund-magazin.de`), Korrespondenz-Eintrag automatisch in LAM angelegt, Quick-Action „Kondition anlegen" mit vorbefülltem Drawer

**3. Spam-Mail von ungeladenem Linkbuilder**
- Eingang: „I have a website with high DA, want to exchange links?"
- KI: Kategorie `spam` (0.98), Folgeaktion `ignorieren`
- Aktion: Mail wird in Verarbeitet/Spam-Subordner verschoben, in DB als status=ignoriert, kein UI-Alert, kein Versand

## 10. Klärungs-Fragen vor Phase-1-Start

1. **Composer:** webklex/php-imap (Composer) ODER PHP-eigenes `imap`-Modul ODER direkt selbst MIME parsen?
2. **SMTP-Library:** PHPMailer (Composer) ODER PHP-`mail()` (limitiert) ODER eigener SMTP-Wrapper?
3. **pr@thoxan.com Provider + Zugangsdaten** stehen bereit?
4. **Initiale Kategorien:** Die 6 oben — passt das? Was fehlt für Deinen Alltag?
5. **Initiale Vorlagen:** Welche 3–5 Standardantworten sind heute schon Daniel/Tom-Routine, die ich als Vorlagen bauen kann?
6. **Stop-Wörter-Liste:** Welche Begriffe sollen Auto-Antwort IMMER verhindern? (Vorschlag: Anwalt, Klage, Beschwerde, Datenschutz, GDPR, Abmahnung, Reklamation)
7. **Signatur-Vorlage** für pr@thoxan.com — möchtest Du mir die schicken oder soll ich eine Standardform vorschlagen?
8. **Cron-Frequenz:** alle 10 Minuten reicht oder eher alle 5? (Trade-off: schneller vs. IMAP-Server-Load)
9. **Anhang-Limit:** max Dateigröße pro Anhang? Vorschlag 25 MB analog Korrespondenz-Anhänge im LAM.
10. **Erstes Empfänger-Whitelisting:** in Phase 1 soll der Auto-Versand nicht laufen — aber wenn manuell gesendet wird, soll ich vor jedem Versand eine Empfänger-Bestätigung einbauen (sicher gegen versehentliches Reply-All)?

## 11. Schnitt zum LAM

Das LAM-System bekommt drei neue Eintrittspunkte:

- **`lam_kommunikation` wird automatisch befüllt** wenn Anbieter-Mail erkannt → kein manueller Korrespondenz-Eintrag mehr nötig
- **`lam_aufgaben` bekommt Mail-Trigger:** unklassifizierte Mails aus Anbieter-Domains werden zu Aufgabe „Anbieter X hat geschrieben — bitte Antwort prüfen"
- **`lam_audit_logs` wird um Mail-Aktionen erweitert:** Eintrag pro auto-versendeter Antwort

Bestehende LAM-Module bleiben unverändert. Die Mail-Welt ist ein eigener Modul-Block.

---

**Nächster Schritt:** Tom klärt die 10 Fragen unter §10. Sobald Antworten da sind, beginne ich mit Phase 1 (Schema + IMAP-Pull + Settings-UI + erste Inbox-Sicht). Geschätzter Aufwand Phase 1: 1–1,5 Tage konzentrierte Arbeit.
