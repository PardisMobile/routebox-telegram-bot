# ATD Panel — Working Memory / UI Contract

> Durable working memory for ATD Panel development. Read this file first when continuing in a new chat.

## 1. Scope and environment

- Repository: `PardisMobile/routebox-telegram-bot`
- Branch: `ATD-Panel`
- Dev deployment path: `/opt/routebox-telegram-bot-dev`
- Dev web service: `routebox-telegram-bot-dev-web@8092.service`
- Current phase: Admin Panel UI/UX normalization.

### Critical rule

**UI changes only unless the user explicitly asks for a functional/provider change.**

Do not rewrite, refactor, move or alter the working RouteBox, IBSng or MikroTik provider implementations, APIs, provisioning functions, peer allocation logic, authentication/session logic or service-dispatching logic merely to make the UI consistent.

## 2. Provider capability matrix

| Provider | Edit Server | Delete Server |
|---|---:|---:|
| IBSng | ✅ | ✅ |
| RouteBox | ❌ | ❌ |
| MikroTik | ✅ | ❌ |

This is the current supported UI/behavior contract.

- Do not invent RouteBox Delete Server.
- Do not invent MikroTik Delete Server.
- IBSng Edit/Delete are real supported actions.
- MikroTik Edit is real supported action.

## 3. Server-page layout contract

When servers exist, every provider page should visually follow:

1. Page heading / description
2. Top summary/stat cards
3. Existing server list/cards
4. Add Server section

If there are no servers, Add Server may be the primary content.

### Current state

- RouteBox list already appears before Add Server.
- IBSng list was moved before Add Server.
- MikroTik list was moved before Add Server.

Server cards must retain meaningful provider-specific information such as status, flag/location, IP/host and ping where available. Do not replace those with generic Version cards.

## 4. Top summary cards

### Users page

`section=users` has four page-level cards directly below the `USERS` heading:

1. RouteBox Users
2. IBSng Users
3. MikroTik Users
4. Telegram Bot Users

The first three use the existing provider subscription/user counts; Telegram Bot Users uses the total registered Telegram users.

Do not move these cards into the table/list.

### Provider pages

The provider pages use the shared ATD status/stat card area. The server-health card must retain:

- server status
- country flag/location
- connected/offline state
- server IP/host
- ping
- refresh/test control where supported

## 5. Cross-browser flag rule

The previous flag implementation relied on regional-indicator emoji characters. Firefox rendered the flags, but Chrome/Edge could show only `FR`, `IR`, etc. because emoji-font rendering is not guaranteed.

Current implementation:

- `ATDStats` resolves a two-letter country code.
- The UI renders a small flag image from FlagCDN.
- The image `alt` contains the country code, so the code remains a safe fallback if the image cannot load.
- Do not revert to emoji-only flags.

## 6. Connected / Running status

Use the established panel pill language:

- small status dot
- rounded pill
- consistent border/background
- consistent typography/spacing

Do not introduce a second style for the same state.

## 7. Server action buttons

All visible actions should look like normal panel buttons, not disclosure arrows.

Current supported presentation:

- IBSng: visible `Test Connection`, `Test Create User`, `Edit Server`, `Delete Server` buttons.
- MikroTik: visible `Test Connection` and `Edit Server` buttons.
- RouteBox: existing provider actions only; no UI-injected Edit/Delete controls.

Destructive actions use the existing red destructive button language.

## 8. Provider Plans

RouteBox, IBSng and MikroTik use the shared `ProviderPlansSection` visual design.

### Action row

Every plan card should use:

`Edit Plan` | `Disable/Enable` | `Delete`

All actions must remain aligned on the same row when the viewport allows it.

### Edit interaction

The edit panel is a compact floating/inline `<details>` panel.

Current improvements:

- explicit `Close` button inside the editor
- Escape closes open plan editors
- native Edit summary still toggles open/closed
- existing save/update backend logic is untouched

Do not replace this with a provider-specific plan implementation.

## 9. Provider Plan Key — critical semantic rule

`Provider Plan Key` is **not** a universal manual field.

### IBSng

This field is operationally important and must remain manual/required.

It represents the real IBSng group name used by the configured IBSng server. If it does not match the actual IBSng group, user creation cannot target the intended group.

The existing working IBSng plan/group mapping must not be changed during UI cleanup.

### RouteBox / MikroTik

Their keys may be generated/populated by existing backend logic. Examples observed:

- RouteBox: `routebox1`
- MikroTik: values beginning with `mt`

The UI must not force administrators to type these values manually when the provider does not require it.

Current `ProviderPlansSection` behavior:

- IBSng: show required Provider Plan Key field.
- RouteBox: do not show a manual Provider Plan Key field.
- MikroTik: do not show a manual Provider Plan Key field.
- Preserve existing provider-generated/internal key values.

Never regenerate, rename or rewrite provider keys as part of UI work.

## 10. Plan-page architecture

The intended UX is one clear provider-scoped plan-management view through:

`section=provider-plans&provider=<provider>`

