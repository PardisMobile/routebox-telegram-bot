# 🔐 Admin Password Management

The admin panel uses a PHP password hash. The original installer prints a random password once; it is intentionally not recoverable from `config.php`.

## Change the password from the panel

After logging in, open:

`/account.php`

Enter the current password and a new password of at least **8 characters**. The new hash is stored in the SQLite `settings` table. The protected `config/config.php` does not need to become writable by `www-data`.

## If the password is forgotten

Use the root-only CLI recovery tool on the server:

```bash
sudo php /opt/routebox-telegram-bot/reset-admin-password.php
```

It prompts for the new password twice without displaying it and stores only a secure password hash.

If SQLite reports `database is locked`, the web/bot service is currently using the database. Stop the RouteBox service temporarily, run the recovery command, then start the service again. The application also uses a SQLite busy timeout to tolerate short concurrent writes.

### If the recovery tool is not installed yet

Update the installation first:

```bash
cd /opt/routebox-telegram-bot
git pull --ff-only
```

Then run the recovery command above.

## Existing installations

The application remains backward-compatible with installations whose password hash is still in `config/config.php`. Once a password is changed through `/account.php` or the recovery tool, the new hash is stored in the database and takes precedence.

## Security notes

- Do not put the password in shell history, Git, screenshots, or chat logs.
- Use HTTPS for the admin panel when it is exposed beyond localhost.
- Keep `config/config.php` owned by `root:www-data` and non-writable by `www-data`.
- If the password is forgotten, SSH/root access to the server is the recovery path; there is intentionally no unauthenticated web "forgot password" endpoint.
