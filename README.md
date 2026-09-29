# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Admin Panel for RouteBox / AmneziaWG
>
> **Version: `0.1.0-beta.1` · Status: 🧪 Beta**

![Status](https://img.shields.io/badge/status-BETA-orange?style=for-the-badge)
![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04%2B-E95420?style=for-the-badge&logo=ubuntu&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-Bot-26A5EA?style=for-the-badge&logo=telegram&logoColor=white)
![RouteBox](https://img.shields.io/badge/RouteBox-API-111827?style=for-the-badge)

---

## ✨ What is it?

RouteBox Telegram Bot is an independent backend for provisioning and managing **AmneziaWG peers** through one or more RouteBox servers.

It does not modify RouteBox files, databases, `peers.toml`, or WireGuard configuration directly. RouteBox operations go through the documented HTTP API.

```text
📱 Telegram
     ↓
🤖 Bot Worker
     ↓
🖥️ Admin Panel / Backend
     ↓
🌐 RouteBox API
     ↓
🔐 AmneziaWG Peer
     ↓
📄 .conf
     ↓
📲 Telegram
```

This is a **Beta** release focused on validating the Telegram → Backend → RouteBox → AmneziaWG workflow before commercial payment features are added.

## 🤖 Current Features

- 🎁 Configurable free trial
- 👤 Telegram user identity and service status
- 🔑 Automatic AmneziaWG peer creation
- 🌍 Multi-RouteBox provisioning
- 🆔 Same logical peer name across enabled servers (`user<telegram_id>`)
- ⏱️ Expiration management
- 📄 Automatic `.conf` delivery
- 🛡️ Trial reuse protection
- 🔄 Rollback of peers created during a failed multi-server provisioning attempt
- 📝 Application and provisioning logs

## 🖥️ Independent Admin Panel

The Bot is configured from its own web panel; administration does not need to be performed inside Telegram.

Current settings:

- 🤖 Telegram Bot Token
- 🎁 Trial duration
- 🌐 RouteBox server list
- 🟢 Enable / disable servers
- 🧪 Full RouteBox integration test
- 👥 Recent Telegram users
- 📝 Application status / logs

### 🔬 RouteBox validation

Adding a RouteBox server does not simply save a URL. The server is saved only after these checks pass:

```text
GET  /api/health
GET  /api/status
GET  /api/awg/status
GET  /api/awg/peers
GET  /api/settings

POST   /api/awg/peers
GET    /api/awg/peers/{publicKey}/config
DELETE /api/awg/peers/{publicKey}
```

The Bot **attempts to delete** the temporary peer after the test. If RouteBox becomes unreachable during cleanup, the failure is reported rather than hidden.

## 🌍 Multi-RouteBox

```text
                  🤖 Bot
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
   RouteBox #1  RouteBox #2  RouteBox #3
       AWG          AWG          AWG
```

For Telegram ID `123456789`, the logical peer name is:

```text
user123456789
```

Each RouteBox generates its own cryptographic keypair/public key while the logical customer identity stays consistent.

If a later server fails during provisioning, the Bot attempts to delete peers created during that same operation.

## 🔌 RouteBox URL / Port

There is **no separate API port** for the Bot. It uses the same HTTP(S) listener as the RouteBox web panel.

Enter the exact Panel URL:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

The installer does **not** ask for VPS/Router mode, scheme, host and API port separately. It accepts one complete Panel URL.

Typical RouteBox deployments use `8443` for the VPS panel and `8080` for the router panel; a reverse proxy may expose the panel on `443`. These are deployment details, not Bot modes.

## 🔐 RouteBox Authentication

```text
POST /api/auth/login
        ↓
Session Cookie
        ↓
Protected API requests
        ↓
401 → re-login once → retry
        ↓
HTTP Basic fallback when session authentication is unavailable
```

The RouteBox session cookie is kept in process memory and is not stored in SQLite. Basic authentication remains available as a script compatibility fallback.

## 📦 Installation — Ubuntu 22.04+

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer handles dependencies, SQLite, encryption-key generation, admin password generation, Telegram validation, RouteBox validation, Nginx/PHP-FPM, systemd and final syntax/service checks.

### Interactive setup

For Telegram:

```text
Telegram Bot Token:
```

For each RouteBox:

```text
Server name
RouteBox Panel URL
RouteBox username (optional if auth is disabled)
RouteBox password (optional if auth is disabled)
Verify TLS certificate? (HTTPS only)
```

The installer calls Telegram `getMe`, and it validates the complete RouteBox API/AWG workflow before saving the server.

## 🧪 End-to-End Smoke Test

The installer does not treat an open port or a read-only API response as sufficient.

```text
1. Authenticate
2. Read RouteBox status
3. Read AWG status
4. Read peers
5. Create temporary peer
6. Fetch generated client .conf
7. Attempt to delete temporary peer
8. Save RouteBox only when validation succeeds
```

This catches connectivity, authentication, AWG availability, write permissions, key generation, config rendering and public-key URL handling before the first real customer request.

## 📄 Client Configuration

The Bot does not generate AWG private keys or `.conf` files itself. It requests the generated configuration from RouteBox:

```text
GET /api/awg/peers/{publicKey}/config
```

Configure a usable RouteBox AWG **Server address** or **Public host** so exported configurations contain a reachable endpoint.

The AWG UDP listen port is separate from the RouteBox HTTP/API listener; the Bot does not use the AWG UDP port for API calls.

## 🔐 Security

- 🔒 Telegram tokens and RouteBox credentials are encrypted with libsodium SecretBox.
- 🔑 The application encryption key is generated locally and is not committed.
- 🍪 RouteBox session cookies stay in memory.
- 🗄️ Runtime configuration and SQLite are outside the public web root.
- 🛡️ Admin POST actions use CSRF protection.
- 🚫 Never commit tokens, passwords, private keys or real `.conf` files.
- 🌐 Use HTTPS and restrict Admin Panel access before public deployment.

## 🛠️ Management

```bash
systemctl status routebox-telegram-bot
journalctl -u routebox-telegram-bot -f
bash /opt/routebox-telegram-bot/update.sh
bash /opt/routebox-telegram-bot/uninstall.sh
```

## 💳 Payment & Subscription Roadmap

Payment is **not implemented in Beta 1**.

Future updates will add both **Iranian Rial** and **Crypto** payment options, in addition to subscription management.

Planned commercial features:

- 🇮🇷 Iranian Rial payment gateway
- 🪙 Cryptocurrency payment gateway
- 🔄 Subscription renewal and upgrades
- 🧾 Invoices and payment history
- 🎟️ Coupons / referral system
- 🌍 Server / Region selection
- 📊 Usage and traffic dashboards
- 🔔 Expiration notifications
- 🔗 Subscription links / QR workflow
- 🌐 Persian / English Bot interface

## 🗺️ Roadmap — Beta 1

- [x] Telegram Bot foundation
- [x] Independent web Admin Panel
- [x] RouteBox API client
- [x] Session authentication + Basic fallback
- [x] Multiple RouteBox servers
- [x] Same logical Telegram identity across servers
- [x] AWG provisioning
- [x] Expiration
- [x] `.conf` delivery
- [x] Encrypted credentials
- [x] Trial reuse protection
- [x] Multi-server rollback
- [x] Interactive installer
- [x] Telegram validation
- [x] RouteBox API validation
- [x] Full AWG create/export/delete smoke test
- [x] Admin-side smoke test
- [x] CSRF protection
- [x] Ubuntu 22.04+ installer
- [x] Update / uninstall scripts

### 🔜 Future

- [ ] 💳 Iranian Rial payment gateway
- [ ] 🪙 Crypto payment gateway
- [ ] 🔄 Subscription system
- [ ] 🛒 Product / plan management
- [ ] 🌍 Region selection
- [ ] 📊 Usage dashboard
- [ ] 👨‍💼 Advanced user management
- [ ] 🔔 Expiration notifications
- [ ] 🌐 Persian / English Bot interface
- [ ] 🔗 Subscription links / QR workflow

## 🧩 RouteBox Compatibility

The integration uses the current RouteBox API architecture, including `/api/auth/login`, `/api/status`, `/api/settings` and `/api/awg/*`.

RouteBox documents separate VPS and router deployments, but the Bot intentionally hides that distinction from the installer: the operator supplies the actual Panel URL and the Bot uses that listener.

RouteBox API behavior can change between releases. Run the full smoke test against the exact RouteBox version installed on the server before accepting real users.

## 🧪 Beta Notice

This is a **Beta release** and has not completed a production-scale test cycle. Treat the first installation as an integration test and do not enable paid sales until Telegram → RouteBox → AWG has been verified on your own server.

## 🤝 Contributing

Issues and pull requests are welcome. When reporting a problem, include Ubuntu, RouteBox, Bot and PHP versions plus sanitized logs.

Never include credentials, private keys or real client configurations.

## 📄 License

The final license is being determined. Review the repository license terms before commercial use or redistribution.
