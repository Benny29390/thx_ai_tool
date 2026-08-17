#!/usr/bin/env bash
#
# install-server.sh — Interaktiver End-to-End-Installer fuer eine neue
# Kundeninstallation auf einem FRISCHEN Ubuntu/Debian-Server.
#
# Standalone: Diese eine Datei auf den neuen Server kopieren und als root starten:
#     sudo bash install-server.sh
# Sie fragt alles Noetige ab und richtet die komplette Installation ein
# (Systempakete, Code, Datenbank, Konfiguration, Apache, HTTPS, Update-Cron).
#
# DATENSICHERHEIT (oberste Regel): Laeuft nur auf einem frischen Server. Findet
# der Installer eine bestehende config.php oder eine befuellte Datenbank, bricht
# er ab — es wird nie etwas ueberschrieben oder geloescht.

set -uo pipefail

# ---------- Hilfsausgaben ----------
c_blue=$'\033[1;34m'; c_green=$'\033[1;32m'; c_red=$'\033[1;31m'; c_yellow=$'\033[1;33m'; c_off=$'\033[0m'
say()  { echo "${c_blue}▸${c_off} $*"; }
ok()   { echo "${c_green}✓${c_off} $*"; }
warn() { echo "${c_yellow}!${c_off} $*"; }
fail() { echo "${c_red}✗ FEHLER:${c_off} $*" >&2; exit 1; }
ask()  { # ask "Frage" "default"  -> Antwort auf STDOUT
    local q="$1" def="${2:-}" ans=""
    if [ -n "$def" ]; then read -r -p "  $q [$def]: " ans; echo "${ans:-$def}";
    else read -r -p "  $q: " ans; echo "$ans"; fi
}
ask_secret() { # ask_secret "Frage" -> Antwort (versteckt); leer -> automatisch erzeugen
    local q="$1" ans=""
    read -r -s -p "  $q (Enter = automatisch erzeugen): " ans; echo >&2
    if [ -z "$ans" ]; then ans="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)"; fi
    echo "$ans"
}

echo
echo "  ============================================================"
echo "   KI Text Tool — Installer fuer eine neue Kundeninstallation"
echo "  ============================================================"
echo

# ---------- 0) Voraussetzungen ----------
[ "$(id -u)" -eq 0 ] || fail "Bitte als root ausfuehren (sudo bash install-server.sh)."
command -v apt-get >/dev/null || fail "Dieser Installer unterstuetzt nur Debian/Ubuntu (apt-get)."

# ---------- 1) Abfragen ----------
say "Bitte ein paar Angaben (Enter uebernimmt den Vorschlag):"
APP_DIR="$(ask 'Installationsverzeichnis' '/var/www')"
DOMAIN="$(ask 'Domain (z.B. ki.kunde.de)')"
[ -n "$DOMAIN" ] || fail "Domain darf nicht leer sein."
REPO_URL="$(ask 'Code-Repository (SSH)' 'git@github.com:Benny29390/thx_ai_tool.git')"
BRANCH="$(ask 'Branch' 'main')"
DB_NAME="$(ask 'Datenbank-Name' 'ki_tool')"
DB_USER="$(ask 'Datenbank-Benutzer' 'ki_tool')"
DB_PASS="$(ask_secret 'Datenbank-Passwort')"
ADMIN_EMAIL="$(ask 'Admin-E-Mail')"
[ -n "$ADMIN_EMAIL" ] || fail "Admin-E-Mail darf nicht leer sein."
ADMIN_PASS="$(ask_secret 'Admin-Passwort')"
ADMIN_NAME="$(ask 'Admin-Name' 'Administrator')"
DO_HTTPS="$(ask 'HTTPS per certbot einrichten? (j/n)' 'j')"
DO_CRON="$(ask 'Automatische Updates (Cron) einrichten? (j/n)' 'j')"

# ---------- Datensicherheits-Check ----------
if [ -f "$APP_DIR/config/config.php" ]; then
    fail "$APP_DIR/config/config.php existiert bereits — Abbruch (keine bestehende Installation ueberschreiben)."
fi

echo
say "Zusammenfassung:"
echo "    Verzeichnis : $APP_DIR"
echo "    Domain      : https://$DOMAIN"
echo "    Repository  : $REPO_URL  (Branch $BRANCH)"
echo "    Datenbank   : $DB_NAME / $DB_USER"
echo "    Admin       : $ADMIN_EMAIL"
echo "    HTTPS       : $DO_HTTPS    Update-Cron: $DO_CRON"
echo
CONFIRM="$(ask 'So installieren? (j/n)' 'j')"
[ "$CONFIRM" = "j" ] || fail "Abgebrochen."

# ---------- 2) Systempakete ----------
say "Installiere Systempakete ..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq || fail "apt-get update fehlgeschlagen."
apt-get install -y -qq php php-cli php-mysql php-mbstring php-curl php-xml php-zip php-gd \
    mariadb-server apache2 composer git unzip curl openssl >/dev/null || fail "Paketinstallation fehlgeschlagen."
a2enmod rewrite >/dev/null 2>&1
ok "Pakete installiert."

# ---------- 3) Deploy-Key (nur lesen) ----------
KEY=/root/.ssh/id_ed25519
mkdir -p /root/.ssh && chmod 700 /root/.ssh
if [ ! -f "$KEY" ]; then
    ssh-keygen -t ed25519 -f "$KEY" -N "" -C "ki-tool-$DOMAIN" >/dev/null
