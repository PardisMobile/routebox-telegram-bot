# 📦 RouteBox Telegram Bot — Installation Guide

**Version:** `0.1.0-beta.1`  
**Status:** 🧪 Beta  
**Platform:** Ubuntu 22.04+

This guide explains how to install and configure RouteBox Telegram Bot on a fresh Ubuntu server.

> ⚠️ This is a Beta release. Test it with your RouteBox installation before production or commercial deployment.

---

## 1. Requirements

### Bot server

- Ubuntu 22.04 or newer
- Root or sudo access
- Internet access for Telegram API and GitHub
- PHP 8+
- A public IP or reachable hostname
- A domain is recommended for the administration panel

### RouteBox

The Bot connects to the **same web-panel/API listener used by RouteBox**. There is **no separate Bot API port**.

To keep installation simple, the wizard only asks for:

```text
Server name
Panel/API host
Panel/API port
Username
Password
TLS verification preference
```

For the standard RouteBox VPS panel, the documented default is **HTTPS on port `8443`**. RouteBox can also be exposed through a reverse proxy, commonly on **HTTPS `443`**. citeturn0search0

The installer therefore assumes **HTTPS** and does not ask for a scheme or a `VPS/Router` mode. This Bot is designed to manage RouteBox server APIs rather than configure a RouteBox home router.

---

## 2. Create a Telegram Bot

1. Open Telegram.
2. Start **@BotFather**.
3. Run `/newbot`.
4. Choose the bot name and username.
5. Copy the Bot Token.

Example:

```text
123456789:AAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Keep the token private.

---

## 3. Install

On the Ubuntu server run:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer will:

1. Check Ubuntu 22.04+.
2. Install required dependencies.
3. Download the latest project files.
4. Initialize SQLite.
5. Generate the private application encryption key.
6. Generate the administrator password.
7. Configure Nginx and PHP-FPM.
8. Install and enable the systemd Bot service.
9. Start the interactive configuration wizard.

---

## 4. Telegram Configuration

The installer asks for:

```text
Telegram Bot Token:
```

It immediately validates the token with Telegram's `getMe` API.

A successful result looks like:

```text
✓ Telegram connection successful: @YourBot
```

Invalid tokens are rejected before the setup continues.

---

## 5. RouteBox Configuration

For each RouteBox server, the installer asks only for the information actually needed:

```text
Server name
Panel/API host
Panel/API port [8443]
RouteBox username [admin]
RouteBox password
Verify TLS certificate? [Y/n]
```

### Standard RouteBox VPS example

```text
Server name: Germany
Panel/API host: de.example.com
Panel/API port [8443]: 8443
RouteBox username [admin]: admin
RouteBox password: ********
Verify TLS certificate? [Y/n]: Y
```

The resulting API base URL is:

```text
https://de.example.com:8443
```

### Reverse proxy example

If RouteBox is exposed through HTTPS on port 443:

```text
Panel/API host: panel.example.com
Panel/API port [8443]: 443
```

The resulting API base URL is:

```text
https://panel.example.com:443
```

### Important

Enter the host and port separately:

```text
Host: de.example.com
Port: 8443
```

Do **not** enter:

```text
Host: https://de.example.com:8443
Port: 8443
```

The installer constructs the HTTPS API base URL automatically.

---

## 6. RouteBox Connectivity Test

The installer does not accept a RouteBox server just because the TCP port is reachable.

It performs an authenticated API request before saving the server configuration.

The flow is:

```text
Bot Server
    │
    │ HTTPS
    ▼
RouteBox Panel/API Listener
    │
    │ Authenticated API request
    ▼
API Response
    │
    ▼
