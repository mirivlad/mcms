# Changelog

This file tracks **published** mCMS versions, not every development checkpoint.
For a current overview of the source tree see [docs/STATUS.md](docs/STATUS.md);
for detailed engineering history see
[docs/history/STATUS-2026-10-17.md](docs/history/STATUS-2026-10-17.md).

## [Unreleased]

Changes on `main` after the last release are not part of the already published
tagged release until the next release is cut.

## [0.1.10] — 2026-10-17

- Redesigned the public account dashboard and recent order summary.
- Fixed responsive site header behaviour for signed-in accounts.
- Added detailed standalone/web-server installation guidance for Apache, Nginx,
  Caddy and LiteSpeed, including secure private-path/DocumentRoot handling.
- Published complete shared-hosting ZIP artifacts and versioned Docker images.
- See the [0.1.10 release notes](docs/RELEASE_0.1.10.md) for compatibility,
  deployment and rollback instructions.

## Earlier 0.1.x development

Earlier implementation checkpoints (Core, modules, commerce, themes, updater,
installers and recovery) are preserved in the
[historical engineering log](docs/history/STATUS-2026-10-17.md). Consult
[GitHub Releases](https://github.com/mirivlad/mcms/releases) for exact
versioned artifacts rather than treating checkpoints as release notes.

[Unreleased]: https://github.com/mirivlad/mcms/compare/v0.1.10...main
[0.1.10]: https://github.com/mirivlad/mcms/releases/tag/v0.1.10