fi
grep -q "Host github.com" /root/.ssh/config 2>/dev/null || \
    printf 'Host github.com\n  IdentityFile %s\n  IdentitiesOnly yes\n' "$KEY" >> /root/.ssh/config
chmod 600 /root/.ssh/config "$KEY"
ssh-keyscan -t ed25519 github.com >> /root/.ssh/known_hosts 2>/dev/null

echo
warn "Einmaliger manueller Schritt: Diesen Schluessel bei GitHub als Deploy-Key"
warn "eintragen (Repo → Settings → Deploy keys → Add), OHNE Schreibrecht:"
echo
echo "    $(cat "$KEY".pub)"
echo
read -r -p "  Wenn eingetragen: Enter druecken zum Fortfahren ... " _
# Zugriff pruefen (mehrere Versuche)
for try in 1 2 3 4 5; do
    if GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=accept-new" git ls-remote "$REPO_URL" >/dev/null 2>&1; then
        ok "Zugriff aufs Repository bestaetigt."; break
    fi
    [ "$try" -eq 5 ] && fail "Kein Zugriff aufs Repository. Deploy-Key korrekt eingetragen?"
    read -r -p "  Noch kein Zugriff. Schluessel eingetragen? Enter fuer neuen Versuch ... " _
done

# ---------- 4) Code holen ----------
say "Hole den Code ..."
if [ -d "$APP_DIR/.git" ]; then
    warn "$APP_DIR ist bereits ein Git-Checkout — ueberspringe Klonen."
else
    GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=accept-new" \
        git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR" || fail "git clone fehlgeschlagen."
fi
ok "Code in $APP_DIR."

# ---------- 5) Datenbank anlegen ----------
say "Lege Datenbank an ..."
DB_HAS=$(mysql -N -s -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null || echo 0)
[ "${DB_HAS:-0}" -gt 0 ] && fail "Datenbank '$DB_NAME' enthaelt bereits Tabellen — Abbruch."
mysql <<SQL || fail "Datenbank-Anlage fehlgeschlagen."
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "Datenbank bereit."

# ---------- 6) App installieren (delegiert an provision.sh) ----------
say "Installiere die Anwendung (Konfiguration, Abhaengigkeiten, Migrationen, Admin) ..."
SKIP_CLONE=1 APP_DIR="$APP_DIR" \
  DB_HOST=localhost DB_PORT=3306 DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" \
  APP_URL="https://$DOMAIN" \
  ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASS="$ADMIN_PASS" ADMIN_NAME="$ADMIN_NAME" \
  bash "$APP_DIR/scripts/provision.sh" || fail "provision.sh fehlgeschlagen."

# ---------- 7) Apache-vhost ----------
say "Richte Apache ein ..."
VHOST=/etc/apache2/sites-available/ki-tool.conf
sed -e "s#__DOMAIN__#$DOMAIN#g" -e "s#__APP_DIR__#$APP_DIR#g" \
    "$APP_DIR/deploy/apache/vhost.template.conf" > "$VHOST"
a2ensite ki-tool >/dev/null 2>&1
a2dissite 000-default >/dev/null 2>&1
systemctl reload apache2 || warn "Apache-Reload meldete ein Problem — bitte pruefen."
ok "Apache konfiguriert."

# ---------- 8) HTTPS ----------
if [ "$DO_HTTPS" = "j" ]; then
    say "Richte HTTPS ein (certbot) ..."
    apt-get install -y -qq certbot python3-certbot-apache >/dev/null 2>&1
    if certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect >/dev/null 2>&1; then
        ok "HTTPS aktiv."
    else
        warn "certbot nicht abgeschlossen (DNS schon auf diesen Server gerichtet?). Spaeter: sudo certbot --apache -d $DOMAIN"
    fi
fi

# ---------- 9) Update-Cron ----------
if [ "$DO_CRON" = "j" ]; then
    sed "s#__APP_DIR__#$APP_DIR#g" "$APP_DIR/deploy/cron/ki-tool-autoupdate" > /etc/cron.d/ki-tool-autoupdate
    chmod 644 /etc/cron.d/ki-tool-autoupdate
    ok "Automatische Updates eingerichtet."
fi

# ---------- 10) Selbsttest + Zusammenfassung ----------
say "Selbsttest ..."
php "$APP_DIR/scripts/doctor.php" || warn "doctor.php meldete Hinweise — bitte oben lesen."

echo
echo "  ============================================================"
ok "Installation abgeschlossen."
echo "  ------------------------------------------------------------"
echo "   Adresse   : https://$DOMAIN"
echo "   Admin     : $ADMIN_EMAIL"
echo "   Passwort  : $ADMIN_PASS"
echo "   DB-Pass   : $DB_PASS"
echo "  ------------------------------------------------------------"
echo "   NOTIEREN: Passwoerter sicher aufbewahren (werden nicht erneut angezeigt)."
echo
echo "   Noch zu tun:"
echo "    1) Lizenz einspielen: auf dem Steuerserver signieren"
echo "       (scripts/license-sign.php) und als $APP_DIR/config/license.json ablegen."
echo "    2) Im Browser einloggen → Styleguide → Eigenes Branding (Logo/Farbe)"
echo "       und Einstellungen → KI-Modelle (API-Schluessel)."
echo "  ============================================================"
echo
