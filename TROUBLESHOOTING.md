# Troubleshooting

## Admin Panel shows `Run install.sh first` / HTTP 503

Beta 2 runs the Admin Panel as a standalone PHP HTTP listener. A 503 from `/` means the PHP process could not load `config/config.php`; it is not a RouteBox API failure.

Repair the existing installation with:

```bash
curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/repair-web.sh | sudo bash
```

Then read the selected port:

```bash
cat /etc/routebox-telegram-bot/web-port
```

Open the panel with **HTTP**, for example:

```text
http://YOUR_SERVER_IP:8093/
```

Do **not** open `https://YOUR_SERVER_IP:8093/`. The standalone PHP listener does not speak TLS. If HTTPS is required, put Apache or Nginx in front of the local/standalone panel and proxy to its HTTP port.

The repair script also tests that the real `www-data` service user can load the configuration and installs a per-service PHP `open_basedir` boundary covering the application and `/tmp`.

## RouteBox URL vs Bot Admin Panel URL

These are two different services and may use different ports:

```text
Browser → Bot Admin Panel :8093       (HTTP by default)
Bot Worker → RouteBox API :8443/8080  (HTTP or HTTPS, depending on RouteBox)
```

The RouteBox URL entered during installation must be the URL of the **RouteBox panel itself**, not the Bot Admin Panel port.

## `Verify TLS certificate?`

- For a valid HTTPS RouteBox certificate: answer `Y`.
- If RouteBox is using HTTP: enter an `http://...` URL; TLS verification is automatically disabled.
- If HTTPS uses a self-signed/untrusted certificate during testing: `N` allows the Bot to connect without certificate verification. This does **not** make the Bot Admin Panel HTTPS.

For production, use a valid RouteBox certificate and keep TLS verification enabled.

## PHP log says `Invalid request` or `Unsupported SSL request`

That normally means an HTTPS client connected to the plain HTTP PHP listener. Use `http://` for the standalone admin port, or configure an HTTPS reverse proxy in front of it.
