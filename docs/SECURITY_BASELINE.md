# ATD Panel — Security Baseline

## Purpose

This document is the security contract for the ATD Panel / RouteBox Telegram Bot project. It is intentionally separate from the UI work so future UI changes do not weaken security-sensitive code.

The goal is not to claim that the application is "unhackable". The goal is to make security review repeatable, evidence-based, and strict enough that common web/application attacks are actively tested before release.

## Non-negotiable rules

1. **UI-only work must not alter Provider business logic or provider functions unless the change is explicitly required and reviewed.**
2. The application web root must remain `public/`; `src/`, `config/`, `storage/`, database files, backups, and secrets must never be directly web-accessible.
3. Never concatenate untrusted input into SQL. Use parameterized queries / prepared statements everywhere.
4. Escape untrusted data at the output/context boundary. HTML output must use the project's escaping helper; do not print raw user/provider data into HTML.
5. Every state-changing browser action must have authorization checks and CSRF protection where applicable.
6. Authentication and authorization are separate requirements. A valid session must not grant access to another resource merely because its numeric ID is known.
7. Provider credentials, bot tokens, database credentials, encryption keys, and other secrets must never be committed to Git, rendered into HTML, or exposed in logs/errors.
8. Provider/API responses are untrusted input. Do not render or execute them as HTML, JavaScript, shell commands, SQL, or filesystem paths.
9. Do not disable TLS certificate/hostname verification in production integrations.
10. Do not expose stack traces, SQL errors, absolute filesystem paths, credentials, or internal provider responses to the browser.
11. File upload/download paths must be constrained and authorized. Prevent path traversal and arbitrary file disclosure.
12. Shell/process execution must be treated as a high-risk boundary and reviewed for command injection and unsafe argument construction.
13. Security fixes must be minimal and localized where possible; do not rewrite working Provider implementations just for UI changes.
14. Any security-sensitive change must be syntax-checked and regression-tested before deployment.

## Threat model / attack classes to test

### Web application

- SQL injection: query parameters, POST fields, IDs, search/filter/sort values, provider fields, plan fields, Telegram/user-derived values.
- Stored XSS: server names, plan names, group names, usernames, provider messages, error messages, notes, and any database-backed text.
- Reflected XSS: query parameters and validation/error paths.
- DOM XSS: client-side rendering, `innerHTML`, template strings, URL/query parsing.
- CSRF: every state-changing admin action.
- IDOR/BOLA: changing server/plan/user/peer IDs must never expose or mutate unauthorized resources.
- Authentication bypass and session fixation.
- Brute force / credential stuffing against admin authentication.
- Session cookie weaknesses: Secure, HttpOnly, SameSite, expiration, regeneration.
- Clickjacking and browser security headers.
- Open redirects.
- Path traversal / local file disclosure.
- Arbitrary file upload / unsafe download.
- SSRF: provider/server URL, host, port, callback/webhook, or other network destinations controlled by an administrator or user.
- Command injection / argument injection in shell/process calls.
- Unsafe deserialization.
- PHP include/require path manipulation.
- Error and debug information disclosure.

### Provider / integration layer

Review all RouteBox, IBSng, MikroTik, Telegram, payment, and updater boundaries for:

- credential leakage
- TLS verification
- certificate/hostname validation
- unsafe URL construction
- untrusted provider responses
- timeout handling
- retry behavior
- response-size limits where appropriate
- command execution
- authorization before destructive operations
- safe logging/redaction

### Database

- Prepared statements for all variable data.
- Correct unique constraints and foreign keys.
- No unintended cascade that can delete unrelated data.
- No plaintext secrets.
- No debug/test credentials in production data.
- Backup files protected from direct web access.
- Least-privilege database account where deployment architecture permits.

## Source-code review checklist

Before a release, search the complete repository for at least:

