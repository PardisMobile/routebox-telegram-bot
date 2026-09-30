#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR=/opt/routebox-telegram-bot
SERVICE=routebox-telegram-bot.service
APP_NAME=routebox-telegram-bot
WEB_SERVICE=${APP_NAME}-web
TLS_SERVICE=${APP_NAME}-tls.service
MUX_SERVICE=${APP_NAME}-mux.service
STATE_DIR=/etc/${APP_NAME}
BACKUP_DIR=/var/backups/${APP_NAME}
[[ $EUID -eq 0 ]] || { echo '[ERROR] Run as root: sudo bash update.sh'; exit 1; }
[[ -d "$APP_DIR/.git" ]] || { echo "[ERROR] $APP_DIR is not a Git checkout." >&2; exit 1; }
cd "$APP_DIR"
mkdir -p "$BACKUP_DIR"; chown root:www-data "$BACKUP_DIR"; chmod 750 "$BACKUP_DIR"
PRE_VERSION=$(tr -d '[:space:]' < VERSION 2>/dev/null || echo unknown)
PRE_STAMP=$(date '+%Y%m%d-%H%M%S')
PRE_FILE="$BACKUP_DIR/routebox-telegram-bot-v${PRE_VERSION}-pre-update-${PRE_STAMP}.tar.gz"
PRE_TMP=$(mktemp -d); trap 'rm -rf "$PRE_TMP"' EXIT
mkdir -p "$PRE_TMP/app" "$PRE_TMP/meta"
tar -C "$APP_DIR" -czf "$PRE_TMP/app/source.tar.gz" --exclude='./.git' --exclude='./storage/logs/*' --exclude='./storage/update.offset' .
cp -a "$APP_DIR/storage/database.sqlite" "$PRE_TMP/meta/database.sqlite" 2>/dev/null || true
cp -a "$APP_DIR/config/config.php" "$PRE_TMP/meta/config.php" 2>/dev/null || true
printf 'version=%s\ntime=%s\n' "$PRE_VERSION" "$(date --iso-8601=seconds)" > "$PRE_TMP/meta/manifest.txt"
tar -C "$PRE_TMP" -czf "$PRE_FILE" app meta
chown root:www-data "$PRE_FILE"; chmod 640 "$PRE_FILE"
git fetch --prune origin
git reset --hard origin/main
if [[ "${ROUTEBOX_UPDATE_REEXEC:-0}" != "1" ]]; then export ROUTEBOX_UPDATE_REEXEC=1; exec bash "$APP_DIR/update.sh"; fi
mkdir -p storage/logs "$STATE_DIR" "$BACKUP_DIR"
chown -R www-data:www-data storage
chmod 750 storage
install -m 0644 systemd/routebox-telegram-bot.service "/etc/systemd/system/$SERVICE"
[[ -f storage/database.sqlite ]] && sqlite3 storage/database.sqlite < database/schema.sql || true
chown www-data:www-data storage/database.sqlite 2>/dev/null || true
chmod 640 storage/database.sqlite 2>/dev/null || true
install -m 0755 backup.sh /usr/local/sbin/routebox-telegram-bot-backup
install -m 0755 restore-backup.sh /usr/local/sbin/routebox-telegram-bot-restore
install -m 0755 admin-update.sh /usr/local/sbin/routebox-telegram-bot-update
touch .updater-installed
cat > /etc/sudoers.d/routebox-telegram-bot <<'EOF_SUDO'
www-data ALL=(root) NOPASSWD: /usr/local/sbin/routebox-telegram-bot-update
www-data ALL=(root) NOPASSWD: /usr/local/sbin/routebox-telegram-bot-backup
www-data ALL=(root) NOPASSWD: /usr/local/sbin/routebox-telegram-bot-backup delete *
www-data ALL=(root) NOPASSWD: /usr/local/sbin/routebox-telegram-bot-restore *
EOF_SUDO
chmod 0440 /etc/sudoers.d/routebox-telegram-bot
visudo -cf /etc/sudoers.d/routebox-telegram-bot >/dev/null
OLD_PORT=$(cat "$STATE_DIR/web-port" 2>/dev/null || echo 8090)
if [[ "$OLD_PORT" =~ ^[0-9]+$ ]]; then systemctl stop "$MUX_SERVICE" >/dev/null 2>&1 || true; systemctl stop "$TLS_SERVICE" >/dev/null 2>&1 || true; systemctl disable --now "${WEB_SERVICE}@${OLD_PORT}.service" >/dev/null 2>&1 || true; fi
rm -f "/etc/nginx/sites-enabled/$APP_NAME" "/etc/nginx/sites-available/$APP_NAME" 2>/dev/null || true
PORT=$OLD_PORT; [[ "$PORT" =~ ^[0-9]+$ ]] || PORT=8090
if ss -ltnH "sport = :$PORT" 2>/dev/null | grep -q .; then echo "[ERROR] Existing Admin Panel port $PORT is occupied by another process." >&2; ss -ltnp "sport = :$PORT" >&2 || true; exit 1; fi
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
ExecStart=/usr/bin/php -d open_basedir=$APP_DIR:/tmp:/usr/local/sbin -S 0.0.0.0:%i -t $APP_DIR/public
Restart=always
RestartSec=2
ProtectHome=true
ReadWritePaths=$APP_DIR/storage
[Install]
WantedBy=multi-user.target
EOF_WEB
echo standalone > "$STATE_DIR/web-mode"; echo "$PORT" > "$STATE_DIR/web-port"
systemctl daemon-reload
systemctl enable --now "${WEB_SERVICE}@${PORT}.service"
systemctl is-active --quiet "${WEB_SERVICE}@${PORT}.service" || { journalctl -u "${WEB_SERVICE}@${PORT}.service" -n 50 --no-pager; exit 1; }
systemctl enable --now "$SERVICE"
php -l worker.php >/dev/null
php -l src/RouteBoxClient.php >/dev/null
php -l public/index.php >/dev/null
php -l public/update.php >/dev/null
bash -n install.sh install-v2.sh update.sh uninstall.sh admin-update.sh setup-routebox-tls.sh backup.sh restore-backup.sh
systemctl restart "$SERVICE"
sleep 1
systemctl is-active --quiet "$SERVICE" || { journalctl -u "$SERVICE" -n 80 --no-pager; exit 1; }
if [[ -f setup-routebox-tls.sh ]]; then bash setup-routebox-tls.sh || { echo '[WARN] RouteBox TLS integration could not be restored; HTTP panel remains available.' >&2; }; fi
mapfile -t OLD_BACKUPS < <(ls -1t "$BACKUP_DIR"/*.tar.gz 2>/dev/null | tail -n +11 || true)
((${#OLD_BACKUPS[@]})) && rm -f -- "${OLD_BACKUPS[@]}" || true
echo "✓ Update completed successfully."
echo "✓ Pre-update backup: $PRE_FILE"
echo "✓ Existing Apache/Nginx/RouteBox services were not reconfigured."
echo "✓ Telegram worker restarted."
if [[ "$(cat "$STATE_DIR/web-tls-mode" 2>/dev/null || true)" == "routebox-panel-acme-multiplex" ]]; then echo "✓ Admin Panel HTTP+HTTPS: same port $PORT"; else echo "✓ Admin Panel HTTP: http://YOUR_SERVER_IP:$PORT/"; fi
echo "✓ Admin Panel updater: /update.php"
