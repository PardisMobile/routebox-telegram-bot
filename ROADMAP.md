# ATD Panel — Roadmap

> This file is the authoritative feature roadmap for the `ATD-Panel` development branch. Read it together with `ATD_PANEL_WORKING_NOTES.md` before changing the project.

## Architecture principles

- Existing Architecture First — New Features Second.
- Existing RouteBox, IBSng and MikroTik Provider Core is protected.
- Existing Telegram Bot and Worker are protected; no parallel Worker.
- Existing provisioning, authentication, peer/IP allocation and database semantics must not be duplicated or casually rewritten.
- Provider/Plan selection is dynamic through the existing service catalog. A future provider supplies its own `provider_key` and provider-specific plan metadata.
- `Provider Plan Key`, `provider_key` and Username Prefix are separate concepts. IBSng Provider Plan Key represents the real IBSng group/plan identifier.
- All Web/Admin UI remains inside the existing `?section=` architecture.
- `section=bot` remains the shared Bot area. Its Bot Settings, Bot Buttons and Bot Menu Preview are protected.
- Existing `section=users` remains the shared Web User Management surface.
- Bot Usage Guides, General Guide and Provider Administration Guides are distinct concepts.
- MirzaBot is reference-only and must never be copied as code, schema, naming, UI or architecture.

## Stable application checkpoint

- Last confirmed healthy application/UI checkpoint: `6026a16` (`6026a1612b2a30bc55b10e0d4f0258d1860974a`).
- Message: `fix(ui): restore compact desktop sidebar sizing baseline`.
- Later documentation commits do not replace this tested application checkpoint unless the application itself is tested and confirmed.

## ✅ Completed — Provider architecture / IBSng

- [x] Modular provider/service architecture.
- [x] Provider-based service routing/dispatching.
- [x] RouteBox / AmneziaWG provisioning remains operational.
- [x] IBSng A1.24 integration tested end-to-end.
- [x] IBSng authentication/session handling.
- [x] IBSng server and group mapping.
- [x] IBSng user lookup and test-user creation support.
- [x] Internet Username + Password provisioning.
- [x] `service_subscriptions` persistence.
- [x] Telegram Worker integration.
- [x] MikroTik WireGuard Provider integration.
- [x] Provider Plan Key semantics preserved; manual key required only where the Provider needs it, especially IBSng.

## ✅ Completed — ATD Panel UI baseline

- [x] Unified RouteBox / IBSng / MikroTik provider UI language.
- [x] Protected four-card/status UI preserved.
- [x] Users summary cards preserved.
- [x] Provider server lists and bounded pagination.
- [x] Compact desktop sidebar baseline restored at `6026a16`.
- [x] Mobile drawer/navigation improvements.
- [x] Persian/English and Light/Dark parity maintained.
- [x] Provider plan action styling unified without changing Provider Core.
- [x] RouteBox public UI alias uses `section=routebox` while legacy/internal compatibility remains protected.
- [x] Existing server/provider capabilities remain the source of UI actions.

## ✅ Completed — Telegram Bot Admin foundation

- [x] Independent Telegram Numeric ID authorization.
- [x] Multiple Telegram Admin records.
- [x] Future-ready role data model (`owner`, `admin`, `support`, `finance`, `operator`).
- [x] Web management at `public/telegram-admins.php`.
- [x] Dedicated Admin menu integrated into the existing Worker.
- [x] No second Worker or polling loop.
- [x] Admin RouteBox/IBSng service creation uses existing provider/service provisioning.
- [x] Admin provisioning action replay protection.
- [x] Admin audit logging without credentials.
- [x] Customer Bot/Admin Bot overview navigation under existing `section=bot`.
- [x] Existing Bot Settings / Buttons / Menu Preview preserved.
- [x] Existing Bot Usage Guides remain separate from Provider Admin Guides.
- [x] General Guide remains separate from service-specific guides.

## 🟡 In progress — Payment / Card-to-Card

### Web Panel

- [x] Existing `section=payment-settings` is the shared payment configuration surface.
- [x] Card-to-card configuration is exposed there.
- [x] No second payment-settings page is required.

### Functional lifecycle

- [ ] Customer selects dynamic Provider/Plan.
- [ ] Create provider-neutral Order.
- [ ] Create Payment state.
- [ ] Display configured card-to-card instructions.
- [ ] Customer submits receipt.
- [ ] Admin sees pending receipts.
- [ ] Admin approves/rejects.
- [ ] Approval is idempotent.
- [ ] Provision only after valid approval.
- [ ] Existing Provider provisioning is used.
- [ ] Customer receives credentials/config.
- [ ] Failure becomes explicit and retryable without duplicate provisioning.
- [ ] Full workflow must be tested end-to-end before marked complete.

Future:

