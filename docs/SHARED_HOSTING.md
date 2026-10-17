# Shared-hosting distribution — mCMS 0.1.10

mCMS supports conventional Apache/PHP/MySQL hosting **without** Docker,
Portainer, SSH, Composer or a PHP CLI for the **site owner**. Docker remains
supported separately. There is one Core and extension API, not a reduced
edition. For operational procedures consult [English](guide/en/README.md) or [Русский](guide/ru/README.md). Hosting limits are listed below.

## Required hosting conditions

- PHP 8.2+ with mysqli, intl, mbstring, gd, curl, zip, fileinfo, XML,
  and other CodeIgniter/Composer platform requirements.
- A MySQL or MariaDB database and a user allowed to create/alter tables.
- A web server capable of routing application URLs to `public_html/index.php`: Apache (with rewrite or fallback), Nginx + PHP-FPM, Caddy + PHP-FPM, or LiteSpeed/LSAPI. See [English](guide/en/webservers.md) and [Русский](guide/ru/webservers.md) for tested routing patterns and Media URL handling.
- Account access to the directory **above** `public_html`. The distribution
  must be extracted there, not entirely inside a web-accessible directory.
- Sufficient PHP memory, upload size, execution limits and disk quota for the
  site, imports and extensions. Some low-cost hosts won't meet these limits.

## Packaging

The maintainer builds a complete release on CI with
`python3 scripts/build-shared-package.py --output dist`; the builder uses
Composer and Node **on the build machine**, not on shared hosting. The ZIP
includes the exact Composer lockfile libraries, Tabler, Bootstrap and built-in
theme assets. `python3 scripts/check-shared-package.py dist/*.zip` validates
required files, empty writable directories and web-root isolation.
The `shared-hosting-release.yml` workflow uploads this ZIP on a matching
GitHub Release tag (and supports a manual artifact-only run). Preview ZIPs
from an unmerged development branch are **not production releases**.

The ZIP includes both administrator manuals in the private `manual/` directory.
Normal shared-hosting sites can use PHP-only backups, restoration and manual
extension updates. Host storage and execution quotas still limit how large a
site can be maintained safely; schedule an independent off-host backup and
verify full recovery before relying on automated safety mechanisms.

## Distribution layout

```text
hosting-account/
  app/ src/ modules/ themes/ catalog/ vendor/ writable/
  .env                    # host-only credentials, not web accessible
  public_html/            # DocumentRoot for any supported web server
    .htaccess           # only relevant to Apache/LiteSpeed configurations
    index.php
    assets/
```

The PHP entry point continues to use `../app/Config/Paths.php`. Never put
`app`, `vendor`, `writable`, the database password, backups or ZIP modules
inside `public_html`. If the provider cannot expose only `public_html`,
the installation must stop. Protect the site with TLS before entering passwords.

## Installation workflow (target)

1. Create an empty database in the hosting control panel.
2. Extract the **shared-hosting release ZIP** into the account home, merging
   its `public_html` subtree with the hosting document root.
3. Open the site and let preflight verify PHP/extensions, filesystem layout,
   front-controller routing and access to writable storage.
4. Enter the MySQL host, port, database, username and password. The installer
   saves these **outside** the webroot, applies scoped Core/Shield/Settings
   migrations and resumes the existing admin account wizard. Database
   credentials are stored in `.env`; the password is base64-encoded for
   reliable configuration parsing, **not encrypted**. File protection
   depends on filesystem permissions and the private directory boundary.
5. Finish initial site setup at `/install` and visit the administration area.

Web-side database setup is closed after configuration and must never allow
replacing credentials on a live or already-installed site. Failure recovery
must not expose passwords in messages or logs.

## Limits and differences from Docker

Docker still uses `mariadb-dump` and an independent CLI restoration worker.
Shared hosting uses a **different logical export format** processed by PHP and
synchronous worker requests; a Docker backup cannot be imported into the
restricted shared-host restore wizard without conversion. The Core ZIP upgrader
supports newer **patch releases only** within the same minor series, with
unchanged bootstrap entry files, and creates a safety backup before swapping
code. It cannot guarantee rollback after total disk failure, MySQL downtime or
prolonged provider-enforced PHP timeouts.

Core unattended updates in Docker use Portainer API; **this is not used** on
shared hosting. In shared hosting, manually upload trusted official ZIP files.
Package hashes are not a publisher signature. Host SMTP credentials can be
entered in the installation wizard; no Docker Postfix service is required.
Cron, where available, can run supported CLI commands; public page requests
never launch unattended upgrades.

Only a single web-instance with local locks is supported, not multiple FPM
instances on different machines with separate storage. Upgrade and backup large
sites through a managed VPS if your PHP host limits are insufficient.
