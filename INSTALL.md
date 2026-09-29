# 📦 RouteBox Telegram Bot — Installation Guide

**Version:** `0.1.0-beta.2` · **Status:** 🧪 Beta · **Platform:** Ubuntu 22.04+

This guide installs the Bot and validates the complete Telegram → RouteBox → AmneziaWG path before a RouteBox server is accepted.

> ⚠️ Beta software. Test it on your own RouteBox installation before production or commercial use.

## 1. Requirements

- Ubuntu 22.04 or newer
- Root or sudo access
- Internet access to Telegram and GitHub
- PHP 8+
- A reachable RouteBox Panel URL

### No Nginx/Apache setup is required

Beta 2 runs the Bot Admin Panel with PHP's built-in listener on a free port, normally `8090`.

The installer does **not** install, start, stop, reload or configure Nginx or Apache. Existing RouteBox, Apache and Nginx services on ports 80/443 are left untouched.

```text
RouteBox / Apache / Nginx   ← untouched
Bot Admin Panel :8090       ← independent PHP service
Bot Worker                  ← systemd
```

If `8090` is already occupied, the installer automatically selects the next free TCP port.

## 2. Create the Telegram Bot

1. Open Telegram.
2. Open **@BotFather**.
3. Run `/newbot`.
4. Choose the Bot name and username.
5. Keep the Bot Token private.

## 3. Install

Run on the Ubuntu server. You do **not** need to clone the repository on your Mac:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The installer:

1. Checks Ubuntu 22.04+.
2. Installs the required PHP/runtime packages.
3. Downloads or updates the current Beta code.
4. Initializes SQLite and the encryption key.
5. Generates the Admin password on first installation.
6. Validates Telegram with `getMe`.
7. Stores the Telegram token encrypted; it is never printed back.
8. Validates every RouteBox with the **same `RouteBoxClient` used by the Bot**.
9. Runs a real AWG create → config export → delete smoke test.
10. Installs the Telegram worker.
11. Starts the independent Admin Panel without touching existing web servers.

## 4. Telegram Setup

The wizard asks:

```text
Telegram Bot Token:
```

The token is entered silently, checked with Telegram `getMe`, and stored encrypted. The full token is never printed back to the terminal.

The installer also attempts to remove an existing Telegram webhook so long polling does not compete with it.

Expected result:

```text
✓ Telegram connection successful: @YourBot
✓ Token accepted and stored encrypted (token is never printed).
```

## 5. RouteBox Setup

For each RouteBox:

```text
Server name [RouteBox-1]:
RouteBox Panel URL:
RouteBox username [admin]:
RouteBox password:
Verify TLS certificate? [Y/n]:
```

### RouteBox Panel URL

Enter the address that opens the RouteBox web panel. The URL may include the port:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
```

For convenience, the installer also accepts:

```text
panel.example.com:8443
```

and automatically adds `https://`.

There is **no separate Bot/API port**. The Bot uses the same HTTP(S) listener as the RouteBox panel.

The installer does not ask for VPS/Router mode, scheme, host and API port separately.

### RouteBox credentials

Use the same credentials used to enter the RouteBox panel. If RouteBox authentication is disabled, leave username/password empty.

For HTTPS, keep TLS verification enabled when the certificate is valid. Disabling it is intended for controlled testing with self-signed certificates.

## 6. RouteBox Authentication

The Bot follows the current RouteBox authentication model:

```text
POST /api/auth/login
        ↓
Session Cookie
        ↓
Protected API requests
        ↓
401 → re-login once → retry
        ↓
HTTP Basic compatibility fallback
```

Session cookies remain in memory and are not stored in SQLite.

## 7. Full RouteBox Validation

A reachable panel or `/api/status` alone is not enough.

The installer validates:

```text
GET /api/health
GET /api/status
GET /api/awg/status
GET /api/awg/peers
GET /api/settings
```

Then it performs a real temporary peer operation:

```text
POST   /api/awg/peers
GET    /api/awg/peers/{publicKey}/config
DELETE /api/awg/peers/{publicKey}
```

The temporary peer is named `rbt-install-test-*`.

This verifies connectivity, authentication, API permissions, AmneziaWG availability, peer creation, key generation, config rendering, URL encoding and deletion.

The RouteBox server is saved only after this complete test succeeds.

## 8. AmneziaWG Client Address and Port

The Bot does not generate the `.conf` itself. It requests it from RouteBox:

```text
GET /api/awg/peers/{publicKey}/config
```

Configure a usable RouteBox **AmneziaWG Server address / Public host** before using the Bot.

The AWG UDP listen port is separate from the RouteBox HTTP/API listener. The Bot does not connect to the AWG UDP port for API operations.

## 9. Multiple RouteBox Servers

You can add multiple servers:

```text
RouteBox #1 → https://de.example.com:8443
RouteBox #2 → https://tr.example.com:8443
RouteBox #3 → https://panel.example.com
```

For Telegram ID `123456789`, the logical peer name is:

```text
user123456789
```

Each RouteBox creates its own cryptographic keypair/public key.

## 10. Admin Panel

First-run credentials:

```text
Username: admin
Password: <generated during installation>
```

The panel configures Telegram, trial duration, RouteBox servers, server enable/disable state and integration tests.

