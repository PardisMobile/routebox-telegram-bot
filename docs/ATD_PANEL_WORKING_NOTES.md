# ATD Panel — Working Memory / UI Contract

> **Purpose:** This file is the durable working memory for the ATD Panel work. If development continues in a new chat, read this file first. It records the UI decisions, completed work, protected provider logic, and remaining UI tasks so the project does not need to be re-explained from scratch.

## 1. Current branch and scope

- Repository: `PardisMobile/routebox-telegram-bot`
- Main ATD Panel development branch: `ATD-Panel`
- Current deployment path used for testing: `/opt/routebox-telegram-bot-dev`
- Current dev web service: `routebox-telegram-bot-dev-web@8092.service`
- The current phase is primarily **Admin Panel UI/UX normalization**.

### Critical scope rule

**UI changes only unless explicitly agreed otherwise.**

Do **not** rewrite, refactor, move, or alter the working provider implementations, provider APIs, provisioning functions, service dispatching, or other core provider logic just to make the UI look consistent.

The existing working RouteBox, IBSng and MikroTik functionality is considered protected.

---

## 2. Provider sections that must remain functionally intact

The ATD Panel currently works with these provider areas:

- RouteBox
- IBSng
- MikroTik

The visual language of all three areas should be consistent, but their provider-specific backend behavior must remain untouched.

### Server action capability matrix

| Provider | Edit Server | Delete Server |
|---|---:|---:|
| IBSng | ✅ | ✅ |
| RouteBox | ❌ | ❌ |
| MikroTik | ✅ | ❌ |

This matrix is intentional/current behavior unless a future task explicitly changes it.

Do not invent backend delete functionality for RouteBox or MikroTik as part of UI work.

---

## 3. Server page layout contract

All provider server pages should follow the same visual structure.

### When servers exist

The order should be:

1. Page heading / description
2. Summary/stat cards
3. Existing server list/cards
4. **Add Server** section/card

**Important:** `Add Server` must NOT appear before an existing server list when servers already exist. The current request is specifically to move the existing server list above the Add Server form.

### When there are no servers

It is acceptable for the Add Server UI to be the primary content because there is no existing server list to display.

### Server cards

The server cards should use the same status/action visual language across providers wherever the underlying data supports it:

- provider icon
- server name
- connection/status indicator
- host/panel endpoint information as appropriate
- flag/location when available
- ping/latency when available
- action buttons with the same visual treatment

Do not replace meaningful status/flag/ping information with a generic `Version` card. Version may exist elsewhere, but it must not replace the server health/status information that was already present.

---

## 4. Top summary cards — Users section

The `section=users` page must have the same top-of-page summary-card pattern used by the other panel sections.

The cards belong **directly below**:

> `USERS`
> `manage telegram bot users and their services.`

The four cards are:

1. **RouteBox Users** — count of real RouteBox users/accounts from the RouteBox side.
2. **IBSng Users** — count of real IBSng users/accounts.
3. **MikroTik Users** — count of real MikroTik users/peers/users as defined by the existing integration.
4. **Telegram Bot Users** — total registered Telegram Bot users.

The first three are provider-side real-user counts. Telegram Bot Users is the total bot-user count.

Do not move these cards into the user table/list area. They are page-level summary cards.

---

## 5. Server status cards — protected UI information

During earlier UI changes, provider server pages accidentally showed a `Version` card in places where the original status information belonged.

The desired design is the one already established on the RouteBox server page:

- server status
- country flag/location
- connected/active state
- server IP/host where appropriate
- ping
- refresh/test control where supported

The same design language should be used for IBSng and MikroTik while respecting the data each provider actually exposes.

**Do not remove or replace working status/flag/ping data just for visual simplification.**

---

## 6. Connected / Running status pills

Status text such as `Connected`, `Active`, `Running`, etc. must visually match the existing panel style.

Use the same pill/badge treatment already established elsewhere in the panel:

- small status dot
- consistent rounded border/background
- consistent typography and spacing
- provider-appropriate status text

Do not introduce a second visual style for the same type of state.

---

## 7. Server action buttons

The goal is for server action controls to look like normal panel buttons, not hidden inline links or tiny disclosure arrows.

### Desired

- `Edit Server` as a visible button wherever the provider supports editing.
- `Delete Server` as a visible button wherever the provider supports deletion.
- Consistent button size, radius, typography, icon placement and spacing.
- Destructive delete actions should keep the established destructive/red visual language.

### IBSng

IBSng already supports:

- Edit Server
- Delete Server

Both should be presented as normal visible buttons.

### RouteBox

