#!/usr/bin/env bash
set -Eeuo pipefail

# RouteBox Telegram Bot - Beta installer
# Ubuntu 22.04+

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
WEB_ROOT="${APP_DIR}/public"
SERVICE_NAME="${APP_NAME}.service"
NGINX_SITE="${APP_NAME}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "[ERROR] Run this installer as root (sudo)."
  exit 1
fi

if [[ -r /etc/os-release ]]; then
  . /etc/os-release
else
  echo "[ERROR] Cannot detect operating system."
  exit 1
fi

if [[ "${ID}" != "ubuntu" ]]; then
  echo "[ERROR] This Beta installer targets Ubuntu 22.04+."
  exit 1
fi

version_id="${VERSION_ID%%.*}"
if (( version_id < 22 )); then
  echo "[ERROR] Ubuntu 22.04 or newer is required."
  exit 1
fi

echo "==> Installing system dependencies..."
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
  ca-certificates curl git nginx sqlite3 openssl \
  php8.2-fpm php8.2-cli php8.2-curl php8.2-sqlite3 php8.2-mbstring php8.2-xml

if [[ ! -d "${APP_DIR}/.git" ]]; then
  mkdir -p "${APP_DIR}"
  if [[ -d "${APP_DIR}" && -n "$(find "${APP_DIR}" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]]; then
    echo "[ERROR] ${APP_DIR} exists and is not a Git checkout."
    echo "        Move/remove it and run the installer again."
    exit 1
  fi
  git clone https://github.com/PardisMobile/routebox-telegram-bot.git "${APP_DIR}"
else
  git -C "${APP_DIR}" pull --ff-only
fi

mkdir -p "${APP_DIR}/storage" "${APP_DIR}/storage/logs"
chown -R www-data:www-data "${APP_DIR}/storage"
chmod 750 "${APP_DIR}/storage"

if [[ -f "${APP_DIR}/.env.example" && ! -f "${APP_DIR}/.env" ]]; then
  cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
  chmod 640 "${APP_DIR}/.env"
fi

if [[ -f "${APP_DIR}/database/schema.sql" && ! -f "${APP_DIR}/storage/app.sqlite" ]]; then
  sqlite3 "${APP_DIR}/storage/app.sqlite" < "${APP_DIR}/database/schema.sql"
  chown www-data:www-data "${APP_DIR}/storage/app.sqlite"
  chmod 640 "${APP_DIR}/storage/app.sqlite"
fi

if [[ -f "${APP_DIR}/systemd/routebox-telegram-bot.service" ]]; then
  install -m 0644 "${APP_DIR}/systemd/routebox-telegram-bot.service" "/etc/systemd/system/${SERVICE_NAME}"
  systemctl daemon-reload
  systemctl enable "${SERVICE_NAME}"
fi

cat > "/etc/nginx/sites-available/${NGINX_SITE}" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root ${WEB_ROOT};
    index index.php;

    client_max_body_size 10M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

ln -sfn "/etc/nginx/sites-available/${NGINX_SITE}" "/etc/nginx/sites-enabled/${NGINX_SITE}"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx
systemctl restart nginx

if [[ -f "${APP_DIR}/systemd/routebox-telegram-bot.service" ]]; then
  systemctl restart "${SERVICE_NAME}"
fi

echo
 echo "=============================================="
echo " RouteBox Telegram Bot - Beta installed"
echo "=============================================="
echo "Directory: ${APP_DIR}"
echo "Panel:     http://YOUR_SERVER_IP/"
echo "Config:    ${APP_DIR}/.env"
echo
 echo "Next step: edit ${APP_DIR}/.env and configure the Bot/RouteBox settings."
echo "WARNING: This release is Beta; use HTTPS before exposing the panel publicly."
