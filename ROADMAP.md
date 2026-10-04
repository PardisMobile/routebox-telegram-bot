# RouteBox Telegram Bot Roadmap

## ✅ Completed — IBSng modular service milestone

- [x] Modular service architecture
- [x] Provider-based provisioning
- [x] RouteBox and IBSng independent service providers
- [x] IBSng A1.24 Web Panel / API integration
- [x] IBSng authentication and session handling
- [x] IBSng server configuration foundation
- [x] IBSng group mapping
- [x] IBSng group listing / synchronization support
- [x] IBSng user lookup / user information operations
- [x] IBSng test-user creation support
- [x] End-to-end IBSng account provisioning from Telegram
- [x] Automatic Internet Username + password provisioning
- [x] Provider/server/group-aware IBSng provisioning
- [x] `service_subscriptions` persistence
- [x] Worker integration with the isolated `IBSngClient`
- [x] One IBSng account for the configured OpenVPN / Cisco / L2TP access methods
- [x] Real IBSng A1.24 provisioning flow tested successfully

> **Architecture note:** IBSng `owner` / `owner_name` is an IBSng-specific concept. It is separate from Telegram Bot Admin permissions.

## 🎨 Completed — ATD Panel UI normalization

### Provider server pages

- [x] Unified RouteBox / IBSng / MikroTik server status-card visual language.
- [x] Preserved server status, country flag/location and ping information.
- [x] Unified Connected / Running status-pill presentation.
- [x] Moved provider server lists above Add Server when servers exist.
- [x] Server pagination standardized to **5 visible server items per page** across server sections.
- [x] RouteBox public UI alias changed from `section=servers` to `section=routebox`; legacy/internal compatibility remains protected.
- [x] RouteBox connected-server flags converted to cross-browser image flags so Chrome/Edge do not fall back to `FR`/`IR` text while Firefox remains correct.
- [x] RouteBox server delete action exposed using the existing supported backend action.
- [x] MikroTik server delete action exposed using the existing supported backend capability.
- [x] Added IBSng Test Create User UI while keeping the tested provider flow intact.

### Users summary / protected cards

- [x] Added four page-level Users cards directly under the Users heading.
- [x] RouteBox Users count uses provider subscriptions/users.
- [x] IBSng Users count uses provider subscriptions/users.
- [x] MikroTik Users count uses provider subscriptions/users.
- [x] Telegram Bot Users count uses total registered Telegram users.
- [x] Protected the four-card/status/dashboard layout from casual redesign.

### Provider Plans

- [x] Unified RouteBox / IBSng / MikroTik plan-card action styling.
- [x] `Edit Plan`, `Disable`, and `Delete` are aligned in the same action row.
- [x] Added explicit Close and Escape-to-close behavior for plan edit panels.
- [x] `Provider Plan Key` is manually required only for IBSng, where it represents the IBSng group name.
- [x] RouteBox/MikroTik provider-generated/internal plan keys remain untouched.
- [x] Provider plan management remains separate from provider protocol/provisioning logic.

### Theme / localization / responsive UI

- [x] Light and Dark theme parity maintained.
- [x] Modern multi-color palette selector added and made functional across the theme.
- [x] Persian typography improved.
- [x] Existing working Persian/English language switching preserved.
- [x] Duplicate sidebar language control removed.
- [x] Persian sidebar navigation normalized.
- [x] Desktop sidebar returned to compact sizing; stable baseline is commit `6026a16`.
- [x] Firefox desktop sidebar sizing fixed.
- [x] Mobile menu/drawer behavior improved so it does not unnecessarily consume the full viewport.
- [x] Mobile navigation labels made understandable without opening every destination blindly.

### Scalability and compatibility

- [x] Added bounded MikroTik peer pagination; large peer sets must not render as one unbounded page.
- [x] Avoided an unbounded visible 1000-row peer list.
- [x] Fixed Chrome/Edge flag rendering with image flags and safe country-code fallback.

### Protected implementation rule

- [x] ATD Panel UI work is isolated from the working RouteBox, IBSng and MikroTik provider implementations.
- [x] Existing provisioning, authentication, peer allocation, IP-pool behavior and provider APIs remain protected from UI-only refactors.
- [x] Provider keys, Provider Plan Key semantics and existing provisioning contracts are protected.

## 🧭 Stable application checkpoint

- [x] Last confirmed healthy ATD Panel application/UI checkpoint: `6026a1612b2a30bc55b10e0d4f0258d1860974a` (`6026a16`).
- [x] Checkpoint message: `fix(ui): restore compact desktop sidebar sizing baseline`.
- [x] `ATD_PANEL_WORKING_NOTES.md` added as the persistent ChatGPT handoff document.

> Documentation commits after `6026a16` are not automatically considered tested application checkpoints.

## 🔜 Upcoming Phase 1 — Telegram Bot Admin

### Independent permission system

