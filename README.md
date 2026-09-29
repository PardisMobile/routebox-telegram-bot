# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Admin Panel for RouteBox / AmneziaWG
>
> **Version: `0.1.0-beta.3` · Status: 🧪 Beta**

## ✨ What is it?

An independent backend for provisioning and managing **AmneziaWG peers** through one or more RouteBox servers. RouteBox files and databases are not modified directly; all RouteBox operations use its HTTP API.

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
- 📦 Traffic quota support per Telegram plan
- 🛒 Dynamic Telegram plan/button management from the Admin Panel
- 📄 Automatic `.conf` delivery
- 🛡️ Trial reuse protection
- 🔄 Rollback attempts after failed multi-server provisioning
- 📡 RouteBox server ping/latency display
- 🌍 RouteBox country flags in the Admin Panel
- ☀️🌙 Light/Dark Admin Panel mode
- 🔐 Web Admin password change and CLI recovery
- 🔄 Admin Panel self-update with GitHub version checking
- 📝 Application and provisioning logs
- 🧪 End-to-end RouteBox validation before a server is accepted
- 🔐 Encrypted Telegram and RouteBox credentials

## 🧩 RouteBox Connection: one URL, no extra API port

The Bot does **not** need a separate RouteBox API port.

Enter the URL that opens the RouteBox panel, including its HTTP/HTTPS scheme and port when one is present:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

For convenience, the installer also accepts `panel.example.com:8443` and automatically adds `https://`.

The AmneziaWG **UDP** listen port is unrelated to the Bot API connection. The Bot talks to the RouteBox HTTP(S) listener only.

The installer does not ask for VPS/Router mode, scheme, host and API port separately.

## 🔐 RouteBox Authentication

The integration follows RouteBox's current authentication model:

```text
POST /api/auth/login
        ↓
Session Cookie
        ↓
Protected API requests
        ↓
401 → re-login once → retry
        ↓
HTTP Basic compatibility fallback
```

Session cookies remain in process memory and are never stored in SQLite.

## 🧪 Installer validation

A reachable panel is not enough. Before a RouteBox server is saved, the installer uses the **same `RouteBoxClient` used by the Bot** and validates:

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

The temporary peer is named `rbt-install-test-*` and is removed after the test. This verifies authentication, API permissions, AmneziaWG availability, peer creation, key handling, config rendering, URL encoding and deletion.

If the real create/export/delete smoke test fails, the RouteBox is **not saved**.

## 📦 Installation — Ubuntu 22.04+

Run this on the Ubuntu server; you do **not** need to clone the repository on your Mac:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer:

1. Checks Ubuntu 22.04+ and installs the required PHP/runtime packages.
2. Downloads or updates the current Beta code.
3. Initializes SQLite and the local encryption key.
4. Validates the Telegram Bot Token with Telegram `getMe`.
5. Stores the Telegram token encrypted; the full token is never printed.
6. Asks only for the RouteBox panel URL and credentials needed for that panel.
7. Runs the complete RouteBox API + AWG smoke test before saving each server.
8. Installs the Telegram worker as a systemd service.
9. Starts the Admin Panel on its own free port, normally `8090`.

### Why the Admin Panel no longer uses Nginx

Beta 2 deliberately removes the Nginx/PHP-FPM path from the installer. The Admin Panel uses PHP's own listener on `8090` (or the next free port).

This means the installer does **not** install, start, stop, reload or configure Nginx or Apache. Existing RouteBox, Apache and Nginx services on ports 80/443 are left alone.

```text
RouteBox / Apache / Nginx   ← untouched
Bot Admin Panel :8090       ← independent PHP service
Bot Worker                  ← systemd
```

This specifically avoids the `nginx.service ... Address already in use` failure seen when another service already owns port 80.

The selected Admin Panel port is stored in:

```text
/etc/routebox-telegram-bot/web-port
```

## 🤖 Telegram Bot

Create the Bot with **@BotFather** using `/newbot` and keep the token private.

During installation the token is entered silently, checked with `getMe`, and stored encrypted. The installer also attempts to remove an existing webhook because this Beta uses long polling.

Expected result:

```text
✓ Telegram connection successful: @YourBot
✓ Token accepted and stored encrypted (token is never printed).
```

## 🖥️ RouteBox setup

For each RouteBox the wizard asks:

```text
Server name [RouteBox-1]:
RouteBox Panel URL:
RouteBox username [admin]:
RouteBox password:
Verify TLS certificate? [Y/n]:
```

If RouteBox authentication is disabled, leave the credentials empty. For HTTPS, keep TLS verification enabled when the certificate is valid.

Before using the Bot, configure a usable AmneziaWG **Server address / Public host** in RouteBox. RouteBox renders the client `.conf`; the Bot does not invent that endpoint.

## 🖥️ Admin Panel

First-run credentials:

```text
Username: admin
Password: <generated during installation>
```

