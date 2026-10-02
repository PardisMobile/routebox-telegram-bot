# RouteBox Telegram Bot Roadmap

## ✅ Completed

- Modular Service Architecture
- Provider based provisioning
- RouteBox and IBSng independent services
- IBSng A1.24 Web Panel integration
- Login and session management
- Real IBSng user provisioning
- Internet Username and Password generation
- Subscription persistence
- Plan → Server → Group mapping
- Worker integration and provisioning test

## 🔜 Next Development Phase

### Telegram Bot Admin

- [ ] Independent admin access system
- [ ] Multiple Telegram ID support
- [ ] Admin management from Web Panel
- [ ] Dedicated Admin menu

### Admin Operations

- [ ] Skip Payment for Bot Admin
- [ ] Create service without payment
- [ ] Keep customer payment flow unchanged

### IBSng Management

- [ ] Search User by Username
- [ ] Display User information
- [ ] Persian expiry date
- [ ] Traffic usage display
- [ ] Create User from Bot Admin
- [ ] Server and Plan/Group selection
- [ ] Configurable Username Prefix
- [ ] Renewal from configured IBSng Plans only
- [ ] Edit User information
- [ ] Admin IBSng testing tools

### Worker Management

- [ ] Worker status
- [ ] Worker restart from Panel
- [ ] Health monitoring

### Payment System

- [ ] Order lifecycle
- [ ] Coupons
- [ ] Payment verification
- [ ] ZarinPal integration
- [ ] Verified-payment provisioning

## Rules

- IBSng provisioning flow must remain stable.
- Telegram Admin permissions are independent from IBSng.
- Normal customer payment flow must not change.
- New features extend existing providers without rewriting provisioning.
