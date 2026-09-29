# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Admin Panel for RouteBox / AmneziaWG
>
> **Current version: `0.1.0-beta.7` · Status: 🧪 Beta**
>
> **Version source of truth:** [`VERSION`](./VERSION). Documentation must not hard-code an older beta number.

## ✨ What is it?

RouteBox Telegram Bot is an independent PHP application for provisioning and managing **AmneziaWG peers** through one or more RouteBox servers.

RouteBox files and databases are not modified directly. The application talks to RouteBox through its HTTP API and uses the RouteBox-provided configuration for client provisioning.

```text
📱 Telegram
    ↓
🤖 Bot Worker
    ↓
🖥️ Admin Panel
    ↓
🌐 RouteBox API
    ↓
🔐 AmneziaWG
    ↓
📄 Client .conf
```

## ✨ Current features

### Telegram Bot

- 🎁 Configurable free trial
- 👤 Telegram user identity and service status
- 🌐 Persian / English user experience
- ✏️ Editable bilingual welcome message
- 🎛️ Editable bilingual fixed buttons
- 📦 Dynamic plan/button management
- 🔑 Automatic AmneziaWG peer creation
- 🌍 Multi-RouteBox provisioning
- 🆔 Same logical peer name across enabled servers (`user<telegram_id>`)
- ⏱️ Expiration management
- 📊 Traffic quota per plan
- 📄 Automatic `.conf` delivery
- 🛡️ Trial reuse protection
- 🔄 Multi-server rollback attempts after failed provisioning
- `/start`, `/menu`, `/account`, `/language`

### Admin Panel

- 🇮🇷 Persian / 🇬🇧 English UI
- 🧭 Responsive sidebar dashboard
- ☀️🌙 Light/Dark theme with saved preference
- 🌍 RouteBox server cards with country flag/code
- 📡 TCP ping/latency display
- 🗺️ Automatic country detection when country is not configured
- 🤖 Editable bilingual welcome messages
- 🎛️ Editable fixed Telegram buttons
- 📦 Plan creation/editing/enabling/disabling
- 🔐 Admin password change and CLI recovery
- 🔄 In-panel software updater
- 📝 Update/application logs
- 🧪 RouteBox integration and AWG smoke-test validation
- 🔐 Encrypted Telegram and RouteBox credentials

### HTTPS / TLS

Beta 7 uses the existing RouteBox panel certificate when it is exported at:

```text
/etc/routebox/panel-cert/fullchain.pem
/etc/routebox/panel-cert/key.pem
```

The important rule is that the **Bot keeps its existing public Admin Panel port** and accepts both HTTP and HTTPS on that same port.

For example, if the panel port is `8093`:

```text
http://SERVER-IP:8093/
https://ROUTEBOX-DOMAIN:8093/
```

The Bot does not take over RouteBox's listener and does not change ports `80` or `443`.

Internally the Bot uses:

```text
                         ┌── HTTP ───────────────→ PHP
Client → :8093 → HAProxy┤
                         └── TLS → stunnel ──────→ PHP
```

The PHP listener and TLS terminator are loopback-only. HAProxy is a **Bot-owned dedicated instance**, not the system HAProxy service.

A systemd timer checks the RouteBox certificate every five minutes and reloads the Bot TLS terminator when the certificate changes.

## 🧩 RouteBox connection

The Bot does not require a separate RouteBox API port. Configure the URL of the RouteBox panel/API listener itself:

```text
https://panel.example.com:8443
http://192.0.2.10:8093
https://panel.example.com
```

The installer accepts a URL with or without a scheme and normalizes it.

The AmneziaWG UDP listen port is unrelated to the Bot's RouteBox API connection.

## 🔐 RouteBox authentication

The integration uses RouteBox's current session authentication model:

```text
POST /api/auth/login
        ↓
Session Cookie
        ↓
Protected API requests
        ↓
401 → re-login once → retry
```

Session cookies remain in process memory and are not stored in SQLite.

## 🧪 RouteBox validation

Before a RouteBox server is accepted, the installer uses the same `RouteBoxClient` used by the Bot and validates the integration, including the real AmneziaWG create/export/delete smoke test.

