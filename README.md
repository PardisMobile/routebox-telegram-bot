# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Web Management Panel for RouteBox

![Status](https://img.shields.io/badge/status-BETA-orange?style=for-the-badge)
![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04%2B-E95420?style=for-the-badge&logo=ubuntu&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-Bot-26A5E4?style=for-the-badge&logo=telegram&logoColor=white)

## ⚠️ Beta

**RouteBox Telegram Bot is currently in Beta.** This release focuses on validating the complete Telegram → Backend → RouteBox API → AmneziaWG workflow before production features are enabled.

> 🧪 Test on your own RouteBox installation before production use.

## ✨ Features

### 🤖 Telegram Bot
- `/start` and help
- 🎁 Trial service creation
- 👤 User account status
- 📄 AmneziaWG `.conf` delivery
- ⏱️ Trial expiration
- 🔑 Peer provisioning through RouteBox API

### 🖥️ Independent Web Panel
- 🔐 Configure Telegram Bot Token
- ⏱️ Configure trial duration
- 🌐 Add multiple RouteBox servers
- 🔌 Test RouteBox connectivity
- ⭐ Select default server
- 🟢 Enable/disable servers
- 👥 View Telegram users
- 🔑 View created peers
- 📝 Operation logs

### 🌍 Multi-RouteBox Ready

```text
                         Telegram
                            │
                            ▼
                    ┌───────────────┐
                    │ Telegram Bot  │
                    └───────┬───────┘
                            │
                            ▼
                    ┌───────────────┐
                    │  Bot Backend  │
                    │  Web Panel    │
                    └───────┬───────┘
                            │
              ┌─────────────┼─────────────┐
              ▼             ▼             ▼
         RouteBox #1   RouteBox #2   RouteBox #3
              │             │             │
             AWG           AWG           AWG
```

The Bot system communicates with RouteBox through its API and does not modify RouteBox's internal configuration files.

## 🛡️ Security

- 🔒 Bot credentials are encrypted before storage.
- 🔒 RouteBox credentials are encrypted before storage.
- 🔑 Secrets are kept outside source code.
- 🚫 RouteBox internal files are not directly modified.
- 🌐 HTTPS is strongly recommended for the management panel.

## 📦 Ubuntu 22.04+

The target installation platform is Ubuntu 22.04 and newer.

Planned one-command installer:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

> 🚧 The installer is being finalized and tested as part of the Beta release.

### Requirements

- Ubuntu 22.04+
- PHP 8.2+
- cURL
- SQLite
- OpenSSL
- Nginx
- systemd
- root/sudo access

## 🧪 Beta Test Flow

```text
Telegram /start
      ↓
🎁 Get Trial
      ↓
Bot Backend
      ↓
RouteBox API
      ↓
Create AWG Peer
      ↓
Set Expiration
      ↓
Get Configuration
      ↓
Telegram
      ↓
📄 AmneziaWG .conf
```

## 🗺️ Roadmap

- [x] Initial RouteBox API client
- [x] Independent management panel
- [x] Basic Telegram Bot
- [x] AWG peer creation
- [x] Configuration delivery
- [x] RouteBox server management
- [x] Basic trial/expiration
- [ ] Production Ubuntu installer
- [ ] Service renewal
- [ ] Payment gateway
- [ ] Multi-server provisioning
- [ ] Central subscription system
- [ ] Traffic analytics
- [ ] Advanced user management

## 🤝 Contributing

Issues and pull requests are welcome. During Beta, please include your RouteBox version, Ubuntu version, and relevant logs when reporting a problem. Never include Bot Tokens, passwords, private keys, or other secrets in an issue.

## 📄 License

License terms are being finalized for the Beta release. Review the repository before commercial redistribution.
