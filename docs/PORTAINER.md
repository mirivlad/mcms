# Portainer deployment

This stack is intended for a Docker Standalone environment managed by Portainer.

For the first Media-module acceptance test, deploy directly from the Git repository
so Portainer has the repository as the Docker build context.

## 1. Create the stack

In Portainer:

1. Open **Stacks** → **Add stack**.
2. Choose **Repository**.
3. Use the mCMS Git repository.
4. For the current Media test, select branch `feature/media`.
5. Set **Compose path** to `portainer-stack.yml`.
6. If the repository is private, configure the Git credentials/token in Portainer.

Set at least these environment variables:

```text
MCMS_BASE_URL=https://cms.example.com/
MCMS_COOKIE_SECURE=1
MCMS_HTTP_PORT=8188

MCMS_DB_NAME=mcms
MCMS_DB_USER=mcms
MCMS_DB_PASSWORD=<strong random password>
MCMS_DB_ROOT_PASSWORD=<different strong random password>

MCMS_MAIL_DOMAIN=example.com
MCMS_MAIL_HOSTNAME=mail.example.com
MCMS_MAIL_FROM=noreply@example.com
MCMS_MAIL_FROM_NAME=mCMS
```

The default bind address is `127.0.0.1`, so the mCMS nginx container is not
directly exposed to the Internet. A host reverse proxy should forward to
`127.0.0.1:8188`.

The persistent volumes are:

- `db_data` — MariaDB;
- `media_data` — public Media uploads shared by the PHP and nginx containers;
- `mail_queue`;
- `dkim_data`.

Redeploying or rebuilding the stack does not remove these volumes.

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

The value of `MCMS_BASE_URL` must match the public HTTPS URL, including the
trailing slash.

When the public endpoint is HTTPS, set `MCMS_COOKIE_SECURE=1`.

## 3. Browser installation

Open the public URL. A fresh database redirects to `/install`.

Create the first superadmin and finish the installer. After setup, `/install`
closes automatically.

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
