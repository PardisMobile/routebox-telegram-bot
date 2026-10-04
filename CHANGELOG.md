# Changelog

## ATD Panel — 2026-10-04

### UI / Admin Panel
- Unified RouteBox, IBSng and MikroTik server presentation.
- Public RouteBox section renamed to `section=routebox` while preserving the legacy internal `servers` action contract.
- Added cross-browser image flags for legacy server cards.
- Kept the existing ATD four-card/status/system statistics layout intact.
- Standardized Add Server placement after server lists.
- Added RouteBox server pagination for larger server lists.
- Exposed the existing RouteBox server delete endpoint through the UI.
- Kept IBSng Test Connection and Test Create User actions on their existing implementations.
- Preserved IBSng group-name / Provider Plan Key semantics.
- Unified provider plan action-button layout.
- Preserved the UI-only separation from provider provisioning logic.
- Updated ATD Panel branding and reserved the Dashboard project button for the future project website URL.

### Safety
- `ATDStats.php` remains on the syntax-safe baseline after the previous parse-error incident.
- Provider APIs, provisioning, repositories, MikroTik peer allocation and IBSng group mapping were not rewritten for these UI changes.
