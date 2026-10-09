# Installation

mCMS is designed to install against an empty database without importing any
application-specific dump.

Demo content is optional and disabled by default. For your own test instance,
set `MCMS_DEMO_SEED=1` before installation or a redeploy. Seeding is additive and
runs only once per database. See [Portainer demo setup](PORTAINER.md#optional-demo-content-for-your-own-test-stack).

Third-party themes can be added from Themes → Install theme ZIP. Preserve the
application-only `theme_packages_data` volume when redeploying; see
[theme package format and lifecycle](THEMES.md).

## Docker Compose / Portainer flow

Copy the environment template:

```bash
cp .env.example .env
```

Set unique database passwords, the public base URL and mail settings, then start
the stack:

```bash
docker compose up -d --build
```

The application container runs:

```text
php spark mcms:update
```

before PHP-FPM starts. This migrates:

1. the mCMS Core (`App` namespace);
2. CodeIgniter Settings;
3. CodeIgniter Shield;
4. only modules already marked enabled in `mcms_modules`.

It deliberately never runs `migrate --all`.

## Browser first-run installer

On a fresh database, opening the site root redirects to:

```text
/install
```

The installer asks for:

- site name;
- first administrator email;
- first administrator username;
- password and confirmation;
- whether public registration should initially be enabled.

Submitting the form creates the first `superadmin`, stores the initial settings,
logs that user in and redirects to the Core dashboard.

As soon as a superadmin exists, `/install` closes and redirects to the normal
login page. The installer cannot be used to create a second superadmin.

## CLI fallback

The same installation service is available through the CLI for recovery or
headless deployments:

```bash
read -r -p "Admin email: " MCMS_ADMIN_EMAIL
read -r -p "Admin username [admin]: " MCMS_ADMIN_USERNAME
MCMS_ADMIN_USERNAME=${MCMS_ADMIN_USERNAME:-admin}
read -r -s -p "Admin password: " MCMS_ADMIN_PASSWORD
echo

export MCMS_ADMIN_EMAIL MCMS_ADMIN_USERNAME MCMS_ADMIN_PASSWORD

docker compose exec \
  -e MCMS_ADMIN_EMAIL \
  -e MCMS_ADMIN_USERNAME \
  -e MCMS_ADMIN_PASSWORD \
  app php spark mcms:admin:create

unset MCMS_ADMIN_EMAIL MCMS_ADMIN_USERNAME MCMS_ADMIN_PASSWORD
```

The CLI command and browser installer share the same Core service and both refuse
to run after installation is complete.

## Frontend assets

The runtime does not depend on a public CDN.

The nginx image installs exact package versions during its build stage and copies
only these runtime assets into the final image:

- `@tabler/core@1.6.1`: core CSS and JS;
- `bootstrap@5.3.8`: core CSS and bundled JS.

Tabler demo/vendor libraries such as ApexCharts are not copied.

Third-party notices are stored in `THIRD_PARTY_NOTICES.md` and are also copied
into the web image under `/usr/share/licenses/mcms/`.

## Updates

Normal application startup runs `mcms:update`, therefore a new image applies
Core/package migrations and migrations for enabled modules only.

Installing or enabling a new module applies that module's migrations and default
settings at enable time. Disabling a module preserves its tables and settings.
