#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
REPO="https://github.com/PardisMobile/routebox-telegram-bot.git"
SERVICE="${APP_NAME}.service"
WEB_SERVICE="${APP_NAME}-web"
STATE_DIR="/etc/${APP_NAME}"

fail(){ echo "[ERROR] $*" >&2; exit 1; }
[[ $EUID -eq 0 ]] || fail "Run as root: sudo bash install-v2.sh"
. /etc/os-release
[[ "${ID:-}" == "ubuntu" && ${VERSION_ID%%.*} -ge 22 ]] || fail "Ubuntu 22.04+ is required."

PORT80="$(ss -ltnpH 'sport = :80' 2>/dev/null || true)"
if [[ -n "$PORT80" ]]; then WEB_MODE=standalone; else WEB_MODE=nginx; fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl git iproute2 sqlite3 openssl php-cli php-curl php-sqlite3 php-mbstring php-xml php-opcache
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
(( ${PHP_VERSION%%.*} >= 8 )) || fail "PHP 8+ is required. Found ${PHP_VERSION}."

if [[ "$WEB_MODE" == nginx ]]; then
  apt-get install -y nginx php-fpm
  PHP_FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
  systemctl enable --now "php${PHP_VERSION}-fpm.service"
  [[ -S "$PHP_FPM_SOCKET" ]] || fail "PHP-FPM socket not found: $PHP_FPM_SOCKET"
else
  echo "ℹ Port 80 is already in use. Existing service will not be stopped or reconfigured."
  echo "ℹ Bot Admin Panel will use an independent port instead of Nginx."
fi

if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" fetch --prune origin
  git -C "$APP_DIR" reset --hard origin/main
