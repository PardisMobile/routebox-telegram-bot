# 🚀 RouteBox Telegram Bot

Telegram Bot + independent Web Admin Panel for **RouteBox / AmneziaWG**, with a modular service architecture that can host multiple service providers such as **IBSng**.

**Current version:** `0.1.0-beta.11.05` · **Status:** 🧪 Beta  
**Production installer:** `install.sh`  
**Platform:** Ubuntu 22.04+

> The `VERSION` file is the source of truth for the installed application version.

## ✨ Current capabilities

### Telegram Bot

- 🇮🇷🇬🇧 Persian / English bot experience
- Service-category menu with provider-independent Telegram UI
- RouteBox / AmneziaWG provisioning
- Multiple RouteBox servers
- Free trial with trial-reuse protection
- Configurable welcome messages and Telegram buttons
- My Services management
- Config delivery as `.conf`
- QR-code delivery for AmneziaWG
- Guide links for Android, iPhone/iPad, Windows and macOS
- Basic commands: `/start`, `/menu`, `/account`, `/help`

### RouteBox integration

The Bot talks to the same RouteBox API surface used by the current RouteBox client, including authentication, health/status, AWG status, peers, expiry, config export, VPN link / Sing-box export and traffic reset.

A full installation smoke test creates a temporary `rbt-install-test-*` peer, requests its configuration and removes it again before the server is accepted.

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

### 🔵 IBSng integration — operational

IBSng A1.24 support is now part of the modular service layer:

- IBSng Web Panel login and session handling
- Server connection management
- Manual Plan → IBSng Group mapping
- Real IBSng account creation
- Internet Username / Password assignment
- Subscription persistence in `service_subscriptions`
- Dynamic IBSng plans exposed through the Telegram service menu
- Telegram provisioning flow
- Provider-neutral worker dispatch
- Username lookup / user information adapter methods
- Renewal and group-change adapter methods
- Existing RouteBox provisioning remains isolated from IBSng behavior

### 💳 Payment foundation

The payment layer is **architecturally present but payment is not yet live**:

- `PaymentGatewayInterface`
- `PaymentResult`
- `OrderService`
- Orders and coupon schema
- Provider-independent service catalog and dispatcher
- Ready for gateway adapters without rewriting RouteBox/IBSng provisioning

No real ZarinPal or crypto payment gateway is advertised as production-ready yet.

## 🧩 Modular architecture

Provider-specific behavior is isolated:

```text
src/Integrations/
├── ServiceProviderInterface.php
├── ServiceRouter.php
├── ServiceDispatcher.php
├── ServiceCatalog.php
├── TelegramServiceMenu.php
├── IBSng/
│   ├── IBSngClient.php
│   ├── IBSngProvider.php
│   ├── IBSngService.php
│   ├── IBSngAdmin.php
│   ├── IBSngSchema.php
│   └── IBSngSection.php
└── Payment/
    ├── PaymentGatewayInterface.php
    ├── PaymentResult.php
    └── OrderService.php
```

The core rule is simple: **new providers extend the system; they do not replace the existing RouteBox provisioning path.**

## 📦 Installation

Production:

