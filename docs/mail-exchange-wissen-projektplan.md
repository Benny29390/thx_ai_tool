# Projektplan: Exchange-Postfach anbinden + Mails ins Wissen

**Stand:** 14.07.2026 · **Auftraggeber:** Thomas · **Ziel:** Microsoft-365-Postfach an
`/mail` anbinden und intelligent verarbeiten — „wie Outlook, nur lokal KI-gestützt".

Ergänzt [mail-modul-status.md](mail-modul-status.md) (Bestand) und
[rag-optimierung.md](rag-optimierung.md) (Lehren fürs Retrieval).

## Entscheidungen (mit Thomas abgestimmt)

| Frage | Entscheidung |
|---|---|
| Exchange-Typ | **Microsoft 365 (Cloud)** → OAuth2 zwingend, Passwort-Anmeldung ist von Microsoft abgeschaltet |
| Tenant-Admin | **Thomas hat volle Admin-Rechte** → Phase 0 ist nicht blockiert |
| Umfang Wissen | **Ordnerweise auswählbar** (revidiert 14.07.). Start mit einzelnen Ordnern statt komplettem Postfach |
| Sichtbarkeit | **Privat, ohne Admin-Ausnahme** — auch Admins (Benny) sehen Thomas' Mails nicht |
| Betriebsart | **Nur-Lesen** — das Postfach wird nicht verändert (Thomas arbeitet parallel in Outlook) |
| Prioritäten | Antworten vorschlagen · Zusammenfassen & Priorisieren · Aufgaben/Termine erkennen · Wissen abrufbar |

## Ausgangslage

**Schon da** (siehe [mail-modul-status.md](mail-modul-status.md)): 4-Spalten-Inbox wie Outlook,
IMAP-Polling mit Dubletten-Schutz, MIME-Parser, KI-Klassifikation, Antwort-Editor mit
Vorlagen/Anhängen, Thread-Gruppierung, LAM-Verknüpfung, Cron, Rechte (`CAP_MAIL`).

**Zwei Lücken:**
1. Anmeldung ist auf Benutzername/Passwort verdrahtet (`MailImapService`: `'authentication' => null`).
2. Mails haben **keinerlei** Verbindung zur Wissensdatenbank.

## ⚠️ Blocker 1: Es gibt keine private Sichtbarkeit

`knowledge_documents` kennt als einzige Zugriffsgrenze `customer_id`. Es gibt **keine
Besitzer-Spalte und keine Sichtbarkeitsstufe**. Eingelesene Mails wären damit für jeden
abrufbar, der Zugriff auf denselben Kunden hat — inklusive Personal-, Rechts- und
Gehaltsthemen.

**Konsequenz: Phase 2 ist Vorbedingung für Phase 3. Nicht verhandelbar.**

### Was „privat" hier bedeutet — und was nicht

- **Zugesagt:** Der Sichtbarkeitsfilter bekommt **keine Admin-Ausnahme**. Das weicht bewusst
  von der sonstigen Regel „Admin hat immer alle Rechte" (CLAUDE.md) ab. Admins sehen private
  Mail-Dokumente weder im Chat, noch im Wissen, noch in der Oberfläche.
- **Nicht zusagbar:** Wer Server- und Datenbankzugriff hat, kommt an der Anwendung vorbei an
  die Inhalte. Das ist keine schließbare Lücke, sondern folgt daraus, wer die Maschine betreibt.
- **Der Zielkonflikt, sauber benannt:** „Die KI durchsucht meine Mails" und „der Server-Admin
  kann sie technisch nicht lesen" schließen sich aus — Volltext und Vektoren müssen dafür
  lesbar auf dem Server liegen. Eine Verschlüsselung, zu der nur Thomas den Schlüssel hat,
  würde genau die gewünschte Funktion zerstören.
- **Erreichbar:** anwendungsseitig dicht für alle außer dem Besitzer, plus Protokollierung
  der Zugriffe im Audit-Log.

## ⚠️ Blocker 2: Der Abholer verändert das Postfach

`MailImapService` verschiebt abgeholte Mails nach `INBOX.Verarbeitet` bzw. `INBOX.Fehler`
(Zeilen 133–146) und holt zudem **nur einen einzigen Ordner** (`imap_folder_inbox`).

Das war für ein reines Verarbeitungs-Postfach (`pr@thoxan.com`) gedacht. Bei Thomas' Postfach,
in dem er täglich mit Outlook arbeitet, würde es die Mails **während der Arbeit wegsortieren**.

