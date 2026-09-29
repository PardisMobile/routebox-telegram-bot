# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Admin Panel for RouteBox / AmneziaWG
>
> **Version: `0.1.0-beta.1` · Status: 🧪 Beta**

## ✨ What is it?

An independent backend for provisioning and managing **AmneziaWG peers** through one or more RouteBox servers. RouteBox files and databases are not modified directly; operations use the RouteBox HTTP API.

```text
📱 Telegram → 🤖 Bot Worker → 🖥️ Admin Panel → 🌐 RouteBox API → 🔐 AmneziaWG → 📄 .conf
```

## 🤖 Current Features

- 🎁 Configurable free trial
- 👤 Telegram user identity and service status
- 🔑 Automatic AmneziaWG peer creation
- 🌍 Multi-RouteBox provisioning
- 🆔 Same logical peer name across enabled servers (`user<telegram_id>`)
- ⏱️ Expiration management
- 📄 Automatic `.conf` delivery
- 🛡️ Trial reuse protection
- 🔄 Rollback attempts after failed multi-server provisioning
- 📝 Application and provisioning logs
- 🧪 End-to-end RouteBox validation before a server is accepted

## 🖥️ Admin Panel and the Port-80 Fix

The installer now **checks port 80 before installing or starting Nginx**.

If RouteBox or another existing service already owns port 80, the installer does **not** stop it, replace it, or reload Nginx against that port. Instead, the Bot Admin Panel runs as an independent PHP service on a free port, normally `8090`.

```text
RouteBox :80
     │
     └── untouched

Bot Admin Panel :8090 (or next free port)
Bot Worker → systemd
```

The selected Admin Panel port is printed at the end of installation and saved in `/etc/routebox-telegram-bot/web-port`.

If port 80 is free, Nginx + PHP-FPM can be used normally.

This prevents the common `bind() to 0.0.0.0:80 failed (98: Address already in use)` error when RouteBox itself owns port 80.

## 🔌 RouteBox URL / API Port

There is **no separate API port for the Bot**. Enter the exact URL that opens the RouteBox web panel:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

The installer does not ask for VPS/Router mode, scheme, host and API port separately. The AWG UDP port is unrelated to the Bot API connection.

## 🔐 RouteBox Authentication

```text
POST /api/auth/login → Session Cookie → protected API calls
                         ↓
                    401 → re-login
                         ↓
              Basic Auth compatibility fallback
```

Session cookies remain in process memory and are not stored in SQLite.

## 📦 Installation — Ubuntu 22.04+

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer:

1. Detects whether port 80 is already occupied.
2. Leaves existing RouteBox/web services untouched.
3. Installs the required PHP/runtime packages.
4. Downloads or updates the current Beta code.
5. Initializes SQLite and encryption.
6. Validates the Telegram Bot Token.
7. Uses the **same `RouteBoxClient` as the Bot** to validate every RouteBox.
8. Runs a real AWG create → config export → delete smoke test.
9. Installs the Telegram worker as a systemd service.
10. Starts the Admin Panel with Nginx when safe, otherwise an independent PHP listener.

### Telegram

Create the Bot with **@BotFather**. Enter the token when prompted. It is read silently, checked with Telegram `getMe`, optionally has its webhook removed for polling, and is stored encrypted. The full token is never printed back.

### RouteBox

For each server enter:

```text
Server name
RouteBox Panel URL
RouteBox username
RouteBox password
TLS verification (HTTPS)
```

Leave username/password empty if RouteBox authentication is disabled.

## 🧪 End-to-End Smoke Test

A reachable panel is not enough. The installer validates:

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

The temporary peer uses an `rbt-install-test-*` name and is deleted after the test. If creation, config export, or cleanup fails, the server is not accepted.

## 🌍 Multi-RouteBox

```text
                  🤖 Bot
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
   RouteBox #1  RouteBox #2  RouteBox #3
       AWG          AWG          AWG
```

For Telegram ID `123456789`, the logical peer name is `user123456789`. Each RouteBox still generates its own cryptographic keypair.

## 📄 Client Configuration

The Bot requests the generated configuration from RouteBox:

```text
GET /api/awg/peers/{publicKey}/config
```

Configure a usable RouteBox AWG **Server address / Public host** so the exported configuration has a reachable endpoint.

## 🛠️ Management

```bash
systemctl status routebox-telegram-bot
journalctl -u routebox-telegram-bot -f
bash /opt/routebox-telegram-bot/update.sh
bash /opt/routebox-telegram-bot/uninstall.sh
```

For the independent Admin Panel:

```bash
cat /etc/routebox-telegram-bot/web-port
systemctl status routebox-telegram-bot-web@$(cat /etc/routebox-telegram-bot/web-port)
```

## 🔐 Security

- 🔒 Telegram tokens and RouteBox credentials use libsodium SecretBox encryption.
- 🔑 The application key is generated locally and is never committed.
- 🍪 RouteBox session cookies stay in memory.
- 🗄️ SQLite and `config/config.php` are outside the public web root.
- 🛡️ Admin POST actions use CSRF protection.
- 🚫 Never commit tokens, passwords, private keys or real `.conf` files.
- 🌐 For public access, put the Admin Panel behind HTTPS/reverse proxy or restrict its port with a firewall.

## 💳 Payment & Subscription Roadmap

Payment is **not implemented in Beta 1**.

Future updates will add both **Iranian Rial** and **Cryptocurrency** payment options, plus subscription management.

Planned features include subscription renewal/upgrades, invoices, coupons/referrals, server/region selection, usage dashboards, expiration notifications, subscription links/QR workflow, and Persian/English Bot interfaces.

## 🗺️ Roadmap — Beta 1

- [x] Telegram Bot foundation
- [x] Independent Web Admin Panel
- [x] RouteBox API client
- [x] Session authentication + Basic fallback
- [x] Multiple RouteBox servers
- [x] Same logical Telegram identity across servers
- [x] AWG provisioning
- [x] Expiration
- [x] `.conf` delivery
- [x] Encrypted credentials
- [x] Trial reuse protection
- [x] Multi-server rollback attempts
- [x] Interactive installer
- [x] Telegram validation
- [x] RouteBox API validation
- [x] Full AWG create/export/delete smoke test
- [x] Admin-side integration test
- [x] CSRF protection
- [x] Ubuntu 22.04+ installer
- [x] Port-80 conflict detection
- [x] Independent Admin Panel fallback
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

The Bot intentionally hides RouteBox deployment-mode details from the installer: provide the actual Panel URL and the Bot uses that HTTP(S) listener.

RouteBox API behavior can change between releases. Run the full smoke test against the exact RouteBox version installed on the server before accepting real users.

## 🧪 Beta Notice

This is a **Beta release** and has not completed a production-scale test cycle. Treat the first installation as an integration test and do not enable paid sales until Telegram → RouteBox → AWG has been verified on your own server.

## 🤝 Contributing

Issues and pull requests are welcome. Include Ubuntu, RouteBox, Bot and PHP versions plus sanitized logs. Never include credentials, private keys or real client configurations.

## 📄 License

The final license is being determined. Review the repository license terms before commercial use or redistribution.
