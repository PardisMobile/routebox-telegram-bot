# 🚀 RouteBox Telegram Bot

Telegram Bot + independent Web Admin Panel for **RouteBox / AmneziaWG**, with a modular service architecture for additional providers such as **IBSng**.

**Version:** [`VERSION`](./VERSION) · **Status:** 🧪 Beta

## ✨ Implemented

- 🇮🇷/🇬🇧 Persian/English Telegram Bot and Admin Panel
- Multiple RouteBox servers, AWG provisioning, expiration and traffic quotas
- `.conf` delivery, QR workflows, free trial and trial-reuse protection
- Configurable plans, welcome text and Telegram buttons
- RouteBox API validation and AWG smoke testing
- Encrypted credentials, admin password recovery and updater/repair tooling
- Light/Dark Admin Panel and HTTPS/TLS support without taking over existing RouteBox `80/443`
- Single-worker protection for Telegram updates

## 🧩 Modular services

Service providers are isolated from the RouteBox core:

```text
src/Integrations/
├── ServiceProviderInterface.php
├── IBSng/       ← OpenVPN / Cisco / L2TP
├── Payment/     ← payment abstraction
└── MikroTik/    ← future provider
```

Additive service/order/subscription/payment/coupon schemas are in place so new providers and gateways can be added without rewriting the existing RouteBox implementation.

### 🔵 IBSng — current integration

The current branch targets **IBSng A1.24 Free Edition** through its existing Apache Web Panel.

Implemented:

- Admin authentication and connection test through `/IBSng/admin/`
- Group discovery/synchronization
- User lookup by username / Internet Username
- User creation in an existing IBSng Group
- Automatic Internet Username + password assignment
- One IBSng account for **L2TP + OpenVPN + Cisco** access methods
- IBSng subscription persistence and provider/server/group-aware provisioning
- Telegram purchase/provisioning flow
- Isolated provider code under `src/Integrations/IBSng/`
- CLI smoke tests for connection, user lookup and test-user creation
- Real end-to-end IBSng provisioning verification

The integration does **not** access the IBSng database and does not require exposing the local Core XML-RPC listener or opening JSON-RPC `1237`.

## 💳 Orders & payments

Payment is intentionally isolated from provisioning:

```text
Product → Order → Coupon → Payment Gateway
                              ↓
                       Verified Payment
                              ↓
                    Service Provisioning
                    ├── RouteBox
                    └── IBSng
```

The payment abstraction is ready for future gateways, including an Iranian gateway such as ZarinPal. Full order/payment/coupon UI and verified-payment provisioning remain on the roadmap.

## 📣 Telegram administration

The architecture supports separate RouteBox/IBSng categories, independent trials, configurable labels/icons/order and future broadcast/rate-limited messaging. A Telegram WebApp can later provide custom UI/colors where native inline keyboards cannot.

## 📦 Installation

### Production

Ubuntu 22.04+:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

### Development / IBSng branch

To test this branch without replacing production:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/feature/modular-services-ibsng/install-dev.sh -o /tmp/install-dev.sh
sudo bash /tmp/install-dev.sh
```

The development build is installed separately under `/opt/routebox-telegram-bot-dev`.

Smoke test:

```bash
cd /opt/routebox-telegram-bot-dev
php tools/ibsng-smoke-test.php YOUR_IBSNG_IP YOUR_ADMIN_USER YOUR_ADMIN_PASSWORD
```

Read a user:

```bash
php tools/ibsng-smoke-test.php YOUR_IBSNG_IP YOUR_ADMIN_USER YOUR_ADMIN_PASSWORD get USERNAME
```

Create a controlled test user:

```bash
php tools/ibsng-smoke-test.php YOUR_IBSNG_IP YOUR_ADMIN_USER YOUR_ADMIN_PASSWORD create P1 Main 0
```

## 🗂️ Important paths

| Path | Purpose |
|---|---|
| `src/` | Core/application code |
| `src/Integrations/IBSng/` | IBSng provider |
| `src/Integrations/Payment/` | Payment abstraction |
| `public/` | Admin Panel |
| `worker.php` | Telegram worker |
| `database/migrations/` | Additive module schemas |
| `tools/` | CLI diagnostics/smoke tests |
| `VERSION` | Version source of truth |
| `CHANGELOG.md` | Release history |

## 🗺️ Roadmap

### Next

- [ ] IBSng Admin Panel: server/group/product management UI
- [ ] Manual product → IBSng Group mapping UI
- [ ] Remaining time/traffic display
- [ ] IBSng renewal/edit/delete/account management
- [ ] Separate IBSng free-trial controls
- [ ] Complete Order lifecycle
- [ ] Coupon creation/validation/usage limits
- [ ] Payment provider management + callback verification
- [ ] ZarinPal adapter
- [ ] Provision only after verified payment

### Later

- [ ] Telegram broadcast with queue/rate limiting
- [ ] Service-category management UI
- [ ] Server/region selection
- [ ] Expiration notifications
- [ ] Usage dashboard
- [ ] MikroTik provider
- [ ] More isolated service providers
- [ ] Optional Telegram WebApp

## 🔐 Security

Credentials are encrypted, SQLite/config data is outside the public web root, Admin POST actions use CSRF protection, and IBSng is integrated through its Web Panel rather than direct database access.

## 🧪 Beta

Use [`VERSION`](./VERSION) as the authoritative version. Test the exact installed RouteBox version and target IBSng environment before production use or paid sales.

## 📜 Changelog

See [`CHANGELOG.md`](./CHANGELOG.md).

## 👤 Creator

**RouteBox Telegram Bot — created and maintained by Amir Taheri.**

© 2026 Amir Taheri

## 📄 License

The final license is being determined. Review repository license terms before commercial use or redistribution.
