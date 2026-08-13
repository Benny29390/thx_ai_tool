#!/usr/bin/env bash
#
# provision.sh — Erstinstallation einer neuen Kundeninstallation.
#
# Richtet auf einem frischen Server eine lauffaehige Instanz ein: Code holen,
# Abhaengigkeiten, Verzeichnisrechte, config.php mit frischem encryption_key,
# Datenbank + Migrationen, Admin-Benutzer.
#
# DATENSICHERHEIT (oberste Regel): Das Skript ueberschreibt NIE eine bestehende
# Installation. Existiert bereits config/config.php oder eine befuellte
# Datenbank, bricht es ab. So kann ein versehentlicher Zweitlauf keine Daten
# oder den encryption_key zerstoeren.
#
# Aufruf (Beispiel):
#   sudo APP_DIR=/var/www REPO_URL=git@host:ki-tool.git BRANCH=stable \
#        DB_HOST=localhost DB_NAME=ki_tool DB_USER=ki_tool DB_PASS='...' \
#        APP_URL=https://kunde.example.de \
#        ADMIN_EMAIL=chef@kunde.de ADMIN_PASS='...' ADMIN_NAME='Max Muster' \
#        bash scripts/provision.sh
#
# Umgebungsvariablen (Vorgaben in Klammern):
#   APP_DIR (/var/www)   REPO_URL   BRANCH (stable)
#   DB_HOST (localhost)  DB_PORT (3306)  DB_NAME  DB_USER  DB_PASS
#   APP_URL              WEB_USER (www-data)
#   ADMIN_EMAIL  ADMIN_PASS  ADMIN_NAME (Administrator)
#   SKIP_CLONE (0)       # 1 = Code liegt bereits, nicht klonen

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www}"
BRANCH="${BRANCH:-stable}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-ki_tool}"
DB_USER="${DB_USER:-ki_tool}"
DB_PASS="${DB_PASS:-}"
APP_URL="${APP_URL:-}"
WEB_USER="${WEB_USER:-www-data}"
ADMIN_NAME="${ADMIN_NAME:-Administrator}"
SKIP_CLONE="${SKIP_CLONE:-0}"

log()  { echo -e "\033[1;34m[provision]\033[0m $*"; }
fail() { echo -e "\033[1;31m[provision] FEHLER:\033[0m $*" >&2; exit 1; }

# --- 0) Voraussetzungen ---
command -v php >/dev/null      || fail "php nicht gefunden."
command -v composer >/dev/null || fail "composer nicht gefunden."
command -v mysql >/dev/null    || fail "mysql-Client nicht gefunden."
if [ "$SKIP_CLONE" != "1" ]; then
    command -v git >/dev/null  || fail "git nicht gefunden (fuer Klonen/Updates noetig). Bitte installieren: apt-get install -y git"
fi
[ -n "$DB_PASS" ]  || fail "DB_PASS ist leer."
[ -n "$APP_URL" ]  || fail "APP_URL ist leer."
[ -n "${ADMIN_EMAIL:-}" ] || fail "ADMIN_EMAIL ist leer."
[ -n "${ADMIN_PASS:-}" ]  || fail "ADMIN_PASS ist leer."

# --- 1) Nie eine bestehende Installation ueberschreiben ---
if [ -f "$APP_DIR/config/config.php" ]; then
    fail "config/config.php existiert bereits in $APP_DIR — Abbruch (keine bestehende Installation ueberschreiben)."
fi
DB_HAS_TABLES="$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -N -s \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null || echo 0)"
if [ "${DB_HAS_TABLES:-0}" -gt 0 ]; then
    fail "Datenbank '$DB_NAME' enthaelt bereits $DB_HAS_TABLES Tabellen — Abbruch (keine bestehenden Daten anfassen)."
fi

# --- 2) Code holen ---
if [ "$SKIP_CLONE" != "1" ]; then
    [ -n "${REPO_URL:-}" ] || fail "REPO_URL ist leer."
    log "Klone $REPO_URL (Branch $BRANCH) nach $APP_DIR ..."
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi
cd "$APP_DIR"

# --- 3) Abhaengigkeiten ---
log "composer install ..."
composer install --no-dev --optimize-autoloader

# --- 4) Verzeichnisse + Rechte ---
log "Storage-/Upload-Verzeichnisse anlegen und Rechte setzen ..."
mkdir -p storage/{logs,vectors,transkription,mail,exports,cache} uploads config
chown -R "$WEB_USER":"$WEB_USER" storage uploads
chmod -R 775 storage uploads
# config/ muss fuer den Installer/Provision schreibbar sein.
chown "$WEB_USER":"$WEB_USER" config || true

# --- 5) config.php aus Vorlage mit frischem encryption_key ---
log "config.php erzeugen (mit frischem encryption_key) ..."
ENC_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export DB_HOST DB_PORT DB_NAME DB_USER DB_PASS APP_URL ENC_KEY
# Werte via PHP einsetzen (robust gegen Sonderzeichen in Passwoertern/URLs).
php -r '
$tpl = file_get_contents("config/config.template.php");
$map = [
  "__DB_HOST__" => getenv("DB_HOST"),
  "__DB_PORT__" => getenv("DB_PORT"),
  "__DB_NAME__" => getenv("DB_NAME"),
  "__DB_USER__" => getenv("DB_USER"),
  "__DB_PASS__" => getenv("DB_PASS"),
  "__APP_URL__" => rtrim(getenv("APP_URL"), "/"),
  "__ENCRYPTION_KEY__" => getenv("ENC_KEY"),
];
// String-Platzhalter escapen (single-quoted PHP-Strings), Port bleibt numerisch.
// chr(92)=Backslash, chr(39)=Hochkomma — bewusst ohne Anfuehrungszeichen im
// Quelltext, damit das Bash-Single-Quoting drumherum nicht bricht.
foreach ($map as $k => $v) {
    if ($k === "__DB_PORT__") { $tpl = str_replace($k, (string)(int)$v, $tpl); continue; }
    $tpl = str_replace($k, addcslashes((string)$v, chr(92).chr(39)), $tpl);
}
file_put_contents("config/config.php", $tpl);
'
chown "$WEB_USER":"$WEB_USER" config/config.php
chmod 640 config/config.php

# --- 6) Datenbank-Schema + Migrationen ---
log "Basis-Schema laden ..."
if [ -f sql/schema.sql ]; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < sql/schema.sql
fi
log "Migrationen anwenden ..."
php scripts/migrate.php

# --- 7) Admin anlegen ---
log "Admin-Benutzer anlegen ..."
php scripts/create-admin.php --email="$ADMIN_EMAIL" --password="$ADMIN_PASS" --name="$ADMIN_NAME"

# --- 8) Selbsttest ---
log "Selbsttest ..."
php scripts/doctor.php || log "Hinweis: doctor.php meldete Warnungen/Fehler — bitte oben pruefen."

log "Fertig. Instanz unter $APP_URL erreichbar (Apache-vhost separat einrichten)."