- [ ] ZarinPal adapter.
- [ ] Crypto adapter.
- [ ] Verified callback/state machine.
- [ ] Invoice/payment history.
- [ ] Coupons.

## 🔴 Phase 1 — Customer Service Management

- [ ] Complete `My Services` provider-neutral view.
- [ ] Service Details.
- [ ] Provider-aware connection/configuration information.
- [ ] Status and lifecycle display.
- [ ] Secure service ownership validation.
- [ ] Re-send/retrieve configuration where Provider supports it.
- [ ] Renewal using the existing real Provider Plans.
- [ ] Renewal idempotency.
- [ ] Provider failure must never produce a false successful renewal.

## 🔴 Phase 2 — Telegram Admin Service Management

- [ ] Admin service search.
- [ ] Search by Telegram ID, username, phone where stored, service username, service ID and Order ID where applicable.
- [ ] User profile/services/orders/payments view.
- [ ] Service details.
- [ ] Provider-supported Enable/Disable actions.
- [ ] Provider-supported Renewal.
- [ ] Config retrieval.
- [ ] Re-provision only where the existing Provider capability safely supports it.
- [ ] All sensitive actions server-side authorized and audit logged.

## 🔴 Phase 3 — IBSng User Management

- [ ] Search IBSng user by username.
- [ ] Real account information from IBSng.
- [ ] Expiry date with correct timezone.
- [ ] Persian/Shamsi display.
- [ ] Real traffic/quota where supported.
- [ ] Renewal using existing ATD/IBSng Plans and real Provider Plan Key.
- [ ] Edit only Provider-supported fields.
- [ ] Existing IBSng creation/test-user functionality reused, not duplicated.

## 🟠 Phase 4 — Username / Password Generation

- [ ] Configurable Username Prefix.
- [ ] Default IBSng prefix remains `rb`.
- [ ] Configurable random suffix length.
- [ ] Configurable password length/character policy.
- [ ] Validate lengths/characters and prevent collisions.
- [ ] Keep Prefix separate from `provider_key` and Provider Plan Key.
- [ ] Preserve existing credentials when new settings change.

## 🟠 Phase 5 — Worker Operations

Use the existing Worker only:

`routebox-telegram-bot-dev.service` / the correct production equivalent.

- [ ] Worker status.
- [ ] Health monitoring.
- [ ] Correct restart/reload control.
- [ ] Recent logs/diagnostics.
- [ ] Expiry notifications: 7/3/1 days and expired state.
- [ ] Retry/failure state for jobs where appropriate.
- [ ] Never replace polling/lock architecture without explicit review.

## 🔐 Phase 6 — Full Security Audit

- [ ] Source-wide SQL Injection audit.
- [ ] Prepared statements / parameter binding for variable SQL.
- [ ] Authentication/session security.
- [ ] Telegram Admin authorization bypass/IDOR/replay.
- [ ] CSRF.
- [ ] XSS/output encoding.
- [ ] Privilege escalation.
- [ ] Command injection.
- [ ] SSRF.
- [ ] Path traversal/file disclosure.
- [ ] Unsafe receipt/file upload handling.
- [ ] Secrets exposure.
- [ ] Rate limiting.
- [ ] Log/credential leakage.
- [ ] Database permissions and backup security.
- [ ] Dependency/configuration review.

SQL Injection rule: if a real exploitable query is found, report file/function/query/input/attack vector/severity before fixing it. Then make the smallest safe prepared-statement change and run regression tests.

## 🟢 Later — Growth / Product Features

These are intentionally after the core service/payment/admin lifecycle:

- [ ] Wallet / balance.
- [ ] Refund/credit.
- [ ] Coupon / gift code.
- [ ] Referral / affiliate.
- [ ] Cashback.
- [ ] Agent/reseller.
- [ ] Bulk purchase/manual sale workflows.
- [ ] Mini App, if product requirements justify it.

## Installer / CI

- [x] Production entrypoint: `install.sh`.
- [x] Production implementation: `installer-core.sh`.
- [x] Development entrypoints: `install-dev.sh`, `install-dev-full.sh`.
- [x] Retired `install-v2.sh` removed from the current installer structure.
- [x] CI validation aligned with the four-file installer structure.
- [x] Installer documentation updated to the current structure.

## Documentation rule

After every completed feature slice:

1. Update `ROADMAP.md`.
2. Update `ATD_PANEL_WORKING_NOTES.md`.
3. Update `CHANGELOG.md`.
4. Record acceptance criteria status.
5. Record tests/regression status.
6. Create a clear independent commit.

## Definition of Done

A feature is not Done merely because code exists. It must satisfy functional happy/error/edge paths, security/authorization/input validation, regression of existing Providers/Worker/Users/Plans, desktop/mobile and Persian/English compatibility where relevant, documentation, tests/lint and a traceable commit.
