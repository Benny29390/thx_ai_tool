# KI Text Tool — Plattform

KI-gestütztes Text- und Wissenswerkzeug (Chat, Wissensdatenbank, Artefakte,
LAM, CRM, Mail, u. a.) als verteilbares White-Label-Produkt. Eine Installation
pro Kunde, zentrale Updates über dieses Repo, Module per Lizenz freischaltbar.

- PHP 8.1+, MariaDB/MySQL, Apache — kein Build-Step.
- Release-Branch: `main`. Updates: `scripts/update.php` (backup-first, kein Datenverlust).

## Installation

**Vollständige, idiotensichere Anleitung: [docs/kunden-installation.md](docs/kunden-installation.md)**
(in der laufenden App auch unter **System → Kunden-Installation**).

### Empfohlen: interaktiver Installer

`scripts/install-server.sh` auf einen frischen Ubuntu/Debian-Server kopieren und starten:

```bash
sudo bash install-server.sh
```

Er fragt alles ab und richtet Systempakete, Code, Datenbank, Konfiguration,
Apache, HTTPS und den Update-Cron ein. Zwei geführte Handgriffe bleiben:
Deploy-Key bei GitHub eintragen (der Installer zeigt ihn) und die Lizenz einspielen.

### Oder manuell

Kurzfassung auf einem frischen Ubuntu/Debian-Server:

```bash
# 1. Grundsoftware
sudo apt-get update && sudo apt-get install -y php php-cli php-mysql php-mbstring \
    php-curl php-xml php-zip php-gd mariadb-server apache2 composer git unzip curl

# 2. Datenbank (DB_PASS ersetzen)
sudo mysql -e "CREATE DATABASE ki_tool CHARACTER SET utf8mb4;
CREATE USER 'ki_tool'@'localhost' IDENTIFIED BY 'DB_PASS';
GRANT ALL ON ki_tool.* TO 'ki_tool'@'localhost'; FLUSH PRIVILEGES;"

# 3. Code holen + installieren (Platzhalter ersetzen)
sudo git clone git@github.com:Benny29390/thx_ai_tool.git /var/www && cd /var/www
sudo SKIP_CLONE=1 DB_PASS='DB_PASS' APP_URL='https://DOMAIN' \
     ADMIN_EMAIL='chef@kunde.de' ADMIN_PASS='PASSWORT' ADMIN_NAME='Administrator' \
     bash scripts/provision.sh

# 4. Prüfen
php scripts/doctor.php
```

Danach Webserver/HTTPS, Update-Cron, Lizenz und Branding — siehe die vollständige
Anleitung.

## Für einen KI-Agenten, der diese Installation durchführt

Du darfst auf einem **frischen** Server (nichts Produktives) autonom installieren.
Vorgehen:

1. Lies **`docs/kunden-installation.md`** vollständig und arbeite die Schritte 1–9 ab.
2. **Frage den Betreiber vorab nach diesen Werten** (nicht raten):
   `DOMAIN`, `DB_PASS`, `ADMIN_EMAIL`, `ADMIN_PASS`, Kundenname und die freizu-
   schaltenden Module.
3. **Datensicherheit ist oberste Regel.** `provision.sh` bricht ab, wenn schon
   eine `config/config.php` oder eine befüllte Datenbank existiert — dieses
   Schutzverhalten NIE umgehen. Kein `git clean`, keine bestehenden Daten löschen.
4. Diese Schritte kann ein Agent **nicht allein** erledigen — hier den Betreiber
   einbeziehen:
   - **Deploy-Key freigeben:** Den auf dem Server erzeugten öffentlichen SSH-
     Schlüssel muss der Betreiber im GitHub-Repo unter *Deploy keys* eintragen
     (nur Lesezugriff). Ohne das schlägt `git clone` fehl.
   - **Lizenz signieren:** `config/license.json` wird mit dem privaten Schlüssel
     des Herstellers erzeugt (`scripts/license-sign.php` auf dem Steuerserver, NICHT
     hier) und dann hierher kopiert. Der private Schlüssel gehört nie auf einen
     Kundenserver.
5. Zum Schluss `php scripts/doctor.php` — muss „OK" zeigen.

## Wichtige Dateien

| Datei | Zweck |
|---|---|
| `scripts/provision.sh` | Erstinstallation (idempotent, überschreibt nie Bestand) |
| `scripts/update.php` | Update ziehen (Backup → Wartung → reset → migrate) |
| `scripts/doctor.php` | Selbsttest der Installation |
| `scripts/license-sign.php` | Lizenz signieren (nur Hersteller) |
| `deploy/apache/vhost.template.conf` | Apache-vhost-Vorlage |
| `deploy/cron/ki-tool-autoupdate` | Cron: Status prüfen + Updates auf Anforderung |
| `config/config.template.php` | Vorlage für die installationsspezifische Konfiguration |
