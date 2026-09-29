#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
STATE_DIR="/etc/${APP_NAME}"
TLS_DIR="${STATE_DIR}/tls"
CERT_SOURCE="/etc/routebox/panel-cert/fullchain.pem"
KEY_SOURCE="/etc/routebox/panel-cert/key.pem"
TLS_SERVICE="${APP_NAME}-tls.service"
SYNC_SERVICE="${APP_NAME}-tls-sync.service"
SYNC_TIMER="${APP_NAME}-tls-sync.timer"

fail(){ echo "[ERROR] $*" >&2; exit 1; }
warn(){ echo "[WARN] $*" >&2; }

[[ $EUID -eq 0 ]] || fail "Run as root: sudo bash setup-routebox-tls.sh"
[[ -d "$APP_DIR" ]] || fail "$APP_DIR does not exist. Install the Bot first."

PORT=""
if [[ -f "$STATE_DIR/web-port" ]]; then
  PORT="$(tr -d '[:space:]' < "$STATE_DIR/web-port" || true)"
fi
[[ "$PORT" =~ ^[0-9]+$ ]] || fail "Could not determine the Admin Panel port from $STATE_DIR/web-port."

# RouteBox exports its currently active ACME/manual panel certificate to this
# stable path. We reuse that certificate; we never run ACME, certbot, nginx,
# Apache, or bind ports 80/443 here.
if [[ ! -s "$CERT_SOURCE" || ! -s "$KEY_SOURCE" ]]; then
  warn "RouteBox panel certificate was not found at $CERT_SOURCE / $KEY_SOURCE."
  warn "HTTPS integration was not changed. The Admin Panel remains HTTP-only."
  exit 0
fi

if ! command -v stunnel4 >/dev/null 2>&1; then
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y stunnel4
fi

TLS_PORT="$PORT"
BACKEND_PORT=$((PORT + 1))
while ss -ltnH "sport = :$BACKEND_PORT" 2>/dev/null | grep -q .; do
  BACKEND_PORT=$((BACKEND_PORT + 1))
done

WEB_INSTANCE="${APP_NAME}-web@${PORT}.service"
DROPIN_DIR="/etc/systemd/system/${APP_NAME}-web@${PORT}.service.d"
mkdir -p "$DROPIN_DIR" "$TLS_DIR"

# Keep the public Admin Panel port unchanged. Move only the PHP backend to
# loopback so TLS can own the existing panel port. The Telegram worker service
# routebox-telegram-bot.service is deliberately untouched.
cat > "${DROPIN_DIR}/backend.conf" <<EOF_BACKEND
[Service]
ExecStart=
ExecStart=/usr/bin/php -d open_basedir=${APP_DIR}:/tmp -S 127.0.0.1:${BACKEND_PORT} -t ${APP_DIR}/public
EOF_BACKEND

echo "$BACKEND_PORT" > "${STATE_DIR}/web-backend-port"
echo "routebox-panel-acme" > "${STATE_DIR}/web-tls-mode"

# Copy the live RouteBox certificate/key into a dedicated location readable by
# the unprivileged stunnel process. The sync timer refreshes these files after
# RouteBox renews its certificate.
install -d -m 0750 "$TLS_DIR"
install -m 0644 -o root -g stunnel4 "$CERT_SOURCE" "${TLS_DIR}/fullchain.pem"
install -m 0640 -o root -g stunnel4 "$KEY_SOURCE" "${TLS_DIR}/key.pem"

cat > "${STATE_DIR}/sync-tls-cert.sh" <<'EOF_SYNC'
#!/usr/bin/env bash
set -Eeuo pipefail
SRC_CERT=/etc/routebox/panel-cert/fullchain.pem
SRC_KEY=/etc/routebox/panel-cert/key.pem
DST=/etc/routebox-telegram-bot/tls
SERVICE=routebox-telegram-bot-tls.service