- `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `WHERE`, `ORDER BY`, `LIMIT` and dynamic SQL construction.
- `query(` / `exec(` and string interpolation around SQL.
- `echo`, `print`, raw template output, `innerHTML`, HTML concatenation.
- `eval`, `assert` with dynamic input, dynamic `include` / `require`.
- `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, backticks, and process-spawning libraries.
- `curl`, HTTP clients, URL construction, redirects, and provider endpoints.
- `move_uploaded_file`, upload handling, `readfile`, `file_get_contents`, filesystem paths, archive extraction.
- `unserialize` / serialization boundaries.
- password/token/secret logging.
- disabled TLS verification such as `CURLOPT_SSL_VERIFYPEER => false` or `CURLOPT_SSL_VERIFYHOST => false`.
- debug flags, verbose errors, `display_errors`, development-only routes, test endpoints, and hard-coded credentials.

Search results must be reviewed manually; a text match alone is not proof of a vulnerability.

## Authentication / authorization requirements

- Admin login must use secure password hashing and constant-time verification APIs.
- Regenerate the session ID after successful authentication.
- Enforce authorization on every sensitive endpoint/action server-side; hiding a button in the UI is not authorization.
- Destructive operations require explicit authorization and appropriate CSRF protection.
- Login failure responses should not reveal whether a username/account exists.
- Add rate limiting / lockout controls appropriate for the deployment environment.

## Output encoding requirements

Use context-appropriate escaping:

- HTML text/attribute context: the project's HTML escaping helper.
- JavaScript context: do not interpolate raw user data into executable JS; use safe JSON encoding where required.
- URL context: validate allowed schemes/hosts and encode components.
- CSS context: never interpolate arbitrary user-controlled CSS values.

Do not rely on a Content Security Policy as a replacement for output encoding.

## API / provider boundary requirements

Provider credentials must remain server-side. Browser JavaScript must not receive:

- provider passwords
- API tokens
- encryption keys
- database credentials
- Telegram bot tokens
- internal service credentials

Provider errors should be normalized before reaching the UI.

## Browser / HTTP hardening

The production deployment should use HTTPS and, where compatible with the deployment, enforce:

- Strict-Transport-Security
- Content-Security-Policy
- X-Content-Type-Options: nosniff
- frame protection via CSP `frame-ancestors` and/or X-Frame-Options as appropriate
- Referrer-Policy
- appropriate Permissions-Policy

Headers must be introduced carefully and tested against the existing UI, especially inline scripts/styles and third-party assets.

## File and deployment boundaries

Expected architecture:

```text
project/
  public/        <-- web-accessible document root
  src/           <-- server-side application code
  config/        <-- server-side configuration/secrets
  storage/       <-- server-side runtime data, DB, backups
```

Never expose the project root as the web document root.

Do not commit:

- `.env`
- local configuration with secrets
- SQLite/database files containing production data
- backups
- private keys
- API tokens
- bot tokens
- provider passwords
- generated runtime logs

## Security testing levels

### Level 1 — every change

- PHP syntax check on changed PHP files.
- Existing automated tests, if present.
- Manual authorization/CSRF smoke test for changed admin actions.
- Verify no secrets or debug output were introduced.
- Verify UI changes do not change provider business logic.

### Level 2 — before beta/release

- Full repository source review using the attack-class checklist above.
- SQL injection review.
- XSS review.
- CSRF review.
- IDOR/authorization review.
- authentication/session review.
- SSRF review.
- command injection review.
- file/path traversal review.
- TLS and HTTP-header review.
- dependency/security configuration review.
- production-vs-development configuration review.

### Level 3 — deployment validation

From an isolated test client, verify:

- unauthenticated access is denied for admin pages/actions.
- invalid/expired CSRF tokens are rejected.
- changing object IDs cannot cross authorization boundaries.
- SQL metacharacters do not alter query behavior.
- XSS payloads are rendered inert.
- malicious URLs cannot make the server access unintended internal destinations.
- filesystem traversal cannot read arbitrary files.
- shell metacharacters cannot alter executed commands.
- production errors do not disclose internals.
- secrets are absent from HTML, JavaScript, logs, and Git history where feasible.

Only non-destructive tests should be performed against production. Destructive/security-exploit tests belong in the DEV/staging environment or a controlled copy.

## Known security-sensitive areas to review first

- `public/` routing and web-server configuration.
- Admin authentication/session handling.
- All Admin POST actions.
- `src/Database/*` and repositories.
- RouteBox provider integration.
- IBSng integration and group/account creation.
- MikroTik client/provider integration, especially TLS verification and network destinations.
- Telegram bot webhooks/API integration.
- updater/install scripts.
- backup/export/download functionality.
- any shell/process execution.

## UI safety boundary

The ATD Panel UI can be redesigned extensively without changing the application's core provider behavior. UI work must preserve:

- existing routes/section names unless a route migration is explicitly planned;
- existing provider function signatures and behavior;
- existing database schema/semantics;
- existing CSRF and authorization checks;
- existing server/plan/provider relationships;
- existing four dashboard cards and their data source;
- pagination behavior and server/peer limits;
- existing language switching behavior;
- existing dark/light/theme infrastructure.

## Release gate

A release is **not security-approved** merely because it passes `php -l` or because the UI works.

Release status must be classified as:

- **BLOCKED** — any known Critical/High vulnerability, exposed secret, authentication bypass, SQL injection, stored XSS, command injection, arbitrary file read/write, or broken authorization boundary.
- **CONDITIONAL** — only Medium/Low findings remain and each has an explicit mitigation/acceptance decision.
- **APPROVED FOR RELEASE** — no known blocking findings, security regression tests pass, and deployment boundaries are verified.

## Important limitation

No code review can honestly guarantee that an application is impossible to hack. The objective of this baseline is to prevent the common failure mode of assuming that "the UI works" means "the application is secure". Findings must be reported with severity, affected file/function, exploit precondition, impact, evidence, and the smallest safe remediation.

## Change history

- Initial baseline: ATD Panel security contract established during the ATD Panel UI hardening phase.
