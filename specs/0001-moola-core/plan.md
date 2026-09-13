# Moola Personal Finance App - Implementation Plan

Status: Approved
Spec: ./spec.md

## Approach

Product questions and the technical stack are resolved. Implementation remains blocked until the specification and this plan are explicitly approved. Build a modular, mobile-first Laravel monolith with server-enforced authentication and permissions, embedded SQLite storage, integer monetary fields, and calendar-aware recurrence logic.

Use Docker Compose to run two lightweight containers: Nginx for HTTP/static assets and PHP-FPM for Laravel. SQLite remains embedded in the application and lives on persistent local storage; there is no database container. The stack must be tuned and measured on the approved 1-CPU/1-GB-RAM home-server profile. Keep feature boundaries explicit without introducing queues, Redis, Node.js at runtime, microservices, or enterprise orchestration.

During implementation, use PHP 8.5, Composer 2, Laravel's local development server, and a repository-local SQLite file. Complete Docker build and runtime integration only after the application features, upgrade tooling, and performance controls are implemented; the deployment contract and acceptance criteria remain unchanged.

## Selected Stack

- PHP 8.5 on its supported stable patch line.
- Laravel 13 with Blade templates, Eloquent, migrations, validation, sessions, hashing, rate limiting, gates, and policies.
- SQLite through PHP's maintained PDO SQLite extension, configured for foreign keys, a bounded busy timeout, and WAL mode on local storage.
- Semantic server-rendered HTML, custom responsive CSS, and small dependency-free JavaScript modules. Do not add Livewire, React, Vue, Svelte, Inertia, Tailwind, Bootstrap, or a Node.js runtime for v1.
- Composer 2 in the container build stage with a committed `composer.lock`, production installs using `--no-dev --prefer-dist --classmap-authoritative`, and no Composer binary in the runtime image.
- Official stable Nginx and PHP-FPM container images pinned to patch versions and immutable digests by the release workflow.
- PHPUnit/Laravel feature tests for application behavior and Laravel Dusk only for the small set of critical responsive browser journeys.

Use framework-native capabilities before adding packages. Every new direct dependency requires a recorded purpose, license, maintenance evidence, security status, and removal/replacement path.

## Application Architecture

- Organize code by feature under `app/Domain`: Identity, Budgeting, Transactions, Dashboard, Debts, NetWorth, Subscriptions, and Settings.
- Keep controllers thin. Put calculations and state changes in typed application services and immutable value objects.
- Use Blade layouts/components for UI composition. Use ordinary form posts and progressive enhancement; add JSON endpoints only when a demonstrated interaction requires them.
- Treat one installation as one household workspace. The initial administrator owns it; additional administrator-created users receive granular permissions within that workspace.
- Store browser sessions and rate-limit state on the persistent local application volume. Do not add Redis or a queue worker.
- Provide `/health/live` and `/health/ready`; readiness verifies the database is reachable and migrated without exposing sensitive details.
- Serve production traffic behind HTTPS. Trust forwarded headers only from explicitly configured reverse-proxy addresses.

## Affected Components

- Web interface: authentication, administrator-managed accounts and permissions, household membership, dashboard, budgets, transactions, debts, net worth, subscriptions, calendar, and settings.
- Application/API layer: validation, authorization, zero-based allocation and group roll-up calculations, budget-period calculations, subscription recurrence, debt amortization/projections, and financial summaries.
- Persistence: embedded SQLite, versioned migrations, and relational records for users, households, memberships, permission grants, settings, categories, budgets, transactions, debts/payments, assets/liabilities/snapshots, and subscriptions.
- Deployment: resource-conscious Docker Compose configuration, persistent database storage, secrets, health checks, backup guidance, upgrade guidance, and documented minimum/recommended home-server requirements.
- Test suites: unit coverage for money/date calculations, integration coverage for ownership and persistence, and browser coverage for critical workflows.
- Supply-chain controls: dependency lockfiles, automated update checks, license review, vulnerability scanning, container scanning, and a generated software bill of materials.
- Performance controls: representative seed data, repeatable API/load checks, mobile web-vitals checks, query analysis, and regression budgets in CI where stable.

## Interfaces and Data

Use server-rendered web routes with CSRF-protected form commands and query-specific view models. There is no public API in v1. The relational design must preserve these invariants:

