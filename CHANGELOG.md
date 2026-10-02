# Changelog

Important user-visible and operational changes for RouteBox Telegram Bot.

Version source of truth: [`VERSION`](./VERSION).

## Unreleased — Modular Services / IBSng

### Completed

- Added provider-isolation architecture with `ServiceProviderInterface`.
- Added RouteBox and IBSng provider-based provisioning.
- Added IBSng A1.24 Web Panel integration.
- Added IBSng login and session management.
- Added real IBSng user creation.
- Added Internet Username and password assignment.
- Added subscription persistence in `service_subscriptions`.
- Added Plan → Server → Group mapping.
- Added IBSng provisioning from Telegram flow.
- Fixed IBSng client loading inside Worker.
- Completed real IBSng provisioning verification.
- Preserved existing RouteBox provisioning flow.

### Telegram Worker

- Worker remains provider-neutral.
- RouteBox and IBSng use the same operational worker architecture.
- Single-instance protection remains enabled.

## 🗺️ Next Development Phase

### Telegram Bot Admin

- [ ] Independent admin permission system separate from IBSng
- [ ] Multiple Telegram ID support
- [ ] Admin management from Web Panel
- [ ] Dedicated Telegram Admin menu

### Admin Skip Payment

- [ ] Allow Bot Admin to create services without payment
- [ ] Keep normal customer payment flow unchanged

### IBSng Management

- [ ] Username search
- [ ] User information display
- [ ] Persian expiry date display
- [ ] Traffic usage display
- [ ] Create IBSng user from Bot Admin
- [ ] Server and Plan/Group selection
- [ ] Configurable username prefix (`rb`, `tgbot`, custom)
- [ ] Renewal based only on RouteBox defined IBSng plans
- [ ] User edit support
- [ ] Move IBSng test tools into Admin Panel

### Worker Management

- [ ] Worker status display
- [ ] Restart Worker from Admin Panel
- [ ] Worker health monitoring

### Payment

- [ ] Complete Order lifecycle
- [ ] Coupons
- [ ] Payment verification
- [ ] ZarinPal adapter
- [ ] Provision after verified payment only

## Development rules

- `owner` and `owner_name` are IBSng-specific only.
- Telegram Bot Admin is independent from IBSng permissions.
- Tested IBSng flow will not be rewritten.
- Existing customer payment flow will not change.
- New features extend the existing provisioning architecture.
