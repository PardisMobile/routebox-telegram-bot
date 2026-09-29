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
chmod 750 "${APP_DIR}/config"

# -----------------------------------------------------------------------------
# First-run configuration wizard
# -----------------------------------------------------------------------------
# RouteBox exposes its REST API on the same HTTP(S) listener as its web panel.
# Current RouteBox VPS installs default to HTTPS :8443; router-mode installs
# commonly use HTTP :8080. A custom reverse proxy/port is also supported.
# Authentication is cookie-based in the UI, while RouteBox explicitly keeps
# HTTP Basic authentication accepted for scripts, so this bot uses Basic Auth.
# The panel port is therefore part of the RouteBox Panel URL/API endpoint.
# -----------------------------------------------------------------------------

has_telegram="$(sqlite3 "${DB}" "SELECT COUNT(*) FROM settings WHERE key='telegram_token' AND value <> '';" 2>/dev/null || echo 0)"
has_routebox="$(sqlite3 "${DB}" "SELECT COUNT(*) FROM routebox_servers;" 2>/dev/null || echo 0)"

if [[ "${has_telegram}" != "1" || "${has_routebox}" == "0" ]]; then
  echo
  echo "============================================================"
  echo " Initial configuration"
  echo "============================================================"
  echo
  echo "[1/2] Telegram Bot"
  echo "Create a bot with @BotFather and paste its token below."
  echo

  while true; do
    read -r -s -p "Telegram Bot Token: " TELEGRAM_TOKEN
    echo
    if [[ -z "${TELEGRAM_TOKEN}" ]]; then
      echo "[ERROR] Telegram Bot Token cannot be empty."
      continue
    fi

    echo "==> Testing Telegram API..."
    telegram_json="$(curl -fsS --connect-timeout 8 --max-time 20 \
      "https://api.telegram.org/bot${TELEGRAM_TOKEN}/getMe" 2>/dev/null || true)"
    if [[ -z "${telegram_json}" ]]; then
      echo "[ERROR] Could not connect to Telegram API. Check network access."
      continue
    fi

    telegram_ok="$(TELEGRAM_JSON="${telegram_json}" php -r '
      $j=json_decode(getenv("TELEGRAM_JSON"),true);
      echo (!empty($j["ok"]) ? "1" : "0");
    ' 2>/dev/null || echo 0)"
    if [[ "${telegram_ok}" != "1" ]]; then
      echo "[ERROR] Telegram rejected the Bot Token."
      echo "        Check the token and try again."
      continue
    fi

    telegram_name="$(TELEGRAM_JSON="${telegram_json}" php -r '
      $j=json_decode(getenv("TELEGRAM_JSON"),true); echo $j["result"]["first_name"] ?? "";
    ' 2>/dev/null || true)"
    telegram_username="$(TELEGRAM_JSON="${telegram_json}" php -r '
      $j=json_decode(getenv("TELEGRAM_JSON"),true); echo $j["result"]["username"] ?? "";
    ' 2>/dev/null || true)"
    echo "✓ Telegram connection successful"
    echo "  Bot: ${telegram_name} (@${telegram_username})"
    break
  done

  # Store the token encrypted with the locally generated application key.
  TELEGRAM_ENC="$(RBT_APP_KEY="${APP_KEY:-$(php -r 'echo "";')}" RBT_SECRET="${TELEGRAM_TOKEN}" php -r '
    $key=base64_decode(getenv("RBT_APP_KEY"),true);
    $plain=getenv("RBT_SECRET");
    if (!$key || strlen($key)!==32 || $plain===false) { exit(2); }
    $iv=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    echo base64_encode($iv.sodium_crypto_secretbox($plain,$iv,$key));
  ' 2>/dev/null)"
  if [[ -z "${TELEGRAM_ENC}" ]]; then
    echo "[ERROR] Failed to encrypt Telegram token."
    exit 1
  fi
  sqlite3 "${DB}" "INSERT INTO settings(key,value) VALUES('telegram_token','${TELEGRAM_ENC}') ON CONFLICT(key) DO UPDATE SET value=excluded.value;"

  echo
  echo "[2/2] RouteBox Server"
  echo "The bot connects to the RouteBox web-panel/API listener."
  echo "The API uses the same panel host and port; there is no separate API port."
  echo

  server_count=0
  while true; do
    server_count=$((server_count + 1))
    echo "--- RouteBox #${server_count} ---"

    read -r -p "Server name [RouteBox-${server_count}]: " RB_NAME
    RB_NAME="${RB_NAME:-RouteBox-${server_count}}"

    read -r -p "Mode (vps/router) [vps]: " RB_MODE
    RB_MODE="${RB_MODE:-vps}"
    if [[ "${RB_MODE}" != "vps" && "${RB_MODE}" != "router" ]]; then
      echo "[ERROR] Mode must be vps or router."
      server_count=$((server_count - 1))
      continue
    fi

    if [[ "${RB_MODE}" == "vps" ]]; then
      DEFAULT_SCHEME="https"
      DEFAULT_PORT="8443"
    else
      DEFAULT_SCHEME="http"
      DEFAULT_PORT="8080"
    fi

    read -r -p "Panel/API scheme [${DEFAULT_SCHEME}]: " RB_SCHEME
    RB_SCHEME="${RB_SCHEME:-${DEFAULT_SCHEME}}"
    if [[ "${RB_SCHEME}" != "http" && "${RB_SCHEME}" != "https" ]]; then
      echo "[ERROR] Scheme must be http or https."
      server_count=$((server_count - 1))
      continue
    fi

    read -r -p "Panel/API host (domain or IP): " RB_HOST
    if [[ -z "${RB_HOST}" || "${RB_HOST}" == *"/"* || "${RB_HOST}" == *":"* ]]; then
      echo "[ERROR] Enter only the domain/IP here, without scheme, path, or port."
      server_count=$((server_count - 1))
      continue
    fi

    read -r -p "Panel/API port [${DEFAULT_PORT}]: " RB_PORT
    RB_PORT="${RB_PORT:-${DEFAULT_PORT}}"
    if ! [[ "${RB_PORT}" =~ ^[0-9]+$ ]] || (( RB_PORT < 1 || RB_PORT > 65535 )); then
      echo "[ERROR] Invalid TCP port."
      server_count=$((server_count - 1))
      continue
    fi

    read -r -p "RouteBox username [admin]: " RB_USER
    RB_USER="${RB_USER:-admin}"
    read -r -s -p "RouteBox password (leave empty if panel auth is disabled): " RB_PASS
    echo

    RB_VERIFY_TLS=1
    if [[ "${RB_SCHEME}" == "https" ]]; then
      read -r -p "Verify TLS certificate? [Y/n]: " VERIFY_ANSWER
      VERIFY_ANSWER="${VERIFY_ANSWER:-Y}"
      if [[ "${VERIFY_ANSWER}" =~ ^[Nn]$ ]]; then
        RB_VERIFY_TLS=0
      fi
    else
      RB_VERIFY_TLS=0
    fi

    RB_BASE_URL="${RB_SCHEME}://${RB_HOST}:${RB_PORT}"
    echo "==> Testing RouteBox API: ${RB_BASE_URL}"

    CURL_TLS_ARGS=()
    if [[ "${RB_VERIFY_TLS}" == "0" ]]; then
      CURL_TLS_ARGS+=("-k")
    fi

    # /api/status is a protected RouteBox API endpoint when panel auth is enabled.
    # RouteBox supports HTTP Basic for scripts, so this tests exactly the auth
    # mechanism used by the bot. With auth disabled, an empty credential pair is OK.
    RB_HTTP_CODE="$(curl -sS "${CURL_TLS_ARGS[@]}" \
      --connect-timeout 8 --max-time 20 \
      -u "${RB_USER}:${RB_PASS}" \
      -o /tmp/routebox-status.$$ -w '%{http_code}' \
      "${RB_BASE_URL}/api/status" 2>/tmp/routebox-curl-error.$$ || true)"

    if [[ "${RB_HTTP_CODE}" != "200" ]]; then
      echo "[ERROR] RouteBox API test failed (HTTP ${RB_HTTP_CODE:-connection-error})."
      if [[ -s /tmp/routebox-curl-error.$$ ]]; then
        sed 's/.*//' /tmp/routebox-curl-error.$$ >/dev/null || true
      fi
      rm -f /tmp/routebox-status.$$ /tmp/routebox-curl-error.$$
      echo "        Check host, port, scheme, firewall, username/password, and TLS settings."
      server_count=$((server_count - 1))
      continue
    fi

    rm -f /tmp/routebox-status.$$ /tmp/routebox-curl-error.$$
    echo "✓ RouteBox API connection successful"

    RB_USER_ENC="$(RBT_APP_KEY="${APP_KEY}" RBT_SECRET="${RB_USER}" php -r '
      $key=base64_decode(getenv("RBT_APP_KEY"),true);$plain=getenv("RBT_SECRET");
      if(!$key||strlen($key)!==32){exit(2);} $iv=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
      echo base64_encode($iv.sodium_crypto_secretbox($plain,$iv,$key));
    ' 2>/dev/null)"
    RB_PASS_ENC="$(RBT_APP_KEY="${APP_KEY}" RBT_SECRET="${RB_PASS}" php -r '
      $key=base64_decode(getenv("RBT_APP_KEY"),true);$plain=getenv("RBT_SECRET");
      if(!$key||strlen($key)!==32){exit(2);} $iv=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
      echo base64_encode($iv.sodium_crypto_secretbox($plain,$iv,$key));
    ' 2>/dev/null)"

    if [[ -z "${RB_USER_ENC}" || -z "${RB_PASS_ENC}" ]]; then
      echo "[ERROR] Failed to encrypt RouteBox credentials."
      exit 1
    fi

    # SQLite string literals use single quotes; escape defensively even though
    # names/URLs above are validated.
    sql_escape() { printf '%s' "$1" | sed "s/'/''/g"; }
    SQL_NAME="$(sql_escape "${RB_NAME}")"
    SQL_URL="$(sql_escape "${RB_BASE_URL}")"
    SQL_USER="$(sql_escape "${RB_USER_ENC}")"
    SQL_PASS="$(sql_escape "${RB_PASS_ENC}")"

    sqlite3 "${DB}" "INSERT INTO routebox_servers(name,base_url,user_enc,pass_enc,verify_tls,enabled,created_at) VALUES('${SQL_NAME}','${SQL_URL}','${SQL_USER}','${SQL_PASS}',${RB_VERIFY_TLS},1,$(date +%s));"
    echo "✓ RouteBox '${RB_NAME}' saved securely"

    read -r -p "Add another RouteBox server? [y/N]: " ADD_ANOTHER
    if [[ ! "${ADD_ANOTHER}" =~ ^[Yy]$ ]]; then
      break
    fi
    echo
  done

  unset TELEGRAM_TOKEN RB_PASS RB_USER_ENC RB_PASS_ENC TELEGRAM_ENC RBT_APP_KEY RBT_SECRET
  echo
  echo "✓ Initial configuration completed."
fi

# Never expose runtime configuration or SQLite through the web root.
chown -R www-data:www-data "${APP_DIR}/storage"
chmod 750 "${APP_DIR}/storage"
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
echo "============================================================"
echo "✅ RouteBox Telegram Bot Beta installed successfully."
echo "============================================================"
echo "🌐 Panel: http://YOUR_SERVER_IP/"
echo "📁 App:   ${APP_DIR}"
echo "📝 Logs:  journalctl -u ${SERVICE_NAME} -f"
echo "🔐 Telegram token and RouteBox credentials are encrypted at rest."
echo "⚠️  Secure the admin panel with HTTPS before public deployment."
echo "============================================================"
