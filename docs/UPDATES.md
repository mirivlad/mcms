# Automatic updates

mCMS 0.1.x supports a separate, **opt-in** update policy for Core, catalog-managed ZIP modules and catalog-managed ZIP themes. The background worker lives in the Compose `updater` service. It uses only the existing `modules_data`, `themes_data` and `backup_data` volumes. No host cron or Docker socket mount is required.

## Settings

Administration → Updates (`/admin/updates`, requires `core.system`) controls three switches. **All are disabled by default.** You can exclude individual installed catalog packages. The worker checks on start and every 6 hours by default (`MCMS_UPDATE_INTERVAL_SECONDS=21600`). The browser `Check now` button only refreshes the extension catalog, it does **not** perform installations or trigger Core redeploy.

Automatic checks read the same curated GitHub catalog (HTTPS/SHA-256/ZIP manifest and dependency validation) used for manual package installation. Existing manual install/upgrade/rollback flows are retained.

### Themes

Compatible installed external theme releases are downloaded from the approved catalog, and a full local backup is created before invoking the existing atomic theme updater. Old code is preserved for manual rollback. Active selections, settings and user files are preserved.

### Modules

The automatic updater accepts compatible versions **only if** there are no new/modified/removed migration files and no enabled dependent modules that need coordinated downtime. It temporarily disables only its own module and restores its original enabled state if the update succeeds. If migration or dependency checks fail, it skips the release, cancels the queued change and records the reason. Existing module backup/recovery and maintenance protection remain in force. A skipped/failed extension version is held from subsequent unattended attempts until a newer catalog version is published (the administrator can still update manually). A failed worker may require manual recovery at the existing `/admin/modules/<id>/update` page; it is never silently declared successful.

**Important:** The backup created before an automatic update is local. Keep independent off-host backups. Arbitrary third-party PHP cannot be sandboxed by a ZIP signature or lint check. Enable automatic updates only for publishers whose code you trust. Installing a new extension is always a deliberate admin action.

## Core image updates — Portainer setup

Normal `portainer-stack.yml` builds from Git and does not automatically update the Core when a new Git commit appears. For published stable releases, deploy a Git-backed Portainer **Docker Standalone** stack with `portainer-release-stack.yml`. This uses `ghcr.io/mirivlad/mcms-app:stable` and `ghcr.io/mirivlad/mcms-web:stable` images instead of building from Git. GHCR packages must be publicly readable or the Docker environment must be logged in to GHCR.

1. Release maintainer: publish a GitHub Release tagged `v<Core\Version::VERSION>` (for example `v0.1.9`). The `release-images.yml` workflow builds versioned plus stable app/web images and uploads `mcms-images-ready.json` **only after both images build successfully**. The Core updater ignores a release without this ready asset.
2. In Portainer, configure the Git stack to reference `main` and Compose path `portainer-release-stack.yml`. Turn off competing GitOps polling/forced redeploy (the update worker, not arbitrary Git commits, must decide when to redeploy). Set `MCMS_IMAGE_TAG=stable` explicitly in the stack environment.
3. **Pin MariaDB** with `MCMS_DB_IMAGE=mariadb:11.4@sha256:<full-existing-image-digest>`. Find the trusted manifest digest of the MariaDB version you intend to run in the Docker/Portainer image details. The Core updater refuses to redeploy a stack whose MariaDB image is floating: Portainer's pull/redeploy may otherwise upgrade the database as an unintended side effect. Postfix is already digest-pinned by the stack.
4. Create a Portainer API key for an account authorized to manage only the intended stack if your Portainer edition/configuration permits. Populate **only the updater service** environment with `MCMS_PORTAINER_URL=https://your-portainer-host`, `MCMS_PORTAINER_API_KEY=ptr_...`, `MCMS_PORTAINER_STACK_ID=<numeric id>`, `MCMS_PORTAINER_ENDPOINT_ID=<numeric environment id>`. Keep the key secret. HTTPS with a valid certificate is required. The browser-facing PHP-FPM service does not receive the API key.
5. Visit `/admin/updates` and enable the Core switch. Only newer **stable patch** versions of the same major/minor series qualify. Minor and major upgrades still require manual review.

The worker queries the GitHub Releases API, checks the ready asset, version and target Portainer Git stack identity, confirms the stable image selector and digest-pinned MariaDB, creates a full backup, saves a durable pending receipt and requests Portainer's Git redeploy with `PullImage` enabled. On its next successful run with the new Core version it records completion. If the redeploy connection breaks, its result remains unknown/pending for investigation rather than being declared successful. It never mounts the Docker socket.

**Limitations:** This iteration does **not** implement a reliable automatic rollback of Core images/database after a failed Portainer redeploy. Preserve the previous `vX.Y.Z` images, the backup and Portainer access. In a failure, redeploy the previous matching app/web image version and restore the matching-version full-site ZIP through the existing recovery procedure. Do not rely on local backups as a replacement for tested off-host backups. The Core switch is opt-in and intended for reviewed, compatible patch releases; rolling upgrades and multi-instance deployment are unsupported.

## Verification

Run `php spark mcms:updates:run` in an application/worker CLI to execute a single check. `php spark mcms:catalog:refresh` remains check-only. When all switches are off the CLI returns without downloading or replacing packages. `php spark routes` should show `/admin/updates`, and `composer test` covers policy validation and migration detection. Docker Compose models can be checked with:

```sh
MCMS_DB_PASSWORD=test MCMS_DB_ROOT_PASSWORD=test docker compose config -q
MCMS_DB_PASSWORD=test MCMS_DB_ROOT_PASSWORD=test docker compose -f portainer-stack.yml config -q
MCMS_DB_PASSWORD=test MCMS_DB_ROOT_PASSWORD=test docker compose -f portainer-release-stack.yml config -q
```

## Recovery

Use Administration → Backups to inspect the pre-update safety ZIP and the documented restore process. Backups require **exact Core version equality**. After a Core image failure restore the app and web images that correspond to the archived version before restoring its data. Restore from a trusted off-host copy if the Docker volume is unavailable. Never use `docker compose down -v` during an upgrade or recovery.
