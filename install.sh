#!/usr/bin/env bash
set -Eeuo pipefail

# RouteBox Telegram Bot — Beta installer entrypoint
# The implementation lives in install-v2.sh so this one-command installer
# always downloads the latest tested setup wizard from the repository.

SCRIPT_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install-v2.sh"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

curl -fsSL --connect-timeout 10 --max-time 60 "$SCRIPT_URL" -o "$TMP"
chmod 700 "$TMP"
exec bash "$TMP" "$@"