- [x] Create an independent Telegram Bot Admin authentication / authorization layer.
- [x] Configure Telegram Bot Admin users from the Web/Admin Panel through `public/telegram-admins.php`.
- [x] Support **multiple Telegram numeric IDs**; do not limit the system to one administrator ID.
- [x] Provide a dedicated/admin Telegram menu for authorized Bot Admin users.
- [x] Keep Telegram Bot Admin permissions completely separate from IBSng `owner` / `owner_name`.

### Admin service management

- [x] Allow authorized Telegram Bot Admin users to manage services through Telegram.
- [x] Allow Bot Admin to create a new RouteBox service without customer payment.
- [x] Allow Bot Admin to create a new IBSng service without customer payment.
- [x] Design the payment bypass as provider-neutral by routing through the existing `ServiceProvisioner` dispatch.
- [x] Keep the normal customer payment flow unchanged.
- [x] Record admin provisioning actions and results in a dedicated audit log.

### Manual / card-to-card payment workflow

- [ ] Customer selects a service/plan and receives card-to-card payment instructions.
- [ ] Customer submits a payment receipt/image through Telegram.
- [ ] Authorized Admin Bot menu shows pending payment/receipt items.
- [ ] Admin can approve or reject a receipt.
- [ ] Provisioning/activation happens **only after payment approval**.
- [ ] Customer receives the correct provider credentials/configuration after approval.
- [ ] Order, payment, approval/rejection and provisioning states are auditable.
- [ ] Keep the payment/order layer provider-neutral for future ZarinPal/crypto gateways.

### Reference feature research

- [x] Compare ATD Panel capabilities against the user-provided `mahdiMGF2/mirzabot` source and the separate WireGuard-only bot screenshot.
- [x] Treat both as feature references only; do not copy code or architecture blindly.
- [x] Produce the feature-gap list before implementation.

## 🔵 Upcoming Phase 2 — IBSng user management through Bot Admin

### Search and information

- [ ] Search IBSng users by username.
- [ ] Display IBSng username information.
- [ ] Display account information.
- [ ] Display account expiry date.
- [ ] Display expiry date in Persian/Shamsi format.
- [ ] Display traffic/quota usage when the IBSng service is quota-based.

### Creation / renewal / editing

- [ ] Create IBSng user from Bot Admin.
- [ ] Select IBSng server and configured Plan/Group.
- [ ] Renew an IBSng user.
- [ ] Renewal must use the IBSng plans already configured in the RouteBox Admin Panel.
- [ ] Do **not** introduce a separate hard-coded renewal-plan catalog.
- [ ] Edit IBSng user information where supported by existing provider capabilities.
- [ ] Add future safe account-management actions as appropriate.

## ⚙️ Upcoming Phase 3 — Credential generation and IBSng administration tools

### Configurable username/password generation

- [ ] Make generated username prefix configurable from the Admin Panel.
- [ ] Keep current IBSng generated prefix `rb` as the default.
- [ ] Allow custom prefixes such as `tgbot`.
- [ ] Make generated random suffix length configurable/shorter than the current long form.
- [ ] Define a safe, explicit password length/policy rather than unnecessarily long generated passwords.
- [ ] Validate prefix characters and maximum credential lengths.
- [ ] Keep username-prefix settings separate from `provider_key` and `provider_plan_key`.
- [ ] Inspect/standardize the `mt` prefix used by MikroTik/WireGuard only after checking all existing call sites; do not rename `mikrotik_wireguard`.

### IBSng test-user UI

- [x] Add IBSng test-user creation to the IBSng section of the Admin Panel.
- [x] Reuse the previously working test/provisioning path through the UI.
- [ ] Move the standalone `test-ibsng-account.php` workflow out of the normal user workflow if still exposed.
- [ ] Keep the existing working IBSng test/provisioning code intact while adding future UI orchestration.

## 🔧 Upcoming Phase 4 — Telegram Worker operations

- [ ] Add Worker status to the Telegram Bot section of the Admin Panel.
- [ ] Add a restart/reload button for the Telegram Bot Worker.
- [ ] Ensure the control targets the correct existing Worker systemd service.
- [ ] Do not change or break the current working Worker behavior.
- [ ] Add Worker health monitoring.
- [ ] Add recent Worker logs / diagnostics.

## 🔐 Upcoming Phase 5 — Full security audit / hardening

