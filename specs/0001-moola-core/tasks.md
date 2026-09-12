# Moola Personal Finance App - Tasks

Status: Approved
Spec: ./spec.md
Plan: ./plan.md

## Tasks

- [x] T-001: Resolve account provisioning, household permissions, and authentication: administrator-created accounts with granular grants and email/password sign-in. (AC-001, AC-015, AC-016, AC-020)
- [x] T-002: Resolve the budgeting and transaction-entry model: three fixed main groups, zero-based subcategories with optional per-subcategory rollover, and manual entries for v1. (AC-004, AC-005, AC-018, AC-019)
- [x] T-003: Resolve debt projection and net-worth behavior: payoff projections are included and net-worth balances remain manual. (AC-007, AC-008, AC-017)
- [x] T-004: Resolve subscription reminder scope: v1 reminders are in-app only. (AC-009)
- [x] T-005: Resolve deployment and persistence: Docker Compose, SQLite on local persistent storage, versioned migrations, backup/restore, the minimum home-server profile, one installation-wide currency, and user-local time-zone calculations. (AC-002, AC-003, AC-011, AC-021)
- [x] T-006: Select and document the Laravel 13/PHP 8.5/Blade/SQLite architecture under the dependency and low-resource constraints. (AC-001, AC-011, AC-012, AC-013, AC-014)
- [x] T-007: Break the approved design into the implementation slices below, covering every acceptance criterion. (AC-001, AC-002, AC-003, AC-004, AC-005, AC-006, AC-007, AC-008, AC-009, AC-010, AC-011, AC-012, AC-013, AC-014, AC-015, AC-016, AC-017, AC-018, AC-019, AC-020, AC-021)
- [ ] T-008: Scaffold the locked Laravel application, Nginx/PHP-FPM containers, Compose services, non-root runtime, persistent volumes, configuration validation, and health endpoints. (AC-011, AC-012, AC-013)
- [ ] T-009: Implement the SQLite connection policy, core schema, constraints, indexes, integer money/rate types, factories, and representative seed data. (AC-002, AC-003, AC-004, AC-007, AC-008, AC-009, AC-014, AC-017, AC-018, AC-019, AC-021)
- [ ] T-010: Implement first-administrator CLI bootstrap, administrator-created accounts, forced password changes/resets, Argon2id authentication, secure sessions, sign-out invalidation, and sign-in rate limiting. (AC-001, AC-015, AC-016)
- [ ] T-011: Implement the granular permission model, policies/gates, administration UI, last-administrator protection, immediate revocation, and complete server-side denial matrix. (AC-001, AC-015, AC-020)
- [ ] T-012: Implement installation currency, locale, time zone, budget-start-day settings, warnings, formatting, and calendar-period boundary rules. (AC-002, AC-003)
- [ ] T-013: Implement the three fixed budget groups, subcategory management, expected income, zero-based allocations, finalization, group totals, optional rollover, and historical forward recalculation. (AC-004, AC-018, AC-019)
- [ ] T-014: Implement manual income/expense transaction creation, editing, deletion, categorization, pagination, confirmation, and immediate budget recalculation. (AC-004, AC-005)
- [ ] T-015: Implement the current-period dashboard with budget totals, recent transactions, upcoming subscriptions, debt balance, net worth, and complete empty/loading/error states. (AC-006)
- [ ] T-016: Implement debt management, payment history, deterministic amortization, payoff/interest estimates, visible assumptions, extra-payment behavior, and non-amortizing warnings. (AC-007, AC-017)
- [ ] T-017: Implement manual asset/liability management, current net-worth totals, and chronological snapshots without transaction-derived changes. (AC-008)
- [ ] T-018: Implement subscription management, recurrence calculation, upcoming reminders, and responsive calendar/list views without external notifications. (AC-009)
- [ ] T-019: Complete the mobile-first visual system, responsive navigation/forms/tables, touch targets, focus behavior, empty/error presentation, accessibility automation, and keyboard review. (AC-010)
- [ ] T-020: Add Composer/container vulnerability checks, license inventory, software bill of materials, dependency-update automation, secret scanning, and the documented dependency review record. (AC-012, AC-013)
- [ ] T-021: Implement locked startup upgrades, automatic pre-migration backups, retention, restore/doctor commands, integrity checks, previous-release fixtures, and failure recovery. (AC-011, AC-021)
- [ ] T-022: Tune OPcache, PHP-FPM workers, SQLite queries/indexes, caching headers, and asset delivery; run API and mobile performance budgets on the 1-CPU/1-GB profile. (AC-014)
- [ ] T-023: Run every automated and manual verification step, reconcile all acceptance criteria, document deployment/backup/upgrade/rollback, and disclose any deviation before marking the package complete. (AC-001, AC-002, AC-003, AC-004, AC-005, AC-006, AC-007, AC-008, AC-009, AC-010, AC-011, AC-012, AC-013, AC-014, AC-015, AC-016, AC-017, AC-018, AC-019, AC-020, AC-021)

## Handoff Notes

Product and architecture decisions are approved. Implementation has not started; T-008 is the first implementation task.