- Every financial record is owned by an authenticated user or household, and household access is granted only through a current membership.
- Account creation and permission changes are restricted to administrators and evaluated server-side on every protected request.
- Money is stored as signed 64-bit integer minor units and interpreted using the single installation-level currency setting; individual financial records do not carry independently selectable currencies. Interest rates use scaled integers, never PHP floats.
- User-local dates, instants, and recurrence rules are modeled distinctly.
- Deletions and relationship behavior are explicit; financial history is not removed accidentally through cascading operations.
- Schema changes use repeatable, versioned migrations.
- The SQLite file stays on a local persistent volume, receives a consistent backup before upgrade migrations, and is never placed on a network filesystem.
- Budget subcategories belong to exactly one of the three fixed main-group identifiers; display labels are not used as mutable accounting keys.
- Budget finalization requires fixed-precision expected income and subcategory allocations to reconcile exactly to zero left to assign.
- Each subcategory stores a rollover preference, and each subcategory-period record preserves the resulting immutable opening balance: the preceding closing balance when enabled, or zero when disabled. Current allocation and spending remain separate values.
- Email addresses are normalized for identity lookup, password hashes use a supported memory-hard algorithm, and session identifiers/credentials are never stored in plaintext logs or client-readable application state.

Use these primary tables, with foreign keys, constraints, timestamps, and indexes on ownership, period, status, and date query paths:

- `installation_settings`: currency, locale, time zone, budget start day, and schema/application settings.
- `users`: normalized unique email, Argon2id password hash, enabled state, forced-password-change state, and administrator flag.
- `permissions`: per-user capability grants for budget, transactions, debts, net worth, subscriptions, users, and settings.
- `budget_subcategories`: fixed main-group identifier, user label, ordering, archive state, and rollover preference.
- `budget_periods`: start/end dates, expected income, state, and finalization timestamp.
- `budget_allocations`: period/subcategory allocation and immutable opening rollover in minor units.
- `transactions`: income/expense type, amount, local financial date, description, and optional subcategory.
- `debts` and `debt_payments`: current principal, scaled APR, minimum/planned payment, payment records, and projection inputs.
- `net_worth_accounts` and `net_worth_snapshots`: manually maintained asset/liability balances and dated history.
- `subscriptions`: amount, recurrence rule, next charge date, subcategory, and active state.
- `audit_events`: administrator account, permission, authentication, and destructive-setting events without financial secrets or credentials.

## Authentication and Permission Model

- On an empty migrated database, redirect ordinary browser requests to a one-time `/setup` form that creates the household and first administrator and signs that administrator in. Disable both setup reads and writes immediately once any user exists. Never ship default credentials or require shell access for normal installation.
- Administrators create users with a temporary password. The user must change it on first sign-in. With no email delivery in v1, password resets are administrator-issued temporary passwords that also force a change.
- Use Laravel session authentication with Argon2id hashing, session rotation at sign-in/password change, generic credential errors, CSRF protection, secure/HTTP-only/SameSite cookies, and sign-in rate limits.
- Define capabilities as `budget.view/manage`, `transactions.view/manage`, `debts.view/manage`, `networth.view/manage`, `subscriptions.view/manage`, `users.manage`, and `settings.manage`. A manage grant implies the matching view grant.
- Administrators have all capabilities. New ordinary users have none until grants are saved. Prevent removal, disablement, or demotion of the last enabled administrator.
- Enforce capability checks with Laravel policies/gates in every controller action and data query. Blade visibility mirrors authorization but is never the security boundary.

## Financial and Date Rules

- Resolve the current budget period in the installation time zone. If the configured start day does not exist in a month, use that month's final calendar day.
- Finalization requires `expected_income_minor - sum(allocation_minor) == 0`. Rollover is excluded from this equation.
- Available balance is `opening_rollover_minor + allocation_minor - expense_minor`. Income transactions affect actual-income reporting but never silently rewrite expected income or allocations.
- When a period closes, persist each next-period opening rollover from the preceding closing availability if that subcategory's rollover setting is enabled; otherwise persist zero.
- Historical transaction edits recalculate the affected subcategory-period chain in one database transaction and show the user which later balances changed.
- Debt projection uses the current principal, scaled annual percentage rate, and planned monthly payment. Apply monthly interest before payment, round half-up to the currency's minor unit, stop when principal reaches zero, warn when payment does not reduce principal, and cap simulation at 1,200 months.
- Store financial dates as ISO calendar dates and security/audit instants as UTC timestamps. Convert for display using the installation time zone.

## Migration, Backup, and Restore

- Run a dedicated entrypoint upgrade command before PHP-FPM accepts traffic. It obtains a local lock, checks the recorded migration state, and exits non-zero on failure.
- Before pending migrations, create a timestamped consistent SQLite backup in the backup volume and verify it can be opened. Retain a configurable number of backups with a safe default.
- Apply Laravel migrations exactly once, run SQLite integrity and foreign-key checks, then mark readiness. Never continue serving on a partially upgraded or failed database.
- Provide `php artisan moola:backup`, `moola:restore`, and `moola:doctor` commands through documented Docker Compose wrappers.
- CI migrates a fixture from the previous supported release and tests failure recovery. Release notes identify irreversible schema changes and the compatible rollback boundary.

