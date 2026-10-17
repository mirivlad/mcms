# Standalone mCMS web servers: Apache, Nginx, Caddy and LiteSpeed

[Contents](README.md) · [Русский](../ru/webservers.md) · [Installation](installation.md)

mCMS is built on CodeIgniter 4, but **neither Apache nor mod_rewrite is mandatory**. Any web server is suitable if it routes public requests through the PHP front controller correctly and keeps private application files outside the web root. Replace example paths, hostname and PHP-FPM socket with your hosting configuration.

## Requirements for every server

- **DocumentRoot** must be `/srv/mcms/public_html` (or your equivalent). Keep `app`, `src`, `vendor`, `writable`, `.env`, `manual` and all other private resources **above and outside** this web root.
- Serve physical CSS/JS/fonts directly. Send every other request through `public_html/index.php`, including `/account`, `/admin`, `/install` **and `/assets/uploads/media/...`**. Media files are stored under private `writable/modules-data` and served through a PHP controller; an unconditional static-only `/assets/` handler **breaks Media**.
- Only the intended front controller should execute as PHP. Do not permit arbitrary uploaded PHP. Supply correct `SCRIPT_FILENAME`, `DOCUMENT_ROOT`, `QUERY_STRING` and original request URI. Configure trusted HTTPS proxies inside mCMS; never trust arbitrary external forwarding headers.
- PHP 8.2+ and its required extensions, MySQL/MariaDB, writable private folders and sufficient PHP worker time/memory/disk quotas. Use HTTPS before sending credentials.

Example installation tree:

```text
/srv/mcms/
  .env                 # PRIVATE
  app/ src/ vendor/ modules/ themes/ manual/
  writable/            # PRIVATE: files, Media, backups, logs
  public_html/         # the ONLY DocumentRoot; index.php and assets/
```

## 1. Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name cms.example.com;
    root /srv/mcms/public_html;
    index index.php;
    autoindex off;

    # Example HTTP listener behind a local HTTPS reverse proxy.
    # Configure listen 443 ssl when Nginx terminates public TLS itself.
    location / {
        try_files $uri /index.php?$query_string;
    }
    # Media is outside public_html: it must be routed to the PHP controller.
    location ^~ /assets/uploads/media/ {
        try_files $uri /index.php?$query_string;
    }
    location ^~ /assets/ {
        try_files $uri =404;
        expires 7d;
    }

    # Only the front controller may be executed as PHP.
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ \.php(?:/|$) { return 404; }
    location ~ /\. { return 404; }
}
```

Adjust the socket or TCP FPM pool endpoint (e.g., `127.0.0.1:9000`). `.htaccess` and `mod_rewrite` are not used. Check `nginx -t` before reloading. **Do not remove** the `/assets/uploads/media/` override when using a static-only `/assets/` location.

## 2. Caddy + PHP-FPM

```caddyfile
cms.example.com {
    root * /srv/mcms/public_html
    encode zstd gzip

    # php_fastcgi performs index.php fallback for missing paths.
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

Caddy can manage HTTPS automatically for an internet-facing domain with working DNS and ports. Avoid an extra `handle /assets/*` using only `file_server`: that prevents fallback to the private Media PHP controller. Validate with `caddy validate --config /etc/caddy/Caddyfile` and test a real Media image.

## 3. Apache (mod_php or PHP-FPM)

**`mod_rewrite` is optional.** With mod_rewrite, keep the bundled `public_html/.htaccess` and allow Apache overrides. Without it, use Apache `mod_dir`'s `FallbackResource /index.php` so non-file URLs reach the front controller.

Example VirtualHost **without mod_rewrite**:

```apache
<VirtualHost *:80>
    ServerName cms.example.com
    DocumentRoot /srv/mcms/public_html
    <Directory /srv/mcms/public_html>
        Require all granted
        Options -Indexes
        AllowOverride None
        DirectoryIndex index.php
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

If `AllowOverride None` is used, `.htaccess` has no effect; the VirtualHost must provide all required routing/access restrictions. With Apache and PHP-FPM also configure the corresponding `SetHandler` or proxy handler for PHP according to your provider's environment. Never expose private source directories.

## 4. LiteSpeed / OpenLiteSpeed (LSAPI)

LiteSpeed supports Apache-style rewrite rules, although configuration locations differ between managed hosting, LiteSpeed Enterprise and OpenLiteSpeed. Configure DocumentRoot to `/srv/mcms/public_html`, enable PHP via LSAPI/lsphp and enable **Rewrite**. Either load the provided `.htaccess` or configure the rules in the virtual host.

Example **Rewrite Rules** for OpenLiteSpeed Virtual Host:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L,QSA]
```

Under OpenLiteSpeed's **Virtual Host → Rewrite** set `Enable Rewrite: Yes`. To use the bundled `.htaccess` also select `Auto Load from .htaccess: Yes`. Managed LiteSpeed hosting often exposes these options through its control panel. Avoid stacking duplicate rewrite configurations. Media URLs must reach `index.php`; ordinary CSS/JS assets should be served directly.

## Acceptance checklist

| URL / check | Expected result |
|---|---|
| `/install` on new empty DB | Setup wizard (closed after initialization) |
| `/login`, `/account` | Routed successfully; `/account` requires login |
| `/assets/vendor/bootstrap/css/bootstrap.min.css` | HTTP 200, CSS |
| `/assets/uploads/media/YYYY/MM/<hash>.jpg` | HTTP 200 if uploaded; 404 for a missing image |
| `/app/Config/Database.php`, `/.env`, `/writable/...`, `/vendor/autoload.php` | Private files inaccessible: 403 or 404 |
| `/assets/uploads/media/... .php` | 404; PHP source cannot execute |

A hosting plan unable to isolate the private application tree from DocumentRoot or route requests correctly is **not suitable**. Rewrites are not a substitute for a private directory. For large-site backup and restoration limitations consult [backup and recovery](recovery.md).
