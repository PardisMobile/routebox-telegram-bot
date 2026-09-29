#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/opt/routebox-telegram-bot"
SERVICE="routebox-telegram-bot.service"

if [[ "${EUID}" -ne 0 ]]; then
  echo "[ERROR] Run as root: sudo bash update.sh"
  exit 1
fi

if [[ ! -d "${APP_DIR}/.git" ]]; then
  echo "[ERROR] ${APP_DIR} is not a Git checkout. Run install.sh first."
  exit 1
fi

cd "${APP_DIR}"
git fetch --prune origin
git pull --ff-only origin main

mkdir -p storage storage/logs
chown -R www-data:www-data storage

if [[ -f systemd/routebox-telegram-bot.service ]]; then
  install -m 0644 systemd/routebox-telegram-bot.service "/etc/systemd/system/${SERVICE}"
  systemctl daemon-reload
  systemctl enable "${SERVICE}"
  systemctl restart "${SERVICE}"
fi

nginx -t
systemctl reload nginx

echo "[OK] RouteBox Telegram Bot updated."
