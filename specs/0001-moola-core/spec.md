# Moola Personal Finance App

Status: Approved
Owner: Unassigned
Created: 2026-09-12

## Context

Personal finance tools are often either too complex, cloud-dependent, or poorly aligned with how people are paid. Moola will give individuals and households a calm, private, self-hosted place to understand their budget, debts, net worth, and recurring subscriptions without spreadsheet overhead. It is a consumer product designed for a modest home server, not for business accounting, enterprise finance, or enterprise-scale infrastructure.

The primary user wants to answer four questions quickly:

1. How much have I budgeted, spent, and got left in this budget period?
2. What debts do I have and how are their balances changing?
3. What is my current net worth and its trend?
4. Which subscriptions are coming up and when will they be charged?

## Scope

Deliver a minimal, polished, mobile-first responsive personal finance web application that is self-hosted using Docker Compose on infrastructure controlled by the user. The same web application must be comfortable to use on a phone or a desktop without requiring a native mobile app. Authentication protects all financial data. The installation uses one configurable currency, and each user can configure a recurring budget-period start day that aligns with their pay cycle.

The first release covers manual financial tracking and clear summaries. An individual can use Moola alone, while the installation administrator can create additional accounts and grant them selected access to household budgeting capabilities. It should favor understandable workflows and trustworthy calculations over advanced accounting features.

## Requirements

- REQ-001: Moola must ship with a documented Docker Compose deployment for a modest home server, with persistent storage that survives container restarts and recreation. The complete application and database stack must support a reference minimum of 1 CPU core, 1 GB available RAM, and SSD-backed storage, excluding storage capacity consumed by user data and backups.
- REQ-002: Every financial screen and data operation must require an authenticated user. Access must be limited to records owned personally by that user or shared through a household of which the user is a current member.
- REQ-003: A Moola installation must use one configurable currency for all users, households, budgets, transactions, debts, assets, liabilities, subscriptions, and reports. All monetary values must use that currency's symbol, decimal rules, and locale-aware formatting consistently; mixed currencies and currency conversion are not supported.
- REQ-004: A user must be able to select the calendar day on which each budget period begins so it can align with their pay date. For months without that day, the period begins on the month's final day.
- REQ-005: Every budget must use three fixed top-level groups: Essentials, Guilt-Free Spend, and Future. A user must be able to create, rename, archive, and order subcategories within those groups and assign a budgeted amount to each subcategory for a budget period. Future subcategories may include retirement, emergency savings, and education funds.
- REQ-006: A user must be able to manually record income and expense transactions with an amount, date, description, and category where applicable, and edit or delete those transactions. Automated bank synchronization and file import are not included in v1.
- REQ-007: The budget must show budgeted, spent, and remaining amounts for every category and for the whole period. Remaining equals budgeted minus qualifying expense transactions.
- REQ-008: A financial dashboard must summarize the current budget period, recent transactions, upcoming subscriptions, debt balance, and net worth without requiring navigation to each feature.
- REQ-009: A user must be able to track debts with a name, current balance, annual interest rate, minimum payment, planned recurring payment, and payment history. Moola must project the estimated payoff date, remaining payment count, and estimated interest using documented amortization assumptions, and recalculate the projection when relevant values or payments change.
- REQ-010: A user must be able to enter and update asset and liability balances manually and see current net worth, calculated as total assets minus total liabilities, together with historical snapshots. V1 must not derive balances from transactions or synchronize valuations from external providers.
- REQ-011: A user must be able to track subscriptions with a name, amount, recurrence, next charge date, category, and active status.
- REQ-012: Subscription charges must be visible in both an upcoming list and a calendar view, with correct recurrence across month and year boundaries. V1 reminders must appear inside the authenticated application only; email, web push, and mobile push delivery are not included.
- REQ-013: Core workflows must use a mobile-first responsive layout that works comfortably on phones and desktop computers, avoids horizontal page scrolling at supported widths, provides clear empty/loading/error states, and is operable by keyboard with visible focus states.
- REQ-014: Monetary calculations must use fixed-precision values rather than binary floating-point arithmetic, and date calculations must use the user's configured time zone.
- REQ-015: Destructive actions must require confirmation, authentication credentials must be stored securely, and secrets must not be committed to the repository or exposed to the browser.
- REQ-016: Production dependencies must be necessary, license-compatible, broadly trusted or independently auditable, actively maintained, and on supported stable releases. Experimental, abandoned, end-of-life, or single-maintainer packages without a documented replacement strategy must not be used for critical functionality.
- REQ-017: Dependency versions must be reproducibly locked and continuously checked for known vulnerabilities, end-of-life status, and available security updates. Releases must not ship with unmitigated known critical or high-severity vulnerabilities.
- REQ-018: Moola must remain responsive on modest self-hosted hardware and mid-tier mobile devices. Expensive data access must be bounded, paginated where appropriate, and backed by measured performance budgets rather than subjective claims of being fast.
- REQ-019: The installation administrator must be able to create additional user accounts, add or remove them from the household, and grant or revoke their permissions. Public self-registration and member-created accounts are not supported in v1.
- REQ-020: V1 authentication must use email address and password credentials. Email addresses must be normalized and unique, passwords must be processed only server-side using a supported password-hashing algorithm with per-password salts, and authenticated sessions must use secure server-managed controls.
- REQ-021: Subcategory budgeting must be zero-based: expected income for a budget period minus all subcategory allocations must equal zero before the budget is finalized. The interface must continuously show income, assigned amount, and amount left to assign; overspending changes category availability but does not silently rewrite the approved allocation.
- REQ-022: Rollover must be configurable independently for each subcategory. When enabled, the subcategory's positive unused or negative overspent ending balance becomes its next-period starting balance; when disabled, its next-period starting balance is zero. Changing the setting applies to future period transitions and must not silently rewrite closed historical budgets. Rollover never counts as new income or changes the zero-based requirement to allocate all current-period expected income.
- REQ-023: Permissions must follow least privilege and be configurable by the administrator per user for viewing and managing budgets, transactions, debts, net worth, subscriptions, household users, and installation settings. Every permission must be enforced on the server for both page and API access; hiding a user-interface control alone is insufficient.
- REQ-024: Moola must use SQLite as its embedded relational database, stored on persistent local SSD-backed storage mounted into the application container. Schema changes must use ordered, versioned migrations with recorded schema versions. The deployment must provide documented, consistent backup and restore commands and create a backup before automatic upgrade migrations.

