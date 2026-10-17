# Backups, restoration and rollback

[Contents](README.md) · [Русский](../ru/recovery.md)

## Creating and storing full-site backups

Open **Administration → Backups** (`/admin/backups`) → **Create backup**. The list provides **Download**, **Restore** and **Delete**. **Download** the completed archive to off-server storage. Deleting a ZIP deletes only that archive, not your live site.

Each archive contains `manifest.json` (size/SHA-256), `app-version.json` (exact mCMS version), `database.sql`, `modules_data/` and `themes_data/`. It does **not** contain Core binaries/containers, private `.env`, reverse proxy settings, mail queue/DKIM keys or previously created backup archives. Keep a matching release package and server configuration separately.

**Docker:** backups use `mariadb-dump`, restores use an isolated PHP CLI worker. Optional CLI backup: `docker compose exec -T app php spark mcms:backup:create`. Schedule backups via your host or orchestration and monitor errors. For host-level disaster recovery preserve `db_data`, `modules_data`, `themes_data`, `backup_data` and relevant mail volumes.

**Standalone:** the site uses a PHP-only MySQL export format without `mariadb-dump` or process spawning. The `database.sql` member is **not identical** to Docker's ordinary SQL output. A Docker-origin backup may be rejected by the shared-host restore wizard. Cross-mode migration requires a compatible conversion/import procedure. Provider execution, upload and disk quotas may block very large backup/restore jobs.

## Restore through administration

1. Confirm the installed Core version **exactly matches** the archive's `app-version.json`. If it doesn't, install that Core version first.
2. At `/admin/backups`, choose **Restore** on a saved archive or upload a ZIP (the mCMS interface limits uploads to 1 GiB; `upload_max_filesize` and server limits may be lower).
3. Review creation date, file count, total size, integrity and version match. Acknowledge that current site data will be replaced.
4. mCMS enters maintenance (HTTP 503), creates an independent **pre-restore safety backup**, then replaces both file roots and imports the database. On completion you may need to sign in again using credentials restored from the archive.
5. Verify pages, media, modules, themes and access. **Keep the safety ZIP** until the recovered site is verified.

### Interrupted restore

On a failure the worker attempts rollback. If a shared-host PHP request is terminated, the next request checks whether a live worker holds the file lock before trying to restore the safety ZIP. Recovery is **not guaranteed** after disk corruption, permission loss, unavailable database or insufficient host limits. Keep off-server backups.

For a persistent HTTP 503 inspect disk space, PHP error log, `writable/logs` and the restore receipt. **Do not manually remove** `restore-active.json`, `maintenance.json` or `.restore-*` folders: doing so can expose inconsistent data.

## Core rollback

**Docker:** switch back to the previous matching app/web image versions or known-good Git commit and redeploy **without removing volumes**. If schema migrations ran, use the full backup taken before upgrading, while running the **exact matching Core version**. Automatic Core image/database rollback is not guaranteed by the Portainer updater.

**Standalone:** a validated patch ZIP upgrade retains the previous code directories and a full backup. An independent boot-time rescue can restore old code if activation was interrupted and restore database/module files if migrations failed. If hosting filesystem or MySQL access is unavailable, manual recovery from an external backup is still necessary; no software can guarantee rollback after every hardware failure.

## Extension rollback

Module updates retain a ZIP snapshot of previous source. If data migrations have occurred, **restore a matching full-site backup first**, then restore the previous module through `/admin/modules/<id>/update`; replacing PHP alone against an incompatible schema is unsafe. External themes have a separate **Rollback** action using preserved previous source.

## Recommended backup policy

Back up before every update, copy finished ZIP files off-server, schedule routine backups where cron is available and **test restores in a separate environment**. Do not restore into the production DB to test your backups. Archives contain user data, password hashes, PHP extension code and potentially sensitive files; handle them as secrets.
