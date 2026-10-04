# RouteBox Telegram Bot — ATD Panel

This repository contains the RouteBox Telegram Bot and its unified ATD administration panel.

The panel provides one UI for RouteBox, IBSng and MikroTik WireGuard services while keeping provider integrations modular.

## ATD Panel UI

Current provider sections:

- RouteBox Servers (`section=routebox` public UI alias)
- IBSng Servers
- MikroTik WireGuard
- Telegram Bot
- Users
- Provider Plans
- Security
- Updates

The Dashboard includes the unified ATD statistics cards and a Version/system card with CPU, RAM and Disk information.

Provider status cards show connection state, server IP, ping and country flag, with the existing provider-specific test/reload actions preserved.

### Important UI safety rule

ATD Panel UI work is intentionally separated from provider logic. Do not modify provider APIs, provisioning, repositories, WireGuard peer allocation, IBSng group mapping or other tested business logic merely to change the UI.

The historical `df05c42` commit is a read-only reference backup.

### Cross-browser flags

Legacy country flags are rendered as image flags rather than Unicode regional-indicator emoji so Chrome/Edge and Firefox display them consistently.

### Server lists

Server lists appear before Add Server forms. RouteBox server lists paginate at 10 items when needed. MikroTik peer lists are also expected to remain paginated at scale.

### Provider plans

Plan cards use a unified action row for Edit, Enable/Disable and Delete. IBSng Provider Plan Key / group name remains meaningful and required; RouteBox and MikroTik should not force unnecessary manual Provider Plan Key input.

### Documentation

See `docs/ATD_PANEL_WORKING_NOTES.md` for the durable UI handoff and protected implementation boundaries.

## Development deployment

DEV path:
`/opt/routebox-telegram-bot-dev`

Branch:
`ATD-Panel`

```bash
cd /opt/routebox-telegram-bot-dev

git fetch origin '+refs/heads/ATD-Panel:refs/remotes/origin/ATD-Panel'
git reset --hard origin/ATD-Panel

systemctl restart routebox-telegram-bot-dev-web@8092.service
```

Run `php -l` on changed PHP files before restarting the web service.
