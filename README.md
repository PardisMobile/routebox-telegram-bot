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
- Single-worker protection and operational Telegram worker

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

### 🔵 IBSng — operational integration

The current branch targets **IBSng A1.24 Free Edition** through its existing Apache Web Panel.

Implemented and tested:

- Admin authentication and connection test
- IBSng server configuration and group discovery/synchronization
- IBSng products/plans with real Group association and enabled state
- Any number of configured IBSng plans/groups exposed automatically to Telegram
- User lookup and real user creation
- Automatic Internet Username + password assignment
- One IBSng account for **L2TP + OpenVPN + Cisco** access methods
- Subscription persistence and provider/server/group-aware provisioning
- Telegram purchase/provisioning flow
- Operational worker loading of the isolated IBSng provider
- Real end-to-end IBSng provisioning verification
- Isolated provider code under `src/Integrations/IBSng/`

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

The payment abstraction is ready for future gateways, including an Iranian gateway such as ZarinPal. Full order/payment/coupon flow remains on the roadmap.

## 📣 Telegram administration

RouteBox and IBSng are separate service categories and the worker is shared; adding a new provider does not require rewriting the worker. Broadcast/rate-limited messaging and further category controls remain on the roadmap.

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

### ✅ Completed

- [x] RouteBox/WireGuard and IBSng as independent services
- [x] Modular provider architecture and shared worker
- [x] Operational IBSng A1.24 integration
- [x] IBSng Admin connection/server configuration
- [x] IBSng Group sync/discovery
- [x] IBSng product/plan → real Group association
- [x] Any number of IBSng plans/groups automatically available in Telegram
- [x] IBSng real user provisioning with automatic username/password
- [x] One IBSng account for L2TP / OpenVPN / Cisco
- [x] IBSng subscription persistence and service provisioning
- [x] End-to-end IBSng provisioning test
- [x] RouteBox free trial and service visibility
- [x] Telegram worker operational with single-instance protection
- [x] Additive Order/Coupon/Payment schema foundation

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
