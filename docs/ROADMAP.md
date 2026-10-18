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



## Stage 7 — mCMS 0.2.0: reliable day-to-day operations

**Goal:** make routine site administration and recovery safe before expanding the feature set.
This is a planned minor release, not a claim that any milestone has shipped.

- [ ] Public account recovery: single-use expiring email reset token, non-enumerating responses,
      abuse throttling, safe email-change verification, tests for compromised/expired links.
- [ ] Verify optional admin 2FA recovery and setup UX without weakening Shield authentication.
- [ ] Users: full administration lifecycle (work in progress).
  - [x] Bounded paginated directory and search by username/email, with per-column
        live type-as-you-search filtering, group filters, and 10/25/50/100 page sizes.
  - [x] Show registration and last activity, edit profile/email, manual password
        replacement, random-password reset, account soft deletion and bulk
        activation/deactivation/deletion (protected/self accounts excluded).
  - [ ] Complete security review and acceptance: revoked sessions on password reset,
        on-demand email recovery, and manual browser testing across hosting modes.
- [ ] Recycle bin for Pages and News: restore content and revisions, protect links/slug
      conflicts, explicit irreversible purge, retention policy and migration tests.
- [ ] Core audit log for important security/admin changes (actors, object, operation,
      time and metadata without secrets); permissions, retention and CSV export.
- [ ] Central job runner contract for maintenance and email retries with CLI cron entry
      and a safe web-host manual fallback. No hidden public-traffic scheduler.
- [ ] Expand diagnostics for mail transport, outstanding notification queue,
      backup age/restore readiness, storage permissions and update recovery status.
- [ ] Release gates: migrations/rollback rules, RU/EN UI and docs, integration tests,
      permission checks, clean-install and restore smoke, compatible patch upgrade.

## Stage 8 — mCMS 0.3.0: reusable Forms and Blocks

**Goal:** let site owners assemble common sections without writing a PHP extension.

- [ ] Forms: field schema (text, email, number, choice, date, file where safe),
      server validation, CAPTCHA, throttles, submissions, privacy and CSV exports.
- [ ] Embed Forms in Pages/News and render in all bundled themes; existing Feedback
      remains compatible, with an optional migration/adaptation path.
- [ ] Blocks: theme-declared placement regions, module-provided block types,
      configurable instances, visibility conditions, order and caching contract.
- [ ] Provide blocks for text, menu, gallery, recent articles and a Form; graceful
      behavior when supplying modules are disabled.
- [ ] Guard admin editing and public rendering by permissions and published state;
      prevent unsafe arbitrary PHP templates supplied by content authors.
- [ ] Acceptance: independent modules, extensible manifests/services, accessible
      mobile UI, clean install, third-party theme compatibility and integration tests.

## Stage 9 — mCMS 0.4.0: editorial content and discovery

**Goal:** improve publication control and content navigation.

- [ ] Draft edits to already published Pages/News without affecting the live content;
      explicit review, approval and scheduled publication/unpublication options.
- [ ] Hierarchical Pages with route collision and redirect protection, parent reordering,
      breadcrumbs and existing flat-URL compatibility/migration.
- [ ] SEO enhancements: canonical, Open Graph/social preview images, robots.txt,
      schema.org/JSON-LD, hreflang-ready metadata contract and large sitemap indexes.
- [ ] Public search: cross-provider ranking with normalized scoring/pagination,
      content-type filters, scalable full-text strategy and publication safeguards.
- [ ] Media enhancements: folders/collections, bulk metadata and upload workflows,
      responsive image derivatives and preservation of originals.
- [ ] Acceptance: correct preview/live separation, URL stability, SEO test fixtures,
      accessibility and backward compatibility for existing modules/themes.

## Stage 10 — mCMS 0.5.0: integrations and commerce growth

**Goal:** connect shops to payment, delivery and business systems without coupling Core.

- [ ] Webhooks: subscribed outgoing events, signed payloads, delivery outbox,
      bounded retry/diagnostic history, replay protection and credential rotation.
- [ ] Customer Notifications: order receipt and status emails with verified addresses,
      templates and clear consent/opt-out rules for non-transactional messages.
- [ ] Payments: pluggable adapters, manual payment method, payment ledger/status
      distinct from order state, partial payments, refunds, verified callbacks.
- [ ] Discounts: optional promotions/coupons with immutable checkout snapshots,
      conflict policies and testable calculation boundaries.
- [ ] Customer delivery addresses, delivery zones/price rules and tracking history;
      optional refund/returns workflow with stock/accounting consistency.
- [ ] Optional CSV/XLSX catalogue import with dry-run and stable SKU mapping;
      1C integration adapter using the existing versioned API (no Core 1C dependency).
- [ ] Acceptance: payment/webhook idempotency, no trust in browser amounts, precise
      arithmetic, secrets handling, rollback/replay tests and optional-module isolation.

## Stage 11 — mCMS 0.6.0: multilingual content and structured fields

**Goal:** provide future-proof content modeling without expanding the Core into a monolith.

- [ ] Multilingual content contract: stable resource identity, localized variants,
      per-locale publication, translated slugs, URL/redirect policy and language switcher.
- [ ] Locale-aware SEO: canonical/hreflang/sitemap, safe fallback and separate
      localization of navigation, Pages, News, categories and products.
- [ ] Custom Fields: typed field definitions and validation for existing resource
      providers, media/reference fields, access control, API contracts and search.
- [ ] Theme API extensions for translated resources and typed fields; migration path
      for existing content and compatibility for extensions that ignore these features.
- [ ] Acceptance: independent install/disable semantics, stable URLs, content migration,
      storage ownership, safe rendering and RU/EN guides.

### Versioning and release rules for Stages 7–11

Each numbered stage targets **one minor release**: `0.2.0`, `0.3.0`, `0.4.0`,
`0.5.0`, `0.6.0`, in that order. These are targets, not published releases.
Development takes place on feature branches with CI and reviews; only tag a version
after its release gates pass. Fixes during development may be merged incrementally,
without advertising an unfinished stage as released. Patch releases retain the
usual `0.x.y` convention. Existing 0.1.x sites must have a documented upgrade and
recovery path before each minor release.


## Feature design policy

Define features from mCMS requirements and implement them against its public Core and module contracts. Keep application-specific data models, routes and deployment assumptions out of reusable modules unless the mCMS design explicitly requires them.
