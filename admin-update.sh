#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/opt/routebox-telegram-bot
LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/admin-update.log"

[[ $EUID -eq 0 ]] || { echo '[ERROR] This updater must run as root.' >&2; exit 1; }
[[ -d "$APP_DIR/.git" ]] || { echo "[ERROR] $APP_DIR is not a Git checkout." >&2; exit 1; }
[[ -f "$APP_DIR/update.sh" ]] || { echo "[ERROR] $APP_DIR/update.sh was not found." >&2; exit 1; }

mkdir -p "$LOG_DIR"
exec >>"$LOG_FILE" 2>&1

echo "===== Admin update started: $(date -Is) ====="
cd "$APP_DIR"

# update.sh is the single canonical updater. It performs the exact same
# repository update used from SSH:
#   cd /opt/routebox-telegram-bot
#   git fetch --prune origin
#   git reset --hard origin/main
#
# It then re-executes the freshly pulled update.sh, so even an older installed
# copy cannot continue with stale service names or update logic.
exec /usr/bin/env bash "$APP_DIR/update.sh"
