# CI / Installer Alignment

The production installer was refactored and `install-v2.sh` is retired.

Current installer structure:

- `install.sh` — public production entrypoint
- `installer-core.sh` — production installer implementation
- `install-dev.sh` — development installer
- `install-dev-full.sh` — development/full modular-services installer

The CI validation must target these current files and must not execute or require `install-v2.sh`.

The CI workflow was aligned with this structure in commit `74d038243bfe7ff8639ad3930d220da24e32a476`.
