# Admin Panel Repair Guide

## When should `repair-web.sh` be used?

Use `repair-web.sh` when the independent PHP Admin Panel is installed but is not responding correctly, especially when you see:

- a blank page;
- HTTP `503`;
- `ERR_CONNECTION_CLOSED`;
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
4. Tests loading `config.php` as the actual `www-data` service user.
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
- take over ports `80` or `443`;
- reset the Admin Panel password.

## Admin login

The default username is:

```text
admin
```

The password is randomly generated during the first installation and printed once. It is stored as a password hash, so the original password cannot be recovered from the hash.

`repair-web.sh` does not change the password.

## HTTPS / TLS

Beta 7 can reuse the RouteBox panel certificate and accept **HTTP and HTTPS on the same Admin Panel port**.

For example, with port `8093`:

```text
http://SERVER-IP:8093/
https://ROUTEBOX-DOMAIN:8093/
```

The HTTPS frontend is configured separately by:

```bash
sudo bash /opt/routebox-telegram-bot/setup-routebox-tls.sh
```

The TLS setup keeps PHP and the TLS terminator on loopback, uses a Bot-owned HAProxy TCP multiplexer, and never takes over RouteBox/Apache/Nginx ports `80/443`.

`repair-web.sh` itself does not enable TLS; it repairs the PHP Admin Panel. After a repair, run `setup-routebox-tls.sh` if the TLS frontend also needs to be restored.

## Successful repair

A healthy repair should end with messages confirming:

```text
✓ www-data can load config.php
✓ Admin panel repaired and responding on HTTP.
✓ www-data can read config.php securely.
```

If `www-data` still cannot load the configuration, the script prints additional permission and PHP diagnostics instead of hiding the remaining problem.
