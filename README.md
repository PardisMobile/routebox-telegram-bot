# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Admin Panel for RouteBox / AmneziaWG
>
> **Version: `0.1.0-beta.6` · Status: 🧪 Beta**

## ✨ What is it?

An independent backend for provisioning and managing **AmneziaWG peers** through one or more RouteBox servers. RouteBox files and databases are not modified directly; all RouteBox operations use its HTTP API.

```text
📱 Telegram → 🤖 Bot Worker → 🖥️ Admin Panel → 🌐 RouteBox API → 🔐 AmneziaWG → 📄 .conf
```

## ✨ Current Features — Beta 6

- 🎁 Configurable free trial
- 👤 Telegram user identity and service status
- 🌐 Persian / English Telegram user experience with language switching
- ✏️ Editable bilingual welcome message from the Admin Panel
- 🎛️ Editable bilingual fixed bot buttons (trial / account / language)
- 📦 Dynamic Telegram plan/button management from the Admin Panel
- 🔑 Automatic AmneziaWG peer creation
- 🌍 Multi-RouteBox provisioning
- 🆔 Same logical peer name across enabled servers (`user<telegram_id>`)
- ⏱️ Expiration management
- 📦 Traffic quota support per Telegram plan
- 📄 Automatic `.conf` delivery
- 🛡️ Trial reuse protection
- 🔄 Rollback attempts after failed multi-server provisioning
- 📡 RouteBox server ping/latency display
- 🌍 RouteBox country flags with automatic country detection fallback
- ☀️🌙 Modern responsive Admin Panel with Light/Dark mode
- 🇮🇷🇬🇧 Persian / English Admin Panel UI
- 🧭 Sidebar navigation and mobile-friendly layout
- 🔐 Web Admin password change and CLI recovery
- 🔄 Admin Panel self-update with GitHub version checking
- 📝 Application and provisioning logs
- 🧪 End-to-end RouteBox validation before a server is accepted
- 🔐 Encrypted Telegram and RouteBox credentials
- 🔒 HTTPS Admin Panel using the existing RouteBox panel certificate when available

## 🧩 RouteBox Connection: one URL, no extra API port

The Bot does **not** need a separate RouteBox API port. Enter the URL that opens the RouteBox panel, including its HTTP/HTTPS scheme and port when one is present:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

The installer accepts `panel.example.com:8443` and automatically adds `https://` when needed. The AmneziaWG UDP listen port is unrelated to the Bot API connection.

## 🔐 RouteBox Authentication

The integration follows RouteBox's current authentication model:

```text
POST /api/auth/login → Session Cookie → Protected API requests
                                  ↘ 401 → re-login once → retry
```

Session cookies remain in process memory and are never stored in SQLite.

## 🧪 Installer validation

Before a RouteBox server is saved, the installer uses the **same `RouteBoxClient` used by the Bot** and validates health/status, authentication, AmneziaWG availability, peer creation, config rendering and deletion. The temporary smoke-test peer is removed after validation. If the real create/export/delete smoke test fails, the RouteBox is **not saved**.

## 📦 Installation — Ubuntu 22.04+

Run this on the Ubuntu server; you do not need to clone the repository on your Mac:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer checks Ubuntu/PHP requirements, initializes SQLite and encryption, validates Telegram, validates RouteBox/AWG end-to-end, installs the Telegram worker, and starts the Admin Panel on its own free port (normally `8090`). If RouteBox exposes its panel certificate at the standard certificate-export path, the installer also enables the safe HTTPS integration described below.

### Why the Admin Panel does not use Nginx

The Admin Panel uses an independent PHP listener. The installer does **not** install, start, stop, reload or configure Nginx/Apache for the Bot, so existing RouteBox/Apache/Nginx services on ports 80/443 remain untouched.

```text
Existing RouteBox / Apache / Nginx : untouched
Bot Admin Panel                    : independent PHP backend
Bot TLS frontend                   : dedicated TLS wrapper on the existing panel port
Bot Worker                         : routebox-telegram-bot.service
```

## 🔒 HTTPS using RouteBox's existing ACME certificate

