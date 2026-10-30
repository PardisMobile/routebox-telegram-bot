# RouteBox IBSng Integration — Requirements & Architecture

> This document records the agreed scope, existing integration architecture, and safety constraints for IBSng work in RouteBox. It is a planning/traceability document, not a claim that every listed capability is already implemented.

## 1. Core non-regression rule

The IBSng integration already has substantial working behavior. **Do not break, replace, or casually refactor existing RouteBox behavior.** Before changing code, inspect the actual implementation and IBSng A1.24 source, map the exact call paths, and make the smallest additive change. Preserve existing RouteBox provider behavior and existing IBSng account creation, credentials, and subscription display.

- Keep changes limited to IBSng unless a shared change is strictly necessary and proven safe.
- Keep existing plans and stored subscriptions compatible; migrations must be additive and safe for existing data.
- Do not change or introduce a Worker/background service as part of this work. Worker review is deferred unless explicitly requested.
- Do not infer IBSng Web Panel form fields or API behavior; verify against the supplied IBSng A1.24 source and current adapter.
- Preserve IBSng's own terminology and exact group names in UI and mappings.

## 2. Existing RouteBox-to-IBSng architecture

### Main components

- `worker.php`: Telegram callbacks and user-facing messages.
- `src/Services/ServiceCatalog.php`: resolves service plans.
- `src/Services/ServiceProvisioner.php`: shared provisioning dispatcher and IBSng purchase path.
- `src/Integrations/IBSng/IBSngService.php`: IBSng integration service, account creation and user-facing account lookup.
- `src/Integrations/IBSng/IBSngClient.php`: HTTP adapter for the IBSng Web Panel.
- `src/Integrations/IBSng/IBSngProvider.php`: provider adapter.
- `src/Integrations/IBSng/IBSngSchema.php`: IBSng schema/seed/synchronization support.
- `src/Integrations/IBSng/IBSngAdmin.php`, `IBSngSection.php`, `IBSngModule.php`: admin UI.
- `database/migrations/001_modular_services.sql`, `database/schema.sql`: data model.
- `tools/ibsng-smoke-test.php`: integration smoke test.

### Current purchase/provisioning call path

Telegram `svcplan` callback → `sendServicePurchase()` → `ServiceProvisioner::provision()` → resolve `service_plans.provider_key` → `provisionIbsng()` → validate configured server and exact IBSng group mapping → generate account credentials → `IBSngService::createAccount()` → `IBSngClient::createUser()` → set Internet username/password → persist `service_subscriptions` → send Telegram success response.

The IBSng path must remain separate from RouteBox-specific provisioning logic.

### Data relationships

- `telegram_users.id` → `service_subscriptions.telegram_user_id`
- `service_plans.id` → `service_subscriptions.plan_id`
- `service_plans.provider_plan_key` stores the **exact IBSng group name**.
- `service_plans.provider_server_id` identifies the configured IBSng server.
- `ibsng_servers.id` → `ibsng_groups.ibsng_server_id`.
- `service_subscriptions.provider_reference` stores the IBSng internal `user_id`.
- Subscription `username` and encrypted password fields hold the credentials RouteBox displays to its customer.

Keep the real IBSng group identifier/name distinct from a display label.

## 3. IBSng Web Panel communication

The integration uses the IBSng A1.24 **Web Panel HTTP interface**, not a direct RADIUS protocol client for these account-management operations.

### Connection/authentication layer

`IBSngClient` owns HTTP requests, login/session handling, and connection checks. Relevant methods include:

- `loginFast()`
- `ensureLogin()`
- `request()`
- `testConnection()`
- `listGroups()`
- `getGroupInfo()`
- `getUserInfoByUsername()`

Any additions must reuse the established session and request mechanisms. Do not add a parallel authentication implementation without a demonstrated need.

### Creating a user

Current adapter method: `IBSngClient::createUser()`

Endpoint:

```http
POST /admin/user/add_new_users.php
```

Documented form fields used by the existing implementation:

- `count=1`
- `credit=<credit>`
- `owner_name=<IBSng admin username>`
- `group_name=<exact IBSng group name>`
- `credit_comment=''`

The adapter does not depend on following the redirect automatically. It extracts the new IBSng `user_id` from the redirect Location, effective URL, or response body according to the existing implementation. Preserve that handling.

### Setting account credentials

Current adapter method: `IBSngClient::setUserCredentials()`

First load the edit page:

```http
GET /admin/plugins/edit.php?edit_user=1&user_id=<id>
```

Then submit the existing form:

```http
POST /admin/plugins/edit.php
```

Important fields include:

- `update=1`
- `edit_tpl_cs=normal_username`
- `target=user`
- `target_id=<id>`
- `user_id=<id>`
- `has_normal_username=t`
- `normal_username=<username>`
- `password=<password>`
- `normal_save_user_add=t`
- `attr_update_method_0=normalAttrs`

