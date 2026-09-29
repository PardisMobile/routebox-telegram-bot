#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
STATE_DIR="/etc/${APP_NAME}"
WEB_SERVICE="${APP_NAME}-web"

fail(){ echo "[ERROR] $*" >&2; exit 1; }
[[ $EUID -eq 0 ]] || fail "Run as root: sudo bash repair-web.sh"
[[ -d "$APP_DIR" ]] || fail "$APP_DIR does not exist. Run install.sh first."
[[ -f "$APP_DIR/config/config.php" ]] || fail "Missing $APP_DIR/config/config.php. Run install.sh first."

# The panel runs as www-data. The directory itself must be traversable by the
# www-data group; fixing only config.php is not enough when config/ is 750.
# Keep the secret file private while allowing the service to read it.
chown root:www-data "$APP_DIR/config"
chmod 750 "$APP_DIR/config"
chown root:www-data "$APP_DIR/config/config.php"
chmod 640 "$APP_DIR/config/config.php"
chown -R www-data:www-data "$APP_DIR/storage"
chmod 750 "$APP_DIR/storage"
chmod 750 "$APP_DIR/storage/logs" 2>/dev/null || true

# Fail early if PHP itself cannot see the config. This catches custom PHP
# open_basedir/AppArmor/permission problems instead of showing a misleading 503.
runuser -u www-data -- php -r 'require $argv[1]; echo "✓ www-data can load config.php\n";' "$APP_DIR/config/config.php" || {
  echo "[ERROR] www-data cannot load config.php." >&2
  namei -l "$APP_DIR/config/config.php" >&2 || true
  php --ini >&2 || true
  php -r 'echo "open_basedir=".(ini_get("open_basedir")?:"<none>")."\n";' >&2 || true
  exit 1
}

PORT="$(cat "$STATE_DIR/web-port" 2>/dev/null || true)"
[[ "$PORT" =~ ^[0-9]+$ ]] || PORT=8090

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
# PHP's built-in listener is HTTP only. Keep it isolated on its own port;
# HTTPS should terminate at the existing Apache/Nginx/RouteBox TLS endpoint.
# Keep the PHP filesystem sandbox, but allow the panel to inspect and invoke
# the dedicated root-owned updater through the restricted sudo rule.
ExecStart=/usr/bin/php -d open_basedir=$APP_DIR:/tmp:/usr/local/sbin -S 0.0.0.0:%i -t $APP_DIR/public
Restart=always
RestartSec=2
ProtectHome=true
ReadWritePaths=$APP_DIR/storage

[Install]
WantedBy=multi-user.target
EOF_WEB

systemctl daemon-reload
systemctl enable --now "${WEB_SERVICE}@${PORT}.service"
sleep 1

HTTP_CODE="$(curl -sS -o /tmp/rbt-web-check.$$ -w '%{http_code}' "http://127.0.0.1:${PORT}/login.php" || true)"
if [[ "$HTTP_CODE" != "200" && "$HTTP_CODE" != "302" ]]; then
  echo "[ERROR] Admin panel health check failed: HTTP $HTTP_CODE" >&2
  journalctl -u "${WEB_SERVICE}@${PORT}.service" -n 40 --no-pager >&2 || true
  rm -f "/tmp/rbt-web-check.$$"
  exit 1
fi
rm -f "/tmp/rbt-web-check.$$"

echo
echo "✓ Admin panel repaired and responding on HTTP."
echo "✓ URL: http://YOUR_SERVER_IP:${PORT}/"
echo "✓ www-data can read config.php securely."
echo "✓ HTTPS on this port is intentionally not enabled. Use the existing Apache/Nginx/RouteBox TLS endpoint as the reverse proxy."
echo "✓ RouteBox itself is a separate service; do not use the Bot admin-panel port as the RouteBox API URL."
