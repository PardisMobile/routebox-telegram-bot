# 🚀 RouteBox Telegram Bot

Telegram Bot + independent Web Admin Panel for **RouteBox / AmneziaWG**, with a modular service architecture for multiple service providers such as **IBSng**.

**Current version:** `0.1.0-beta.11.05` · **Status:** 🧪 Beta  
**Production installer:** `install.sh`  
**Platform:** Ubuntu 22.04+

> The `VERSION` file is the source of truth for the installed application version.

## ✨ Current capabilities

### Telegram Bot

- 🇮🇷🇬🇧 Persian / English bot experience
- Provider-independent service-category menu
- RouteBox / AmneziaWG provisioning
- Multiple RouteBox servers
- Free trial with trial-reuse protection
- Configurable welcome messages and Telegram buttons
- My Services management
- `.conf` configuration delivery
- QR-code delivery for AmneziaWG
- Guide links for Android, iPhone/iPad, Windows and macOS
- Basic commands: `/start`, `/menu`, `/account`, `/help`

### RouteBox integration

The Bot uses the current RouteBox API integration for authentication, health/status, AWG status, peers, expiry, configuration export, VPN link / Sing-box export and traffic reset.

The production installer also performs a real temporary RouteBox/AWG smoke test before accepting a configured server.

### Admin Panel

- 🖥️ Independent PHP Admin Panel
- 🌙 Light / Dark mode
- 🇮🇷🇬🇧 Persian / English interface
- RouteBox server management and connection tests
- Plan management
- Trial and bot settings
- Password change and CSRF protection
- Version check against GitHub
- In-panel update workflow
- Recovery / repair tooling
- Optional HTTP + HTTPS on the same panel port by reusing the existing RouteBox panel certificate
- Does not require taking over existing Apache/Nginx ports 80/443

### 🎨 ATD Panel UI baseline

The `ATD-Panel` branch contains the current Admin Panel visual-normalization work for RouteBox, IBSng and MikroTik.

- Consistent provider/server status cards with connection state, country flag, IP and ping where available
- Four Users summary cards: RouteBox Users, IBSng Users, MikroTik Users and Telegram Bot Users
- Consistent server action-button styling while preserving provider capabilities
- Consistent plan-card actions: `Edit Plan` / `Disable` / `Delete`
- Compact plan edit panels with an explicit Close action and Escape-to-close behavior
- IBSng Provider Plan Key is shown as the meaningful/manual group-name field; RouteBox and MikroTik do not require manual entry of provider-generated/internal keys
- Server lists are shown before Add Server when servers already exist
- MikroTik peer lists are bounded to 50 visible peers per page with pagination
- Cross-browser country flags use image assets with country-code fallback instead of relying on emoji font rendering

**Provider capability contract:** IBSng supports Edit Server + Delete Server; MikroTik supports Edit Server but not Delete Server; RouteBox currently exposes neither as a provider capability. UI normalization must not invent backend actions.

> **Important:** ATD Panel UI work is intentionally isolated from the working provider implementations. RouteBox, IBSng and MikroTik provisioning/API logic, peer allocation and tested business functions are treated as protected unless a separate functional task explicitly requests a change.

## 🔵 IBSng integration — completed and tested

IBSng A1.24 is implemented as an additional modular service provider. The existing RouteBox/AWG provisioning path remains separate and is not replaced by IBSng.

Completed IBSng capabilities include:

- IBSng Web Panel / API connection
- IBSng authentication and session handling
- IBSng server configuration foundation
- IBSng group mapping
- IBSng group listing / synchronization support
- IBSng user lookup / user information operations
- IBSng test-user creation support
- End-to-end IBSng account provisioning from Telegram
- Automatic IBSng Internet Username + password assignment
- IBSng subscription persistence in `service_subscriptions`
- Provider / server / group-aware IBSng service provisioning
- Worker loading of the isolated `IBSngClient`
- One IBSng account for the configured access methods (OpenVPN / Cisco / L2TP), rather than creating separate accounts for each access method
- Real IBSng A1.24 provisioning flow tested successfully

### IBSng architecture

The repository uses `ServiceRouter`, `ServiceDispatcher`, `ServiceProviderInterface` and the IBSng provider/service classes rather than a separate `ServiceProvisioner` class. The effective flow is:

