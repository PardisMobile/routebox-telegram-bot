#!/usr/bin/env bash
set -Eeuo pipefail

# RouteBox Telegram Bot — Beta installer
# Ubuntu 22.04+

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
WEB_ROOT="${APP_DIR}/public"
SERVICE_NAME="${APP_NAME}.service"
NGINX_SITE="${APP_NAME}"
REPO="https://github.com/PardisMobile/routebox-telegram-bot.git"

if [[ "${EUID}" -ne 0 ]]; then
  echo "[ERROR] Run as root: sudo bash install.sh"
  exit 1
fi

. /etc/os-release
if [[ "${ID:-}" != "ubuntu" ]]; then
  echo "[ERROR] This installer targets Ubuntu 22.04+."
  exit 1
fi

ubuntu_major="${VERSION_ID%%.*}"
if (( ubuntu_major < 22 )); then
  echo "[ERROR] Ubuntu 22.04 or newer is required."
  exit 1
fi

echo "==> Installing system dependencies..."
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
  ca-certificates curl git nginx sqlite3 openssl \
  php-cli php-fpm php-curl php-sqlite3 php-mbstring php-xml php-opcache

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_MAJOR="${PHP_VERSION%%.*}"
if (( PHP_MAJOR < 8 )); then
  echo "[ERROR] PHP 8.0+ is required. Found PHP ${PHP_VERSION}."
  exit 1
fi
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm.service"
PHP_FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
if [[ ! -S "${PHP_FPM_SOCKET}" ]]; then
  systemctl enable --now "${PHP_FPM_SERVICE}" || true
fi
if [[ ! -S "${PHP_FPM_SOCKET}" ]]; then
  echo "[ERROR] PHP-FPM socket not found: ${PHP_FPM_SOCKET}"
  exit 1
fi

if [[ -d "${APP_DIR}/.git" ]]; then
  echo "==> Updating existing installation..."
  git -C "${APP_DIR}" fetch --prune origin
  git -C "${APP_DIR}" reset --hard origin/main
else
  if [[ -e "${APP_DIR}" && -n "$(find "${APP_DIR}" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]]; then
    echo "[ERROR] ${APP_DIR} exists and is not an empty Git checkout."
    exit 1
  fi
  rm -rf "${APP_DIR}"
  git clone --depth 1 "${REPO}" "${APP_DIR}"
fi

mkdir -p "${APP_DIR}/storage/logs"
chown -R www-data:www-data "${APP_DIR}/storage"
chmod 750 "${APP_DIR}/storage"

# Generate the private runtime configuration once. It is intentionally not stored in Git.
if [[ ! -f "${APP_DIR}/config/config.php" ]]; then
  APP_KEY="$(openssl rand -base64 32 | tr -d '\n')"
  ADMIN_PASSWORD="$(openssl rand -hex 12)"
  ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "${ADMIN_PASSWORD}")"
  cat > "${APP_DIR}/config/config.php" <<PHP
<?php
return [
    'app_name' => 'RouteBox Telegram Bot',
    'version' => '0.1.0-beta.1',
    'timezone' => 'Asia/Tehran',
    'db' => __DIR__ . '/../storage/database.sqlite',
    'app_key' => '${APP_KEY}',
    'admin_user' => 'admin',
    'admin_password_hash' => '${ADMIN_HASH}',
    'telegram' => ['poll_timeout' => 25],
    'security' => ['session_name' => 'rbt_session', 'cookie_secure' => false],
];
PHP
  chown root:www-data "${APP_DIR}/config/config.php"
  chmod 640 "${APP_DIR}/config/config.php"
  echo
  echo "============================================================"
  echo " RouteBox Telegram Bot — Beta 0.1.0-beta.1"
  echo "============================================================"
  echo " Admin URL:      http://YOUR_SERVER_IP/"
  echo " Admin username: admin"
  echo " Admin password: ${ADMIN_PASSWORD}"
  echo "------------------------------------------------------------"
  echo " SAVE THIS PASSWORD. It is not stored in the repository."
  echo "============================================================"
  echo
fi

DB="${APP_DIR}/storage/database.sqlite"
if [[ ! -f "${DB}" ]]; then
  sqlite3 "${DB}" < "${APP_DIR}/database/schema.sql"
fi
chown www-data:www-data "${DB}"
chmod 640 "${DB}"

# Never expose runtime configuration or SQLite through the web root.
chmod 750 "${APP_DIR}/config"

install -m 0644 "${APP_DIR}/systemd/routebox-telegram-bot.service" "/etc/systemd/system/${SERVICE_NAME}"
systemctl daemon-reload
systemctl enable "${SERVICE_NAME}"

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
        fastcgi_pass unix:${PHP_FPM_SOCKET};
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
systemctl restart "${SERVICE_NAME}"

# Verify the application syntax before declaring success.
echo "==> Running PHP syntax checks..."
while IFS= read -r -d '' file; do php -l "${file}" >/dev/null; done < <(find "${APP_DIR}" -type f -name '*.php' -print0)

if ! systemctl is-active --quiet "${SERVICE_NAME}"; then
  echo "[ERROR] Bot service failed to start."
  journalctl -u "${SERVICE_NAME}" -n  80 --no-pager || true
  exit 1
fi

echo
echo "✅ RouteBox Telegram Bot Beta installed successfully."
echo "🌐 Panel: http://YOUR_SERVER_IP/"
echo "📁 App:   ${APP_DIR}"
echo "📝 Logs:  journalctl -u ${SERVICE_NAME} -f"
echo "⚠️  Configure HTTPS before exposing the panel publicly."
