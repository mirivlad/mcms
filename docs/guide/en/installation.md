# Installing mCMS

[Contents](README.md) · [Русский](../ru/installation.md)

## Option A: Docker Compose and Portainer

**You need:** Docker + Compose or Portainer with a Docker Standalone endpoint, a domain and HTTPS reverse proxy. The stack bundles MariaDB. Repository: [mirivlad/mcms](https://github.com/mirivlad/mcms). Default bind address/port: 127.0.0.1:8188.

### Portainer Git Stack

**For automatic Core updates:** follow [the release-image Stack and Portainer API setup guide](portainer-auto-updates.md). The standard `portainer-stack.yml` requires manual Git Re-pull/Redeploy for Core updates.

1. Open **Stacks → Add stack → Repository**, select the mCMS Git URL, branch `main`, and Compose path `portainer-stack.yml`. Add Git credentials in Portainer if the repository is private.
2. Set independent, strong `MCMS_DB_PASSWORD` and `MCMS_DB_ROOT_PASSWORD` in Stack environment. Optional settings: `MCMS_DB_NAME`/`MCMS_DB_USER` (default `mcms`), `MCMS_HTTP_PORT` (8188), `MCMS_BIND_ADDRESS` (127.0.0.1), `MCMS_MAIL_FROM`, `MCMS_MAIL_DOMAIN`.
3. Deploy and **build** the Git sources. Forward incoming HTTPS traffic from your host Nginx/Caddy reverse proxy to `http://127.0.0.1:8188`. Preserve `Host`, `X-Forwarded-For`, `X-Forwarded-Proto`; configure `MCMS_TRUSTED_PROXIES` to trust only your proxy. Do not expose 8188 directly by default.
4. Open your public HTTPS domain; a new site redirects to `/install`. Set site name and the first superadmin's email, login and password. Public registration is optional.
5. Verify login, `/admin`, Pages/Media and extensions. Enable the built-in modules required by your site.

The `app` entrypoint automatically runs `php spark mcms:update`: Core, Settings, Shield and enabled-module migrations only. It **does not drop** the site database. Do not use `MCMS_DEMO_SEED=1` on a real production site.

### Plain Docker Compose

```bash
git clone https://github.com/mirivlad/mcms.git
cd mcms
cp .env.example .env
# Edit .env: use different strong MCMS_DB_PASSWORD and MCMS_DB_ROOT_PASSWORD
docker compose up -d --build
docker compose ps
docker compose logs --tail=100 app web db
```

Use the same reverse proxy and `/install` wizard as above. `portainer-release-stack.yml` is a separate advanced mode for GHCR release images: **never create a second stack sharing production data volumes**.

### Persistent data

| Named volume | Purpose |
|---|---|
| `db_data` | MariaDB |
| `modules_data` | Installed module PHP, user uploads, Media files |
| `themes_data` | External themes and their files |
| `backup_data` | Completed ZIP archives, restore state |
| `mail_queue`, `dkim_data` | Postfix queue and DKIM keys |

Actual names may carry your Compose project prefix. Keep the same stack/project name when redeploying. **Never run `docker compose down -v` during an upgrade or recovery.**

## Option B: Standalone / shared hosting

**Requirements:** PHP 8.2+ with mysqli, intl, mbstring, gd, curl, zip, fileinfo and CodeIgniter requirements; Apache 2.4 with `mod_rewrite` and `.htaccess`; a MySQL/MariaDB database with CREATE/ALTER privileges; PHP write access to private `writable`; ability to place source files **outside the site's DocumentRoot**. PHP execution/upload limits must be sufficient for your actual site. Low-cost hosts may impose limits that prevent backups/restores of larger sites.

1. Download **`mcms-X.Y.Z-shared-hosting.zip`** from an official [GitHub Release](https://github.com/mirivlad/mcms/releases). Do **not** upload GitHub's auto-generated “Source code ZIP” — that lacks Composer dependencies and built assets.
2. Create a new, **empty** MySQL/MariaDB database and database user in your hosting control panel. Enable HTTPS; select PHP 8.3/8.4 where available.
3. Extract the official ZIP **above** the webroot. Only its `public_html/` folder may be exposed as the site's DocumentRoot:

   ```text
   /home/account/site/
       .env                  # private DB and SMTP credentials
       app/ src/ vendor/ modules/ themes/ catalog/
       shared-core-rescue.php
       writable/             # private uploads, ZIP backups, logs
       public_html/          # the ONLY web-accessible directory
           index.php .htaccess assets/
   ```
4. Open the domain over **HTTPS**. The `/install` wizard checks extensions, filesystem safety and write access. Enter database hostname, port (usually 3306), database name, username and password. SMTP settings are optional.
5. Confirm; Core creates tables, then asks for your first administrator. Finish and log in. No `spark`, Composer, server shell or SQL import is necessary.

**Never** put `app`, `vendor`, `src`, `writable`, backups or `.env` under an accessible `public_html`. If your provider cannot isolate the DocumentRoot, change hosting plan/provider. Do not bypass the preflight. Protect `.env` with private file permissions.

### First operational checks

Sign in to `/admin`; confirm password/security, the active theme, required modules, outgoing SMTP, and backup creation. **Download the first ZIP off-server** before uploading valuable content.
