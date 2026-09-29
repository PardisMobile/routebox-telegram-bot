#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR=/opt/routebox-telegram-bot
SERVICE=routebox-telegram-bot.service
APP_NAME=routebox-telegram-bot
WEB_SERVICE=${APP_NAME}-web
STATE_DIR=/etc/${APP_NAME}
[[ $EUID -eq 0 ]] || { echo '[ERROR] Run as root: sudo bash update.sh'; exit 1; }
[[ -d "$APP_DIR/.git" ]] || { echo "[ERROR] $APP_DIR is not a Git checkout."; exit 1; }
cd "$APP_DIR"
git fetch --prune origin
git reset --hard origin/main
mkdir -p storage/logs "$STATE_DIR"
chown -R www-data:www-data storage
chmod 750 storage
install -m 0644 systemd/routebox-telegram-bot.service "/etc/systemd/system/$SERVICE"
[[ -f storage/database.sqlite ]] && sqlite3 storage/database.sqlite < database/schema.sql || true
chown www-data:www-data storage/database.sqlite 2>/dev/null || true
chmod 640 storage/database.sqlite 2>/dev/null || true
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
MODE=$(cat "$STATE_DIR/web-mode" 2>/dev/null || true)
PORT80=$(ss -ltnpH 'sport = :80' 2>/dev/null || true)
if [[ -z "$MODE" ]]; then
  [[ -f "/etc/nginx/sites-enabled/$APP_NAME" && -z "$PORT80" ]] && MODE=nginx || MODE=standalone
fi
[[ -z "$PORT80" || "$MODE" != nginx ]] || MODE=standalone
if [[ "$MODE" == nginx ]]; then
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y nginx php-fpm
  PHP_FPM_SOCKET=/run/php/php${PHP_VERSION}-fpm.sock
  systemctl enable --now "php${PHP_VERSION}-fpm.service"
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
  nginx -t && systemctl reload nginx
  echo nginx > "$STATE_DIR/web-mode"
else
  PORT=$(cat "$STATE_DIR/web-port" 2>/dev/null || echo 8090)
  [[ "$PORT" =~ ^[0-9]+$ ]] || PORT=8090
  if ss -ltnH "sport = :$PORT" 2>/dev/null | grep -q . && [[ ! -f "/etc/systemd/system/${WEB_SERVICE}@${PORT}.service" ]]; then
    while ss -ltnH "sport = :$PORT" 2>/dev/null | grep -q .; do PORT=$((PORT+1)); done
  fi
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
  rm -f "/etc/nginx/sites-enabled/$APP_NAME" "/etc/nginx/sites-available/$APP_NAME"
  echo standalone > "$STATE_DIR/web-mode"
  echo "$PORT" > "$STATE_DIR/web-port"
  systemctl daemon-reload
  systemctl enable --now "${WEB_SERVICE}@${PORT}.service"
  systemctl is-active --quiet "${WEB_SERVICE}@${PORT}.service" || { journalctl -u "${WEB_SERVICE}@${PORT}.service" -n 50 --no-pager; exit 1; }
fi
systemctl daemon-reload
systemctl enable --now "$SERVICE"
php -l worker.php >/dev/null
php -l src/RouteBoxClient.php >/dev/null
bash -n install.sh install-v2.sh update.sh uninstall.sh
systemctl restart "$SERVICE"
sleep 1
systemctl is-active --quiet "$SERVICE" || { journalctl -u "$SERVICE" -n 80 --no-pager; exit 1; }
echo "✓ Update completed successfully."
[[ "$MODE" == standalone ]] && echo "✓ Admin Panel: http://YOUR_SERVER_IP:$(cat "$STATE_DIR/web-port")/" || echo "✓ Admin Panel: http://YOUR_SERVER_IP/"