```bash
sudo -i
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer is designed for Ubuntu 22.04+ and performs package setup, Telegram validation, RouteBox API/AWG smoke testing, worker installation, Admin Panel setup and post-install validation.

See the full guide: [INSTALL.md](./INSTALL.md)

## 🛠️ Production vs development installers

There are currently **four installer-related files by design**:

| File | Purpose |
|---|---|
| `install.sh` | ✅ Canonical production entrypoint |
| `install-v2.sh` | Production implementation invoked by `install.sh` |
| `install-dev.sh` | Development entrypoint for the isolated feature branch |
| `install-dev-full.sh` | Full development-branch installer implementation |

For normal deployments, use **only `install.sh`**.

The DEV installers use the separate `routebox-telegram-bot-dev` application/state paths and are not the production install path.

## 🔐 Security model

- Telegram Bot Tokens are entered silently and stored encrypted.
- RouteBox and IBSng credentials are stored encrypted.
- Admin sessions use HTTP-only cookies and CSRF protection.
- SQLite/database and configuration are kept outside the public web root.
- The Admin Panel runs as `www-data`.
- The web updater uses a restricted root wrapper rather than general sudo access.
- A worker lock prevents accidental duplicate Telegram polling workers.
- Production installer/update flows do not reconfigure existing Apache/Nginx/RouteBox listeners on ports 80/443.

## 🗺️ Development roadmap

### ✅ Completed foundation

- [x] Modular service architecture
- [x] RouteBox + IBSng provider routing
- [x] Real IBSng A1.24 integration
- [x] IBSng plan/group mapping
- [x] IBSng provisioning and credentials
- [x] Provider-neutral Worker integration
- [x] Dynamic service-category / plan menu
- [x] Service subscription persistence
- [x] Payment/order abstraction layer
- [x] Coupon database foundation
- [x] Admin update + repair tooling
- [x] Independent Admin Panel
- [x] RouteBox TLS certificate reuse path

### 🔜 Next planned updates

#### Telegram Bot Admin

- [ ] Independent Bot Admin authentication / authorization
- [ ] Multiple Telegram admin IDs
- [ ] Admin management from Web Panel
- [ ] Dedicated Bot Admin menu
- [ ] Admin-only operational actions

#### Admin Skip Payment

- [ ] Let authorized Bot Admin create a service without customer payment
- [ ] Keep the normal customer payment path unchanged
- [ ] Record admin-created orders/provisioning actions for auditability

#### IBSng Management

- [ ] Search IBSng users by username from Bot Admin
- [ ] Rich user information view
- [ ] Persian calendar expiry display
- [ ] Traffic/usage reporting where IBSng exposes it
- [ ] Create IBSng users directly from Bot Admin
- [ ] Server + RouteBox-defined Plan/Group selection
- [ ] Configurable username prefix such as `rb` / `tgbot`
- [ ] Renewal limited to configured RouteBox IBSng plans
- [ ] Edit username/password/group where supported
- [ ] Move IBSng diagnostic tools into the Admin Panel

#### Worker & Operations

- [ ] Worker status in Admin Panel
- [ ] Restart Worker from Admin Panel
- [ ] Worker health monitoring
- [ ] Recent log viewer / diagnostics
- [ ] Safer operational audit trail

#### Payment

- [ ] Complete order lifecycle
- [ ] Payment callback endpoints
- [ ] Payment verification state machine
- [ ] ZarinPal adapter
- [ ] Crypto gateway adapter
- [ ] Provision only after verified payment
- [ ] Coupon usage enforcement and admin management
- [ ] Invoice / payment history

## 🔄 Update

From an installed production copy:

```bash
sudo bash /opt/routebox-telegram-bot/update.sh
```

Or use the Admin Panel's update page.

The updater creates a pre-update backup, pulls `main`, refreshes service files, runs syntax checks and restarts the Bot/Panel services.

## 🧯 Troubleshooting

### Admin Panel is on a different port than expected

```bash
cat /etc/routebox-telegram-bot/web-port
```

### Check Worker

```bash
systemctl status routebox-telegram-bot
journalctl -u routebox-telegram-bot -f
```

### Check Admin Panel

```bash
PORT=$(cat /etc/routebox-telegram-bot/web-port)
systemctl status routebox-telegram-bot-web@$PORT
journalctl -u routebox-telegram-bot-web@$PORT -f
```

### Repair Admin Panel

```bash
sudo bash /opt/routebox-telegram-bot/repair-web.sh
```

### Uninstall

```bash
sudo bash /opt/routebox-telegram-bot/uninstall.sh
```

## 📚 Documentation

- [Installation Guide](./INSTALL.md)
- [Roadmap](./ROADMAP.md)
- [Changelog](./CHANGELOG.md)
- [Modular Architecture](./docs/MODULAR_ARCHITECTURE.md)
- [IBSng tools](./tools/)
- [Troubleshooting](./TROUBLESHOOTING.md)

## 👤 Creator

**RouteBox Telegram Bot — created and maintained by Amir Taheri.**
