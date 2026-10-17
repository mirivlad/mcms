# Shared-hosting distribution (work in progress)

mCMS will support conventional Apache/PHP/MySQL hosting **without** Docker,
Portainer, SSH, Composer or a PHP CLI for the **site owner**. Docker remains
supported separately. There is one Core and extension API, not a reduced
edition. This is a staged implementation: see [roadmap](ROADMAP.md).

## Required hosting conditions

- PHP 8.2+ with mysqli, intl, mbstring, gd, curl, zip, fileinfo, XML,
  and other CodeIgniter/Composer platform requirements.
- A MySQL or MariaDB database and a user allowed to create/alter tables.
- Apache with `mod_rewrite`, `.htaccess` support and writable PHP storage.
- Account access to the directory **above** `public_html`. The distribution
  must be extracted there, not entirely inside a web-accessible directory.
- Sufficient PHP memory, upload size, execution limits and disk quota for the
  site, imports and extensions. Some low-cost hosts won't meet these limits.

## Distribution layout

```text
hosting-account/
  app/ src/ modules/ themes/ catalog/ vendor/ writable/
  .env                    # host-only credentials, not web accessible
  public_html/            # Apache DocumentRoot (the sole public directory)
    .htaccess
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
   rewrite behavior and access to writable storage.
4. Enter the MySQL host, port, database, username and password. The installer
   saves these **outside** the webroot, applies scoped Core/Shield/Settings
   migrations and resumes the existing admin account wizard.
5. Finish initial site setup at `/install` and visit the administration area.

Web-side database setup is closed after configuration and must never allow
replacing credentials on a live or already-installed site. Failure recovery
must not expose passwords in messages or logs.

## Not yet guaranteed

The current Docker flow uses `mariadb-dump` and an external PHP CLI worker
for some backup/restore and module-update operations. Shared hosting must have
tested PHP-only alternatives before it is treated as production-supported.
The current Docker core auto-updater uses Portainer API and is not valid for
shared hosting. No unattended updater should be run from a public request.
Shared hosting SMTP must use the provider's settings rather than the Docker
Postfix hostname `mail`.
