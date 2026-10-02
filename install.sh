#!/usr/bin/env bash
set -Eeuo pipefail

# ╔════════════════════════════════════════════════════════════════════╗
# ║                  🚀 ROUTEBOX TELEGRAM BOT                         ║
# ║                    PUBLIC PRODUCTION INSTALLER                    ║
# ╚════════════════════════════════════════════════════════════════════╝
#
# This is the user-facing production entrypoint.
# The actual production setup implementation lives in installer-core.sh.

APP_NAME="routebox-telegram-bot"
CORE_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/installer-core.sh"
REPAIR_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/repair-web.sh"
UPDATER_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/admin-update.sh"
TLS_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/setup-routebox-tls.sh"
APP_DIR="/opt/${APP_NAME}"
STATE_DIR="/etc/${APP_NAME}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

RESET='\033[0m'; BOLD='\033[1m'; CYAN='\033[36m'; GREEN='\033[32m'; YELLOW='\033[33m'; RED='\033[31m'; BLUE='\033[34m'

say(){ printf "${CYAN}▶${RESET} %s\n" "$*"; }
ok(){ printf "${GREEN}✓${RESET} %s\n" "$*"; }
warn(){ printf "${YELLOW}⚠${RESET} %s\n" "$*" >&2; }
fail(){ printf "${RED}✗${RESET} %s\n" "$*" >&2; exit 1; }
section(){ printf "\n${BLUE}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}\n${BLUE}${BOLD}  %s${RESET}\n${BLUE}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}\n" "$*"; }

banner(){
  printf '\n'
  printf "${CYAN}${BOLD}╔══════════════════════════════════════════════════════════════╗${RESET}\n"
  printf "${CYAN}${BOLD}║                  🚀 RouteBox Telegram Bot                   ║${RESET}\n"
  printf "${CYAN}${BOLD}║                  Production Installer                      ║${RESET}\n"
  printf "${CYAN}${BOLD}╠══════════════════════════════════════════════════════════════╣${RESET}\n"
  printf "${CYAN}${BOLD}║  ⭐ Public entrypoint • 🔧 Internal installer core          ║${RESET}\n"
  printf "${CYAN}${BOLD}║  🔐 Secure • Modular • RouteBox + IBSng ready               ║${RESET}\n"
  printf "${CYAN}${BOLD}╚══════════════════════════════════════════════════════════════╝${RESET}\n\n"
}

banner
[[ $EUID -eq 0 ]] || fail "Run as root: sudo bash install.sh"
command -v curl >/dev/null 2>&1 || fail "curl is required."

section "1/4  Download production installer core"
say "Fetching the latest installer-core.sh from main..."
curl -fsSL --connect-timeout 10 --max-time 120 "$CORE_URL" -o "$TMP_DIR/installer-core.sh" || fail "Could not download installer-core.sh."
chmod 700 "$TMP_DIR/installer-core.sh"
ok "Production installer core downloaded."

section "2/4  Run production installation"
if bash "$TMP_DIR/installer-core.sh" "$@"; then
  ok "Production installation core completed successfully."
else
  status=$?
  fail "Production installation failed (exit code ${status})."
fi

section "3/4  Final web-panel repair and tooling"
REPAIR_TMP="$TMP_DIR/repair-web.sh"
if curl -fsSL --connect-timeout 10 --max-time 60 "$REPAIR_URL" -o "$REPAIR_TMP"; then
  chmod 700 "$REPAIR_TMP"
  bash "$REPAIR_TMP"
  ok "Admin Panel repair/health step completed."
else
  fail "Could not download the Admin Panel health/repair step."
fi

# Keep the persistent config version aligned with the repository VERSION file.
if [[ -f "$APP_DIR/VERSION" && -f "$APP_DIR/config/config.php" ]]; then
  REPO_VERSION="$(tr -d '[:space:]' < "$APP_DIR/VERSION")"
  if [[ "$REPO_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]; then
    sed -i -E "s/'version'=>'[^']*'/'version'=>'${REPO_VERSION}'/" "$APP_DIR/config/config.php"
  fi
fi

UPDATER_TMP="$TMP_DIR/admin-update.sh"
if curl -fsSL --connect-timeout 10 --max-time 60 "$UPDATER_URL" -o "$UPDATER_TMP"; then
  chmod 0755 "$UPDATER_TMP"
  install -m 0755 "$UPDATER_TMP" /usr/local/sbin/routebox-telegram-bot-update
  cat > /etc/sudoers.d/routebox-telegram-bot-update <<'EOF_SUDO'
www-data ALL=(root) NOPASSWD: /usr/local/sbin/routebox-telegram-bot-update
EOF_SUDO
  chmod 0440 /etc/sudoers.d/routebox-telegram-bot-update
  visudo -cf /etc/sudoers.d/routebox-telegram-bot-update >/dev/null
  ok "Restricted Admin Panel updater installed."
else
  warn "Could not download the Admin Panel updater; existing installation remains available."
fi

TLS_TMP="$TMP_DIR/setup-routebox-tls.sh"
if curl -fsSL --connect-timeout 10 --max-time 60 "$TLS_URL" -o "$TLS_TMP"; then
  chmod 700 "$TLS_TMP"
  bash "$TLS_TMP" || warn "RouteBox TLS integration was not enabled; the HTTP panel remains available."
else
  warn "Could not download the optional RouteBox TLS integration step."
fi

section "4/4  Installation summary"
VERSION="unknown"; PORT="unknown"
[[ -f "$APP_DIR/VERSION" ]] && VERSION="$(tr -d '[:space:]' < "$APP_DIR/VERSION")"
[[ -f "$STATE_DIR/web-port" ]] && PORT="$(tr -d '[:space:]' < "$STATE_DIR/web-port")"

printf "${GREEN}${BOLD}╔══════════════════════════════════════════════════════════════╗${RESET}\n"
printf "${GREEN}${BOLD}║              🎉 Production installation DONE               ║${RESET}\n"
printf "${GREEN}${BOLD}╠══════════════════════════════════════════════════════════════╣${RESET}\n"
printf "${GREEN}║  Version       : %-41s║${RESET}\n" "$VERSION"
printf "${GREEN}║  Application   : %-41s║${RESET}\n" "$APP_DIR"
printf "${GREEN}║  Admin Panel   : http://SERVER-IP:%-22s║${RESET}\n" "$PORT"
printf "${GREEN}║  Worker        : %-41s║${RESET}\n" "${APP_NAME}.service"
printf "${GREEN}║  Web service   : %-41s║${RESET}\n" "${APP_NAME}-web@${PORT}.service"
printf "${GREEN}╠══════════════════════════════════════════════════════════════╣${RESET}\n"
printf "${GREEN}║  Ports 80/443 and existing RouteBox/Apache/Nginx untouched.  ║${RESET}\n"
printf "${GREEN}╚══════════════════════════════════════════════════════════════╝${RESET}\n\n"
printf "${YELLOW}Next:${RESET} Open the Admin Panel and send ${BOLD}/start${RESET} to your Telegram bot.\n"
printf "${YELLOW}Production entrypoint:${RESET} install.sh\n"
printf "${YELLOW}Internal core:${RESET} installer-core.sh\n\n"
