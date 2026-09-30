#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR=/opt/routebox-telegram-bot
BACKUP_DIR=/var/backups/routebox-telegram-bot
[[ $EUID -eq 0 ]] || { echo '[ERROR] Run with sudo.' >&2; exit 1; }
case "${1:-status}" in
  status) echo "Version: $(tr -d '[:space:]' < "$APP_DIR/VERSION" 2>/dev/null || echo unknown)"; systemctl is-active routebox-telegram-bot.service || true; PORT=$(cat /etc/routebox-telegram-bot/web-port 2>/dev/null || true); [[ "$PORT" =~ ^[0-9]+$ ]] && systemctl is-active "routebox-telegram-bot-web@${PORT}.service" || true ;;
  backup) exec /usr/local/sbin/routebox-telegram-bot-backup ;;
  restore) [[ -n "${2:-}" ]] || { echo "Usage: $0 restore <backup-file>" >&2; exit 1; }; exec /usr/local/sbin/routebox-telegram-bot-restore "$(basename "$2")" ;;
  update) exec "$APP_DIR/update.sh" ;;
  backups) ls -lht "$BACKUP_DIR"/*.tar.gz 2>/dev/null | head -10 || true ;;
  *) echo "Usage: $0 {status|backup|restore <file>|update|backups}"; exit 1 ;;
esac
