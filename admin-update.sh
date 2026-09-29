#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/opt/routebox-telegram-bot
SERVICE=routebox-telegram-bot.service
WEB_SERVICE=routebox-telegram-bot-web
STATE_DIR=/etc/routebox-telegram-bot
LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/admin-update.log"

[[ $EUID -eq 0 ]] || { echo '[ERROR] This updater must run as root.' >&2; exit 1; }
[[ -d "$APP_DIR/.git" ]] || { echo "[ERROR] $APP_DIR is not a Git checkout." >&2; exit 1; }

mkdir -p "$LOG_DIR" "$STATE_DIR"
exec >>"$LOG_FILE" 2>&1

echo "===== Admin update started: $(date -Is) ====="
cd "$APP_DIR"

# Stop both application services before replacing the code. update.sh will
# recreate/re-enable the web listener and restart the worker after the update.
systemctl stop "$SERVICE" >/dev/null 2>&1 || true
PORT=$(cat "$STATE_DIR/web-port" 2>/dev/null || true)
if [[ "$PORT" =~ ^[0-9]+$ ]]; then
  systemctl stop "${WEB_SERVICE}@${PORT}.service" >/dev/null 2>&1 || true
fi
sleep 1

bash "$APP_DIR/update.sh"

echo "===== Admin update finished: $(date -Is) ====="
