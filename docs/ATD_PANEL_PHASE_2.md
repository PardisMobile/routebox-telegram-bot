# ATD Panel — Phase 2

This phase adds the shared administration surfaces without rewriting the stable RouteBox, IBSng or MikroTik provisioning flows.

## Implemented

- Original tested sidebar SVG icon set preserved.
- Sidebar width/spacing improved to prevent provider-name clipping.
- Provider-first navigation remains:
  - RouteBox Servers → Plans → Provider Guide
  - IBSng Servers → Plans → Provider Guide
  - MikroTik WireGuard → Plans → Provider Guide
- Telegram Bot → Usage Guides.
- Provider-neutral Users list with search by Telegram ID, username and name.
- User Details with provider, plan, status and expiry visibility.
- Shared Payment Settings foundation using the existing payment schema.
- Shared guide storage with a preserved General Guide migrated from `guide_fa` / `guide_en` on first initialization.
- Per-service guide records linked to `service_categories`.
- Provider Admin Guides for RouteBox, IBSng and MikroTik WireGuard.
- Initial disabled ZarinPal and Crypto Gateway payment-provider entries.

## Compatibility rules

The phase is additive. Existing provider clients, provisioning paths, MikroTik/IBSng/RouteBox server screens and the working Telegram Bot flows are not replaced merely to introduce the new shared admin surfaces.

## Remaining work

1. Move the Telegram Worker `guide` callback to a service selector backed by `telegram_service_guides`.
2. Add shared subscription actions to User Details, with provider-specific actions delegated to provider adapters.
3. Add Telegram Bot Admin authorization and admin-only service operations.
4. Complete payment order/callback/verification lifecycle and real gateway adapters.
5. Add Worker health/restart/log controls.
6. Register future providers through the same Provider → Server → Plan → Guide → Provisioning architecture.

## Future-provider rule

Adding V2Ray or another provider should add an integration/adapter and metadata, not a second UI architecture. Shared Plans, Users, Payment Settings and Telegram guide management remain provider-neutral.
