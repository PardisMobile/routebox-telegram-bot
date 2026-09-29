# 📦 RouteBox Telegram Bot — Installation Guide

**Version:** `0.1.0-beta.1`  
**Status:** 🧪 Beta  
**Platform:** Ubuntu 22.04+

This guide explains how to install and configure RouteBox Telegram Bot on a fresh Ubuntu server.

> ⚠️ This is a Beta release. Test it with a non-production RouteBox server before commercial deployment.

---

## 1. Requirements

### Server

- Ubuntu 22.04 or newer
- Root or sudo access
- A public IP or reachable hostname
- Outbound HTTPS access to the Telegram Bot API
- A domain name is recommended for the administration panel

### RouteBox

- A reachable RouteBox installation
- RouteBox web panel/API access enabled
- RouteBox username and password
- The RouteBox panel/API port

For a standard RouteBox VPS installation, the default panel/API listener is normally **HTTPS port `8443`**. Router-mode installations commonly use **HTTP port `8080`**. If RouteBox is behind a reverse proxy, use the externally exposed port, commonly `443`.

The bot does **not** require a separate API port. It connects to the same listener used by the RouteBox panel/API.

---

## 2. Create a Telegram Bot

1. Open Telegram.
2. Start a conversation with **@BotFather**.
3. Run `/newbot`.
4. Choose a name and username for the bot.
5. Copy the Bot Token.

Example format:

```text
123456789:AAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Keep this token private.

---

## 3. Install

On the Ubuntu server, run:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer will:

1. Check Ubuntu 22.04+.
2. Install required packages.
3. Download the repository.
4. Initialize the SQLite database.
5. Generate the private application encryption key.
6. Generate the administrator password.
7. Configure Nginx and PHP-FPM.
8. Install the systemd Bot service.
9. Start the first-run configuration wizard.

---

## 4. Telegram Configuration

The installer asks for:

```text
Telegram Bot Token:
```

The installer immediately calls Telegram's `getMe` endpoint.

A successful result looks like:

```text
✓ Telegram connection successful
✓ Bot: @YourBot
```

If Telegram rejects the token, installation does not continue until a valid token is supplied.

---

## 5. RouteBox Configuration

For each RouteBox server, the installer asks for:

```text
Server name
Mode (vps/router)
Panel/API scheme
Panel/API host
Panel/API port
RouteBox username
RouteBox password
TLS verification
```

### Example — standard VPS installation

```text
Server name: Germany
Mode: vps
Scheme: https
Host: de.example.com
Port: 8443
Username: admin
Password: ********
Verify TLS: Y
```

### Example — reverse proxy

If RouteBox is exposed through HTTPS on port 443:

```text
Scheme: https
Host: panel.example.com
Port: 443
```

### Important

Do **not** enter the port twice.

Correct:

```text
Host: de.example.com
Port: 8443
```

Not:

```text
Host: de.example.com:8443
Port: 8443
```

The installer constructs the API base URL automatically.

---

## 6. RouteBox Connectivity Test

The installer must verify the RouteBox server before saving it.

The intended authentication flow is compatible with the RouteBox API authentication model:

```text
Bot
 │
 │ POST /api/auth/login
 ▼
RouteBox
 │
 │ Session
 ▼
Bot
 │
 │ Authenticated API request
 ▼
RouteBox API
```

The installer should not mark a server as configured unless authentication and an authenticated API request succeed.

If the connection fails, check:

- Hostname/IP
- Port
- HTTP vs HTTPS
- RouteBox username
- RouteBox password
- Firewall rules
- Reverse proxy configuration
- TLS certificate
- RouteBox API availability

---

## 7. Multiple RouteBox Servers

The installer supports adding more than one RouteBox server.

Example:

```text
RouteBox #1 → Germany
RouteBox #2 → Turkey
RouteBox #3 → Netherlands
```

When multi-server provisioning is enabled, the same Telegram user identity can be provisioned across all enabled RouteBox servers.

Example peer identity:

```text
user123456789
```

This makes it possible to manage one Telegram customer across multiple RouteBox locations.

---

## 8. Administrator Panel

At the end of installation, the installer displays the generated administrator password.

Example:

```text
Admin username: admin
Admin password: <generated-password>
```

Save this password securely.

The administration panel is served through Nginx. For production use, place it behind HTTPS and restrict administrative access where appropriate.

---

## 9. Service Management

Check the Bot service:

```bash
systemctl status routebox-telegram-bot
```

Follow live logs:

```bash
journalctl -u routebox-telegram-bot -f
```

Restart:

```bash
systemctl restart routebox-telegram-bot
```

Stop:

```bash
systemctl stop routebox-telegram-bot
```

Start:

```bash
systemctl start routebox-telegram-bot
```

---

## 10. Updating

Run:

```bash
bash /opt/routebox-telegram-bot/update.sh
```

The update script should preserve runtime configuration and the SQLite database.

> ⚠️ Always back up the database before major Beta upgrades.

---

## 11. Uninstalling

Run:

```bash
bash /opt/routebox-telegram-bot/uninstall.sh
```

Review the uninstall script before production use if you need to preserve local data.

---

## 12. Troubleshooting

### Telegram connection failed

Test outbound HTTPS:

```bash
curl -I https://api.telegram.org
```

Verify the Bot Token with BotFather.

### RouteBox connection failed

Test the panel port from the Bot server:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:8443/
```

For a reverse proxy on 443:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:443/
```

For router mode:

```bash
curl -v http://YOUR_ROUTEBOX_HOST:8080/
```

A reachable TCP port alone does not prove API authentication works. The installer must perform an authenticated API test before accepting the RouteBox configuration.

### Check RouteBox logs

Use the RouteBox administration/logging facilities on the RouteBox server and verify that the Bot server's IP is allowed to reach the panel/API listener.

---

## 13. Security Recommendations

- 🔐 Never publish Telegram Bot Tokens.
- 🔐 Never publish RouteBox passwords.
- 🔐 Never publish private keys or real `.conf` files.
- 🌐 Use HTTPS for the admin panel.
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
- 🖥️ Independent admin panel
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

This project is designed to integrate with the RouteBox API and should be tested against the exact RouteBox version installed on your server.

RouteBox API behavior can change between releases. If a RouteBox update changes authentication, endpoint paths, response formats, or required parameters, the Bot integration may require an update.

For support, include:

```text
Ubuntu version
RouteBox version
RouteBox Telegram Bot version
PHP version
Relevant sanitized logs
```

Never include credentials or private configuration data.
