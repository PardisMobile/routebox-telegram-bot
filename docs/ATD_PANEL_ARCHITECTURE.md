# ATD Panel — Architecture

## Goal

ATD Panel is the provider-neutral administration layer for RouteBox Telegram Bot. It must not become a RouteBox-only admin panel.

The current working integrations are RouteBox, IBSng and MikroTik WireGuard. Future providers such as V2Ray must fit the same navigation, server, plan, guide and provisioning architecture without redesigning the panel.

## Safety rule

The current production-tested provider flows are the baseline. The ATD Panel refactor must be additive and incremental. Do not rewrite stable RouteBox, IBSng or MikroTik provisioning code merely to change the UI.

## Sidebar

```text
Dashboard
Telegram Bot
RouteBox Servers
Plans        <-- removed from top-level after provider migration
IBSng Servers
MikroTik WireGuard
Security
Updates
```

The final provider entries are expected to expose a provider-scoped submenu:

```text
RouteBox Servers
  ├─ Servers / Connection
  ├─ Plans
  └─ Usage Guide

IBSng Servers
  ├─ Servers / Connection
  ├─ Plans
  └─ Usage Guide

MikroTik WireGuard
  ├─ Servers / Connection
  ├─ Plans
  └─ Usage Guide
```

The exact visual navigation can use collapsible provider groups while preserving the existing icon set.

## Shared provider contract

Each provider owns:

1. Provider identity and label.
2. Server configuration and connection tests.
3. Provider-specific setup/connection guide.
4. Provider-scoped plans.
5. Provider-specific operational controls where required.

The shared ATD Panel owns:

- sidebar and page shell
- authentication, CSRF and security
- reusable cards/tables/forms
- shared plan CRUD presentation
- price field and currency handling
- users and subscription management
- user details
- payment settings and order/payment foundations
- Telegram Bot administration
- multi-provider usage-guide management
- updates/recovery UX

## Plans

Plans are provider-scoped. There must be one consistent Plan UI for every provider.

Common plan fields:

- name
- provider
- category/service
- duration
- quota/traffic when applicable
- price
- currency
- enabled/disabled
- sort order
- optional provider-specific metadata

A price of `0` must be treated according to an explicit product rule. It must never silently become a normal customer-purchasable paid service. The Telegram flow must validate the price/payment state before provisioning when payment is required.

Provider-specific fields belong in an extensible metadata/config layer rather than being hard-coded into the shared plan screen.

## Usage Guides

There are two distinct guide layers:

### Telegram Bot service guides

Managed under `Telegram Bot` and editable per service/provider. These are customer-facing connection instructions such as Android, iOS, Windows, macOS, OpenVPN, L2TP, WireGuard/AmneziaWG, etc.

The existing guide content must be migrated without loss.

### Provider administration guides

Each provider has its own Admin Panel guide explaining how to add/configure/test that provider's server. Examples:

- RouteBox: API credentials, URL, TLS and smoke test.
- IBSng: panel/API credentials, group mapping and account provisioning prerequisites.
- MikroTik WireGuard: RouterOS REST access, WireGuard interface, endpoint, pool and listen-port detection.
- Future V2Ray: its own connection, API and server prerequisites.

## Users

`Users` is a provider-neutral administration area.

It should provide:

- Telegram users list
- search by Telegram ID/name/username/phone where stored
- active subscriptions
- expired subscriptions
- provider and plan
- subscription expiry
- service status
- user details page
- safe enable/disable/renew/manage actions
- audit-friendly operational history where available

Provider-specific account actions remain inside the provider adapter; the shared user screen should not contain provider-specific SQL or API logic.

## Payment Settings

Payment is a first-class shared subsystem, not a RouteBox feature.

The panel should prepare for:

- payment provider configuration
- currency
- order lifecycle
- payment status
- callback/verification configuration
- coupons/discounts
- provider-neutral paid service provisioning
- admin bypass with auditability where the Telegram Bot Admin roadmap requires it

Existing payment abstractions must be preserved and extended rather than duplicated.

## Extension rule for future providers

Adding a provider such as V2Ray should normally require:

```text
New Provider
   ├─ integration/client
   ├─ server adapter
   ├─ plan/provider metadata
   ├─ provider admin adapter
   ├─ provider guide
   └─ provisioning/service adapter

No redesign of:
   ├─ sidebar
   ├─ shared Plan UI
   ├─ Users
   ├─ Payment Settings
   └─ Telegram Bot guide management
```

## Implementation sequence

1. Freeze current stable behavior on `ATD-Panel`.
2. Introduce provider-admin contract/registry (foundation).
3. Build shared visual shell and provider navigation without changing provisioning.
4. Migrate RouteBox Plans to provider-scoped shared Plan UI.
5. Migrate IBSng Plans.
6. Migrate MikroTik Plans.
7. Add common `price`/currency support to all service plans and enforce payment-state behavior.
8. Add Telegram Bot multi-service Usage Guides while preserving existing content.
9. Add provider-specific Admin Guides.
10. Add Users + User Details.
11. Add Payment Settings using the existing payment abstractions.
12. Update README, CHANGELOG and ROADMAP after each stable milestone.
13. Only then consider additional providers such as V2Ray.

## Non-negotiable compatibility requirements

- Do not break existing RouteBox provisioning.
- Do not break existing IBSng provisioning.
- Do not break existing MikroTik WireGuard provisioning.
- Do not remove existing Telegram Bot guides during migration.
- Do not duplicate payment logic inside providers.
- Do not hard-code future provider names into shared plan/user/payment code.
- Keep provider-specific API/client code isolated from shared Admin Panel components.
