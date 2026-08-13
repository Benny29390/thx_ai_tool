# Mail-Lernsystem: Wie die KI Thomas' Stil lernt

Stand: 14.07.2026 · Gehört zu [mail-exchange-wissen-projektplan.md](mail-exchange-wissen-projektplan.md)
(Phase 4) und ergänzt [mail-modul-status.md](mail-modul-status.md).

## Der Grundgedanke

**Es wird kein Modell trainiert.** Kein Fine-Tuning. Das wäre teuer, träge und für diesen
Zweck das falsche Werkzeug.

Stattdessen zwei Hebel:

1. **Der KI das richtige Material vorlegen** — Thomas' Schreibstil, aus seinen echten Mails
   gelernt, plus verbindliche Regeln.
2. **Aus Thomas' Korrekturen lernen** — jedes Mal, wenn er einen KI-Entwurf ändert, sagt der
   Unterschied wörtlich, was die KI falsch gemacht hat.

**Kernprinzip: review-to-activate.** Jede abgeleitete Regel ist zuerst nur ein *Vorschlag*.
Erst wenn Thomas sie freigibt, wirkt sie. Ohne diese Bremse würde ein einziger Ausreißer
(eine untypische Korrektur) das System dauerhaft verbiegen. Dasselbe Muster läuft bereits im
Tagesplaner (`planner_learned_rules`) — bewährt, nicht neu erfunden.

## Die drei Bausteine

### 1. Stil-Ernte — woher das Material kommt

**Das Problem:** Thomas hat keinen gefüllten „Gesendet"-Ordner (0 Mails). Er sortiert seine
Antworten in Themenordner, zwischen die eingegangenen Mails. Seine geschriebenen Mails liegen
also über 2000 Ordner verstreut.

**Die Lösung:** Der gemeinsame Nenner ist der **Absender**. Alles, was von ihm kommt, hat er
geschrieben. Der IMAP-Server sucht das selbst (`SEARCH FROM …`) — es muss nichts
heruntergeladen und danach gefiltert werden.

| Detail | Warum |
|---|---|
| **Zwei Absender-Adressen** (`mail_konten.eigene_adressen`) | Thomas verschickt aus demselben Postfach unter `thomas.kilian@` **und** `info@`. Wer nur die Konto-Adresse durchsucht, findet die Hälfte nicht. |
| **Nur Ordner aus dem Katalog** mit Mails, größte zuerst, max. 40 | Der erste Versuch lief in den Timeout, weil er hunderte (meist leere) Unterordner einzeln beim Server erfragte. |
| **Zitate abschneiden** (`nurEigenerText`) | In einer Antwortmail macht der zitierte Text der Gegenseite oft 80 % aus. Ohne Filter hätte die KI **den Stil der anderen Person gelernt**. Es wird beim *frühesten* Zitat-Marker geschnitten, nicht beim letzten. |
| **Plausibilitätsprüfung** (`wirkichEigen`) | Ein Text, der mit „Hallo Thomas," beginnt, hat er nicht geschrieben. Doppelte Sicherung gegen durchgerutschte Zitate. |
| **Die Mailtexte werden verworfen** | Nur das abgeleitete Profil bleibt gespeichert, nicht die Mails. |

**Das Postfach wird nicht verändert:** `leaveUnread()`, nichts verschoben, nichts gelöscht.

### 2. Stilprofil — wie Thomas schreibt

Ein LLM liest bis zu 40 seiner echten Mails und beschreibt: Anrede (duzt/siezt, wovon hängt es
ab), Tonalität, Aufbau, typische Formulierungen, Grußformel, **und was er nie tut**.

Ergebnis in `mail_stilprofil` (ein aktives Profil je Konto, alte bleiben als Historie).

### 3. Regeln — was verbindlich gilt

