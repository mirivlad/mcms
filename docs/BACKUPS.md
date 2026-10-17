# Full-site backups (mCMS 0.1.x)

For complete operator procedures in both deployment modes see
[Русский](guide/ru/recovery.md) or [English](guide/en/recovery.md). The
remainder of this document covers the Docker/CLI backup format; the standalone
PHP logical database format is intentionally different and not directly
importable from Docker backups through the shared-host web restore wizard.

## In administration

Sign in with `core.backups` permission (superadministrator by default) and
open **Administration → Backups** (`/admin/backups`). Click **Create backup**.
After completion, the generated ZIP is listed with **Download**, **Restore** and **Delete** actions. Deleting a backup requires one confirmation and removes only that archive from `backup_data`, not any site files or database records. Backups cannot be deleted while full-site restoration is active.
Only finished archives appear. Archives are stored in the separate Docker
`backup_data` volume, mounted privately at `writable/backups` in the app.
No public HTTP URL or nginx volume mount exposes these files. This volume is
not backed up *inside* its own ZIP.

**Large sites:** archive creation is synchronous in PHP-FPM. Proxy request
timeouts can affect the browser response on a very large site; run the CLI
command for unattended/back-office copies. Only one archive can be generated at
a time. Temporary files are not listed for download. The ZIP is verified
against its manifest before it becomes downloadable.

## CLI and unattended scheduling

```sh
# In the running app container:
php spark mcms:backup:create

# Optional retention; keep the seven newest completed archives:
php spark mcms:backup:create --keep=7

# Example host crontab (adapt Compose project directory):
15 2 * * * cd /srv/mcms && docker compose exec -T app php spark mcms:backup:create --keep=7
```

The command exits nonzero when creation fails. A separate off-host copy job
(e.g. rsync, restic or object-storage upload) **is still necessary**.
A Docker named volume on the same disk is not protection against disk loss.
Make sure the backup volume has sufficient free space, monitor cron exit
status, and periodically test restoration. Do not expose the backup directory
through a reverse proxy. ZIP archives can include personal data, account
password hashes, licensed assets and extension PHP code.

For an unattended local recovery rehearsal, run
`python3 .github/scripts/verify-deployment.py` from the repository root.
It generates test data and restores a downloaded ZIP into a second isolated
instance, compares database records and file hashes, and removes both test stacks.
See [local verification](VERIFICATION.md) for evidence and scope; this does not
schedule or recover your production backups.

## ZIP layout

```text
manifest.json             # format marker, timestamps, file sizes and SHA-256
app-version.json          # application: mCMS, version: e.g. 0.1.6
database.sql              # MariaDB logical dump, --single-transaction
modules_data/...          # all installed extension code and module data
themes_data/...           # all installed themes and theme-owned files
```

The application image is **not** included: reinstall a compatible mCMS
release on the destination. Postfix's `mail_queue` and `dkim_data` are also
outside site ZIP scope, as is any external mail relay or host-level config.
Do not assume bare-metal recovery from the ZIP alone.

For **manual disaster recovery** into a stopped, clean installation:

1. Verify every member against `manifest.json` SHA-256 and check archive
   paths for traversal before extraction.
2. Install a compatible application image and configure database credentials.
3. Restore `modules_data/` and `themes_data/` to their corresponding Docker
   volumes, preserving application file ownership.
4. Import `database.sql` into a new empty MariaDB database.
5. Start the application, run `php spark mcms:update` if required by the
   chosen release, and test login, media, catalogue and installed themes.

## Restore from the administration panel

With `core.backups` permission, open **Administration → Backups**.

1. Click **Restore** beside a saved ZIP, or upload an existing mCMS ZIP from
   your computer (**up to 1 GiB** via the standard web upload configuration).
2. Review the backup's creation date, file count, total unpacked size and SHA-256.
   The complete manifest is checked: paths, bytes, hashes, duplicate entries,
   size limits, forbidden symlinks and expected data root prefixes.
3. Before confirmation, the preview compares `app-version.json` to the
   running `MCMS\Core\Version::VERSION`. If they differ, both versions are
   shown, the **Restore** form is disabled, and the exact version required by
   the archive is displayed. An archive without the version file is also
   rejected, because its compatibility cannot be established. The version
   descriptor has its own SHA-256 entry in `manifest.json`.
4. Check **I understand the existing site data will be replaced** and click
   **Start restoration**. No additional text challenge is required. The source ZIP is privately staged inside `backup_data`.
5. A **fresh CLI worker** sets maintenance mode and creates a safety backup
   of the *current* database and both persistent file volumes before modifying
   any data. While the worker is active, the website returns HTTP 503.
6. The worker stages files on their respective volumes, replaces both sets of
   files, imports the SQL dump, and removes maintenance mode. The admin session
   might need a fresh login because users and permissions are restored.

Status is retained in the private `backup_data/restore` directory and displayed
on the Backups screen. The pre-restore safety ZIP is retained as a normal
downloadable backup. If restoration fails, the worker attempts to return to
the previous files and reimport the safety SQL automatically.

**Important:** An interrupted process, full disk or failed automatic recovery
may leave a `recovery_required` state with the site held in maintenance mode.
Keep an off-host copy and use the safety ZIP for manual recovery as above.
The app image itself is not rolled back; restore ZIPs only into compatible
Core releases. Treat uploaded ZIPs as trusted administrator-supplied data:
installed extension PHP code is executable, and ZIP inspection is not a PHP
sandbox. Never run restoration over a production database that has not been
backed up off-host.

File-and-database snapshots are still not fully atomic if uploads are changing
during the safety backup. Schedule planned maintenance for strict consistency.

## Consistency limitation

`mariadb-dump --single-transaction --quick` produces an InnoDB-consistent
database snapshot without long table locks. Files are read and hashed during
the archive process. A successful ZIP guarantees that the archived bytes match
the manifest, but cannot provide an **atomic snapshot across MariaDB and
files actively changed by other requests**. Schedule routine backups at quiet
times or place the website in maintenance mode externally for strict
database/files cross-consistency. This limitation is not hidden by the UI.

Restore compatibility is intentionally **exact mCMS Core version equality**,
not merely compatible major/minor. The same check runs again immediately before
maintenance starts and inside the restoration worker, so upgrading mCMS between
inspection and execution cannot bypass the guard. Bump `Version::VERSION` for
releases that change the recoverable data format. Older ZIPs without
`app-version.json` remain downloadable but cannot be restored through the
admin interface: recreate the backup under an identified release first.
