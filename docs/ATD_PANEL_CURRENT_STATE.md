# ATD Panel — Current State / New-Chat Handoff

**Date:** 2026-10-05  
**Branch:** `ATD-Panel`  
**Repository:** `PardisMobile/routebox-telegram-bot`

This file is a compact current-state reference. It supplements, but does not replace, `ATD_PANEL_WORKING_NOTES.md` and `ROADMAP.md`.

## Last tested application checkpoint

`6026a16` — `fix(ui): restore compact desktop sidebar sizing baseline`

Documentation commits after this SHA are not application checkpoints unless the application was tested and explicitly confirmed.

## Protected architecture

ATD Panel already has an operational Telegram Bot + Worker + Service Layer + Provider integrations + database architecture.

Do not:

- create a second Telegram Bot or Worker;
- rewrite the current Worker polling/lock mechanism without explicit review;
- duplicate Provider provisioning inside Telegram handlers;
- duplicate MikroTik WireGuard peer/IP allocation;
- rewrite working RouteBox/IBSng/MikroTik Provider Core for UI work;
- change existing database semantics casually.

Existing functionality > refactor.

## Provider model

Provider/Plan selection is dynamic through the existing service catalog.

Current Provider keys:

```text
routebox
ibsng
mikrotik_wireguard
```

A future Provider gets its own `provider_key` and its own Provider-specific Plan metadata. The Bot must discover it through the existing architecture rather than receiving a hard-coded Bot implementation.

Keep these concepts separate:

```text
provider_key
Provider Plan Key
Username Prefix
```

For IBSng, Provider Plan Key is the real IBSng Group/Plan identifier and is important to provisioning.

## Existing Web UI architecture

All Web Panel functionality belongs to the shared `?section=` architecture.

Important protected areas:

- `section=users` — existing shared User Management; do not duplicate it.
- `section=payment-settings` — existing shared Payment Settings.
- `section=bot` — existing Bot section.

Inside `section=bot`, preserve:

- Bot Settings
- Bot Buttons
- Bot Menu Preview
- protected ATD Stats cards

Customer Bot and Telegram Bot Admin are child destinations of the existing section model, not a separate Web application.

## Usage Guides — important distinction

There are three distinct concepts:

1. **General Guide** — general bot/product usage.
2. **Bot/service Usage Guides** — instructions for the Provider/service the customer actually purchased, such as WireGuard/OpenVPN/etc.
3. **Provider Admin Guides** — instructions for the operator to configure a Provider server in ATD Panel.

Do not merge these concepts.

## Telegram Bot Admin

Foundation completed:

- Numeric Telegram ID authorization.
- Multiple Admins.
- Future-ready roles.
- Web Admin management at `public/telegram-admins.php`.
- Admin menu integrated into the existing Worker.
- Existing Provider/service provisioning reused for Admin operations.
- Replay-safe Admin actions and audit logging without credentials.

Still needed:

- full Admin User/Search workflow;
- full Admin Service Management;
- IBSng account search/renew/edit;
- Worker health/log/restart controls;
- expiry notifications;
- complete payment lifecycle.

## Payment

The existing `section=payment-settings` was extended for card-to-card configuration.

The full production workflow is **not to be called complete merely because the settings UI exists**.

Target workflow:

```text
Customer
  ↓
Dynamic Provider
  ↓
Dynamic Plan
  ↓
Order
  ↓
Payment
  ↓
Receipt
  ↓
Admin Review
  ├─ Reject
  └─ Approve
       ↓
Existing Service/Provider Provisioning
       ↓
Activate
       ↓
Credentials / Config
```

Approval must be idempotent and provisioning must happen only after valid approval.

## Customer Bot — highest remaining gaps

1. Complete provider-neutral My Services/Service Details.
2. Renewal using real configured Provider Plans.
3. Provider-capability-aware config/credential retrieval.
4. Notifications and expiry lifecycle.

## Admin Bot — highest remaining gaps

1. Search Users/Services securely.
2. User profile + services + orders + payments.
3. Service details and Provider-supported actions.
4. Renewal with idempotency.
5. IBSng account management.
6. Payment/receipt lifecycle.

## MirzaBot

`mahdiMGF2/mirzabot` is **reference-only**.

Use it to benchmark feature ideas and user/admin needs. Do not copy its code, classes, functions, database schema, naming, UI, menu structure, texts, architecture or implementation. Any useful idea must be independently designed for ATD Panel.

## Security

Full source-wide security audit is still pending.

Especially important:

- SQL Injection
- XSS
- CSRF
- IDOR
- Authentication/Authorization bypass
- Privilege escalation
- Command injection
- SSRF
- Path traversal/file upload
- Telegram callback forgery/replay
- Rate limiting
- Secrets/credential leakage
- Session security

For a real SQL Injection finding: report file/function/query/input/attack vector/severity before fixing it. Then make the smallest prepared-statement/parameter-binding fix and regression-test it.

## Installer

Current installer structure:

```text
install.sh
installer-core.sh
install-dev.sh
install-dev-full.sh
```

`install-v2.sh` is retired. CI and documentation must not reference it as a current installer.

## Next recommended implementation order

1. Customer Service Details + Renewal.
2. Admin User/Search + Service Management.
3. IBSng Admin management.
4. Worker health + expiry notifications.
5. Complete card-to-card Order/Receipt/Approval/Provision lifecycle.
6. ZarinPal/Crypto adapters.
7. Later: wallet, coupon, referral/affiliate, reseller and growth features.