[[ -s "$SRC_CERT" && -s "$SRC_KEY" ]] || exit 0
mkdir -p "$DST"
changed=0
if ! cmp -s "$SRC_CERT" "$DST/fullchain.pem"; then
  install -m 0644 -o root -g stunnel4 "$SRC_CERT" "$DST/fullchain.pem"
  changed=1
fi
if ! cmp -s "$SRC_KEY" "$DST/key.pem"; then
  install -m 0640 -o root -g stunnel4 "$SRC_KEY" "$DST/key.pem"
  changed=1
fi
if (( changed )); then
  systemctl reload "$SERVICE" >/dev/null 2>&1 || systemctl restart "$SERVICE" >/dev/null 2>&1 || true
fi
EOF_SYNC
chmod 0750 "${STATE_DIR}/sync-tls-cert.sh"

cat > "/etc/stunnel/${APP_NAME}.conf" <<EOF_STUNNEL
foreground = yes
setuid = stunnel4
setgid = stunnel4
pid =

[admin-panel]
accept = 0.0.0.0:${TLS_PORT}
connect = 127.0.0.1:${BACKEND_PORT}
cert = ${TLS_DIR}/fullchain.pem
key = ${TLS_DIR}/key.pem
sslVersionMin = TLSv1.2
EOF_STUNNEL
chmod 0644 "/etc/stunnel/${APP_NAME}.conf"

cat > "/etc/systemd/system/${TLS_SERVICE}" <<EOF_TLS
[Unit]
Description=RouteBox Telegram Bot Admin Panel TLS
After=network-online.target ${WEB_INSTANCE}
Wants=network-online.target
Requires=${WEB_INSTANCE}

[Service]
Type=simple
ExecStart=/usr/bin/stunnel4 /etc/stunnel/${APP_NAME}.conf
ExecReload=/bin/kill -HUP \$MAINPID
Restart=always
RestartSec=2
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectSystem=strict
ReadWritePaths=${TLS_DIR}

[Install]
WantedBy=multi-user.target
EOF_TLS

cat > "/etc/systemd/system/${SYNC_SERVICE}" <<EOF_SYNC_UNIT
[Unit]
Description=Sync RouteBox panel certificate for Telegram Bot TLS
After=${TLS_SERVICE}

[Service]
Type=oneshot
ExecStart=${STATE_DIR}/sync-tls-cert.sh
EOF_SYNC_UNIT

cat > "/etc/systemd/system/${SYNC_TIMER}" <<EOF_SYNC_TIMER
[Unit]
Description=Watch RouteBox panel certificate renewal

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
Unit=${SYNC_SERVICE}

[Install]
WantedBy=timers.target
EOF_SYNC_TIMER

systemctl daemon-reload
systemctl stop "$WEB_INSTANCE" >/dev/null 2>&1 || true
systemctl enable --now "$WEB_INSTANCE"
systemctl enable --now "$TLS_SERVICE"
systemctl enable --now "$SYNC_TIMER"

# One immediate sync after both services are up.
"${STATE_DIR}/sync-tls-cert.sh"

# Local health checks. The HTTP backend must stay loopback-only and the TLS
# frontend must own the original panel port.
curl -fsS --max-time 10 "http://127.0.0.1:${BACKEND_PORT}/login.php" >/dev/null || fail "Admin Panel HTTP backend health check failed on 127.0.0.1:${BACKEND_PORT}."

if ! timeout 8 bash -c "</dev/tcp/127.0.0.1/${TLS_PORT}" 2>/dev/null; then
  fail "TLS listener is not accepting connections on ${TLS_PORT}."
fi

printf '\n✓ RouteBox panel certificate is reused for the Bot Admin Panel.\n'
printf '✓ Public Admin Panel: https://<RouteBox-domain>:%s/\n' "$TLS_PORT"
printf '✓ PHP backend: 127.0.0.1:%s (not public)\n' "$BACKEND_PORT"
printf '✓ Ports 80/443 were not changed.\n'
printf '✓ routebox-telegram-bot.service was not modified.\n'
printf '✓ Certificate renewal sync timer: %s\n' "$SYNC_TIMER"
