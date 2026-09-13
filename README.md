# Moola

Moola is a private, self-hosted personal and household finance application. The approved v1 scope covers budgeting, manual transactions, debt payoff projections, manually maintained net worth, subscription tracking, and in-app reminders.

The application is a mobile-first Laravel 13 monolith on PHP 8.5 and SQLite. It has no Node.js, Redis, queue-worker, or external-database requirement.

## Current status

Implementation is in progress. Local PHP/SQLite development is the active workflow. Docker deployment assets have been drafted, but Docker integration and deployment verification are intentionally scheduled after the application features are complete.

## Local development

Install PHP 8.5 with the Intl, Mbstring, and PDO SQLite extensions, plus Composer 2. Then run these commands from the repository root on Windows, macOS, or Linux:

```sh
composer setup
composer dev
```

`composer setup` installs the locked dependencies, creates an ignored local `.env`, generates an application key, creates `database/database.sqlite`, and runs every migration. Moola is then available at `http://127.0.0.1:8000`.

Open `http://127.0.0.1:8000` after the server starts. On a new installation, Moola automatically opens the one-time setup screen where you create the household and first administrator. No terminal command or default credential is used.

If an administrator needs to issue a temporary password before the account-management screen is implemented:

```sh
php artisan moola:user:reset-password person@example.com
```

Run the automated checks with:

```sh
composer test
vendor/bin/pint --test
python scripts/spec_guard.py check --all
```

Load the representative development dataset when useful:

```sh
php artisan migrate:fresh --seed
```

The seeder refuses to add representative data in production. It creates an illustrative household, zero-based budget across all three groups, transactions, a debt and payment, net-worth snapshots, a subscription, permissions, and an audit event. It does not create a known password.

No frontend compilation step is required. Moola serves its small custom CSS and JavaScript assets directly.

## Health endpoints

- `GET /health/live` confirms that Laravel can serve a request.
- `GET /health/ready` confirms that SQLite is reachable and every committed migration has run.

Neither endpoint exposes configuration or database details.

## Docker deployment

Docker Compose remains the required self-hosted production format, but integration is scheduled as the final implementation slice. The eventual stack uses Nginx and PHP-FPM as its only runtime services and persists the SQLite database and application state outside the container lifecycle.

## Specification-driven development

All product changes begin in an approved package under `specs/`. See `AGENTS.md` and `CONTRIBUTING.md`; run `python scripts/spec_guard.py check --all` before committing implementation changes.