Currently no server delete action is implemented.

Do not add fake delete behavior. Keep the UI consistent without changing provider functionality.

### MikroTik

Currently Edit Server exists, but Delete Server does not.

Do not add backend deletion as part of UI-only work.

---

## 8. Provider Plans — common visual design

RouteBox, IBSng and MikroTik plan management should have a visually consistent layout.

### Plan cards

Each plan card should have the same action-row layout.

The desired action row is:

`Edit Plan` | `Disable` | `Delete`

All three buttons should sit on the **same row** when the viewport allows it, with consistent sizing and spacing.

Do not place `Edit Plan` on a separate line above or below `Disable` / `Delete`.

### Edit Plan interaction

The current edit-plan UI opens as a compact inline/edit panel and looks good.

A known remaining UX issue is that the edit panel does not close automatically/cleanly after interaction. The next UI pass should provide a clear close/cancel behavior without changing the underlying plan update logic.

Safe UI options include an explicit close/cancel control or equivalent presentation behavior. Do not alter the existing save/update backend logic just to solve this UI issue.

---

## 9. Provider Plan Key — very important

`Provider Plan Key` is **not equally required for every provider**.

### IBSng

For IBSng, this field is functionally important.

The value must correspond to the actual IBSng group name/key used by the configured IBSng server. Previously the plan used an IBSng group name such as `ibsng`.

If the provider plan key/group name does not match the IBSng group that exists on the IBSng server, IBSng cannot create the user in the intended group.

Therefore:

- IBSng `Provider Plan Key` is required/meaningful.
- Its value must be entered correctly.
- It must remain connected to the existing IBSng group mapping/provisioning behavior.
- **Do not change the IBSng group/provisioning functions as part of UI cleanup.**

### RouteBox / MikroTik

These providers may have provider-specific/internal plan keys generated or populated by the existing system. Examples observed during testing include values such as:

- RouteBox: `routebox1`
- MikroTik: keys beginning with `mt`

These are not the same semantic requirement as the IBSng group name.

The UI should therefore **not force the administrator to manually enter a Provider Plan Key for every provider** when the underlying provider does not require manual entry.

The safe UI rule is:

- IBSng: show an editable/required Provider Plan Key field because it is operationally necessary.
- RouteBox: do not make the user manually supply the key unless the existing backend explicitly requires it.
- MikroTik: do not make the user manually supply the key unless the existing backend explicitly requires it.
- Preserve whatever existing backend-generated/default key behavior already works.

**Do not rename, regenerate, or change existing provider plan keys in the backend. This is a UI presentation/input concern only.**

---

## 10. Plan section architecture / duplication issue

The panel has historically exposed plan information through more than one UI route, including provider-specific sections and a generic provider-plans route such as:

`section=provider-plans&provider=mikrotik_wireguard`

The desired UX is to make the plan management experience consistent with the server/provider pages and avoid confusing duplicate plan-management views.

### Target UX

- Provider-specific plan information should be presented in the provider's normal plan area.
- Do not show the same plan management data twice in unrelated sections merely because two routes can technically render it.
- Keep the existing underlying plan functions and data model intact.
- Consolidation is a **UI routing/presentation concern**, not a reason to rewrite provider plan logic.

When changing this, verify that create/edit/disable/delete operations still call the existing working plan functions.

---

## 11. IBSng test-user UI

A working IBSng test-user creation capability already exists and has been tested successfully.

The Admin Panel should expose a clear **Test User / Test & Save** style action in the IBSng area as previously planned.

Important:

- Reuse the existing working IBSng test/provisioning logic.
- Add only the UI orchestration needed to expose it.
- Do not rewrite `IBSngClient`, authentication/session handling, provisioning, group mapping, or the existing tested creation flow.

---

## 12. Large server/user lists — scalability rule

Do **not** build a UI that blindly renders thousands of rows/cards on one page.

Examples:

- 5 servers should be easy to see on the server page.
- 1000 MikroTik peers/users should **not** become 1000 large UI rows in one unbounded page.

For potentially large datasets, the target UI should use an appropriate scalable presentation such as:

- pagination
- server-side pagination where supported
- search/filter
- compact table/list views
- sensible per-page limits
- lazy loading where appropriate

This is a UI scalability requirement. Do not change provider data APIs/functions unless a later task explicitly requires backend pagination support.

---

## 13. Existing provider logic that must be treated as protected

The following areas are known working and must not be rewritten merely for ATD Panel styling:

### IBSng

