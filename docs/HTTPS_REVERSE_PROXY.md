# Admin Panel HTTPS / TLS

## Current architecture — Beta 7

The Bot Admin Panel can reuse the certificate exported by RouteBox:

```text
/etc/routebox/panel-cert/fullchain.pem
/etc/routebox/panel-cert/key.pem
```

The Bot keeps its existing public Admin Panel port and accepts **both HTTP and HTTPS on that same port**.

For example, with Admin Panel port `8093`:

```text
http://SERVER-IP:8093/
https://ROUTEBOX-DOMAIN:8093/
```

The HTTPS hostname must match the RouteBox certificate.

## Internal flow

```text
                         ┌── HTTP ───────────────→ PHP
Client → :8093 → HAProxy┤
                         └── TLS → stunnel ──────→ PHP
```

- HAProxy is a Bot-owned dedicated instance.
- PHP listens only on `127.0.0.1`.
- stunnel listens only on `127.0.0.1`.
- The public Bot port remains unchanged.
- RouteBox's own listener remains untouched.
- Ports `80/443` are not claimed by the Bot.
- The Bot does not install or reconfigure Apache/Nginx.

## Enable or repair

Run as root on the Bot server:

```bash
cd /opt/routebox-telegram-bot
sudo bash setup-routebox-tls.sh
```

The script installs the required HAProxy/stunnel packages if needed, creates Bot-owned systemd units, copies the RouteBox certificate to a protected local directory, and performs HTTP and HTTPS health checks on the same public port.

If the RouteBox certificate is unavailable, the script leaves the existing HTTP panel running and does not make the panel unavailable.

## Certificate renewal

RouteBox remains responsible for certificate issuance and renewal. The Bot runs a five-minute systemd timer that compares the RouteBox certificate with its local copy and reloads the Bot TLS terminator when the certificate changes.

## Services

```bash
sudo systemctl status routebox-telegram-bot-mux.service
sudo systemctl status routebox-telegram-bot-tls.service
sudo systemctl status routebox-telegram-bot-tls-sync.timer
```

## Important: do not use the old reverse-proxy model

Do **not** create a second Nginx/Apache listener for this purpose and do not move the Bot to another public port just to add HTTPS.

The intended Beta 7 model is same-port HTTP+HTTPS using the Bot-owned TCP multiplexer.
