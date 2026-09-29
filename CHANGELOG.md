# Changelog

All notable changes to RouteBox Telegram Bot are documented here.

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
