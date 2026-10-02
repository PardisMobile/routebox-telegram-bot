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

The production entry point is `install.sh`. It delegates to the tested `install-v2.sh` setup implementation and then performs the additional production repair/update/TLS integration steps already defined by the installer.

One-command installation:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh | bash
```

The production installer is intended for **Ubuntu 22.04+** and, based on the current installer implementation, handles the production installation requirements including:

- required system packages and PHP 8+ validation
- SQLite/database initialization
- application configuration and first-install Admin credentials
- Telegram Bot token validation
- RouteBox server/API validation and real AWG smoke testing
- production systemd Worker service
- independent PHP Admin Panel and dynamic panel port
- Admin Panel health/repair integration
- restricted Admin Panel update tooling
- optional reuse of the existing RouteBox TLS certificate without taking over ports 80/443

For normal production deployments, use `install.sh`. Do not run `install-v2.sh` directly unless you specifically need the underlying setup implementation.

### Development / IBSng Environment

The development installer is a separate, isolated deployment intended for development/testing of the modular-services and IBSng work. It targets the `feature/modular-services-ibsng` branch and uses the separate application/state paths `routebox-telegram-bot-dev`, so it does not replace the normal production installation.

One-command development installation:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/feature/modular-services-ibsng/install-dev.sh | bash
```

The development entry point delegates to `install-dev-full.sh`. The current development installer:

- requires Ubuntu 22.04+
- installs the same core PHP/SQLite/Git/curl/OpenSSL/QR prerequisites
- checks PHP 8+
- checks out `feature/modular-services-ibsng`
- uses `/opt/routebox-telegram-bot-dev`
- creates an isolated DEV Admin Panel and systemd Worker
- runs PHP syntax validation across the development checkout
- validates the web panel health
- keeps production `/opt/routebox-telegram-bot` untouched
- exposes the IBSng development smoke-test tooling included in that branch

The development installer should be used for testing the modular IBSng environment, not for a normal production deployment.

### Installer structure

There are currently four installer-related files. They are intentionally not interchangeable:

| File | Role |
|---|---|
| `install.sh` | **User-facing production entry point**. Downloads and executes the current production setup wizard, then runs production repair/update/TLS integration steps. |
| `install-v2.sh` | Production setup implementation used by `install.sh`. Installs prerequisites, initializes the application, validates Telegram/RouteBox, installs systemd services and the Admin Panel, and performs health checks. |
| `install-dev.sh` | **User-facing development entry point** for `feature/modular-services-ibsng`; delegates to `install-dev-full.sh`. |
| `install-dev-full.sh` | Full isolated DEV/IBSng installer implementation for `routebox-telegram-bot-dev`. |

Do **not** delete, rename or merge these installer files without first checking their deployment roles.

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

## 📚 Documentation

- [Installation Guide](./INSTALL.md)
- [Roadmap](./ROADMAP.md)
- [Changelog](./CHANGELOG.md)
- [Modular Architecture](./docs/MODULAR_ARCHITECTURE.md)
- [IBSng tools](./tools/)
- [Troubleshooting](./TROUBLESHOOTING.md)

## 👤 Creator

**RouteBox Telegram Bot — created and maintained by Amir Taheri.**
