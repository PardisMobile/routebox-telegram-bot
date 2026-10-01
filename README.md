# 🚀 RouteBox Telegram Bot

Telegram Bot + independent Web Admin Panel for RouteBox / AmneziaWG.

**Version:** [`VERSION`](./VERSION) · **Status:** 🧪 Beta

**Created and maintained by Amir Taheri.**

## ✨ Current RouteBox features

- Persian/English Telegram Bot and Admin Panel
- Multiple RouteBox servers
- AWG provisioning, expiration and traffic quotas
- `.conf` delivery
- Free trial and trial reuse protection
- Configurable plans, welcome text and Telegram buttons
- RouteBox API validation + real AWG create/export/delete smoke test
- Encrypted credentials
- Admin password recovery/change
- Software updater
- Light/Dark Admin Panel
- Same-port HTTP + HTTPS without taking over RouteBox ports `80/443`

## 🧩 Modular service architecture

New providers are **separate integrations**. Existing RouteBox/WireGuard code remains the current working service and is not replaced by IBSng.

```text
src/Integrations/
├── ServiceProviderInterface.php
├── IBSng/          ← OpenVPN / Cisco / L2TP
├── MikroTik/       ← future
└── Payment/        ← future gateways
```

Provider-specific API/provisioning code stays in its own directory. The Admin Panel is the configuration/UI layer.

### 🔵 IBSng — OpenVPN / Cisco / L2TP

IBSng is a separate service category alongside RouteBox/WireGuard.

**One IBSng account provides L2TP + OpenVPN + Cisco.** They are three access methods for one subscription, not three accounts.

The current development adapter targets **IBSng A1.24 Free Edition** through its existing Apache Web Panel. A1.24's Core XML-RPC listener is local-only (`127.0.0.1:1235`), so RouteBox does **not** require opening port `1235` or `1237` on the IBSng server and does not access the IBSng database.

Planned/implemented IBSng management:

- Multiple IBSng servers
- Server IP/host + Web Panel port (default `80`)
- IBSng Admin username/password with encrypted storage
- Connection test against `/IBSng/admin/`
- Read existing IBSng group names
- Manual Group Name mapping per RouteBox product (for example `یک ماهه` → `P1`)
- Create/read/manage users through the IBSng Admin Web Panel
- One account for OpenVPN / Cisco / L2TP
- Remaining time/traffic and renewal as the adapter expands

Telegram will keep the existing WireGuard category and add a separate OpenVPN / Cisco / L2TP category:

```text
🚀 سرویس موردنظر را انتخاب کنید

🟣 WireGuard
🔵 OpenVPN / Cisco / L2TP
🎁 دریافت تست رایگان
```

Free trials will have separate WireGuard and IBSng paths.

### 💳 Orders / Payment / Coupons

Payment is isolated so gateways can be added without rewriting RouteBox or IBSng provisioning.

```text
Product → Order → Coupon → Payment Gateway
                              ↓
                       Verified Payment
                              ↓
                    Service Provisioning
                       ├─ RouteBox
                       └─ IBSng
```

Provisioning happens only after successful payment verification. Future gateways live under `src/Integrations/Payment/`.

### 📣 Telegram administration

Planned separately:

- Broadcast/announcement messages from Admin Panel
- Queued/rate-limited delivery
- Service-category management
- Separate free-trial controls
- Editable labels/icons/order

Telegram inline keyboards do not support arbitrary button background colors. Labels, emoji/icons, order and categories will remain configurable. A Telegram WebApp can be added later for fully custom UI/colors.

## 📦 Installation

### Production / main

Ubuntu 22.04+:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The production installer installs required packages including **`qrencode`** for QR-code workflows, validates Telegram and RouteBox, performs the AWG smoke test, installs the Bot/Admin Panel and optionally applies RouteBox TLS.

Existing RouteBox/Apache/Nginx services and ports `80/443` are left alone.

### 🧪 Development / IBSng test branch

For testing the modular IBSng work **without installing over the production RouteBox installation**, use the `feature/modular-services-ibsng` branch installer:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/feature/modular-services-ibsng/install-dev.sh -o /tmp/install-dev.sh
sudo bash /tmp/install-dev.sh
```

Or, when already logged in as `root`:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/feature/modular-services-ibsng/install-dev.sh -o /tmp/install-dev.sh
bash /tmp/install-dev.sh
```

The development installer installs the same RouteBox panel prerequisites (including **PHP, required PHP extensions and `qrencode`**) and installs the development build separately under `/opt/routebox-telegram-bot-dev`. It must not replace the existing production installation under `/opt/routebox-telegram-bot`.

For the current IBSng A1.24 adapter, the default connection transport is the IBSng **HTTP Web Panel on port 80**. The smoke test authenticates using the IBSng Admin username/password, reads the existing Group list, and can read a user by username or create a test user. It does not require TCP/1237.

