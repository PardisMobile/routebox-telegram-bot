#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
REPO="https://github.com/PardisMobile/routebox-telegram-bot.git"
SERVICE="${APP_NAME}.service"
SITE="${APP_NAME}"

fail(){ echo "[ERROR] $*" >&2; exit 1; }
[[ $EUID -eq 0 ]] || fail "Run as root: sudo bash install-v2.sh"
. /etc/os-release
[[ "${ID:-}" == "ubuntu" ]] || fail "Ubuntu 22.04+ is required."
(( ${VERSION_ID%%.*} >= 22 )) || fail "Ubuntu 22.04+ is required."

echo "==> Installing dependencies..."
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl git nginx sqlite3 openssl php-cli php-fpm php-curl php-sqlite3 php-mbstring php-xml php-opcache
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
(( ${PHP_VERSION%%.*} >= 8 )) || fail "PHP 8+ is required. Found ${PHP_VERSION}."
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm.service"
PHP_FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
systemctl enable --now "${PHP_FPM_SERVICE}" || true
[[ -S "${PHP_FPM_SOCKET}" ]] || fail "PHP-FPM socket not found: ${PHP_FPM_SOCKET}"

if [[ -d "${APP_DIR}/.git" ]]; then
  echo "==> Updating application files..."
  git -C "${APP_DIR}" fetch --prune origin
  git -C "${APP_DIR}" reset --hard origin/main
