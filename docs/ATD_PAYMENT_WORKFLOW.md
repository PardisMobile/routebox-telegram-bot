# ATD Panel — Manual / Card-to-Card Payment Workflow

## Scope

Card-to-card is a Provider-neutral payment feature for the existing Telegram Bot and existing Worker. It must not create a second Worker and must not duplicate Provider provisioning.

## Current implementation state

The existing Web Panel surface is `section=payment-settings` and is the authoritative place for shared card-to-card configuration. It now exposes the card-to-card settings required by the planned workflow.

The complete customer receipt/approval/provisioning lifecycle is **not marked production-complete yet**. It must be implemented and end-to-end tested before the roadmap marks it complete.

## Target flow

Customer:

1. Select a Provider/service category from the existing dynamic Service Catalog.
2. Select an enabled Plan from the existing Provider-owned Plan catalog.
3. Create a Provider-neutral Order.
4. If card-to-card is enabled, show the configured card/bank instructions.
5. Customer submits a receipt in Telegram.
6. Receipt is associated with the correct User, Order and Payment.
7. Payment becomes pending review.

Admin:

1. Authorized Telegram Admin opens Payments.
2. Pending receipts are listed with Order, customer, Provider, Plan and amount.
3. Admin reviews and approves or rejects.
4. Only an approved payment may enter provisioning.
5. Existing Provider/service provisioning is called; Provider protocol logic remains in its existing module.
6. Successful provisioning activates the service and sends the correct credentials/configuration.
7. Provisioning failure is explicit and retryable without creating a duplicate service.

## Required state model

Order states should support at least:

- `pending_payment`
- `receipt_pending`
- `approved`
- `provisioning`
- `completed`
- `rejected`
- `provision_failed`
- `cancelled`

Payment states should support at least:

- `awaiting_receipt`
- `review`
- `approved`
- `completed`
- `rejected`
- `failed`

Exact state names must follow the existing database/application implementation once the lifecycle is completed; do not introduce a second competing state machine.

## Security / idempotency requirements

- Telegram Admin authorization is based on Numeric Telegram ID.
- User receipt submission must be tied to the user's own Order/Payment.
- Admin approval must be an atomic, conditional state transition.
- A repeated callback or double-click must not provision twice.
- A repeated Worker job must be safe/idempotent.
- Provisioning must use the existing Provider/service layer.
- Receipt storage must not expose arbitrary application filesystem paths.
- Variable SQL values must use prepared statements/parameter binding.
- Credential values must never be written to audit logs.

## Provider compatibility

The workflow is Provider-neutral:

```text
Dynamic Service Catalog
        ↓
Provider-owned Plan
        ↓
Order / Payment
        ↓
Verified approval
        ↓
Existing Service / Provider provisioning
```

The Bot must not hard-code RouteBox, IBSng or MikroTik Plan catalogs. `provider_key` and Provider Plan Key remain Provider-specific data owned by the existing service architecture.

## Future gateways

The same Order/Payment layer must support future adapters such as ZarinPal and Crypto without rewriting Provider provisioning or the Telegram Bot flow.
