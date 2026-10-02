# Changelog

Important user-visible and operational changes for RouteBox Telegram Bot.

Version source of truth: [`VERSION`](./VERSION).

## Unreleased — Modular Services / IBSng

### 🧩 Modular architecture

- Added provider-isolation architecture with `ServiceProviderInterface`.
- Added isolated `src/Integrations/IBSng/` provider for OpenVPN / Cisco / L2TP.
- Added isolated `src/Integrations/Payment/` abstraction for future gateways.
- Added additive service categories, provider plans, subscriptions, orders, payment providers and coupons schema.
- Existing RouteBox/WireGuard functionality remains separate and operational.

### 🔵 IBSng A1.24

- Switched the development integration to the IBSng A1.24 Apache Web Panel instead of relying on an external `:1237` JSON-RPC endpoint.
- Added Admin authentication and connection testing through `/IBSng/admin/`.
- Added non-destructive Group discovery/synchronization.
- Added existing-user lookup by username / Internet Username.
- Added controlled user creation in an existing IBSng Group.
- Added automatic Internet Username + password assignment.
- Added one subscription/account for L2TP + OpenVPN + Cisco access methods.
- Added IBSng subscription persistence and provider/server/group-aware provisioning.
- Connected the IBSng provisioning flow to Telegram.
- Verified real end-to-end IBSng provisioning.
- Added CLI smoke tests for connection, lookup and test-user creation.
- No direct IBSng database access is used.
- No requirement to expose local Core XML-RPC or open JSON-RPC `1237`.

### 🛠️ Admin / reliability

- Expanded Admin Tools, backup/guide/RouteBox management and recovery tooling.
- Hardened RouteBox client loading and prevented duplicate `RouteBoxClient` declarations.
- Improved updater self-refresh and canonical `update.sh` execution.
- Added/fixed Admin navigation and GitHub version visibility.
- Added Telegram usage guide and fixed escaped/newline rendering.
- Added `qrencode` to installer requirements for QR workflows.
- Added development installer isolation under `/opt/routebox-telegram-bot-dev`.

## 0.1.0-beta.11.x

- Continued Admin Panel stabilization and updater/repair improvements.
- Introduced the modular-services development line and IBSng integration work.
- Added the service/order/payment/coupon architecture foundation.

## Earlier releases

- RouteBox Telegram Bot + independent Web Admin Panel.
- Multi-RouteBox server support and AWG provisioning.
- Expiration and traffic quota handling.
- `.conf` delivery and free-trial protection.
- Persian/English Bot and Admin Panel.
- Configurable plans, welcome text and Telegram buttons.
- RouteBox API validation and AWG create/export/delete smoke testing.
- Encrypted credentials and admin password recovery/change.
- Software updater, backup, restore and repair tooling.
- Light/Dark Admin Panel.
- HTTPS/TLS integration without taking over existing RouteBox ports `80/443`.
- Telegram worker single-instance protection.

## 🗺️ Roadmap

### Next

- [ ] IBSng Admin Panel: server/group/product management
- [ ] Product → IBSng Group mapping UI
- [ ] Remaining time/traffic display
- [ ] IBSng renewal/edit/delete/account management
- [ ] Separate IBSng free-trial controls
- [ ] Complete Order lifecycle
- [ ] Coupon management and usage limits
- [ ] Payment provider management and callback verification
- [ ] ZarinPal adapter
- [ ] Provision only after verified payment

### Later

- [ ] Telegram broadcast with queue/rate limiting
- [ ] Service-category management UI
- [ ] Server/region selection
- [ ] Expiration notifications and usage dashboard
- [ ] MikroTik provider
- [ ] Additional isolated service providers
- [ ] Optional Telegram WebApp
