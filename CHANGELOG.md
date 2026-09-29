# Changelog

All notable changes to RouteBox Telegram Bot are documented here.

## [0.1.0-beta.3] - 2026-09-29

### Added

- 🛒 **Telegram plan/button manager** in the Admin Panel.
  - Create, edit, enable/disable and delete buttons without editing PHP code.
  - Each plan supports a duration in days and an optional traffic quota in GB.
  - `0 GB` means unlimited traffic.
  - Enabled plans appear automatically under `/start` in Telegram.
- 📡 RouteBox server **TCP latency/ping** measurement in the Admin Panel.
- 🌍 Optional two-letter **country code + flag** for each RouteBox server, with best-effort detection from the hostname when possible.
- ☀️🌙 **Light/Dark mode** for the Admin Panel, remembered in the browser.
- 🔐 Direct **Change Password** link from the dashboard.
- 🔄 **Admin Panel software updater** with GitHub version checking and controlled service restart.
  - Stops the Bot worker and Bot Admin Panel before replacing the application code.
  - Runs the existing `update.sh` update path.
  - Starts the services again after a successful update.
  - Uses a restricted `sudo` rule for the fixed updater only; the web user is not granted general root access.
- 📝 Release notes and update documentation for future versions.
- ©️ Creator footer linking to **Amir Taheri** via Telegram phone deep link.

### Changed

- Provisioning now passes the selected plan's expiry and traffic quota to RouteBox when a plan button is used.
- Telegram `/start` is now generated dynamically from enabled plans instead of being limited to two hard-coded buttons.
- The Admin Panel UI was refreshed for Persian RTL usage and responsive mobile/desktop layouts.
- Existing RouteBox/Nginx/Apache ports remain untouched by the Bot update architecture.
- The README now identifies this release as **Beta 3** and documents the new Admin Panel updater.

### Notes

- Payment processing is intentionally **not** included in this release. Plan buttons provision immediately for testing; payment integration remains the next phase.
- Users are still protected by the current one-provision-per-Telegram-account rule. Subscription renewal/upgrades will be added with the payment/subscription system.

## [0.1.0-beta.2]

- Independent PHP Admin Panel on a free local port.
- RouteBox API session authentication with Basic fallback.
- Full RouteBox + AmneziaWG create/config/delete smoke test.
- Telegram token validation and encrypted credential storage.
- Admin password recovery and 8-character minimum password policy.

---

© 2026 [Amir Taheri](https://t.me/+918807085399) · RouteBox Telegram Bot