When RouteBox is running its own ACME/manual TLS, it exports the current panel certificate to:

```text
/etc/routebox/panel-cert/fullchain.pem
/etc/routebox/panel-cert/key.pem
```

Beta 6 can reuse that certificate for the Bot Admin Panel. The design is intentionally isolated:

1. The existing Admin Panel port is kept (for example `8093`).
2. PHP moves behind the TLS wrapper on `127.0.0.1` only.
3. The TLS wrapper listens on the existing Admin Panel port and forwards locally to PHP.
4. No Nginx or Apache configuration is changed.
5. Ports `80` and `443` are never claimed by the Bot.
6. A systemd timer checks the RouteBox certificate every five minutes and reloads the Bot TLS listener when RouteBox renews the certificate.

So, for an existing `8093` installation, the public address becomes:

```text
https://YOUR-ROUTEBOX-DOMAIN:8093/
```

The browser sees the same trusted RouteBox/Let's Encrypt certificate. The Bot does not request a second certificate and does not run a second ACME client.

If the RouteBox certificate export is not available, the installer leaves the Admin Panel on HTTP and does not break the installation.

### Existing installation

After pulling the new version, the one-command installer can apply the TLS integration automatically. You can also run the integration directly:

```bash
cd /opt/routebox-telegram-bot
sudo bash setup-routebox-tls.sh
```

Useful checks:

```bash
sudo systemctl status routebox-telegram-bot.service
sudo systemctl status routebox-telegram-bot-tls.service
sudo systemctl status routebox-telegram-bot-tls-sync.timer
```

The Telegram worker remains:

```bash
sudo systemctl restart routebox-telegram-bot.service
```

## 🤖 Telegram Bot

Create the Bot with **@BotFather** using `/newbot` and keep the token private.

### User language

On first `/start`, the Bot uses Telegram's language when available and provides a language switcher. Users can also use:

```text
/start
/menu
/account
/language
```

Persian and English welcome messages and the fixed buttons are editable from the Admin Panel. Enabled Plans automatically become additional Telegram buttons.

## 🖥️ Admin Panel

First-run credentials:

```text
Username: admin
Password: <generated during installation>
```

The selected public panel port is stored in:

```text
/etc/routebox-telegram-bot/web-port
```

Open HTTP only when TLS integration is unavailable:

```text
http://YOUR_SERVER_IP:<PORT>/
```

When RouteBox TLS integration is enabled, use:

```text
https://YOUR-ROUTEBOX-DOMAIN:<PORT>/
```

### ✨ Beta 6 Admin Panel

The panel includes:

- 🇮🇷 Persian / 🇬🇧 English interface
- 🧭 Sidebar navigation
- ☀️ Light / 🌙 Dark theme with saved preference
- 🌐 RouteBox server cards with country flag and ping
- 🤖 Editable bilingual welcome messages
- 🎛️ Editable fixed Telegram buttons
- 📦 Plan creation/editing/enabling/disabling
- 🔐 Admin password change
- ⬆️ In-panel software updater
- 📱 Responsive mobile layout
- 🔒 Optional safe HTTPS integration using the existing RouteBox panel certificate

## 🌍 Server country and ping

Each server can have a two-letter country code such as `US`, `DE` or `TR`. If the field is left empty, the panel attempts to detect the public IP country automatically. The panel also measures TCP connection latency to the configured RouteBox endpoint.

## 🔄 Updating from the Admin Panel

The controlled updater is available from the **Updates** section. It stops the Bot worker and Admin Panel, runs the Git-based update, reinstalls the service definitions, and starts the services again. Existing RouteBox/Apache/Nginx services remain untouched.

For security, the web user receives only one restricted sudo permission for `/usr/local/sbin/routebox-telegram-bot-update`; it is not granted general root access.

Update logs are stored at:

```text
/opt/routebox-telegram-bot/storage/logs/admin-update.log
```

## 🛒 Telegram Plans and Buttons

Plans are managed from the Admin Panel. Each plan supports:

```text
Name:        e.g. 30 Days / 50 GB
Duration:    number of days
Traffic:     GB
Traffic = 0: unlimited
Enabled:     show/hide Telegram button
```

