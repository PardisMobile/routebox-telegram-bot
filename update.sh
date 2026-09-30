#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR=/opt/routebox-telegram-bot
SERVICE=routebox-telegram-bot.service
APP_NAME=routebox-telegram-bot
WEB_SERVICE=${APP_NAME}-web
TLS_SERVICE=${APP_NAME}-tls.service
MUX_SERVICE=${APP_NAME}-mux.service
STATE_DIR=/etc/${APP_NAME}
[[ $EUID -eq 0 ]] || { echo '[ERROR] Run as root: sudo bash update.sh'; exit 1; }
[[ -d "$APP_DIR/.git" ]] || { echo "[ERROR] $APP_DIR is not a Git checkout." >&2; exit 1; }
cd "$APP_DIR"
git fetch --prune origin
git reset --hard origin/main

# The updater itself is part of the repository. If this invocation started from
# an older copy, restart from the freshly pulled file so stale service names or
# old logic cannot continue running from memory.
if [[ "${ROUTEBOX_UPDATE_REEXEC:-0}" != "1" ]]; then
  export ROUTEBOX_UPDATE_REEXEC=1
  exec bash "$APP_DIR/update.sh"
fi

mkdir -p storage/logs "$STATE_DIR"
chown -R www-data:www-data storage
chmod 750 storage
install -m 0644 systemd/routebox-telegram-bot.service "/etc/systemd/system/$SERVICE"
[[ -f storage/database.sqlite ]] && sqlite3 storage/database.sqlite < database/schema.sql || true
chown www-data:www-data storage/database.sqlite 2>/dev/null || true
chmod 640 storage/database.sqlite 2>/dev/null || true

# Install the restricted root updater used by the Admin Panel.
install -m 0755 admin-update.sh /usr/local/sbin/routebox-telegram-bot-update
cat > /etc/sudoers.d/routebox-telegram-bot-update <<'EOF_SUDO'
www-data ALL=(root) NOPASSWD: /usr/local/sbin/routebox-telegram-bot-update
EOF_SUDO
chmod 0440 /etc/sudoers.d/routebox-telegram-bot-update
visudo -cf /etc/sudoers.d/routebox-telegram-bot-update >/dev/null

# Preserve the existing public Admin Panel port. Both the TLS terminator and
# the HTTP+HTTPS multiplexer must be stopped before checking port availability;
# otherwise the updater could mistake the Bot's own mux for a port conflict and
# incorrectly move the panel to another port.
OLD_PORT=$(cat "$STATE_DIR/web-port" 2>/dev/null || echo 8090)
if [[ "$OLD_PORT" =~ ^[0-9]+$ ]]; then
  systemctl stop "$MUX_SERVICE" >/dev/null 2>&1 || true
  systemctl stop "$TLS_SERVICE" >/dev/null 2>&1 || true
  systemctl disable --now "${WEB_SERVICE}@${OLD_PORT}.service" >/dev/null 2>&1 || true
fi
rm -f "/etc/nginx/sites-enabled/$APP_NAME" "/etc/nginx/sites-available/$APP_NAME" 2>/dev/null || true

PORT=$OLD_PORT
[[ "$PORT" =~ ^[0-9]+$ ]] || PORT=8090
# At this point the Bot-owned listeners are stopped. Do not silently choose a
# different port for a valid existing installation: if another process owns it,
# fail instead of changing the public address.
if ss -ltnH "sport = :$PORT" 2>/dev/null | grep -q .; then
  echo "[ERROR] Existing Admin Panel port $PORT is occupied by another process." >&2
  ss -ltnp "sport = :$PORT" >&2 || true
  exit 1
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
# Keep the PHP panel filesystem sandboxed, but allow it to see the root-owned
# updater path used by the restricted sudo rule below.
ExecStart=/usr/bin/php -d open_basedir=$APP_DIR:/tmp:/usr/local/sbin -S 0.0.0.0:%i -t $APP_DIR/public
Restart=always
RestartSec=2
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
bash -n install.sh install-v2.sh update.sh uninstall.sh admin-update.sh setup-routebox-tls.sh
systemctl restart "$SERVICE"
sleep 1
systemctl is-active --quiet "$SERVICE" || { journalctl -u "$SERVICE" -n 80 --no-pager; exit 1; }

# Restore/reapply the optional RouteBox certificate integration. If the RouteBox
# panel certificate is not available, setup-routebox-tls.sh exits successfully
# and HTTP remains available.
if [[ -f setup-routebox-tls.sh ]]; then
  bash setup-routebox-tls.sh || { echo '[WARN] RouteBox TLS integration could not be restored; HTTP panel remains available.' >&2; }
fi

echo "✓ Update completed successfully."
echo "✓ Existing Apache/Nginx/RouteBox services were not reconfigured."
echo "✓ Telegram worker: systemctl restart routebox-telegram-bot.service"
if [[ "$(cat "$STATE_DIR/web-tls-mode" 2>/dev/null || true)" == "routebox-panel-acme-multiplex" ]]; then
  echo "✓ Admin Panel HTTP+HTTPS: same port $PORT"
else
  echo "✓ Admin Panel HTTP: http://YOUR_SERVER_IP:$PORT/"
fi
echo "✓ Admin Panel updater: /update.php"
