# mCMS

mCMS is a modular, self-hosted CMS platform built on CodeIgniter 4.

The Core is intentionally small: framework runtime, authentication/authorization,
administration, settings, module lifecycle, hooks, storage contracts and system
maintenance. Content and business features are installed as modules.

## Core stack

- PHP 8.4 target;
- CodeIgniter 4.7.4;
- CodeIgniter Shield 1.4.x;
- CodeIgniter Settings 2.4.x;
- MariaDB 11.4;
- nginx + PHP-FPM;
- Bootstrap 5.3.8 for the default public UI;
- Tabler 1.6.1 for the administration shell;
- Docker Compose / Portainer deployment.

## Core responsibilities

- public login, CAPTCHA-protected registration, personal account and authorization
  (see docs/ACCOUNTS.md; email-based password recovery remains planned);
- users, groups and permissions;
- admin dashboard and extension registry;
- module discovery, dependencies, enable/disable/update lifecycle;
- namespaced module migrations;
- persisted Core/module settings;
- stable events/hooks contract;
- storage abstraction;
- browser first-run installation and CLI recovery bootstrap;
- health and diagnostics.

## Modules

News, Galleries, Shop, Orders, Delivery and other domain features are not part
of Core. Media, Pages, Menus, News, Galleries, Feedback, SEO, ProductImages, Catalogue, Stock, Cart, Orders and Delivery are provided as reusable first-party modules.

A module owns its schema, settings, permissions, routes and admin contributions.
Disabling a module preserves its data.

See [docs/MODULES.md](docs/MODULES.md) and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Development status

The 2026-10-06 baseline is recorded in [docs/STATUS.md](docs/STATUS.md).
Media, Pages, Menus, News, Galleries, Feedback, SEO, ProductImages, Catalogue, Stock, Cart, Orders and Delivery are implemented as reusable modules and covered by
module/integration checks.

Theme API, four first-party themes and Menus ↔ theme-location assignment are implemented.
mCMS Default, [mCMS Signature](themes/mcms-signature/README.md), [mCMS Depth](themes/mcms-depth/README.md) and [mCMS Focus](themes/mcms-focus/README.md) render Home, Pages, News, Galleries, Feedback, Search and Catalogue through logical templates. Depth and Focus include generated local covers and an editable Home in Administration → Themes → Configure: heading, description, Media cover, button and News/Galleries sections, saved independently per theme. A published Home chosen in Pages settings retains priority; choose the built-in Home there to use the theme layout.

The Gallery button in Pages/News editors searches published albums and inserts a shortcode at the cursor. Choose a thumbnail grid, a large banner slider, a horizontal carousel or cards with captions. The same album can use different views on different pages; existing `[gallery id="12"]` shortcodes keep their grid layout. For example: `[gallery id="12" view="slider"]`, `[gallery id="12" view="carousel"]` or `[gallery id="12" view="cards"]`. The album editor also generates a shortcode for the selected view.

Pages and News editors provide private unsaved previews, personal autosave/recovery
and revision history. SEO supports manual metadata and per-content title/description
rules, including keywords from tags. Theme configuration includes per-theme branding,
accent and footer contacts. News supports categories/tag filters; `/search/results`
searches published Pages/News/Catalogue through Core provider contracts.

Catalogue and ProductImages now implement the first commerce stage. Enable
**Catalogue** in Administration → Modules; its separate image module is enabled
as the sole dependency. Products have multiple photos, crop/resize from preserved
originals, main selection, drag-and-drop order, variants and RUB prices. `/shop`
renders the storefront in all four themes. Media and Galleries are independent.
Photo edits save immediately; product/variant fields save with the product form.

Stock provides variant-level on-hand/reserved/available quantities. Cart checks live availability but never reserves stock; Orders creates the reservation only after checkout succeeds. Delivery adds fixed-price delivery and pickup methods whose name/type/price are snapshotted into each order. Orders → Order report provides date/status totals and CSV exports of historical orders and their items, with a separate export permission. See [Orders](modules/Orders/README.md).

Administration → Modules → **Install from ZIP** accepts a module archive,
checks its manifest and dependencies, and shows a preview before adding trusted
code. Enable the added module separately to apply its migrations. Build a package
with `php spark mcms:module:pack modules/Example /tmp/example.zip`; for a repository,
export the required revision first. External modules and their data persist in the common
`modules_data` volume, which must be backed up with the database. Existing
modules cannot be replaced through this installer. See the [package contract](docs/MODULES.md#zip-package-contract).

See [commerce contracts](docs/COMMERCE.md), [Catalogue](modules/Catalogue/README.md)
and [ProductImages](modules/ProductImages/README.md), plus [Stock](modules/Stock/README.md), [Cart](modules/Cart/README.md), [Orders](modules/Orders/README.md) and [Delivery](modules/Delivery/README.md). Back up the shared `modules_data` volume together with the database. No module needs its own volume.

For your own test instance, optional one-time demo content can be enabled with
`MCMS_DEMO_SEED=1` before installation or redeployment. The default is `0`; an
existing database completion marker prevents repeated seeding. Real content is
preserved. See [demo setup](docs/PORTAINER.md#optional-demo-content-for-your-own-test-stack).

Ferma is used only as a functional reference for features that proved useful in a
real application. mCMS does not inherit Ferma's Git history, database schema,
customer data, routes or deployment assumptions.

### Third-party theme example

[Atelier](examples/themes/mcms-atelier/README.md) is a complete independent
editorial theme, available as a [ready-to-install ZIP](examples/packages/mcms-atelier-1.1.1.zip).
Install it under Administration → Themes → Install theme ZIP; it remains
inactive until explicitly activated. See [the package contract](docs/THEMES.md).

## File storage

All extensions use two shared Docker volumes: `modules_data` (module packages, uploaded files, generated data and Core files) and `themes_data` (theme packages and theme data). Module-specific volumes are not supported. See [the storage contract](docs/STORAGE.md).

## Backups

Create, download and restore full-site ZIP archives from **Administration → Backups**, or schedule `php spark mcms:backup:create --keep=7` from cron. The separately mounted `backup_data` volume holds completed ZIPs, while each archive includes a transaction-consistent MariaDB SQL dump and both CMS data volumes. See [docs/BACKUPS.md](docs/BACKUPS.md).
