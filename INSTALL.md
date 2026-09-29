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

To make installation as simple as possible, the wizard asks for the **exact RouteBox Panel URL you already use in your browser**.

Examples:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

You do **not** need to choose:

- VPS / Router mode
- HTTP / HTTPS scheme separately
- API port separately

The URL already contains everything required to reach the RouteBox listener.

RouteBox documents the standard VPS panel as HTTPS on port `8443`. Router-mode installations commonly expose the panel on HTTP port `8080`. Reverse-proxy deployments can expose the same panel through another external port such as `443`. citeturn0search0turn0search1

The Bot therefore treats the RouteBox panel URL as the API base URL.

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

For each RouteBox server, the installer keeps the setup intentionally small:

```text
Server name [RouteBox-1]:
RouteBox Panel URL:
RouteBox username [admin]:
RouteBox password:
Verify TLS certificate? [Y/n]:
```

### Standard RouteBox VPS

If you open RouteBox in your browser at:

```text
https://panel.example.com:8443
```

enter exactly:

```text
RouteBox Panel URL: https://panel.example.com:8443
```

### Router-mode / HTTP example

If your RouteBox panel is opened at:

```text
http://192.0.2.10:8080
```

enter exactly:

```text
RouteBox Panel URL: http://192.0.2.10:8080
```

### Reverse proxy example

If you normally open the panel at:

```text
https://panel.example.com
```

enter exactly that URL.

There is no separate port question because the port is part of the URL when a non-default port is used.

### TLS verification

The installer asks about TLS verification only when the supplied URL uses HTTPS.

For production:

```text
Verify TLS certificate? Y
```

If you are temporarily using a self-signed certificate:

```text
Verify TLS certificate? n
```

Using valid certificates with verification enabled is strongly recommended for production.

---

## 6. RouteBox API Connectivity Test

The installer does not accept a RouteBox server just because the panel URL responds.

It performs an authenticated request to:

```text
<your-panel-url>/api/status
```

For example:

```text
https://panel.example.com:8443/api/status
```

or:

```text
http://192.0.2.10:8080/api/status
```

The flow is:

```text
Bot Server
    │
    │ RouteBox Panel URL
    ▼
RouteBox Panel/API Listener
    │
    │ Authenticated API request
    ▼
/api/status
    │
    ▼
✓ Server accepted
```

RouteBox documents that the REST API is served by the same panel listener. It also documents cookie-based panel sessions and explicitly keeps HTTP Basic authentication available for scripts. This project uses the supported Basic authentication path for scripted API access. citeturn0search0turn0search2

There is therefore **no additional API port to discover or configure**.

If the test fails, check:

- The exact URL you use to open the RouteBox panel
- RouteBox username
- RouteBox password
- Firewall rules
- Reverse proxy configuration
- TLS certificate
- RouteBox API availability

The setup will ask again instead of saving an unverified server.

---

## 7. Multiple RouteBox Servers

You can add multiple RouteBox servers during the same installation.

Example:

```text
RouteBox #1 → https://de.example.com:8443
RouteBox #2 → https://tr.example.com:8443
RouteBox #3 → https://nl.example.com
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

Use the exact same URL that works in your browser.

For example:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:8443/api/status
```

or:

```bash
curl -v http://YOUR_ROUTEBOX_HOST:8080/api/status
```

A reachable URL does not prove that authentication or the API is working. The installer performs an authenticated API test before accepting the server.

### TLS errors

If RouteBox uses a self-signed certificate during testing, disable TLS verification for that server during setup. For production, use a valid certificate and keep TLS verification enabled.

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