```text
Telegram Bot
      |
      v
ServiceRouter / ServiceDispatcher
      |
      +----------------------+
      |                      |
      v                      v
RouteBox provider       IBSngService
                             |
                             v
                        IBSngProvider
                             |
                             v
                        IBSngClient
                             |
                             v
                      IBSng A1.24 API
```

The IBSng module lives under `src/Integrations/IBSng/`. Provider-specific protocol code stays isolated from the existing RouteBox client and provisioning flow.

> **Important terminology:** IBSng `owner` / `owner_name` is an IBSng-specific concept. It is **not** the Telegram Bot Admin system. Telegram Bot Admin is a separate future permission layer.

## 💳 Payment foundation

The payment layer is architecturally present, but real payment gateways are not currently advertised as production-ready:

- `PaymentGatewayInterface`
- `PaymentResult`
- `OrderService`
- Orders and payment-provider schema
- Coupon and coupon-redemption schema
- Provider-independent service catalog and dispatcher

Future gateways can be added without rewriting the RouteBox or IBSng provisioning implementations.

## 📦 Installation

### Production Installation

`install.sh` is the **user-facing production installer**. It downloads and executes the internal `installer-core.sh`, then performs the existing production repair, restricted updater and optional RouteBox TLS integration steps.

One-command production installation:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh | bash
```

The production installer is intended for **Ubuntu 22.04+** and the current installer flow handles:

- required system dependencies and PHP 8+ validation
- SQLite/database initialization
- application configuration and first-install Admin credentials
- Telegram Bot token validation
- RouteBox server/API validation and real AWG smoke testing
- production systemd Worker service
- independent PHP Admin Panel and dynamic panel port
- Admin Panel health/repair integration
- restricted Admin Panel update tooling
- optional reuse of the existing RouteBox TLS certificate without taking over ports 80/443

### Development / IBSng Environment

The development installer is an isolated deployment for development/testing of the modular-services and IBSng work. It targets the `feature/modular-services-ibsng` branch and uses separate `routebox-telegram-bot-dev` application/state paths, leaving the production installation separate.

One-command development installation:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/feature/modular-services-ibsng/install-dev.sh | bash
```

`install-dev.sh` is the developer-facing entrypoint and delegates to `install-dev-full.sh`. The current development flow installs the development checkout, PHP/SQLite/Git/curl/OpenSSL/QR prerequisites, an isolated Worker and Admin Panel, performs syntax validation and web-panel health checks, and exposes the IBSng development/test tooling present on that branch.

### Final installer structure

There are exactly **four installer files** in the final layout:

| File | Role |
|---|---|
| `install.sh` | ⭐ **User-facing production installer / public entrypoint** |
| `installer-core.sh` | 🔧 **Internal production installer core**, called by `install.sh` |
| `install-dev.sh` | 🛠 **Developer entrypoint** for `feature/modular-services-ibsng` |
| `install-dev-full.sh` | 🧪 **Full development / IBSng developer setup** |

`install-v2.sh` has been retired and is no longer part of the repository. Do not use or reference it.

The production entrypoint remains `install.sh`; users should not need to call the internal core directly.

## 🛠️ Update and recovery tooling

Production updates can be started with:

```bash
sudo bash /opt/routebox-telegram-bot/update.sh
```

The Admin Panel also contains the update workflow. The repository includes backup/restore and Admin Panel repair tooling alongside the installer.

## 🔐 Security model

- Telegram Bot Tokens are entered silently during installation and stored encrypted.
- RouteBox and IBSng credentials are stored encrypted.
- Admin sessions use HTTP-only / SameSite cookies and CSRF protection.
- SQLite/database and configuration are kept outside the public web root.
- The Admin Panel runs as `www-data`.
- The web updater uses a restricted root wrapper rather than general sudo access.
- A worker lock prevents accidental duplicate Telegram polling workers.
- Production installation/update flows do not take over existing Apache/Nginx/RouteBox ports 80/443.

## 🗺️ Development roadmap

### ✅ Completed IBSng milestone

- [x] Modular IBSng provider integration
- [x] IBSng A1.24 connection/authentication/session handling
- [x] IBSng server configuration foundation
- [x] IBSng group mapping and listing
- [x] IBSng user lookup operations
- [x] IBSng test-user creation support
- [x] End-to-end Telegram IBSng account provisioning
- [x] Internet Username + password provisioning
- [x] `service_subscriptions` persistence
- [x] Provider/server/group-aware provisioning
- [x] Worker integration with the isolated IBSng client
- [x] Real IBSng A1.24 provisioning flow tested successfully

