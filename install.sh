#!/usr/bin/env bash
set -Eeuo pipefail

# RouteBox Telegram Bot — Beta installer entrypoint
# The implementation lives in install-v2.sh so this one-command installer
# always downloads the latest tested setup wizard from the repository.

SCRIPT_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install-v2.sh"
REPAIR_URL="https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/repair-web.sh"
TMP="$(mktemp)"
REPAIR_TMP="$(mktemp)"
trap 'rm -f "$TMP" "$REPAIR_TMP"' EXIT

curl -fsSL --connect-timeout 10 --max-time 60 "$SCRIPT_URL" -o "$TMP"
chmod 700 "$TMP"
if bash "$TMP" "$@"; then
  # Beta 2 uses an independent PHP listener. Run a final check as the actual
  # web-service user so a successful package install can never hide a 503 panel.
  if curl -fsSL --connect-timeout 10 --max-time 60 "$REPAIR_URL" -o "$REPAIR_TMP"; then
    chmod 700 "$REPAIR_TMP"
    bash "$REPAIR_TMP"
  else
    echo "[WARN] Could not download the admin-panel health/repair step." >&2
    exit 1
  fi
else
  status=$?
  exit "$status"
fi