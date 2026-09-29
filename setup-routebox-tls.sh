#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
STATE_DIR="/etc/${APP_NAME}"
TLS_DIR="${STATE_DIR}/tls"
CERT_SOURCE="/etc/routebox/panel-cert/fullchain.pem"
KEY_SOURCE="/etc/routebox/panel-cert/key.pem"
TLS_SERVICE="${APP_NAME}-tls.service"
MUX_SERVICE="${APP_NAME}-mux.service"
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

# The Bot must keep ONE public Admin Panel port and accept BOTH protocols on it.
# A raw PHP listener cannot speak TLS, and stunnel alone would make the port
# HTTPS-only. HAProxy is therefore used only as a small TCP protocol multiplexer:
#   HTTP  -> PHP loopback listener
#   TLS   -> local stunnel TLS terminator -> PHP loopback listener
# The public port never changes.
if ! command -v haproxy >/dev/null 2>&1; then
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y haproxy
fi
if ! command -v stunnel4 >/dev/null 2>&1; then
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y stunnel4
fi

# Never touch a system-wide HAProxy configuration/service. We run a dedicated
# Bot-owned HAProxy instance with its own config and systemd unit.
systemctl stop "$MUX_SERVICE" >/dev/null 2>&1 || true
systemctl disable "$MUX_SERVICE" >/dev/null 2>&1 || true
systemctl stop "$TLS_SERVICE" >/dev/null 2>&1 || true
systemctl disable "$TLS_SERVICE" >/dev/null 2>&1 || true

WEB_INSTANCE="${APP_NAME}-web@${PORT}.service"
DROPIN_DIR="/etc/systemd/system/${APP_NAME}-web@${PORT}.service.d"
mkdir -p "$DROPIN_DIR" "$TLS_DIR" "$STATE_DIR"

# Pick two private backend ports. They are never exposed publicly.
free_port(){
  local p="$1"
  while ss -ltnH "sport = :$p" 2>/dev/null | grep -q .; do p=$((p+1)); done
  printf '%s' "$p"
}
BACKEND_PORT="$(free_port $((PORT + 1)))"
TLS_BACKEND_PORT="$(free_port $((BACKEND_PORT + 1)))"

# Keep the PHP application on loopback only. The public Admin Panel port remains
# exactly the value already stored in web-port (for example 8093).
cat > "${DROPIN_DIR}/backend.conf" <<EOF_BACKEND
[Service]
ExecStart=
ExecStart=/usr/bin/php -d open_basedir=${APP_DIR}:/tmp -S 127.0.0.1:${BACKEND_PORT} -t ${APP_DIR}/public
EOF_BACKEND

echo "$BACKEND_PORT" > "${STATE_DIR}/web-backend-port"
echo "$TLS_BACKEND_PORT" > "${STATE_DIR}/web-tls-backend-port"
echo "routebox-panel-acme-multiplex" > "${STATE_DIR}/web-tls-mode"

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

# TLS terminator: loopback only. It receives already-classified TLS traffic
# from HAProxy and forwards decrypted HTTP to the PHP listener.
cat > "/etc/stunnel/${APP_NAME}.conf" <<EOF_STUNNEL
foreground = yes
setuid = stunnel4
setgid = stunnel4
pid =

[admin-panel-tls]
accept = 127.0.0.1:${TLS_BACKEND_PORT}
connect = 127.0.0.1:${BACKEND_PORT}
cert = ${TLS_DIR}/fullchain.pem
key = ${TLS_DIR}/key.pem
sslVersionMin = TLSv1.2
EOF_STUNNEL
chmod 0644 "/etc/stunnel/${APP_NAME}.conf"

cat > "/etc/systemd/system/${TLS_SERVICE}" <<EOF_TLS
[Unit]
Description=RouteBox Telegram Bot Admin Panel TLS terminator
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

# Dedicated HAProxy instance. It inspects only the first client bytes:
# HTTP is sent directly to PHP; a TLS ClientHello is sent to stunnel.
# HAProxy's inspect-delay does not add a 5-second delay when the protocol is
# recognized immediately (HTTP or TLS).
cat > "${STATE_DIR}/haproxy.cfg" <<EOF_HAPROXY
global
    user haproxy
    group haproxy
    maxconn 4096

