# HTTPS for the Admin Panel without taking over 80/443

Beta 3 keeps the Admin Panel as an independent local PHP service. It does **not** install, stop, reload or replace the existing RouteBox web server.

The safe production model is:

```text
Internet HTTPS :443
        │
        ▼
Existing RouteBox web server / reverse proxy
        │
        ▼
127.0.0.1:<Bot panel port>
        │
        ▼
RouteBox Telegram Bot PHP panel
```

The Bot panel port is stored in:

```bash
cat /etc/routebox-telegram-bot/web-port
```

## Important

Do not start a second Nginx/Apache on ports 80 or 443. Do not copy RouteBox's ACME files into the Bot configuration just to make the PHP listener public.

Instead, use the **already-running TLS endpoint** as the reverse proxy and forward only the Bot's local port. This keeps certificate renewal with the existing RouteBox/web-server stack.

### Nginx example

Add a dedicated location/server rule to the existing TLS configuration only after backing it up and validating it with `nginx -t`:

```nginx
location /routebox-bot/ {
    proxy_pass http://127.0.0.1:8093/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

> The application currently uses root-relative URLs such as `/login.php`. A dedicated subdomain is therefore cleaner than a path prefix unless the panel is later given a configurable base path.

### Preferred production layout

Use a hostname already covered by the existing certificate, or a dedicated subdomain whose certificate is managed by the existing TLS server, and proxy that hostname to the Bot's local port.

After changing the existing proxy configuration, validate it first and reload the existing service only if the configuration test succeeds.

The Bot itself must never bind to 80/443.
