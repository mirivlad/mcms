# mCMS 0.1.10 — account redesign and standalone hosting documentation

**Stable patch release** for existing mCMS 0.1.x Docker/Portainer installations and shared-hosting sites. Includes all 0.1.9 features; no new database schema migrations are required for this release.

## Changed

- Redesigned the user account dashboard: separate compact identity header, unified responsive workspace for profile and security, clearer buttons, removal of duplicate logout/navigation elements and invalid nested form markup.
- When the Orders service is enabled, the account dashboard shows the user's latest **three** orders and a direct link to the complete order history. Queries enforce the logged-in user's ID; no Orders tables are accessed if the module is disabled. Account and password forms retain CSRF, validation and existing server endpoints.
- Fixed mobile site header spacing when signed-in account links and the theme navigation compete for width.
- Added theme-independent responsive styling shared across all bundled themes and standalone layouts; account CSS URLs now include the Core version to prevent stale browser caches.
- Documented **Nginx + PHP-FPM, Caddy + PHP-FPM, Apache without mod_rewrite, and LiteSpeed/OpenLiteSpeed** in full Russian and English administrator guides. The web-server requirement is front-controller routing and protected private files, **not Apache/mod_rewrite specifically**. Media URLs must be forwarded to the PHP controller even though CSS/JS is served statically.

## Upgrading & rollback

- Official standalone PHP installer package: `mcms-0.1.10-shared-hosting.zip` (includes Composer dependencies, frontend assets and both manuals). Check `SHA256SUMS` from the same GitHub Release; **do not** use GitHub's source ZIP as a shared-host installer.
- Docker: app/web images published as `ghcr.io/mirivlad/mcms-app:v0.1.10`, `ghcr.io/mirivlad/mcms-web:v0.1.10` and `:stable`. An opt-in Core auto-updater on `portainer-release-stack.yml` may deploy this compatible patch release **only when Portainer HTTPS API credentials, Stack/Endpoint IDs, MariaDB digest pin and `MCMS_IMAGE_TAG=stable` are correctly configured**.
- Back up and download the full site ZIP before upgrading. Portainer auto-updates are not a guarantee of automatic rollback for image/database failures. Keep the previous release image/tag and archive until verification.
- Shared hosting: use the verified patch ZIP updater in `/admin/updates`. The existing webroot and rescue bootstrap files remain unchanged, allowing staged code swap/rollback.

Guides: [Русский](https://github.com/mirivlad/mcms/blob/main/docs/guide/ru/README.md) · [English](https://github.com/mirivlad/mcms/blob/main/docs/guide/en/README.md).