The selected port is saved at:

```text
/etc/routebox-telegram-bot/web-port
```

Check it with:

```bash
cat /etc/routebox-telegram-bot/web-port
```

Check the service with:

```bash
PORT=$(cat /etc/routebox-telegram-bot/web-port)
systemctl status routebox-telegram-bot-web@$PORT
```

Open:

```text
http://YOUR_SERVER_IP:<PORT>/
```

For public deployment, place the panel behind HTTPS/reverse proxy or restrict its port with a firewall.

## 11. RouteBox API Surface

The integration uses the current RouteBox API architecture, including:

```text
POST /api/auth/login
GET  /api/health
GET  /api/status
GET  /api/settings
GET  /api/awg/status
GET  /api/awg/peers
POST /api/awg/peers
PATCH /api/awg/peers/{publicKey}/expiry
GET  /api/awg/peers/{publicKey}/config
GET  /api/awg/peers/{publicKey}/vpn-link
GET  /api/awg/peers/{publicKey}/singbox
POST /api/awg/peers/{publicKey}/traffic/reset
DELETE /api/awg/peers/{publicKey}
```

## 12. Service Management

Worker:

```bash
systemctl status routebox-telegram-bot
journalctl -u routebox-telegram-bot -f
systemctl restart routebox-telegram-bot
```

Admin Panel:

```bash
PORT=$(cat /etc/routebox-telegram-bot/web-port)
systemctl status routebox-telegram-bot-web@$PORT
journalctl -u routebox-telegram-bot-web@$PORT -f
```

## 13. Updating

```bash
bash /opt/routebox-telegram-bot/update.sh
```

The Beta 2 installer/update path uses the independent Admin Panel listener. It does not configure or reload Nginx/Apache.

If you are migrating from an older Beta that created the Bot's Nginx site, the new installer removes **only the Bot's own Nginx site files** and leaves the Nginx service itself and all other sites untouched.

## 14. Uninstalling

```bash
bash /opt/routebox-telegram-bot/uninstall.sh
```

The uninstall script removes the Bot services, its application and its state directory. It does not remove RouteBox, Apache, Nginx, PHP or other system packages.

## 15. Troubleshooting

### `nginx.service` is failed or port 80 is already in use

Beta 2 does not use Nginx for the Bot Admin Panel. A RouteBox/Apache/Nginx listener on port 80 is therefore not a Bot installation blocker.

Check the Bot port:

```bash
cat /etc/routebox-telegram-bot/web-port
```

### Telegram token is not visible while typing

This is intentional. The installer uses silent input for the Bot Token so it is not echoed to the terminal. After validation it prints the Bot username and confirms that the token was accepted; it never prints the full token.

### Telegram connection failed

```bash
curl -I https://api.telegram.org
```

Verify the token with @BotFather.

### RouteBox cannot be reached

Use the exact URL that opens the RouteBox panel:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:8443/api/health
curl -v http://YOUR_ROUTEBOX_HOST:8080/api/health
```

A successful TCP connection does not prove authentication or AWG operations work; the full smoke test is intentionally stricter.

### RouteBox login failed

Use the same credentials used for the RouteBox panel. If authentication is disabled, leave both fields empty.

### TLS errors

For a self-signed certificate during controlled testing, disable TLS verification for that RouteBox entry. For production, use a valid certificate and keep verification enabled.

### AWG config export failed

Check RouteBox's AmneziaWG **Server address / Public host**. The Bot asks RouteBox to render the client configuration; it does not invent the endpoint itself.

### A smoke-test peer remains after an interrupted install

Look for a uniquely named `rbt-install-test-*` peer in RouteBox and remove only that test peer. The installer normally cleans it up, but a network/process interruption can prevent the DELETE request from reaching RouteBox.

## 16. Security Recommendations

- 🔐 Never publish Telegram Bot Tokens or RouteBox passwords.
- 🔐 Never publish private keys or real `.conf` files.
- 🌐 Use HTTPS for the Admin Panel in public deployments.
- 🔥 Restrict the Admin Panel port with firewall/network controls.
- 🧱 Keep SQLite and `config/config.php` outside the public web root.
- 🔄 Keep Ubuntu and RouteBox updated.
- 📝 Sanitize logs before sharing them publicly.

## 17. Payment & Subscription Roadmap

Payment is not implemented in Beta 2.

Future updates will add:

- 💰 **Iranian Rial payment gateway**
- 🪙 **Cryptocurrency payment gateway**
- 🔄 Subscription renewal and upgrades
- 🧾 Invoices and payment history
- 🎟️ Coupons / referral system
- 🌍 Server / Region selection
- 📊 Usage and traffic dashboards
- 🔔 Expiration notifications
- 🌐 Persian / English Bot interface
- 🔗 Subscription links / QR workflow

The future **Rial and Crypto payment options are planned for both Persian and English experiences**.

## 18. Compatibility Note

The Bot is designed around the current RouteBox API architecture. RouteBox API behavior can change between releases, so the full smoke test against the exact RouteBox version installed on the server is mandatory before real users are added.

When reporting a problem, include Ubuntu, RouteBox, Bot and PHP versions plus sanitized logs. Never include credentials or private configuration data.
