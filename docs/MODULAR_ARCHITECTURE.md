# Modular services architecture

This branch introduces the foundation for adding new service backends without moving provider-specific logic into the RouteBox core or Telegram worker.

## Rules

1. The existing RouteBox/AWG flow remains the default and is not rewritten as an IBSng flow.
2. IBSng is an additional provider under `src/Integrations/IBSng/`.
3. Future providers such as MikroTik get their own directory and adapter.
4. Payment gateways live under `src/Integrations/Payment/` and implement `PaymentGatewayInterface`.
5. Orders are created before payment and become payable through any enabled gateway.
6. Provisioning happens only after a payment is verified as successful.
7. Coupons modify the order total; the gateway receives the final total.
8. Telegram should call provider-agnostic service operations instead of IBSng/RouteBox RPC directly.
9. Admin UI is an orchestration/configuration layer; provider protocol code stays in its module.
10. Existing `routebox_servers`, `plans`, `provisions` and RouteBox client code are retained for backward compatibility.

## Provider layout

```text
src/Integrations/
├── ServiceProviderInterface.php
├── IBSng/
│   └── IBSngClient.php
├── MikroTik/                 # future
│   └── MikroTikClient.php
└── Payment/
    ├── PaymentGatewayInterface.php
    ├── PaymentResult.php
    ├── ZarinPal/             # future
    └── ...                   # future gateways
```

## Service model

The first new category is intended to be a single IBSng subscription category shown as **OpenVPN / Cisco / L2TP**. One IBSng account is created for the selected group; the three access methods are not treated as three independent products.

The existing RouteBox plans remain under the existing RouteBox/WireGuard service category.

## Database separation

The additive migration `database/migrations/001_modular_services.sql` introduces:

- `service_categories`
- `ibsng_servers`
- `ibsng_groups`
- `service_plans`
- `service_subscriptions`
- `orders`
- `payment_providers`
- `coupons`
- `coupon_redemptions`

No existing RouteBox table is removed or repurposed.

## IBSng connection

`IBSngClient` talks to the IBSng JSON-RPC Admin API using only:

- server host/IP
- API port (default `1237`)
- Admin username
- Admin password

It can list groups and retrieve group information, then create/update/renew users through the IBSng API. It does not access the IBSng database or require the IBSng web panel files.

The implementation is based on the documented IBSng JSON-RPC methods such as `group.listGroups`, `group.getGroupInfo`, `user.addNewUsers`, `user.getUserInfo`, `user.updateUserAttrs` and `user.renewUsers`.

## Next integration steps

The safe implementation order is:

1. Run the additive migration from the application's controlled migration path.
2. Add an **IBSng** section to the existing Admin Panel without changing the existing RouteBox sections.
3. Add multi-IBSng server CRUD and encrypted credentials.
4. Add group synchronization from each IBSng server.
5. Add the service category and map display plans to IBSng groups.
6. Add the Telegram second category **OpenVPN / Cisco / L2TP** while leaving the existing WireGuard category intact.
7. Add subscription/account status and IBSng remaining time/traffic views.
8. Add an order layer and coupon validation.
9. Add payment gateway adapters and enable provisioning only after verification.
10. Add broadcast messaging as a separate Telegram administration module.
