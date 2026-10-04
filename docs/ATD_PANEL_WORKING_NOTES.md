# ATD Panel — Working Notes

Durable handoff for the ATD Panel. Read this before further UI or security work.

## Golden rule

**UI/presentation changes only unless explicitly requested otherwise.** Do not rewrite provider APIs, provisioning, repositories, allocation, authentication, payment, IBSng group mapping, MikroTik peer creation/IP allocation, or other tested provider logic merely to change appearance.

`df05c42` is the read-only historical backup/reference. Never write to it.

## Current branch / milestone

- Working branch: `ATD-Panel`
- Current reviewed head: `04bf2bc33c26921e2d36dc3dd16583c535f48c4f`
- Current milestone: `0.1.0-beta.11.05`
- DEV deployment target used during UI work: `/opt/routebox-telegram-bot-dev`, web service `routebox-telegram-bot-dev-web@8092.service`.

## Providers and important files

- RouteBox
- IBSng
- MikroTik WireGuard
- `public/index.php` — public ATD routing wrapper.
- `public/index.legacy.php` — preserved original ATD front controller; do not casually edit.
- `public/index.core.php` — original RouteBox admin shell/content.
- `src/Admin/ATDStats.php` — sensitive four-card/status/system presentation. **Do not rebuild casually.**
- `src/Admin/ATDPanelSections.php` — ATD extra sections such as Users.
- `src/Admin/Plans/ProviderPlansSection.php` — provider plan UI.
- `src/Integrations/IBSng/IBSngSection.php` — IBSng UI.
- `src/Integrations/MikroTik/MikroTikSection.php` — MikroTik UI.
- `src/Integrations/MikroTik/MikroTikAdmin.php` — MikroTik logic; avoid for UI tasks.
- `public/routebox-server-action.php` — existing RouteBox edit/delete endpoint.
- `src/Admin/ATDUICompatibility.php` — presentation-only compatibility layer.
- `docs/SECURITY_BASELINE.md` — security contract/checklist.

## What is completed and must be preserved

### Dashboard / ATD identity

- ATD Panel branding is normalized to `ATD Panel, server and telegram bot control center` where appropriate.
- Dashboard structure and the four top cards are preserved.
- Dashboard Version/System card remains intact and includes CPU/RAM/Disk information.
- The Dashboard `Servers` button is reserved for the future project website. Until the real URL is supplied, it is a non-navigating `Project Website` placeholder. Do not point it back to the admin server section.

### Four cards / Stats

The four cards at the top are already working and must not be duplicated or rebuilt.

- Dashboard: global stats + Version/system card.
- Telegram Bot: Bot Server, Worker Status, Server IP, flag, Reload Worker.
- RouteBox Servers: Server Status, Connection, Server IP, Ping, flag, Refresh/Test Connection.
- IBSng Servers: Server Status, Connection, Server IP, Ping, flag, Refresh using the existing IBSng test-connection method.
- MikroTik WireGuard: Server Status, Connection, Server IP, Ping, flag, Refresh/Test Connection.

Dashboard Version card also shows CPU, RAM and Disk. Preserve it.

### Flags

Do not rely on Unicode regional-indicator emoji for legacy server flags. Firefox can render them as flags while Chrome/Edge may show `FR`, `IR`, etc.

`ATDStats` already uses real flag images. `ATDUICompatibility` converts legacy `.flag` elements to FlagCDN images with ISO codes and falls back to the code if the image fails.

### Branding / UI compatibility layer

`src/Admin/ATDUICompatibility.php` is presentation-only. It currently handles:

- public `section=routebox` aliasing compatibility,
- cross-browser flag images for legacy `.flag` elements,
- branding normalization without touching textarea/script/style content,
- removal of accidental literal `\\n` UI fragments,
- moving Add Server cards after server lists,
- exposing the existing RouteBox Delete Server endpoint as a UI button,
- theme/accent styling across legacy and ATD UI variables,
- compact accent-color picker using color-only swatches,
- top language/theme/accent controls where applicable,
- responsive desktop/mobile sidebar compatibility.

The compatibility layer must remain presentation-only. Do not move provider business logic into it.

### Theme / palette / language

- Dark and light themes are supported.
- Light theme uses a soft Windows-11-like neutral background rather than plain white.
- Accent palettes currently include Blue, Indigo, Emerald, Cyan, Amber and Rose.
- Accent selection is wired into the legacy `--primary`, `--primary2` and related variables so it affects more than only the accent buttons.
- Color picker shows only color swatches; do not re-add color names unless intentionally redesigned.
- The existing sidebar language switch is the canonical language mechanism. Do not create a second sidebar language button.
- A compact top language control may exist for desktop/topbar UX, but it must use the same server-side `?lang=` mechanism as the working sidebar switch.
- Persian sidebar labels are normalized by the UI layer where legacy English labels remain.

### Responsive behavior

