# mCMS roadmap

## Stage 0 — modular Core

- [x] Initialize independent repository with no inherited Git history.
- [x] Move CodeIgniter application layout to repository root.
- [x] Remove customer-specific runtime/deployment configuration.
- [x] Remove dependency on an imported legacy database.
- [x] Adopt CodeIgniter Shield for authentication/authorization.
- [x] Adopt CodeIgniter Settings for persisted configuration.
- [x] Define module manifest/provider lifecycle.
- [x] Add database-backed module registry.
- [x] Ensure module migrations are namespace-scoped and opt-in.
- [x] Add initial Tabler-based admin shell.
- [x] Add browser setup wizard, CLI fallback and first superadmin creation.
- [x] Add Core user/group/permission administration.
- [x] Add Core settings UI.
- [x] Grouped central settings with original module entry points retained and shared form handlers.
- [x] Add diagnostics/system page.
- [x] Add English/Russian localization and site locale selection.
- [x] Add local/runtime packaging for Bootstrap and Tabler assets.
- [x] Enforce mCMS and module dependency version constraints.
- [x] Add module lifecycle and fresh-install integration tests.

## Stage 1 — first reusable modules and public presentation

- [x] Media.
- [x] Pages.
- [x] Menus/navigation.
- [x] Theme API and administration.
- [x] Default theme with declared menu locations.
- [x] Menus ↔ theme-location assignment.
- [x] News.
- [x] Galleries.
- [x] Gallery shortcodes in Pages and News.
- [x] Second first-party theme: mCMS Signature.
- [x] Selected published page as Home.
- [x] Feedback form and administration inbox.
- [x] Optional sitemap and moved-resource redirects.
- [x] Media previews and explicit used-file deletion confirmation.
- [x] Content-list pagination and public-path conflict protection.

## Content workflow and customization — approved follow-up

- [x] SEO title/description templates and manual/tag-derived keywords.
- [x] Private unsaved Pages/News preview in the active theme.
- [x] Autosave/recovery and revision history.
- [x] Per-theme logo, favicon, color and contact settings.
- [x] News categories, tags, filters and public search.

## Stage 2 — commerce modules

- [x] Define the product-image boundary: dedicated images, independent of Media.
- [x] Define first-stage Catalogue product/variant/price/stock/image contracts; see [commerce requirements](COMMERCE.md).
- [x] ProductImages: multiple uploads, crop/scale editor, main-image selection and drag-and-drop ordering.
- [x] Catalogue: products, variants/RUB prices, editor and public galleries in all themes.
- [x] Cart: guest/session cart, live Stock checks without reservation, immutable line snapshots and automatic quantity correction.
- [x] Orders: checkout snapshots, reserve-on-order, manager statuses, cancel release and completion write-off.
- [x] Stock: variant-level on-hand/available quantities, reservations, audited corrections and manager quick controls.
- [x] Delivery: fixed-price delivery and pickup methods, admin ordering/enabled state and immutable order snapshots.
- [x] Export/report adapters: order totals by creation date/status/currency and full orders/items CSV from saved snapshots.

## Stage 3 — ecosystem

- [x] Module ZIP packaging/install, including locally exported repository sources.
- [x] Module compatibility/version constraints at discovery/enablement.
- [x] Safe external module update workflow with code snapshots, maintenance and explicit database-backup recovery.
- [x] Third-party theme ZIP packaging/install, review, separate activation and persistent static resources.
- [x] External theme updates with identity/version checks, preserved settings/data, reader draining, interrupted-swap recovery and code rollback.
- [x] Optional API module: scoped Bearer keys, physical stock exchange with safe retries, saved orders/delivery and current delivery methods; see [API contract](API.md).
- [x] Optional order Notifications: recipient/events/templates, transactional outbox, test email and retry; see [delivery contract](NOTIFICATIONS.md).
- [ ] Further notification/integration modules as concrete requirements arise.

## Stage 4 — public interaction and flexible navigation (approved 2026-10-12)

