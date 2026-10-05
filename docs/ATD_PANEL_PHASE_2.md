# ATD Panel — Phase 2

Phase 2 adds shared administration surfaces without rewriting stable RouteBox, IBSng or MikroTik Provider flows.

## Completed

- [x] Existing shared `section=` UI architecture preserved.
- [x] Protected four-card/status UI preserved.
- [x] Provider-first navigation for RouteBox, IBSng and MikroTik.
- [x] Provider-neutral Users area remains the shared Web User Management surface.
- [x] Shared Payment Settings remains at `section=payment-settings`.
- [x] Existing Bot section remains at `section=bot` with Bot Settings, Bot Buttons and Bot Menu Preview preserved.
- [x] Customer Bot and Telegram Bot Admin overview navigation added within the existing section architecture.
- [x] Bot Usage Guides remain separate from Provider Admin Guides and the General Guide.
- [x] Independent Telegram Numeric ID Admin authorization foundation.
- [x] Multiple Admins/future roles.
- [x] Existing Worker integration; no second Worker.
- [x] Admin RouteBox/IBSng provisioning reuses existing Provider/service logic.
- [x] Admin audit/replay protection foundation.
- [x] Card-to-card configuration surfaced through the existing Payment Settings section.

## Current boundaries

The following are intentionally not marked complete yet:

1. Full customer Order → Receipt → Admin Review → Approval/Reject → Provision lifecycle.
2. Customer Service Details and complete Service lifecycle.
3. Customer Renewal.
4. Admin User/Search and Service Management.
5. IBSng account search/renew/edit through Telegram Admin.
6. Worker health/log/restart controls.
7. Expiry notifications.
8. Full source-wide security audit.

## Dynamic Provider rule

The Bot must read Provider/Plan information from the existing service catalog. It must not maintain a second hard-coded provider or plan catalog.

Current Provider keys include `routebox`, `ibsng` and `mikrotik_wireguard`. Future Providers receive their own `provider_key` and Provider-specific Plan metadata.

## Compatibility rules

- Existing Provider clients and provisioning remain protected.
- MikroTik peer/IP allocation remains inside the Provider integration.
- IBSng Provider Plan Key remains the real IBSng group/plan identifier.
- Username Prefix remains separate from Provider key and Provider Plan Key.
- Existing Worker polling/lock behavior remains protected.
- MirzaBot is reference-only and is not copied.