- [ ] Audit the **entire PHP/source tree and database layer** for SQL Injection.
- [ ] For every variable SQL value, use PDO prepared statements / parameter binding.
- [ ] Audit dynamic SQL identifiers and enforce strict whitelisting where binding is not possible.
- [ ] Before each SQLi fix, report whether a real injectable query was found, where it is reachable and why.
- [ ] Audit authentication/authorization and privilege escalation.
- [ ] Audit Telegram Bot Admin authorization bypass paths.
- [ ] Audit CSRF on all state-changing Web Panel requests.
- [ ] Audit XSS/output escaping.
- [ ] Audit session fixation, cookie flags and logout behavior.
- [ ] Audit secrets/credentials exposure in HTML, JavaScript, logs, Git history and errors.
- [ ] Audit path traversal/local and remote file inclusion.
- [ ] Audit file upload/receipt handling.
- [ ] Audit command injection/shell execution.
- [ ] Audit SSRF in provider/server URL handling.
- [ ] Audit open redirects.
- [ ] Add/verify rate limiting and brute-force protection.
- [ ] Audit production error disclosure.
- [ ] Verify database and backup permissions.
- [ ] Verify systemd/service privileges and writable paths.
- [ ] Verify TLS/HTTPS and public-port exposure.
- [ ] Verify Telegram token/webhook handling.
- [ ] Verify password encryption/storage.
- [ ] Verify audit logs do not leak secrets.
- [ ] Review dependency/package risks.
- [ ] Do not mark security review complete until source-wide verification is actually performed.

## 💳 Future payment system

- [ ] Complete order lifecycle/state machine.
- [ ] Coupon management and enforcement.
- [ ] Payment callback endpoints.
- [ ] Payment verification state machine.
- [ ] Manual/card-to-card receipt workflow.
- [ ] ZarinPal adapter.
- [ ] Crypto gateway adapter.
- [ ] Verified-payment-only customer provisioning.
- [ ] Invoice and payment history.

## 🌐 Remaining small UI follow-up

- [ ] Replace the Dashboard Project Website placeholder when the real project URL is supplied.
- [ ] Continue visual polish only after regression checks.
- [ ] Re-test desktop Chrome, Edge, Firefox and mobile browsers after functional changes.
- [ ] Maintain light/dark parity and Persian/English parity.

## 📦 Completed — Installer refactor and deployment documentation

- [x] `install.sh` remains the production user-facing entry point.
- [x] `install-v2.sh` renamed to `installer-core.sh`.
- [x] `install.sh` updated to download and execute `installer-core.sh`.
- [x] `install-dev.sh` remains the development entry point for `feature/modular-services-ibsng`.
- [x] `install-dev-full.sh` remains the full development / IBSng developer setup.
- [x] Final repository structure contains exactly four installer files.
- [x] Production installer UX improved with professional/colorful output, clear sections and final installation summary.
- [x] Installer core UX improved without rewriting the production installation architecture.
- [x] Existing production ports, systemd services, Worker, Web/Admin Panel and RouteBox installation behavior preserved.
- [x] README and CHANGELOG updated to the final installer structure.

## Rules for future development

1. Existing IBSng end-to-end provisioning is working and must not be rewritten as part of Telegram Bot Admin work.
2. Existing RouteBox provisioning must remain backward-compatible.
3. Telegram Bot Admin is an independent permission system and must not be conflated with IBSng `owner` / `owner_name`.
4. Telegram Bot Admin must support multiple numeric Telegram IDs.
5. Payment bypass must be provider-neutral and must not alter the normal customer payment path.
6. Manual/card-to-card payment approval must occur before provisioning.
7. IBSng renewal must consume the plans configured in the RouteBox Admin Panel.
8. New Admin UI features should orchestrate the existing provider modules rather than moving provider protocol logic into the UI.
9. Future development for Telegram Bot Admin should start on `feature/telegram-bot-admin`.
10. ATD Panel UI work must remain UI-only unless a functional/provider change is explicitly requested.
11. Do not reintroduce manual Provider Plan Key requirements for RouteBox or MikroTik.
12. Do not invent new provider server-delete implementations when an existing supported backend action is absent; reuse existing endpoints when they already exist.
13. Do not render potentially thousands of MikroTik peers as one unbounded visible page.
14. Treat `6026a16` as the last confirmed healthy application/UI checkpoint until a newer commit is explicitly tested and confirmed.
15. Documentation-only commits do not change the tested application checkpoint.

## Telegram Bot Admin — implementation checkpoint

- [x] Added additive schema: `database/migrations/003_telegram_bot_admin.sql`.
- [x] Added independent authorization/orchestration: `src/Telegram/AdminBot.php`.
- [x] Integrated AdminBot into the existing `worker.php`; no second Worker or polling loop was created.
- [x] Added Web Panel management page: `public/telegram-admins.php`.
- [x] Admin IDs are numeric Telegram IDs; usernames/names/IBSng owner identity are not used for authorization.
- [x] Multiple Admins and future roles (`owner`, `admin`, `support`, `finance`, `operator`) are supported at the data-model level.
- [x] Admin service creation uses the existing `ServiceProvisioner` and existing provider integrations for RouteBox/IBSng.
- [x] Admin provisioning callbacks use short-lived, Admin-bound, one-time action records and do not repeat a successful provisioning action.
- [x] Admin provisioning actions are audit logged without storing credentials in the audit payload.
- [ ] Full card-to-card Order/Payment/Receipt approval workflow remains the next implementation slice.
