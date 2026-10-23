# mCMS 0.1.11 — PHP2Shell security hotfix

**Security patch** based on the published v0.1.10 source. No unrelated `main` development changes are included.

## Security
- Update the official Docker application and updater runtime to PHP **8.4.27**, fixing **CVE-2026-109618** and **CVE-2026-109619** (PHP2Shell).
- Make the Docker build fail when PHP is older than 8.4.27.
- The issue affects the PHP interpreter, not mCMS application logic. Standalone/shared-hosting installs also need their PHP runtime updated by the hosting provider or server administrator.

## Compatibility and upgrade
- No schema changes, new required settings or interface changes compared to v0.1.10.
- Docker/Portainer: install `ghcr.io/mirivlad/mcms-app:v0.1.11` and `ghcr.io/mirivlad/mcms-web:v0.1.11`, or re-pull `:stable` after the image-ready release asset is published. Preserve all existing data volumes; keep a backup before upgrading.
- Updater worker uses the same corrected application image.
- Shared hosting: the verified `mcms-0.1.11-shared-hosting.zip` package updates the mCMS application, **not** system PHP. Check the interpreter is patched (PHP 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12 or later).
- Verify `php -r 'echo PHP_VERSION, "\\n";'` inside the running app container and check the application after redeployment.

See the PHP security advisory: https://www.php.net/archive/2026.php#2026-10-21-5
