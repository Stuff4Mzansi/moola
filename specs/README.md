# Specification packages

Every material change lives in one directory named `NNNN-short-slug`. The package is a durable record of why the change exists, what is accepted, how it will be built, and what remains.

## Lifecycle

`Draft` → `Approved` → `Complete`

- `Draft`: requirements or design are still being resolved. Implementation is blocked.
- `Approved`: implementation may proceed within the documented scope.
- `Complete`: acceptance criteria are verified and every task is checked.

Approval must come from the user/reviewer, unless the initiating request explicitly authorizes an end-to-end specification-and-implementation pass. An agent must not infer approval merely because it wrote the documents.

## Create a package

```sh
python scripts/spec_guard.py new account-export --title "Account data export"
```

The command chooses the next four-digit ID, copies the templates, and records the new package in `.spec-driven/active-spec`. Work on one active package at a time. A branch may commit its package before its code; the active pointer lets the staged pre-commit check retain traceability.

## Required traceability

- Requirements use `REQ-001`, `REQ-002`, and so on.
- Acceptance criteria use `AC-001`, `AC-002`, and so on.
- Each task contains at least one acceptance criterion ID.
- Verification in `plan.md` maps checks to acceptance criteria.

If implementation reveals a new requirement or changes scope, return the affected documents to `Draft`, revise them, and obtain approval again before continuing.

## Enforcement

`scripts/spec_guard.py` validates package structure and blocks implementation diffs that lack an approved active or changed spec package. Tooling/configuration and prose-only files are excluded; source, tests, schemas, migrations, infrastructure, and dependency manifests are treated as implementation.

The pre-commit hook checks staged changes. CI checks the complete pull-request diff. Neither proves that the design is good; review still owns correctness and approval.

Install the local hook on Windows, macOS, or Linux with:

```sh
python scripts/spec_guard.py install
```
