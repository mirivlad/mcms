# Full-site backups (mCMS 0.1.7 development)

## In administration

Sign in with `core.backups` permission (superadministrator by default) and
open **Administration → Backups** (`/admin/backups`). Click **Create backup**.
After completion, the generated ZIP is listed with a **Download** action.
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

## ZIP layout

```text
manifest.json             # format marker, timestamps, file sizes and SHA-256
database.sql              # MariaDB logical dump, --single-transaction
modules_data/...          # all installed extension code and module data
themes_data/...           # all installed themes and theme-owned files
```

The application image is **not** included: reinstall a compatible mCMS
release on the destination. Postfix's `mail_queue` and `dkim_data` are also
outside site ZIP scope, as is any external mail relay or host-level config.
Do not assume bare-metal recovery from the ZIP alone.

To restore into a **stopped, clean** test installation:

1. Verify every member against `manifest.json` SHA-256 and check archive
   paths for traversal before extraction.
2. Install a compatible application image and configure database credentials.
3. Restore `modules_data/` and `themes_data/` to their corresponding Docker
   volumes, preserving application file ownership.
4. Import `database.sql` into a new empty MariaDB database.
5. Start the application, run `php spark mcms:update` if required by the
   chosen release, and test login, media, catalogue and installed themes.

This release does **not** yet include a one-click restore wizard. Never extract
an untrusted ZIP as root or over a running production stack.

## Consistency limitation

`mariadb-dump --single-transaction --quick` produces an InnoDB-consistent
database snapshot without long table locks. Files are read and hashed during
the archive process. A successful ZIP guarantees that the archived bytes match
the manifest, but cannot provide an **atomic snapshot across MariaDB and
files actively changed by other requests**. Schedule routine backups at quiet
times or place the website in maintenance mode externally for strict
database/files cross-consistency. This limitation is not hidden by the UI.