defaults
    mode tcp
    timeout connect 5s
    timeout client 1h
    timeout server 1h

frontend rbt_admin_mux
    bind 0.0.0.0:${PORT}
    tcp-request inspect-delay 5s
    tcp-request content accept if HTTP
    tcp-request content accept if { req.ssl_hello_type 1 }
    use_backend rbt_tls if { req.ssl_hello_type 1 }
    default_backend rbt_http

backend rbt_http
    mode tcp
    server php 127.0.0.1:${BACKEND_PORT}

backend rbt_tls
    mode tcp
    server stunnel 127.0.0.1:${TLS_BACKEND_PORT}
EOF_HAPROXY
chmod 0644 "${STATE_DIR}/haproxy.cfg"

cat > "/etc/systemd/system/${MUX_SERVICE}" <<EOF_MUX
[Unit]
Description=RouteBox Telegram Bot Admin Panel HTTP+HTTPS multiplexer
After=network-online.target ${WEB_INSTANCE} ${TLS_SERVICE}
Wants=network-online.target
Requires=${WEB_INSTANCE} ${TLS_SERVICE}

[Service]
Type=simple
ExecStart=/usr/sbin/haproxy -db -f ${STATE_DIR}/haproxy.cfg
ExecReload=/usr/sbin/haproxy -c -f ${STATE_DIR}/haproxy.cfg
Restart=always
RestartSec=2
NoNewPrivileges=true
ProtectHome=true
ProtectSystem=strict
ReadWritePaths=${STATE_DIR}

[Install]
WantedBy=multi-user.target
EOF_MUX

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

# Validate configuration before starting anything public.
/usr/sbin/haproxy -c -f "${STATE_DIR}/haproxy.cfg" >/dev/null || fail "HAProxy configuration validation failed."

systemctl daemon-reload
systemctl stop "$WEB_INSTANCE" >/dev/null 2>&1 || true
systemctl enable --now "$WEB_INSTANCE"
systemctl enable --now "$TLS_SERVICE"
systemctl enable --now "$MUX_SERVICE"
systemctl enable --now "$SYNC_TIMER"

# One immediate certificate sync after all services are up.
"${STATE_DIR}/sync-tls-cert.sh"

# Local health checks for BOTH protocols on the SAME public port.
HTTP_CODE="$(curl -sS -o /tmp/rbt-http-check.$$ -w '%{http_code}' "http://127.0.0.1:${PORT}/login.php" || true)"
rm -f "/tmp/rbt-http-check.$$"
[[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "302" ]] || fail "Admin Panel HTTP health check failed on :${PORT}: HTTP ${HTTP_CODE:-000}."

HTTPS_CODE="$(curl -ksS -o /tmp/rbt-https-check.$$ -w '%{http_code}' "https://127.0.0.1:${PORT}/login.php" || true)"
rm -f "/tmp/rbt-https-check.$$"
[[ "$HTTPS_CODE" == "200" || "$HTTPS_CODE" == "302" ]] || fail "Admin Panel HTTPS health check failed on :${PORT}: HTTP ${HTTPS_CODE:-000}."

printf '\n✓ RouteBox panel certificate is reused for the Bot Admin Panel.\n'
printf '✓ SAME public Admin Panel port: %s\n' "$PORT"
printf '✓ HTTP:  http://<server-ip>:%s/\n' "$PORT"
printf '✓ HTTPS: https://<RouteBox-domain>:%s/\n' "$PORT"
printf '✓ PHP backend: 127.0.0.1:%s (not public)\n' "$BACKEND_PORT"
printf '✓ TLS terminator: 127.0.0.1:%s (not public)\n' "$TLS_BACKEND_PORT"
printf '✓ Ports 80/443 and RouteBox/Apache/Nginx were not changed.\n'
printf '✓ Certificate renewal sync timer: %s\n' "$SYNC_TIMER"
