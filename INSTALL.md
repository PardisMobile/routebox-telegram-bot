# 📦 RouteBox Telegram Bot — Installation Guide

**Version:** `0.1.0-beta.1`  
**Status:** 🧪 Beta  
**Platform:** Ubuntu 22.04+

This guide installs the Bot and verifies the complete Telegram → RouteBox → AmneziaWG path before a RouteBox server is accepted.

> ⚠️ Beta software. Test it on your own RouteBox installation before production or commercial use.

---

## 1. Requirements

### Bot server

- Ubuntu 22.04 or newer
- Root or sudo access
- Internet access to Telegram and GitHub
- PHP 8+
- A reachable hostname or public IP

The installer installs Nginx, PHP-FPM, SQLite and the required PHP extensions automatically.

### RouteBox

The Bot connects to the **same HTTP(S) listener used by the RouteBox web panel**. There is no separate Bot/API port.

Enter the exact URL that opens the RouteBox panel, for example:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

The installer does not ask for RouteBox mode, scheme, host and port separately.

RouteBox's upstream documentation describes `8443` as the usual VPS panel listener and `8080` as the usual router listener. A reverse proxy may expose the panel on `443`. These are deployment details; the Bot only needs the actual Panel URL.

---

## 2. Create the Telegram Bot

1. Open Telegram.
2. Open **@BotFather**.
3. Run `/newbot`.
4. Choose a name and username.
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
3. Downloads the current repository.
4. Initializes SQLite.
5. Generates the application encryption key.
6. Generates the admin password.
7. Starts the interactive setup wizard.
8. Validates Telegram.
9. Validates every RouteBox server before saving it.
10. Configures Nginx/PHP-FPM.
11. Installs and enables the systemd worker.
12. Runs final syntax and service checks.

The generated administrator password is printed once. Save it securely.

---

## 4. Telegram Setup

The wizard asks:

```text
Telegram Bot Token:
```

It calls Telegram `getMe` before storing the token.

It also removes an existing Telegram webhook so the Bot's long-polling worker does not compete with a webhook.

Expected result:

```text
✓ Telegram connection successful: @YourBot
```

---

## 5. RouteBox Setup

For each RouteBox server the wizard asks:

```text
Server name [RouteBox-1]:
RouteBox Panel URL:
RouteBox username [admin]:
RouteBox password:
Verify TLS certificate? [Y/n]:
```

Username and password may be empty when RouteBox authentication is disabled. If authentication is enabled, provide the same credentials used to enter the RouteBox panel.

### VPS example

If the panel opens at:

```text
https://panel.example.com:8443
```

enter that exact URL.

### Router example

If the panel opens at:

```text
http://192.0.2.10:8080
```

enter that exact URL.

### Reverse proxy example

If the panel opens at:

```text
https://panel.example.com
```

enter that exact URL.

There is no separate API port.

For HTTPS, TLS verification should remain enabled when the certificate is valid. Disabling verification is intended for controlled testing with self-signed certificates.

---

## 6. RouteBox Authentication

The Bot follows the current RouteBox session API:

```text
POST /api/auth/login
        ↓
Set-Cookie: RouteBox session
        ↓
Protected API requests using the session cookie
        ↓
401 → re-login once → retry
        ↓
HTTP Basic fallback when session authentication is unavailable
```

The session cookie is kept only in the running PHP process. It is not written to the Bot database.

RouteBox also supports HTTP Basic authentication for scripts, so the Bot keeps that as a compatibility fallback.

---

## 7. RouteBox Validation — No Guessing

The installer does not consider an open TCP port or a successful `/api/status` response sufficient.

Before saving a RouteBox server, it performs:

```text
GET /api/health
GET /api/status
GET /api/awg/status
GET /api/awg/peers
GET /api/settings
```

Then it performs a real temporary AWG operation:

```text
POST   /api/awg/peers
GET    /api/awg/peers/{publicKey}/config
DELETE /api/awg/peers/{publicKey}
```

The temporary peer has a unique `rbt-install-test-*` name.

### What this catches

- RouteBox connectivity
- Authentication / authorization
- Protected API access
- AmneziaWG API availability
- Peer creation permissions
- RouteBox key generation
- Client configuration rendering
- Public-key URL encoding
- Peer deletion / cleanup

The server is saved only after the complete test succeeds.

If cleanup itself fails, the installer reports that failure instead of silently claiming success.

---

## 8. AmneziaWG Client Address

The Bot does not generate AWG keys or client configuration files itself.

It requests the generated configuration from RouteBox:

```text
GET /api/awg/peers/{publicKey}/config
```

RouteBox uses its configured client-facing server address when rendering the `.conf`.

Therefore, before using the Bot, configure one of these in RouteBox:

- **AmneziaWG → Server address**, or
- **App Settings → Server → Public host**

If the RouteBox configuration has no usable public/client address, the Bot's installation smoke test should fail at the configuration-export step. Fix the RouteBox address and run the test again.

The AWG UDP listen port is separate from the RouteBox HTTP/API listener. The Bot does not connect to the AWG UDP port for API operations.

---

## 9. Multiple RouteBox Servers

You can add multiple servers during installation:

```text
RouteBox #1 → https://de.example.com:8443
RouteBox #2 → https://tr.example.com:8443
RouteBox #3 → https://panel.example.com
```

For a Telegram user ID `123456789`, the logical peer name is:

```text
user123456789
```

Each RouteBox creates its own cryptographic keypair and public key.

During a real multi-server provisioning operation, if a later server fails, the Bot attempts to delete peers created earlier in that same operation.

---

## 10. Admin Panel

