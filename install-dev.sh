#!/usr/bin/env bash
set -Eeuo pipefail

# RouteBox Telegram Bot — isolated development installer
# Installs the IBSng/modular branch beside the production installation.
# It does NOT stop, replace, or update the production RouteBox Bot.

BRANCH="feature/modular-services-ibsng"
REPO="https://github.com/PardisMobile/routebox-telegram-bot.git"
APP_DIR="/opt/routebox-telegram-bot-dev"
IBSNG_API_PORT=1237

say(){ printf '\033[36m▶\033[0m %s\n' "$*"; }
ok(){ printf '\033[32m✓\033[0m %s\n' "$*"; }
warn(){ printf '\033[33m⚠\033[0m %s\n' "$*" >&2; }
fail(){ printf '\033[31m✗\033[0m %s\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || fail 'Run this script as root.'
command -v git >/dev/null 2>&1 || fail 'git is required.'
command -v php >/dev/null 2>&1 || fail 'PHP is required.'

printf '\n\033[36m\033[1m╔══════════════════════════════════════════════════════════════╗\033[0m\n'
printf '\033[36m\033[1m║       RouteBox Telegram Bot — DEV / IBSng Test             ║\033[0m\n'
printf '\033[36m\033[1m║              Created by Amir Taheri                        ║\033[0m\n'
printf '\033[36m\033[1m╚══════════════════════════════════════════════════════════════╝\033[0m\n\n'

say "Preparing isolated directory: ${APP_DIR}"
if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" fetch origin "$BRANCH"
  git -C "$APP_DIR" checkout -B "$BRANCH" "origin/$BRANCH"
  git -C "$APP_DIR" reset --hard "origin/$BRANCH" >/dev/null
else
  rm -rf "$APP_DIR"
  git clone --branch "$BRANCH" --single-branch "$REPO" "$APP_DIR"
fi

cd "$APP_DIR"

say 'Installing QR-code support (qrencode)...'
if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y -qq qrencode >/dev/null
fi
command -v qrencode >/dev/null 2>&1 || fail 'qrencode installation failed.'
ok 'qrencode is installed.'

say 'Checking PHP syntax in modular PHP files...'
mapfile -t PHP_FILES < <(find src/Integrations tools -type f -name '*.php' -print 2>/dev/null)
for f in "${PHP_FILES[@]}"; do
  php -l "$f" >/dev/null || fail "PHP syntax error: $f"
done
ok 'PHP syntax checks passed.'

say 'Applying the safe IBSng sidebar integration...'
php tools/patch-ibsng-sidebar.php || fail 'Sidebar patch failed; production installation was not touched.'
ok 'IBSng sidebar integration prepared.'

say 'IBSng JSON-RPC default API port: 1237 (advanced override supported).'
say 'Running isolated IBSng smoke-test help check...'
php tools/ibsng-smoke-test.php 2>&1 | head -n 1 || true

printf '\n\033[32m\033[1mDEV branch is ready.\033[0m\n'
printf 'Path : %s\n' "$APP_DIR"
printf 'Branch: %s\n' "$BRANCH"
printf 'IBSng API default port: %s\n' "$IBSNG_API_PORT"
printf '\nNext step — test a real IBSng connection (port is optional):\n'
printf '  cd %s\n' "$APP_DIR"
printf '  php tools/ibsng-smoke-test.php IBSNG_IP ADMIN_USER ADMIN_PASSWORD\n'
printf '\nOptional custom API port:\n'
printf '  php tools/ibsng-smoke-test.php IBSNG_IP ADMIN_USER ADMIN_PASSWORD 1237\n'
printf '\nThis installer does NOT replace the production installation at /opt/routebox-telegram-bot.\n'
printf '\033[36mCreated & maintained by Amir Taheri\033[0m\n'
