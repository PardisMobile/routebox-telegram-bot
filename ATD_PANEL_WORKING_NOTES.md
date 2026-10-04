# ATD Panel — Working Notes / Handoff

> Persistent handoff context for future ChatGPT conversations. Read this file and `ROADMAP.md` before changing the RouteBox Telegram Bot / ATD Panel project.

## Project

- Repository: `PardisMobile/routebox-telegram-bot`
- Main ATD Panel development branch: `ATD-Panel`
- Development path: `/opt/routebox-telegram-bot-dev`
- Development web service: `routebox-telegram-bot-dev-web@8092.service`
- Product/UI name: **ATD Panel**
- Current branding direction: **ATD Panel — server and telegram bot control center** / Multi-Service Control Center.

## Critical rules — DO NOT VIOLATE

1. Existing RouteBox, IBSng and MikroTik provider logic is production-critical and must not be casually rewritten.
2. UI work is UI-only unless a functional change is explicitly requested and reviewed first.
3. Preserve provisioning, authentication, peer allocation, WireGuard IP-pool behavior, provider APIs, worker behavior and database semantics.
4. Before touching core/provider code, identify the exact file/function and explain why the change is necessary.
5. `Provider Plan Key`, `provider_key`, provider server IDs and generated username prefixes are separate concepts.
6. `Provider Plan Key` is manually required for IBSng because it represents the IBSng group name. RouteBox/MikroTik must not be forced to enter it manually when their provider owns/generates it.
7. Telegram Bot Admin permissions are independent from IBSng `owner` / `owner_name`.
8. Normal customer payment/provisioning must remain unchanged when adding Admin features.
9. Every variable SQL value must use PDO prepared statements / parameter binding. A source-wide SQL-injection audit is still pending until explicitly completed.
10. Never expose secrets, bot tokens, passwords or private credentials in client-visible HTML/JS or source-controlled UI assets.
11. Keep a known-good checkpoint before risky changes and run syntax/tests after changes.

## Last known-good application checkpoint

- Commit: `6026a1612b2a30bc55b10e0d4f0258d1860974a9`
- Short SHA: `6026a16`
- Message: `fix(ui): restore compact desktop sidebar sizing baseline`
- Branch: `ATD-Panel`
- Date: 2026-10-04

This is the last confirmed healthy application/UI checkpoint. Documentation commits may be newer; do not interpret a documentation-only commit as a new tested application checkpoint.

## Completed UI state

### Dashboard / shell

- ATD Panel branding applied.
- Four protected dashboard summary cards preserved.
- Light and Dark themes preserved.
- Modern multi-color palette selector implemented.
- Palette changes affect the theme rather than only a single button.
- Persian typography improved.
- Existing working Persian/English language mechanism preserved.
- Duplicate sidebar language button removed when the working language control already existed elsewhere.
- Persian sidebar navigation normalized to Persian.

### Responsive behavior

- Desktop sidebar restored to compact sizing at `6026a16`.
- Mobile uses a menu/drawer rather than the permanent desktop sidebar.
- Mobile menu labels/navigation were improved so destinations are understandable.
- Mobile drawer no longer unnecessarily consumes the whole viewport.
- Firefox desktop sidebar sizing was fixed to the intended compact baseline.

### Provider server pages

- RouteBox / IBSng / MikroTik server sections share a unified UI language.
- Server list appears before Add Server when servers exist.
- Server pagination target is **5 items per page across server sections**.
- Public RouteBox UI alias changed from `section=servers` to `section=routebox`; preserve internal/legacy compatibility.
- RouteBox connected-server flags use cross-browser image rendering instead of emoji-only rendering.
- RouteBox server delete UI exposed using the existing supported backend action.
- MikroTik server delete UI exposed using the existing supported backend capability.
- Status, location/flag and ping information preserved.
- Do not create unbounded visible server/peer lists.

### Provider Plans

- RouteBox / IBSng / MikroTik plan-card action styling unified.
- Edit Plan / Disable / Delete aligned in one action row.
- Edit panel closes explicitly and via Escape.
- Provider Plan Key is manually required only for IBSng.
- IBSng Provider Plan Key must exactly match the selected IBSng group name.
- RouteBox/MikroTik internal/provider-generated keys remain untouched.
- Provider plan UI is kept separate from provider protocol/provisioning logic.

### Protected four-card/status UI

The four cards/status blocks that were difficult to implement are protected. Do not remove, reorder or substantially change their data source/layout without explicit approval.

## Functional architecture already working

- Modular provider architecture is operational.
- RouteBox / AmneziaWG provisioning is operational.
- IBSng A1.24 integration is operational and tested end-to-end.
- MikroTik WireGuard provider is integrated through the modular architecture.
- `ServiceProvisioner` dispatches by `service_plans.provider_key`.
- Existing provider provisioning should remain provider-neutral at the worker level.
- Existing IBSng flow must not be rewritten merely to support Bot Admin.

## IBSng — already working

- A1.24 authentication/session handling.
- Server configuration and group mapping.
- Group listing/synchronization support.
- Real user creation.
- Internet Username + Password assignment.
- Plan → Server → Group provisioning.
- `service_subscriptions` persistence.
- Telegram provisioning flow.
- Worker integration.
- Real provisioning verification.

