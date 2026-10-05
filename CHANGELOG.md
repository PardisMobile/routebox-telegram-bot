# Changelog

Important user-visible and operational changes for RouteBox Telegram Bot / ATD Panel.

Version source of truth: [`VERSION`](./VERSION)

## 0.1.0-beta.11.05 — ATD Panel current development line

### 🎨 ATD Panel UI

- Unified RouteBox, IBSng and MikroTik provider/server presentation.
- Preserved the protected four-card/status ATD layout.
- Preserved Light/Dark and Persian/English behavior.
- Restored compact desktop sidebar sizing at the confirmed checkpoint `6026a16`.
- Improved mobile navigation/drawer behavior.
- Preserved provider plan actions and Provider Plan Key semantics.
- Kept existing `section=bot` as the shared Bot UI surface; Bot Settings, Bot Buttons and Bot Menu Preview remain protected.
- Added Customer Bot and Telegram Bot Admin overview destinations within the existing `section=` architecture.
- Kept Bot Usage Guides separate from Provider Admin Guides and from the General Guide.

### 🧩 Modular Provider Architecture

- RouteBox, IBSng and MikroTik remain independent Provider integrations.
- Provider/Plan selection remains driven by the existing service catalog and `provider_key` values.
- Provider Plan Key remains Provider-specific; IBSng uses the real IBSng group/plan identifier.
- Username Prefix remains separate from Provider ID and Provider Plan Key.
- No Provider Core, provisioning, peer/IP allocation or Worker polling architecture was rewritten for the UI/Admin work.

### 🤖 Telegram Bot Admin foundation

- Independent Telegram Numeric ID authorization.
- Multiple Admin records and future-ready roles.
- Web Admin management through `public/telegram-admins.php`.
- Dedicated Admin menu integrated into the existing Worker; no second Worker was created.
- Admin service operations reuse existing provider/service provisioning.
- Admin provisioning actions have replay protection and audit logging without credentials.
- Customer Bot/Admin Bot navigation is integrated into the existing `section=bot` panel architecture.

### 💳 Payment Settings / Card-to-Card

- Existing `section=payment-settings` remains the shared payment configuration surface.
- Card-to-card configuration is exposed there.
- Payment architecture remains Provider-neutral.
- The complete Order → Receipt → Review → Approval → Provision lifecycle remains staged and is **not** marked production-complete until end-to-end testing is performed.
- Future ZarinPal/Crypto adapters remain planned.

### 📦 Installer / CI

- Production installer structure is now exactly:
  - `install.sh`
  - `installer-core.sh`
  - `install-dev.sh`
  - `install-dev-full.sh`
- `install-v2.sh` is retired.
- CI/installer validation is aligned with the current four-file structure.
- Installer documentation no longer treats `install-v2.sh` as a current installer.

### 🔐 Security / reliability

- Existing provider credentials and Telegram Bot Tokens remain protected/encrypted according to the current application model.
- Existing Worker lock remains protected.
- Full source-wide security audit remains pending and must not be claimed complete until performed.
- SQL Injection review must follow the project rule: report real injectable queries before fixing them, then use prepared statements/parameter binding and regression-test the behavior.

## 2026-10-05 — Persistent project-state update

The project handoff documentation was synchronized with the current ATD Panel development state. The authoritative files are:

- `ATD_PANEL_WORKING_NOTES.md`
- `ROADMAP.md`
- `CHANGELOG.md`

The last confirmed healthy application/UI checkpoint remains `6026a16`. Documentation commits after that checkpoint do not automatically constitute a new tested application baseline.

### Current Feature Gap priorities

1. Customer Service Details and complete Service lifecycle.
2. Customer Renewal using real configured Provider Plans.
3. Telegram Admin Service Management/Search.
4. Worker-based notifications and expiry lifecycle.
5. Complete payment/order/receipt lifecycle and future gateway adapters.
6. Later: wallet, coupons, referral/affiliate, reseller and other growth features.

### Reference policy

MirzaBot is a research/feature reference only. No MirzaBot source code, database schema, naming, UI or implementation is part of ATD Panel.