The actual source of IBSng A1.24 remains authoritative for any form changes.

## 4. Planned IBSng functionality and requirements

### A. IBSng plan validity duration — immediate scoped task

Add a validity-duration field to **IBSng plans only**.

Requirements:

- Admin can define and edit a plan's validity duration.
- The field must be stored and retrieved consistently with that IBSng plan.
- Existing IBSng plans and records without a duration must continue to work; use a safe compatibility/default strategy rather than assuming old rows have values.
- Keep the field scoped to the IBSng provider. Do not inadvertently change RouteBox provider plans or shared plan behavior.
- The plan's validity value should be available to later IBSng purchase/renewal work, but this task by itself does not authorize changing provisioning, renewals, or Worker behavior.
- Validate the chosen unit and range in implementation; do not silently reinterpret existing values.

### B. IBSng account lookup

Support lookup of an IBSng account by its username through the existing Web Panel adapter. Lookup must not be treated as proof that an externally created account belongs to a RouteBox Telegram user. Linking ownership requires an explicit, validated workflow.

### C. Subscription renewal — planned, not enabled yet

Renewal must be implemented only after its exact IBSng A1.24 behavior is verified against the source and tested against the existing adapter. The current client explicitly disables renewal; do not claim it is already operational.

Expected product behavior recorded for future implementation:

- **End-user renewal:** act only on the selected RouteBox subscription owned by the requesting Telegram user. Validate subscription ownership and provider before contacting IBSng.
- **Admin renewal:** an administrator may renew an IBSng account by username, including an account not originally created through RouteBox, using an explicitly selected/configured IBSng plan.
- Do not require the IBSng user's password for admin renewal; use the configured IBSng panel session.
- For an expired account, the intended behavior is to reset the IBSng First Login/initial-login expiry behavior as appropriate, without overwriting Relative or Absolute Expiration values.
- For an active account, preserve remaining validity and add the selected plan duration plus any configured gift days through the correct IBSng expiry attribute. Do not reset First Login for an active user.
- Exact IBSng form fields, attribute names, and POST payloads must be extracted from IBSng A1.24 source and verified. Never invent them.
- Keep renewal disabled until the exact operation and error handling are implemented and tested.

### D. Other operations

The current adapter also marks group change and deletion as not enabled. Do not expose these as working features or enable them implicitly as part of plan-duration work. Plan discovery is currently manual; do not assume IBSng groups automatically provide RouteBox plan duration.

## 5. Data and compatibility rules

- Inspect current schema and migrations before choosing the new duration column.
- Prefer an additive migration and preserve current rows.
- Ensure plan create, edit, list, and read paths all handle the new field.
- Keep schema definitions/synchronization aligned where the project maintains more than one schema representation.
- Do not make existing subscriptions depend on a newly required value.
- Avoid unrelated schema renames or data rewrites.

## 6. Testing and acceptance criteria

For the immediate plan-duration change:

- Existing IBSng plans can still be listed, edited, and purchased using the current flow.
- New and edited IBSng plans persist and display their validity duration correctly.
- Legacy IBSng plans without the field do not trigger warnings, errors, or broken purchases.
- RouteBox plans and their provisioning remain unchanged.
- Existing IBSng server/group validation and exact group mapping remain intact.
- Smoke tests cover plan persistence and the existing account-creation path.
- No Worker/background service, renewal call, group-change operation, or deletion behavior is modified.

For future renewal work:

- Test expired and active accounts separately.
- Test customer-owned subscription renewal and admin username-based renewal separately.
- Verify actual A1.24 request payloads and resulting account attributes on a test IBSng server.
- Test failure paths and avoid duplicate/partial RouteBox subscription records if IBSng succeeds but local persistence fails.

## 7. Current implementation status (from the architecture review)

Existing integration behavior includes Web Panel authentication/connection, server/group mapping, account creation, Internet username/password assignment, persistence, Telegram credential display, and lookup by username.

Not enabled in the reviewed adapter: renewal, group change, and deletion. Expiration was reported as NULL in the current subscription record; IBSng group rules govern time/quota today. External accounts are not automatically linked to RouteBox ownership by lookup alone.

This status must be rechecked against the current repository before coding, since the code may have changed after the review.

## 8. Change discipline

1. Inspect the current main-branch code and relevant IBSng A1.24 source.
2. Trace each change through admin UI → plan persistence → catalog/provisioning/provider → Telegram where applicable.
3. Implement the smallest change that meets the stated requirement.
4. Run focused tests/smoke tests and review the diff for accidental RouteBox or Worker changes.
5. Update this document when behavior or status changes. Keep documented facts, planned behavior, and completed implementation clearly distinguished.
