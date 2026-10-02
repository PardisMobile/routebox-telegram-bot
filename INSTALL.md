# 📦 RouteBox Telegram Bot — Installation Guide

**Version:** `0.1.0-beta.11.05` · **Status:** 🧪 Beta · **Platform:** Ubuntu 22.04+

This is the canonical production installation guide. Use `install.sh` for normal deployments.

## 1. Requirements

- Ubuntu 22.04 or newer
- Root or sudo access
- Internet access to GitHub and Telegram
- PHP 8+
- A reachable RouteBox Panel URL
- RouteBox / AmneziaWG configured with a usable server address / public host

No Nginx or Apache installation is required for the Bot Admin Panel.

## 2. Production installation

Run on the target Ubuntu server:

```bash
sudo -i
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

The production entrypoint downloads the tested setup wizard and then performs the complete setup.

## 3. What the installer does

1. Checks Ubuntu 22.04+ and root access.
2. Installs required runtime packages, including `qrencode`.
3. Detects PHP 8+.
4. Downloads or refreshes the current production code.
5. Creates the application encryption key and first-run Admin password.
6. Initializes SQLite.
7. Initializes the modular service schema through the IBSng schema layer.
8. Prompts for the Telegram Bot Token using silent input.
9. Validates the token with Telegram `getMe`.
10. Removes an existing Telegram webhook so long polling can work.
11. Prompts for one or more RouteBox Panel endpoints and credentials.
12. Validates RouteBox API connectivity and performs a real AWG create → config → delete smoke test.
13. Stores RouteBox credentials encrypted.
14. Installs the systemd Telegram Worker.
15. Installs the independent PHP Admin Panel on a free TCP port, normally `8090`.
16. Installs the restricted Admin Panel updater.
17. Runs web-panel permission and health checks.
18. Attempts the optional RouteBox TLS certificate reuse flow without changing RouteBox/Apache/Nginx port 80/443 ownership.
19. Prints the final version, panel port and service information.

## 4. Telegram token

The token is entered silently:

```text
Telegram Bot Token:
```

The installer validates it with Telegram and prints only the Bot username / success state. The full token is never echoed back.

## 5. RouteBox setup

For each server:

```text
Server name [RouteBox-1]:
RouteBox Panel URL:
RouteBox username [admin]:
RouteBox password:
Verify TLS certificate? [Y/n]:
```

Examples:

```text
https://panel.example.com:8443
http://192.0.2.10:8080
https://panel.example.com
panel.example.com:8443
```

The installer adds `https://` when a scheme is omitted.

The Bot uses the same RouteBox Panel HTTP(S) listener for API requests. There is no separate Bot API port.

## 6. RouteBox validation

The installer is intentionally stricter than a simple HTTP reachability test.

It validates the current RouteBox client integration, including health/status and AWG endpoints, then performs a real temporary peer workflow:

```text
POST   /api/awg/peers
GET    /api/awg/peers/{publicKey}/config
DELETE /api/awg/peers/{publicKey}
```

The temporary peer is named `rbt-install-test-*`.

The server is saved only after the complete test succeeds.

## 7. Modular service initialization

The current application contains a provider-independent service catalog and subscription layer.

The first production boot ensures:

```text
RouteBox category
IBSng category
IBSng servers
IBSng groups
Generic service plans
Service subscriptions
Orders
Payment providers
Coupons
```

Legacy RouteBox plans remain compatible and are mirrored into the generic service catalog.

## 8. IBSng

IBSng support targets the A1.24 Web Panel architecture.

Configured from the Admin Panel, IBSng supports:

- server connection testing
- admin credential storage
- ISP name
- manual Plan → Group mapping
- Telegram provisioning
- generated Internet Username / Password
- subscription persistence
- dynamic exposure of configured IBSng plans

IBSng groups are deliberately mapped from RouteBox-defined plans. The production provider does not depend on automatically scraping a slow/broken Group List page.

## 9. Admin Panel

First installation generates:

```text
Username: admin
Password: <generated during installation>
```

The installer prints the password once on a first install. Store it securely.

The panel port is stored at:

```text
/etc/routebox-telegram-bot/web-port
```

Check it:

```bash
cat /etc/routebox-telegram-bot/web-port
```

Check the panel service:

