# Portainer: install and configure automatic mCMS Core updates

[Contents](README.md) · [Русский](../ru/portainer-auto-updates.md) · [Upgrade and rollback](operations.md)

For **Portainer CE with a Docker Standalone environment**. This guide covers both a new release-image deployment and **migration of an existing Git Stack without losing persistent data**. The Portainer API token is needed only for **automatic Core upgrades**, not for normal site operations or independent module/theme update checks.

## 1. Choose the deployment model

| Mode | Compose path | Core upgrades |
|---|---|---|
| Build from Git | `portainer-stack.yml` | Operator performs Git Re-pull/Redeploy with image rebuild; Core is not self-updating |
| Versioned GHCR releases | `portainer-release-stack.yml` | When the API is configured, `mcms-updater` checks published GitHub Releases, creates a backup and requests a Portainer Git redeploy |

The release stack runs public images `ghcr.io/mirivlad/mcms-app:stable` and `ghcr.io/mirivlad/mcms-web:stable`. Check the [official releases](https://github.com/mirivlad/mcms/releases) and the `mcms-images-ready.json` release asset first. Commits to `main` are **not** automatically stable releases.

Automatic Core updates support only **newer stable patch versions in the same major/minor series** (for example, 0.1.9 → 0.1.10). Moving from 0.1.x to 0.2.0 and guaranteed automatic rollback after failed deployment are outside this workflow.

## 2. Fresh installation with release images

1. In Portainer open **Stacks → Add stack → Repository**. Enter a stack name, e.g. `mcms`, repository `https://github.com/mirivlad/mcms`, Git reference `refs/heads/main` and **Compose path `portainer-release-stack.yml`**. For an existing deployment use [section 3](#3-migrate-an-existing-git-stack) instead.
2. Under **Environment variables** set strong, **different** values for `MCMS_DB_PASSWORD` and `MCMS_DB_ROOT_PASSWORD`. Do not use example passwords from documentation. Configure your HTTPS reverse proxy to forward to `127.0.0.1:8188` (default), preserving `Host` and `X-Forwarded-Proto` headers.
3. Explicitly set `MCMS_IMAGE_TAG=stable` and **digest-pin MariaDB** using `MCMS_DB_IMAGE` as in section 4. Keep any custom database/mail settings.
4. Deploy the Stack, wait for healthy MariaDB, then open the mCMS URL. Complete the `/install` wizard, and create **and download** your first full backup from **Administration → Backups**.
5. Create a Portainer API token and configure section 5; enable Core auto-updates in `/admin/updates`. Without an API connection, the site works normally but **Core does not update itself**.

## 3. Migrate an existing Git Stack

**Do not create a second Stack pointing to the same database and do not delete the current Stack.** Keep the same Stack/Compose project name: persistent Docker volumes are usually named with the project prefix.

1. In **mCMS → Administration → Backups**, create and **download** a full ZIP to another device. Save existing Portainer Stack environment variables/configuration securely (they contain passwords). Keep the current image or Git revision for manual recovery.
2. Open **Portainer → Stacks → your existing Stack → Edit Git settings** (labels vary by Portainer version). Keep the repository, branch `main`, **Stack name** and **all existing environment variables**. Change only **Compose path** to `portainer-release-stack.yml`.
3. Disable competing GitOps polling/force redeploy. Core should decide when to redeploy, after validating compatibility and creating a safety archive.
4. Set `MCMS_IMAGE_TAG=stable` and digest-pin the MariaDB image. Add the Portainer API settings from section 5. Verify the existing Docker volumes.
5. Use **Pull and redeploy / Re-pull image** for the **same Stack**, without deleting volumes. Wait for `app`, `web`, `updater` and a healthy database. Confirm admin login, pages, media, orders and backup access.

**List existing volume mounts before changing anything** on the Docker host:

```bash
for c in mcms-db-1 mcms-app-1 mcms-web-1 mcms-mail-1; do
  docker inspect "$c" --format '{{.Name}} {{range .Mounts}}{{.Name}}:{{.Destination}} {{end}}'
done
```

The container names here assume a Stack named `mcms`; other Stack names have different prefixes. **Five application containers with six named volumes** (`db_data`, `modules_data`, `themes_data`, `backup_data`, `mail_queue`, `dkim_data`) is normal. **Never** use `docker compose down -v`, delete volumes, or rename the Stack during migration. If your Portainer version does not allow changing the Compose path, stop and identify its supported conversion procedure instead of deploying a competing second Stack.

## 4. Pin MariaDB and select stable Core images

In **Stack → Environment variables**:

```dotenv
MCMS_IMAGE_TAG=stable
MCMS_DB_IMAGE=mariadb:11.4@sha256:<64_character_manifest_digest>
```

On the **Docker host**, find the original registry **RepoDigest**, not the local image ID:

```bash
docker image inspect mariadb:11.4 \
  --format '{{range .RepoDigests}}{{println .}}{{end}}'
```

Example output: `mariadb@sha256:...`. Combine the 64-character digest from the image's registry with the version to form `mariadb:11.4@sha256:...`. **Do not** copy the local `Image ID` reported by `docker inspect <container> --format '{{.Image}}'`. Verify the digest is appropriate for the exact image release and platform you use. A MariaDB digest change is a separate database upgrade and should not be an incidental consequence of updating mCMS.

The Core updater refuses automatic deployment unless `MCMS_IMAGE_TAG` is explicitly **`stable`** and `MCMS_DB_IMAGE` is pinned using SHA-256.

## 5. Configure Portainer API for automatic Core upgrades only

1. Use a Portainer account with access limited to the **intended Stack**, if your Portainer edition/permission configuration supports that restriction. Open **My account → Access tokens → Add access token**, and create e.g. `mcms-updater`. Store the secret when it is shown: it will not be displayed again. Never put it in Git, screenshots or logs.
2. Find the numeric **Stack ID** in the Portainer Stack details or URL, e.g. `/stacks/37`. The suffix of `mcms-app-1` is a **container** ordinal, not the Stack ID.
3. Find the numeric **Environment / Endpoint ID** under **Environments** by opening the actual Docker environment and inspecting its details or URL. In a URL containing `#!/2/docker/dashboard`, **2** is the environment ID, which is distinct from the Stack ID.
4. Choose an HTTPS URL for **Portainer itself**, reachable **from the `updater` container**, using a trusted certificate. `https://localhost:9443` in the updater container normally refers to the wrong host.
5. Add these variables to the **same Stack**:

   ```dotenv
   MCMS_PORTAINER_URL=https://portainer.example.com
   MCMS_PORTAINER_API_KEY=ptr_<secret_token>
   MCMS_PORTAINER_STACK_ID=<numeric_stack_id>
   MCMS_PORTAINER_ENDPOINT_ID=<numeric_docker_environment_id>
   ```

6. Apply/redeploy the Stack so `updater` receives the settings. The four variables are passed **to `updater` only**, not to public PHP `app`, but anyone with sufficient access to the Stack's settings may see the secrets. Restrict Portainer administrator permissions accordingly.
7. Open **mCMS → Administration → Updates** (`/admin/updates`) and enable **Core**. Module and theme switches are independent.

### Why is an API token needed?

`mcms-updater` intentionally has **no Docker socket**. It checks GitHub Releases and the `mcms-images-ready.json` marker; validates patch compatibility, the exact authorized Stack and Endpoint IDs, `stable` selector and pinned database; takes a safety ZIP; and then calls **Portainer REST API** to redeploy the existing Git Stack. Portainer downloads new app/web images and recreates containers **without changing volumes**. The updater saves durable state first, because its own container will also be replaced.

Turning on the mCMS **Core** switch **does not mean** Portainer API has been configured successfully. If the settings are empty or the Stack still uses `portainer-stack.yml`, Core will **not** auto-update even though module updates might still work.

## 6. Validate operation

Check `app`, `web`, `updater`, `db` and `mail` service health. On the Docker host:

```bash
# Actual running Core version (adjust the container prefix for another Stack name)
docker exec mcms-app-1 php -r \
  'require "/var/www/html/src/Core/Version.php"; echo \MCMS\Core\Version::VERSION, PHP_EOL;'

# Background updater errors
docker logs --tail=100 mcms-updater-1
```

In **Portainer → Stacks → your Stack**, confirm the Compose path is `portainer-release-stack.yml` and `app`/`web` images are `ghcr.io/mirivlad/mcms-*:stable` rather than locally built `mcms-app`/`mcms-web` images.

By default the service checks roughly **every 6 hours** (`MCMS_UPDATE_INTERVAL_SECONDS=21600`). The log message “No eligible automatic extension updates” only describes **extensions**: it does **not** certify that automatic Core updates are ready. Running `php spark mcms:updates:run` is **not a read-only diagnostic** when Core auto-update is enabled — it may trigger a real redeploy.

### Troubleshooting

- Missing `mcms-images-ready.json` → wait for both GHCR images to be published.
- Portainer URL not reachable from `updater` or TLS certificate untrusted → correct DNS/HTTPS/routing; do not disable TLS verification.
- API errors 401/403 → check the token and its permissions.
- Wrong Stack/Endpoint ID, selector other than `stable` or MariaDB not digest-pinned → correct and redeploy configuration.
- Target version changes major/minor → perform a supervised manual upgrade and plan restoration.
- Site still on an older Core version → inspect the updater logs, Core update state and published image availability. Do not send a second redeploy request until the first one's status is known.

## 7. Backups and rollback

Before the initial conversion or any manual upgrade, **download a full ZIP off the server**. Automatic Core updates create a local pre-update safety ZIP, but **do not guarantee automatic rollback** of Docker images and database migrations. Disaster recovery requires the previous known-good image/tag, the full-site archive **from that same earlier Core version**, and access to Portainer. Follow the [backup and recovery guide](recovery.md).

**Security:** a Portainer API token grants infrastructure management permissions. Use the narrowest available scope, store it only in private Stack variables, limit who can view Stack settings and rotate it periodically.
