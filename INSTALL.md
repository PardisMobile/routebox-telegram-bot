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

For the current RouteBox source, the standard VPS installation exposes the panel on HTTPS `8443`, while the router installation uses `8080` by default. Reverse-proxy or all-in-one deployments may expose the panel through another public URL such as `443`. The Bot follows the exact panel URL you provide.

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

The installer performs these read-only API tests before saving a RouteBox server:

```text
GET /api/health
GET /api/status
GET /api/awg/status
GET /api/awg/peers
GET /api/settings
```

The first endpoint is RouteBox's public health endpoint. The remaining endpoints verify authenticated access to the application and AmneziaWG APIs used by the Bot.

For example, with a VPS panel on `8443`:

```text
https://panel.example.com:8443/api/status
https://panel.example.com:8443/api/awg/status
https://panel.example.com:8443/api/awg/peers
```

This validates the **same listener and authentication mechanism** that the Bot will use later. There is no second API port.

RouteBox's current backend registers the `/api/awg/*` routes under the main `/api` router, and the protected API group is guarded by the same authentication middleware. RouteBox accepts HTTP Basic authentication for scripts, so this Bot uses Basic authentication for server-to-server requests rather than imitating the browser session.

Expected result:

```text
✓ RouteBox health API OK
✓ RouteBox status API OK
✓ AmneziaWG API OK
✓ AmneziaWG peers API OK
✓ RouteBox settings API OK
✓ RouteBox integration verified
```

If any required endpoint fails, the server is not saved and the wizard asks for the information again.

---

## 7. Important: AmneziaWG Client Address

RouteBox's peer configuration endpoint generates the client `.conf` using the AWG server's configured client-facing address.

RouteBox resolves this as:

```text
AWG server_host
        ↓ if empty
server public_host
```

Therefore, the RouteBox server should have either:

- `AWG → Server address` configured, or
- `App Settings → Server → Public host` configured.

The Bot does **not** need a separate client VPN port for its API connection. The AWG listen port is a VPN-service setting, not the RouteBox API port.

The Bot simply asks RouteBox for the generated peer configuration through:

```text
GET /api/awg/peers/{publicKey}/config
```

RouteBox itself renders the correct client configuration.

---

## 8. Multiple RouteBox Servers

Add as many enabled servers as required during installation:

```text
RouteBox #1 → https://de.example.com:8443
RouteBox #2 → https://tr.example.com:8443
RouteBox #3 → https://nl.example.com
```

The same Telegram customer identity can then be provisioned across all enabled RouteBox servers.

Example logical peer name:

```text
user123456789
```

Each RouteBox creates its own cryptographic keypair/public key. The logical customer identity remains the same across the servers.

---

## 9. Authentication

When RouteBox authentication is enabled, protected API endpoints require authentication.

The Bot stores the supplied RouteBox username and password encrypted with **libsodium SecretBox**.

RouteBox currently supports both:

- Cookie-based sessions for the web panel
- HTTP Basic authentication for scripts

The Bot uses **HTTP Basic authentication** for its server-to-server API calls because this is explicitly supported by RouteBox and avoids coupling the Bot to browser-only session behavior.

The Bot does not modify RouteBox files, SQLite databases, `peers.toml`, or WireGuard configuration directly.

---

## 10. Bot Provisioning Flow

When a Telegram customer requests a trial, the Bot performs this flow for every enabled RouteBox server:

```text
Telegram user
     │
     ▼
Bot database
     │
     ├── RouteBox #1
     │      ├── GET peers
     │      ├── POST peer
     │      ├── PATCH expiry
     │      └── GET .conf
     │
     ├── RouteBox #2
     │      ├── GET peers
     │      ├── POST peer
     │      ├── PATCH expiry
     │      └── GET .conf
     │
     └── RouteBox #N
```

If a newly-created peer cannot be completed, the Bot attempts to delete peers that it created during that provisioning attempt so a partial trial is not left behind.

---

## 11. RouteBox API Endpoints Used by the Bot

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/health` | Connectivity/health check |
| `GET` | `/api/status` | RouteBox process status |
| `GET` | `/api/settings` | Read RouteBox settings needed for integration validation |
| `GET` | `/api/awg/status` | AmneziaWG server status |
| `GET` | `/api/awg/peers` | List existing peers |
| `POST` | `/api/awg/peers` | Create a peer |
| `PATCH` | `/api/awg/peers/{publicKey}/expiry` | Set expiry/quota |
| `GET` | `/api/awg/peers/{publicKey}/config` | Get client `.conf` |
| `GET` | `/api/awg/peers/{publicKey}/vpn-link` | Get Amnezia `vpn://` link |
| `GET` | `/api/awg/peers/{publicKey}/singbox` | Get sing-box peer export |
| `POST` | `/api/awg/peers/{publicKey}/traffic/reset` | Reset peer traffic counters |
| `DELETE` | `/api/awg/peers/{publicKey}` | Delete peer |
| `POST` | `/api/awg/enable` | Enable AWG server (admin operation) |
| `POST` | `/api/awg/disable` | Disable AWG server (admin operation) |

The current Bot provisioning path primarily uses peer listing, peer creation, expiry and configuration export.

---

## 12. Administrator Panel

The installer generates a random admin password and displays it during the first installation:

```text
Admin username: admin
Admin password: <generated-password>
```

Save it securely.

For production, protect the Bot administration panel with HTTPS and firewall/access controls.

---

## 13. Service Management

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

## 14. Updating

```bash
bash /opt/routebox-telegram-bot/update.sh
```

Back up the SQLite database before major Beta upgrades.

---

## 15. Uninstalling

```bash
bash /opt/routebox-telegram-bot/uninstall.sh
```

Review the uninstall behavior if you need to preserve local data.

---

## 16. Troubleshooting

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

A reachable URL does not prove that authentication or the required AmneziaWG API is available. The installer checks the RouteBox API endpoints before saving the server.

### TLS errors

For a self-signed certificate during testing, TLS verification can be disabled for that server. For production, use a valid certificate and keep verification enabled.

### AmneziaWG API unavailable

If `/api/awg/status` or `/api/awg/peers` fails, verify that the RouteBox installation exposes the AmneziaWG server API and that the RouteBox version supports the `/api/awg/*` endpoints.

### Client `.conf` cannot be generated

Check RouteBox:

1. Open **Config → AmneziaWG**.
2. Confirm the AWG server is configured.
3. Set the client-facing **Server address**.
4. If that is empty, configure **Server → Public host** in App Settings.

The Bot relies on RouteBox to render the client configuration and does not generate private keys or `.conf` files itself.

---

## 17. Security Recommendations

- 🔐 Never publish Telegram Bot Tokens.
- 🔐 Never publish RouteBox passwords.
- 🔐 Never publish private keys or real `.conf` files.
- 🌐 Use HTTPS for the administration panel.
- 🔥 Restrict the administration panel with firewall rules where possible.
- 🧱 Do not expose SQLite or runtime configuration through Nginx.
- 🔄 Keep Ubuntu and RouteBox updated.
- 📝 Remove secrets from logs before sharing GitHub Issues.

---

## 18. Beta Scope

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

## 19. Important Beta Note

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
