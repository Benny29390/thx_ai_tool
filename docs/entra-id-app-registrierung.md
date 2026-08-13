# Anleitung: App-Registrierung in Entra ID (für Thomas)

Damit das KI-Tool Dein Microsoft-365-Postfach lesen und darüber versenden darf, braucht es
eine registrierte Anwendung in Eurem Microsoft-Konto. Microsoft hat die Anmeldung per
Benutzername/Passwort abgeschaltet — das hier ist der Ersatz.

**Was am Ende rauskommen muss:** drei Werte (Verzeichnis-ID, Anwendungs-ID, Geheimnis).
Die trägst Du dann im Tool ein. Dauer: etwa 15 Minuten.

---

## Schritt 1 — Portal öffnen

Gehe auf **https://entra.microsoft.com** und melde Dich mit Deinem Admin-Konto an.

Links im Menü: **Identität** → **Anwendungen** → **App-Registrierungen**.

## Schritt 2 — Neue Registrierung anlegen

Oben auf **„+ Neue Registrierung"** klicken.

| Feld | Was Du einträgst |
|---|---|
| **Name** | `Thoxan KI-Tool Mail` |
| **Unterstützte Kontotypen** | „Nur Konten in diesem Organisationsverzeichnis" (Single Tenant) |
| **Umleitungs-URI** | Plattform **„Web"** auswählen, dann diese Adresse eintragen: |

```
https://ai.thoxan-dev.de/api/v1/mail/oauth-callback
```

Auf **„Registrieren"** klicken.

## Schritt 3 — Die ersten beiden Werte notieren

Du landest auf der Übersichtsseite der neuen App. Dort stehen oben:

- **Anwendungs-ID (Client)** → das ist die **Client-ID** ✏️ notieren
- **Verzeichnis-ID (Mandant)** → das ist die **Tenant-ID** ✏️ notieren

## Schritt 4 — Berechtigungen erteilen

Links im Menü: **API-Berechtigungen** → **„+ Berechtigung hinzufügen"**.

1. **Microsoft Graph** wählen
2. **Delegierte Berechtigungen** wählen (NICHT „Anwendungsberechtigungen")
3. Diese vier suchen und ankreuzen:

| Berechtigung | Wofür |
|---|---|
| `IMAP.AccessAsUser.All` | Mails lesen |
| `SMTP.Send` | Mails versenden |
| `offline_access` | Damit die Verbindung nicht alle 60 Minuten abreißt |
| `openid` | Anmeldung |

4. **„Berechtigungen hinzufügen"** klicken
5. Wichtig: Danach auf **„Administratorzustimmung für Thoxan erteilen"** klicken
   (der Button darüber) und bestätigen. Alle vier Zeilen müssen danach einen **grünen Haken**
   in der Spalte „Status" haben.

## Schritt 5 — Geheimnis erzeugen

Links im Menü: **Zertifikate & Geheimnisse** → Reiter **„Geheime Clientschlüssel"** →
**„+ Neuer geheimer Clientschlüssel"**.

| Feld | Wert |
|---|---|
| Beschreibung | `KI-Tool Mail` |
| Gültig bis | 24 Monate |

Nach dem Klick auf „Hinzufügen" erscheint eine Tabelle mit einer Spalte **„Wert"**.

> ⚠️ **Diesen Wert sofort kopieren.** Er wird nur **einmal** angezeigt. Wenn Du die Seite
> verlässt, ist er weg und Du musst ein neues Geheimnis erzeugen. Die Spalte „Geheime
> Schlüssel-ID" daneben ist **nicht** der richtige Wert — Du brauchst die Spalte **„Wert"**.

✏️ notieren als **Client-Secret**.

## Schritt 6 — Prüfen, ob IMAP für Dein Postfach aktiv ist

Microsoft schaltet IMAP bei manchen Konten ab. Prüfen unter
**https://admin.microsoft.com** → **Benutzer** → **Aktive Benutzer** → Dein Konto anklicken →
Reiter **„E-Mail"** → **„E-Mail-Apps verwalten"**.

Dort muss **IMAP** einen Haken haben. Falls nicht: Haken setzen und speichern.

## Das brauche ich von Dir

Drei Werte:

```
Tenant-ID (Verzeichnis-ID):   ________________________________
Client-ID (Anwendungs-ID):    ________________________________
Client-Secret (Spalte „Wert"): ________________________________
```

Das Geheimnis ist ein Passwort-Äquivalent. **Bitte nicht per Mail oder Chat schicken** —
trage es direkt im Tool ein, sobald ich Dir die Eingabemaske gebaut habe. Bis dahin sicher
verwahren (Passwort-Manager).

## Falls etwas klemmt

| Problem | Ursache |
|---|---|
| „Administratorzustimmung" ist ausgegraut | Du bist nicht als Admin angemeldet |
| Nach dem Verbinden: „AUTHENTICATE failed" | IMAP für das Postfach nicht freigeschaltet (Schritt 6) |
| Verbindung reißt nach einer Stunde ab | `offline_access` fehlt (Schritt 4) |
| Secret funktioniert nicht | Vermutlich die „Schlüssel-ID" statt der Spalte „Wert" kopiert |
