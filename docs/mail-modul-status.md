# Mail-Modul — Status

**Stand:** 22.05.2026 — Phase 1+2+4 funktional vollständig, Phase 3 bewusst offen

## Was läuft

### Architektur
- Composer + `webklex/php-imap` + `symfony/mailer` + `symfony/mime` installiert
- Composer-Autoloader in [api/handler.php](api/handler.php) integriert (fail-safe falls vendor/ fehlt)
- 9 neue Tabellen + 4 Settings + 3 Storage-Ordner via [scripts/migrate-mail-modul.php](scripts/migrate-mail-modul.php) (idempotent)
- Neue Capability `CAP_MAIL` + Default für Admin und Manager

### Service-Layer (6 Klassen)
- [`MailKontoService`](services/MailKontoService.php) — CRUD + Verbindungs-Test (IMAP + SMTP) mit AES-256-GCM-Passwort-Verschlüsselung
- [`MailImapService`](services/MailImapService.php) — Polling-Pipeline mit MIME-Parser, Dubletten-Schutz, Verschiebe-Ordner-Logik
- [`MailService`](services/MailService.php) — Inbox-Listen, Detail, Mark-As-Read, Status, Markiert-Toggle, Soft-Delete
- [`MailKlassifikationService`](services/MailKlassifikationService.php) — Regel-Engine (priorisiert) + Claude-Haiku-KI mit Heuristik-Fallback
- [`MailAntwortService`](services/MailAntwortService.php) — SMTP-Versand mit Stop-Wort-Schutz, Rate-Limit (50/h), Mailinglisten-Schutz, automatischer Korrespondenz-Eintrag
- [`MailLamAdapter`](services/MailLamAdapter.php) — Anbieter-Auto-Match via Mail-Domain, Maßnahmen-Auto-Match via Subject/Body, Korrespondenz + Aufgabe automatisch anlegen

### API-Endpoints (28)
Alle unter `/api/v1/mail/`:
- Konten: `konten`, `konto-save`, `konto-loeschen`, `konto-test`
- Inbox: `nachrichten`, `nachricht-detail`, `nachricht-aktion`
- Pull: `pull` (manuell)
- Klassifikation: `klassifizieren`
- Antwort: `antwort-senden`, `antwort-anhang-upload` (Path-Traversal-geschützt)
- Upload: `eml-upload`
- Anhang: `anhang` (Path-Traversal-geschützt)
- Vorlagen: `vorlagen`, `vorlage-save`, `vorlage-loeschen`
- Regeln: `regeln`, `regel-save`, `regel-loeschen`
- LAM: `lam-verknuepfen` (manuell), `lam-kondition-anlegen`, `lam-massnahme-status`
- Badge: `ungelesen-zaehler` (Sidebar)
- Sichten + Ordner: `personen-sicht`, `ordner`, `ordner-save`, `ordner-loeschen`, `verschieben`

### UI
- **Sidebar-Eintrag „Mail"** unter Site-Monitor (sichtbar bei Cap `mail`) mit **Live-Badge ungelesene Mails** (Auto-Refresh alle 60 Sek)
- **Inbox-Sicht** [/mail](views/mail/inbox.php) — **4-Spalten-Layout** wie Outlook: Ordner | Mail-Liste | Original | KI-Antwort
  - **Spalte 1 (Ordner):** System-Ordner (Posteingang/Markiert/Gesendet/Archiv/Spam/Papierkorb), manuelle Ordner (CRUD via ＋), LAM-Sichten (Anbieter/Maßnahmen/Absender)
  - **Spalte 2 (Liste):** Suche, Filter (ungelesen/Threads), Mail-Items mit Rechtsklick-Kontextmenü
  - **Spalte 3 (Detail):** Header, LAM-Quick-Actions, Thread-Nav, HTML/Text-Toggle, Anhänge, Audit-Trail
  - **Spalte 4 (Antwort):** KI-Klassifikation, Editor mit Vorlage/Zitieren/Anhänge, Senden-Footer
- **HTML-Rendering** in Sandbox-Iframe mit Default-CSS, Tracking-Pixel-Filter und Script-/onEvent-Stripping
- **Antwort-Editor** mit Vorlagen-Picker (Platzhalter-Ersetzung), „↩ Original zitieren" und Multi-Anhang-Upload
- **Thread-Gruppierung** in Sidebar (toggle): Mails mit gleichem Betreff (Re:/AW:/Fwd:/WG: normalisiert) zu einem Eintrag zusammengefasst; Thread-Navigation oben im Detail
- **LAM-Quick-Actions** im Detail: „💶 Kondition anlegen" (vorbefüllt aus KI-Extraktion) und „Maßnahme-Status setzen"
- **EML-Upload-Modal** mit Multi-Datei
- **Settings-Tab „E-Mail"** unter [/admin/settings?tab=smtp](views/admin/settings/_tab_smtp.php) — System-Versand-SMTP + Mail-Konten-CRUD mit IMAP/SMTP-Verbindungs-Tests
- **Settings-Tab „Mail-Tool"** unter [/admin/settings?tab=mail](views/admin/settings/_tab_mail.php) — Auto-Versand-Master + Polling + Stop-Wörter + **Vorlagen-CRUD** + **Regel-CRUD** + Diagnose-Tabelle (letzte 20 Pull-Läufe)

