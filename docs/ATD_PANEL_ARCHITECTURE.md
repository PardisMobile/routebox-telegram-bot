# ATD Panel — Architecture

## Goal

ATD Panel is the provider-neutral administration layer for RouteBox Telegram Bot. It must not become a RouteBox-only panel.

Current working Providers are RouteBox, IBSng and MikroTik WireGuard. Future Providers must fit the same Provider → Server → Plan → Guide → Service/Provisioning architecture.

## Core architecture

```text
                    ATD Panel
                       │
        ┌──────────────┼──────────────┐
        │              │              │
     Web Panel     Telegram Bot    Future API
        │              │
        │           Existing Worker
        │              │
        └──────────────┼──────────────┘
                       │
                 Service Layer
                       │
                Provider Abstraction
             ┌─────────┼─────────┐
             │         │         │
          RouteBox   IBSng   MikroTik
```

Existing Provider Core and Worker behavior are protected. New UI/Admin features orchestrate existing services rather than moving protocol logic into Telegram handlers.

## Provider / Plan discovery

The Bot does not own a hard-coded provider catalog.

```text
Provider
   ↓
provider_key
   ↓
Service Category
   ↓
Provider-owned Plans
   ↓
Telegram / Web UI
```

Current Provider keys include `routebox`, `ibsng` and `mikrotik_wireguard`.

A future Provider supplies its own key and Provider-specific Plan metadata. The Bot should discover it through the existing service architecture.

`Provider Plan Key`, `provider_key` and Username Prefix are separate concepts. IBSng Provider Plan Key is the real IBSng group/plan identifier and must not be confused with a generated username prefix.

## Existing Web UI architecture

All shared Web UI remains based on the existing `?section=` model.

Important protected destinations:

- `section=users` — shared User Management.
- `section=payment-settings` — shared Payment Settings.
- `section=bot` — shared Telegram Bot area.

The four protected ATD Stats/status cards are not to be removed, reordered or redesigned casually.

## Telegram Bot UI architecture

`section=bot` contains the existing Bot Settings, Bot Buttons and Bot Menu Preview. Customer Bot and Telegram Bot Admin are child destinations within the same section architecture; do not create a separate Web application/page architecture for Bot Admin.

Bot Usage Guides are customer-facing service/provider usage instructions. They are separate from:

1. the General Guide; and
2. Provider Administration Guides.

## Shared Provider contract

Each Provider owns:

1. Provider identity/key.
2. Server configuration and connection tests.
3. Provider-specific administration guide.
4. Provider-scoped Plans.
5. Provider-specific operations/capabilities.
6. Provisioning/service adapter.

The shared ATD layer owns:

- common shell/navigation
- authentication/CSRF/security
- reusable cards/tables/forms
- shared Plan presentation
- Users
- Payment Settings
- Orders/Payments foundations
- Telegram Bot/Admin orchestration
- customer service guides
- updates/recovery UX

## Users

`section=users` is the existing shared Web User Management surface. Telegram Admin must consume the same underlying user/service data and authorization model; it must not create a second Web user-management implementation.

## Payment Settings

Payment is a shared subsystem. The existing `section=payment-settings` surface is the authoritative configuration location.

Card-to-card is the first manual payment method. The complete Order → Receipt → Review → Approval → Provision lifecycle remains staged until end-to-end validation is complete.

Future adapters include ZarinPal and Crypto. Adding a gateway must not require Provider Core changes.

## Telegram Admin

Telegram Admin authorization is independent from IBSng `owner` / `owner_name` and uses Telegram Numeric IDs. Multiple Admins and future roles are supported.

Admin operations must use the existing Worker and existing Provider/service layer. No second Worker and no duplicate Provider provisioning are allowed.

## MirzaBot reference policy

MirzaBot is reference-only for feature research. Its source code, schema, naming, UI, menu structure and implementation are not dependencies of ATD Panel.

## Future Provider rule

Adding a Provider should normally require:

```text
New Provider
   ├─ integration/client
   ├─ server adapter
   ├─ Provider key/metadata
   ├─ Provider Plans
   ├─ Provider admin guide
   ├─ customer usage guide(s)
   └─ service/provisioning adapter

No redesign of:
   ├─ shared section-based UI
   ├─ Users
   ├─ Payment Settings
   ├─ Telegram Bot navigation
   └─ existing Provider integrations
```

## Compatibility requirements

- Do not break RouteBox provisioning.
- Do not break IBSng provisioning.
- Do not break MikroTik WireGuard provisioning or IP allocation.
- Do not create a second Worker.
- Do not duplicate Provider protocol logic in the Bot.
- Do not hard-code future Provider names or Plans into shared Bot code.
- Preserve existing database semantics and user/service ownership boundaries.
