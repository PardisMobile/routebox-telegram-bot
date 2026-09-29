#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR=/opt/routebox-telegram-bot
SERVICE=routebox-telegram-bot.service
APP_NAME=routebox-telegram-bot
WEB_SERVICE=${APP_NAME}-web
STATE_DIR=/etc/${APP_NAME}
[[ $EUID -eq 0 ]] || { echo '[ERROR] Run as root: sudo bash uninstall.sh'; exit 1; }
read -r -p 'Remove RouteBox Telegram Bot and its web panel? [y/N] ' answer
[[ "$answer" =~ ^[Yy]$ ]] || { echo 'Cancelled.'; exit 0; }
systemctl disable --now "$SERVICE" 2>/dev/null || true
rm -f "/etc/systemd/system/$SERVICE"
for unit in /etc/systemd/system/${WEB_SERVICE}@*.service; do
  [[ -e "$unit" ]] || continue
  name=$(basename "$unit" .service)
  systemctl disable --now "$name" 2>/dev/null || true
done
rm -f "/etc/systemd/system/${WEB_SERVICE}@.service"
rm -f "/etc/nginx/sites-enabled/$APP_NAME" "/etc/nginx/sites-available/$APP_NAME"
if command -v nginx >/dev/null 2>&1; then nginx -t >/dev/null 2>&1 && systemctl reload nginx >/dev/null 2>&1 || true; fi
systemctl daemon-reload
rm -rf "$STATE_DIR" "$APP_DIR"
echo '[OK] RouteBox Telegram Bot removed. Existing RouteBox/Apache/Nginx/PHP packages were not removed.'
