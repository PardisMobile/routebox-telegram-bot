# RouteBox Telegram Bot Roadmap

## ✅ Completed — IBSng modular service milestone

- [x] Modular service architecture
- [x] Provider-based provisioning
- [x] RouteBox and IBSng independent service providers
- [x] IBSng A1.24 Web Panel / API integration
- [x] IBSng authentication and session handling
- [x] IBSng server configuration foundation
- [x] IBSng group mapping
- [x] IBSng group listing / synchronization support
- [x] IBSng user lookup / user information operations
- [x] IBSng test-user creation support
- [x] End-to-end IBSng account provisioning from Telegram
- [x] Automatic Internet Username + password provisioning
- [x] Provider/server/group-aware IBSng provisioning
- [x] `service_subscriptions` persistence
- [x] Worker integration with the isolated `IBSngClient`
- [x] One IBSng account for the configured OpenVPN / Cisco / L2TP access methods
- [x] Real IBSng A1.24 provisioning flow tested successfully

> **Architecture note:** IBSng `owner` / `owner_name` is an IBSng-specific concept. It is separate from Telegram Bot Admin permissions.

## 🔜 Upcoming Phase 1 — Telegram Bot Admin

### Independent permission system

- [ ] Create an independent Telegram Bot Admin authentication / authorization layer.
- [ ] Configure Telegram Bot Admin users from the Web/Admin Panel.
- [ ] Support **multiple Telegram numeric IDs**; do not limit the system to one administrator ID.
- [ ] Provide a dedicated/admin Telegram menu for authorized Bot Admin users.
- [ ] Keep Telegram Bot Admin permissions completely separate from IBSng `owner` / `owner_name`.

### Admin service management

- [ ] Allow authorized Telegram Bot Admin users to manage services through Telegram.
- [ ] Allow Bot Admin to create a new RouteBox service without customer payment.
- [ ] Allow Bot Admin to create a new IBSng service without customer payment.
- [ ] Design the payment bypass as provider-neutral so future service providers can use the same authorization path.
- [ ] Keep the normal customer payment flow unchanged.
- [ ] Record admin-created orders/provisioning actions for auditability.

## 🔵 Upcoming Phase 2 — IBSng user management through Bot Admin

### Search and information

- [ ] Search IBSng users by username.
- [ ] Display IBSng username information.
- [ ] Display account information.
- [ ] Display account expiry date.
- [ ] Display expiry date in Persian/Shamsi format.
- [ ] Display traffic/quota usage when the IBSng service is quota-based.

### Renewal and editing

- [ ] Renew an IBSng user.
- [ ] Renewal must use the IBSng plans already configured in the RouteBox Admin Panel.
- [ ] Do **not** introduce a separate hard-coded renewal-plan catalog.
- [ ] Edit IBSng user information where supported by the existing provider capabilities.
- [ ] Add future safe account-management actions as appropriate.

## ⚙️ Upcoming Phase 3 — IBSng administration tools

### Configurable username prefix

- [ ] Make the generated IBSng username prefix configurable from the Admin Panel.
- [ ] Keep the current generated prefix as `rb` by default.
- [ ] Allow an administrator to change it to values such as `tgbot`.
- [ ] Scope this setting specifically to generated IBSng usernames.

### IBSng test-user UI

- [ ] Add IBSng test-user creation to the IBSng section of the Admin Panel.
- [ ] Move the capability currently exposed by `test-ibsng-account.php` into a safe Admin Panel UI.
- [ ] Keep the existing working IBSng test/provisioning code intact while adding the UI orchestration layer.

## 🔧 Upcoming Phase 4 — Telegram Worker operations

- [ ] Add Worker status to the Telegram Bot section of the Admin Panel.
- [ ] Add a restart/reload button for the Telegram Bot Worker.
- [ ] Ensure the control targets the correct existing Worker systemd service.
- [ ] Do not change or break the current working Worker behavior.
- [ ] Add Worker health monitoring.
- [ ] Add recent Worker logs / diagnostics.

## 💳 Future payment system

- [ ] Complete order lifecycle.
- [ ] Coupon management and enforcement.
- [ ] Payment callback endpoints.
- [ ] Payment verification state machine.
- [ ] ZarinPal adapter.
- [ ] Crypto gateway adapter.
- [ ] Verified-payment-only customer provisioning.
- [ ] Invoice and payment history.

## 📦 Completed — Installer refactor and deployment documentation

- [x] `install.sh` remains the production user-facing entry point.
- [x] `install-v2.sh` renamed to `installer-core.sh`.
- [x] `install.sh` updated to download and execute `installer-core.sh`.
- [x] `install-dev.sh` remains the development entry point for `feature/modular-services-ibsng`.
- [x] `install-dev-full.sh` remains the full development / IBSng developer setup.
- [x] Final repository structure contains exactly four installer files.
- [x] Production installer UX improved with professional/colorful output, clear sections and final installation summary.
- [x] Installer core UX improved without rewriting the production installation architecture.
- [x] Existing production ports, systemd services, Worker, Web/Admin Panel and RouteBox installation behavior preserved.
- [x] README and CHANGELOG updated to the final installer structure.

## Rules for future development

1. Existing IBSng end-to-end provisioning is working and must not be rewritten as part of Telegram Bot Admin work.
2. Existing RouteBox provisioning must remain backward-compatible.
3. Telegram Bot Admin is an independent permission system and must not be conflated with IBSng `owner` / `owner_name`.
4. Telegram Bot Admin must support multiple numeric Telegram IDs.
5. Payment bypass must be provider-neutral and must not alter the normal customer payment path.
6. IBSng renewal must consume the plans configured in the RouteBox Admin Panel.
7. New Admin UI features should orchestrate the existing provider modules rather than moving provider protocol logic into the UI.
8. Future development for Telegram Bot Admin should start on `feature/telegram-bot-admin`.