Fixed Bot buttons such as **Free Trial**, **My Account** and **Language** have separate Persian/English labels that can be edited from the Admin Panel. Plan buttons are generated dynamically from the enabled Plans.

Payment is not yet connected, so Beta 6 still provisions selected plans immediately for testing. Payment integration is the next phase.

## 🌍 Multi-RouteBox

```text
                  🤖 Bot
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
   RouteBox #1  RouteBox #2  RouteBox #3
       AWG          AWG          AWG
```

For Telegram ID `123456789`, the logical peer name is `user123456789`. Each RouteBox still creates its own cryptographic keypair.

## 📄 Client configuration

The Bot requests the real configuration from RouteBox and sends the resulting `.conf` file to the Telegram user. The Bot does not invent the AmneziaWG endpoint.

## 🛠️ Management

```bash
sudo systemctl status routebox-telegram-bot.service
sudo journalctl -u routebox-telegram-bot.service -f
sudo bash /opt/routebox-telegram-bot/update.sh
sudo bash /opt/routebox-telegram-bot/uninstall.sh
```

## 💳 Payment & Subscription Roadmap

Payment is **not implemented in Beta 6**.

Future updates will add Iranian Rial and cryptocurrency payment options, Persian/English checkout, subscription renewal/upgrades, invoices, coupons/referrals, server/region selection, usage dashboards, expiration notifications and subscription links/QR workflows.

## 🗺️ Roadmap

- [x] Telegram Bot foundation
- [x] Independent Web Admin Panel
- [x] RouteBox API client
- [x] Session authentication + Basic fallback
- [x] Multiple RouteBox servers
- [x] AWG provisioning
- [x] Expiration
- [x] Traffic quota per plan
- [x] `.conf` delivery
- [x] Encrypted credentials
- [x] Trial reuse protection
- [x] Multi-server rollback attempts
- [x] Interactive installer
- [x] Telegram validation
- [x] RouteBox API validation
- [x] Full AWG create/export/delete smoke test
- [x] Port-independent Admin Panel
- [x] Plan/button manager
- [x] Server ping + country flag
- [x] Light/Dark mode
- [x] Persian/English Admin Panel
- [x] Persian/English Telegram Bot
- [x] Editable welcome message and fixed buttons
- [x] Web password change + CLI recovery
- [x] Admin Panel software updater
- [x] Update / uninstall scripts
- [x] RouteBox certificate reuse for Admin Panel HTTPS

### 🔜 Future

- [ ] 💰 Iranian Rial payment gateway
- [ ] 🪙 Cryptocurrency payment gateway
- [ ] 🔄 Subscription system
- [ ] 💳 Payment-gated provisioning
- [ ] 🌍 Region selection
- [ ] 📊 Usage dashboard
- [ ] 👨‍💼 Advanced user management
- [ ] 🔔 Expiration notifications
- [ ] 🔗 Subscription links / QR workflow

## 🔐 Security

- 🔒 Telegram tokens and RouteBox credentials use libsodium SecretBox encryption.
- 🔑 The application key is generated locally and is never committed.
- 🍪 RouteBox session cookies stay in memory.
- 🗄️ SQLite and `config/config.php` are outside the public web root.
- 🛡️ Admin POST actions use CSRF protection.
- 🔐 The Admin Panel updater uses a dedicated fixed root wrapper instead of general sudo access.
- 🔒 The HTTPS frontend reuses the RouteBox certificate and keeps the PHP backend on loopback.
- 🚫 Never commit tokens, passwords, private keys or real `.conf` files.

## 🧪 Beta notice

This is **Beta 6**. The installer is intentionally strict: it will not accept a RouteBox server until the real API + AmneziaWG create/export/delete smoke test succeeds.

RouteBox API behavior can change between releases. Test the exact RouteBox version installed on your server before enabling real users or paid sales.

## 👤 Creator

**RouteBox Telegram Bot** is created and maintained by **Amir Taheri**.

📱 Telegram: https://t.me/+918807085399

© 2026 Amir Taheri

## 📄 License

The final license is being determined. Review the repository license terms before commercial use or redistribution.
