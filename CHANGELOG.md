# Changelog

All notable changes to RouteBox Telegram Bot are documented here.

## 0.1.0-beta.6 — 2026-09-30

### RouteBox TLS integration
- Added a safe HTTPS integration for the Admin Panel using the certificate already issued and maintained by RouteBox.
- Reuses RouteBox's canonical panel certificate export at `/etc/routebox/panel-cert/{fullchain.pem,key.pem}` when available.
- Uses a dedicated TLS wrapper for the Bot Admin Panel; the PHP backend is moved to loopback only.
- Does **not** install or reconfigure Nginx or Apache.
- Does **not** bind, change, or occupy ports `80` or `443`.
- Keeps the Telegram worker service `routebox-telegram-bot.service` unchanged.
- Adds a small systemd timer to sync RouteBox certificate renewals automatically and reload the TLS listener.
- Keeps the existing Admin Panel port, so an existing `:8093` panel remains `:8093`, now over HTTPS.
- If the RouteBox certificate is not available, the installer safely leaves the Admin Panel on HTTP instead of breaking the installation.

## 0.1.0-beta.5 — 2026-09-30

### Admin Panel Hotfix
- Fixed the PHP parse error that caused the Admin Panel to return HTTP 500.
- Replaced the affected mixed `foreach`/alternative-syntax blocks with explicit, valid PHP blocks.
- Fixed the missing TLS translation key in the bilingual panel.
- Kept the existing RouteBox/TLS architecture unchanged; the panel does not bind or reconfigure ports 80/443.
- Kept server country flag/ping display, bilingual UI, dark/light theme, editable welcome messages, editable bot buttons, plan management, password change, and in-panel updater functionality.

## 0.1.0-beta.4 — 2026-09-29

### Admin Panel
- Modern responsive dashboard with sidebar navigation and cleaner cards/forms.
- Persian and English panel UI with one-click language switching.
- Dark/light theme with browser preference detection and saved theme choice.
- Server cards now show country flag/code and measured TCP ping.
- Automatic country detection is attempted for existing servers whose country is empty.
- Editable Persian/English Telegram welcome messages.
- Editable Persian/English labels for fixed Telegram buttons.
- Plan management remains fully editable for duration and quota.
- Admin password can be changed from the panel; minimum remains 8 characters.
- Existing HTTPS/TLS architecture remains untouched; the panel does not bind 80/443.
- Existing in-panel updater is retained.

### Telegram Bot
- Added Persian/English user language support.
- First-time users receive the language selector and can switch language later.
- `/start`, `/menu`, `/account`, and `/language` commands are supported.
- Welcome text is controlled from the Admin Panel.
- Fixed buttons (free trial, account, language) are controlled from the Admin Panel.
- Plan buttons continue to be generated from the editable Plans section.

### Database
- Added persistent user language field.
- Added `telegram_buttons` table for editable bot button labels.
- Added bilingual welcome-message settings.
- Runtime migration is included so existing Beta 3 installations upgrade without manual database editing.

## 0.1.0-beta.3

- Added restricted Admin Panel updater.
- Added password reset/change support.
- Added admin-panel update logs.
- Added RouteBox/AmneziaWG repair documentation.
- Added version and changelog handling.

## 0.1.0-beta.2

- Stable RouteBox API integration.
- Admin panel on an independent PHP listener.
- No automatic Nginx/Apache reconfiguration.
- RouteBox AWG create/export/delete smoke test during installation.
