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

### 📦 Installer tooling and documentation

- Documented `install.sh` as the user-facing production entry point.
- Documented that production `install.sh` delegates to `install-v2.sh` and then performs the existing repair, restricted updater and optional RouteBox TLS integration steps.
- Documented the production one-command installation path.
- Documented `install-dev.sh` as the development entry point for `feature/modular-services-ibsng`.
- Documented that `install-dev.sh` delegates to `install-dev-full.sh` and uses the isolated `routebox-telegram-bot-dev` deployment paths.
- Documented the actual production/development installer responsibilities instead of treating the four installer files as interchangeable.
- Updated README installation, IBSng architecture and upcoming Admin roadmap documentation.

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
- `install-v2.sh` remains the production setup implementation used by the main entry point.
- `install-dev.sh` / `install-dev-full.sh` are isolated development tools for the modular-services/IBSng branch.
- Existing tested IBSng provisioning must be extended, not rewritten.
- Existing RouteBox provisioning must remain backward-compatible.
- Telegram Bot Admin permissions are independent from IBSng `owner` / `owner_name`.
- Payment integration must remain provider-independent.
