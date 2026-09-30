#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR=/opt/routebox-telegram-bot
BACKUP_DIR=/var/backups/routebox-telegram-bot
STATE_DIR=/etc/routebox-telegram-bot
[[ $EUID -eq 0 ]] || { echo '[ERROR] Run as root.' >&2; exit 1; }
mkdir -p "$BACKUP_DIR"; chown root:www-data "$BACKUP_DIR"; chmod 750 "$BACKUP_DIR"
if [[ "${1:-}" == "delete" ]]; then
  NAME=$(basename "${2:-}")
  [[ "$NAME" =~ ^routebox-telegram-bot-v[0-9A-Za-z.-]+(?:-pre-update)?-[0-9]{8}-[0-9]{6}\.tar\.gz$ ]] || { echo '[ERROR] Invalid backup name.' >&2; exit 1; }
  rm -f -- "$BACKUP_DIR/$NAME"; echo "Deleted $NAME"; exit 0
fi
VERSION=$(tr -d '[:space:]' < "$APP_DIR/VERSION" 2>/dev/null || echo unknown)
STAMP=$(date '+%Y%m%d-%H%M%S')
OUT="$BACKUP_DIR/routebox-telegram-bot-v${VERSION}-${STAMP}.tar.gz"
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/app" "$TMP/meta"
tar -C "$APP_DIR" -czf "$TMP/app/source.tar.gz" --exclude='./storage/logs/*' --exclude='./storage/update.offset' --exclude='./.git' VERSION database src public worker.php bot.php config/config.php systemd install.sh install-v2.sh update.sh admin-update.sh setup-routebox-tls.sh uninstall.sh backup.sh restore-backup.sh
cp -a "$APP_DIR/storage/database.sqlite" "$TMP/meta/database.sqlite" 2>/dev/null || true
cp -a "$APP_DIR/config/config.php" "$TMP/meta/config.php" 2>/dev/null || true
cp -a "$STATE_DIR" "$TMP/meta/state" 2>/dev/null || true
printf 'version=%s\ntime=%s\napp_dir=%s\n' "$VERSION" "$(date --iso-8601=seconds)" "$APP_DIR" > "$TMP/meta/manifest.txt"
tar -C "$TMP" -czf "$OUT" app meta
chown root:www-data "$OUT"; chmod 640 "$OUT"
mapfile -t OLD < <(ls -1t "$BACKUP_DIR"/*.tar.gz 2>/dev/null | tail -n +11 || true)
((${#OLD[@]})) && rm -f -- "${OLD[@]}" || true
echo "$OUT"
