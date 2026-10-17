# Troubleshooting and security

[Contents](README.md) · [Русский](../ru/troubleshooting.md)

| Symptom | Checks and next steps |
|---|---|
| HTTP 500 after extracting ZIP | PHP 8.2+ and extensions (intl, mysqli, mbstring, gd, zip), writable `writable/logs`, private DocumentRoot boundary; do not use GitHub's source archive. |
| Friendly URLs return 404 | Apache `mod_rewrite`, `AllowOverride` and `.htaccess`; in Docker inspect reverse proxy configuration. |
| Installer cannot connect to database | MySQL hostname/port, credentials, CREATE/ALTER privileges, MariaDB health. Never wipe an installed site's DB to retry. |
| Portainer: “pull access denied for mcms” | Update `portainer-stack.yml`: `app`, `web`, `updater` are **built** from Git, not pulled from Docker Hub as `mcms:dev`. |
| Redeploy shows old UI | Verify Git branch `main`, Compose path, image rebuild, new container image ID and startup logs. |
| Media images missing | Enable Media, check `modules_data`, nginx read-only volume or Apache/PHP route, file permissions and actual URL. |
| Mail not delivered | SMTP relay hostname/port/auth/TLS, host egress restrictions, SPF/DKIM and mail logs. |
| Module will not install/update | Core constraints, dependencies, trusted ZIP integrity, storage rights and space; disable before updates. |
| Complete removal refused | An installed dependent exists, a built-in or active theme is targeted, unfinished operation or incomplete ownership proof. |
| Persistent 503 while recovering | Don't delete lock/maintenance files. Check disk, MySQL, error logs and off-server backups. Restore to a matching Core version. |

## Logs

Docker: `docker compose ps`, `docker compose logs --tail=200 app web db updater` or **Portainer → Containers → Logs**. Shared hosting: PHP error log from your hosting panel and private `writable/logs`; Apache logs may require provider support. Extension recovery: `/admin/extensions/recovery`. Core and site restoration state: `/admin/updates` and `/admin/backups`.

## Security guidelines

Use HTTPS, strong unique secrets, supported PHP releases, restricted Portainer access, protected MySQL/private directories, and off-server backups. An administrator installing a PHP ZIP grants its code the application's permissions; internal file checksums do **not** make unknown PHP trustworthy.

**Operating limits:** one application instance and shared local file locks are assumed. MariaDB and actively changing uploaded files do not form a single atomic snapshot, even if both are archived successfully. Very cheap shared hosting with restrictive HTTP timeouts or no private DocumentRoot may be unsuitable for production sites with significant data.
