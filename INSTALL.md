# 📦 RouteBox Telegram Bot — Installation Guide

**Version:** `0.1.0-beta.11.05` · **Status:** 🧪 Beta · **Platform:** Ubuntu 22.04+

This is the canonical production installation guide. Use `install.sh` for normal deployments.

## 1. Requirements

- Ubuntu 22.04 or newer
- Root or sudo access
- Internet access to GitHub and Telegram
- PHP 8+
- Reachable RouteBox Panel URL when configuring RouteBox

No Nginx or Apache installation is required for the Bot Admin Panel.

## 2. Production installation

```bash
sudo -i
bash <(curl -fsSL https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/install.sh)
```

`install.sh` is the user-facing production entrypoint. It delegates to the current internal `installer-core.sh`.

## 3. Current installer structure

Exactly four installer files are part of the current structure:

```text
install.sh
installer-core.sh
install-dev.sh
install-dev-full.sh
```

`install-v2.sh` is retired and must not be referenced by CI, documentation or deployment instructions.

## 4. What production installation does

The current production installer validates the runtime, initializes application/database state, validates the Telegram token, configures RouteBox, performs the existing RouteBox/AWG smoke test, installs the existing Worker and independent PHP Admin Panel, and runs health/permission checks.

It does not take over existing Apache/Nginx/RouteBox ports `80/443`.

## 5. Development environment

The development installation is isolated under the `routebox-telegram-bot-dev` environment. The current DEV service used by the ATD Panel project is:

```text
routebox-telegram-bot-dev.service
```

and the development Web Panel commonly uses:

```text
routebox-telegram-bot-dev-web@8092.service
```

The exact active service should always be checked before restart/reload operations.

## 6. Admin Panel

The Web Admin Panel is a shared ATD Panel UI based on `?section=` routes. Important existing surfaces include:

- `section=users`
- `section=payment-settings`
- `section=bot`

The protected four-card/status UI must remain intact.

## 7. Telegram Bot / Admin architecture

The project already has an operational Telegram Bot and Worker. Bot Admin is an additive feature inside that Worker; a second Worker must not be created.

Telegram Bot Admin authorization uses Telegram Numeric IDs and is independent from IBSng `owner` / `owner_name`.

Provider and Plan selection remains dynamic through the existing service catalog. New Providers must supply their own `provider_key` and Provider-specific Plan metadata.

## 8. Payment settings

Card-to-card configuration belongs to the existing:

```text
?section=payment-settings
```

Do not create a second payment settings page. The full Order → Receipt → Admin Review → Provision lifecycle is still staged until it is end-to-end tested.

## 9. Provider safety

Do not rewrite or duplicate:

- RouteBox provisioning/API integration
- IBSng authentication/provisioning/group mapping
- MikroTik WireGuard peer/IP allocation
- existing Worker polling/lock behavior
- existing database semantics

For IBSng, Provider Plan Key means the actual IBSng group/plan identifier. It is separate from `provider_key` and Username Prefix.

## 10. Updating

For production use the existing project updater and follow the current deployment documentation. For DEV, update the `ATD-Panel` checkout and validate the relevant PHP/shell files before restarting services.

Never assume a documentation-only commit is a new tested application checkpoint. The last confirmed healthy application/UI checkpoint is `6026a16` until a newer one is explicitly tested and confirmed.

## 11. Troubleshooting

For Admin Panel repair see `docs/ADMIN_PANEL_REPAIR.md`. For TLS behavior see `docs/HTTPS_REVERSE_PROXY.md`. For provider-specific setup see `docs/providers/` and `docs/MIKROTIK_SETUP_GUIDE.md`.

## 12. Security

Do not publish Telegram tokens, Provider credentials, private keys, real configuration files or database contents. Keep the Admin Panel protected by authentication, CSRF and network policy appropriate to the deployment.

The full source-wide security audit remains a roadmap item and must not be described as complete until actually performed.

## 13. Project handoff

Before changing ATD Panel, read:

1. `ATD_PANEL_WORKING_NOTES.md`
2. `ROADMAP.md`
3. `CHANGELOG.md`

Then inspect the actual source/call chain before touching Provider Core, Worker or database behavior.
