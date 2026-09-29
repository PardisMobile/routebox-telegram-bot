# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Management Panel for RouteBox and AmneziaWG
>
> **Version: `0.1.0-beta.1` · Status: 🧪 Beta**

![Status](https://img.shields.io/badge/status-BETA-orange?style=for-the-badge)
![Version](https://img.shields.io/badge/version-0.1.0--beta.1-blue?style=for-the-badge)
![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04%2B-E95420?style=for-the-badge&logo=ubuntu&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-Bot-26A5E4?style=for-the-badge&logo=telegram&logoColor=white)
![RouteBox](https://img.shields.io/badge/RouteBox-API-111827?style=for-the-badge)

---

## ✨ Overview

**RouteBox Telegram Bot** is an independent backend for managing AmneziaWG services across one or multiple RouteBox servers. The bot is managed through a separate web administration panel and communicates with RouteBox through its API without modifying RouteBox internal files.

This release is **Beta** and focuses on a stable, testable Telegram → Backend → RouteBox → AmneziaWG workflow.

## 🤖 Telegram Bot Features

```text
👤 Telegram User
        ↓
🤖 Telegram Bot
        ↓
🖥️ Backend / Admin Panel
        ↓
🌐 RouteBox API
        ↓
🔐 AmneziaWG Peer
        ↓
📄 .conf
        ↓
📲 Telegram
```

- 🎁 **Free Trial** with configurable duration
- 👤 **User account** and active service status
- 🔑 **Automatic AWG Peer provisioning** through RouteBox API
- 🌍 **Multi-RouteBox support** — provision the same user across all enabled servers
- 🆔 Consistent Peer identity such as `user123456789`
- ⏱️ **Peer expiration** management
- 📄 Direct **AmneziaWG `.conf`** delivery
- 🛡️ Trial reuse protection
- 🔄 Provisioning rollback when a multi-server operation fails
- 📝 Structured application and provisioning logs

## 🖥️ Independent Admin Panel

The bot is configured and managed from a separate web panel. Telegram does not need to be used for administrative configuration.

### ⚙️ Settings

- 🤖 Telegram Bot Token
- ⏱️ Trial duration
- 🌐 Multiple RouteBox servers
- 🔌 RouteBox connectivity testing
- 🟢 Enable / disable servers
- 👥 User management
- 📋 Provisioning status
- 📝 System logs

### 🔐 Security

- Telegram Bot Tokens and RouteBox credentials are encrypted with **libsodium SecretBox**.
- The application encryption key is generated locally and never committed to GitHub.
- The administrator password is generated during installation.
- Database and runtime configuration are stored outside the public web root.
- HTTPS is strongly recommended for production deployments.
- Never publish tokens, passwords, private keys, or real `.conf` files in issues or pull requests.

---

## 🌍 Multi-Server Architecture

```text
                         📱 Telegram
                              │
                              ▼
                    ┌─────────────────┐
                    │  🤖 Bot Worker  │
                    └────────┬────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │ 🖥️ Admin Panel  │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
        🌐 RouteBox #1  🌐 RouteBox #2  🌐 RouteBox #3
              │              │              │
             AWG            AWG            AWG
```

The backend uses the RouteBox API and does not directly modify RouteBox internal panel files.

## 📦 Quick Installation — Ubuntu 22.04+

On a clean Ubuntu 22.04+ VPS:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

### 🧙 Interactive Setup Wizard

The installer performs an interactive first-run configuration. You do **not** need to edit `.env` files or manually configure PHP files.

#### 🤖 Telegram

The installer asks for:

- Telegram Bot Token

It immediately calls Telegram `getMe` to verify the token before saving it.

#### 🌐 RouteBox

For each RouteBox server, the installer asks only for:

- Server name
- **RouteBox Panel URL** — the exact URL you already use to open the RouteBox panel
- RouteBox username
- RouteBox password
- TLS verification preference when the URL uses HTTPS

Examples:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

You do **not** need to enter RouteBox mode, scheme, host, or port separately.

The installer derives the API base URL from the exact panel URL you provide.

### 🔌 RouteBox API Port

There is **no separate API port** for the bot. The RouteBox REST API is exposed through the same HTTP(S) listener as the RouteBox web panel.

Typical examples are:

| RouteBox deployment | Panel/API URL | Listener |
|---|---|---:|
| VPS | `https://panel.example.com:8443` | `8443/tcp` |
| Router | `http://router-ip:8080` | `8080/tcp` |
| Reverse proxy | `https://panel.example.com` | `443/tcp` |

Enter the URL exactly as it is opened in the browser. The installer derives the API base URL from that value.

### 🔐 RouteBox Authentication

Current RouteBox releases use **cookie-based login sessions** for the web panel and also accept **HTTP Basic authentication for scripts**. The Bot therefore uses the following order:

```text
POST /api/auth/login
        ↓
Session Cookie
        ↓
Protected RouteBox API requests
        ↓
If the session expires → re-login once → retry
        ↓
HTTP Basic fallback when session authentication is unavailable
```

This keeps the Bot compatible with the current RouteBox authentication model without depending on browser behavior. The session cookie is kept in process memory and is not stored in the Bot database.

### 🧪 Installation Smoke Test

Before a RouteBox server is saved, the installer verifies:

```text
GET /api/health
GET /api/status
GET /api/awg/status
GET /api/awg/peers
GET /api/settings
```

It then performs a temporary end-to-end AWG test:

```text
POST   /api/awg/peers
GET    /api/awg/peers/{publicKey}/config
DELETE /api/awg/peers/{publicKey}
```

The temporary peer is deleted immediately. This catches authentication, write permissions, AWG availability, key generation, configuration export, and public-key URL encoding problems during installation instead of after the first real customer request.

### 🔐 What Gets Stored

The Telegram Bot Token and RouteBox username/password are encrypted with the locally generated application key before being written to SQLite. The encryption key itself is stored in the private runtime configuration outside the public web root.

The installer never writes your real credentials into GitHub or the repository.

### Installer Tasks

The installer automatically:

1. 📦 Installs required dependencies.
2. 📥 Downloads the project.
3. 🗄️ Initializes SQLite.
4. 🔐 Generates the application encryption key and administrator password.
5. 🧙 Runs the Telegram + RouteBox setup wizard.
6. 🧪 Tests Telegram and RouteBox authentication before saving credentials.
7. 🧪 Runs a full temporary AWG create/export/delete smoke test.
8. 🌐 Configures Nginx + PHP-FPM.
9. ⚙️ Installs and enables the systemd service.
10. 🧪 Runs PHP syntax validation.
11. ❤️ Checks the Bot service status.

The generated administrator password is displayed by the installer. Store it securely.

### 📋 Management Commands

```bash
journalctl -u routebox-telegram-bot -f
systemctl status routebox-telegram-bot
bash /opt/routebox-telegram-bot/update.sh
bash /opt/routebox-telegram-bot/uninstall.sh
```

> ⚠️ This is a Beta release. Secure the panel with HTTPS, firewall rules, and appropriate access controls before public deployment.

## 🧪 Beta Test Flow

```text
/start
  ↓
🎁 Get Free Trial
  ↓
Check previous Trial
  ↓
🌐 Get enabled RouteBox servers
  ↓
🔎 Find user<TelegramID>
  ↓
➕ Create Peer if missing
  ↓
⏱️ Set expiration
  ↓
📄 Get .conf
  ↓
📲 Send configuration to Telegram
```

For multi-server provisioning, if a later step fails, the system attempts to roll back peers created by the same provisioning operation.

---

## 💳 Payment & Subscription Roadmap

Future updates will add a complete sales and subscription layer, including both **Iranian Rial** and **Crypto** payment options.

### 🇮🇷 Iranian Rial

- 💰 Iranian Rial payment gateway
- 🔄 Subscription renewal and upgrades
- 🎟️ Discount and campaign codes
- 🧾 Invoices and payment history

### 🪙 Crypto

- Cryptocurrency payment gateway
- 🔄 Subscription renewal and upgrades
- 🧾 Payment history
- 🔔 Automated payment status handling

Additional planned features include:

- 🌍 Server / Region selection
- 📊 Usage and service dashboards
- 👨‍💼 Advanced user management
- 🎟️ Coupons and referral system
- 🔔 Expiration notifications
- 🌐 Full Persian / English Bot interface
- 🔗 Subscription links and QR workflow

---

## 🗺️ Roadmap

### v0.1.0-beta.1

- [x] Telegram Bot foundation
- [x] Independent web admin panel
- [x] RouteBox API client
- [x] RouteBox session authentication with Basic fallback
- [x] Multiple RouteBox servers
- [x] Same Telegram user identity across servers
- [x] AWG Peer provisioning
- [x] Expiration
- [x] `.conf` delivery
- [x] Encrypted credentials
- [x] Trial abuse protection
- [x] Partial provisioning rollback
- [x] Interactive installation wizard
- [x] Telegram token validation
- [x] RouteBox API connectivity validation
- [x] Full AWG create/export/delete installation smoke test
- [x] RouteBox Panel URL configuration
- [x] Ubuntu 22.04+ installer
- [x] Update / uninstall scripts
- [x] PHP syntax checks

### 🔜 Future Releases

- [ ] 💳 Iranian Rial payment gateway
- [ ] 🪙 Crypto payment gateway
- [ ] 🔄 Subscription renewal
- [ ] 🛒 Product / plan management
- [ ] 🌍 Region selection
- [ ] 📊 Traffic and usage dashboard
- [ ] 👨‍💼 Advanced user management
- [ ] 🎟️ Coupons and referral system
- [ ] 🔔 Expiration notifications
- [ ] 🌐 Persian / English Bot interface
- [ ] 🔗 Subscription links / QR workflow

---

## 🧩 RouteBox Compatibility

This project is designed around the current RouteBox API architecture. RouteBox documents `/api/status`, `/api/settings`, and `/api/awg/*` alongside its panel listener. Protected endpoints use the panel authentication middleware; current RouteBox releases support cookie sessions and retain HTTP Basic authentication for scripts. The Bot prefers the session flow and keeps Basic as a compatibility fallback.

The Bot also validates the real provisioning path during installation instead of assuming that read-only API access is sufficient.

RouteBox may change its API between releases, so End-to-End testing against the RouteBox version installed on your server is part of the Beta process.

## 🛠️ Development & Contributing

Pull requests and issues are welcome for bug reports, feature requests, and documentation improvements.

When reporting a bug, include where possible:

- Ubuntu version
- RouteBox version
- Project version
- PHP version
- Relevant `journalctl` output

❌ Never include Bot Tokens, passwords, private keys, or real `.conf` files in issues or pull requests.

## ⚠️ Current Status

**This is a Beta release, not the final Production release.** The current goal is to validate the Telegram → Backend → RouteBox → AmneziaWG workflow. Payment and commercial subscription features are planned for future updates.

## 📄 License

The final license is being determined. Review the repository license terms before commercial use or redistribution.