Read-only connection/group test:

```bash
cd /opt/routebox-telegram-bot-dev
php tools/ibsng-smoke-test.php YOUR_IBSNG_IP YOUR_ADMIN_USER YOUR_ADMIN_PASSWORD
```

Read an existing IBSng user:

```bash
php tools/ibsng-smoke-test.php YOUR_IBSNG_IP YOUR_ADMIN_USER YOUR_ADMIN_PASSWORD get USERNAME
```

Create a test user in an existing IBSng group:

```bash
php tools/ibsng-smoke-test.php YOUR_IBSNG_IP YOUR_ADMIN_USER YOUR_ADMIN_PASSWORD create P1 Main 0
```

Existing RouteBox/Apache/Nginx services and production ports must remain untouched by the development installer.

## 🗂️ Important paths

| Path | Purpose |
|---|---|
| `install.sh` | Public installer |
| `install-v2.sh` | Main installer implementation |
| `worker.php` | Telegram worker |
| `src/` | Core/application code |
| `src/Integrations/IBSng/` | Isolated IBSng provider |
| `src/Integrations/Payment/` | Payment abstraction/future gateways |
| `public/` | Admin Panel |
| `database/migrations/` | Additive schemas for new modules |
| `VERSION` | Version source of truth |
| `CHANGELOG.md` | Release history |

## 🗺️ Roadmap

### ✅ Completed

- [x] Telegram Bot + independent Admin Panel
- [x] RouteBox API/session authentication
- [x] Multi-RouteBox provisioning
- [x] AWG provisioning, expiration and traffic quotas
- [x] `.conf` delivery and trial protection
- [x] Persian/English Bot + Panel
- [x] Editable plans/welcome/buttons
- [x] Admin password recovery
- [x] Updater / repair / uninstall tooling
- [x] HTTPS/TLS integration without taking over `80/443`
- [x] Modular provider/payment architecture foundation
- [x] Isolated IBSng A1.24 Web Panel client foundation
- [x] IBSng Admin login/connection test
- [x] IBSng group listing through the Web Panel
- [x] IBSng user lookup by username
- [x] IBSng test-user creation through the Web Panel
- [x] Additive services/orders/payments/coupons schema foundation
- [x] `qrencode` in installer

### 🔵 IBSng

- [x] IBSng A1.24 Free Edition Web Panel transport
- [x] Admin connection test
- [x] Read existing groups
- [x] Read user by username
- [x] Create user in a specified existing group
- [ ] IBSng Admin Panel section
- [ ] Multiple IBSng server management
- [ ] Encrypted Admin credentials in the final settings UI
- [ ] Manual Group Name mapping per product
- [ ] OpenVPN / Cisco / L2TP Telegram category
- [ ] One account for all three access methods
- [ ] Assign username/password during provisioning
- [ ] Remaining time/traffic display
- [ ] Renewal/edit/delete/account management
- [ ] Separate IBSng free trial

### 💳 Payment / Orders / Coupons

- [ ] Complete Order lifecycle
- [ ] Coupon creation, validation, expiry and usage limits
- [ ] Payment provider management UI
- [ ] Iranian gateway adapter (e.g. ZarinPal)
- [ ] Additional gateway adapters
- [ ] Callback/payment verification
- [ ] Provision only after verified payment
- [ ] Payment/Order history

### 📣 Telegram

- [ ] Service-category management UI
- [ ] Broadcast messages
- [ ] Queued/rate-limited broadcast delivery
- [ ] Separate WireGuard/IBSng trial settings
- [ ] Optional Telegram WebApp for custom colors/UI

### 🔌 Future integrations

- [ ] MikroTik under `src/Integrations/MikroTik/`
- [ ] More providers as isolated modules
- [ ] Region/server selection
- [ ] Usage dashboard
- [ ] Expiration notifications
- [ ] Subscription links / QR workflow
- [ ] Advanced user management

## 🔐 Security

Credentials are encrypted; RouteBox session cookies stay in memory; SQLite/config are outside the public web root; Admin POST actions use CSRF protection; the updater uses restricted sudo; IBSng integration uses the IBSng Admin Web Panel/Core path rather than direct database access.

## 🧪 Beta

Always use [`VERSION`](./VERSION) as the authoritative version. Test against the exact RouteBox version installed on the target server before real users or paid sales.

## 📜 Changelog

See [`CHANGELOG.md`](./CHANGELOG.md).

## 👤 Creator

**RouteBox Telegram Bot — created and maintained by Amir Taheri.**

© 2026 Amir Taheri

## 📄 License

The final license is being determined. Review repository license terms before commercial use or redistribution.
