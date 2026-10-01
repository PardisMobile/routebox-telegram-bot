# Changelog

All notable changes to RouteBox Telegram Bot are documented here.

The repository version is defined by [`VERSION`](./VERSION). Release notes below describe the important user-visible and operational changes for each beta.

## Unreleased — IBSng A1.24 Web Panel Integration

### IBSng

- Switched the development IBSng transport from the unavailable external JSON-RPC `:1237` assumption to the **IBSng A1.24 Free Edition Apache Web Panel**.
- The adapter authenticates with the existing IBSng Admin username/password at `/IBSng/admin/` and keeps the session in a temporary cookie jar.
- Added read-only connection testing and Group discovery through the existing Admin Web Panel.
- Added **Sync Groups** in the IBSng admin section to read all available Group Names from IBSng and store them locally for product/plan selection.
- Group synchronization is non-destructive: it only reads Group information and does not create, delete or modify IBSng Groups.
- Added user lookup by normal/Internet username through the existing A1.24 user-information page.
- Added test-user creation through the existing A1.24 `add_new_users.php` flow, preserving IBSng Core validation and permissions.
- Added an admin UI for testing connection, syncing Groups, reading an existing User, and creating a controlled test User.
- The Web Panel port now defaults to `80`; the previous `1237` default has been removed from the IBSng setup UI.
- Restored the **IBSng** entry in the main Admin Panel sidebar as a separate module entry point, without rewriting the existing RouteBox Admin UI.
- No IBSng database access is used.
- No requirement to expose the A1.24 Core XML-RPC listener on `127.0.0.1:1235` or to open JSON-RPC `:1237`.
- Product-to-IBSng Group mapping is intentionally designed to be manual (for example `یک ماهه` → `P1`); Group synchronization is used for discovery/verification and convenient selection, not as a requirement for product creation.
- Username/password assignment, renewal, usage display and full account management remain follow-up tasks after the connection/create/read smoke test.

### Smoke test

- `tools/ibsng-smoke-test.php` defaults to HTTP port `80` for A1.24.
- `get USERNAME` mode reads an existing account.
- `create GROUP [ISP] [CREDIT]` mode creates a controlled test account.
- The smoke test remains isolated from the production RouteBox installation.

### Architecture

- Existing RouteBox/WireGuard functionality remains unchanged.
- IBSng remains an isolated provider under `src/Integrations/IBSng/`.
- Payment, coupons, Telegram service categories and future MikroTik integration remain separate modules.

### Previous modular foundation

- Added an isolated provider architecture so new service backends can be added without rewriting the existing RouteBox core.
- Added a generic `ServiceProviderInterface` for provider-neutral service operations.
- Added an isolated `src/Integrations/Payment/` abstraction for future payment gateways.
- Added additive database structures for service categories, provider plans, subscriptions, orders, payment providers and coupons without repurposing existing RouteBox tables.
- Added architecture documentation in `docs/MODULAR_ARCHITECTURE.md`.
