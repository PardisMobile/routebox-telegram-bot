#!/usr/bin/env bash
set -Eeuo pipefail

# RouteBox Telegram Bot — DEV installer
# The full installer mirrors the production install prerequisites while
# keeping the development branch isolated from production.
SCRIPT_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/feature/modular-services-ibsng/install-dev-full.sh"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
curl -fsSL --connect-timeout 10 --max-time 120 "$SCRIPT_URL" -o "$TMP"
chmod 700 "$TMP"
exec bash "$TMP" "$@"
