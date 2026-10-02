# Changelog

Important user-visible and operational changes for RouteBox Telegram Bot.

Version source of truth: [`VERSION`](./VERSION)

## 0.1.0-beta.11.05 — Current milestone

### 🔵 Completed IBSng modular-service milestone

- Completed the modular IBSng provider integration without replacing the existing RouteBox/AWG provisioning path.
- Added IBSng server configuration and server/group mapping foundations.
- Added IBSng group listing / synchronization support.
- Added IBSng authentication and session handling.
- Added IBSng user lookup / user information operations.
- Added IBSng test-user creation support.
- Completed end-to-end IBSng account creation from Telegram.
- Completed automatic IBSng Internet Username + password provisioning.
- Persisted IBSng subscriptions in `service_subscriptions`.
- Added provider/server/group-aware IBSng provisioning.
- Kept the IBSng protocol client isolated under `src/Integrations/IBSng/`.
- Updated the Worker integration so the isolated `IBSngClient` is correctly loaded by the long-running Telegram process.
- Verified the real IBSng A1.24 provisioning flow successfully.
- Documented the one-account model for the configured OpenVPN / Cisco / L2TP access methods.

### 🧩 Modular services

- Provider-independent service catalog / routing / dispatching is documented and retained.
- RouteBox and IBSng remain separate providers.
- `service_subscriptions` provides persistent provider-neutral subscription records.
- The existing RouteBox provisioning implementation remains backward-compatible.
- IBSng `owner` / `owner_name` terminology remains an IBSng-specific concept and is not treated as Telegram Bot Admin authorization.

### 📦 Installer refactor — completed

- Renamed the production implementation from `install-v2.sh` to `installer-core.sh`.
- Kept `install.sh` as the user-facing production entrypoint.
- Updated `install.sh` to download and execute `installer-core.sh` from `main`.
- Retained `install-dev.sh` and `install-dev-full.sh` as the development/IBSng installer flow.
- Removed the retired `install-v2.sh` path from the production installer structure.
- Improved `install.sh` with a professional banner, colored status output, clear sections, error handling and a final installation summary.
- Improved `installer-core.sh` output with section headers, readable progress messages and service/installation summary information.
- Preserved the existing production installation architecture, systemd services, Admin Panel, RouteBox ports and RouteBox/IBSng application code.
- Updated installation documentation and roadmap terminology to the final four-file installer structure.

### 💳 Payment foundation

- `PaymentGatewayInterface`, `PaymentResult` and `OrderService` remain available as the payment abstraction foundation.
- Orders, payment providers, coupons and coupon redemptions have schema support.
- Real payment gateways are not yet documented as production-ready.

### 🔐 Security / reliability

- Provider credentials and Telegram Bot Tokens remain encrypted at rest.
- Admin actions use CSRF protection and secure session cookie settings.
- The production updater remains restricted through its dedicated root wrapper.
- Duplicate Telegram polling is prevented by the existing Worker lock.
- Production installer/update flows do not take ownership of existing Apache/Nginx/RouteBox ports 80/443.

## 🧭 Upcoming

### Telegram Bot Admin

- [ ] Independent Telegram Bot Admin authentication / authorization.
- [ ] Configure Bot Admin users from the Web/Admin Panel.
- [ ] Support multiple Telegram numeric administrator IDs.
- [ ] Dedicated Bot Admin Telegram menu.
- [ ] Telegram service-management operations for authorized admins.

### Admin payment bypass

- [ ] Allow authorized Telegram Bot Admin users to create services without customer payment.
- [ ] RouteBox service creation without payment.
- [ ] IBSng service creation without payment.
- [ ] Provider-neutral design for future service providers.
- [ ] Preserve the normal customer payment flow.
- [ ] Record admin-created orders/provisioning actions for auditability.

### IBSng user management

- [ ] Search IBSng users by username.
- [ ] View username and account information.
- [ ] Show expiry date and Persian/Shamsi expiry date.
- [ ] Show traffic/quota usage when applicable.
- [ ] Renew users using the IBSng plans already configured in the RouteBox Admin Panel.
- [ ] Edit IBSng users where supported.
- [ ] Add further safe account-management actions as appropriate.

### IBSng administration tools

- [ ] Configure the generated IBSng username prefix from the Admin Panel.
- [ ] Keep the current generated prefix `rb` as the default; support values such as `tgbot` later.
- [ ] Move `test-ibsng-account.php` into a safe IBSng Admin Panel UI.

### Worker / operations

- [ ] Worker status in the Admin Panel.
- [ ] Telegram Bot Worker restart/reload button targeting the existing Worker service.
- [ ] Worker health monitoring.
- [ ] Recent operational logs / diagnostics.

### Payment

- [ ] Complete order lifecycle.
- [ ] Payment callback endpoints and verification.
- [ ] ZarinPal adapter.
- [ ] Crypto gateway adapter.
- [ ] Verified-payment-only provisioning.
- [ ] Coupon administration and enforcement.
- [ ] Invoice and payment history.

## 📝 Release / development rules

- Production installs use `install.sh`.
- `installer-core.sh` is the internal production setup implementation used by the public entry point.
- `install-dev.sh` / `install-dev-full.sh` are isolated development tools for the modular-services/IBSng branch.
- Existing tested IBSng provisioning must be extended, not rewritten.
- Existing RouteBox provisioning must remain backward-compatible.
- Telegram Bot Admin permissions are independent from IBSng `owner` / `owner_name`.
- Payment integration must remain provider-independent.
