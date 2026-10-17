# mCMS — current status

Last reviewed: **2026-10-18**, branch `main`. Core version in `src/Core/Version.php`: **0.1.10**.

This describes the current source tree, **not necessarily the exact contents of the
latest published release ZIP/image**. For released artifacts and their changes see
[Releases](https://github.com/mirivlad/mcms/releases) and [CHANGELOG](../CHANGELOG.md).
Historical work checkpoints are preserved in
[the archived status](history/STATUS-2026-10-17.md).

## Distribution and installation

- Docker Compose / Portainer using persistent database, module, theme and backup volumes.
- Standalone/shared hosting with PHP 8.2+ declared in Composer and the installer
  guide; PHP 8.4 is the Docker image target. See compatibility CI results before
  treating every lower PHP version as independently verified.
- The release's **shared-hosting ZIP** includes PHP dependencies and frontend
  assets; the GitHub-generated *Source code ZIP* is not the installer.
- Only `public_html` should be the public DocumentRoot on shared hosting;
  `.env`, `vendor`, `app`, `src`, `writable` and backups remain private.
- A browser installer, Core/module migrations, admin diagnostics, updater and
  full-site ZIP backup/restore are available. Operators must keep off-host backups.

## Core and extensions

- CodeIgniter 4.7.4, Shield and Settings; authentication, public accounts, groups,
  permissions, settings, admin UI, theme API, navigation services and module lifecycle.
- Core does **not** run every module's migrations indiscriminately.
- Bundled content modules include Media, Pages, News, Menus, Galleries, Feedback and SEO.
- Bundled commerce modules include Catalogue, ProductImages, Stock, Cart,
  Orders, Delivery, Notifications and API integration. Each module owns its data
  and publishes services instead of coupling business logic into Core.
- Optional analytics records server-side visits using pseudonymous identifiers;
  see [privacy details](../modules/Analytics/README.md).
- Four bundled themes: Default, Signature, Depth and Focus.
  Additional trusted themes and modules can be installed from ZIP or catalog.
- Package enable/disable, dependency checks, controlled updates and remove/retain-data
  or complete-removal flows are implemented; third-party PHP packages execute with
  application privileges and **must be trusted**.

## Verification

- `.github/workflows/ci.yml`: Composer validation, PHP lint, PHPUnit, UI/script
  checks, route/bootstrap checks and Compose/security contract validations.
- `.github/workflows/clean-install.yml`: end-to-end clean Docker installation,
  module and browser-facing workflows, backups and recovery smoke tests.
- CI status must be read **live** from [GitHub Actions](https://github.com/mirivlad/mcms/actions);
  old successful or failed checkpoints do not describe its current health.
- The repository also publishes release bundles and container images on release tags.
- See [verification guidance](VERIFICATION.md) for local deployment/recovery tests.

## Known boundaries

- mCMS is maintained as a self-hosted project, not a hosted SaaS.
- External modules/themes are trusted code, not sandboxed packages.
- Some optional extension updates need an operator-controlled backup and recovery flow.
- PHP hosting quotas can limit large backup/restore operations.
- Application security and analytics privacy depend on correct proxy/TLS,
  hosting and site-operator configuration.
- No guaranteed compatibility with arbitrary third-party themes/modules.
