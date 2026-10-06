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

- login, registration, recovery and authorization;
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
of Core. Media, Pages, Menus, News, Galleries, Feedback, SEO, ProductImages, Catalogue and Stock are provided as reusable first-party modules.

A module owns its schema, settings, permissions, routes and admin contributions.
Disabling a module preserves its data.

See [docs/MODULES.md](docs/MODULES.md) and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Development status

The 2026-10-06 baseline is recorded in [docs/STATUS.md](docs/STATUS.md).
Media, Pages, Menus, News, Galleries, Feedback, SEO, ProductImages, Catalogue and Stock are implemented as reusable modules and covered by
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

Stock is the next completed commerce layer: variant-level on-hand/reserved/available quantities, reservations and quick manager correction from the stock screen, Catalogue and storefront. The next stages are Cart, Orders, Delivery and export/report adapters.

See [commerce contracts](docs/COMMERCE.md), [Catalogue](modules/Catalogue/README.md)
and [ProductImages](modules/ProductImages/README.md), plus [Stock](modules/Stock/README.md). Back up the dedicated
`product_images_data` volume together with the database.

For your own test instance, optional one-time demo content can be enabled with
`MCMS_DEMO_SEED=1` before installation or redeployment. The default is `0`; an
existing database completion marker prevents repeated seeding. Real content is
preserved. See [demo setup](docs/PORTAINER.md#optional-demo-content-for-your-own-test-stack).

Ferma is used only as a functional reference for features that proved useful in a
real application. mCMS does not inherit Ferma's Git history, database schema,
customer data, routes or deployment assumptions.
