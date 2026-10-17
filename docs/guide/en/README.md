# mCMS — Administrator's guide (English)

**Release line:** 0.1.x. Two installation methods use the **same Core, themes and modules**:

- **Docker / Portainer:** PHP-FPM, Nginx, MariaDB, mail and optional update checker services.
- **Standalone / shared hosting:** PHP 8.2+, Apache and MySQL/MariaDB, installed from a self-contained ZIP. No SSH, Composer, Node or Docker is required for the site owner.

## Contents

1. [Install with Docker, Portainer or shared hosting](installation.md).
2. [Standalone: Apache, Nginx, Caddy and LiteSpeed](webservers.md).
3. [Portainer release-image installation and automatic Core updates](portainer-auto-updates.md).
4. [Operate the site; update Core, modules and themes](operations.md).
5. [Create and restore backups; recover and roll back](recovery.md).
6. [Troubleshooting, security and limitations](troubleshooting.md).

Russian: [Русский](../ru/README.md). Developer references: [Architecture](../../ARCHITECTURE.md), [module API](../../MODULES.md), [theme API](../../THEMES.md), [package lifecycle](../../EXTENSIONS.md).

**Important:** A site backup ZIP is not a full server backup. Preserve a compatible application release and infrastructure configuration separately. A third-party ZIP may include executable PHP: install only code you trust.
