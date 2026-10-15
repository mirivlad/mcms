# Stage 5 verification — 2026-10-16

Implementation commits:

- `bcb8f23`: journalled external package removal, retained-data reinstallation,
  explicit cleanup ownership and a real migration-backed demo module.
- `e2b020a`: repository catalog, validator, bounded GitHub downloads, cached
  checks, RU/EN administration, existing ZIP workflow integration, additional
  removal/recovery guards, packages, tests and operational documentation.

## Local gates

The final implementation passed **323 PHPUnit tests / 2176 assertions** on PHP
8.4.26. This includes the existing suite plus catalog/lifecycle/permission tests.
The host's global `15-psr.ini` was excluded with a temporary PHP scan directory:

```sh
PHP_INI_SCAN_DIR=/tmp/mcms-stage5-ini php vendor/bin/phpunit
php spark mcms:catalog:validate
node --check public/assets/admin/catalog.js
git diff --check
```

The catalog validator reported **2 valid extensions** (Lifecycle demo 1.0.0 and
Atelier 1.1.1/1.1.2), checking every shipped ZIP, checksum, screenshot and manifest.
All changed PHP files passed syntax checks. Spark route discovery included catalog,
removal, recovery and both update-preview cancellation endpoints.

Integration coverage includes:

- Catalog install, real module upgrade with an additional migration, rejection of
  same-version updates while code is present, and disabled reverse dependencies
  excluded from available update counts/downloads.
- Removal keeping DB rows/settings/scoped files/migration history; same-version
  reinstallation through the ordinary installer; newer retained-data installation;
  rejection of older schemas, changed migrations and namespace reuse by another ID.
- Complete deletion and clean installation; no cleanup without a complete contract;
  observed table ownership, shared-schema changes, incomplete migration receipts,
  foreign references and another module's similarly named rollback history.
- Both removal modes blocked by code-only and disabled registered dependents;
  built-in packages and active themes protected; unfinished ZIP previews blocked.
- Interrupted keep-data and partial full-deletion journals resumed safely, repeat
  cleanup and the journal/maintenance commit gap; existing theme swap recovery.
- Atelier install/update retaining current settings; retained same-version theme
  reinstallation and complete theme cleanup.
- Bad size/SHA-256, corrupt ZIP, manifest disagreement, unsupported catalog schema,
  duplicate IDs/versions, unsafe URLs, missing/incompatible dependencies, offline
  cache preservation and no network requests during ordinary metadata reads.
- Controller permission checks, session-owned theme previews and rendering through
  the existing theme ZIP form, including template/menu counts.

## Browser and runtime checks

The current source was mounted read-only into a disposable PHP 8.4 app, with a
separate MariaDB 11.4 database and writable directories. Standard local Tabler
assets were supplied from the existing web image. Candidate GitHub catalog bodies
were initially provided by a **test-only fetcher outside the repository**, since
these candidate files were not yet published and the repository is private.
Production fetch policy and installation services were unchanged.

Browser checks passed in EN and RU:

- Catalog download → standard ZIP preview → trust confirmation → inactive module
  installation → explicit enablement → keep-data deletion → same-version catalog
  reinstallation, still disabled.
- The same retained module reinstalled through **manual ZIP upload**. Database
  inspection confirmed one note, one migration record and a disabled registry row;
  the scoped `welcome.txt` file survived. Full deletion and clean reinstallation
  were also exercised.
- Full-deletion submission without backup acknowledgement was rejected. Empty ID
  confirmation and unchecked package trust could not submit normal forms.
- Atelier 1.1.1 installed/activated from the catalog, update count 1 displayed,
  catalog update to 1.1.2 applied, active-theme removal blocked, then switching to
  the default theme allowed keep-data removal and same-version reinstallation.
  Complete theme deletion followed by clean catalog installation also passed.
- Search, category/state controls, no-result output, details/changelog, screenshots,
  update badges and mobile navigation worked. At widths **1440 px and 390 px** the
  document width matched the viewport: no horizontal page overflow.
- An independent guest browser context was redirected to login for both catalog
  and deletion routes.

Screenshots saved with the task: `catalog-desktop-ru.png`,
`catalog-mobile-ru.png`, `catalog-private-error.png`. The demo's removal preview
screenshot is also committed under its catalog folder.

## Network evidence and operational limits

`gh repo view` confirmed **PRIVATE** visibility. The production fetcher, without
credentials, returned a readable GitHub **HTTP 404** for the real catalog URL.
The browser was then switched to that real index request: it showed the error,
retained the successful cached catalog/timestamp and still opened manual ZIP
management. Repository visibility was not changed.

A current public GitHub release asset HEAD request returned **302** to
`release-assets.githubusercontent.com`, matching the constrained redirect policy.
No mCMS release was published. A live successful download of an asset from this
private repository was not possible; package installation/update used the actual
shipped ZIP bytes through the local fixture and ordinary Core services.

Without public anonymous access, a fresh installation cannot fetch this catalog
or its packages. Existing cache/manual ZIP management stays available. The
implementation intentionally adds no private-repository tokens or credential UI.

The disposable PHP image predates the current Dockerfile's `mariadb-client`
dependency, so its existing backup button could not spawn a dump client.
Destructive fixture operations used separate MariaDB SQL dumps and scoped-file
archives outside the application volume. The current Dockerfile already installs
that client; the backup implementation was not changed as part of Stage 5.

Supported deployment remains one app instance with local filesystem locks.
External backups require operator verification. Complete cleanup covers only a
reviewed complete contract and observed ownership; arbitrary trusted PHP writes
to shared records/external services are not sandboxed or automatically inferred.
Legacy/unproven resources require keep-data removal or verified external recovery.
Without cron, daily checks happen while an authorized administrator uses the panel.
