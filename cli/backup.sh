#!/bin/bash
# KI-Tool Backup — DB + Code-Tree → /var/backups/ki-tool/
#
# Wird nightly via /etc/cron.d/ki-tool-backup als root ausgeführt.
# Behält die letzten N Tage; ältere werden gelöscht.
#
# Manuell triggern: sudo bash /var/www/cli/backup.sh

set -euo pipefail

# === Konfiguration ===
BACKUP_DIR="/var/backups/ki-tool"
KEEP_DAYS=14
WWW_DIR="/var/www"
DB_NAME="ki_tool"
DB_USER="ki_tool"
DB_PASS="ki_tool_2024!"
TIMESTAMP="$(date +%Y-%m-%d_%H%M)"
LOG_FILE="$BACKUP_DIR/backup.log"

# === Vorbereitung ===
mkdir -p "$BACKUP_DIR"
exec 3>&1 1>>"$LOG_FILE" 2>&1
echo ""
echo "==================================================================="
echo "[$TIMESTAMP] Backup gestartet"
echo "==================================================================="

# === DB-Backup (mysqldump) ===
DB_FILE="$BACKUP_DIR/db-${TIMESTAMP}.sql.gz"
echo "[$(date +%T)] DB-Dump → $DB_FILE"
if mysqldump \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --default-character-set=utf8mb4 \
    "$DB_NAME" 2>>"$LOG_FILE" \
  | gzip > "$DB_FILE"; then
    DB_SIZE="$(du -h "$DB_FILE" | cut -f1)"
    echo "[$(date +%T)] DB-Dump OK ($DB_SIZE)"
else
    echo "[$(date +%T)] DB-Dump FEHLGESCHLAGEN"
    rm -f "$DB_FILE"
    exit 1
fi

# === File-Backup (tar) ===
# storage/cache, storage/vectors, vendor und .git werden ausgenommen — die sind reproduzierbar.
FILES_FILE="$BACKUP_DIR/files-${TIMESTAMP}.tar.gz"
echo "[$(date +%T)] File-Tar → $FILES_FILE"
if tar -czf "$FILES_FILE" \
    --exclude="$WWW_DIR/storage/cache" \
    --exclude="$WWW_DIR/storage/uploads/tmp" \
    --exclude="$WWW_DIR/vendor" \
    --exclude="$WWW_DIR/node_modules" \
    --exclude="$WWW_DIR/.git" \
    --warning=no-file-changed \
    -C / "var/www" 2>>"$LOG_FILE"; then
    FILES_SIZE="$(du -h "$FILES_FILE" | cut -f1)"
    echo "[$(date +%T)] File-Tar OK ($FILES_SIZE)"
else
    # tar gibt manchmal Exit-Code 1 bei "file changed during read", aber das ist nur Warnung
    if [ -s "$FILES_FILE" ]; then
        FILES_SIZE="$(du -h "$FILES_FILE" | cut -f1)"
        echo "[$(date +%T)] File-Tar OK mit Warnungen ($FILES_SIZE)"
    else
        echo "[$(date +%T)] File-Tar FEHLGESCHLAGEN"
        rm -f "$FILES_FILE"
        exit 1
    fi
fi

# === Rotation: alte Backups löschen ===
echo "[$(date +%T)] Rotation: lösche Backups älter als $KEEP_DAYS Tage"
find "$BACKUP_DIR" -maxdepth 1 -type f -name "db-*.sql.gz" -mtime +$KEEP_DAYS -delete -print
find "$BACKUP_DIR" -maxdepth 1 -type f -name "files-*.tar.gz" -mtime +$KEEP_DAYS -delete -print

# === Übersicht ===
TOTAL_SIZE="$(du -sh "$BACKUP_DIR" | cut -f1)"
echo "[$(date +%T)] Backup abgeschlossen — Gesamt: $TOTAL_SIZE"
ls -la "$BACKUP_DIR" | tail -20

# === Status-JSON für das Admin-UI schreiben ===
# Apache PHP kann /var/backups/ wegen open_basedir nicht direkt lesen. Daher
# legen wir den aktuellen Stand als JSON in /var/www/storage/ ab — das ist
# innerhalb der Apache-Whitelist.
STATUS_FILE="$WWW_DIR/storage/backup-status.json"
{
    echo "{"
    echo "  \"last_run\": \"$(date '+%Y-%m-%d %H:%M:%S')\","
    echo "  \"last_run_iso\": \"$(date -Iseconds)\","
    echo "  \"backup_dir\": \"$BACKUP_DIR\","
    echo "  \"keep_days\": $KEEP_DAYS,"
    echo "  \"total_size\": \"$TOTAL_SIZE\","
    echo "  \"last_db\": {"
    echo "    \"file\": \"$(basename "$DB_FILE")\","
    echo "    \"size\": \"$DB_SIZE\","
    echo "    \"size_bytes\": $(stat -c%s "$DB_FILE" 2>/dev/null || echo 0)"
    echo "  },"
    echo "  \"last_files\": {"
    echo "    \"file\": \"$(basename "$FILES_FILE")\","
    echo "    \"size\": \"$FILES_SIZE\","
    echo "    \"size_bytes\": $(stat -c%s "$FILES_FILE" 2>/dev/null || echo 0)"
    echo "  },"
    echo "  \"backups\": ["
    FIRST=1
    for f in $(ls -1t "$BACKUP_DIR" 2>/dev/null | grep -E '^(db|files)-.*\.(sql|tar)\.gz$'); do
        FULL="$BACKUP_DIR/$f"
        SIZE_B="$(stat -c%s "$FULL" 2>/dev/null || echo 0)"
        SIZE_H="$(du -h "$FULL" 2>/dev/null | cut -f1)"
        MTIME="$(stat -c '%y' "$FULL" 2>/dev/null | cut -d. -f1)"
        TYPE="$(echo "$f" | cut -d- -f1)"
        [ $FIRST -eq 0 ] && echo ","
        printf '    {"name":"%s","type":"%s","size_bytes":%s,"size":"%s","mtime":"%s"}' "$f" "$TYPE" "$SIZE_B" "$SIZE_H" "$MTIME"
        FIRST=0
    done
    echo ""
    echo "  ]"
    echo "}"
} > "$STATUS_FILE"
chown www-data:www-data "$STATUS_FILE" 2>/dev/null || true
chmod 0644 "$STATUS_FILE"
echo "[$(date +%T)] Status-JSON geschrieben: $STATUS_FILE"

# Re-Aktivierung von stdout/stderr für Cron-Output (leer wenn kein Fehler)
exec 1>&3 3>&-
echo "Backup OK: db=$DB_SIZE files=$FILES_SIZE total=$TOTAL_SIZE"
