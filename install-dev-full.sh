#!/usr/bin/env bash
set -Eeuo pipefail
APP_NAME="routebox-telegram-bot-dev"
APP_DIR="/opt/${APP_NAME}"
REPO="https://github.com/PardisMobile/routebox-telegram-bot.git"
BRANCH="feature/modular-services-ibsng"
SERVICE="${APP_NAME}.service"
WEB_SERVICE="${APP_NAME}-web"
STATE_DIR="/etc/${APP_NAME}"
IBSNG_API_PORT=1237
say(){ printf '\033[36m▶\033[0m %s\n' "$*"; }
ok(){ printf '\033[32m✓\033[0m %s\n' "$*"; }
fail(){ printf '\033[31m✗\033[0m %s\n' "$*" >&2; exit 1; }
[[ $EUID -eq 0 ]] || fail 'Run as root: sudo bash install-dev.sh'
. /etc/os-release
[[ "${ID:-}" == ubuntu && ${VERSION_ID%%.*} -ge 22 ]] || fail 'Ubuntu 22.04+ is required.'
export DEBIAN_FRONTEND=noninteractive
printf '\n\033[36m\033[1m╔══════════════════════════════════════════════════════════════╗\033[0m\n'
printf '\033[36m\033[1m║       RouteBox Telegram Bot — DEV / IBSng Test             ║\033[0m\n'
printf '\033[36m\033[1m║              Created & maintained by Amir Taheri            ║\033[0m\n'
printf '\033[36m\033[1m╚══════════════════════════════════════════════════════════════╝\033[0m\n\n'
say 'Installing the same base prerequisites as the production installer...'
apt-get update
apt-get install -y ca-certificates curl git iproute2 sqlite3 openssl qrencode php-cli php-curl php-sqlite3 php-mbstring php-xml php-opcache
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
(( ${PHP_VERSION%%.*} >= 8 )) || fail "PHP 8+ is required. Found ${PHP_VERSION}."
ok "Prerequisites ready: PHP ${PHP_VERSION}, SQLite, Git, curl, OpenSSL and qrencode."
say "Preparing isolated checkout: ${APP_DIR}"
mkdir -p "$STATE_DIR"
if [[ -d "$APP_DIR/.git" ]]; then git -C "$APP_DIR" fetch --prune origin "$BRANCH"; git -C "$APP_DIR" reset --hard "origin/$BRANCH" >/dev/null; else rm -rf "$APP_DIR"; git clone --depth 1 --single-branch --branch "$BRANCH" "$REPO" "$APP_DIR"; fi
cd "$APP_DIR"
VERSION="$(tr -d '[:space:]' < VERSION)"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] || fail 'Invalid or missing VERSION.'
mkdir -p config storage/logs
chown root:www-data config; chmod 750 config
if [[ ! -f config/config.php ]]; then
 APP_KEY="$(openssl rand -base64 32 | tr -d '\n')"; ADMIN_PASSWORD="$(openssl rand -hex 12)"; ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASSWORD")"
 cat > config/config.php <<PHP
<?php
return ['app_name'=>'RouteBox Telegram Bot DEV','version'=>'$VERSION','timezone'=>'Asia/Tehran','db'=>__DIR__.'/../storage/database.sqlite','app_key'=>'$APP_KEY','admin_user'=>'admin','admin_password_hash'=>'$ADMIN_HASH','telegram'=>['poll_timeout'=>25],'security'=>['session_name'=>'rbt_dev_session','cookie_secure'=>false]];
PHP
 printf '\n============================================================\nRouteBox Telegram Bot DEV — %s\nAdmin username: admin\nAdmin password: %s\nSAVE THIS PASSWORD SECURELY.\n============================================================\n\n' "$VERSION" "$ADMIN_PASSWORD"
