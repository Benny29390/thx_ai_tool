# Neue Kundeninstallation — Schritt für Schritt

Diese Anleitung beschreibt, wie eine eigene Installation der Plattform für einen
Kunden auf einem frischen Server (Ubuntu/Debian) aufgesetzt wird. Alle Befehle
sind zum Kopieren. **Fett markierte Platzhalter** vorher ersetzen.

> Kürzel: **KUNDENSERVER** = der neue Server des Kunden · **STEUERSERVER** =
> Dein zentraler Server (dieser hier), auf dem der Lizenz-Schlüssel liegt.

---

## 0. Was Du vorher festlegst

| Platzhalter | Beispiel | Bedeutung |
|---|---|---|
| `DOMAIN` | `ki.kunde.de` | Adresse, unter der es laufen soll (DNS zeigt auf den Server) |
| `DB_PASS` | (langes Passwort) | Datenbank-Passwort (frei wählen) |
| `ADMIN_EMAIL` | `chef@kunde.de` | Login des ersten Admins |
| `ADMIN_PASS` | (Passwort) | Passwort des ersten Admins |
| `KUNDE` | `Kunde GmbH` | Name des Kunden (für die Lizenz) |
| `MODULE` | `chat,knowledge,artifacts,site_monitor` | Module, die der Kunde nutzen darf |

DNS: Vor dem Start einen A-Record von `DOMAIN` auf die IP des KUNDENSERVERS setzen.

---

## 1. Grundsoftware installieren  (KUNDENSERVER, als root)

```
sudo apt-get update
sudo apt-get install -y php php-cli php-mysql php-mbstring php-curl php-xml php-zip php-gd \
    mariadb-server apache2 composer git unzip curl
sudo a2enmod rewrite && sudo systemctl reload apache2
```

## 2. Datenbank anlegen  (KUNDENSERVER)

`DB_PASS` ersetzen:

```
sudo mysql -e "CREATE DATABASE ki_tool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ki_tool'@'localhost' IDENTIFIED BY 'DB_PASS';
GRANT ALL PRIVILEGES ON ki_tool.* TO 'ki_tool'@'localhost';
FLUSH PRIVILEGES;"
```

## 3. Nur-Lese-Zugang zum Code einrichten  (KUNDENSERVER)

Schlüssel erzeugen und anzeigen:

```
ssh-keygen -t ed25519 -f /root/.ssh/id_ed25519 -N ""
printf 'Host github.com\n  IdentityFile /root/.ssh/id_ed25519\n  IdentitiesOnly yes\n' >> /root/.ssh/config
cat /root/.ssh/id_ed25519.pub
```

Den ausgegebenen Schlüssel bei GitHub eintragen:
**Repo → Settings → Deploy keys → Add deploy key** — Titel z. B. „Kunde DOMAIN",
Schlüssel einfügen, **„Allow write access" NICHT ankreuzen** (nur lesen).

## 4. Installation ausführen  (KUNDENSERVER)

Code holen und Installer starten (`DB_PASS`, `DOMAIN`, `ADMIN_*` ersetzen):

```
sudo git clone git@github.com:Benny29390/thx_ai_tool.git /var/www
cd /var/www
sudo SKIP_CLONE=1 APP_DIR=/var/www \
     DB_PASS='DB_PASS' APP_URL='https://DOMAIN' \
     ADMIN_EMAIL='ADMIN_EMAIL' ADMIN_PASS='ADMIN_PASS' ADMIN_NAME='Administrator' \
     bash scripts/provision.sh
```

Der Installer legt Konfiguration (mit eigenem Verschlüsselungs-Schlüssel),
Datenbank-Tabellen und den Admin an — und bricht ab, falls schon eine
Installation existiert (überschreibt also nie etwas).

## 5. Webserver + HTTPS  (KUNDENSERVER)

```
sudo sed -e 's#__DOMAIN__#DOMAIN#g' -e 's#__APP_DIR__#/var/www#g' \
     /var/www/deploy/apache/vhost.template.conf > /etc/apache2/sites-available/ki-tool.conf
sudo a2ensite ki-tool && sudo systemctl reload apache2
sudo apt-get install -y certbot python3-certbot-apache
sudo certbot --apache -d DOMAIN
```

## 6. Automatische Updates einschalten  (KUNDENSERVER)

```
sudo sed 's#__APP_DIR__#/var/www#g' /var/www/deploy/cron/ki-tool-autoupdate \
     > /etc/cron.d/ki-tool-autoupdate
```

Ab jetzt sieht der Kunde neue Versionen unter **System → System-Update** und
spielt sie per Knopfdruck ein (mit automatischem Backup vorher).

## 7. Lizenz erzeugen und einspielen

**Auf dem STEUERSERVER** (dieser Server) die Lizenz signieren
(`KUNDE`, `MODULE` ersetzen):

```
php /var/www/scripts/license-sign.php \
    --installation='kunde-01' --customer='KUNDE' \
    --modules='MODULE' --out=/tmp/license.json
```

Die Datei `/tmp/license.json` auf den KUNDENSERVER kopieren nach
`/var/www/config/license.json` (z. B. per scp). Erst damit sind genau die
gekauften Module freigeschaltet.

## 8. Erscheinungsbild + Module  (im Browser)

Als Admin einloggen (`https://DOMAIN`) und:
- **Styleguide → Eigenes Branding**: Logo, Farbe, Name, Favicon setzen.
- **Einstellungen → Module**: Module ein-/ausschalten (innerhalb der Lizenz).
- **Einstellungen → KI-Modelle**: API-Schlüssel (OpenAI/Anthropic) eintragen.

## 9. Abschluss-Prüfung  (KUNDENSERVER)

```
php /var/www/scripts/doctor.php
```

Zeigt „OK", wenn Konfiguration, Datenbank, Verschlüsselung und Schreibrechte
stimmen. Fertig.

---

## Externe Zusatzmodule (nur wenn gebucht)

Manche Module brauchen zusätzliche Software auf dem KUNDENSERVER:

- **Transkription**: `ffmpeg`, `yt-dlp` und die Whisper-Umgebung
  (`scripts/installers/` bzw. `/opt/ki-tool-whisper`).
- **Wissen (Qdrant-Variante)**: Qdrant-Dienst (`/opt/qdrant`).

Sind diese Module aus, wird davon nichts benötigt. `doctor.php` weist auf
fehlende Werkzeuge hin.

## Wenn etwas klemmt

- **Seite lädt nicht / 500**: `sudo tail -f /var/log/apache2/ki-tool-error.log`
- **„Modul nicht aktiv"**: Modul unter Einstellungen → Module bzw. per Lizenz freischalten.
- **Update hängt**: Wartungsmeldung im Browser ist normal für 1–2 Minuten;
  Protokoll in `/var/www/storage/logs/update.log`.