- Desktop sidebar geometry is explicitly constrained to avoid Firefox becoming excessively wide.
- Mobile has no permanent sidebar. A menu button opens a bounded overlay/sidebar rather than occupying the entire screen.
- Mobile sidebar has a visible close button and scrollable content.
- Mobile navigation exposes provider subitems instead of forcing the user to guess which icon opens which page.
- Responsive changes must not alter provider actions or routes.

### RouteBox public section

The historical RouteBox section was `section=servers`. The public UI is now intended to use:

`section=routebox`

The legacy implementation can continue using `servers` internally for its existing POST/action contract. `public/index.php` maps the public alias to the legacy implementation without changing provider logic.

The original controller is preserved as `public/index.legacy.php`.

### Server pages

Visual order for all providers:

1. Server list/cards
2. Add Server

When server records exist, the list must appear before the Add Server form. Empty states may show the Add form naturally.

#### RouteBox

- Existing `public/routebox-server-action.php` supports `delete_server` and `update_server`.
- The UI exposes the existing Delete action; no second provider/backend implementation is created.
- Public UI uses `section=routebox` while preserving the legacy internal action contract.
- **Server pagination target: 5 servers per page everywhere.** This is a UI requirement and must be kept consistent if pagination is refactored.

#### IBSng

- Existing Test Connection remains the source of truth for Refresh.
- Existing Test Create User remains functional.
- Existing Edit/Delete remain functional.
- IBSng Group Name / Provider Plan Key is required and must exactly match the real IBSng group used for user creation.
- Do not weaken or rename the IBSng group mapping just for UI consistency.
- **Server pagination target: 5 servers per page.**

#### MikroTik

- Existing Delete Server action is preserved.
- Existing peer creation/IP allocation/API logic is not to be changed for UI work.
- Peer lists must remain paginated; current implementation uses 50 peers per page. This is separate from the requested 5-server-per-page rule.
- **Server pagination target: 5 servers per page.**

### Provider plans

All three provider plan pages share the same visual structure.

Actions stay on one row:
- Edit Plan
- Enable/Disable
- Delete

`Edit Plan` must be on the same action row as Disable/Delete, not on a separate line.

Provider Plan Key:
- IBSng: required/manual because it maps to the real IBSng group name.
- RouteBox: do not require manual entry.
- MikroTik: do not require manual entry.
- Preserve existing internal/provider-generated values during Edit.

Edit Plan uses a compact panel and must have Save Changes + Close; Escape-to-close is desirable. The edit panel is presentation-only and must not replace the existing update logic.

### Users

Users page cards are at the top, below the USERS heading/subtitle and before the list:
- RouteBox Users — real service user count.
- IBSng Users — real service user count.
- MikroTik Users — real service user count.
- Telegram Bot Users — total Telegram bot users.

### Bot worker

Bot Server card shows Worker Status, Server IP, country flag and Reload.

Observed DEV server:
- `routebox-telegram-bot-dev.service` runs `/usr/bin/php /opt/routebox-telegram-bot-dev/worker.php`.
- `routebox-worker.service` exists but is disabled.
- Actual running worker was the DEV service process as `www-data`.

Do not display the long service name in the UI.

## Security review status

### SQL Injection — reviewed: no confirmed exploitable finding

A source review of the current ATD branch found the main database paths using prepared statements / parameter binding for variable values, including RouteBox, IBSng, MikroTik, service catalog/provisioning, plans and bootstrap/admin paths.

Dynamic SQL locations observed are currently constrained by internal/whitelisted table/column values rather than direct HTTP input. Examples include schema helpers such as `ensureColumn()` and whitelisted provider table selection in the provider-plan layer. These are **not currently confirmed SQL injection vulnerabilities**, but they remain review points whenever schema/provider mapping code changes.

Do not change SQL solely for style if it is already safely parameterized. If a future source scan finds variable SQL concatenation, report the exact file/function and exploit path before modifying it.

### Security baseline already added

`docs/SECURITY_BASELINE.md` is the project security contract. It requires review of:

- SQL injection / prepared statements
- Stored/reflected/DOM XSS
- CSRF
- IDOR/BOLA / authorization
- authentication/session security
- SSRF
- command/argument injection
- path traversal / arbitrary file access
- unsafe deserialization
- TLS verification
- secret/credential exposure
- security headers
- production-vs-development boundaries
- file upload/download and backup exposure

The security baseline explicitly says a passing `php -l` is not a security approval.

### Security work still required before release approval

The following have **not yet been fully completed as an evidence-based whole-repository security audit** and must not be described as finished:

