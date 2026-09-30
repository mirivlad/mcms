# Installation

mCMS is still in Core bootstrap, but the intended clean-install path is already the
deployment contract.

## Development with Docker Compose

Copy the environment template:

```bash
cp .env.example .env
```

Set unique database passwords, then start the stack:

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

## Create the first super administrator

Do not store the initial administrator password in `.env` or Portainer stack
configuration.

For a shell deployment, export the values only for the bootstrap command:

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

The command refuses to create another account once a super administrator exists.

A browser-based first-run installer is planned before the first release so normal
Portainer users do not need this CLI bootstrap.

## Updates

Normal application startup runs `mcms:update`, therefore a new image applies
Core/package migrations and migrations for enabled modules only.

Installing or enabling a new module applies that module's migrations and default
settings at enable time.
