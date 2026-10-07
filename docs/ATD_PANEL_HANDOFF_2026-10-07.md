# ATD Panel — Persistent Handoff / New-Chat Memory
Date: 2026-10-07

## 1. Project identity

- Repository: PardisMobile/routebox-telegram-bot
- Development branch: ATD-Panel
- Development server path: /opt/routebox-telegram-bot-dev
- Development web service: routebox-telegram-bot-dev-web@8092.service
- Product name: ATD Panel — server and telegram bot control center
- Current working Providers:
  - routebox
  - ibsng
  - mikrotik_wireguard

IMPORTANT: The user has NOT yet deployed the latest GitHub commit 5fdbbe4 to the server. Do not assume it is running on 8092 until the user applies it and tests it.

## 2. Current GitHub state

Latest UI commit prepared:
- SHA: 5fdbbe4ede6d27dd64bca576253dfa011e1c0b1a
- Message: feat(ui): unify users trial search and add table pagination
- File changed: src/Admin/TrialAdminPanel.php

This commit:
1. Makes the Users search and Trials search visually consistent.
2. Keeps Trial search independent through trial_q.
3. Adds client-side pagination to Users and Trials.
4. Uses 20 rows per page.
5. Shows pagination only when more than 20 rows exist.
6. Preserves existing search/filter behavior.
7. Does NOT change Provider Core, ServiceProvisioner, Worker, MikroTik allocation, IBSng provisioning, database semantics, or the main Users query.

Deployment/test status of 5fdbbe4:
- On GitHub: YES
- Applied to server: NO
- User acceptance test: NOT YET
- Do not call it a tested application checkpoint yet.

The last explicitly confirmed healthy application/UI checkpoint in the repository documentation is 6026a16, but later Trial UI work was also visually verified by the user. Treat 5fdbbe4 specifically as pending deployment/validation.

## 3. Server/deployment rules

The user works from:
  /opt/routebox-telegram-bot-dev

Target development web service:
  routebox-telegram-bot-dev-web@8092.service

The existing Worker is protected. Do NOT restart or replace the Worker during ordinary Web Panel UI work.

Before applying a new commit:
  cd /opt/routebox-telegram-bot-dev
  git fetch origin '+refs/heads/ATD-Panel:refs/remotes/origin/ATD-Panel'
  git reset --hard origin/ATD-Panel
  php -l <changed PHP files>
  git diff --check
  systemctl restart routebox-telegram-bot-dev-web@8092.service

Only restart the web service when needed for the Web Panel. Never create a second Worker.

## 4. Architecture — NON-NEGOTIABLE

Existing functionality > refactor.

Protected layers:
  Web Panel
      ↓
  Telegram Bot + Existing Worker
      ↓
  Service/Application Layer
      ↓
  Provider Integrations
      ↓
  Database

Do not:
- create a second Telegram Bot;
- create a second Worker;
- duplicate Provider provisioning inside Telegram handlers;
- duplicate MikroTik WireGuard peer/IP allocation;
- rewrite working RouteBox/IBSng/MikroTik Provider Core for UI work;
- casually change database semantics;
- move protocol logic into UI code;
- hard-code Provider/Plan catalogs into the Bot.

All Admin/Web UI must remain inside the existing ?section= architecture.

Protected sections:
- section=users — existing shared Users management
- section=payment-settings — shared payment settings
- section=bot — shared Bot control center

Inside section=bot preserve:
- Bot Settings
- Bot Buttons
- Bot Menu Preview
- Customer Bot
- Telegram Bot Admin
- Usage Guides
- protected ATD Stats/status cards

Do not create a duplicate Users system or duplicate Users header/list.

## 5. Dynamic Provider/Plan model

The Bot must never own a hard-coded Provider/Plan catalog.

Correct model:
  Provider
    ↓
  provider_key
    ↓
  Service Category
    ↓
  Provider-owned Plan
    ↓
  Telegram Bot / Web UI
    ↓
  Existing ServiceProvisioner / Provider adapter

Current provider_key values:
- routebox
- ibsng
- mikrotik_wireguard

Keep these concepts separate:
- provider_key
- Provider Plan Key
- Provider server ID
- Username Prefix

IBSng:
- Provider Plan Key = real IBSng Group/Plan identifier.
- Username Prefix is only username generation.
- Default IBSng prefix remains rb until explicitly changed.
- Never conflate IBSng Group/Plan Key with username prefix.

## 6. Provider work already completed / protected

### RouteBox / AmneziaWG
- Existing provisioning remains operational.
- Existing Provider Core must not be rewritten for UI work.
- Existing server management and supported delete actions are reused.
- RouteBox UI public section alias is section=routebox; legacy/internal compatibility remains protected.

### IBSng A1.24
Verified working:
- authentication/session handling
- server configuration
- group mapping/listing
- real user creation
- Internet Username + password assignment
- Plan → Server → Group provisioning
- service_subscriptions persistence
- Telegram provisioning flow
- Worker integration
- real provisioning verification

