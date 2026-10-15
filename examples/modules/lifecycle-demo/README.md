# Lifecycle demo

Independent external package for mCMS's catalog and lifecycle checks. It needs no
site-specific content or other module. Explicit enablement runs its real namespace
migration, creates `lifecycle_demo_notes`, inserts one note and installs a greeting
setting. Its provider writes `welcome.txt` through Core's scoped module storage.

Disable keeps everything. Remove with retained data and reinstall exactly 1.0.0
without re-running the initial migration; enable explicitly afterward. Complete
removal uses the manifest's explicit owned-table contract and Core's observed
ownership receipt, clears only this module's settings/files/history and permits a
clean installation. Confirm a verified full backup before complete removal.

Build with `php spark mcms:module:pack examples/modules/lifecycle-demo /tmp/demo.zip`.
See `docs/EXTENSIONS.md` for the publication and cleanup contracts. MIT licensed.
