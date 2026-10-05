# ATD Panel — Telegram Bot Admin

This document records the Telegram Bot Admin foundation and the current implementation boundary.

## Implemented

- Independent authorization by Telegram Numeric ID.
- Multiple Admin records and future-ready roles.
- Web management at `public/telegram-admins.php`.
- Dedicated Admin menu integrated into the existing `worker.php`.
- Admin service operations reuse the existing Provider/Service provisioning path.
- Admin actions are bound to the authorized Admin identity and audit logged without credentials.
- One-time/replay-safe Admin actions prevent successful provisioning callbacks from being executed twice.
- Customer Bot and Telegram Bot Admin navigation is integrated into the existing `section=bot` UI architecture.
- Existing Bot Settings, Bot Buttons and Bot Menu Preview remain untouched.

## Existing architecture rules

- There is one existing Telegram Worker. Do not create a second Worker or polling loop.
- Provider Core implementations remain protected.
- Provider/Plan selection is dynamic through the existing service catalog; the Bot must not hard-code a second provider catalog.
- `provider_key`, Provider Plan Key and Username Prefix are separate concepts.
- Existing `section=users` remains the shared Web User Management surface.
- Bot Usage Guides are customer-facing service/provider guides and are separate from Provider Admin Guides and the General Guide.

## Payment / card-to-card boundary

The existing `section=payment-settings` surface is the shared location for card-to-card configuration. The complete customer Order → Receipt → Admin Review → Approve/Reject → Provision lifecycle is still a staged feature and must be validated end-to-end before it is marked complete.

## Remaining Admin features

- Admin User/Search and profile workflow.
- Admin Service Management and provider-supported actions.
- IBSng username/account search, Shamsi expiry, quota and renewal.
- Service renewal with idempotency.
- Worker health/log/restart controls using the existing Worker.
- Notifications and expiry lifecycle.
- Complete payment/order/receipt lifecycle.

## MirzaBot policy

`mahdiMGF2/mirzabot` is reference-only for feature research. No source code, schema, naming, UI, menu structure or implementation is copied into ATD Panel.
