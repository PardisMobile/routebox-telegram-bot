# RouteBox Telegram Bot Roadmap

This roadmap reflects the current development state of the modular RouteBox Telegram Bot project and the work completed through the MikroTik WireGuard milestone.

## ✅ Completed — Core modular service foundation

- [x] Modular service architecture
- [x] Provider-based provisioning
- [x] RouteBox, IBSng and MikroTik independent service providers
- [x] Shared provider-neutral `service_subscriptions` persistence
- [x] Service routing / dispatching through the shared provider layer
- [x] Existing RouteBox provisioning kept backward-compatible
- [x] Provider protocol logic kept isolated from the Telegram UI

## ✅ Completed — IBSng modular service milestone

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

## ✅ Completed — MikroTik WireGuard milestone

### RouterOS integration

- [x] MikroTik RouterOS REST client
- [x] MikroTik provider/service integration
- [x] RouterOS connection testing
- [x] RouterOS version discovery
- [x] RouterOS uptime discovery
- [x] CPU and memory discovery
- [x] REST latency discovery
- [x] WireGuard interface discovery
- [x] IP pool discovery
- [x] DNS discovery
- [x] Automatic WireGuard interface selection
- [x] Automatic WireGuard `listen-port` detection when VPN port is blank

### MikroTik Admin Panel

- [x] Add MikroTik server
- [x] Edit MikroTik server
- [x] Delete MikroTik server
- [x] Test MikroTik connection
- [x] Server card with RouterOS/WireGuard operational information
- [x] Server settings section styled consistently with the existing Admin Panel
- [x] Recent panel HTTP 500/rendering regressions fixed and verified

### WireGuard plans

- [x] Create MikroTik WireGuard plan
- [x] Edit MikroTik WireGuard plan
- [x] Delete MikroTik WireGuard plan
- [x] Subscription-safe plan deletion
- [x] Plan server selection
- [x] Persian/English plan names
- [x] Price and duration fields
- [x] Quota and upload/download metadata

### WireGuard peers

- [x] Create peer from Telegram service flow
- [x] Persist peer and subscription state
- [x] Disable peer
- [x] Enable peer
- [x] Delete peer
- [x] Delete peer from RouterOS
- [x] Synchronize RouteBox subscription state after peer deletion
- [x] Fix RouterOS compatibility issues affecting enable/disable/delete actions

### Telegram MikroTik service flow

- [x] MikroTik WireGuard service category
- [x] Plan selection
- [x] Server-aware provisioning
- [x] Username / VPN IP / server / expiry display
- [x] `.conf` delivery
- [x] QR delivery
- [x] Services shortcut after config delivery
- [x] Services shortcut after QR delivery
- [x] My Services list
- [x] Back-to-main-menu button from My Services
- [x] Worker restart/refresh verification after code updates

## 📦 Completed — Installer refactor and deployment documentation

- [x] `install.sh` remains the production user-facing entry point
- [x] `install-v2.sh` renamed to `installer-core.sh`
- [x] `install.sh` updated to download and execute `installer-core.sh`
- [x] `install-dev.sh` remains the development entry point for `feature/modular-services-ibsng`
- [x] `install-dev-full.sh` remains the full development / IBSng developer setup
- [x] Final repository structure contains exactly four installer files
- [x] Production installer UX improved with professional/colorful output, clear sections and final installation summary
- [x] Installer core UX improved without rewriting the production installation architecture
- [x] Existing production ports, systemd services, Worker, Web/Admin Panel and RouteBox installation behavior preserved
- [x] README and CHANGELOG updated to the final installer structure

## 🔜 Phase 1 — Telegram Bot Admin

### Independent permission system

- [ ] Create an independent Telegram Bot Admin authentication / authorization layer
- [ ] Configure Telegram Bot Admin users from the Web/Admin Panel
- [ ] Support **multiple Telegram numeric IDs**; do not limit the system to one administrator ID
- [ ] Provide a dedicated/admin Telegram menu for authorized Bot Admin users
- [ ] Keep Telegram Bot Admin permissions completely separate from IBSng `owner` / `owner_name`

### Admin service management