Do not replace this flow just to add Admin functionality.

### MikroTik WireGuard
- Modular Provider integration is operational.
- Provider-owned provisioning is protected.
- WireGuard peer/IP allocation remains Provider-owned.
- The Bot must never implement its own IP-pool allocator.
- The known allocation semantics must remain unchanged.

## 7. ATD Panel UI work completed

Previously completed and accepted UI work includes:
- ATD Panel branding.
- Protected four-card/status dashboard layout preserved.
- Light/Dark themes.
- Multi-color palette selector.
- Persian typography improvements.
- Persian/English behavior preserved.
- Sidebar language duplication removed where applicable.
- Persian sidebar navigation normalized.
- Compact desktop sidebar restored at 6026a16.
- Mobile drawer/navigation improvements.
- Firefox desktop sidebar sizing fixed.
- Unified RouteBox/IBSng/MikroTik server UI.
- Bounded server pagination target of 5 items/page.
- Provider plan action styling unified.
- Edit/Disable/Delete plan action alignment.
- IBSng Provider Plan Key manual-entry semantics preserved.
- RouteBox/MikroTik generated/provider-owned keys preserved.
- Protected ATD Stats cards preserved.

Do not casually redesign the four protected Stats/status cards.

## 8. Telegram Bot Admin foundation completed

Implemented:
- Telegram Numeric ID authorization independent from IBSng owner/owner_name.
- Multiple Admin records.
- Future-ready roles.
- Web Admin management at public/telegram-admins.php.
- Dedicated Admin menu inside the existing Worker.
- Existing Provider/service provisioning reused.
- Replay protection for Admin provisioning callbacks/actions.
- Audit logging without storing credentials.
- Customer Bot/Admin Bot navigation inside existing section=bot architecture.

Still pending:
- complete Admin User/Search workflow
- complete Admin Service Management
- IBSng Admin account search/renew/edit
- Worker health/log/reload controls
- expiry notifications
- complete payment lifecycle

## 9. Trial implementation — important recent work

Trial architecture was added without creating a second Worker or second User system.

Core:
- src/Services/TrialService.php
- src/Admin/TrialAdminPanel.php

Trial data:
- telegram_trial_plans
- telegram_trial_instances
- permanent per-user/per-category claim protection via unique constraint
- Provider reference JSON for cleanup
- expiry and cleanup state

Provider-aware design:
- Trial is selected from a real Provider-owned Plan in the existing service catalog.
- TrialService provisions through existing ServiceProvisioner.
- Provider-specific cleanup uses Provider-owned integration paths.
- RouteBox legacy Trial recording is preserved.
- Expired cleanup removes the Provider resource while preserving eligibility history, so the same Provider Trial cannot simply be claimed again.

Trial UI:
- section=bot has Provider/Category Trial Plan configuration.
- section=users has the Trials panel.
- Trials are additive to the existing Users page.
- Trial search is independent from Users search through trial_q.
- Trial search covers Telegram ID, username, name, Provider and Plan.
- Expired Trial cleanup is protected by admin authorization and CSRF.

Verified:
- Trial PHP syntax/lint passed in the implementation cycle.
- Trial placement under Bot Settings was corrected and visually verified.
- User confirmed the current Trial UI appearance is good.
- Existing Users list/search remains present.
- Trial UI is not allowed to replace or duplicate Users.

Important distinction:
- PHP lint proves syntax only.
- It does NOT prove real RouteBox/IBSng/MikroTik Trial provisioning end-to-end.
- Any claim of full provider Trial E2E testing must be based on an actual provider test.

## 10. Latest Trial UI change — pending deployment

Commit 5fdbbe4 changes only src/Admin/TrialAdminPanel.php.

Users search and Trial search now share:
- 44px input height
- matching border/radius/padding
- matching button height
- matching gaps
- responsive mobile behavior

Pagination:
- Users: 20 rows/page
- Trials: 20 rows/page
- Pagination appears only if >20 rows
- Previous/next + page buttons
- Current page indicator
- Client-side only in this commit
- Existing query/search logic is preserved

Do NOT say this is server-tested until the user deploys it.

## 11. Payment state

The authoritative payment UI is:
  section=payment-settings

Card-to-card configuration exists.

The complete production lifecycle is NOT complete:
  Dynamic Provider/Plan
      ↓
  Order
      ↓
  Payment
      ↓
  Receipt
      ↓
  Admin Review
      ↓
  Approve / Reject
      ↓
  Existing Provider provisioning
      ↓
  Activate + send credentials/config

Required:
- idempotent approval
- no provisioning before valid approval
- duplicate callback/double-click protection
- retryable provisioning failure without duplicate service
- Provider-neutral payment layer
- future ZarinPal/Crypto adapters without rewriting Provider Core

## 12. Usage Guides distinction

