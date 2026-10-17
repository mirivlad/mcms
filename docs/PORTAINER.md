# Portainer deployment

This stack is intended for a Docker Standalone environment managed by Portainer.

Deploy directly from the Git repository so Portainer has the repository as the
Docker build context.

## 1. Create the stack

In Portainer:

1. Open **Stacks** → **Add stack**.
2. Choose **Repository**.
3. Use the mCMS Git repository.
4. Select branch `main` for the current stable development baseline.
5. Set **Compose path** to `portainer-stack.yml`.
6. If the repository is private, configure the Git credentials/token in Portainer.

Set at least the database passwords:

```text
MCMS_DB_PASSWORD=<strong random password>
MCMS_DB_ROOT_PASSWORD=<different strong random password>
```

The public base URL and secure-cookie mode are auto-detected from the incoming
`Host` and `X-Forwarded-Proto` headers. mCMS trusts private Docker/LAN proxy
ranges by default through `MCMS_TRUSTED_PROXIES`; override that value when the
deployment uses a different proxy network. `MCMS_BASE_URL` and
`MCMS_COOKIE_SECURE` remain optional overrides for unusual proxy setups.

Other settings such as `MCMS_HTTP_PORT`, database name/user and mail identity
have safe defaults and can be overridden when needed.

The default bind address is `127.0.0.1`, so the mCMS nginx container is not
directly exposed to the Internet. A host reverse proxy should forward to
`127.0.0.1:8188`.

The persistent volumes are:

- `db_data` — MariaDB;
- `modules_data` — installed third-party module code, each module's own
  uploaded/generated files, and Core persistent storage. Media URLs are served by
  nginx from a **read-only mount of the same volume**;
- `themes_data` — installed third-party theme code and per-theme persistent data;
- `backup_data` — downloaded backups created from the admin panel or CLI (not inside archived volumes);
- `mail_queue` — Postfix queue;
- `dkim_data` — mail signing keys.

No extension is allowed to require an additional Compose volume. Both file
volumes are created by Docker on a fresh install; enabling or installing modules
creates subdirectories automatically. Read [Storage](STORAGE.md) for the contract.

**Development reset:** this change intentionally does not migrate the former
`media_data`, `product_images_data`, `module_packages_data`, or
`theme_packages_data` volumes. For a clean development installation, recreate
the test stack/database using the new Compose configuration. Old volumes are not
deleted automatically. Never run `docker compose down -v` against unrelated or
production stacks.

For backups/restores, preserve `db_data`, `modules_data`, `themes_data`
and the Postfix volumes if mail queue/DKIM continuity is required. A snapshot of
only the database does not include uploaded files or installed ZIP packages.

## 2. Reverse proxy

A minimal host-nginx location is:

```nginx
server {
    listen 443 ssl http2;
    server_name cms.example.com;

    # Your existing certificate configuration goes here.

    client_max_body_size 64m;

    location / {
        proxy_pass http://127.0.0.1:8188;
        proxy_http_version 1.1;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

With the recommended proxy headers, mCMS automatically detects the public
hostname and HTTPS scheme. No public-domain variable is required.

## 3. Browser installation

Open the public URL. A fresh database redirects to `/install`.

Create the first superadmin and finish the installer. After setup, `/install`
closes automatically.

### Optional demo content for your own test stack

Demo seeding is **off by default**. To populate only your test instance, set
`MCMS_DEMO_SEED=1` in that stack's Portainer environment variables and redeploy.
Do not change the default in the repository. An installed instance is populated
at container startup; a fresh instance is populated after creating its first owner.

The bundle adds 5 pages, 6 news articles (one draft), 12 products (one draft),
6 nested product categories with colored Tabler icons, and 2 redirects. Products
include single and multiple variants, with demo RUB prices and empty photo sections.
All demo addresses start with `demo-`, product SKUs with `MCMS-DEMO-`; the text is
marked as demo content. Pages, News, Catalogue, SEO and their dependencies are
enabled explicitly for this bundle. Existing content, matching addresses, site
identity, theme and menu assignments are preserved.

Completion is saved in the database as `Core.demoSeedCompletedAt`. Once complete,
further redeploys or explicit command runs do nothing, even with the flag still
set. Edited or deleted demo entries are not restored. Content and completion are
committed together; a failed content transaction can be retried after resolving
its error. The guard belongs to that database and survives rebuilding containers.

After the first successful run, remove the variable or set it back to `0`.
For a manual run in an opted-in container, use `php spark mcms:demo:seed`.
The command also requires the exact value `MCMS_DEMO_SEED=1` and has no force/reset
option. This mechanism never clears or replaces the site's content.

Try `/demo-about`, `/news`, `/shop`, `/shop/category/demo-home`,
`/demo-about-old.html` → `/demo-about` and
`/demo-delivery-old.html` → `/demo-delivery`.

## 4. Install Media

Open **Admin → Modules**.

Media should be visible as:

```text
Media  0.1.0  Not installed
```

Click **Enable**.

Only at this point should mCMS:

- create the `media_files` table;
- create Media settings;
- register Media permissions;
- mark the module enabled;
- add **Media** to the admin navigation.

Open **Admin → Media** and upload an image or PDF.

The file is stored in the persistent volume under:

```text
/assets/uploads/media/YYYY/MM/<random-name>.<ext>
```

Uploaded files are served statically by nginx, while PHP/PHTML/PHAR execution
under the uploads tree is explicitly denied.

## 5. What to verify

For this first real-module acceptance test, check:

- clean browser installation succeeds;
- login survives redirects through the HTTPS reverse proxy;
- Media starts as `Not installed`;
- enabling Media does not require a container restart;
- `/admin/media` appears immediately after enable;
- image preview and direct URL work;
- metadata can be edited;
- Media settings are saved;
- disabling Media removes its routes/menu but preserves table/settings/files;
- re-enabling Media brings the library back with the uploaded files intact.

Site ZIP backup creation, download and restoration are available in **Administration → Backups**.
The archives remain in the `backup_data` volume through container recreation.
See [Backup management](BACKUPS.md) for cron, off-host copies, and restore.


## Optional unattended update manager

The standard portainer-stack.yml now includes an unprivileged updater
container built from the same PHP source as app. It checks eligible
catalog-managed external packages (only when enabled in Administration → Updates)
without Docker socket, browser visits, or host cron. It uses the existing
persistent volumes. MCMS_UPDATE_INTERVAL_SECONDS defaults to 21600 (6 hours).

For **Core image** auto-updates, migrate the existing Portainer Git Stack
carefully to portainer-release-stack.yml, preserving its stack name,
environment and volume names. Do not create a second stack on the same database
volumes or run down -v. Follow [the release/update contract](UPDATES.md).
The Core mode requires GHCR image releases, Portainer HTTPS API settings,
explicit stable channel and a digest-pinned MariaDB image. Without those inputs
it cannot redeploy the application. All update switches default to disabled.

### Git Stack Re-pull / Redeploy

The Git-based `portainer-stack.yml` deliberately contains **no `image:`**
for `app`, `web` or `updater`: all three build locally from the fetched
repository. Portainer may run `compose pull --policy always` before its
Compose build stage; attaching a non-published dev image (such as `mcms:dev`)
would make this pull fail. The application and updater share the same Dockerfile
but use separately named local build results. A Git redeploy should fetch
`main` and rebuild all three services. Only MariaDB and Postfix are pulled
from external registries by the Git Stack.
