#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR=/opt/routebox-telegram-bot
BACKUP_DIR=/var/backups/routebox-telegram-bot
[[ $EUID -eq 0 ]] || { echo '[ERROR] Run as root.' >&2; exit 1; }
NAME="${1:-}"
[[ -n "$NAME" ]] || { echo "Usage: $0 <backup-file>"; exit 1; }
NAME=$(basename "$NAME")
FILE="$BACKUP_DIR/$NAME"
[[ -f "$FILE" ]] || { echo "[ERROR] Backup not found: $NAME" >&2; exit 1; }
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/extract"
tar -xzf "$FILE" -C "$TMP/extract"
SRC="$TMP/extract/app/source.tar.gz"
[[ -f "$SRC" ]] || { echo '[ERROR] Invalid backup.' >&2; exit 1; }
systemctl stop routebox-telegram-bot.service >/dev/null 2>&1 || true
PORT=$(cat /etc/routebox-telegram-bot/web-port 2>/dev/null || true)
[[ "$PORT" =~ ^[0-9]+$ ]] && systemctl stop "routebox-telegram-bot-web@${PORT}.service" >/dev/null 2>&1 || true
ROLLBACK="/var/backups/routebox-telegram-bot/pre-restore-$(date +%Y%m%d-%H%M%S).tar.gz"
if [[ -x "$APP_DIR/backup.sh" ]]; then "$APP_DIR/backup.sh" >"$TMP/pre-restore.txt" 2>&1 || true; fi
rm -rf "$TMP/source"
mkdir -p "$TMP/source"
tar -xzf "$SRC" -C "$TMP/source"
cp -a "$TMP/source/." "$APP_DIR/"
if [[ -f "$TMP/extract/meta/config.php" ]]; then cp -a "$TMP/extract/meta/config.php" "$APP_DIR/config/config.php"; fi
if [[ -f "$TMP/extract/meta/database.sqlite" ]]; then cp -a "$TMP/extract/meta/database.sqlite" "$APP_DIR/storage/database.sqlite"; fi
chown -R www-data:www-data "$APP_DIR/storage"
chmod 750 "$APP_DIR/storage"; chmod 640 "$APP_DIR/storage/database.sqlite" 2>/dev/null || true
systemctl daemon-reload
systemctl enable --now routebox-telegram-bot.service
if [[ "$PORT" =~ ^[0-9]+$ ]]; then systemctl enable --now "routebox-telegram-bot-web@${PORT}.service"; fi
php -l "$APP_DIR/worker.php" >/dev/null
php -l "$APP_DIR/public/index.php" >/dev/null
echo "✓ Restored $NAME"