Keep these separate:
1. General Guide — general bot/product usage.
2. Bot/service Usage Guides — customer connection/use instructions.
3. Provider Admin Guides — operator instructions for configuring Provider servers.

Never merge them.

## 13. Username/password generation — pending refinement

Pending:
- configurable username prefix
- configurable random suffix length
- configurable password length/character policy
- safe validation
- collision prevention

Default IBSng prefix remains rb.

Do not rename provider_key mikrotik_wireguard or any existing provider identifiers casually.

## 14. Worker — protected

Use the existing Worker only.

Never create a second Worker or polling loop.

Pending Worker work:
- status
- health monitoring
- safe reload/restart control
- recent logs/diagnostics
- expiry notifications 7/3/1 days and expired state
- retry/failure handling where appropriate

For Web Panel-only changes, restart the web service, not the Worker.

## 15. Security audit — pending

Full source-wide audit is NOT complete.

Must check:
- SQL Injection
- XSS
- CSRF
- IDOR
- authentication/authorization
- privilege escalation
- command injection
- SSRF
- path traversal
- unsafe uploads/receipts
- Telegram callback forgery/replay
- rate limiting
- secrets/credential leakage
- session security
- database security
- dependency/configuration security

SQL Injection project rule:
1. If a real injectable query is found, first report exact file/function/query/input/attack vector/severity.
2. Then make the smallest prepared-statement/parameter-binding fix.
3. Regression-test it.
Do not claim a source-wide security audit complete until the entire relevant source tree has been checked.

## 16. Installer / CI

Current installer structure:
- install.sh
- installer-core.sh
- install-dev.sh
- install-dev-full.sh

install-v2.sh is retired. Do not reintroduce it into current installer docs or workflows.

## 17. MirzaBot policy

https://github.com/mahdiMGF2/mirzabot is research/reference only.

Allowed:
- feature comparison
- workflow ideas
- user/admin needs analysis

Forbidden:
- copying source code
- copying schema
- copying names/classes/functions
- copying UI/menu structure
- copying text/implementation
- making ATD Panel dependent on MirzaBot

All useful ideas must be independently designed for ATD Panel architecture.

## 18. Priority of remaining work

Recommended order:
1. Customer Service Details + complete Service lifecycle.
2. Customer Renewal using real configured Provider Plans.
3. Admin User/Search + Service Management.
4. IBSng Admin account management.
5. Worker health + expiry notifications.
6. Complete card-to-card Order/Receipt/Approval/Provision lifecycle.
7. ZarinPal/Crypto adapters.
8. Full source-wide security audit.
9. Later: wallet, coupons, referral/affiliate, reseller and growth features.

## 19. Definition of Done

A feature is not Done merely because code exists.

Required:
- happy path
- error/edge paths
- authorization/security
- input validation
- regression of RouteBox/IBSng/MikroTik
- Worker regression where relevant
- Users/Plans regression
- desktop/mobile check
- Persian/English check where relevant
- PHP lint / relevant tests
- documentation update
- traceable commit
- actual acceptance test for functional claims

## 20. Documentation rule

After each completed feature slice:
1. Update ROADMAP.md.
2. Update ATD_PANEL_WORKING_NOTES.md.
3. Update CHANGELOG.md when user-visible/operational.
4. Record acceptance criteria.
5. Record test/regression status.
6. Create a clear traceable commit.

Documentation-only commits do not become application checkpoints unless the application itself was tested.

## 21. Immediate next task

Before doing new feature work:
1. User must deploy 5fdbbe4 to /opt/routebox-telegram-bot-dev.
2. Run PHP lint and git diff --check.
3. Restart only routebox-telegram-bot-dev-web@8092.service if required.
4. Verify Users search UI.
5. Verify Trials search UI.
6. Create >20 test-visible rows if needed and verify Users pagination.
7. Create >20 Trial rows if needed and verify Trial pagination.
8. Verify search + pagination interaction.
9. Verify mobile layout.
10. Only then mark 5fdbbe4 tested/accepted.

After acceptance, update this file, ATD_PANEL_WORKING_NOTES.md, ROADMAP.md and CHANGELOG.md with the actual test result and new confirmed checkpoint.

## 22. New-chat instruction

If a new ChatGPT conversation is opened because the previous conversation reached memory limits, paste the user-facing handoff prompt supplied with this repository state. The new chat must first read:
- docs/ATD_PANEL_HANDOFF_2026-10-07.md
- ATD_PANEL_WORKING_NOTES.md
- docs/ATD_PANEL_CURRENT_STATE.md
- ROADMAP.md

Then inspect the actual GitHub branch and current server state before making changes.

Do NOT ask the user to re-explain the project. Ask only for a missing decision or missing runtime result that cannot be recovered from the repository/server.

When the user says "ادامه بده", continue the active task end-to-end without repeatedly asking for permission between obvious sequential steps. Keep the user updated at meaningful milestones.

