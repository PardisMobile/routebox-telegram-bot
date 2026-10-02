# Changelog

Important user-visible and operational changes for RouteBox Telegram Bot.

Version source of truth: [`VERSION`](./VERSION)

## 0.1.0-beta.11.05 — Current

### ✅ Current platform / operations

- Promoted `install.sh` as the single canonical production entrypoint.
- Kept `install-v2.sh` as the tested production setup implementation.
- Kept DEV installers isolated from production deployment.
- Independent PHP Admin Panel with persisted dynamic port.
- Existing RouteBox / Apache / Nginx listeners on 80/443 are left untouched.
- Admin Panel updater, repair flow and optional RouteBox certificate reuse remain available.
- Worker single-instance locking and systemd restart behavior remain enabled.
- QR-code support is part of the standard installation prerequisites.

### 🧩 Modular services

- Added provider-independent service catalog and dispatcher flow.
- Added service categories with RouteBox and IBSng providers.
- Added dynamic Telegram service-category / plan menus.
- Added persistent `service_subscriptions` records.
- Preserved the legacy RouteBox provisioning path while exposing it through the modular layer.

### 🔵 IBSng

- Added IBSng A1.24 Web Panel integration.
- Added login/session handling with temporary cookie storage.
- Added server management and connection testing in the Admin Panel.
- Added manual Plan → Group mapping.
- Added real IBSng user creation.
- Added Internet Username / Password assignment.
- Added Telegram provisioning for IBSng services.
- Added dynamic IBSng plans generated from configured groups.
- Added adapter methods for username lookup, renewal and group change.
- Fixed Worker loading so IBSng remains available inside the long-running Telegram process.

### 💳 Payment foundation

- Added `PaymentGatewayInterface`.
- Added `PaymentResult`.
- Added `OrderService`.
- Added order, payment-provider and coupon database tables.
- Added coupon redemption schema.
- Kept payment gateways disabled until a concrete adapter and callback/verification flow are implemented.
- Designed the payment layer as a separate integration so future gateways do not require rewriting RouteBox or IBSng provisioning.

### 🔐 Security / reliability

- Encrypted provider credentials.
- Encrypted Telegram Bot Token storage.
- CSRF-protected Admin actions.
- Admin sessions use HTTP-only / SameSite cookies.
- Restricted web updater through a dedicated root wrapper.
- Added Admin Panel config/permission repair workflow.
- RouteBox API smoke testing includes create → config → delete verification.
- Duplicate Telegram polling is prevented with a worker lock.

## 🧭 Upcoming

### Telegram Bot Admin
- [ ] Independent admin authentication / authorization.
- [ ] Multiple Telegram admin IDs.
- [ ] Admin management from the Web Panel.
- [ ] Dedicated Bot Admin menu.
- [ ] Admin-only operational actions.

### Admin Skip Payment
- [ ] Authorized Bot Admin can create a service without customer payment.
- [ ] Normal customer payment flow remains unchanged.
- [ ] Admin-created provisioning is recorded for auditability.

### IBSng Management
- [ ] Username search from Bot Admin.
- [ ] Rich IBSng user information view.
- [ ] Persian expiry-date display.
- [ ] Traffic/usage reporting where available.
- [ ] Direct IBSng user creation from Bot Admin.
- [ ] Server + Plan/Group selection.
- [ ] Configurable username prefix.
- [ ] Renewal limited to configured IBSng plans.
- [ ] User edit support where the provider allows it.
- [ ] Move IBSng smoke/diagnostic tools into the Admin Panel.

### Worker / Operations
- [ ] Worker status page.
- [ ] Restart Worker from the Admin Panel.
- [ ] Worker health monitoring.
- [ ] Recent operational log viewer.
- [ ] Audit trail for admin actions.

### Payment
- [ ] Complete order state machine.
- [ ] Gateway callback endpoints.
- [ ] Payment verification.
- [ ] ZarinPal adapter.
- [ ] Crypto gateway adapter.
- [ ] Verified-payment-only provisioning.
- [ ] Coupon administration and usage enforcement.
- [ ] Invoice and payment history.

## 📝 Release policy

- Production installs use `install.sh`.
- `install-v2.sh` is the current production implementation used by the main entrypoint.
- `install-dev.sh` / `install-dev-full.sh` are development tools only.
- Existing tested IBSng provisioning must be extended, not rewritten.
- Existing RouteBox provisioning must remain backward-compatible.
- Payment integration must remain provider-independent.