The installer prints the generated password once. Save it securely.

Check the selected port:

```bash
cat /etc/routebox-telegram-bot/web-port
```

Check the service:

```bash
PORT=$(cat /etc/routebox-telegram-bot/web-port)
systemctl status routebox-telegram-bot-web@$PORT
```

Open:

```text
http://YOUR_SERVER_IP:<PORT>/
```

### 🔄 Updating from the Admin Panel

Beta 3 adds a controlled software updater at:

```text
/update.php
```

The updater:

1. Reads the installed `VERSION`.
2. Checks the latest `VERSION` on the official GitHub repository.
3. Shows whether a newer release is available.
4. When confirmed, stops the Bot worker and its Admin Panel service.
5. Runs the existing `update.sh` Git-based update process.
6. Reinstalls the systemd definitions and the restricted updater permission.
7. Starts the Admin Panel and Bot worker again.
8. Keeps the existing RouteBox, Apache and Nginx services untouched.

For security, the web user receives **only one restricted sudo permission** for `/usr/local/sbin/routebox-telegram-bot-update`; it is not granted general root access.

The update log is stored at:

```text
/opt/routebox-telegram-bot/storage/logs/admin-update.log
```

If the panel updater has not yet been enabled on an existing installation, run one manual update first:

```bash
cd /opt/routebox-telegram-bot
sudo bash update.sh
```

After that, the `/update.php` updater is installed and available from the Admin Panel security page.

### 🔐 HTTPS / Reverse Proxy

The Bot Admin Panel does not take over ports 80/443. For production HTTPS, use the existing TLS endpoint / reverse proxy and forward internally to the Bot's local PHP listener. Do not install another Nginx configuration on an already-used 80/443 port.

## 🛒 Telegram plans and buttons

Plans are managed from the Admin Panel. No PHP editing is required.

Each plan supports:

```text
Name:        e.g. 30 Days / 50 GB
Duration:    number of days
Traffic:     GB
Traffic = 0: unlimited
Enabled:     show/hide Telegram button
```

Enabled plans automatically appear in the Telegram `/start` menu. Editing a plan changes future provisioning without changing existing RouteBox peers.

Payment is not yet connected, so Beta 3 provisions the selected plan immediately for testing. Payment integration is planned as the next phase.

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

The Bot requests the real configuration from RouteBox:

```text
GET /api/awg/peers/{publicKey}/config
```

The AWG UDP port is separate from the RouteBox HTTP/API listener. The Bot never connects to the AWG UDP port for API operations.

## 🛠️ Management

```bash
systemctl status routebox-telegram-bot
journalctl -u routebox-telegram-bot -f
bash /opt/routebox-telegram-bot/update.sh
bash /opt/routebox-telegram-bot/uninstall.sh
```

## 💳 Payment & Subscription Roadmap

Payment is **not implemented in Beta 3**.

Future updates will add both **Iranian Rial** and **Cryptocurrency** payment options, in both the **Persian and English** user experience, together with subscription management.

Planned features include subscription renewal/upgrades, invoices, coupons/referrals, server/region selection, usage dashboards, expiration notifications, subscription links/QR workflow, and richer Persian/English Bot interfaces.

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
- [x] Web password change + CLI recovery
- [x] Admin Panel software updater
- [x] Update / uninstall scripts

### 🔜 Future

- [ ] 💰 Iranian Rial payment gateway
- [ ] 🪙 Cryptocurrency payment gateway
- [ ] 🔄 Subscription system
- [ ] 💳 Payment-gated provisioning
- [ ] 🌍 Region selection
- [ ] 📊 Usage dashboard
- [ ] 👨‍💼 Advanced user management
- [ ] 🔔 Expiration notifications
- [ ] 🌐 Persian / English Bot interface
- [ ] 🔗 Subscription links / QR workflow

## 🔐 Security

- 🔒 Telegram tokens and RouteBox credentials use libsodium SecretBox encryption.
- 🔑 The application key is generated locally and is never committed.
- 🍪 RouteBox session cookies stay in memory.
- 🗄️ SQLite and `config/config.php` are outside the public web root.
- 🛡️ Admin POST actions use CSRF protection.
- 🔐 The Admin Panel updater uses a dedicated fixed root wrapper instead of general sudo access.
- 🚫 Never commit tokens, passwords, private keys or real `.conf` files.

## 🧪 Beta notice

This is **Beta 3**. The installer is intentionally strict: it will not accept a RouteBox server until the real API + AmneziaWG create/export/delete smoke test succeeds.

RouteBox API behavior can change between releases. Test the exact RouteBox version installed on your server before enabling real users or paid sales.

## 👤 Creator

**RouteBox Telegram Bot** is created and maintained by **Amir Taheri**.

📱 Telegram: https://t.me/+918807085399

© 2026 Amir Taheri

## 📄 License

The final license is being determined. Review the repository license terms before commercial use or redistribution.
