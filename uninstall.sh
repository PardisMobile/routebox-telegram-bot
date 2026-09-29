#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/opt/routebox-telegram-bot"
SERVICE="routebox-telegram-bot.service"
NGINX_SITE="routebox-telegram-bot"

if [[ "${EUID}" -ne 0 ]]; then
  echo "[ERROR] Run as root: sudo bash uninstall.sh"
  exit 1
fi

echo "This removes the installed RouteBox Telegram Bot service, Nginx site and application directory."
read -r -p "Continue? [y/N] " answer
if [[ ! "${answer}" =~ ^[Yy]$ ]]; then
  echo "Cancelled."
  exit 0
fi

systemctl disable --now "${SERVICE}" 2>/dev/null || true
rm -f "/etc/systemd/system/${SERVICE}"
systemctl daemon-reload

rm -f "/etc/nginx/sites-enabled/${NGINX_SITE}"
rm -f "/etc/nginx/sites-available/${NGINX_SITE}"
nginx -t && systemctl reload nginx || true

rm -rf "${APP_DIR}"

echo "[OK] RouteBox Telegram Bot has been removed from this server."
echo "Note: system packages such as PHP, Nginx and SQLite were intentionally not removed."
