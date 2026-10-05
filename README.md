# Moola

Self-hosted financial planning for individuals and households: budgets, subscriptions, debts, savings goals, net worth, and liquidity.

## Docker quick start

Install Docker Engine/Desktop with Docker Compose v2.24 or newer. From this repository:

```sh
docker compose up -d --build
```

Open `http://localhost:8080` (or your server's IP on port 8080) and create your super administrator in the web UI. Complete this on a private network before enabling public access. No demo user, default password, or manual database setup is required. Only administrators can add users.

The image contains PHP 8.5, Apache, production Composer dependencies, and built frontend assets. Node and Composer are only needed during the image build. The default SQLite installation needs no database container, Redis, mail server, separate queue worker, or separately configured cron job. A supervised scheduler runs automatically alongside Apache for budget notifications. Web requests run as `www-data`; the entrypoint and Apache master start as root to initialize volume ownership.

`moola-data` holds `/data/database.sqlite`, its WAL files, `/data/app.key`, storage, and backups. Keep this volume when upgrading. `docker compose down` preserves it; **`down --volumes` deletes it**. Run only one app container per SQLite volume.

## Optional configuration and HTTPS

Copy `.docker.env.example` to `.docker.env`, then set `APP_URL` to the address you use and `APP_TIMEZONE` to your timezone. Recreate the container after changing settings:

```sh
docker compose --env-file .docker.env up -d --build
```

The `--env-file` option is also needed when changing `MOOLA_PORT` or `MOOLA_BIND_ADDRESS`. No configuration file is required for the default installation. `.docker.env` is ignored by Git and excluded from image builds; your development `.env` is also excluded.

For HTTPS, use a reverse proxy, set `APP_URL=https://your-domain`, and set `TRUSTED_PROXIES` to the actual proxy IP or subnet. Secure cookies follow the HTTPS URL automatically. Do not trust arbitrary clients. `/up` checks application boot and database connectivity. Budget notifications are checked every minute; due dates follow `APP_TIMEZONE`. For development or a non-Docker install, run `php artisan schedule:work` alongside your web server (or configure Laravel's scheduler in cron). Admins can configure SMTP and send a test email under Settings. SMTP passwords are encrypted in the database, and changes apply without a restart. Members opt into new email reminders per budget under Notifications. Failed deliveries retry up to five times; saving corrected SMTP settings resets failed attempts. Keep APP_URL set to the address members use so email links work. Container logs go to standard output/error and Compose limits their size.

The application key is generated once and saved privately in `/data/app.key`. Startup refuses to rotate an existing key silently. Do not run `key:generate` on a live installation.

## Runtipi and other Docker UI managers

For your own Runtipi installation, use **Create Custom App** and paste `runtipi-compose.yaml`. It uses the current schema-v2 `x-runtipi` format, Runtipi routing, its external URL/timezone, and `${APP_DATA_DIR}/data` for persistent storage. Review the trusted proxy subnets if your network differs. See [Runtipi dynamic compose](https://runtipi.io/docs/reference/dynamic-compose).

The template currently uses `moola:local`. Before installing it, build the image on your Runtipi server:

```sh
docker build --target production -t moola:local .
```

Alternatively, replace `image: moola:local` with your published image and version. This repository does not assume an image has already been published and does not add the app to Runtipi's official store.

For a registry image, `.github/workflows/docker.yml` provides a manual GitHub Actions publish option. Push the complete application to your GitHub repository, run the **Docker** workflow with **publish** checked and a version such as `1.0.0`, and use `ghcr.io/<owner>/<repository>:1.0.0`. Publication happens when explicitly selected or when a release tag such as `v1.0.0` is pushed. Release tags publish the matching image version (`1.0.0`) after the container smoke tests pass. Set the package visibility to public for UI installations without registry credentials. The workflow tests the amd64 container before building and publishing amd64/arm64 images; ARM runtime still needs verification on your hardware.

Other managers supporting Compose can use `compose.image.yaml` with `MOOLA_IMAGE` set to a real published image. The source-build `compose.yaml` can also be used by managers with build support.

## Backups, upgrades, and recovery

Create a consistent SQLite snapshot, including the encryption key and files in `storage/app`:

```sh
docker compose exec --user www-data app php artisan moola:backup --no-interaction
```

The command prints the archive path under `/data/backups`. Copy it off the server regularly using your preferred backup tool. These archives contain private financial information and the encryption key; protect them. Backups do not include `.docker.env` or external database servers. Concurrent uploaded-file changes are not synchronized with the database snapshot; stop writes for an exact whole-app backup. Automatic backups are made before pending SQLite migrations, not on a daily schedule, and are not pruned automatically.

To upgrade a source build:

```sh
docker compose exec --user www-data app php artisan moola:backup --no-interaction
# Update the source to the desired tested release.
docker compose up -d --build
```

For a registry image, update its version, pull, and recreate using the same Compose file and volume. Startup runs migrations automatically and stops if the pre-upgrade backup fails. Do not downgrade the image against an upgraded database; restore the matching backup and previous image together.

To restore an archive already copied into `/data/backups`, stop the app and replace `BACKUP.tar` below with its actual filename:

```sh
docker compose stop app
docker compose run --rm --no-deps --entrypoint sh app -c 'rm -f /data/database.sqlite-wal /data/database.sqlite-shm; tar -xf /data/backups/BACKUP.tar -C /data'
docker compose up -d
```

The entrypoint repairs ownership. Restart with the saved key; if `.docker.env` supplies `APP_KEY`, it must match the restored key. For a completely fresh volume, first copy the archive into it with your Docker manager or a temporary volume-mounted container. Runtipi's backup UI can also protect its app data directory; stop the app while taking a filesystem-level backup of SQLite.

To import an existing installation, stop its writes, copy its database and `storage/app` into the persistent data directory, and supply its original `APP_KEY` on the first start. Never generate a replacement key for an existing database. Keep an untouched copy of the original data until the import is verified.

## Verification and development

```sh
php artisan test --compact
node --test tests/*.test.js
```

The Docker workflow validates Compose, builds the production image, and checks first-admin setup, frontend assets, encrypted session persistence after recreation, backups, and recovery of the administrator, session, key, and stored files. To run those container checks locally against a disposable fresh volume, first make sure port 8080 is free:

```sh
COMPOSE_PROJECT_NAME=moola-smoke docker compose up -d --build
COMPOSE_PROJECT_NAME=moola-smoke node tests/docker-smoke.mjs
COMPOSE_PROJECT_NAME=moola-smoke docker compose down --volumes
```

For development, use Laravel Herd and the existing Composer/npm workflows. Docker configuration changes do not modify your local `.env` or existing database.
