# Contributing to mCMS

Thanks for helping improve mCMS. The code is MIT licensed. Discussions,
bug reports, documentation and tests are all useful contributions.

## Issues and pull requests

1. Search existing issues before filing a report; include the mCMS version,
   installation mode (Docker/Portainer or shared hosting), PHP version, affected
   module/theme, reproduction steps and expected/actual behaviour.
2. Do **not** publish passwords, access tokens, production DB dumps, session
   cookies, personal data or private logs. For vulnerabilities follow
   [SECURITY.md](SECURITY.md) rather than opening a public issue.
3. Use a focused branch and pull request against `main`. Explain the motivation,
   compatibility implications, configuration changes and how you tested.
4. Include regression tests for behavioural changes. CI checks PHP compatibility,
   static/source rules and full-stack clean installation. Never bypass failing
   checks without documenting and fixing the underlying problem.

## Development

- Install PHP and Composer dependencies using `composer install`.
- Run `composer validate --no-check-lock` and `composer test`.
- Use `docker compose up -d --build` to exercise the full installation and
  the browser installer in a disposable environment.
- The PHP package requires its own installation and recovery verification.
- Core/extensions architecture: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md),
  [docs/MODULES.md](docs/MODULES.md), [docs/THEMES.md](docs/THEMES.md).
- Package catalog/ZIP publication rules: [docs/EXTENSIONS.md](docs/EXTENSIONS.md).
  New third-party packages are *trusted executable code*, not isolated plugins;
  package submissions require source, license, dependency and ownership review.
- Follow the bilingual user guides for installation and operations:
  [Русский](docs/guide/ru/README.md), [English](docs/guide/en/README.md).

Avoid unrelated formatting churn, undocumented API compatibility breaks and
hard-coded absolute installation paths. The public DocumentRoot must be the
installed site's public directory, independent of its absolute location.