The temporary smoke-test peer is removed after validation. If validation fails, the server is not saved.

## 📦 Installation — Ubuntu 22.04+

The recommended installation command is:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

`install.sh` is the public entrypoint. It downloads and executes the current `install-v2.sh`, performs the final web-panel repair/health check, installs the restricted updater, and then applies the optional RouteBox certificate integration.

The installer:

1. Installs required Ubuntu/PHP packages.
2. Creates the application and SQLite database.
3. Generates the application encryption key and Admin password on first install.
4. Validates the Telegram Bot token.
5. Validates every configured RouteBox server.
6. Runs the AWG create/export/delete smoke test.
7. Installs the Telegram worker and independent Admin Panel.
8. Stores the selected Admin Panel port in `/etc/routebox-telegram-bot/web-port`.
9. If the RouteBox panel certificate is available, enables same-port HTTP+HTTPS.

### Existing services are left alone

The Bot does **not** install, configure, stop or replace RouteBox, Apache or Nginx on ports `80/443`.

```text
RouteBox / Apache / Nginx : existing services, untouched
Bot Admin Panel           : independent service
Bot TLS frontend          : Bot-owned HAProxy + stunnel on the existing Bot port
Bot Worker                : routebox-telegram-bot.service
```

## 🖥️ Admin Panel

The selected port is stored here:

```bash
cat /etc/routebox-telegram-bot/web-port
```

First-run credentials:

```text
Username: admin
Password: generated during installation
```

HTTP when TLS is unavailable:

```text
http://SERVER-IP:<PORT>/
```

When RouteBox TLS integration is enabled, both are valid on the **same `<PORT>`**:

```text
http://SERVER-IP:<PORT>/
https://ROUTEBOX-DOMAIN:<PORT>/
```

The HTTPS hostname must match the certificate presented by RouteBox.

### Useful service checks

```bash
sudo systemctl status routebox-telegram-bot.service
sudo systemctl status routebox-telegram-bot-web@$(cat /etc/routebox-telegram-bot/web-port).service
sudo systemctl status routebox-telegram-bot-mux.service
sudo systemctl status routebox-telegram-bot-tls.service
sudo systemctl status routebox-telegram-bot-tls-sync.timer
```

## 🔄 HTTPS setup / repair

The integration can be applied or repaired with:

```bash
cd /opt/routebox-telegram-bot
sudo bash setup-routebox-tls.sh
```

It will:

- reuse the RouteBox panel certificate;
- keep the existing Bot public port unchanged;
- accept HTTP and HTTPS on that same port;
- keep PHP and the TLS terminator on loopback;
- run HTTP and HTTPS health checks before reporting success;
- never bind `80/443`;
- never change the RouteBox/Apache/Nginx configuration.

If the RouteBox certificate is not available, the script leaves the HTTP panel running and exits without breaking it.

## 🤖 Telegram Bot

Create the Bot with **@BotFather** using `/newbot` and keep the token private.

Users can use:

```text
/start
/menu
/account
/language
```

The Admin Panel controls the bilingual welcome message, fixed buttons and enabled plans.

## 🛒 Plans

Each plan supports:

```text
Name:       e.g. 30 Days / 50 GB
Duration:   number of days
Traffic:    GB
Traffic=0:  unlimited
Enabled:    show/hide Telegram button
```

Payment is **not implemented yet**. Selected plans are provisioned immediately for testing.

## 🌍 Multi-RouteBox

```text
                  🤖 Bot
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
   RouteBox #1  RouteBox #2  RouteBox #3
       AWG          AWG          AWG
```

For Telegram ID `123456789`, the logical peer name is `user123456789`. Each RouteBox generates its own cryptographic keypair.

## 📄 Client configuration

The Bot requests the real configuration from RouteBox and sends the resulting `.conf` file to the Telegram user. It does not invent the AmneziaWG endpoint.

## 🔄 Updating

### From the Admin Panel

The controlled updater can be run from the **Updates** section. It uses the repository's `VERSION` file as the application version source and reinstalls the required service definitions.

### From the server

```bash
sudo bash /opt/routebox-telegram-bot/update.sh
```

The updater restores the optional RouteBox TLS integration when the RouteBox certificate is available.

Update logs:

```text
/opt/routebox-telegram-bot/storage/logs/admin-update.log
```

The web user is granted only the fixed updater sudo command; it does not receive general root access.

## 🛠️ Management

```bash
sudo systemctl status routebox-telegram-bot.service
sudo journalctl -u routebox-telegram-bot.service -f
sudo bash /opt/routebox-telegram-bot/update.sh
sudo bash /opt/routebox-telegram-bot/uninstall.sh
```

For a broken/blank panel:

```bash
sudo bash /opt/routebox-telegram-bot/repair-web.sh
```

## 🗂️ Repository layout

| Path | Purpose |
|---|---|
| `install.sh` | Public one-command installer entrypoint |
| `install-v2.sh` | Main installation/setup implementation used by `install.sh` |
| `update.sh` | Full server update and service restoration |
| `admin-update.sh` | Restricted root wrapper used by the Admin Panel updater |
| `setup-routebox-tls.sh` | Same-port HTTP+HTTPS integration using RouteBox's certificate |
| `repair-web.sh` | Admin Panel permission/service repair and health check |
| `reset-admin-password.php` | Manual Admin password recovery utility |
| `worker.php` | Telegram Bot worker |
| `bot.php` | Backward-compatible entrypoint that loads `worker.php` |
| `src/` | RouteBox API client and application bootstrap |
| `public/` | Web Admin Panel |
| `database/schema.sql` | SQLite schema |
| `systemd/` | Telegram worker service definition |
| `docs/` | Operational documentation |
| `VERSION` | **Single source of truth for the application version** |
| `CHANGELOG.md` | Release history |

`install-v2.sh` and `bot.php` are **not unused leftovers**: the first is called by `install.sh`, and the second is retained as a compatibility entrypoint.

## 🗺️ Roadmap

- [x] Telegram Bot foundation
- [x] Independent Web Admin Panel
- [x] RouteBox API client
- [x] Session authentication
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
- [x] Plan/button manager
- [x] Server ping + country flag
- [x] Light/Dark mode
- [x] Persian/English Admin Panel
- [x] Persian/English Telegram Bot
- [x] Editable welcome message and fixed buttons
- [x] Web password change + CLI recovery
- [x] Admin Panel software updater
- [x] Update / uninstall / repair scripts
- [x] RouteBox certificate reuse for Admin Panel HTTPS
- [x] Same-port HTTP + HTTPS for the Admin Panel

### 🔜 Future

- [ ] 💰 Iranian Rial payment gateway
- [ ] 🪙 Cryptocurrency payment gateway
- [ ] 🔄 Subscription system
- [ ] 💳 Payment-gated provisioning
- [ ] 🌍 Region/server selection
- [ ] 📊 Usage dashboard
- [ ] 👨‍💼 Advanced user management
- [ ] 🔔 Expiration notifications
- [ ] 🔗 Subscription links / QR workflow

## 🔐 Security

- 🔒 Telegram tokens and RouteBox credentials use libsodium SecretBox encryption.
- 🔑 The application key is generated locally and is never committed.
- 🍪 RouteBox session cookies remain in memory.
- 🗄️ SQLite and `config/config.php` are outside the public web root.
- 🛡️ Admin POST actions use CSRF protection.
- 🔐 The Admin Panel updater uses a dedicated fixed root wrapper instead of general sudo access.
- 🔒 The PHP backend and TLS terminator are loopback-only when HTTPS integration is enabled.
- 🚫 Never commit tokens, passwords, private keys or real `.conf` files.

## 🧪 Beta status

This repository is currently **`0.1.0-beta.7`**. Always use [`VERSION`](./VERSION) as the authoritative version number.

RouteBox API behavior can change between RouteBox releases. Test the exact RouteBox version installed on your server before enabling real users or paid sales.

## 📜 Changelog

See [`CHANGELOG.md`](./CHANGELOG.md) for the complete release history, including Beta 2–Beta 7.

## 👤 Creator

**RouteBox Telegram Bot** is created and maintained by **Amir Taheri**.

Telegram: https://t.me/+918807085399

© 2026 Amir Taheri

## 📄 License

The final license is being determined. Review the repository license terms before commercial use or redistribution.
