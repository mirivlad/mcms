# Repository guidance for coding agents

mCMS is a **standalone, modular CodeIgniter 4 CMS**, not a fork of any other
application. Make targeted changes. Respect the architecture and public module
contracts documented in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

- Core owns authentication/authorization, settings, module lifecycle, theme
  and storage contracts. Domain-specific features belong in modules.
- Never use blanket `migrate --all`: module migrations are namespace scoped.
- Do not expose `.env`, `app`, `src`, `vendor`, `writable`, uploaded originals,
  credentials or backup data through the public web root.
- Respect extension ownership: do not directly access other modules' private
  tables/files; use published interfaces. Do not change data/removal contracts
  without regression tests and upgrade/recovery considerations.
- Do not silently broaden requested scope, change application versions, remove
  tests, suppress CI errors or create tagged releases.
- Add tests for bug fixes and compatibility changes. Before handing off, run
  `composer validate --no-check-lock`, `composer test`, lint modified PHP
  files and applicable smoke checks. If not run, state why.
- Update `docs/STATUS.md` only for current facts and `CHANGELOG.md` when
  recording a released version. Preserve historical notes, do not append dated
  checkpoint diaries to the current-status file.
- Do not execute third-party modules/themes as part of untrusted reviews.
- See [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).
