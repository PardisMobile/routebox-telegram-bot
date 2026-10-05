# ATD Panel — Manual / Card-to-Card Payment Workflow

## Scope

This slice adds the first manual payment workflow for the existing Telegram Bot and existing Worker. It does not create a second worker and does not duplicate provider provisioning.

## Flow

Customer:

1. Selects a provider/service category from the existing dynamic Service Catalog.
2. Selects an enabled Plan from the existing `service_plans` catalog.
3. If card-to-card is enabled, the bot creates a provider-neutral Order and Payment record.
4. The bot displays the configured card/bank instructions.
5. Customer sends a receipt image or PDF in the same Telegram chat.
6. The receipt is stored as the Telegram `file_id`; the bot does not copy the upload into an application-controlled public directory.
7. Payment enters `review` and Order enters `receipt_pending`.

Admin:

1. Authorized Telegram Admin opens `Payments`.
2. Pending receipts are listed with Order, customer, Provider, Plan and amount.
3. Admin opens the receipt and can approve or reject it.
4. Approval atomically claims the payment state before provisioning.
5. Existing `ServiceProvisioner` is called with the selected Plan; Provider-specific provisioning remains in the existing Provider integration layer.
6. Successful provisioning completes the Order and Payment state and notifies the customer.
7. A provisioning failure is persisted as `provision_failed` and exposes a controlled retry action to Admin.

## State model

Order states used by this workflow:

- `pending_payment`
- `receipt_pending`
- `approved`
- `provisioning`
- `completed`
- `rejected`
- `provision_failed`
- `cancelled`

Payment states:

- `awaiting_receipt`
- `review`
- `approved`
- `completed`
- `rejected`
- `failed`

## Security / idempotency

- Telegram Bot Admin authorization remains based on numeric Telegram IDs.
- Payment callbacks are handled only after Admin authorization succeeds.
- User receipt uploads are associated with the user's own active Order.
- Order/Payment state transitions use conditional SQL updates so a repeated approval cannot claim the same provisioning transition twice.
- Provisioning is delegated to the existing `ServiceProvisioner`.
- Receipt files are represented by Telegram file IDs instead of exposing an application upload directory.
- All variable SQL values use prepared statements.

## Configuration

The existing `section=payment-settings` surface now contains a dedicated card-to-card configuration block:

- Enable / Disable card-to-card payments
- Card number
- Card holder
- Bank
- Payment instructions
- Existing global currency / gateway settings remain separate

Card-to-card is disabled by default so existing customer provisioning remains unchanged until the operator configures and enables the workflow.

## Provider compatibility

The workflow is provider-neutral. The Order stores the selected Plan and its `provider_key`; approval passes the Plan ID into the existing `ServiceProvisioner`. No RouteBox, IBSng or MikroTik provider implementation was copied into the payment layer.
