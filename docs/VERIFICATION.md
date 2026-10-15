# Local deployment and recovery verification

From the repository root, run:

```sh
python3 .github/scripts/verify-deployment.py
```

Requires Python 3, Git, a running local Docker daemon and Docker Compose v2.
Docker builds may download base images and locked application dependencies.
GitHub Actions, host PHP/Composer, server credentials and manual browser setup
are not required.

The command creates two uniquely named Compose projects, private test images
and fresh volumes. HTTP binds only to `127.0.0.1` on Docker-assigned ports.
It does not accept an existing stack or site URL as a target. Local `.env`,
`MCMS_*` and `COMPOSE_*` settings are replaced for this run. The mail service
is not started.

## What it checks

1. Storage contracts in both Compose and Portainer configurations.
2. Production image builds, database health and the HTTP installer.
3. Demo pages, news and catalogue, uploaded Media, and optional module schemas.
4. External ZIP module/theme installation through authenticated HTTP endpoints,
   package validation, permissions, public routes, assets and saved theme settings.
5. A real site backup, SQL/file mutation and recovery through the admin restore
   worker. It also checks the backup's hashes, version mismatch rejection and
   archive deletion. The downloaded test ZIP is retained outside Docker volumes.
6. Actual app/web container recreation with the current image and automatic
   startup migrations, followed by another explicit idempotent updater run.
7. Uploading the saved ZIP to a second independently installed stack whose
   database has no content-module tables, then restoring it through the same
   admin workflow.
8. Public pages, uploaded image bytes, external module output, theme assets and
   administrator login on the restored site, including its next container restart.

Comparison covers complete rows in content/catalogue/media and the external
module's table, all setting identities/values, module IDs/versions/enabled state,
and SHA-256 hashes of files in both shared site volumes. Column definitions and
the recorded migration classes/versions are also compared. Registry/settings
update timestamps and lock files are excluded from comparison. Authentication
activity is verified by signing in, not by comparing login/session timestamps.

## Result and cleanup

Progress and the evidence directory are printed to the terminal. Exit code `0`
means all steps and cleanup passed; any failure returns nonzero. To choose the
evidence directory, supply a **new** path:

```sh
python3 .github/scripts/verify-deployment.py --report-dir /tmp/mcms-verification-run
```

The private directory contains `report.json`, `commands.log`, compared snapshots
and `site-backup.zip`. The report records the checked Git commit, local changes,
Core version, step results and cleanup status. Database credentials are not
written to command output or the JSON report. Temporary environment/cookie files
are removed after successful cleanup.

Both temporary stacks, their volumes and this invocation's image tags are removed
on completion or a handled failure. Other projects, volumes and image tags are
not removed. If Docker cleanup fails, the command reports failure and retains its
private environment file for recovery. A forced process kill or host shutdown may
also leave temporary resources; their exact project names are in `report.json`.

This proves a **local** rebuild/recreation and recovery using the current Core
version. It does not deploy the production server, test a migration from an older
release, configure scheduled/off-host backups, or validate real SMTP delivery,
reverse proxies, DNS/TLS, visual layout or screen-reader behavior. Existing focused
HTTP/browser checks remain useful for those concerns.