- [ ] Allow authorized Telegram Bot Admin users to manage services through Telegram
- [ ] Allow Bot Admin to create a new RouteBox service without customer payment
- [ ] Allow Bot Admin to create a new IBSng service without customer payment
- [ ] Allow Bot Admin to create a new MikroTik service without customer payment
- [ ] Design the payment bypass as provider-neutral so future providers can use the same authorization path
- [ ] Keep the normal customer payment flow unchanged
- [ ] Record admin-created orders/provisioning actions for auditability

## 🔵 Phase 2 — IBSng user management through Bot Admin

### Search and information

- [ ] Search IBSng users by username
- [ ] Display IBSng username information
- [ ] Display account information
- [ ] Display account expiry date
- [ ] Display expiry date in Persian/Shamsi format
- [ ] Display traffic/quota usage when the IBSng service is quota-based

### Renewal and editing

- [ ] Renew an IBSng user
- [ ] Renewal must use the IBSng plans already configured in the RouteBox Admin Panel
- [ ] Do **not** introduce a separate hard-coded renewal-plan catalog
- [ ] Edit IBSng user information where supported by the existing provider capabilities
- [ ] Add future safe account-management actions as appropriate

## ⚙️ Phase 3 — IBSng administration tools

### Configurable username prefix

- [ ] Make the generated IBSng username prefix configurable from the Admin Panel
- [ ] Keep the current generated prefix as `rb` by default
- [ ] Allow an administrator to change it to values such as `tgbot`
- [ ] Scope this setting specifically to generated IBSng usernames

### IBSng test-user UI

- [ ] Add IBSng test-user creation to the IBSng section of the Admin Panel
- [ ] Move the capability currently exposed by `test-ibsng-account.php` into a safe Admin Panel UI
- [ ] Keep the existing working IBSng test/provisioning code intact while adding the UI orchestration layer

## 🔧 Phase 4 — Telegram Worker operations

- [ ] Add Worker status to the Telegram Bot section of the Admin Panel
- [ ] Add a restart/reload button for the Telegram Bot Worker
- [ ] Ensure the control targets the correct existing Worker systemd service
- [ ] Do not change or break the current working Worker behavior
- [ ] Add Worker health monitoring
- [ ] Add recent Worker logs / diagnostics

## 💳 Phase 5 — Production payment system

### Payment architecture

- [x] Payment abstraction foundation (`PaymentGatewayInterface`, `PaymentResult`, `OrderService`)
- [x] Order/payment-provider schema foundation
- [x] Coupon/coupon-redemption schema foundation
- [x] Provider-independent service catalog and dispatcher

### Remaining payment work

- [ ] Complete order lifecycle
- [ ] Payment callback endpoints
- [ ] Payment verification state machine
- [ ] ZarinPal adapter
- [ ] Crypto gateway adapter
- [ ] Verified-payment-only customer provisioning
- [ ] Coupon management and enforcement
- [ ] Invoice and payment history

## 🧪 Quality / hardening after the current milestones

- [ ] Expand automated integration tests for MikroTik RouterOS operations
- [ ] Add regression coverage for MikroTik peer lifecycle actions
- [ ] Add regression coverage for plan deletion with and without subscription history
- [ ] Add Admin Panel rendering/smoke checks for the MikroTik section
- [ ] Add Telegram flow regression checks for config, QR, Services and Back buttons
- [ ] Document production MikroTik deployment and RouterOS permission requirements

## Rules for future development

1. Existing IBSng end-to-end provisioning is working and must not be rewritten as part of Telegram Bot Admin work.
2. Existing RouteBox provisioning must remain backward-compatible.
3. Existing MikroTik provisioning must be extended through the provider/service layer rather than rewritten inside Telegram UI code.
4. Telegram Bot Admin is an independent permission system and must not be conflated with IBSng `owner` / `owner_name`.
5. Telegram Bot Admin must support multiple numeric Telegram IDs.
6. Payment bypass must be provider-neutral and must not alter the normal customer payment path.
7. IBSng renewal must consume the plans configured in the RouteBox Admin Panel.
8. New Admin UI features should orchestrate existing provider modules rather than moving provider protocol logic into the UI.
9. Do not replace working provider implementations with parallel hard-coded implementations.
10. Future development for Telegram Bot Admin should start from the current modular architecture and preserve the tested provider flows.
