#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/opt/routebox-telegram-bot
SERVICE=routebox-telegram-bot.service
WEB_SERVICE=routebox-telegram-bot-web
TLS_SERVICE=routebox-telegram-bot-tls.service
STATE_DIR=/etc/routebox-telegram-bot
LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/admin-update.log"

[[ $EUID -eq 0 ]] || { echo '[ERROR] This updater must run as root.' >&2; exit 1; }
[[ -d "$APP_DIR/.git" ]] || { echo "[ERROR] $APP_DIR is not a Git checkout." >&2; exit 1; }

mkdir -p "$LOG_DIR" "$STATE_DIR"
exec >>"$LOG_FILE" 2>&1

echo "===== Admin update started: $(date -Is) ====="
cd "$APP_DIR"

# Stop the application, PHP backend and the optional TLS frontend before the
# update. The updater restores TLS automatically after the new code is live.
systemctl stop "$TLS_SERVICE" >/dev/null 2>&1 || true
systemctl stop "$SERVICE" >/dev/null 2>&1 || true
PORT=$(cat "$STATE_DIR/web-port" 2>/dev/null || true)
if [[ "$PORT" =~ ^[0-9]+$ ]]; then
  systemctl stop "${WEB_SERVICE}@${PORT}.service" >/dev/null 2>&1 || true
fi
sleep 1

bash "$APP_DIR/update.sh"

echo "===== Admin update finished: $(date -Is) ====="
