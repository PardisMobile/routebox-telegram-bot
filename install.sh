#!/usr/bin/env bash
set -Eeuo pipefail

# ╔════════════════════════════════════════════════════════════════════╗
# ║                  🚀 ROUTEBOX TELEGRAM BOT                         ║
# ║                       MAIN INSTALLER                             ║
# ║                 Created & maintained by Amir Taheri               ║
# ╚════════════════════════════════════════════════════════════════════╝
#
# Production installer.
# Development installers remain available as:
#   ./install-dev.sh
#   ./install-dev-full.sh
#
# Canonical production flow:
#   install.sh → install-v2.sh → repair-web.sh → updater/TLS integration
#
# This entrypoint deliberately keeps installation behavior in the tested
# install-v2.sh implementation, while adding preflight checks, clean output,
# and a final deployment summary.

APP_NAME="routebox-telegram-bot"
APP_DIR="/opt/${APP_NAME}"
STATE_DIR="/etc/${APP_NAME}"
REPO_URL="https://github.com/PardisMobile/routebox-telegram-bot.git"
RAW_BASE="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main"

INSTALLER_URL="${RAW_BASE}/install-v2.sh"
REPAIR_URL="${RAW_BASE}/repair-web.sh"
UPDATER_URL="${RAW_BASE}/admin-update.sh"
TLS_URL="${RAW_BASE}/setup-routebox-tls.sh"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

# ───────────────────────────── Colors ──────────────────────────────
RESET='\033[0m'
BOLD='\033[1m'
DIM='\033[2m'
CYAN='\033[36m'
GREEN='\033[32m'
YELLOW='\033[33m'
RED='\033[31m'
BLUE='\033[34m'
MAGENTA='\033[35m'
WHITE='\033[97m'

info(){ printf "%b▶%b %s\n" "${CYAN}" "${RESET}" "$*"; }
ok(){ printf "%b✓%b %s\n" "${GREEN}" "${RESET}" "$*"; }
warn(){ printf "%b⚠%b %s\n" "${YELLOW}" "${RESET}" "$*" >&2; }
fail(){ printf "%b✗%b %s\n" "${RED}" "${RESET}" "$*" >&2; exit 1; }
step(){ printf "\n%b━━ %s ━━%b\n" "${BLUE}" "$*" "${RESET}"; }

banner(){
  printf "\n"
  printf "%b╔════════════════════════════════════════════════════════════════════╗%b\n" "${CYAN}${BOLD}" "${RESET}"
  printf "%b║                  🚀 ROUTEBOX TELEGRAM BOT                       ║%b\n" "${CYAN}${BOLD}" "${RESET}"
  printf "%b║                       MAIN INSTALLER                             ║%b\n" "${CYAN}${BOLD}" "${RESET}"
  printf "%b╠════════════════════════════════════════════════════════════════════╣%b\n" "${CYAN}${BOLD}" "${RESET}"
  printf "%b║   Secure • Modular • RouteBox + IBSng • Auto Validation          ║%b\n" "${WHITE}" "${RESET}"
  printf "%b║   Created & maintained by Amir Taheri                             ║%b\n" "${MAGENTA}" "${RESET}"
  printf "%b╚════════════════════════════════════════════════════════════════════╝%b\n" "${CYAN}${BOLD}" "${RESET}"
  printf "\n"
}

banner

step "Preflight"
[[ $EUID -eq 0 ]] || fail "Run as root: sudo bash install.sh"
[[ -r /etc/os-release ]] || fail "/etc/os-release is missing."
. /etc/os-release
[[ "${ID:-}" == "ubuntu" ]] || fail "Ubuntu 22.04+ is required."
UBUNTU_MAJOR="${VERSION_ID%%.*}"
[[ "$UBUNTU_MAJOR" =~ ^[0-9]+$ && "$UBUNTU_MAJOR" -ge 22 ]] || fail "Ubuntu 22.04+ is required."
command -v curl >/dev/null 2>&1 || fail "curl is required."
command -v bash >/dev/null 2>&1 || fail "bash is required."
ok "Ubuntu ${VERSION_ID} detected."
ok "Root privileges and required bootstrap tools are available."

