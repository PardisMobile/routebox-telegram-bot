# ATD Stats implementation plan

The stable baseline is `df05c42`.

Required UI behavior:

- Dashboard: global Servers / Users / Plans / Version.
- Telegram Bot: global Servers / Users / Plans plus Bot Server status, public IP, country flag, and worker reload.
- RouteBox Servers: provider-scoped Servers / Users / Plans plus Server Status (connection, IP, ping, flag, refresh/test).
- IBSng: provider-scoped Servers / Users / Plans plus Server Status.
- MikroTik WireGuard: provider-scoped Servers / Users / Plans plus Server Status.
- Users and Payment Settings: no stats row.
- Security and Updates: retain the existing global four-card row.

Implementation constraint: do not replace or reconstruct the existing provider page body. Stats must be inserted into the existing page layout without buffering/replacing the full provider HTML and without changing provider provisioning logic.
