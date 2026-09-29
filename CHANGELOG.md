# Changelog

## 0.1.0-beta.7

### Fixed

- Admin Panel TLS integration no longer changes the configured public Admin Panel port.
- HTTP and HTTPS are now both accepted on the same Admin Panel port.
- RouteBox's own panel/API listener remains untouched.
- RouteBox's exported panel certificate is reused only for the Bot Admin Panel TLS path.
- Added a dedicated Bot-owned HAProxy TCP multiplexer: plain HTTP goes to the local PHP listener, TLS ClientHello goes to the local stunnel TLS terminator.
- Added separate loopback-only backend ports so the PHP and TLS services are never exposed publicly.
- TLS setup now performs explicit HTTP and HTTPS health checks on the same public port before reporting success.
