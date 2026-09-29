# Changelog

All notable changes to RouteBox Telegram Bot are documented here.

The repository version is defined by [`VERSION`](./VERSION). Release notes below describe the important user-visible and operational changes for each beta.

## 0.1.0-beta.9 — 2026-09-30

### Admin Panel UX / UI

- Rebuilt the Admin Panel visual layer with a cleaner, more professional control-center layout.
- Replaced the basic emoji-style navigation with inline SVG icons and consistent active/hover/focus states.
- Fixed sidebar selection so the current section remains highlighted after navigation and actions.
- Fixed post-action scrolling: mutations now return to the section that was just edited instead of jumping to the end of the page.
- Improved responsive navigation for smaller screens.
- Added clearer cards, status badges, buttons, forms, empty states and update status blocks.
- Added visible keyboard focus states and reduced-motion support for smoother, more accessible interaction.
- Replaced the old TLS informational note with a direct **Contact Support** Telegram button.

### Update Manager

- Dashboard now distinguishes installed version, GitHub version and updater readiness.
- Update Manager explicitly reports when the installed version is already the latest available version.
- Update actions now report a successful updater launch instead of only exposing a generic updater error.
- Updater availability detection also probes the executable through the system path, avoiding false "Updater is not installed" messages caused by PHP filesystem visibility.
- Existing restricted `sudo` updater architecture is preserved.

### Telegram Bot / Bilingual Preview

- Bot Menu Preview now shows **Persian and English side-by-side** instead of only the current/English view.
- Fixed literal `/n`, `\\n` and escaped newline text in welcome messages and fixed bot buttons.
- Button and welcome-message saves normalize newline representations before storing them.
- The Telegram worker also normalizes stored text at send time, so existing records containing `/n` are rendered correctly without manual re-entry.

### Compatibility

- RouteBox API integration, server credentials, plans, Telegram provisioning, SQLite schema and existing service architecture are preserved.
- No changes to RouteBox public ports `80/443`.
- Existing Apache/Nginx/RouteBox services remain outside the Bot Admin Panel's configuration scope.

## 0.1.0-beta.8 — 2026-09-30

### Admin Panel Updater

- **Fixed Admin Panel updater detection:** the PHP panel can now see the root-owned updater at `/usr/local/sbin/routebox-telegram-bot-update` while retaining the existing PHP filesystem sandbox.
- **Fixed updater execution:** the Admin Panel service no longer sets `NoNewPrivileges`, allowing the existing restricted `sudo` rule for `www-data` to invoke only the dedicated root updater.
- The updater continues to use the existing restricted sudoers rule; no unrestricted root shell is granted to the web panel.
- Existing Admin Panel port preservation, Bot/TLS services, and RouteBox/Apache/Nginx isolation are unchanged.
- No changes are made to RouteBox ports `80/443` or the RouteBox panel/API listener.

## 0.1.0-beta.7 — 2026-09-29

### HTTPS / Admin Panel

- **Fixed the same-port TLS architecture:** enabling HTTPS no longer changes the configured Admin Panel public port.
- HTTP and HTTPS are both accepted on the **same public Admin Panel port**. Example: `http://server:8093` and `https://domain:8093`.
- RouteBox's own panel/API listener remains untouched.
- RouteBox's exported panel certificate is reused for the Bot Admin Panel instead of requesting a second certificate.
- Added a dedicated Bot-owned HAProxy TCP multiplexer to distinguish plain HTTP from a TLS ClientHello.
- TLS traffic is terminated by a dedicated loopback-only stunnel instance and then forwarded to the same PHP backend.
- PHP and the TLS terminator use separate loopback-only backend ports; neither is publicly exposed.
- Added explicit HTTP and HTTPS health checks on the **same public port** before TLS setup reports success.
- Certificate synchronization continues to watch the RouteBox certificate and reload the Bot TLS terminator after renewal.
- Ports `80/443` and existing Apache/Nginx/RouteBox services are not claimed or reconfigured by the Bot.

### Documentation / Maintenance

