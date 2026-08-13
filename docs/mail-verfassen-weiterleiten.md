# Mail verfassen & weiterleiten (15.07.2026)

Ergänzt das Mail-Tool um zwei Funktionen neben dem Antworten: **neue E-Mail verfassen** und
**bestehende Mail weiterleiten** — beide KI-gestützt im gelernten Stil.

## Bedienung

- **Verfassen** (Knopf oben im Mail-Tool): öffnet das Compose-Modal im Modus „neu".
- **Weiterleiten** (Icon ➜ im Mail-Detail): öffnet dasselbe Modal, vorbefüllt mit „Fwd: …",
  dem zitierten Original und dessen Anhängen (alle angehakt, einzeln abwählbar).

Im Modal:
- **An** mit Autovervollständigung aus bekannten Kontakten (LAM-Kontakte + bisherige Absender),
  freie Eingabe bleibt möglich. Cc/Bcc kommagetrennt.
- **KI-Entwurf**: Stichworte eingeben → „Entwurf" schreibt eine Mail (neu) bzw. einen kurzen
  Begleittext (weiterleiten) im gelernten Stil — **lokal** (datensouverän).
- **✨ mit Claude**: glättet den Text bewusst über die Cloud (Hybrid, wie beim Antworten).

## Architektur

Baut auf Vorhandenem auf: `MailAntwortService::sendeNeueMail()` konnte schon Empfänger,
Cc/Bcc, Anhänge und Versand über Exchange (XOAUTH2).

| Baustein | Datei |
|---|---|
| KI-Entwurf neue Mail / Weiterleiten-Begleittext | `MailKlassifikationService::entwerfeNeueMail()` / `entwerfeWeiterleitung()` |
| Weiterleiten-Versand (Zitat + Original-Anhänge) | `MailAntwortService::sendeWeiterleitung()` |
| Feinschliff (jetzt auch für freien Text, mail_id=0) | `MailKlassifikationService::verfeinereAntwort()` |
| Endpunkte | `api/v1/mail/{neue-mail-entwurf,weiterleiten-entwurf,weiterleiten,empfaenger-vorschlaege}.php` |
| Freier Versand | `api/v1/mail/mail-senden.php` (bestand schon, aus dem LAM) |
| Oberfläche | `views/mail/inbox.php` — Compose-Modal + Knöpfe „Verfassen"/„Weiterleiten" |

**Weiterleiten** = neue Mail mit Begleittext + „Ursprüngliche Nachricht"-Zitat + übernommenen
Original-Anhängen (aus `mail_anhaenge.pfad`, nichts wird neu hochgeladen). Fwd-Betreff wird nicht
verdoppelt.

## Sicherheit

- **Kein Postfach-Schreibzugriff.** Versand ist nach außen (SMTP), verändert das Exchange-Postfach
  nicht — die Nur-Lesen-Sperre (`darfSchreiben()`) bleibt unberührt.
- **Kein Auto-Versand.** Jede Mail geht erst auf Thomas' „Senden"-Klick raus.
- KI-Entwurf läuft lokal; nur „✨ mit Claude" nutzt die Cloud, bewusst pro Mail.

## Verifiziert

KI-Entwürfe (neu + Weiterleiten-Begleittext) laufen lokal und treffen den Stil. Fwd-Betreff,
Anhang-Übernahme (Pfad lesbar) und Anhang-Auswahl geprüft. Der echte SMTP-Versand nutzt den
bewährten `sendeNeueMail()`-Pfad; ein realer End-to-End-Versand steht noch aus (bewusst nicht
ungefragt eine echte Mail verschickt).
