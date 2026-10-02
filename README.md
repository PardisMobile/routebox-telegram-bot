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

## 🔵 IBSng — operational integration

Implemented and tested:

- IBSng A1.24 Web Panel integration
- Login and session management
- Server and Group mapping
- Real user creation
- Internet Username and Password assignment
- Subscription persistence in `service_subscriptions`
- Plan → Server → Group provisioning
- Telegram provisioning flow
- Worker integration
- End-to-end provisioning verification

The IBSng flow is isolated and existing RouteBox provisioning remains unchanged.

## 🗺️ Roadmap

### ✅ Completed

- [x] Modular Service Architecture
- [x] Provider based provisioning for RouteBox and IBSng
- [x] Real IBSng A1.24 integration
- [x] IBSng Worker operation
- [x] Real provisioning test
- [x] Subscription persistence
- [x] Plan/Server/Group mapping

### 🔜 Next Development Phase

#### Telegram Bot Admin

- [ ] Independent admin access system (separate from IBSng)
- [ ] Multiple Telegram ID support
- [ ] Admin management from Web Panel
- [ ] Dedicated Telegram Admin menu
- [ ] Full admin operations from bot

#### Admin Skip Payment

- [ ] Create service without payment for Telegram Bot Admin
- [ ] Keep normal user payment flow unchanged

#### IBSng Management

- [ ] Search user by Username
- [ ] Display user information
- [ ] Persian expiry date display
- [ ] Traffic usage display for volume services
- [ ] Create IBSng user from Admin Bot
- [ ] Select Server and Plan/Group
- [ ] Configurable username prefix (`rb`, `tgbot`, etc.)
- [ ] IBSng renewal based only on RouteBox defined plans
- [ ] Edit Username/Password/Group when supported
- [ ] Move IBSng test tools into Admin Panel

#### Worker Management

- [ ] Worker status in Admin Panel
- [ ] Restart Worker from Admin Panel
- [ ] Worker health monitoring

#### Payment

- [ ] Complete Order lifecycle
- [ ] Coupon management
- [ ] Payment verification flow
- [ ] ZarinPal adapter
- [ ] Provision only after verified payment

## 🔒 Rules

- `owner` and `owner_name` are IBSng-specific fields only.
- Telegram Bot Admin is an independent permission system.
- Current tested IBSng flow must not be rewritten.
- Normal user payment flow remains unchanged.
- New features extend existing provisioning instead of replacing it.

## 📜 Changelog

See [`CHANGELOG.md`](./CHANGELOG.md).

## 👤 Creator

**RouteBox Telegram Bot — created and maintained by Amir Taheri.**