```bash
PORT=$(cat /etc/routebox-telegram-bot/web-port)
systemctl status routebox-telegram-bot-web@$PORT
```

Open:

```text
http://YOUR_SERVER_IP:<PORT>/
```

## 10. HTTP / HTTPS behavior

The normal Admin Panel uses PHP's built-in HTTP listener on its own port.

The optional TLS integration can reuse the active RouteBox panel certificate and expose **HTTP and HTTPS on the same Bot panel port** through the Bot-owned multiplexer.

It does not:

- bind ports 80/443
- run Certbot / ACME for the Bot
- rewrite existing Apache/Nginx configuration
- replace RouteBox

If the RouteBox panel certificate is not available at the expected path, the Admin Panel remains HTTP-only.

## 11. Service management

Worker:

```bash
systemctl status routebox-telegram-bot
systemctl restart routebox-telegram-bot
journalctl -u routebox-telegram-bot -f
```

Admin Panel:

```bash
PORT=$(cat /etc/routebox-telegram-bot/web-port)
systemctl status routebox-telegram-bot-web@$PORT
journalctl -u routebox-telegram-bot-web@$PORT -f
```

## 12. Updating

Command-line update:

```bash
sudo bash /opt/routebox-telegram-bot/update.sh
```

The Admin Panel can also start the update flow when a newer GitHub version is detected.

The updater:

- creates a pre-update backup
- pulls `origin/main`
- refreshes systemd / updater files
- runs PHP and shell syntax checks
- restarts the Worker and Admin Panel
- preserves the existing SQLite/config state

## 13. Production vs development installers

There are four installer-related files:

```text
install.sh           → canonical production entrypoint
install-v2.sh        → production setup implementation
install-dev.sh       → development entrypoint
install-dev-full.sh  → development implementation
```

Normal production deployments should use:

```bash
bash install.sh
```

The DEV installers use isolated `routebox-telegram-bot-dev` paths and the `feature/modular-services-ibsng` branch.

## 14. Troubleshooting

### Telegram token rejected

Check network access:

```bash
curl -I https://api.telegram.org
```

Then verify the token in @BotFather.

### RouteBox validation failed

Use the exact URL that opens the RouteBox panel and check reachability:

```bash
curl -vk https://YOUR_ROUTEBOX_HOST:8443/api/health
curl -v http://YOUR_ROUTEBOX_HOST:8080/api/health
```

A reachable panel is not enough; the full AWG smoke test must also succeed.

### Admin Panel returns 503

Run:

```bash
sudo bash /opt/routebox-telegram-bot/repair-web.sh
```

Then inspect:

```bash
PORT=$(cat /etc/routebox-telegram-bot/web-port)
journalctl -u routebox-telegram-bot-web@$PORT -n 80 --no-pager
```

### Telegram token input appears blank

This is intentional. Token input is hidden to reduce accidental disclosure.

### AWG config export fails

Check the RouteBox AmneziaWG **Server address / Public host**. The Bot asks RouteBox to render the client config; it does not invent that endpoint.

### A smoke-test peer remains

If installation was interrupted during cleanup, look only for the uniquely named `rbt-install-test-*` peer and remove that test peer from RouteBox.

## 15. Security recommendations

- Never publish Bot Tokens, RouteBox passwords or IBSng credentials.
- Never publish private keys or real `.conf` files.
- Use HTTPS for public Admin Panel access.
- Restrict the Admin Panel port with firewall/network policy.
- Keep SQLite and `config/config.php` outside the public web root.
- Keep Ubuntu, RouteBox and PHP packages current.
- Sanitize logs before sharing them.

## 16. Uninstall

```bash
sudo bash /opt/routebox-telegram-bot/uninstall.sh
```

The uninstall script removes the Bot application/services/state. It does not remove RouteBox, Apache, Nginx or unrelated system packages.

## 17. Future payment and subscription work

The database and abstraction layer already contain the foundation for:

- order lifecycle
- coupons
- payment providers
- verified-payment provisioning
- invoices / payment history

Planned gateways include:

- Iranian Rial gateway adapter
- Crypto gateway adapter

These are **planned**, not currently live production payment gateways.

## 18. Compatibility

The Bot is tied to the current RouteBox API architecture. RouteBox API behavior may change between releases, so the full integration smoke test against the exact RouteBox installation is mandatory before real users are onboarded.
