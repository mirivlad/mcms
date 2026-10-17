# mCMS 0.1.9 — first public dual-deployment release

This release supports both Docker/Portainer deployments and **conventional Apache/PHP/MySQL shared hosting** without requiring Docker, SSH or Composer on the hosting account. It uses one Core/module/theme implementation for both modes.

**Get started / Начало работы:** [English manual](https://github.com/mirivlad/mcms/blob/main/docs/guide/en/README.md) · [Руководство на русском](https://github.com/mirivlad/mcms/blob/main/docs/guide/ru/README.md). The official release asset `mcms-0.1.9-shared-hosting.zip` includes all PHP dependencies, bundled frontend resources, **both user manuals**, and a browser-first MySQL installer. The standard Git Stack uses `portainer-stack.yml`; no Docker Hub `mcms:dev` image is needed.

## Major additions

- Standalone installation with PHP/extensions/document-root preflight, MySQL credentials and initialization, optional SMTP, and a first-superadmin wizard.
- Apache/PHP Media delivery with strict filenames and private-directory isolation.
- PHP-only logical backups, database restore, module/theme updates and recovery procedures for hosts without shell utilities.
- Validated, manual **same-minor patch** Core ZIP upgrades for standalone, including a site backup, staged code swap and independent pre-bootstrap recovery logic.
- Russian and English user and administrator documentation: install, update, modules, themes, backup, restore, rollback, troubleshooting for both deployment modes.

## Release safety notes

- **Back up and download the ZIP off-server** before updates; preserve compatible Core source/images and deployment configuration. Never remove production Docker volumes during redeploy.
- The backup schema is tied to the **exact Core version**. Standalone PHP database exports are not interchangeable with Docker's CLI SQL dumps through the web restore wizard.
- The Core ZIP updater is deliberately limited to patch releases in the current X.Y line, with the same bootstrap entry files. Other upgrades require supervised manual work. Automatic updates are **off by default**.
- PHP shared-hosting quotas, timeouts, storage permissions and SQL dialect differences can prevent large-site restores. Keep external backups and test recovery on a separate instance. Package hashes detect corruption but do not authenticate unknown third-party code.
- Docker Core auto-update through the Portainer API still **does not guarantee** automatic rollback of image and database migrations.

All artifacts are built from this tag and checksummed in `SHA256SUMS`. Do **not** install the GitHub-generated "Source code" ZIP on shared hosting: it does not include vendor dependencies or built UI assets.