## Risks and Mitigations

- Incorrect date boundaries could misstate budget or subscription periods. Centralize and exhaustively test period/recurrence functions.
- Floating-point arithmetic could corrupt totals. Use fixed-precision storage and calculation throughout.
- Zero-based allocations and spending actuals can be confused. Model planned allocations separately from transactions, make left-to-assign explicit, and never alter allocations implicitly when spending occurs.
- Editing historical transactions could change later rollover balances. Recalculate the affected subcategory's forward balance chain deterministically and surface the resulting changes rather than leaving stale derived values.
- Debt projections could imply false precision or fail to converge when payments do not cover interest. Document assumptions, use fixed-precision amortization rules, detect non-amortizing plans, and label results as estimates rather than financial advice.
- Missing ownership filters could expose sensitive records. Enforce authorization server-side and test cross-user denial for every resource family.
- An overly broad first release could undermine the minimal experience. Use the approved out-of-scope list and deliver vertical slices.
- Self-hosted upgrades could lose data. Use migrations, persistent volumes, documented backups, and rollback guidance before release.
- SQLite permits one writer at a time. Keep transactions short, configure a bounded busy timeout and WAL mode where supported by the local filesystem, use one application instance, and fail visibly rather than dropping writes under contention.
- Changing the installation currency could be mistaken for conversion. Make the settings warning explicit: changing currency affects formatting only and never recalculates stored amounts.
- Any dependency can become vulnerable or lose maintenance. Minimize production dependencies, select supported stable releases with credible maintenance and security practices, scan continuously, and maintain documented replacement paths for critical packages.
- Laravel has a shorter major-version cadence than the PHP runtime. Track both support windows, apply patch releases promptly, begin major-upgrade validation before security support ends, and keep business logic isolated from framework internals.
- Performance can degrade as financial history grows. Establish representative datasets and budgets early, paginate unbounded collections, index measured query paths, and fail release checks on material regressions.
- A feature-heavy runtime or oversized database configuration could exceed home-server resources. Treat the 1-CPU/1-GB profile as an architecture constraint, measure total Compose-stack memory, and prefer simple in-process capabilities over additional services.
- Excess PHP-FPM workers can exhaust the reference server. Enable OPcache, set conservative worker limits from measured per-process memory, cap request sizes, and verify the complete Compose stack under load.
- Adding message-delivery infrastructure would increase resource use and operational complexity. Keep v1 subscription reminders inside the application and do not deploy email or push workers.
- Household sharing and granular permissions expand the authorization boundary. Centralize membership and capability checks, default new users to no financial permissions, invalidate authorization state promptly after changes, and test denial for non-members, disabled users, removed users, and insufficient permissions.
- Email/password authentication is exposed to credential stuffing and account enumeration. Use generic failures, rate limits, secure password hashing, secure cookie settings, session rotation, and explicit session invalidation.

## Verification

- AC-001: Authentication integration tests and cross-user authorization tests for every protected resource.
- AC-002: Table-driven unit tests for pay-cycle boundaries, leap years, and days 28 through 31.
- AC-003: Formatting tests plus a settings workflow test confirming that amounts are not converted.
- AC-004: Fixed-precision budget calculation unit tests and aggregate reconciliation tests.
- AC-005: Integration/browser tests covering transaction create, edit, recategorize, and delete effects.
- AC-006: Dashboard browser tests for populated, empty, loading, and error states.
- AC-007: Debt lifecycle integration tests covering payments, recalculation, projection display, visible assumptions, zero-interest debt, and non-amortizing warnings.
- AC-008: Net-worth calculation and snapshot-ordering tests.
- AC-009: Table-driven recurrence tests plus upcoming-list and calendar browser tests.
- AC-010: Responsive visual checks, automated accessibility checks, and manual keyboard verification.
- AC-011: Clean-host Docker Compose smoke test for Nginx/PHP-FPM, restart persistence test, resource-limit observation, and secret-scanning check.
- AC-012: Reproducible locked Composer install, dependency-policy review, `composer licenses`, software bill of materials generation, and vulnerability scan.
- AC-013: Pull-request and scheduled dependency/container scans, with a test confirming that disallowed findings fail the release gate.
- AC-014: Repeatable warm API benchmark and mobile performance audit against the approved hardware, network, and representative-data profile.
- AC-015: Administrator account-provisioning and household-membership tests, including creation, disablement, removal, non-member denial, and preservation of unrelated data.
- AC-016: Browser administrator bootstrap, administrator-provisioned account, forced-password-change, reset, sign-in/sign-out, generic invalid-credential, rate-limit, Argon2id-hash, and session-invalidation tests.
- AC-017: Independent amortization fixtures for zero-interest, ordinary-interest, extra-payment, rounding-boundary, and payment-below-interest cases.
- AC-018: Budget creation tests for the three fixed groups, subcategory management, exact zero-based reconciliation, under-allocation, over-allocation, and group roll-ups.
- AC-019: Positive and negative rollover fixtures, current-income reconciliation tests, available-balance formula tests, and forward recalculation tests after historical edits.
- AC-020: Permission-matrix integration tests for every protected resource and operation, including direct API denial, administrator-only account management, and immediate revocation.
- AC-021: Previous-release SQLite fixture migration, migration-failure atomicity, pre-migration backup, documented restore, persistent-volume recreation, and database-integrity tests.
- AC-022: First-run browser tests covering automatic setup redirect, valid administrator creation and sign-in, invalid form handling, concurrent/duplicate setup protection, permanent setup closure, and continued absence of public registration.

