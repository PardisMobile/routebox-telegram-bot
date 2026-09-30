#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/opt/routebox-telegram-bot
SERVICE=routebox-telegram-bot.service
WEB_SERVICE=routebox-telegram-bot-web
TLS_SERVICE=routebox-telegram-bot-tls.service
STATE_DIR=/etc/routebox-telegram-bot
LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/admin-update.log"
UPDATE_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/update.sh"

[[ $EUID -eq 0 ]] || { echo '[ERROR] This updater must run as root.' >&2; exit 1; }
[[ -d "$APP_DIR/.git" ]] || { echo "[ERROR] $APP_DIR is not a Git checkout." >&2; exit 1; }

mkdir -p "$LOG_DIR" "$STATE_DIR"
exec >>"$LOG_FILE" 2>&1

echo "===== Admin update started: $(date -Is) ====="
cd "$APP_DIR"

# Refresh update.sh before executing it. This is important because an older
# updater may have already been installed on the server; running that stale
# script in-memory would otherwise continue with old service names even after
# git reset updates the file on disk.
TMP_UPDATE="$(mktemp)"
trap 'rm -f "$TMP_UPDATE"' EXIT
curl -fsSL --connect-timeout 10 --max-time 60 "$UPDATE_URL" -o "$TMP_UPDATE"
chmod 0755 "$TMP_UPDATE"
bash -n "$TMP_UPDATE"
install -m 0755 "$TMP_UPDATE" "$APP_DIR/update.sh"

# Stop only the Bot-owned services before the repository updater runs. The
# existing RouteBox/Apache/Nginx stack is intentionally not touched here.
systemctl stop "$TLS_SERVICE" >/dev/null 2>&1 || true
systemctl stop "$SERVICE" >/dev/null 2>&1 || true
PORT=$(cat "$STATE_DIR/web-port" 2>/dev/null || true)
if [[ "$PORT" =~ ^[0-9]+$ ]]; then
  systemctl stop "${WEB_SERVICE}@${PORT}.service" >/dev/null 2>&1 || true
fi
sleep 1

bash "$APP_DIR/update.sh"

echo "===== Admin update finished: $(date -Is) ====="