✓ Server accepted
```

RouteBox documents cookie-based login sessions for the panel and also explicitly keeps HTTP Basic authentication available for scripts. The Bot uses the supported Basic authentication path for scripted API access. citeturn0search1

If the test fails, check:

- Hostname/IP
- Port
- RouteBox username
- RouteBox password
- Firewall rules
- Reverse proxy configuration
- TLS certificate
- RouteBox API availability

The setup will ask you to correct the information instead of saving an unverified server.

---

## 7. Multiple RouteBox Servers

You can add multiple RouteBox servers during the same installation.

Example:

```text
RouteBox #1 → Germany
RouteBox #2 → Turkey
RouteBox #3 → Netherlands
```

When multi-server provisioning is enabled, the same Telegram customer identity can be provisioned across all enabled RouteBox servers.

Example peer name:

```text
user123456789
```

---

## 8. Administrator Panel

During first installation the setup wizard generates a random administrator password and displays it once.

```text
Admin username: admin
Admin password: <generated-password>
```

Save it securely.

The panel is served by Nginx. For production deployments, put it behind HTTPS and restrict access where appropriate.

---

## 9. Service Management

Check the Bot service:

```bash
systemctl status routebox-telegram-bot
```

View live logs:

```bash
journalctl -u routebox-telegram-bot -f
```

Restart:

```bash
systemctl restart routebox-telegram-bot
```

---

## 10. Updating

```bash
bash /opt/routebox-telegram-bot/update.sh
```

Runtime configuration and the SQLite database should be preserved during updates.

> ⚠️ Back up the database before major Beta upgrades.

---

## 11. Uninstalling

```bash
bash /opt/routebox-telegram-bot/uninstall.sh
```

Review the uninstall behavior before using it on a production system if you need to preserve local data.

---

## 12. Troubleshooting

### Telegram connection failed

```bash
curl -I https://api.telegram.org
```

Verify the Bot Token with @BotFather.

### RouteBox connection failed

For the standard RouteBox VPS panel:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:8443/
```

For a reverse proxy on 443:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:443/
```

A reachable port does not prove that authentication or the API is working. The installer performs an authenticated API test before accepting the server.

### TLS errors

If your RouteBox uses a self-signed certificate during testing, the installer can disable TLS certificate verification for that server. For production, a valid certificate and TLS verification are strongly recommended.

---

## 13. Security Recommendations

- 🔐 Never publish Telegram Bot Tokens.
- 🔐 Never publish RouteBox passwords.
- 🔐 Never publish private keys or real `.conf` files.
- 🌐 Use HTTPS for the administration panel.
- 🔥 Restrict the admin panel with firewall rules where possible.
- 🧱 Do not expose SQLite or runtime configuration through Nginx.
- 🔄 Keep Ubuntu and RouteBox updated.
- 📝 Remove secrets from logs before sharing them in GitHub Issues.

---

## 14. Beta Scope

Current Beta functionality focuses on:

- 🤖 Telegram Bot
- 🎁 Free Trial
- 👤 User management foundation
- 🔑 AmneziaWG peer provisioning
- 🌍 Multi-RouteBox provisioning
- ⏱️ Expiration
- 📄 Configuration delivery
- 🖥️ Independent administration panel
- 🔐 Encrypted credentials
- 📝 Logging

### Planned Updates

Future releases will add:

- 💰 Iranian Rial payment gateway
- 🪙 Cryptocurrency payment gateway
- 🔄 Subscription renewal and upgrades
- 🧾 Invoices and payment history
- 🎟️ Coupons and referral system
- 🌍 Server/Region selection
- 📊 Usage and traffic dashboards
- 🔔 Expiration notifications
- 🌐 Persian and English Bot interface

---

## 15. Important Beta Note

The project is designed around the RouteBox API and should be tested against the exact RouteBox version installed on your server.

RouteBox API behavior can change between releases. If authentication, endpoint paths, response formats, or required parameters change, the Bot integration may require an update.

For support, include:

```text
Ubuntu version
RouteBox version
RouteBox Telegram Bot version
PHP version
Relevant sanitized logs
```

Never include credentials or private configuration data.