- IBSng A1.24 authentication/session handling
- IBSng client/API communication
- server configuration
- group mapping/listing
- user lookup
- test-user creation
- Telegram IBSng account provisioning
- Internet Username + password assignment
- `service_subscriptions` persistence
- provider/server/group-aware provisioning
- Worker loading of the isolated `IBSngClient`
- one-account model for OpenVPN/Cisco/L2TP access methods

### RouteBox

- existing RouteBox/AWG API integration
- authentication/health/status handling
- peers/config export
- provisioning
- expiry/traffic operations
- existing server behavior

### MikroTik

- existing MikroTik connection/provider functions
- peer/user creation and allocation logic
- existing IP-pool / peer addressing behavior
- existing WireGuard peer provisioning

In particular, previously fixed MikroTik WireGuard IP allocation behavior must not be broken by UI work.

---

## 14. Important recent UI work already completed

The ATD Panel work has already included substantial visual normalization, including:

- Added/positioned the four `Users` summary cards.
- Corrected their placement to the top of the Users page under the page heading.
- Restored provider server status information instead of showing a generic Version card in its place.
- Added/normalized country flags and ping/status presentation where available.
- Unified `Connected` / `Running` style badges with the rest of the panel.
- Normalized server action-button presentation.
- Converted provider plan edit controls from small disclosure/arrow affordances to visible buttons.
- Unified plan-card action styling across providers.
- Moved `Edit Plan` toward the same action row as `Disable` and `Delete`.
- Added/retained IBSng test-user UI work based on the previously working test flow.
- Kept provider implementations separate while making the UI consistent.

These changes should be considered the baseline visual language for future ATD Panel work.

---

## 15. Remaining UI work / next tasks

### High priority

- [ ] Make `Edit Plan` close/cancel cleanly after editing instead of requiring another Edit/Save interaction.
- [ ] Ensure `Edit Plan`, `Disable`, and `Delete` remain on one aligned action row in all provider plan cards and responsive layouts.
- [ ] Move the existing server list above the `Add Server` form on all provider server pages when servers already exist.
- [ ] Verify all three provider server pages use the same status-card visual structure without losing provider-specific status data.
- [ ] Make sure `Provider Plan Key` is only a mandatory/manual field where the provider actually needs it, especially IBSng.
- [ ] Keep RouteBox/MikroTik provider-generated/internal plan keys intact.
- [ ] Ensure no duplicate/confusing provider plan-management view remains exposed unnecessarily.
- [ ] Keep large user/peer/server datasets paginated or otherwise bounded; do not render 1000-item unbounded cards/rows.

### Server action follow-up

- [ ] Keep IBSng Edit Server + Delete Server as visible buttons.
- [ ] Keep RouteBox without Delete Server unless backend functionality is intentionally added in a separate task.
- [ ] Keep MikroTik Edit Server; Delete Server remains a separate future capability unless explicitly implemented.

### IBSng

- [ ] Verify the Test User UI calls the existing working test/provisioning path.
- [ ] Do not modify the underlying IBSng group mapping behavior.

---

## 16. Safe deployment/check commands

For the current dev branch/environment, the standard update sequence used during testing is:

```bash
cd /opt/routebox-telegram-bot-dev

git fetch origin '+refs/heads/ATD-Panel:refs/remotes/origin/ATD-Panel'
git reset --hard origin/ATD-Panel

# Run the relevant PHP syntax checks for changed files.
# Example:
php -l src/Admin/Plans/ProviderPlansSection.php
php -l src/Integrations/IBSng/IBSngSection.php
php -l src/Integrations/MikroTik/MikroTikSection.php

systemctl restart routebox-telegram-bot-dev-web@8092.service
```

Only syntax-check files that actually changed when possible.

---

## 17. Golden rule for the next chat

When continuing ATD Panel development:

1. Read this file first.
2. Treat existing provider logic as working/protected.
3. Make UI changes in the Admin Panel layer only unless the user explicitly requests functional/backend changes.
4. Preserve existing routes, provider APIs, provisioning functions and data semantics.
5. If a visual consistency problem can be solved with HTML/CSS/view composition, do that instead of changing provider logic.
6. Before changing anything, check the three provider pages together: **RouteBox + IBSng + MikroTik**.
7. After changes, verify both desktop and narrower layouts so action buttons do not wrap into visually inconsistent rows.
8. Do not reintroduce the `Provider Plan Key` requirement as a mandatory manual field for every provider.
9. Do not replace status/flag/ping cards with Version cards.
10. Do not put Add Server above an existing server list.
11. Do not render potentially thousands of records as one unbounded page.

**This document is a UI/architecture guardrail, not a license to modify backend provider behavior.**
