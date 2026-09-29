# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Admin Panel for RouteBox / AmneziaWG
>
> **Version: `0.1.0-beta.1` · Status: 🧪 Beta**

![Status](https://img.shields.io/badge/status-BETA-orange?style=for-the-badge)
![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04%2B-E95420?style=for-the-badge&logo=ubuntu&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-Bot-26A5E4?style=for-the-badge&logo=telegram&logoColor=white)
![RouteBox](https://img.shields.io/badge/RouteBox-API-111827?style=for-the-badge)

---

## ✨ What is it?

RouteBox Telegram Bot is an independent backend for provisioning and managing **AmneziaWG peers** through one or more RouteBox servers.

It does **not** modify RouteBox files, databases, `peers.toml`, or WireGuard configuration directly. All RouteBox operations go through the documented RouteBox HTTP API.

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

This is a **Beta** release. The current goal is to validate the complete Telegram → Backend → RouteBox → AmneziaWG workflow before adding commercial payment features.

## 🤖 Current Bot Features

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

The Bot is configured from its own web panel. Administrative settings do **not** need to be entered inside Telegram.

Current settings include:

- 🤖 Telegram Bot Token
- 🎁 Trial duration
- 🌐 RouteBox server list
- 🟢 Enable / disable RouteBox servers
- 🧪 Full RouteBox integration test
- 👥 Recent Telegram users
- 📝 Application status and logs

### 🔬 Important: Admin-side RouteBox validation

Adding a RouteBox server from the Admin Panel does not simply save the URL.

The server is saved only after these checks pass:

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

The temporary peer is always deleted after the test. The same full integration test can be run later from the Admin Panel.

## 🌍 Multi-RouteBox

```text
                  🤖 Bot
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
   RouteBox #1  RouteBox #2  RouteBox #3
       AWG          AWG          AWG
```

For a Telegram user such as ID `123456789`, the logical peer name is:

```text
user123456789
```

Each RouteBox generates its own cryptographic keypair. The logical customer identity stays consistent across all enabled servers.

If a later server fails during a new provisioning operation, the Bot attempts to delete peers that it created during that operation.

## 🔌 RouteBox URL / Port

There is **no separate API port** for the Bot.

The Bot uses the same HTTP(S) listener as the RouteBox web panel. Enter the exact URL that opens the RouteBox panel:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

The installer and Admin Panel do **not** ask for:

- ❌ VPS / Router mode
- ❌ Scheme
- ❌ Host separately
- ❌ API port separately

They accept one complete Panel URL.

Typical RouteBox deployments use `8443` for the VPS panel and `8080` for the router panel; a reverse proxy can expose the panel on `443`. These are deployment details, not Bot configuration modes.

## 🔐 RouteBox Authentication

The Bot follows the current RouteBox authentication model:

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

RouteBox's current source sets a session cookie from `/api/auth/login` and retains Basic authentication support for scripts. The Bot keeps the session in process memory and does not store the RouteBox session cookie in SQLite.

## 📦 Installation — Ubuntu 22.04+

On a clean Ubuntu 22.04+ VPS:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The interactive installer handles:

1. 📦 Dependencies
2. 📥 Application installation
3. 🗄️ SQLite initialization
4. 🔐 Local encryption key generation
5. 👤 Admin password generation
6. 🤖 Telegram token validation
7. 🌐 RouteBox configuration
8. 🧪 Full RouteBox + AWG smoke testing
9. 🌐 Nginx + PHP-FPM setup
10. ⚙️ systemd service setup
11. 🧪 PHP / shell validation
12. ❤️ Service health check

### Telegram setup

Create a bot with **@BotFather** using `/newbot`. The installer calls Telegram `getMe` before saving the token.

### RouteBox setup

For each server the installer asks for:

```text
Server name
RouteBox Panel URL
RouteBox username (optional if RouteBox auth is disabled)
RouteBox password (optional if RouteBox auth is disabled)
Verify TLS certificate? (HTTPS only)
```

The URL is the only network address you need to enter.

## 🧪 Installation Smoke Test

A RouteBox server is not accepted merely because its port is reachable.

The installer validates authentication and the API, then performs a real temporary AWG operation:

```text
1. Authenticate
2. Read RouteBox status
3. Read AWG status
4. Read peers
5. Create temporary peer
6. Fetch the generated client .conf
7. Delete temporary peer
8. Save the RouteBox server only if all steps succeed
```

This is intentionally destructive only to a temporary peer created by the installer itself. If cleanup fails, installation reports the failure instead of silently pretending the integration is healthy.

## 📄 Client Configuration

The Bot does not generate AWG private keys or `.conf` files itself.

It asks RouteBox for the generated configuration:

```text
GET /api/awg/peers/{publicKey}/config
```

RouteBox itself renders the client configuration. Therefore the RouteBox AWG server should have a valid client-facing **Server address** or a configured **Public host** so exported configurations contain a usable endpoint.

## 🔐 Credential Security

- 🔒 Telegram Bot Tokens are encrypted with libsodium SecretBox.
- 🔒 RouteBox usernames/passwords are encrypted with libsodium SecretBox.
- 🔑 The application encryption key is generated locally and is not committed to GitHub.
- 🍪 RouteBox session cookies are kept in memory only.
- 🗄️ Runtime configuration and SQLite are outside the public web root.
- 🛡️ Admin POST actions use CSRF protection.
- 🚫 Real tokens, passwords, private keys, and `.conf` files must never be committed.

Use HTTPS and restrict access to the Admin Panel before exposing it publicly.

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

### 🇮🇷 Iranian Rial

- 💰 Iranian Rial payment gateway
- 🔄 Subscription renewal and upgrades
- 🧾 Invoices and payment history
- 🎟️ Discount / campaign codes

### 🪙 Crypto

- Cryptocurrency payment gateway
- 🔄 Subscription renewal and upgrades
- 🧾 Payment history
- 🔔 Automated payment status handling

Planned commercial features also include:

- 🌍 Server / Region selection
- 📊 Usage and traffic dashboards
- 👨‍💼 Advanced user management
- 🎟️ Coupons and referrals
- 🔔 Expiration notifications
- 🔗 Subscription links and QR workflow
- 🌐 Persian / English Bot interface

## 🗺️ Roadmap

### v0.1.0-beta.1

- [x] Telegram Bot foundation
- [x] Independent web Admin Panel
- [x] RouteBox API client
- [x] RouteBox session authentication + Basic fallback
- [x] Multiple RouteBox servers
- [x] Same logical Telegram identity across servers
- [x] AWG peer provisioning
- [x] Expiration
- [x] `.conf` delivery
- [x] Encrypted credentials
- [x] Trial reuse protection
- [x] Multi-server rollback
- [x] Interactive installer
- [x] Telegram token validation
- [x] RouteBox API validation
- [x] Full AWG create/export/delete installation smoke test
- [x] Admin-side RouteBox smoke test
- [x] CSRF protection for admin actions
- [x] Ubuntu 22.04+ installation
- [x] Update / uninstall scripts

### 🔜 Future

- [ ] 💳 Iranian Rial payment gateway
- [ ] 🪙 Crypto payment gateway
- [ ] 🔄 Subscription renewal
- [ ] 🛒 Product / plan management
- [ ] 🌍 Region selection
- [ ] 📊 Usage dashboard
- [ ] 👨‍💼 Advanced user management
- [ ] 🎟️ Coupons / referrals
- [ ] 🔔 Expiration notifications
- [ ] 🌐 Persian / English Bot interface
- [ ] 🔗 Subscription links / QR workflow

## 🧩 RouteBox Compatibility

The integration is built around the RouteBox API documented by the upstream project, including `/api/auth/login`, `/api/status`, `/api/settings`, and `/api/awg/*`.

The upstream RouteBox project documents separate VPS and router deployments, but the Bot intentionally does not expose that distinction to the installer: the operator supplies the actual Panel URL and the Bot uses that listener for the API.

RouteBox API behavior can change between releases. Always run the Bot's full smoke test against the exact RouteBox version installed on the server before accepting real users.

## 🧪 Beta Notice

This project is a **Beta release** and has not yet completed a production-scale test cycle.

The first real installation should be treated as an integration test. Do not enable paid sales until the Telegram → RouteBox → AWG flow has been verified on your own server.

## 🤝 Contributing

Issues and pull requests are welcome for bugs, documentation, and feature requests.

When reporting a problem, include:

- Ubuntu version
- RouteBox version
- Bot version
- PHP version
- Sanitized logs

Never include credentials, private keys, or real client configurations.

## 📄 License

The final license is being determined. Review the repository license terms before commercial use or redistribution.