else
  [[ ! -e "${APP_DIR}" || -z "$(find "${APP_DIR}" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]] || fail "${APP_DIR} is not empty."
  rm -rf "${APP_DIR}"
  git clone --depth 1 "${REPO}" "${APP_DIR}"
fi

mkdir -p "${APP_DIR}/config" "${APP_DIR}/storage/logs"

if [[ ! -f "${APP_DIR}/config/config.php" ]]; then
  APP_KEY="$(openssl rand -base64 32 | tr -d '\n')"
  ADMIN_PASSWORD="$(openssl rand -hex 12)"
  ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "${ADMIN_PASSWORD}")"
  cat > "${APP_DIR}/config/config.php" <<PHP
<?php
return [
 'app_name'=>'RouteBox Telegram Bot',
 'version'=>'0.1.0-beta.1',
 'timezone'=>'Asia/Tehran',
 'db'=>__DIR__.'/../storage/database.sqlite',
 'app_key'=>'${APP_KEY}',
 'admin_user'=>'admin',
 'admin_password_hash'=>'${ADMIN_HASH}',
 'telegram'=>['poll_timeout'=>25],
 'security'=>['session_name'=>'rbt_session','cookie_secure'=>false],
];
PHP
  echo
  echo "============================================================"
  echo " RouteBox Telegram Bot — 0.1.0-beta.1"
  echo "============================================================"
  echo "Admin username: admin"
  echo "Admin password: ${ADMIN_PASSWORD}"
  echo "SAVE THIS PASSWORD SECURELY."
  echo "============================================================"
fi

APP_KEY="$(php -r '$c=require $argv[1];echo $c["app_key"]??"";' "${APP_DIR}/config/config.php")"
[[ -n "${APP_KEY}" ]] || fail "Could not load application encryption key."
DB="${APP_DIR}/storage/database.sqlite"
if [[ ! -f "${DB}" ]]; then sqlite3 "${DB}" < "${APP_DIR}/database/schema.sql"; fi

enc(){ RBT_APP_KEY="${APP_KEY}" RBT_SECRET="$1" php -r '$k=base64_decode(getenv("RBT_APP_KEY"),true);$p=getenv("RBT_SECRET");if(!$k||strlen($k)!==32)exit(2);$n=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);echo base64_encode($n.sodium_crypto_secretbox($p,$n,$k));'; }
sql(){ printf '%s' "$1" | sed "s/'/''/g"; }

has_token="$(sqlite3 "${DB}" "SELECT COUNT(*) FROM settings WHERE key='telegram_token' AND value<>'';" 2>/dev/null || echo 0)"
has_servers="$(sqlite3 "${DB}" "SELECT COUNT(*) FROM routebox_servers;" 2>/dev/null || echo 0)"

if [[ "${has_token}" != 1 || "${has_servers}" == 0 ]]; then
  echo
  echo "=== Initial configuration ==="
  echo
  while :; do
    read -r -s -p "Telegram Bot Token: " TELEGRAM_TOKEN; echo
    [[ -n "${TELEGRAM_TOKEN}" ]] || { echo "[ERROR] Token cannot be empty."; continue; }
    TELEGRAM_JSON="$(curl -fsS --connect-timeout 8 --max-time 20 "https://api.telegram.org/bot${TELEGRAM_TOKEN}/getMe" 2>/dev/null || true)"
    TELEGRAM_OK="$(TELEGRAM_JSON="${TELEGRAM_JSON}" php -r '$j=json_decode(getenv("TELEGRAM_JSON"),true);echo !empty($j["ok"])?1:0;' 2>/dev/null || echo 0)"
    [[ "${TELEGRAM_OK}" == 1 ]] || { echo "[ERROR] Telegram rejected the token."; continue; }
    BOT_USERNAME="$(TELEGRAM_JSON="${TELEGRAM_JSON}" php -r '$j=json_decode(getenv("TELEGRAM_JSON"),true);echo $j["result"]["username"]??"";' 2>/dev/null || true)"
    echo "✓ Telegram connection successful: @${BOT_USERNAME}"
    break
  done
  TELEGRAM_ENC="$(enc "${TELEGRAM_TOKEN}")" || fail "Could not encrypt Telegram token."
  sqlite3 "${DB}" "INSERT INTO settings(key,value) VALUES('telegram_token','$(sql "${TELEGRAM_ENC}")') ON CONFLICT(key) DO UPDATE SET value=excluded.value;"

  server_no=0
  while :; do
    server_no=$((server_no+1)); echo; echo "--- RouteBox #${server_no} ---"
    read -r -p "Server name [RouteBox-${server_no}]: " RB_NAME; RB_NAME="${RB_NAME:-RouteBox-${server_no}}"

    read -r -p "RouteBox Panel URL (e.g. https://panel.example.com:8443 or http://192.0.2.10:8080): " RB_BASE
    RB_BASE="${RB_BASE%/}"
    if [[ ! "${RB_BASE}" =~ ^https?://[^/[:space:]]+$ ]]; then
      echo "[ERROR] Enter the complete RouteBox Panel URL, including http:// or https:// and optional port."; server_no=$((server_no-1)); continue
    fi
    RB_SCHEME="${RB_BASE%%://*}"

    read -r -p "RouteBox username [admin]: " RB_USER; RB_USER="${RB_USER:-admin}"
    read -r -s -p "RouteBox password (leave empty if authentication is disabled): " RB_PASS; echo

    RB_VERIFY_TLS=1
    if [[ "${RB_SCHEME}" == "https" ]]; then
      read -r -p "Verify TLS certificate? [Y/n]: " V; V="${V:-Y}"; [[ "$V" =~ ^[Nn]$ ]] && RB_VERIFY_TLS=0
    else
      RB_VERIFY_TLS=0
    fi

    echo "==> Testing RouteBox API and authentication: ${RB_BASE}"
    TEST_DIR="$(mktemp -d)"
    COOKIE_JAR="${TEST_DIR}/cookies.txt"
    trap 'rm -rf "${TEST_DIR:-}"' RETURN
    CURL_ARGS=( -sS --connect-timeout 8 --max-time 25 )
    [[ ${RB_VERIFY_TLS} -eq 0 ]] && CURL_ARGS+=( -k )

    # Prefer the real RouteBox panel session API. Basic Auth is retained as a
    # documented script fallback and as support for auth-disabled router mode.
    SESSION_OK=0
    if [[ -n "${RB_USER}" && -n "${RB_PASS}" ]]; then
      LOGIN_CODE="$(curl "${CURL_ARGS[@]}" -c "${COOKIE_JAR}" -H 'Content-Type: application/json' -o "${TEST_DIR}/login.json" -w '%{http_code}' -X POST "${RB_BASE}/api/auth/login" --data "$(php -r 'echo json_encode(["username"=>$argv[1],"password"=>$argv[2]], JSON_UNESCAPED_SLASHES);' "${RB_USER}" "${RB_PASS}")" 2>/dev/null || true)"
      if [[ "${LOGIN_CODE}" =~ ^2[0-9][0-9]$ ]] && grep -Eq '^[^#].*=|^#HttpOnly_' "${COOKIE_JAR}" 2>/dev/null; then
        SESSION_OK=1
        echo "✓ RouteBox session login OK"
      else
        echo "ℹ Session login was not available; trying HTTP Basic fallback"
      fi
    else
      echo "ℹ No RouteBox credentials supplied; testing unauthenticated router mode"
    fi

    rb_get(){
      local path="$1" label="$2" code
      if [[ ${SESSION_OK} -eq 1 ]]; then
        code="$(curl "${CURL_ARGS[@]}" -b "${COOKIE_JAR}" -o "${TEST_DIR}/response.json" -w '%{http_code}' "${RB_BASE}${path}" 2>/dev/null || true)"
      elif [[ -n "${RB_USER}" || -n "${RB_PASS}" ]]; then
        code="$(curl "${CURL_ARGS[@]}" -u "${RB_USER}:${RB_PASS}" -o "${TEST_DIR}/response.json" -w '%{http_code}' "${RB_BASE}${path}" 2>/dev/null || true)"
      else
        code="$(curl "${CURL_ARGS[@]}" -o "${TEST_DIR}/response.json" -w '%{http_code}' "${RB_BASE}${path}" 2>/dev/null || true)"
      fi
      if [[ "${code}" != 2* ]]; then
        echo "[ERROR] ${label} failed (HTTP ${code:-connection-error})."
        return 1
      fi
      echo "✓ ${label} OK"
    }

    rb_write(){
      local method="$1" path="$2" body="$3" out="$4" code
      if [[ ${SESSION_OK} -eq 1 ]]; then
        code="$(curl "${CURL_ARGS[@]}" -b "${COOKIE_JAR}" -H 'Content-Type: application/json' -o "${out}" -w '%{http_code}' -X "${method}" "${RB_BASE}${path}" --data "${body}" 2>/dev/null || true)"
      elif [[ -n "${RB_USER}" || -n "${RB_PASS}" ]]; then
        code="$(curl "${CURL_ARGS[@]}" -u "${RB_USER}:${RB_PASS}" -H 'Content-Type: application/json' -o "${out}" -w '%{http_code}' -X "${method}" "${RB_BASE}${path}" --data "${body}" 2>/dev/null || true)"
      else
        code="$(curl "${CURL_ARGS[@]}" -H 'Content-Type: application/json' -o "${out}" -w '%{http_code}' -X "${method}" "${RB_BASE}${path}" --data "${body}" 2>/dev/null || true)"
      fi
      [[ "${code}" == 2* ]] || { echo "[ERROR] ${method} ${path} failed (HTTP ${code:-connection-error})."; return 1; }
    }

    rb_get "/api/health" "RouteBox health API" || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue; }
    rb_get "/api/status" "RouteBox status API" || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue; }
    rb_get "/api/awg/status" "AmneziaWG status API" || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue; }
    rb_get "/api/awg/peers" "AmneziaWG peers API" || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue; }
    rb_get "/api/settings" "RouteBox settings API" || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue; }

    # Full end-to-end smoke test: create one temporary peer, retrieve its real
    # client configuration, then delete it. This catches auth, write access,
    # AWG availability, key generation, config rendering and URL decoding issues
    # before the server is accepted by the installer.
    TEST_PEER="rbt-install-test-$(date +%s)-$$"
    TEST_BODY="$(php -r 'echo json_encode(["name"=>$argv[1]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);' "${TEST_PEER}")"
    rb_write POST "/api/awg/peers" "${TEST_BODY}" "${TEST_DIR}/create.json" || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue; }
    TEST_PUBLIC_KEY="$(php -r '$j=json_decode(file_get_contents($argv[1]),true);$d=$j["data"]??$j;echo is_array($d)?($d["public_key"]??$d["publicKey"]??""):"";' "${TEST_DIR}/create.json" 2>/dev/null || true)"
    if [[ -z "${TEST_PUBLIC_KEY}" ]]; then
      # Be tolerant of future RouteBox response envelopes: locate the peer by
      # its unique test name in the roster if the POST response omits the key.
      rb_get "/api/awg/peers" "AmneziaWG peer roster after smoke-test create" || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue; }
      TEST_PUBLIC_KEY="$(php -r '$j=json_decode(file_get_contents($argv[1]),true);$d=$j["data"]??$j;if(is_array($d)){foreach($d as $p){if(is_array($p)&&(($p["name"]??"")===$argv[2])){echo $p["public_key"]??$p["publicKey"]??"";break;}}}' "${TEST_DIR}/response.json" "${TEST_PEER}" 2>/dev/null || true)"
    fi
    if [[ -z "${TEST_PUBLIC_KEY}" ]]; then
      echo "[ERROR] RouteBox created the smoke-test peer but did not return its public key."
      rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue
    fi

    TEST_ENCODED_KEY="$(php -r 'echo rawurlencode($argv[1]);' "${TEST_PUBLIC_KEY}")"
    rb_get "/api/awg/peers/${TEST_ENCODED_KEY}/config" "AmneziaWG client config export" || {
      if [[ ${SESSION_OK} -eq 1 ]]; then curl "${CURL_ARGS[@]}" -b "${COOKIE_JAR}" -X DELETE "${RB_BASE}/api/awg/peers/${TEST_ENCODED_KEY}" >/dev/null 2>&1 || true; else curl "${CURL_ARGS[@]}" -u "${RB_USER}:${RB_PASS}" -X DELETE "${RB_BASE}/api/awg/peers/${TEST_ENCODED_KEY}" >/dev/null 2>&1 || true; fi
      rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); continue;
    }

    if [[ ${SESSION_OK} -eq 1 ]]; then
      DELETE_CODE="$(curl "${CURL_ARGS[@]}" -b "${COOKIE_JAR}" -o /dev/null -w '%{http_code}' -X DELETE "${RB_BASE}/api/awg/peers/${TEST_ENCODED_KEY}" 2>/dev/null || true)"
    else
      DELETE_CODE="$(curl "${CURL_ARGS[@]}" -u "${RB_USER}:${RB_PASS}" -o /dev/null -w '%{http_code}' -X DELETE "${RB_BASE}/api/awg/peers/${TEST_ENCODED_KEY}" 2>/dev/null || true)"
    fi
    [[ "${DELETE_CODE}" == 2* ]] || { rm -rf "${TEST_DIR}"; server_no=$((server_no-1)); echo "[ERROR] Smoke-test peer cleanup failed (HTTP ${DELETE_CODE:-connection-error})."; continue; }
    echo "✓ Full RouteBox + AmneziaWG create/export/delete smoke test OK"

    rm -rf "${TEST_DIR}"
    trap - RETURN

    UENC="$(enc "${RB_USER}")"; PENC="$(enc "${RB_PASS}")"
    [[ -n "$UENC" && -n "$PENC" ]] || fail "Could not encrypt RouteBox credentials."
    sqlite3 "${DB}" "INSERT INTO routebox_servers(name,base_url,user_enc,pass_enc,verify_tls,enabled,created_at) VALUES('$(sql "$RB_NAME")','$(sql "$RB_BASE")','$(sql "$UENC")','$(sql "$PENC")',${RB_VERIFY_TLS},1,$(date +%s));"
    echo "✓ RouteBox '${RB_NAME}' saved securely"
    read -r -p "Add another RouteBox server? [y/N]: " MORE
    [[ "$MORE" =~ ^[Yy]$ ]] || break
  done
  unset TELEGRAM_TOKEN TELEGRAM_ENC RB_PASS UENC PENC APP_KEY