Provider pages may expose a `Manage Plans` navigation affordance, but should not duplicate the same editable plan catalog in another provider page.

Keep the existing plan data model and working create/update/toggle/delete functions intact.

## 11. IBSng test-user UI

A working IBSng test-user creation path already exists and has been tested.

The IBSng Admin UI exposes `Test Create User`.

Important:

- Reuse the existing tested path.
- Do not rewrite `IBSngClient`, authentication/session handling, provisioning, group mapping or service creation.
- UI orchestration only.

## 12. Large lists / scalability

Do not present potentially thousands of records as one huge visible page.

Current implementation:

- MikroTik peers are displayed 50 per page with UI pagination.
- The underlying provider data/API is unchanged.
- Server lists are expected to be small enough for normal card display.
- Users page remains bounded by its existing 100-user query limit.

If a future provider can return very large server/user sets, prefer bounded/paginated UI without rewriting provider APIs unless explicitly requested.

## 13. Protected provider logic

### IBSng — protected

- A1.24 authentication/session handling
- Web Panel/API communication
- server configuration
- group mapping/listing
- user lookup
- test-user creation
- Telegram IBSng account provisioning
- Internet Username + password assignment
- `service_subscriptions` persistence
- provider/server/group-aware provisioning
- isolated `IBSngClient`
- one-account model for OpenVPN/Cisco/L2TP

### RouteBox — protected

- RouteBox/AWG API integration
- authentication/health/status handling
- peers/config export
- provisioning
- expiry/traffic operations
- existing server behavior

### MikroTik — protected

- MikroTik connection/provider functions
- peer/user creation and allocation logic
- WireGuard peer provisioning
- IP-pool/peer addressing behavior
- previously fixed WireGuard first-peer allocation behavior

## 14. Completed ATD UI work

- [x] Four Users summary cards created and placed below Users heading.
- [x] Provider server status cards restored/normalized.
- [x] Flag + status + ping presentation normalized.
- [x] Connected / Running pills normalized.
- [x] Server action buttons normalized.
- [x] IBSng Edit/Delete and Test Create User UI exposed.
- [x] RouteBox/MikroTik unsupported Delete Server UI injections removed.
- [x] Provider plan action rows unified.
- [x] Edit Plan moved onto the same action row as Disable/Delete.
- [x] Plan editor Close + Escape behavior added.
- [x] Provider Plan Key made conditional/manual only for IBSng.
- [x] IBSng server list moved before Add Server.
- [x] MikroTik server list moved before Add Server.
- [x] MikroTik peer pagination added at 50/page.
- [x] Chrome/Edge flag rendering fixed with image assets + code fallback.
- [x] README / CHANGELOG / ROADMAP updated for the ATD UI baseline.

## 15. Current remaining work

### High priority

- [ ] Visual QA on the live dev panel in Firefox, Chrome and Edge.
- [ ] Verify desktop and narrow/mobile layouts for all three provider pages.
- [ ] Verify all plan action rows stay aligned at common desktop widths.
- [ ] Verify the external flag image fallback behaves acceptably if FlagCDN is unreachable.
- [ ] Verify MikroTik pagination with 0, 1, 50, 51 and 100+ peers.
- [ ] Verify IBSng Test Create User still reaches the existing tested provisioning path.

### Functional roadmap (not UI cleanup)

- [ ] Telegram Bot Admin authentication/authorization.
- [ ] Multiple Telegram Bot Admin IDs.
- [ ] Dedicated Bot Admin Telegram menu.
- [ ] Admin payment bypass.
- [ ] IBSng user search/management/renewal through Bot Admin.
- [ ] Configurable generated IBSng username prefix.
- [ ] Worker status/restart/health/logs.
- [ ] Payment callbacks, ZarinPal, crypto gateway, verified-payment-only provisioning and invoice history.

## 16. Safe deployment/check commands

```bash
cd /opt/routebox-telegram-bot-dev

git fetch origin '+refs/heads/ATD-Panel:refs/remotes/origin/ATD-Panel'
git reset --hard origin/ATD-Panel

# Run syntax checks for the files changed in the current pass.
php -l src/Admin/Plans/ProviderPlansSection.php
php -l src/Integrations/IBSng/IBSngSection.php
php -l src/Integrations/MikroTik/MikroTikSection.php
php -l src/Admin/ATDStats.php

systemctl restart routebox-telegram-bot-dev-web@8092.service
```

## 17. Golden rule for the next chat

1. Read this file first.
2. Treat existing provider logic as working/protected.
3. Prefer HTML/CSS/view composition for UI problems.
4. Do not change provider APIs/provisioning functions to solve visual issues.
5. Check RouteBox + IBSng + MikroTik together before changing shared UI.
6. Do not reintroduce manual Provider Plan Key for RouteBox/MikroTik.
7. Do not replace status/flag/ping cards with Version cards.
8. Do not put Add Server above an existing server list.
9. Do not invent RouteBox/MikroTik Delete Server.
10. Do not render thousands of peers as one unbounded visible list.

**This document is the ATD Panel guardrail and project memory.**
