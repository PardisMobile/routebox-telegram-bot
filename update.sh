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

# Install the restricted root updater used by the Admin Panel. The web user
# can run only this fixed wrapper through sudo; it cannot run arbitrary root commands.
install -m 0755 admin-update.sh /usr/local/sbin/routebox-telegram-bot-update
cat > /etc/sudoers.d/routebox-telegram-bot-update <<'EOF_SUDO'
www-data ALL=(root) NOPASSWD: /usr/local/sbin/routebox-telegram-bot-update
EOF_SUDO
chmod 0440 /etc/sudoers.d/routebox-telegram-bot-update
visudo -cf /etc/sudoers.d/routebox-telegram-bot-update >/dev/null

# Beta 2+ uses the independent PHP listener. Do not install, start, stop,
# reload or configure Nginx/Apache as part of a Bot update.
OLD_PORT=$(cat "$STATE_DIR/web-port" 2>/dev/null || echo 8090)
if [[ "$OLD_PORT" =~ ^[0-9]+$ ]]; then
  systemctl disable --now "${WEB_SERVICE}@${OLD_PORT}.service" >/dev/null 2>&1 || true
fi
rm -f "/etc/nginx/sites-enabled/$APP_NAME" "/etc/nginx/sites-available/$APP_NAME" 2>/dev/null || true

PORT=$OLD_PORT
[[ "$PORT" =~ ^[0-9]+$ ]] || PORT=8090
if ss -ltnH "sport = :$PORT" 2>/dev/null | grep -q .; then
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

echo standalone > "$STATE_DIR/web-mode"
echo "$PORT" > "$STATE_DIR/web-port"
systemctl daemon-reload
systemctl enable --now "${WEB_SERVICE}@${PORT}.service"
systemctl is-active --quiet "${WEB_SERVICE}@${PORT}.service" || { journalctl -u "${WEB_SERVICE}@${PORT}.service" -n 50 --no-pager; exit 1; }

systemctl daemon-reload
systemctl enable --now "$SERVICE"
php -l worker.php >/dev/null
php -l src/RouteBoxClient.php >/dev/null
php -l public/index.php >/dev/null
php -l public/update.php >/dev/null
bash -n install.sh install-v2.sh update.sh uninstall.sh admin-update.sh
systemctl restart "$SERVICE"
sleep 1
systemctl is-active --quiet "$SERVICE" || { journalctl -u "$SERVICE" -n 80 --no-pager; exit 1; }
echo "✓ Update completed successfully."
echo "✓ Existing Apache/Nginx/RouteBox services were not started, stopped or reconfigured."
echo "✓ Admin Panel: http://YOUR_SERVER_IP:$PORT/"
echo "✓ Admin Panel updater: /update.php"