## Username / password generation — pending refinement

The automatic credentials are currently longer than desired. Next refinement:

- Configurable username prefix/prefix policy.
- Configurable random suffix length.
- Explicit shorter password policy.
- Safe defaults and character/length validation.
- Keep IBSng default prefix `rb` until changed from Admin Panel.
- Do not confuse username prefix with `provider_key` or `provider_plan_key`.
- Investigate/standardize any `mt` prefix used by MikroTik/WireGuard only after checking all existing code/call sites. The provider key is `mikrotik_wireguard`; do not rename it casually.

## Telegram Bot Admin — next major phase

Not completed yet.

### Authorization

- Independent Telegram Bot Admin authentication/authorization.
- Multiple Telegram numeric IDs.
- Admin management from Web/Admin Panel.
- Dedicated Admin menu for authorized users only.
- Completely separate from IBSng `owner` / `owner_name`.

### Service operations

- Manage services from Telegram as an authorized admin.
- Create RouteBox service without customer payment.
- Create IBSng service without customer payment.
- Provider-neutral payment bypass/authorization path.
- Audit/admin action records.

### Manual card-to-card payment flow

Primary business flow to support:

1. Customer selects service/plan.
2. Bot provides card-to-card payment instructions.
3. Customer pays.
4. Customer sends payment receipt/image.
5. Admin Bot shows pending receipt/order to authorized admin.
6. Admin reviews and approves or rejects.
7. Only after approval, the service is activated/provisioned.
8. Customer receives the correct service credentials/configuration (RouteBox `.conf`/QR, IBSng credentials, etc.).
9. Payment/order/provisioning states are auditable.

Design this through the payment/order abstraction so later ZarinPal/crypto gateways do not require a rewrite.

### Feature research reference

The user provided `https://github.com/mahdiMGF2/mirzabot` and a separate WireGuard-only bot screenshot as feature references only. They are not the same project and must not be copied. First perform a feature-gap comparison; implement only after user approval.

## IBSng management through Bot Admin — pending

- Search by username.
- Display account/user information.
- Persian/Shamsi expiry display.
- Traffic/quota usage for volume services.
- Create IBSng user from Bot Admin.
- Select server and configured plan/group.
- Renew only from IBSng plans defined in RouteBox Admin Panel.
- Edit username/password/group/plan where existing provider capabilities safely support it.
- Move standalone IBSng test tooling into safe panel orchestration where appropriate.

## Worker management — pending

- Worker status.
- Restart/reload control.
- Correct systemd service targeting.
- Health monitoring.
- Recent logs/diagnostics.

## Payment architecture — pending

- Complete order lifecycle/state machine.
- Manual/card-to-card receipt review and approval.
- Coupons.
- Payment callbacks/state handling.
- ZarinPal adapter.
- Crypto gateway adapter.
- Verified-payment-only customer provisioning.
- Invoice/payment history.

## Security audit — high priority / pending

Perform a source-wide review of the full application and database layer for:

- SQL Injection and dynamic SQL identifiers.
- Authentication/authorization and privilege escalation.
- Telegram Admin authorization bypass.
- CSRF on state-changing Web requests.
- XSS/output escaping.
- Session/cookie security.
- Secrets/credentials exposure in HTML/JS/logs/Git history/errors.
- Path traversal and local/remote file inclusion.
- Unsafe file upload/receipt handling.
- Command injection/shell execution.
- SSRF in server/provider URL handling.
- Open redirects.
- Rate limiting/brute force.
- Error disclosure.
- Database/backup permissions.
- systemd/service privileges and writable paths.
- TLS/HTTPS and public-port exposure.
- Telegram token/webhook handling.
- Password encryption/storage.
- Audit logging without secret leakage.
- Dependency/package risks.

For SQL Injection specifically: before changing anything, report whether a real injectable query exists, where it is reachable, and why. Then convert all variable SQL to prepared statements/parameter binding. Do not mark the audit complete until the whole source tree has been checked.

## Database safety

- Preserve existing data.
- Use backward-compatible migrations.
- Do not casually delete/rename existing columns.
- Keep provider-specific data isolated.
- Use transactions where payment approval + provisioning state changes must be atomic.

## Remaining UI follow-up

- Replace the Dashboard Project Website placeholder when the real URL is supplied.
- Continue visual polish only after regression checks.
- Test Chrome, Edge, Firefox and mobile browsers.
- Maintain light/dark parity and Persian/English parity.

## New-chat handoff

Read this file and `ROADMAP.md` first. Treat `6026a16` as the last known-good application checkpoint unless a newer commit has been explicitly tested and confirmed by the user. Do not ask the user to re-explain the project; ask only for the missing decision needed for the next task.

## Telegram Bot Admin — implementation slice completed

- Independent Telegram Numeric ID authorization is implemented in src/Telegram/AdminBot.php.
- Multiple Admins and future-ready roles are supported.
- Web management is available at public/telegram-admins.php.
- The dedicated Admin menu is integrated into the existing worker.php; no second Worker was created.
- RouteBox and IBSng admin service creation without customer payment uses the existing ServiceProvisioner.
- Successful Admin provisioning callbacks cannot provision the same action twice.
- Admin provisioning actions are audit logged without credentials.
- Card-to-card Order/Payment/Receipt approval remains the next slice.