### Cron
- [/etc/cron.d/ki-tool-mail-pull](/etc/cron.d/ki-tool-mail-pull) — alle 5 Min Tick
- Effektives Intervall pro Konto via `mail_pull_intervall_minuten` (Default 10)
- Log: `/var/log/ki-tool-mail-pull.log`

### LAM-Hooks (automatisch bei Klassifikation)
- Mail-Domain matcht Anbieter-Kontakt-E-Mail-Domain → `lam_kommunikation`-Eintrag automatisch (Typ `mail_eingang`)
- Subject/Body matcht offene Maßnahmen-Domain/Linktext → `massnahme_id` zusätzlich verknüpft
- Anbieter unbekannt + KI vermutet Anbieter-Bezug → `lam_aufgaben`-Eintrag „Anbieter zuordnen"
- SMTP-Versand mit Anbieter-Verknüpfung → `lam_kommunikation`-Eintrag Typ `mail_ausgang`

### Sicherheits-Schutzregeln
- **Stop-Wort-Liste** (Anwalt, Klage, Datenschutz, GDPR, Abmahnung, Reklamation, Beschwerde, Inkasso) — Auto-Versand IMMER verweigert
- **Mailinglisten-Schutz** — `List-Id` oder `List-Unsubscribe`-Header → kein Auto-Versand
- **Rate-Limit** 50 Versendungen pro Konto pro Stunde
- **Auto-Versand-Master-Switch** global deaktiviert (`mail_auto_versand_global_aktiv=0`)
- **Konto-Schalter** muss zusätzlich aktiv sein (`auto_antwort_aktiv=1`)
- **Konfidenz-Schwelle** pro Konto (Default 0.95) für Auto-Versand
- Passwörter AES-256-GCM verschlüsselt in DB

## Wie startet Tom?

**Aufteilung der Settings-Tabs:**
- **„E-Mail"** ([/admin/settings?tab=smtp](http://ai.thoxan-dev.de/admin/settings?tab=smtp)) = alle Mail-**Zugangsdaten**:
  - System-Versand-SMTP (für Einladungen, Passwort-Reset)
  - Mail-Konten (IMAP/SMTP pro Postfach) für das Mail-Tool
- **„Mail-Tool"** ([/admin/settings?tab=mail](http://ai.thoxan-dev.de/admin/settings?tab=mail)) = nur **Tool-Einstellungen** für /mail:
  - Auto-Versand-Master-Switch (default OFF)
  - Pull-Intervall + Anhang-Limit
  - Stop-Wörter
  - Diagnose-Tabelle (letzte 20 Pull-Läufe)