else
  [[ ! -e "$APP_DIR" || -z "$(find "$APP_DIR" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]] || fail "$APP_DIR is not empty."
  rm -rf "$APP_DIR"
  git clone --depth 1 "$REPO" "$APP_DIR"
fi

mkdir -p "$APP_DIR/config" "$APP_DIR/storage/logs" "$STATE_DIR"

if [[ ! -f "$APP_DIR/config/config.php" ]]; then
  APP_KEY="$(openssl rand -base64 32 | tr -d '\n')"
  ADMIN_PASSWORD="$(openssl rand -hex 12)"
  ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASSWORD")"
  cat > "$APP_DIR/config/config.php" <<PHP
<?php
return [
 'app_name'=>'RouteBox Telegram Bot',
 'version'=>'0.1.0-beta.1',
 'timezone'=>'Asia/Tehran',
 'db'=>__DIR__.'/../storage/database.sqlite',
 'app_key'=>'$APP_KEY',
 'admin_user'=>'admin',
 'admin_password_hash'=>'$ADMIN_HASH',
 'telegram'=>['poll_timeout'=>25],
 'security'=>['session_name'=>'rbt_session','cookie_secure'=>false],
];
PHP
  echo "============================================================"
  echo " RouteBox Telegram Bot — 0.1.0-beta.1"
  echo "============================================================"
  echo "Admin username: admin"
  echo "Admin password: $ADMIN_PASSWORD"
  echo "SAVE THIS PASSWORD SECURELY."
  echo "============================================================"
fi

APP_KEY="$(php -r '$c=require $argv[1];echo $c["app_key"]??"";' "$APP_DIR/config/config.php")"
[[ -n "$APP_KEY" ]] || fail "Could not load application encryption key."
DB="$APP_DIR/storage/database.sqlite"
[[ -f "$DB" ]] || sqlite3 "$DB" < "$APP_DIR/database/schema.sql"

enc(){ RBT_APP_KEY="$APP_KEY" RBT_SECRET="$1" php -r '$k=base64_decode(getenv("RBT_APP_KEY"),true);$p=getenv("RBT_SECRET");if(!$k||strlen($k)!==32)exit(2);$n=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);echo base64_encode($n.sodium_crypto_secretbox($p,$n,$k));'; }
sql(){ printf '%s' "$1" | sed "s/'/''/g"; }

has_token="$(sqlite3 "$DB" "SELECT COUNT(*) FROM settings WHERE key='telegram_token' AND value<>'';" 2>/dev/null || echo 0)"
has_servers="$(sqlite3 "$DB" "SELECT COUNT(*) FROM routebox_servers;" 2>/dev/null || echo 0)"

if [[ "$has_token" != 1 || "$has_servers" == 0 ]]; then
  while :; do
    read -r -s -p "Telegram Bot Token: " TELEGRAM_TOKEN; echo
    [[ -n "$TELEGRAM_TOKEN" ]] || { echo "[ERROR] Token cannot be empty."; continue; }
    TELEGRAM_JSON="$(curl -fsS --connect-timeout 8 --max-time 20 "https://api.telegram.org/bot${TELEGRAM_TOKEN}/getMe" 2>/dev/null || true)"
    TELEGRAM_OK="$(TELEGRAM_JSON="$TELEGRAM_JSON" php -r '$j=json_decode(getenv("TELEGRAM_JSON"),true);echo !empty($j["ok"])?1:0;' 2>/dev/null || echo 0)"
    [[ "$TELEGRAM_OK" == 1 ]] || { echo "[ERROR] Telegram rejected the token."; continue; }
    BOT_USERNAME="$(TELEGRAM_JSON="$TELEGRAM_JSON" php -r '$j=json_decode(getenv("TELEGRAM_JSON"),true);echo $j["result"]["username"]??"";' 2>/dev/null || true)"
    curl -fsS -X POST "https://api.telegram.org/bot${TELEGRAM_TOKEN}/deleteWebhook" >/dev/null 2>&1 || true
    echo "✓ Telegram connection successful: @${BOT_USERNAME}"
    echo "✓ Token accepted and will be stored encrypted."
    break
  done
  TELEGRAM_ENC="$(enc "$TELEGRAM_TOKEN")" || fail "Could not encrypt Telegram token."
  sqlite3 "$DB" "INSERT INTO settings(key,value) VALUES('telegram_token','$(sql "$TELEGRAM_ENC")') ON CONFLICT(key) DO UPDATE SET value=excluded.value;"

  n=0
  while :; do
    n=$((n+1)); echo; echo "--- RouteBox #$n ---"
    read -r -p "Server name [RouteBox-$n]: " RB_NAME; RB_NAME="${RB_NAME:-RouteBox-$n}"
    read -r -p "RouteBox Panel URL: " RB_BASE; RB_BASE="${RB_BASE%/}"
    [[ "$RB_BASE" =~ ^https?://[^/[:space:]]+$ ]] || { echo "[ERROR] Use the exact URL that opens the RouteBox panel, including http:// or https:// and optional port."; n=$((n-1)); continue; }
    read -r -p "RouteBox username [admin]: " RB_USER; RB_USER="${RB_USER:-admin}"
    read -r -s -p "RouteBox password (leave empty if authentication is disabled): " RB_PASS; echo
    RB_VERIFY_TLS=1
    if [[ "$RB_BASE" == http://* ]]; then RB_VERIFY_TLS=0; else read -r -p "Verify TLS certificate? [Y/n]: " V; V="${V:-Y}"; [[ "$V" =~ ^[Nn]$ ]] && RB_VERIFY_TLS=0; fi

    echo "==> Running the real RouteBox client validation..."
    RB_BASE="$RB_BASE" RB_USER="$RB_USER" RB_PASS="$RB_PASS" RB_VERIFY_TLS="$RB_VERIFY_TLS" php -r '
      require $argv[1];
      $c=new RouteBoxClient(getenv("RB_BASE"),getenv("RB_USER")?:"",getenv("RB_PASS")?:"",getenv("RB_VERIFY_TLS")==="1");
      $c->validateIntegration();
      $r=$c->smokeTest("rbt-install-test");
      echo "✓ RouteBox session/API validation OK\n";
      echo "✓ Full AmneziaWG create/export/delete smoke test OK (".$r["config_bytes"]." bytes)\n";
    ' "$APP_DIR/src/RouteBoxClient.php" || { echo "[ERROR] RouteBox validation failed. Nothing was saved for this server."; n=$((n-1)); continue; }

    UENC="$(enc "$RB_USER")"; PENC="$(enc "$RB_PASS")"
    sqlite3 "$DB" "INSERT INTO routebox_servers(name,base_url,user_enc,pass_enc,verify_tls,enabled,created_at) VALUES('$(sql "$RB_NAME")','$(sql "$RB_BASE")','$(sql "$UENC")','$(sql "$PENC")',$RB_VERIFY_TLS,1,$(date +%s));"
    echo "✓ RouteBox '$RB_NAME' saved securely"
    read -r -p "Add another RouteBox server? [y/N]: " MORE
    [[ "$MORE" =~ ^[Yy]$ ]] || break
  done
  unset TELEGRAM_TOKEN TELEGRAM_ENC RB_PASS UENC PENC APP_KEY
fi

chown -R www-data:www-data "$APP_DIR/storage"
chown root:www-data "$APP_DIR/config/config.php"
chmod 640 "$APP_DIR/config/config.php" "$DB"
chmod 750 "$APP_DIR/config" "$APP_DIR/storage"

install -m 0644 "$APP_DIR/systemd/routebox-telegram-bot.service" "/etc/systemd/system/$SERVICE"
systemctl daemon-reload
systemctl enable --now "$SERVICE"

if [[ "$WEB_MODE" == nginx ]]; then
  cat > "/etc/nginx/sites-available/$APP_NAME" <<EOF_NGINX
server {
    listen 80 default_server;
    server_name _;
    root $APP_DIR/public;
    index index.php;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCKET; }
    location ~ /\. { deny all; }
}
EOF_NGINX
  ln -sf "/etc/nginx/sites-available/$APP_NAME" "/etc/nginx/sites-enabled/$APP_NAME"
  rm -f /etc/nginx/sites-enabled/default
  nginx -t
  systemctl reload nginx
  echo nginx > "$STATE_DIR/web-mode"
  rm -f "$STATE_DIR/web-port"
  ADMIN_URL="http://YOUR_SERVER_IP/"
else
  ADMIN_PORT=8090
  while ss -ltnH "sport = :$ADMIN_PORT" 2>/dev/null | grep -q .; do ADMIN_PORT=$((ADMIN_PORT+1)); done
  cat > "/etc/systemd/system/${WEB_SERVICE}@.service" <<EOF_WEB
[Unit]
Description=RouteBox Telegram Bot Admin Panel on port %i
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php -S 0.0.0.0:%i -t $APP_DIR/public
Restart=always
RestartSec=2
NoNewPrivileges=true
ProtectHome=true
ReadWritePaths=$APP_DIR/storage

[Install]
WantedBy=multi-user.target
EOF_WEB
  echo standalone > "$STATE_DIR/web-mode"
  echo "$ADMIN_PORT" > "$STATE_DIR/web-port"
  systemctl daemon-reload
  systemctl enable --now "${WEB_SERVICE}@${ADMIN_PORT}.service"
  ADMIN_URL="http://YOUR_SERVER_IP:${ADMIN_PORT}/"
  echo "✓ Port 80 was left untouched. Admin Panel uses port $ADMIN_PORT."
fi

php -l "$APP_DIR/worker.php" >/dev/null
php -l "$APP_DIR/src/RouteBoxClient.php" >/dev/null

echo
echo "✓ Installation completed successfully."
echo "✓ Telegram validation passed."
echo "✓ RouteBox API + AWG create/export/delete smoke test passed."
echo "✓ Admin Panel: $ADMIN_URL"
