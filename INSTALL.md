# 📦 RouteBox Telegram Bot — Installation Guide

**Version:** `0.1.0-beta.1`  
**Status:** 🧪 Beta  
**Platform:** Ubuntu 22.04+

This guide explains how to install and configure RouteBox Telegram Bot on Ubuntu 22.04+.

> ⚠️ This is a Beta release. Test it with your RouteBox installation before production or commercial deployment.

---

## 1. Requirements

### Bot server

- Ubuntu 22.04 or newer
- Root or sudo access
- Internet access for Telegram API and GitHub
- PHP 8+
- A public IP or reachable hostname

### RouteBox

The Bot connects to the **same web-panel/API listener used by RouteBox**. There is **no separate API port** for the Bot.

The installer asks for the exact URL you already use to open the RouteBox panel:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

You do not need to enter RouteBox mode, scheme, host, or port separately.

RouteBox documents the standard VPS panel on HTTPS `8443`, router-mode panel access commonly on HTTP `8080`, and reverse-proxy deployments on the externally exposed URL/port. citeturn0search0turn0search1

---

## 2. Create a Telegram Bot

1. Open Telegram.
2. Start **@BotFather**.
3. Run `/newbot`.
4. Choose the bot name and username.
5. Copy the Bot Token.

Keep the token private.

---

## 3. Install

Run on the Ubuntu server:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer automatically:

1. Checks Ubuntu 22.04+.
2. Installs dependencies.
3. Downloads the project.
4. Initializes SQLite.
5. Generates the application encryption key.
6. Generates the admin password.
7. Starts the interactive configuration wizard.
8. Tests Telegram and RouteBox before saving credentials.
9. Configures Nginx/PHP-FPM.
10. Installs and enables the systemd service.

---

## 4. Telegram Setup

The wizard asks for:

```text
Telegram Bot Token:
```

It validates the token using Telegram `getMe` before continuing.

Expected result:

```text
✓ Telegram connection successful: @YourBot
```

---

## 5. RouteBox Setup

For each server:

```text
Server name [RouteBox-1]:
RouteBox Panel URL:
RouteBox username [admin]:
RouteBox password:
Verify TLS certificate? [Y/n]:
```

### VPS example

If the panel opens at:

```text
https://panel.example.com:8443
```

enter:

```text
https://panel.example.com:8443
```

### Router/HTTP example

If the panel opens at:

```text
http://192.0.2.10:8080
```

enter:

```text
http://192.0.2.10:8080
```

### Reverse proxy example

If the panel opens at:

```text
https://panel.example.com
```

enter exactly that URL.

The installer derives the protocol and port from the URL. There is no separate scheme or port prompt.

TLS verification is only requested for HTTPS URLs.

---

## 6. RouteBox API Validation

The installer performs **three read-only API tests** before saving a RouteBox server:

```text
GET /api/status
GET /api/awg/status
GET /api/awg/peers
```

For example:

```text
https://panel.example.com:8443/api/status
https://panel.example.com:8443/api/awg/status
https://panel.example.com:8443/api/awg/peers
```

This verifies not only that the panel is reachable, but that the Bot server can access the **AmneziaWG API required by this project**.

Expected result:

```text
✓ RouteBox status API OK
✓ AmneziaWG API OK
✓ AmneziaWG peers API OK
✓ RouteBox API and AmneziaWG endpoints are reachable and authenticated
```

If any required endpoint fails, the server is not saved and the wizard asks for the information again.

RouteBox documents `/api/awg/*` for AmneziaWG status, peers and configuration, and documents HTTP Basic authentication as supported for scripts. citeturn0search0turn0search2

There is therefore **no second API port** to configure.

---

## 7. Multiple RouteBox Servers

Add as many enabled servers as required during installation:

```text
RouteBox #1 → https://de.example.com:8443
RouteBox #2 → https://tr.example.com:8443
RouteBox #3 → https://nl.example.com
```

The same Telegram customer identity can then be provisioned across all enabled RouteBox servers.

Example:

```text
user123456789
```

---

## 8. Authentication

RouteBox uses protected API endpoints when authentication is enabled. The Bot stores the supplied RouteBox username and password encrypted with **libsodium SecretBox**.

RouteBox documents cookie-based panel sessions and also explicitly keeps HTTP Basic authentication available for scripts. This project uses the supported Basic authentication path for its server-to-server API calls. citeturn0search2

The Bot does not need to imitate the browser UI or manipulate RouteBox internal files.

---

## 9. Administrator Panel

The installer generates a random admin password and displays it during the first installation:

```text
Admin username: admin
Admin password: <generated-password>
```

Save it securely.

For production, protect the Bot administration panel with HTTPS and firewall/access controls.

---

## 10. Service Management

```bash
systemctl status routebox-telegram-bot
```

Live logs:

```bash
journalctl -u routebox-telegram-bot -f
```

Restart:

```bash
systemctl restart routebox-telegram-bot
```

---

## 11. Updating

```bash
bash /opt/routebox-telegram-bot/update.sh
```

Back up the SQLite database before major Beta upgrades.

---

## 12. Uninstalling

```bash
bash /opt/routebox-telegram-bot/uninstall.sh
```

Review the uninstall behavior if you need to preserve local data.

---

## 13. Troubleshooting

### Telegram connection failed

```bash
curl -I https://api.telegram.org
```

Verify the Bot Token with @BotFather.

### RouteBox connection failed

Use the exact URL that works in your browser.

For example:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:8443/api/status
```

or:

```bash
curl -v http://YOUR_ROUTEBOX_HOST:8080/api/status
```

A reachable URL does not prove that authentication or the required AmneziaWG API is available. The installer checks all three required endpoints.

### TLS errors

For a self-signed certificate during testing, TLS verification can be disabled for that server. For production, use a valid certificate and keep verification enabled.

### AmneziaWG API unavailable

If `/api/awg/status` or `/api/awg/peers` fails, verify that the RouteBox installation/version you are using exposes the AmneziaWG server API and that the required AmneziaWG server functionality is enabled.

---

## 14. Security Recommendations

- 🔐 Never publish Telegram Bot Tokens.
- 🔐 Never publish RouteBox passwords.
- 🔐 Never publish private keys or real `.conf` files.
- 🌐 Use HTTPS for the administration panel.
- 🔥 Restrict the administration panel with firewall rules where possible.
- 🧱 Do not expose SQLite or runtime configuration through Nginx.
- 🔄 Keep Ubuntu and RouteBox updated.
- 📝 Remove secrets from logs before sharing GitHub Issues.

---

## 15. Beta Scope

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

## 16. Important Beta Note

This project is designed around the RouteBox API and should be tested against the exact RouteBox version installed on your server.

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
