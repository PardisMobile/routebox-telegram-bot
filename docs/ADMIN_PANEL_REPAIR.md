# Admin Panel Repair Guide

## When should `repair-web.sh` be used?

Use `repair-web.sh` when the independent PHP Admin Panel is installed but is not responding correctly, especially when you see:

- a blank page;
- HTTP `503`;
- Chrome `ERR_CONNECTION_CLOSED`;
- PHP errors saying it cannot load `config/config.php`;
- permission/ownership problems involving `config/` or `config.php`;
- a stopped or broken Admin Panel systemd service;
- an installation/update that completed but failed its final panel health check.

## Run the repair

As `root` on the Ubuntu server:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/repair-web.sh)
```

Or, if the repository is already installed:

```bash
bash /opt/routebox-telegram-bot/repair-web.sh
```

## What the repair does

The script:

1. Verifies that the existing installation and `config/config.php` exist.
2. Restores the expected `root:www-data` ownership and permissions for `config/` and `config.php`.
3. Ensures the panel storage is writable by `www-data`.
4. Tests loading `config.php` as the **actual `www-data` service user**.
5. Recreates the dedicated PHP Admin Panel systemd unit.
6. Starts the panel on the port stored in `/etc/routebox-telegram-bot/web-port`.
7. Performs a real HTTP health check against `/login.php`.
8. Refuses to report success if the panel still does not respond correctly.

## Why this can fix a blank/503 panel

The Admin Panel runs as `www-data`. If `config/` is `750 root:root`, or `config.php` is readable only by `root`, PHP cannot traverse the directory or read the configuration file. The result can be a blank page or a service-level failure even though the PHP service itself is running.

The repair deliberately fixes the directory and file permissions together and then verifies access as `www-data` before declaring success.

## What it does NOT change

`repair-web.sh` does not:

- change RouteBox API credentials;
- change the Telegram bot token;
- delete or recreate the SQLite database;
- change RouteBox itself;
- stop or reconfigure an existing Apache/Nginx installation;
- enable HTTPS directly on the PHP panel port;
- reset the Admin Panel password.

## Admin login

The default username is:

```text
admin
```

The password is randomly generated during the first installation and printed once. It is stored as a password hash, so the original password cannot be recovered from the hash.

`repair-web.sh` does not change the password.

## HTTPS

The Admin Panel's PHP built-in listener is HTTP-only by design. HTTPS should terminate at an existing Apache/Nginx/RouteBox TLS endpoint and reverse-proxy to the Admin Panel port.

Do not make the repair script take over ports `80` or `443`; that can conflict with RouteBox or another existing web server.

## Successful repair

A healthy repair should end with messages similar to:

```text
✓ www-data can load config.php
✓ Admin panel repaired and responding on HTTP.
✓ www-data can read config.php securely.
✓ HTTPS on this port is intentionally not enabled.
```

If `www-data` still cannot load the configuration, the script prints additional permission and PHP diagnostics so the remaining issue can be investigated instead of hiding it.
