# 🚀 RouteBox Telegram Bot / ATD Panel

Telegram Bot + Web Admin Panel for a modular multi-Provider service platform.

**Development branch:** `ATD-Panel`  
**Current version:** `0.1.0-beta.11.05`  
**Last confirmed healthy application/UI checkpoint:** `6026a16`

> Read `ATD_PANEL_WORKING_NOTES.md`, `ROADMAP.md` and `docs/ATD_PANEL_CURRENT_STATE.md` before changing the project.

## Providers

Current Provider integrations:

- RouteBox / AmneziaWG
- IBSng A1.24
- MikroTik WireGuard

The architecture is Provider-neutral. Provider/Plan discovery is based on the existing service catalog and Provider-specific `provider_key` / Plan metadata.

Current Provider keys include:

```text
routebox
ibsng
mikrotik_wireguard
```

A future Provider must integrate through the existing architecture rather than requiring a rewrite of the Bot or Web Panel.

## Protected architecture

The project already has an operational:

```text
Web Panel
   │
Telegram Bot ── Existing Worker
   │
Service/Application Layer
   │
Provider Integrations
   │
Database
```

Do not create a second Worker, duplicate Provider provisioning, duplicate MikroTik IP allocation, or casually rewrite working Provider Core.

## ATD Panel UI

The Web Panel uses a shared `?section=` architecture.

Important existing sections:

- `section=users` — shared User Management
- `section=payment-settings` — shared Payment Settings
- `section=bot` — Telegram Bot control center

The `section=bot` area already contains protected Bot Settings, Bot Buttons and Bot Menu Preview. Customer Bot and Telegram Bot Admin are child destinations inside this same UI architecture.

The four protected ATD Stats/status cards must not be removed, reordered or redesigned casually.

## Telegram Bot

The existing Customer Bot supports the modular service architecture and Provider-specific service flows.

The Bot must not maintain a separate hard-coded Provider/Plan catalog:

```text
Provider
   ↓
provider_key
   ↓
Service Category
   ↓
Provider-owned Plan
   ↓
Telegram Bot
```

### Telegram Bot Admin

The Admin foundation is implemented:

- Telegram Numeric ID authorization
- multiple Admins
- future-ready roles
- Web management via `public/telegram-admins.php`
- dedicated Admin menu inside the existing Worker
- existing Provider/service provisioning reused
- replay protection and audit logging

Remaining major Admin work includes User/Search, Service Management, IBSng account management, Worker operations, notifications and the complete payment lifecycle.

## Usage Guides

Do not confuse these:

1. **General Guide** — general product/bot usage.
2. **Bot/service Usage Guides** — customer instructions for the Provider/service they purchased.
3. **Provider Admin Guides** — operator instructions for configuring Provider servers in ATD Panel.

## Payment

Payment is Provider-neutral.

The existing `section=payment-settings` surface now contains the card-to-card configuration.

The complete workflow is still staged and must be tested before being called complete:

```text
Customer
  ↓
Dynamic Provider / Plan
  ↓
Order
  ↓
Payment
  ↓
Receipt
  ↓
Admin Review
  ↓
Approve / Reject
  ↓
Existing Provider Provisioning
  ↓
Activate + Send Config/Credentials
```

Future payment adapters include ZarinPal and Crypto.

## IBSng

IBSng A1.24 provisioning is operational and tested end-to-end.

Important:

- `provider_key` identifies the Provider.
- IBSng Provider Plan Key represents the real IBSng Group/Plan identifier.
- Username Prefix is only a username-generation setting.

These values must never be conflated.

## MikroTik WireGuard

MikroTik provisioning and IP/peer allocation remain Provider-owned. The Telegram Bot must never duplicate the allocation algorithm.

## MirzaBot reference policy

`https://github.com/mahdiMGF2/mirzabot` is a **research/feature reference only**.

It may be used to compare feature ideas and user/admin workflows. Its source code, schema, naming, UI, menu structure, text and implementation must not be copied into ATD Panel.

## Security

A full source-wide security audit remains pending. Required areas include SQL Injection, XSS, CSRF, IDOR, authentication/authorization, privilege escalation, command injection, SSRF, path traversal, unsafe uploads, Telegram callback forgery/replay, rate limiting, secrets and session security.

For a real SQL Injection finding, report the exact file/function/query/input/attack vector/severity before fixing it. Then use the smallest prepared-statement/parameter-binding change and regression-test it.

## Installer

Current installer structure:

```text
install.sh
installer-core.sh
install-dev.sh
install-dev-full.sh
```

`install-v2.sh` is retired and must not be treated as a current installer.

## Documentation

- [Persistent Working Notes](./ATD_PANEL_WORKING_NOTES.md)
- [Persistent New-Chat Handoff — 2026-10-07](./docs/ATD_PANEL_HANDOFF_2026-10-07.md)
- [Roadmap](./ROADMAP.md)
- [Current State / New-Chat Handoff](./docs/ATD_PANEL_CURRENT_STATE.md)
- [Changelog](./CHANGELOG.md)
- [Installation](./INSTALL.md)
- [ATD Architecture](./docs/ATD_PANEL_ARCHITECTURE.md)
- [Telegram Bot Admin](./docs/TELEGRAM_ADMIN.md)
- [Payment Workflow](./docs/ATD_PAYMENT_WORKFLOW.md)
- [Security Baseline](./docs/SECURITY_BASELINE.md)
- [Troubleshooting](./TROUBLESHOOTING.md)

## Development rule

Existing functionality > Refactor.

Before changing Provider Core, Worker, database semantics or sensitive functional flows: inspect the complete call chain, explain the dependency and risk, make the smallest change, test it, update documentation and create a traceable commit.