**Konsequenz: Neuer Nur-Lesen-Modus pro Konto (Phase 1).** Nichts verschieben, nichts als
gelesen markieren; der Bearbeitungsstand wird ausschließlich in unserer DB geführt (über die
IMAP-UID je Ordner).

## Phase 0 — Voraussetzung: App-Registrierung in Entra ID (Benny + Tenant-Admin)

Ohne das geht gar nichts. Microsoft verlangt für IMAP/SMTP-Zugriff auf Microsoft 365 eine
registrierte Anwendung.

- App-Registrierung in Entra ID (früher Azure AD) im Thoxan-Tenant
- Benötigte Berechtigungen (delegiert): `IMAP.AccessAsUser.All`, `SMTP.Send`,
  `offline_access` (für den Refresh-Token), `openid`, `profile`
- Redirect-URI: `https://ai.thoxan-dev.de/api/v1/mail/oauth-callback`
- Ergebnis, das wir brauchen: **Tenant-ID, Client-ID, Client-Secret**
- Admin-Zustimmung („Admin Consent") für den Tenant erteilen

> Wichtig: Es gibt zwei Wege — IMAP+OAuth2 oder die Microsoft-Graph-API. **Wir nehmen
> IMAP+OAuth2**, weil damit die komplette bestehende Abhol-Pipeline (Dubletten, MIME,
> Anhänge, Ordner) unverändert weiterläuft. Graph bleibt als spätere Ausbaustufe offen,
> wenn wir Kalender und Aufgaben direkt anbinden wollen.

## Phase 1 — Exchange-Anbindung (IMAP + OAuth2) ✅ GEBAUT (14.07.2026)

**Was Thomas jetzt tun kann:** `/admin/settings?tab=smtp` → Konto anlegen → Anmeldeart
**„Microsoft 365 (Exchange Online)"** → die drei Werte eintragen → **Speichern** →
**„Mit Microsoft verbinden"** → danach **„Ordner"** je Konto auswählen → **„test IMAP"**.

| Baustein | Datei |
|---|---|
| OAuth2 (Anmeldung, Token-Tausch, automatischer Refresh) | `services/MailOAuthService.php` (neu) |
| IMAP-Verbindung + SMTP-Transport (eine Stelle für beide Anmeldearten) | `services/MailKontoService.php` |
| Abholung mit Nur-Lesen + Ordner-Auswahl | `services/MailImapService.php` |
| Versand über XOAUTH2 | `services/MailAntwortService.php` |
| Anmeldung starten / Rückleitung / Ordner-Auswahl | `api/v1/mail/oauth-start.php`, `oauth-callback.php`, `ordner-auswahl.php` (neu) |
| Oberfläche (Anmeldeart, Microsoft-Felder, Nur-Lesen, Ordnerbaum) | `views/admin/settings/_tab_smtp.php` |

**Getestet (ohne Microsoft-Zugang, was ohne ihn testbar ist):** Client-Secret landet
AES-verschlüsselt (`enc:v1:`) in der DB; Authorize-URL enthält die richtigen
Outlook-Scopes **und** `offline_access`; Verbindungstest ohne Verbindung liefert eine
verständliche Meldung; „Ins Wissen ohne Abholen" wird automatisch korrigiert; Konto-Löschung
räumt die Ordner-Auswahl mit ab. **Bestandskonto `pr@thoxan.com` unverändert**
(`auth_typ=passwort`, keine Ordner-Auswahl → Fallback auf INBOX wie bisher).

**Noch nicht möglich zu testen:** der echte Anmelde-Durchlauf gegen Microsoft und ein echter
Abruf — dafür braucht es Thomas' Zugangsdaten in der Oberfläche.

### Ursprünglicher Bauplan (zur Nachvollziehbarkeit)

- **Schema** `mail_konten` erweitern: `auth_typ` ENUM(`passwort`,`oauth2`), `oauth_tenant_id`,
  `oauth_client_id`, `oauth_client_secret_enc`, `oauth_refresh_token_enc`,
  `oauth_access_token_enc`, `oauth_token_expires`
  (Secrets über `Core\Crypto` verschlüsselt, wie die bestehenden Passwörter)
- **Neuer Service** `MailOAuthService`: Autorisierungs-Flow (einmalig im Browser), Token-Tausch,
  automatischer Refresh vor Ablauf
- **`MailImapService`**: bei `auth_typ=oauth2` → `'authentication' => 'oauth'`, Access-Token
  statt Passwort. Die Bibliothek (`webklex/php-imap` 6.2) kann XOAUTH2 bereits.
- **Versand**: Symfony Mailer mit `XOAuth2Authenticator` statt Passwort-Auth
- **UI**: In den Konto-Einstellungen Auswahl „Passwort" vs. „Microsoft 365", Button
  „Mit Microsoft verbinden" + Verbindungstest

### Nur-Lesen-Modus (Pflicht für Thomas' Postfach)

- `mail_konten`: neue Spalte `nur_lesen` TINYINT(1) DEFAULT 1
- Bei `nur_lesen=1`: **kein** Verschieben, **kein** Setzen des Gelesen-Flags auf dem Server.
  Bearbeitungsstand nur in `mail_nachrichten` (IMAP-UID + Ordner als Schlüssel).
- Das bestehende Verhalten (Verschieben) bleibt für Verarbeitungs-Postfächer wie
  `pr@thoxan.com` erhalten — es wird nicht abgeschafft, nur abschaltbar.

### Ordner-Auswahl (statt „nur INBOX")

- **Neue Tabelle** `mail_konten_ordner`: `konto_id`, `ordner_pfad`, `abholen` TINYINT(1),
  `ins_wissen` TINYINT(1), `rekursiv` TINYINT(1)
- **Zwei getrennte Schalter je Ordner** — bewusst nicht einer:
  - `abholen` = Ordner erscheint in `/mail`, lesbar und beantwortbar
  - `ins_wissen` = Inhalt wird zusätzlich in die Wissensdatenbank übernommen

  Ohne diese Trennung müsste Thomas sich je Ordner zwischen „gar nicht sehen" und „für die KI
  durchsuchbar" entscheiden. Beispiel: Ordner „Personal" → abholen ja, ins Wissen nein.
- `rekursiv=1` übernimmt alle Unterordner eines Hauptordners mit
- **UI**: Ordnerbaum mit Haken, gespeist aus `Client::getFolders(hierarchical: true)` — die
  Bibliothek liefert die Hierarchie fertig aus.

**Ergebnis:** Postfach hängt dran, ausgewählte Ordner laufen in die bestehende Inbox, das
Postfach in Outlook bleibt unangetastet.

## Phase 2 — Private Sichtbarkeit in der Wissensdatenbank ✅ ERLEDIGT (14.07.2026)

**Schema** (`core/App.php`, Auto-Migration):
- `knowledge_documents.owner_user_id` INT NULL
- `knowledge_documents.visibility` ENUM(`privat`,`team`,`kunde`) NOT NULL DEFAULT `kunde`
- Index `idx_visibility (visibility, owner_user_id)`
- Default `kunde` → **alle Bestandsdokumente verhalten sich unverändert**

**Die zentrale Stelle:** `KnowledgeRetrievalHybridTrait::loadChunkDetails()`. Dort laufen alle
drei Such-Beine zusammen (dense/Qdrant, sparse/Volltext, graph/Entitäten) — Inhalte werden
ausschließlich dort aus MariaDB nachgeladen. Der Filter an dieser Stelle deckt deshalb auch
Qdrant-Treffer ab, **ohne dass der Vektor-Index die Sichtbarkeit kennen muss**. Zusätzlich
filtern `sparseSearch()` und `graphSearch()` selbst (doppelter Boden).

**Abgedichtete Stellen:**

| Stelle | Datei |
|---|---|
| Hybrid-Retrieval (alle 3 Beine + Backstop) | `services/KnowledgeRetrievalHybridTrait.php` |
| Ganzdokument-Lader im Chat | `api/v1/chat-stream.php` |
| Reporting-Übersicht im Chat | `api/v1/chat-stream.php` |
| Wissens-Liste + Einzelabruf (`/wissen`) | `services/KnowledgeService.php` |
| Wissens-Graph | `api/v1/knowledge/graph-global.php` |
| **Kunden-Portal** (hätte Mail-Titel an Kunden ausgeliefert!) | `services/CustomerPortalService.php` |
| Steckbrief-Vorschläge (geteiltes Artefakt → `setViewer(null)`) | `services/SteckbriefSuggestionService.php` |

**Zwei Regeln, die nicht gekippt werden dürfen:**
1. **Keine Admin-Ausnahme.** Der Filter fragt nicht `Auth::isAdmin()`. Sonst wäre die Zusage
   „auch ein Administrator sieht meine Mails nicht" wertlos.
2. **Fail-closed.** Ist kein Betrachter bekannt (Cron/CLI), bleiben private Dokumente außen vor.

**Bestandener Test** (`Besitzer #1 Benny` vs. `Nutzer #2 Thomas`, beide Admin):
Volltext-Suche, untergeschobene Chunk-ID am Backstop, Wissens-Liste, Einzelabruf per ID —
der jeweils andere findet **nichts**; der Besitzer findet alles; 13.673 normale Dokumente
bleiben unverändert sichtbar.

**Optionale Nachbesserung (kein Leck, nur Effizienz):** Private Chunks belegen im Qdrant-Bein
noch Kandidatenplätze, bevor der Backstop sie verwirft. Ein `visibility`-Feld im Qdrant-Payload
plus Filter würde das sparen. Erst sinnvoll, wenn viele private Dokumente existieren.

## Phase 3 — Postfach ins Wissen

- `knowledge_documents.source_type` ist ein **ENUM** → `ALTER TABLE` um `mail` erweitern
  (bekannte Falle, siehe CLAUDE.md)
- **Neuer Service** `MailKnowledgeSync`: Mail → `KnowledgeIngestService` (derselbe Weg, den
  das Transkriptions-Modul schon nutzt: Volltext + Embeddings + Entitäten)
- **Thread statt Einzelmail**: Ein Dokument = ein Gesprächsfaden (Betreff-normalisiert bzw.
  über `In-Reply-To`/`References`), nicht eine Mail.
  **Begründung:** Laut [rag-optimierung.md](rag-optimierung.md) machen CRM-Mini-Chunks schon
  heute ~48 % der Vektor-DB aus und verdrängen gute Treffer. Zehntausende Kurz-Mails
  („Danke!", „Passt.") würden denselben Fehler wiederholen — nur in größerem Maßstab.
- **Mindestlänge + Rauschfilter**: Automatische Benachrichtigungen, Newsletter (`List-Id`),
  Signaturen und Zitat-Ketten vor dem Einlesen abschneiden
- **Anhänge** über den bestehenden `DocumentProcessor` (PDF/DOCX) mitlesen
- **Backfill-Skript** für den Bestand + laufender Sync im bestehenden `mail-pull-cron`
- **Nur Ordner mit `ins_wissen=1`** werden übernommen (siehe Ordner-Auswahl in Phase 1)
- Alle Mail-Dokumente: `visibility='privat'`, `owner_user_id=<Thomas>`

## Phase 4 — Intelligente Verarbeitung (Deine vier Prioritäten)

| Priorität | Was gebaut wird | Basis |
|---|---|---|
| **Antworten vorschlagen** | Entwurf zieht Kunden-Kontext, Stilprofil (Autor-Artefakt) und Mail-Historie per RAG heran, statt nur die aktuelle Mail zu sehen | Grundgerüst existiert (`MailAntwortService`) |
| **Zusammenfassen & Priorisieren** | Thread-Zusammenfassung auf Knopfdruck + Tages-Digest „Was ist heute wirklich wichtig?" | neu |
| **Aufgaben & Termine erkennen** | Aus Mails Fristen, To-dos und Maßnahmen ableiten → Tagesplaner / Asana / LAM | LAM-Hooks existieren, Muster übertragbar |
| **Wissen abrufbar** | „Was hatten wir mit Kunde X vereinbart?" im Chat beantwortbar | fällt aus Phase 3 automatisch ab |

**Sicherheitsregel bleibt:** Mensch immer dazwischen. Auto-Versand bleibt aus (Master-Switch
`mail_auto_versand_global_aktiv=0`), wie mit Thomas festgelegt.

## Reihenfolge und Abhängigkeiten

```
Phase 0 (Benny: Entra ID)  ──►  Phase 1 (Anbindung)  ──►  Phase 4 (Ausbau)
                                        │
                    Phase 2 (private Sichtbarkeit)  ──►  Phase 3 (Postfach ins Wissen)
```

Phase 2 kann **parallel** zu Phase 0/1 gebaut werden — sie hängt nicht an Microsoft.
Das ist der sinnvollste Startpunkt, solange die App-Registrierung läuft.

## Offene Punkte

- ~~Wer hat Tenant-Admin-Rechte?~~ **Geklärt: Thomas hat volle Admin-Rechte.**
- Welche Ordner sollen zum Start abgeholt werden, welche davon ins Wissen?
  (Entscheidung fällt in der Ordner-Auswahl, nicht vorab im Code)
- Sollen **gesendete** Mails auch ins Wissen? (Empfehlung: ja — dort steht, was *wir* zugesagt haben)
- Aufbewahrung: Wie lange bleiben Mail-Dokumente im Wissen? (Empfehlung: mit dem Postfach synchron,
  gelöschte Mail → Dokument deaktivieren)
