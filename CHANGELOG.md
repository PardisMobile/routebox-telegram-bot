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
- Completed the shared-worker design so new service providers do not require rewriting the Telegram worker.

### 🔵 IBSng A1.24 — operational

- Switched the integration to the IBSng A1.24 Apache Web Panel instead of relying on an external `:1237` JSON-RPC endpoint.
- Added Admin authentication, server configuration and connection testing.
- Added non-destructive Group discovery/synchronization.
- Added IBSng products/plans linked to real existing Groups.
- Any number of configured IBSng plans/groups can be exposed automatically through Telegram.
- Added existing-user lookup by username / Internet Username.
- Added real user creation in an existing IBSng Group.
- Added automatic Internet Username + password assignment.
- Added one subscription/account for L2TP + OpenVPN + Cisco access methods.
- Added IBSng subscription persistence and provider/server/group-aware provisioning.
- Connected the IBSng provisioning flow to Telegram.
- Completed worker loading of the isolated IBSng provider.
- Verified the real end-to-end IBSng provisioning flow successfully.
- No direct IBSng database access is used.
- No requirement to expose local Core XML-RPC or open JSON-RPC `1237`.

### 🤖 Telegram worker

- Completed the shared worker integration for RouteBox and IBSng.
- Added/retained single-instance protection so Telegram updates are not consumed by multiple workers.
- Provider-specific provisioning remains inside provider integrations; the worker stays provider-neutral.
- Verified the operational worker path with the modular service architecture.

### 🛠️ Admin / reliability

- Expanded Admin Tools, backup/guide/RouteBox management and recovery tooling.
- Hardened RouteBox client loading and prevented duplicate `RouteBoxClient` declarations.
- Improved updater self-refresh and canonical `update.sh` execution.
- Added/fixed Admin navigation and GitHub version visibility.
- Added Telegram usage guide and fixed escaped/newline rendering.
- Added `qrencode` to installer requirements for QR workflows.
- Added development installer isolation under `/opt/routebox-telegram-bot-dev`.
- Finalized RouteBox free-trial visibility and service visibility rules.

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

## 🗺️ Roadmap

### 🔜 Next

- [ ] IBSng remaining time/traffic display
- [ ] IBSng renewal / edit / delete / account management
- [ ] Separate IBSng free-trial controls
- [ ] Complete Order lifecycle
- [ ] Coupon creation, validation, expiry and usage limits
- [ ] Payment provider management and callback verification
- [ ] Provision only after verified payment
- [ ] ZarinPal adapter
- [ ] Payment/Order history

### 📣 After payment

- [ ] Telegram broadcast with queue/rate limiting
- [ ] Service-category management UI
- [ ] Expiration notifications
- [ ] Usage dashboard
- [ ] Server/region selection
- [ ] MikroTik provider
- [ ] Additional isolated service providers
- [ ] Optional Telegram WebApp