Das Stilprofil *beschreibt* („Thomas schreibt knapp"). Regeln *schreiben vor* („Halte Antworten
unter 150 Wörtern"). Beides wird gebraucht.

Zwei Quellen:

| Quelle | Wann | Wo |
|---|---|---|
| `stilanalyse` | beim Stil-Lauf, aus den echten Mails | `MailLernService::regelnAusStil()` |
| `korrektur` | wenn Thomas einen KI-Entwurf **ändert** | `MailLernService::lerneAusKorrekturen()` |

Die Korrektur-Schleife ist die eigentliche Lernmaschine. `mail_antworten` hält bereits **beides**:
`ki_vorschlag` (was die KI schrieb) und `finaler_text` (was Thomas abschickte). Ein LLM
vergleicht beide und benennt, was zu ändern ist. Unveränderte Entwürfe werden übersprungen —
wer einen Entwurf unverändert abschickt, bestätigt ihn; daraus gibt es nichts zu lernen.

Dubletten werden per Ähnlichkeitsvergleich (>85 %) abgefangen, damit dieselbe Regel nicht
zwanzigmal vorgeschlagen wird.

## Wo das Gelernte wirkt

`MailLernService::promptBlock()` baut den Block, der jedem Antwort-Prompt vorangestellt wird:

```
=== SO SCHREIBT THOMAS (aus seinen echten Mails gelernt) ===
<Stilprofil>

=== VERBINDLICHE REGELN (von Thomas freigegeben) ===
- <nur AKTIVE Regeln, max. 25>
Diese Regeln haben Vorrang vor Deinen eigenen Konventionen.
```

Eingehängt in `MailKlassifikationService::klassifiziereMail()` — dort entsteht die
`vorgeschlagene_antwort`.

**Ist nichts gelernt oder freigegeben, kommt ein leerer String zurück.** Die Klassifikation
arbeitet dann exakt wie vorher. Kein Risiko für Bestandskonten wie `pr@thoxan.com`.

## Bedienung

**`/admin/settings?tab=mail` → Karte „Stil & gelernte Regeln"**

| Element | Funktion |
|---|---|
| Postfach-Auswahl | Stil und Regeln hängen immer an EINEM Postfach |
| **Stil neu lernen** | startet den Hintergrund-Lauf (Ernte → Profil → Regeln), dauert Minuten |
| **Aus Korrekturen lernen** | wertet die editierten Antworten aus |
| Dein Schreibstil | das Profil, aufklappbar |
| Vorschläge | Regeln mit **Freigeben** / **Verwerfen** — Text ist vorher editierbar |
| Aktive Regeln | was tatsächlich wirkt, mit **Abschalten** |

Welche Ordner gelernt werden dürfen: Tab „E-Mail" → **Ordner** → Spalte **Stil lernen**.
Der Schalter ist **unabhängig von „Abholen"** — aus einem Ordner darf gelernt werden, ohne
dass seine Mails im Posteingang landen.

## Dateien

| Datei | Aufgabe |
|---|---|
| `services/MailStilService.php` | Ernte der eigenen Mails + Stilprofil |
| `services/MailLernService.php` | Regeln (ableiten, freigeben, in den Prompt bauen) |
| `scripts/mail-stil-lernen.php` | Hintergrund-Lauf: Ernte → Profil → Regeln |
| `scripts/mail-lernschleife.php` | Korrekturen auswerten (für Cron; **noch nicht eingerichtet**) |
| `api/v1/mail/lernen.php` | Status, Start, Freigabe |
| `views/admin/settings/_tab_mail.php` | Karte „Stil & gelernte Regeln" |
| `services/MailKlassifikationService.php` | **hier wird das Gelernte wirksam** |

## Datenmodell

| Tabelle | Inhalt |
|---|---|
| `mail_stilprofil` | `konto_id, profil_text, basis_anzahl, beispiele, aktiv` |
| `mail_gelernte_regeln` | `konto_id, regel_text, begruendung, beispiel, kategorie, quelle(stilanalyse\|korrektur\|manuell), status(vorschlag\|aktiv\|verworfen), basis_antwort_id` |
| `mail_konten.stil_status` | `leer\|laeuft\|fertig\|fehler` + `stil_meldung`, `stil_am` |
| `mail_antworten.gelernt_am` | verhindert, dass dieselbe Korrektur zweimal ausgewertet wird |
| `mail_konten_ordner.stil_lernen` | welche Ordner gelernt werden dürfen |

## Sicherheit

- **Das Exchange-Postfach wird NIE verändert.** Kein Löschen, kein Verschieben, kein Anlegen
  von Ordnern, kein Setzen des Gelesen-Flags. Sortiert wird ausschließlich in Outlook.
- Die Sperre ist **strukturell**, nicht per Häkchen: `MailImapService::darfSchreiben()` gibt für
  OAuth2-Konten (= Microsoft 365) **immer** `false` zurück, unabhängig von jeder Einstellung.
  `MailKontoService::speichereKonto()` erzwingt zusätzlich `nur_lesen=1` für solche Konten —
  ein versehentlich abgehakter Schalter kann die Sperre nicht aushebeln.
- **Kein Auto-Versand.** Der Master-Schalter bleibt aus; die KI schlägt vor, Thomas gibt frei.
- Regeln wirken **nur nach Freigabe**.

## Echte Umlaute (Falle vom 15.07.2026)

Die erste Fassung der Lern-Prompts war selbst in `ae/oe/ue` geschrieben (aus übertriebener
Encoding-Vorsicht beim Prompt-Schreiben). Das Modell hat den Stil gespiegelt und **alle Regeln
in Ersatzschreibweise ausgegeben** — direkt gegen die Projektregel „echte Umlaute".

Fix: Die Konstante `MailStilService::UMLAUT_REGEL` steht jetzt in **jedem** Lern-Prompt
(Stilprofil, Regeln aus Stil, Regeln aus Korrekturen) und weist echte Umlaute + große
Höflichkeitsformen ausdrücklich an. Die Prompt-Anweisungstexte selbst wurden auf echte Umlaute
umgestellt, damit das Beispiel nicht gegen die Anweisung arbeitet.

**Lehre:** Ein Prompt lehrt auch durch seine eigene Schreibweise, nicht nur durch seine
Anweisungen. Prompts für deutschsprachige Ausgaben immer mit echten Umlauten schreiben.

## Hybrid: lokales Modell + Cloud auf Knopfdruck (15.07.2026)

Auf Thomas' Wunsch (Datenschutz für Mail-Inhalte) läuft die Verarbeitung standardmäßig lokal:

| Aufgabe | Modell | Warum |
|---|---|---|
| Wissens-Zugriff (RAG) | **lokal** (bge-m3 + Qdrant + bge-reranker) | war schon lokal — kein Cloud-LLM beteiligt |
| Klassifikation + Erstentwurf | **lokal** (`mail_entwurf_modell`, Default `qwen2.5:32b`) | Mail-Inhalt verlässt den Server nicht |
| Feinschliff einzelner Mails | **Cloud** (Claude Opus 4.7) — Knopf „✨ mit Claude" | beste Qualität, bewusst pro Mail |

**A/B-Vergleich, der die Entscheidung getragen hat** (dieselbe Mail, mit Thomas' Stilprofil):

| Modell | Ort | Zeit | Befund |
|---|---|---|---|
| Claude Opus 4.7 | Cloud | 6,7 s | am natürlichsten |
| gpt-oss:20b | lokal | 24 s | sauber, brauchbar, etwas behördlicher |
| qwen2.5:32b | lokal | 57 s | bester Stiltreffer, aber wendet Regeln zu mechanisch an (Emoji-/„Ansonsten"-Übertreibung) |

Alle drei waren **brauchbar** — kein lokales Modell produzierte Müll. Das war die tragende
Erkenntnis: lokal ist gut genug für den Erstentwurf, Cloud bleibt für den Feinschliff.

**Umschaltbar** unter `/admin/settings?tab=mail` → „Antwort-Erstentwurf: Modell".

Beteiligte Stellen:
- `MailKlassifikationService::baueEntwurfsModell()` — liest `mail_entwurf_modell`, lokal bevorzugt
- `MailKlassifikationService::verfeinereAntwort()` — Cloud-Feinschliff mit Stilprofil + Regeln
- `api/v1/mail/antwort-verfeinern.php` + Knopf „✨ mit Claude" in `views/mail/inbox.php`

## Offen

- Cron für die nächtliche Korrektur-Auswertung (`scripts/mail-lernschleife.php`) — Skript ist
  fertig, der Cron-Eintrag braucht noch Thomas' ausdrückliche Freigabe.
- Anbindung des Antwort-Editors an die Regeln beim *manuellen* Nachschärfen („zu förmlich",
  „kürzer") — bisher wirkt das Gelernte nur beim automatischen Erstentwurf.
