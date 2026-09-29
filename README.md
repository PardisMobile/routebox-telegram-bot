# 🚀 RouteBox Telegram Bot

> 🤖 Telegram Bot + 🖥️ Independent Web Admin Panel for RouteBox / AmneziaWG
>
> **Current version: see [`VERSION`](./VERSION) · Status: 🧪 Beta**
>
> **Version source of truth:** [`VERSION`](./VERSION). The README intentionally does not hard-code a beta number, so documentation cannot become stale after a release.

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