The installer creates a separate Bot administration panel.

The first-run credentials are:

```text
Username: admin
Password: <generated during installation>
```

The panel lets you configure:

- Telegram Bot Token
- Trial duration
- RouteBox servers
- Enable / disable servers
- Full RouteBox integration tests
- Recent Telegram users

### Adding a RouteBox from the panel

The Admin Panel uses the **same validation path as the installer**.

A RouteBox is not saved until the read-only API checks and temporary AWG create → config → delete smoke test pass.

This prevents a server with a reachable panel but a broken AWG API from being silently added.

### Admin security

Admin POST actions use CSRF protection. Use HTTPS and restrict the panel with firewall or network access controls before public deployment.

---

## 11. API Endpoints Used

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/auth/login` | Create RouteBox session |
| `GET` | `/api/auth/session` | Session compatibility endpoint |
| `POST` | `/api/auth/logout` | End a session |
| `GET` | `/api/health` | Health check |
| `GET` | `/api/status` | RouteBox process status |
| `GET` | `/api/settings` | Integration validation |
| `GET` | `/api/awg/status` | AWG server status |
| `GET` | `/api/awg/peers` | List peers |
| `POST` | `/api/awg/peers` | Create peer |
| `PATCH` | `/api/awg/peers/{publicKey}/expiry` | Set expiry / quota |
| `GET` | `/api/awg/peers/{publicKey}/config` | Get `.conf` |
| `GET` | `/api/awg/peers/{publicKey}/vpn-link` | Get `vpn://` link |
| `GET` | `/api/awg/peers/{publicKey}/singbox` | Get sing-box export |
| `POST` | `/api/awg/peers/{publicKey}/traffic/reset` | Reset traffic |
| `DELETE` | `/api/awg/peers/{publicKey}` | Delete peer |

The current Bot provisioning path primarily uses peer list, create, expiry, config export and delete.

---

## 12. Service Management

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

## 13. Updating

```bash
bash /opt/routebox-telegram-bot/update.sh
```

Back up the SQLite database before major Beta updates.

The update script validates PHP and shell syntax and checks that the systemd service starts after the update.

---

## 14. Uninstalling

```bash
bash /opt/routebox-telegram-bot/uninstall.sh
```

The uninstall script removes the Bot application, systemd service and Nginx site. It intentionally does not remove system packages such as PHP, Nginx or SQLite.

---

## 15. Troubleshooting

### Telegram connection failed

Check basic outbound connectivity:

```bash
curl -I https://api.telegram.org
```

Then verify the Bot Token with @BotFather.

### RouteBox URL cannot be reached

Use the exact URL that opens the RouteBox panel in your browser.

Examples:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:8443/api/status
curl -v http://YOUR_ROUTEBOX_HOST:8080/api/status
```

A successful TCP connection does **not** prove that authentication or AWG operations work. The Bot's smoke test is intentionally stricter.

### RouteBox login fails

Use the exact RouteBox administrator username/password used by the RouteBox panel.

If RouteBox authentication is disabled, leave both fields empty and the Bot will test the unauthenticated API path.

### TLS errors

For a self-signed certificate during controlled testing, disable TLS verification for that RouteBox entry. For production, use a valid certificate and keep verification enabled.

### AWG API unavailable

Check the RouteBox panel's AmneziaWG configuration and confirm that the installed RouteBox version exposes `/api/awg/*`.

### Client `.conf` cannot be generated

Check the RouteBox AWG server address / public host as described in Section 8.

### A smoke-test peer remains after an interrupted install

List peers in the RouteBox panel and remove only the uniquely named `rbt-install-test-*` peer that belongs to the interrupted test. The installer attempts cleanup automatically, but an abrupt process or network failure can prevent a remote DELETE from reaching RouteBox.

---

## 16. Security Recommendations

- 🔐 Never publish Telegram Bot Tokens.
- 🔐 Never publish RouteBox passwords.
- 🔐 Never publish private keys or real `.conf` files.
- 🌐 Use HTTPS for the Admin Panel.
- 🔥 Restrict Admin Panel access with firewall/network controls.
- 🧱 Keep SQLite and `config/config.php` outside the public web root.
- 🔄 Keep Ubuntu and RouteBox updated.
- 📝 Sanitize logs before sharing them publicly.

---

## 17. Beta Scope

Current Beta functionality focuses on:

- 🤖 Telegram Bot
- 🎁 Free Trial
- 👤 Telegram user management foundation
- 🔑 AmneziaWG peer provisioning
- 🌍 Multi-RouteBox provisioning
- ⏱️ Expiration
- 📄 Configuration delivery
- 🖥️ Independent Admin Panel
- 🔐 Encrypted credentials
- 🧪 End-to-end RouteBox smoke testing
- 📝 Logging

### Planned Updates

Future releases will add:

- 💰 **Iranian Rial payment gateway**
- 🪙 **Cryptocurrency payment gateway**
- 🔄 Subscription renewal and upgrades
- 🧾 Invoices and payment history
- 🎟️ Coupons and referral system
- 🌍 Server / Region selection
- 📊 Usage and traffic dashboards
- 🔔 Expiration notifications
- 🌐 Persian / English Bot interface
- 🔗 Subscription links / QR workflow

---

## 18. Beta Compatibility Note

The Bot is designed around the current RouteBox API architecture. RouteBox can change API behavior between releases, so the **full smoke test against the exact RouteBox version installed on your server is mandatory before real users are added**.

When reporting a problem, include:

```text
Ubuntu version
RouteBox version
RouteBox Telegram Bot version
PHP version
Sanitized journalctl output
```

Never include credentials or private configuration data.
