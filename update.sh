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

echo "==> Fetching latest Beta release..."
git fetch --prune origin
git reset --hard origin/main

echo "==> Ensuring runtime directories..."
mkdir -p storage/logs
chown -R www-data:www-data storage
chmod 750 storage

if [[ -f systemd/routebox-telegram-bot.service ]]; then
  install -m 0644 systemd/routebox-telegram-bot.service "/etc/systemd/system/${SERVICE}"
  systemctl daemon-reload
  systemctl enable "${SERVICE}"
fi

if [[ -f database/schema.sql ]]; then
  sqlite3 storage/database.sqlite < database/schema.sql
  chown www-data:www-data storage/database.sqlite
  chmod 640 storage/database.sqlite
fi

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm.service"
systemctl enable --now "${PHP_FPM_SERVICE}" >/dev/null 2>&1 || true

nginx -t
systemctl reload nginx

echo "==> Running validation..."
while IFS= read -r -d '' file; do php -l "${file}" >/dev/null; done < <(find . -type f -name '*.php' -not -path './.git/*' -print0)
bash -n install.sh install-v2.sh update.sh uninstall.sh

grep -q '/api/auth/login' src/RouteBoxClient.php
grep -q 'sessionCookie' src/RouteBoxClient.php

echo "✓ PHP and RouteBox integration validation passed."

systemctl restart "${SERVICE}"
sleep 1
if ! systemctl is-active --quiet "${SERVICE}"; then
  echo "[ERROR] Bot service failed after update."
  journalctl -u "${SERVICE}" -n 80 --no-pager || true
  exit 1
fi

echo "✅ RouteBox Telegram Bot updated successfully."