step "Download the tested production installer"
curl -fsSL --connect-timeout 10 --max-time 120 "${INSTALLER_URL}" -o "${TMP_DIR}/install-v2.sh"
chmod 700 "${TMP_DIR}/install-v2.sh"
ok "Production setup wizard downloaded."

printf "\n%bThe setup wizard will now perform:%b\n" "${BOLD}" "${RESET}"
printf "  %b•%b PHP / SQLite / QR prerequisites\n" "${CYAN}" "${RESET}"
printf "  %b•%b Telegram token validation (silent input)\n" "${CYAN}" "${RESET}"
printf "  %b•%b RouteBox API + real AmneziaWG smoke test\n" "${CYAN}" "${RESET}"
printf "  %b•%b Modular service schema initialization\n" "${CYAN}" "${RESET}"
printf "  %b•%b Telegram worker + independent Admin Panel\n" "${CYAN}" "${RESET}"
printf "  %b•%b Admin updater / repair integration\n" "${CYAN}" "${RESET}"
printf "  %b•%b Optional reuse of the existing RouteBox TLS certificate\n\n" "${CYAN}" "${RESET}"

step "Run production setup"
if bash "${TMP_DIR}/install-v2.sh" "$@"; then
  ok "Base production setup completed."
else
  status=$?
  fail "Production setup failed (exit code ${status})."
fi

step "Post-install validation"
for url in "${REPAIR_URL}" "${UPDATER_URL}" "${TLS_URL}"; do
  curl -fsSL --connect-timeout 10 --max-time 60 "$url" >/dev/null || warn "Could not prefetch ${url##*/}; existing installed copy may still be sufficient."
done

# The current install-v2 flow creates the modular schema through the IBSng
# schema layer at application bootstrap. Do not duplicate or destructive-migrate
# the database here.

if [[ -d "${APP_DIR}" && -f "${APP_DIR}/VERSION" ]]; then
  VERSION="$(tr -d '[:space:]' < "${APP_DIR}/VERSION")"
  ok "Installed version: ${VERSION}"
else
  warn "Installed VERSION file was not found."
fi

PORT="unknown"
if [[ -f "${STATE_DIR}/web-port" ]]; then
  PORT="$(tr -d '[:space:]' < "${STATE_DIR}/web-port")"
fi

printf "\n%b╔════════════════════════════════════════════════════════════════════╗%b\n" "${GREEN}${BOLD}" "${RESET}"
printf "%b║                 🎉 ROUTEBOX INSTALLATION DONE                   ║%b\n" "${GREEN}${BOLD}" "${RESET}"
printf "%b╠════════════════════════════════════════════════════════════════════╣%b\n" "${GREEN}${BOLD}" "${RESET}"
printf "%b║  Version      : %-46s║%b\n" "${WHITE}" "${VERSION:-unknown}" "${RESET}"
printf "%b║  App path     : %-46s║%b\n" "${WHITE}" "${APP_DIR}" "${RESET}"
printf "%b║  Admin port   : %-46s║%b\n" "${WHITE}" "${PORT}" "${RESET}"
printf "%b║  Worker       : routebox-telegram-bot.service                 ║%b\n" "${WHITE}" "${RESET}"
printf "%b║  Admin Panel  : http://SERVER-IP:%-30s║%b\n" "${WHITE}" "${PORT}" "${RESET}"
printf "%b╠════════════════════════════════════════════════════════════════════╣%b\n" "${GREEN}${BOLD}" "${RESET}"
printf "%b║  Existing RouteBox / Apache / Nginx on 80/443 remain untouched. ║%b\n" "${YELLOW}" "${RESET}"
printf "%b║  Use install-dev.sh only for the isolated development branch.    ║%b\n" "${YELLOW}" "${RESET}"
printf "%b╚════════════════════════════════════════════════════════════════════╝%b\n\n" "${GREEN}${BOLD}" "${RESET}"

printf "%bNext:%b open the Admin Panel, then send %b/start%b to the Telegram bot.\n" "${CYAN}" "${RESET}" "${BOLD}" "${RESET}"
printf "%bDocumentation:%b README.md • INSTALL.md • ROADMAP.md • CHANGELOG.md\n" "${DIM}" "${RESET}"
printf "\n"