fi

chown -R www-data:www-data "${APP_DIR}/storage"
chown root:www-data "${APP_DIR}/config/config.php"
chmod 640 "${APP_DIR}/config/config.php" "${DB}"
chmod 750 "${APP_DIR}/config" "${APP_DIR}/storage"

install -m 0644 "${APP_DIR}/systemd/routebox-telegram-bot.service" "/etc/systemd/system/${SERVICE}"
systemctl daemon-reload
systemctl enable --now "${SERVICE}"

cat > "/etc/nginx/sites-available/${SITE}" <<EOF
server {
    listen 80 default_server;
    server_name _;
    root ${APP_DIR}/public;
    index index.php;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCKET};
    }
    location ~ /\. { deny all; }
}
EOF
ln -sf "/etc/nginx/sites-available/${SITE}" "/etc/nginx/sites-enabled/${SITE}"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

php -l "${APP_DIR}/worker.php" >/dev/null || fail "worker.php syntax check failed."
php -l "${APP_DIR}/src/RouteBoxClient.php" >/dev/null || fail "RouteBoxClient.php syntax check failed."
echo
echo "✓ Installation completed successfully."
echo "✓ Telegram and RouteBox authentication were verified during setup."
echo "✓ Full AmneziaWG create → config → delete smoke test passed."
echo "✓ RouteBox mode, scheme, host and port are not separate inputs."
echo "✓ Enter the exact URL you already use to open the RouteBox panel."
echo "Admin panel: http://YOUR_SERVER_IP/"