- README rewritten for Beta 7 and aligned with the actual installer/TLS architecture.
- `VERSION` is documented as the single source of truth so release numbers do not become stale in documentation.
- Repository layout and the purpose of installer/repair/update scripts are documented explicitly.

## 0.1.0-beta.6 — 2026-09-29

### HTTPS / Admin Panel

- Added reuse of the existing RouteBox panel certificate at `/etc/routebox/panel-cert/` for the Bot Admin Panel.
- Added automatic certificate synchronization so RouteBox certificate renewal can be reflected in the Bot TLS frontend.
- Added an independent Bot-owned TLS frontend without taking over ports `80/443` or modifying an existing RouteBox/Apache/Nginx configuration.
- Added TLS setup/repair automation through `setup-routebox-tls.sh`.
- Added HTTPS service health checks and service status diagnostics.

### Installer / Operations

- The main installer applies the optional RouteBox TLS integration after the normal Admin Panel health check.
- Existing installations can apply the integration directly with `setup-routebox-tls.sh`.
- The Admin Panel updater and server updater restore the optional TLS integration after an update when the RouteBox certificate is available.

## 0.1.0-beta.5 — 2026-09-29

### Fixed

- Fixed an Admin Panel PHP/parse-error issue that could leave the panel unavailable after deployment/update.
- Tightened the final Admin Panel health-check and repair flow so installation does not report success while the panel is broken.
- Kept the independent PHP listener architecture and avoided taking ownership of existing web servers.

### Maintenance

- Improved operational diagnostics around Admin Panel permissions, service state and PHP configuration loading.

## 0.1.0-beta.4 — 2026-09-29

### Admin Panel

- Added a modern responsive dashboard with sidebar navigation and cleaner cards/forms.
- Added Persian and English Admin Panel UI with one-click language switching.
- Added dark/light theme with browser preference detection and saved theme choice.
- Server cards show country flag/code and measured TCP ping.
- Existing servers without a country can use automatic public-IP country detection.
- Added editable Persian/English Telegram welcome messages.
- Added editable Persian/English labels for fixed Telegram buttons.
- Plan management remains editable for duration and traffic quota.
- Added Admin password change from the panel with an 8-character minimum.
- Retained the in-panel updater.

### Telegram Bot

- Added Persian/English user language support.
- First-time users receive a language selector and can switch language later.
- Added `/start`, `/menu`, `/account`, and `/language` command handling.
- Welcome text is controlled from the Admin Panel.
- Fixed buttons for free trial, account and language are controlled from the Admin Panel.
- Plan buttons continue to be generated from enabled Plans.

### Database

- Added persistent Telegram user language.
- Added `telegram_buttons` storage for editable bot button labels.
- Added bilingual welcome-message settings.
- Added runtime migration support so existing Beta 3 installations can upgrade without manual database editing.

## 0.1.0-beta.3

### Admin / Maintenance

- Added the restricted Admin Panel updater.
- Added Admin password reset/change support.
- Added Admin Panel update logs.
- Added RouteBox/AmneziaWG repair documentation.
- Added application version and changelog handling.

## 0.1.0-beta.2

### Core

- Established the stable RouteBox API integration.
- Added the independent PHP Admin Panel listener.
- Avoided automatic Nginx/Apache reconfiguration.
- Added the RouteBox AmneziaWG create/export/delete smoke test during installation.
- Added the basic installer flow, Telegram validation and RouteBox validation.
- Added encrypted storage for Telegram and RouteBox credentials.
- Added multi-RouteBox server configuration and provisioning.
- Added expiration and traffic-quota support for plans.
- Added `.conf` delivery to Telegram users.
- Added free-trial provisioning and trial reuse protection.
- Added rollback attempts when multi-server provisioning partially fails.

## 0.1.0-beta.1

### Initial foundation

- Initial RouteBox Telegram Bot architecture.
- Telegram worker and independent Admin Panel foundation.
- RouteBox client abstraction for API-based management.
- Initial AmneziaWG provisioning flow.
- Initial SQLite-backed configuration and user/service data model.
