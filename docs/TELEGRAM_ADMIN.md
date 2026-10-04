# ATD Panel — Telegram Bot Admin Foundation

This document records the first Telegram Bot Admin implementation slice.

## Implemented

- Independent authorization by Telegram Numeric ID.
- Multiple Admin records and future-ready roles.
- Web management at `public/telegram-admins.php`.
- Dedicated Admin menu integrated into the existing `worker.php`.
- RouteBox and IBSng admin provisioning without customer payment through the existing `ServiceProvisioner`.
- Short-lived Admin-bound action records prevent successful callback replay from provisioning twice.
- Admin provisioning audit records do not store credentials.

## Not yet complete

- Card-to-card Order / Payment / Receipt approval.
- Full IBSng user search/renewal/edit tools.
- Worker health/log management.
- Full security audit.

## Architecture rule

No provider implementation was copied into the Bot. Admin operations call the existing ServiceCatalog/ServiceProvisioner and existing Provider integrations.

MirzaBot remains reference-only and is not a code or architecture dependency.