## Decision Log

- 2026-09-13: Replaced the first-administrator CLI requirement with a one-time browser setup flow for non-technical self-hosters and app-store installers such as Runtipi. Setup is available only while the users table is empty and does not enable public registration.
- 2026-09-13: Completed the authentication foundation with browser-based first-run setup, administrator-driven account provisioning actions, Argon2id password hashing, generic throttled sign-in failures, global forced-password-change enforcement, encrypted server-side sessions, session invalidation, and authentication audit events.
- 2026-09-13: Completed the SQLite domain foundation with strict tables, fixed group/capability identifiers, signed 64-bit minor-unit money, basis-point interest rates, explicit foreign-key behavior, measured query-path indexes, typed Eloquent models, factories, and non-production representative data.
- 2026-09-13: Local development will use PHP/Composer and SQLite directly. Docker deployment assets remain versioned, but image builds and Compose runtime integration move to the final implementation stage so feature development does not require Docker.
- 2026-09-13: Implemented the foundation on Laravel 13.31, PHP 8.5.10, Composer 2.10.3, and Nginx 1.30.4. Browser assets are served directly so development and production require no Node.js toolchain.
- 2026-09-12: User approved the initial product specification and Laravel technical plan for implementation.
- 2026-09-12: Laravel stack approved for planning: PHP 8.5, Laravel 13, Blade, Eloquent, SQLite, custom CSS, minimal vanilla JavaScript, Nginx, and PHP-FPM. No SPA framework, Livewire, Redis, queue worker, or Node.js runtime in v1.
- 2026-09-12: SQLite selected as the v1 database because its embedded, single-file, low-administration model fits Moola's home-server scope. Storage must remain local, persistent, backed up, and version-migrated.
- 2026-09-12: Account provisioning and permissions confirmed as administrator-controlled. Public registration and account creation by ordinary household users are excluded from v1.
- 2026-09-12: Rollover confirmed as a per-subcategory option. When enabled, positive and negative balances carry forward; when disabled, the next period starts at zero. Rollover never changes current-income zero-based allocation.
- 2026-09-12: Budgeting confirmed as zero-based at the subcategory level beneath three fixed groups: Essentials, Guilt-Free Spend, and Future.
- 2026-09-12: Debt payoff projections and interest accrual confirmed for v1, including estimated payoff timing and interest based on planned payments.
- 2026-09-12: One configurable currency confirmed for the entire installation. Per-user/per-record currencies and exchange-rate conversion are out of scope.
- 2026-09-12: Subscription reminders confirmed as in-app only for v1; email and push delivery are deferred.
- 2026-09-12: Moola confirmed as a home-server application rather than an enterprise platform. The minimum reference profile is 1 CPU core, 1 GB available RAM, and SSD-backed storage for the complete Docker Compose stack.
- 2026-09-12: Mobile-first responsive web access confirmed for both phone and desktop use; a native mobile app remains out of scope for v1.
- 2026-09-12: Docker Compose confirmed as the required self-hosting and deployment model.
- 2026-09-12: Dependency longevity, prompt security maintenance, and measured performance are release requirements. Zero future ecosystem risk cannot be guaranteed, so continuous detection and replacement controls are required.
- 2026-09-12: Moola confirmed as a consumer app for individuals, with optional shared household budgets; business use is out of scope.
- 2026-09-12: V1 transaction entry confirmed as manual-only; bank synchronization and file import are out of scope.
- 2026-09-12: V1 net-worth balances confirmed as manually maintained; transaction-derived balances and external valuation synchronization are deferred.
- 2026-09-12: Email address and password confirmed as the v1 authentication method.
- 2026-09-12: Captured the product vision as a draft. Technology selection and implementation sequencing are deferred until the open product questions are resolved.
