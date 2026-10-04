# ATD Panel — Working Notes

Durable handoff for the ATD Panel. Read this before further UI work.

## Golden rule

**UI/presentation changes only unless explicitly requested otherwise.** Do not rewrite provider APIs, provisioning, repositories, allocation, authentication, payment, IBSng group mapping, MikroTik peer creation, or other tested provider logic merely to change appearance.

`df05c42` is the read-only historical backup/reference.

## Providers and important files

- RouteBox
- IBSng
- MikroTik WireGuard
- `public/index.php` — public ATD routing wrapper.
- `public/index.legacy.php` — preserved original ATD front controller; do not casually edit.
- `public/index.core.php` — original RouteBox admin shell/content.
- `src/Admin/ATDStats.php` — sensitive four-card/status/system presentation.
- `src/Admin/ATDPanelSections.php` — ATD extra sections such as Users.
- `src/Admin/Plans/ProviderPlansSection.php` — provider plan UI.
- `src/Integrations/IBSng/IBSngSection.php` — IBSng UI.
- `src/Integrations/MikroTik/MikroTikSection.php` — MikroTik UI.
- `src/Integrations/MikroTik/MikroTikAdmin.php` — MikroTik logic; avoid for UI tasks.
- `public/routebox-server-action.php` — existing RouteBox edit/delete endpoint.
- `src/Admin/ATDUICompatibility.php` — presentation-only compatibility layer.

## Four cards / Stats

The four cards at the top are already working and must not be duplicated or rebuilt.

- Dashboard: global stats + Version/system card.
- Telegram Bot: Bot Server, Worker Status, Server IP, flag, Reload Worker.
- RouteBox Servers: Server Status, Connection, Server IP, Ping, flag, Refresh/Test Connection.
- IBSng Servers: Server Status, Connection, Server IP, Ping, flag, Refresh using the existing IBSng test-connection method.
- MikroTik WireGuard: Server Status, Connection, Server IP, Ping, flag, Refresh/Test Connection.

Dashboard Version card also shows CPU, RAM and Disk. Preserve it.

## Flags

Do not rely on Unicode regional-indicator emoji for legacy server flags. Firefox can render them as flags while Chrome/Edge may show `FR`, `IR`, etc.

`ATDStats` already uses real flag images. `ATDUICompatibility` converts legacy `.flag` elements to FlagCDN images with ISO codes and falls back to the code if the image fails.

## Branding

Visible panel UI branding is:

`ATD Panel, server and telegram bot control center`

Do not change user-editable bot messages/textareas merely for branding.

## RouteBox public section

The historical RouteBox section was `section=servers`. The public UI is now intended to use:

`section=routebox`

The legacy implementation can continue using `servers` internally for its existing POST/action contract. `public/index.php` maps the public alias to the legacy implementation without changing provider logic.

The original controller is preserved as `public/index.legacy.php`.

## Dashboard Servers button

It is reserved for the future project website URL. Until the real URL is supplied, it is shown as `Project Website` and does not navigate. Replace only that URL/text once the project URL is provided.

## Server pages

Visual order for all providers:

1. Server list/cards
2. Add Server

RouteBox:
- Existing `public/routebox-server-action.php` already supports `delete_server` and `update_server`.
- The UI exposes the existing Delete action; no second backend implementation is created.
- RouteBox server list is paginated at 10 when needed.

IBSng:
- Existing Test Connection remains the source of truth for Refresh.
- Existing Test Create User remains functional.
- Existing Edit/Delete remain functional.
- IBSng Group Name is required and must exactly match the real IBSng group for user creation.

MikroTik:
- Existing Delete Server action is preserved.
- Peer lists must remain paginated; never render thousands of peers into one huge page.
- Do not touch peer creation/IP allocation/API logic for UI work.

## Provider plans

All three provider plan pages share the same visual structure.

Actions stay on one row:
- Edit Plan
- Enable/Disable
- Delete

Provider Plan Key:
- IBSng: required/manual because it maps to the real IBSng group name.
- RouteBox: do not require manual entry.
- MikroTik: do not require manual entry.
- Preserve existing internal/provider-generated values during Edit.

Edit Plan uses a compact panel and must have Save Changes + Close; ESC-to-close is desirable.

## Users

Users page cards are at the top, below the USERS heading/subtitle and before the list:
- RouteBox Users — real service user count.
- IBSng Users — real service user count.
- MikroTik Users — real service user count.
- Telegram Bot Users — total Telegram bot users.

## Bot worker

Bot Server card shows Worker Status, Server IP, country flag and Reload.

Observed DEV server:
- `routebox-telegram-bot-dev.service` runs `/usr/bin/php /opt/routebox-telegram-bot-dev/worker.php`.
- `routebox-worker.service` exists but is disabled.
- Actual running worker was the DEV service process as `www-data`.

Do not display the long service name in the UI.

## Status cards

Status labels must use the same visual style as the rest of the panel. Flags should be clearly visible.

## Safety / docs

Major ATD UI changes should update:
- `README.md`
- `CHANGELOG.md`
- `ROADMAP.md`
- this file

Never write to the old `df05c42` backup.

## Recent incident

`af48f2a` temporarily introduced a PHP parse error into `ATDStats.php`; `1fd7ace` restored a syntax-safe baseline. Do not casually rebuild or replace ATDStats.

## Current compatibility layer

`src/Admin/ATDUICompatibility.php` is presentation-only and currently handles:
- public `section=routebox` aliasing compatibility,
- cross-browser flag images for legacy `.flag` elements,
- branding normalization without touching textarea/script/style content,
- removal of accidental literal `\\n` UI fragments,
- moving Add Server cards after server lists,
- RouteBox server pagination at 10 items,
- exposing the existing RouteBox Delete Server endpoint as a UI button.

## DEV deployment

```bash
cd /opt/routebox-telegram-bot-dev

git fetch origin '+refs/heads/ATD-Panel:refs/remotes/origin/ATD-Panel'
git reset --hard origin/ATD-Panel

systemctl restart routebox-telegram-bot-dev-web@8092.service
```

Run `php -l` on changed PHP files before restart.