1. Full XSS audit: stored, reflected and DOM sinks across the complete repository.
2. Full CSRF audit of every state-changing admin endpoint/action.
3. Full IDOR/BOLA/authorization audit for server, plan, user, peer and provider IDs.
4. Authentication/session review, including fixation/regeneration, cookie attributes, brute-force controls and authorization boundaries.
5. SSRF review of provider/server URL, host, callback/webhook and network-destination handling.
6. Command injection / argument injection review of all shell/process execution and installer/updater paths.
7. Path traversal, arbitrary file read/write, upload/download and backup exposure review.
8. TLS/certificate/hostname verification review for all provider/API connections.
9. Security-header and production HTTP configuration review.
10. Secret/credential scan across source, logs, HTML/JS and Git history where feasible.
11. Dependency/configuration/security hardening review.
12. DEV/staging negative tests for SQLi/XSS/CSRF/IDOR/SSRF/path traversal/command injection. Destructive exploit tests must not be run against production.

### Security rule for future fixes

Before fixing a security issue:

1. Report whether a vulnerability actually exists.
2. Identify severity, affected file/function, precondition and impact.
3. Prefer the smallest localized remediation.
4. Do not mix a security fix into unrelated UI work.
5. Run syntax checks and targeted regression tests after the fix.
6. Never claim the application is "unhackable"; classify release status as BLOCKED / CONDITIONAL / APPROVED FOR RELEASE according to `SECURITY_BASELINE.md`.

## Important current implementation caveats

- The current branch contains UI and compatibility work that is intended to preserve tested provider behavior, but some UI requirements are documented here as targets rather than proof that every browser/device has been manually verified.
- In particular, the 5-server pagination requirement is now the canonical target; if the implementation still shows a different page size, change only the pagination/presentation layer and do not touch provider logic.
- The current MikroTik peer pagination is 50, intentionally separate from server pagination. Do not render thousands of peers in one page.
- The current changelog has some historical wording that may lag behind the latest UI delete-action work; treat this working-notes file and the actual branch code as the authoritative handoff until the changelog is reconciled.

## Recent incident / caution

`af48f2a` temporarily introduced a PHP parse error into `ATDStats.php`; `1fd7ace` restored a syntax-safe baseline. Do not casually rebuild or replace `ATDStats`.

## Deployment / verification checklist

DEV deployment:

```bash
cd /opt/routebox-telegram-bot-dev

git fetch origin '+refs/heads/ATD-Panel:refs/remotes/origin/ATD-Panel'
git reset --hard origin/ATD-Panel

php -l src/Admin/ATDStats.php
php -l src/Admin/ATDUICompatibility.php
php -l src/Admin/Plans/ProviderPlansSection.php
php -l src/Integrations/IBSng/IBSngSection.php
php -l src/Integrations/MikroTik/MikroTikSection.php

systemctl restart routebox-telegram-bot-dev-web@8092.service
```

After deployment, manually verify at minimum:

- Chrome + Edge + Firefox desktop flags.
- Desktop sidebar width.
- Mobile menu width/overlay/scroll/close.
- Persian and English navigation.
- Dark/light theme and all accent colors.
- Four dashboard/status cards.
- RouteBox/IBSng/MikroTik server list before Add Server.
- 5-server pagination.
- Provider plan action row and compact Edit/Close behavior.
- IBSng Provider Plan Key/group behavior.
- RouteBox and MikroTik Delete Server buttons.
- Existing Test Connection / Test Create User behavior.
- No literal `\\n` fragments.

## Major changes / files that should be treated carefully

- `public/index.php`
- `public/index.legacy.php`
- `public/index.core.php`
- `src/Admin/ATDStats.php`
- `src/Admin/ATDUICompatibility.php`
- `src/Admin/Plans/ProviderPlansSection.php`
- `src/Integrations/IBSng/IBSngSection.php`
- `src/Integrations/IBSng/IBSngAdmin.php`
- `src/Integrations/MikroTik/MikroTikSection.php`
- `src/Integrations/MikroTik/MikroTikAdmin.php`
- `public/routebox-server-action.php`

When the task is UI-only, prefer editing `ATDUICompatibility.php` or the presentation markup in the relevant `*Section.php` file. Do not modify `*Admin.php`, repositories, provisioning, allocation or protocol clients unless the user explicitly asks for a functional/security change.

## Handoff rule for a new chat

If memory is full and another chat continues this project, read this file first. The next assistant should understand:

- this is the `ATD-Panel` branch of `PardisMobile/routebox-telegram-bot`;
- provider logic is precious and must be preserved;
- UI work must be isolated from provider/business logic;
- the four cards and dashboard structure are already working and must not be rebuilt;
- RouteBox public section is `section=routebox` with legacy compatibility underneath;
- IBSng Provider Plan Key is special and must map exactly to the real IBSng group;
- RouteBox/MikroTik Provider Plan Key should not be manually required;
- server lists come before Add Server;
- server pagination target is 5 everywhere;
- MikroTik peers remain separately paginated at 50 currently;
- flags must work in Chrome/Edge/Firefox using image assets, not emoji;
- dark/light/accent/theme and responsive sidebar are UI concerns;
- the existing sidebar language switch is canonical;
- the security baseline is mandatory;
- SQL injection has been reviewed with no confirmed exploitable finding so far;
- the remaining security audit areas are explicitly listed above;
- before any security fix, report the finding first and make the smallest safe change.