- [x] Captcha foundation: first-party PNG counting challenge, session/form-bound single-use tokens, server verification, expiry, throttles and Feedback guest integration.
- [x] Captcha: readable nonvisual tasks, keyboard/screen-reader labels, per-form guest/all/off policies, difficulty/expiry, atomic IP/session limits and aggregate abuse metrics.
- [x] Comments v0.1: guests with Captcha, registered users, reply depth, central moderation queue, reports, and module-type enablement for Pages/News/Catalogue.
- [x] Comments: independent per-resource policies, threaded reply UI and optional transactional Notifications with live eligibility checks.
- [ ] Comments follow-up: further moderation refinements.
- [x] Ratings v0.1: independent five-star votes, one mutable vote per user/resource, average and distribution, admin deletion and optional Captcha-guarded guest mode.
- [x] Ratings: independent per-resource inherit/allow/deny policies enforced by public vote endpoints.
- [ ] Ratings follow-up: suspicious-vote analytics, verified purchase badges and stronger guest deduplication.
- [x] Menus 2.0 foundation: dynamic menu sources, page-type visibility conditions and two ordered secondary menus per existing theme location without altering primary assignments.
- [x] Menus 2.0: per-resource visibility through the navigation registry, unlimited ordered extras, keyboard/touch assignment controls and optional limits for arbitrary third-party theme positions.
- [x] Catalogue navigation v0.2.5: full category tree (default), collapsible first-level tree and horizontal dropdown with responsive controls, plus optional shop secondary menu and Theme API integration.

## Stage 5 — GitHub extension catalog and external package lifecycle

- [x] Core-owned keep-data and complete module/theme removal with explicit resource previews, confirmation, backup acknowledgement and dependency/built-in/active-theme protection.
- [x] Retained identity/schema/migration receipts, same-version reinstallation and disabled module restoration.
- [x] Explicit cleanup contract, migration-created table ownership and foreign-reference/shared-schema guards; unsupported cleanup stays blocked.
- [x] Writer locking, durable removal journals, maintenance and interrupted-operation recovery before module boot/startup updates.
- [x] Versioned repository catalog, local/CI validator, immutable ZIP metadata and same-repository GitHub Releases support.
- [x] Bounded trusted HTTPS fetches, atomic offline cache, daily/background/manual checks and cron CLI.
- [x] Tabler RU/EN catalog tabs, search/filters, detail/screenshots/history, compatible updates and admin update counts.
- [x] Catalog downloads routed through existing ZIP previews/install/update/backup/recovery flows; manual uploads preserved.
- [x] Atelier releases and a migration/data-backed Lifecycle demo package.
- [x] Integration and browser coverage for install/update/removal/reinstallation, permissions, confirmation and responsive layouts.

See [extension lifecycle and catalog publication](EXTENSIONS.md) for contracts,
operational recovery, scheduling and supported limitations.

## Stage 6 — ordinary shared-hosting installation (approved 2026-10-17)

**Objective:** same Core/modules/themes on standard Apache + PHP + MySQL hosting,
without Docker, SSH, Composer or a shell for the person installing the CMS.
See [Shared hosting design and deployment](SHARED_HOSTING.md).

- [x] Document host requirements, web-root boundary and staged acceptance criteria.
- [x] Build a complete ZIP containing Composer vendor and built frontend assets,
  with the private code outside `public_html`.
- [x] Browser preflight and database credentials before the existing superadmin wizard;
  run scoped migrations in web mode with retry/lock safety.
- [x] Apache-compatible safe delivery of Media uploads, while keeping PHP and
  internal writable files inaccessible.
- [x] Test from extracted ZIP with PHP runtime and real Apache 2.4: installer,
  paths, media and packaging integrity; disposable MySQL test database.
- [x] Add PHP database export/import without `mariadb-dump`, `mariadb` or
  `proc_open`, with checksum validation, safety ZIP and interrupted recovery.
  Large sites still require adequate hosting quotas and tested recovery.
- [x] Web-host module and theme update paths without CLI process spawning;
  use a recovery journal and preserve previous package snapshots.
- [x] Same-minor Core patch ZIP updates with file hashes, data backup,
  staged code switch and pre-bootstrap recovery (no Portainer API).
- [x] Manual admin operations on shared hosting; CLI cron remains optional
  where supported. Public site traffic never triggers automatic upgrades.
- [x] Shared-host mail delivery configured with provider SMTP credentials.
- [x] Document managed PHP-host limitations, independent backups and differing
  Docker vs PHP database formats, in Russian and English user manuals.

Production operators must still verify that their provider's PHP timeouts,
account filesystem permissions, MySQL capabilities and quotas fit their site's
size. No application can guarantee recovery from inaccessible disks/databases.


## Reference policy

Ferma is a functional reference only.

When mCMS needs a feature already implemented there, first extract the user-facing requirement and behavior, then implement it against mCMS Core contracts. Do not copy legacy schema, customer assumptions or compatibility routes unless they are independently justified for a reusable module.
