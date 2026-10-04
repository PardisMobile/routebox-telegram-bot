# Roadmap

## ATD Panel — Current UI phase

### Completed
- Unified Dashboard and provider status-card presentation.
- Four-card stats layout preserved across Dashboard, Bot and provider sections.
- Dashboard Version card includes CPU, RAM and Disk.
- Telegram Bot worker status/reload presentation.
- RouteBox / IBSng / MikroTik server cards standardized.
- Provider plan action buttons standardized: Edit / Enable-Disable / Delete.
- IBSng group-name / Provider Plan Key semantics preserved.
- Users page four-card summary placed at the top.
- Cross-browser server flag rendering.
- Public RouteBox section moved to `section=routebox` alias while preserving legacy internal action contracts.
- Server lists placed before Add Server forms.
- RouteBox server deletion exposed through the existing backend endpoint.

### Next
- Replace Dashboard `Project Website` placeholder with the real project URL when supplied.
- Continue visual polish only after functional regression testing.
- Add/extend server and peer search where large datasets justify it.
- Keep pagination for large MikroTik peer/server datasets.
- Continue updating `docs/ATD_PANEL_WORKING_NOTES.md` as the durable handoff.

## Protected boundary

Do not rewrite provider APIs, provisioning, repositories, IBSng group mapping, MikroTik peer allocation or other tested business logic for presentation-only changes.