fi
APP_KEY="$(php -r '$c=require $argv[1];echo $c["app_key"]??"";' config/config.php)"; [[ -n "$APP_KEY" ]] || fail 'Missing application encryption key.'
DB="$APP_DIR/storage/database.sqlite"; [[ -f "$DB" ]] || sqlite3 "$DB" < database/schema.sql
enc(){ RBT_APP_KEY="$APP_KEY" RBT_SECRET="$1" php -r '$k=base64_decode(getenv("RBT_APP_KEY"),true);$p=getenv("RBT_SECRET");$n=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);echo base64_encode($n.sodium_crypto_secretbox($p,$n,$k));'; }
sql(){ printf '%s' "$1" | sed "s/'/''/g"; }
if [[ "$(sqlite3 "$DB" "SELECT COUNT(*) FROM settings WHERE key='telegram_token' AND value<>'';" 2>/dev/null || echo 0)" != 1 ]]; then
 while :; do read -r -s -p 'Telegram Bot Token: ' TELEGRAM_TOKEN; echo; [[ -n "$TELEGRAM_TOKEN" ]] || continue; J="$(curl -fsS --connect-timeout 8 --max-time 20 "https://api.telegram.org/bot${TELEGRAM_TOKEN}/getMe" 2>/dev/null || true)"; OK="$(J="$J" php -r '$j=json_decode(getenv("J"),true);echo !empty($j["ok"])?1:0;' 2>/dev/null || echo 0)"; [[ "$OK" == 1 ]] && break; printf '\033[31m✗ Telegram token rejected.\033[0m\n'; done
 E="$(enc "$TELEGRAM_TOKEN")"; sqlite3 "$DB" "INSERT INTO settings(key,value) VALUES('telegram_token','$(sql "$E")') ON CONFLICT(key) DO UPDATE SET value=excluded.value;"; unset TELEGRAM_TOKEN E
fi
chown -R www-data:www-data storage; chown root:www-data config; chmod 750 config storage; chmod 640 config/config.php "$DB"
runuser -u www-data -- php -r 'require $argv[1];echo "✓ www-data can load config.php\n";' "$APP_DIR/config/config.php" || fail 'www-data cannot load config.php.'
say 'Checking PHP syntax across the development branch...'
while IFS= read -r -d '' f; do php -l "$f" >/dev/null || fail "PHP syntax error: $f"; done < <(find . -type f -name '*.php' -print0)
ok 'PHP syntax checks passed.'
command -v qrencode >/dev/null || fail 'qrencode is missing.'
[[ ! -f tools/patch-ibsng-sidebar.php ]] || { say 'Applying the safe IBSng sidebar integration...'; php tools/patch-ibsng-sidebar.php || fail 'Sidebar patch failed.'; }
cat > "/etc/systemd/system/$SERVICE" <<EOF
[Unit]
Description=RouteBox Telegram Bot DEV
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php $APP_DIR/worker.php
Restart=always
RestartSec=2
[Install]
WantedBy=multi-user.target
EOF
PORT=8092; while ss -ltnH "sport = :$PORT" 2>/dev/null | grep -q .; do PORT=$((PORT+1)); done
cat > "/etc/systemd/system/${WEB_SERVICE}@.service" <<EOF
[Unit]
Description=RouteBox Telegram Bot DEV Admin Panel on port %i
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php -d open_basedir=$APP_DIR:/tmp -S 0.0.0.0:%i -t $APP_DIR/public
Restart=always
RestartSec=2
ProtectHome=true
ReadWritePaths=$APP_DIR/storage
[Install]
WantedBy=multi-user.target
EOF
echo "$PORT" > "$STATE_DIR/web-port"
systemctl daemon-reload; systemctl enable --now "$SERVICE"; systemctl enable --now "${WEB_SERVICE}@${PORT}.service"; sleep 1
systemctl is-active --quiet "$SERVICE" || fail 'DEV Telegram service failed.'
systemctl is-active --quiet "${WEB_SERVICE}@${PORT}.service" || fail 'DEV Admin Panel failed.'
HTTP_CODE="$(curl -sS -o /tmp/rbt-dev-check.$$ -w '%{http_code}' "http://127.0.0.1:${PORT}/login.php" || true)"; rm -f /tmp/rbt-dev-check.$$
[[ "$HTTP_CODE" == 200 || "$HTTP_CODE" == 302 ]] || fail "Admin Panel health check failed: HTTP ${HTTP_CODE:-000}"
printf '\n\033[32m\033[1mDEV INSTALLATION COMPLETED\033[0m\n'
printf 'Version : %s\nPath    : %s\nBranch  : %s\nPanel   : http://SERVER-IP:%s\nIBSng API default: %s\n\n' "$VERSION" "$APP_DIR" "$BRANCH" "$PORT" "$IBSNG_API_PORT"
printf 'IBSng smoke test:\n  cd %s\n  php tools/ibsng-smoke-test.php IBSNG_IP ADMIN_USER ADMIN_PASSWORD\n\n' "$APP_DIR"
printf '\033[36mCreated & maintained by Amir Taheri\033[0m\n'
printf '\033[33mProduction /opt/routebox-telegram-bot was not stopped, reset, or modified.\033[0m\n'