## Acceptance Criteria

- AC-001: An unauthenticated visitor attempting to open any financial page or API operation is sent to sign in and receives no financial data; authenticated users can access only their own records and records belonging to households where they are current members. (REQ-002, REQ-015, REQ-019)
- AC-002: Given a budget start day of 25, the September 2026 period is 25 August through 24 September; selecting day 31 uses the final calendar day in shorter months without skipping a period. (REQ-004, REQ-014)
- AC-003: An authorized user can change the installation currency, and monetary formatting updates consistently throughout the application without changing stored numeric amounts or silently converting historical values. No record can be assigned a different currency. (REQ-003, REQ-014)
- AC-004: For a subcategory budgeted at 1,000 with qualifying expenses of 625.50, the subcategory displays 1,000 budgeted, 625.50 spent, and 374.50 remaining; each main-group roll-up and the whole-budget total reconcile exactly with their subcategories. (REQ-005, REQ-007, REQ-014)
- AC-005: Creating, editing, recategorizing, or deleting a transaction immediately produces the correct current-period dashboard and budget totals. (REQ-006, REQ-007, REQ-008)
- AC-006: The dashboard shows the active period and its budget totals, recent transactions, the next subscription charges, total outstanding debt, and current net worth, with useful empty states when data is absent. (REQ-008, REQ-013)
- AC-007: A user can create a debt, record a payment, and see its balance, payment history, estimated payoff date, remaining payment count, and estimated interest update. Projection assumptions are visible, zero-interest debt is supported, and a planned payment that does not reduce principal produces a clear warning instead of a misleading payoff date. (REQ-009, REQ-014)
- AC-008: A user can manually add and update asset and liability balances, see net worth equal their summed assets minus summed liabilities, and view at least two dated historical snapshots in chronological order. Transaction activity does not silently alter these balances. (REQ-010, REQ-014)
- AC-009: Monthly, annual, and custom recurring subscriptions appear on the correct dates in the in-app upcoming list and calendar across month-end and year-end boundaries; due and upcoming charges are visibly identified, inactive subscriptions do not appear as upcoming, and no email or push notification is sent. (REQ-011, REQ-012, REQ-014)
- AC-010: The budget, dashboard, debt, net-worth, subscription, and authentication workflows are usable without horizontal page scrolling at 360 px and 1280 px viewport widths, expose touch-friendly controls on mobile, and support keyboard navigation with visible focus. (REQ-013)
- AC-011: A documented Docker Compose deployment can be started on a clean host with 1 CPU core and 1 GB available RAM, passes its health checks without sustained memory exhaustion, retains users and financial records after container recreation, and requires deployment secrets to be provided outside version control. (REQ-001, REQ-015)
- AC-012: The release produces a dependency inventory and software bill of materials, uses a committed lockfile with reproducible installation, and passes automated vulnerability and unsupported-version checks with no unmitigated known critical or high-severity findings. Each critical direct dependency has a recorded purpose, support status, license, and replacement path. (REQ-016, REQ-017)
- AC-013: Dependency and container-image security checks run on every pull request and on a scheduled basis. A detected end-of-life component or critical/high vulnerability blocks the next release until it is upgraded, replaced, removed, or explicitly mitigated and risk-accepted in writing. (REQ-016, REQ-017)
- AC-014: On the 1-CPU/1-GB-RAM home-server reference deployment with SSD-backed storage and a representative dataset of 10,000 transactions, common authenticated read operations complete within 500 ms at p95 after warm-up, and primary mobile pages meet p75 field-equivalent budgets of 2.5 seconds LCP, 200 ms INP, and 0.1 CLS. The test profile, results, and regressions are recorded for releases. (REQ-018)
- AC-015: The administrator can create a household user account, add it to the household, and later remove or disable it. The new user can access only the household capabilities explicitly granted; a non-member, disabled user, or removed member cannot access household data. (REQ-002, REQ-019, REQ-023)
- AC-016: A registered user can sign in with the correct email address and password and sign out again. An incorrect email or password returns the same generic failure, repeated failed attempts are rate-limited, stored credentials never contain plaintext passwords, and signing out invalidates the session. (REQ-002, REQ-015, REQ-020)
- AC-017: Debt payoff projections match independently calculated amortization fixtures for zero-interest, ordinary-interest, extra-payment, and payment-below-interest scenarios, with monetary rounding performed consistently at each documented boundary. (REQ-009, REQ-014)
- AC-018: A new budget displays exactly the Essentials, Guilt-Free Spend, and Future main groups. Given expected income of 10,000, it cannot be finalized while subcategory allocations total 9,500 or 10,500, clearly showing 500 left to assign or 500 over-assigned respectively; it can be finalized when allocations total exactly 10,000. (REQ-005, REQ-021)
- AC-019: For a rollover-enabled subcategory, an ending balance of positive 300 or negative 125 becomes the next period's corresponding starting balance. For a rollover-disabled subcategory, either ending balance produces a zero starting balance. With expected income of 10,000, current-period allocations must still total exactly 10,000, and available equals starting balance plus current allocation minus current spending. Changing the option does not alter already closed periods. (REQ-007, REQ-021, REQ-022)
- AC-020: Given one view-only user and one user granted transaction-management permission, the view-only user cannot create, edit, or delete transactions through either the interface or direct API requests, while the permitted user can. Only an administrator with user-management permission can create accounts or change permissions, and every permission change takes effect no later than the user's next request. (REQ-002, REQ-019, REQ-023)
- AC-021: The Docker Compose deployment stores the SQLite database outside the container lifecycle. A fixture database from the previous supported release is backed up, migrated exactly once to the current schema without data loss, survives container recreation, and can be restored using the documented command. Startup fails safely with an actionable error rather than partially applying an invalid migration. (REQ-001, REQ-024)

## Out of Scope

- Direct bank connections, open-banking feeds, and automatic transaction synchronization.
- CSV, spreadsheet, or financial-file transaction import in v1.
- Investment trading, tax preparation, invoicing, and double-entry accounting.
- Business accounting, business finance, payroll, invoicing, tax, and organization administration workflows.
- Kubernetes, clustering, horizontal scaling, multi-region deployment, high availability, and enterprise identity or compliance features.
- Foreign-exchange conversion or mixed-currency portfolios in the first release.
- Per-user, per-household, or per-record currency overrides.
- Public self-registration and account creation by non-administrators.
- External database servers, database clustering, and database files hosted on network filesystems in v1.
- Native iOS or Android applications; the responsive web application is the initial client.
- Email, web-push, or mobile-push subscription reminders in v1.
- Financial advice, credit scoring, or automated debt-refinancing recommendations.
- Transaction-derived net-worth balances and external asset-price or account synchronization in v1.

## Open Questions

None. The product requirements are ready for review; implementation remains blocked until the specification and technical plan are explicitly approved.