### 🎨 ATD Panel UI normalization — completed baseline

- [x] Unified RouteBox / IBSng / MikroTik server status-card presentation
- [x] Restored status, flag and ping information in the provider summary area
- [x] Added the four Users summary cards and placed them directly under the Users heading
- [x] Unified Connected / Running status-pill styling
- [x] Unified provider plan action-row styling
- [x] Added explicit plan-editor close behavior without changing plan update logic
- [x] Made Provider Plan Key manual/required only for IBSng where it represents the IBSng group name
- [x] Preserved RouteBox/MikroTik provider-generated/internal plan-key behavior
- [x] Moved IBSng and MikroTik server lists before Add Server when servers exist
- [x] Added bounded MikroTik peer pagination at 50 peers per page
- [x] Removed UI-injected RouteBox/MikroTik Delete Server actions that were not supported by the provider capability contract
- [x] Fixed country-flag rendering so Chrome/Edge do not depend on regional-indicator emoji fonts

### 🔜 Upcoming: Telegram Bot Admin

Telegram Bot Admin is a **new, independent permission layer**. It is not the same thing as the IBSng `owner` / `owner_name` concept.

- [ ] Independent Telegram Bot Admin authentication / authorization system
- [ ] Configure Telegram Bot Admin users from the Web/Admin Panel
- [ ] Support **multiple Telegram numeric administrator IDs**
- [ ] Dedicated Telegram Bot Admin menu
- [ ] Telegram service-management capabilities for authorized admins

### 💳 Upcoming: Admin payment bypass

- [ ] Authorized Telegram Bot Admin can bypass customer payment when creating services
- [ ] RouteBox service creation without customer payment
- [ ] IBSng service creation without customer payment
- [ ] Provider-neutral design so the bypass can support future service providers
- [ ] Keep the normal customer payment flow unchanged
- [ ] Record admin-created orders/provisioning actions for auditability

### 🔵 Upcoming: IBSng user management from Telegram Bot Admin

- [ ] Search IBSng users by username
- [ ] View IBSng username and account information
- [ ] Display account expiry date
- [ ] Display expiry date in Persian/Shamsi format
- [ ] Display traffic/quota usage when the IBSng service is quota-based
- [ ] Renew an IBSng user
- [ ] Edit IBSng user information where supported
- [ ] Additional safe account-management actions as appropriate
- [ ] Renewal must use the IBSng plans already configured in the RouteBox Admin Panel; no separate hard-coded renewal catalog

### ⚙️ Upcoming: IBSng administration tools

- [ ] Configurable generated IBSng username prefix from the Admin Panel
- [ ] Current generated prefix is `rb`; allow future configuration such as `tgbot`
- [ ] Move `test-ibsng-account.php` functionality into the IBSng Admin Panel as a safe UI
- [ ] Keep the standalone test script out of the normal user workflow

### 🔧 Upcoming: Worker operations

- [ ] Telegram Bot Worker status in the Admin Panel
- [ ] Restart/reload button in the Telegram Bot section
- [ ] Ensure the control targets the correct existing Worker systemd service
- [ ] Worker health monitoring
- [ ] Recent operational logs / diagnostics

### 📦 Completed: Installer refactor

- [x] `install.sh` remains the user-facing production entrypoint
- [x] `install-v2.sh` renamed to `installer-core.sh`
- [x] Production entrypoint updated to use `installer-core.sh`
- [x] Production installer UX improved with clear sections, status messages and final summary
- [x] Installer core UX improved without changing the production architecture
- [x] Development installers retained as `install-dev.sh` and `install-dev-full.sh`
- [x] Installer documentation updated to the final four-file structure

## 📚 Documentation

- [Installation Guide](./INSTALL.md)
- [Roadmap](./ROADMAP.md)
- [Changelog](./CHANGELOG.md)
- [Modular Architecture](./docs/MODULAR_ARCHITECTURE.md)
- [ATD Panel Working Notes](./docs/ATD_PANEL_WORKING_NOTES.md)
- [IBSng tools](./tools/)
- [Troubleshooting](./TROUBLESHOOTING.md)

## 👤 Creator

**RouteBox Telegram Bot — created and maintained by Amir Taheri.**
