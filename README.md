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

Planned IBSng management:

- Multiple IBSng servers
- Server IP/host + API port (default `1237`)
- IBSng Admin username/password with encrypted storage
- Connection test
- Real IBSng group synchronization
- Group → service-plan mapping
- Create/edit/renew/manage users
- Remaining time/traffic

Telegram will keep the existing WireGuard category and add:

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

Ubuntu 22.04+:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer installs required packages including **`qrencode`** for QR-code workflows, validates Telegram and RouteBox, performs the AWG smoke test, installs the Bot/Admin Panel and optionally applies RouteBox TLS.

Existing RouteBox/Apache/Nginx services and ports `80/443` are left alone.

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
- [x] Isolated IBSng API client foundation
- [x] Additive services/orders/payments/coupons schema foundation
- [x] `qrencode` in installer

### 🔵 IBSng

- [ ] IBSng Admin Panel section
- [ ] Multiple IBSng server management
- [ ] Encrypted Admin credentials
- [ ] Connection test
- [ ] Group synchronization
- [ ] Group → plan mapping
- [ ] OpenVPN / Cisco / L2TP Telegram category
- [ ] One account for all three access methods
- [ ] Create account after successful purchase
- [ ] Username/password delivery
- [ ] Remaining time/traffic
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

Credentials are encrypted; RouteBox session cookies stay in memory; SQLite/config are outside the public web root; Admin POST actions use CSRF protection; the updater uses restricted sudo; IBSng uses its Admin API rather than direct database access.

## 🧪 Beta

Always use [`VERSION`](./VERSION) as the authoritative version. Test against the exact RouteBox version installed on the target server before real users or paid sales.

## 📜 Changelog

See [`CHANGELOG.md`](./CHANGELOG.md).

## 👤 Creator

**RouteBox Telegram Bot — created and maintained by Amir Taheri.**

© 2026 Amir Taheri

## 📄 License

The final license is being determined. Review repository license terms before commercial use or redistribution.
