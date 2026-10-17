# Site operations, upgrades and extensions

[Contents](README.md) · [Русский](../ru/operations.md)

## Everyday administration

Sign in at `/login`, then open `/admin`. The superadmin manages configuration, accounts and permissions, pages, menus, themes and modules. Some sections appear only when their module is enabled. Public self-registration is controlled separately by the site settings.

**Before every Core, module or theme upgrade:** create a full ZIP at **Administration → Backups**, download it off-server, and confirm it matches the current Core release. Ideally restore it into an isolated test site.

## Upgrading Core — Docker/Portainer

**Portainer Git Stack:** open the existing Stack and use its Git pull/redeploy action (the name varies by Portainer version). Pull `main` and rebuild `app`, `web` and `updater`. In the normal `portainer-stack.yml` these are **built from Git**, not fetched as `mcms:dev`. Check new images, MariaDB health, startup logs and login. The `app` entrypoint applies applicable migrations.

**Compose:** from the existing checkout run `git pull --ff-only` followed by `docker compose up -d --build`. Preserve the original Compose configuration and a known-good image/tag.

**Full Portainer auto-update setup:** [fresh installation, existing Stack conversion, API key, Stack/Environment IDs and verification](portainer-auto-updates.md).

**GHCR release stack:** if you deliberately use `portainer-release-stack.yml`, make sure app/web versioned release images exist, change `MCMS_IMAGE_TAG` to the appropriate tag and redeploy. Optional Core auto-updates through Portainer API require dedicated API settings and digest-pinned MariaDB; **do not turn this on without reviewing the full contract**. They do not guarantee automatic image/database rollback. All automatic-update switches start disabled.

## Upgrading Core — standalone/shared hosting

Open **Administration → Updates** (`/admin/updates`). Select a trusted **official standalone release ZIP** with a newer patch version within the same X.Y line (e.g. 0.1.8 → 0.1.9). Preview, verify and confirm. mCMS checks the package manifest and file hashes, creates a safety ZIP, stages source files and enters maintenance. A **new HTTP request** then runs Core/extension migrations. Wait for the result; if a request is terminated, opening the site again invokes an independent pre-CodeIgniter recovery gate.

**Boundaries:** checksums within the ZIP detect corruption but do **not authenticate the publisher**. Obtain packages only from the official release, comparing checksums to trusted published metadata. Code directories change while private `writable` data persists. The host must support same-filesystem directory renames and have disk space for new code, old code and backups. For minor/major or incompatible schema upgrades, use a reviewed manual recovery plan. Extremely restrictive HTTP execution limits may prevent safe web-based updates.

## Install a module

1. Open **Administration → Extension catalog** (`/admin/extensions`) or **Modules** (`/admin/modules`).
2. Choose a catalog package or upload a trusted module ZIP.
3. Review publisher, exact version, compatible Core requirements, dependencies and migrations; confirm. **Uploading/installation alone does not activate the module.**
4. **Enable** the module. Its migrations/permissions are registered at this point and its routes/admin menu become available.
5. **Disable** it to remove its runtime routes while keeping its tables, settings and files.

**Upgrade a module:** generally **disable it first**, open `/admin/modules/<id>/update`, upload the new trusted ZIP, review and queue it, create/download a full backup, then apply. Schema-changing updates may require manual database restoration before code rollback. Executable third-party PHP is not sandboxed.

## Remove a module: two distinct choices

From Modules or the Extension catalog select **Remove**, inspect the affected tables, files and references, and acknowledge your backup.

- **Remove code, keep data:** code is removed and the module disabled, but database tables, configuration, scoped files, migration receipts and version metadata remain. Reinstall the **exact same version** later; its migration checksums must still match. Explicitly enable it afterwards.
- **Remove completely:** remove source and only the resources mCMS can prove the module owns. A correct declared cleanup contract and recorded table ownership are mandatory. If ownership is ambiguous, **mCMS blocks complete deletion**. Without backup this action is irreversible.

Both modes are blocked by **any installed dependent module**, including disabled dependents; removal never cascades. Built-in modules cannot be removed. Failed/interrupted transactions are recoverable through `/admin/extensions/recovery`.

## Themes

Use **Administration → Themes** to upload a trusted ZIP, install, configure and activate. Switch to another theme before removing the current active one. External theme updates keep previous source files for manual rollback. Keep-data removal preserves theme-specific configuration and menu assignments; built-in themes cannot be deleted.

## Check vs install updates

In **Administration → Updates**, **Check now** only refreshes the catalog. It **does not install** anything. Docker's optional `updater` service checks approved catalog updates periodically (6 hours by default). Shared hosts without cron rely on manual administration operations; visiting a public page never runs an unattended installer.