**Schritte:**
1. **[/admin/settings?tab=smtp](http://ai.thoxan-dev.de/admin/settings?tab=smtp)** öffnen → Sektion „Mail-Konten"
2. **„+ Neues Konto"** klicken — Daten für `pr@thoxan.com` eintragen:
   - Name: „PR Thoxan"
   - E-Mail: `pr@thoxan.com`
   - IMAP: `imap.ionos.de:993`, SSL/TLS, Username = E-Mail, Passwort
   - SMTP: `smtp.ionos.de:587`, STARTTLS, Username = E-Mail, Passwort
3. **„test IMAP"** + **„test SMTP"** Buttons drücken — grün = ok
4. **„↻ IMAP-Pull"** unter [/mail](http://ai.thoxan-dev.de/mail) drücken — Mails werden geholt
5. Mail anklicken → KI klassifiziert beim Detail-Aufruf (oder Button „🤖 KI" rechts klicken)
6. Antwort-Text editieren → „📤 Senden"

## Was noch fehlt

- **Phase 3 — Halbautomatik mit Konfidenz-Gate**: Bewusst NICHT gebaut (Tom: „Mensch IMMER dazwischen", Auto-Versand bleibt vorerst aus). Infrastruktur ist da (Stop-Wort-Schutz, Rate-Limit, Mailinglisten-Schutz, Konfidenz-Schwelle pro Konto, Master-Switch), aber Standard ist manuelle Freigabe.
- **HTML-Sanitizer** über `htmlpurifier` o.ä. — aktuell nur Script/onEvent-Stripping + Sandbox-Iframe. Reicht für seriöse Mails, reicht nicht für Spam-Mails mit raffinierten Tricks.

## Was schon umgesetzt ist (Phase 2 + 4)

- **Vorlagen-CRUD-UI** im Settings-Tab „Mail-Tool" (Tabelle `mail_vorlagen` + UI)
- **Regel-CRUD-UI** im Settings-Tab „Mail-Tool" (Tabelle `mail_regeln` + UI)
- **Konditionen-Quick-Action** im Detail: KI-Extraktion → Drawer mit vorbefülltem Buchungstyp/Preis/Link-Typ → `lam_konditionen`-Eintrag mit Verknüpfung zur Mail
- **Maßnahmen-Status-Quick-Action** im Detail: Dropdown + Button → setzt `lam_massnahmen.status` und verknüpft die Mail per `mail_lam_verknuepfung`
- **Thread-Gruppierung** in Inbox-Sidebar (toggle) per normalisiertem Betreff
- **Reply-Editor mit Original-Zitat**: `>` -prefixed Quote des Plain-Body, Klick auf „↩ Original zitieren"
- **Anhang-Upload** im Antwort-Editor: Multi-File-Upload nach `/var/www/storage/mail/anhaenge/_uploads/{uid}/`, sicherer Pfad-Check beim Versand, Symfony Mailer hängt sie an
- **Sidebar-Badge** für ungelesene Mails (Auto-Refresh 60 Sek)

## Akzeptanztests (manuell, sobald Konto eingetragen)

- **T1**: Settings → neues Konto anlegen, IMAP+SMTP-Test = grün
- **T2**: „IMAP-Pull" manuell → Mails landen in Inbox-Liste
- **T3**: EML-Datei hochladen → identische Mail wird als Dublette erkannt
- **T4**: Mail anklicken → KI klassifiziert (Kategorie + Folgeaktion sichtbar)
- **T5**: Antwort editieren + senden → kommt beim Empfänger an, Eintrag in `mail_antworten`
- **T6**: Anbieter-Mail (E-Mail-Domain = LAM-Anbieter-Kontakt-Domain) → automatischer Korrespondenz-Eintrag im LAM
- **T7**: Mail mit Stop-Wort „Anwalt" → Warnung in der Antwort-UI sichtbar
- **T8**: 51× hintereinander senden → bei #51 Rate-Limit-Fehler

## Schema-Marker

- Tabellen: `mail_konten`, `mail_nachrichten`, `mail_anhaenge`, `mail_klassifikationen`, `mail_vorlagen`, `mail_antworten`, `mail_regeln`, `mail_lam_verknuepfung`, `mail_pull_logs`
- Settings: `mail_auto_versand_global_aktiv`, `mail_pull_intervall_minuten`, `mail_anhang_max_mb`, `mail_stop_woerter`
- Storage: `/var/www/storage/mail/{eml,anhaenge}/` (www-data:www-data)
- Cron: `/etc/cron.d/ki-tool-mail-pull` (tick 5 Min)
- Capability: `CAP_MAIL` (Admin + Manager Default)
- Composer-Dependencies: `webklex/php-imap`, `symfony/mailer`, `symfony/mime`
- 15 MB `vendor/`-Ordner

## Inkonsistenzen / Limits

- **`MailAntwortService::pruefeStopWoerter` warnt nur** bei manuellem Versand (Versand geht trotzdem durch). Bei Auto-Versand wird hart verweigert.
- **Thread-Gruppierung** im Inbox-UI ist client-seitig per normalisiertem Betreff (Re:/Fwd:/WG: weg). Server-seitiges Threading per `in_reply_to`/`References`-Header wäre robuster (lässt sich nachrüsten), reicht aber für den 99%-Fall.
- HTML-Rendering im Detail nutzt eine **eigene Light-Sanitizing-Implementation** (Script/onEvent/javascript:/iframe-Stripping) plus Sandbox-Iframe ohne `allow-scripts`. Bei misstrauischen Mails (Spam-Kategorie) sollte der User trotzdem vorsichtig sein. Voller Sanitizer (HTMLPurifier) wäre noch sauberer.
- Anhang-Limit ist im EML-Upload-Endpoint 20 MB (statisch), Setting `mail_anhang_max_mb` ist nur informativ. Antwort-Anhang-Upload nutzt das Setting korrekt.

## Was Tom als nächstes braucht

1. **IONOS-Zugangsdaten für `pr@thoxan.com`** eintragen unter [/admin/settings?tab=mail](http://ai.thoxan-dev.de/admin/settings?tab=mail)
2. **Verbindungs-Test** mit beiden Buttons (IMAP + SMTP) durchspielen
3. **Erste Mail von außen schicken**, dann manuell IMAP-Pull triggern, klassifizieren, Antwort schreiben
4. Optional: Erste **Vorlagen** und **Regeln** händisch via SQL einfügen (UI dafür kommt Phase 2)
