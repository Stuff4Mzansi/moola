# Spec-driven development is mandatory

These instructions apply to every human and AI coding agent working in this repository.

## Non-negotiable gate

Do not change product code, tests, schemas, infrastructure, dependencies, or externally visible behavior until an approved spec package exists under `specs/<id>-<slug>/`.

A spec package contains:

- `spec.md` — problem, scope, numbered requirements, and testable acceptance criteria.
- `plan.md` — technical approach, affected components, interfaces/data changes, risks, and verification.
- `tasks.md` — ordered implementation checklist, with every task linked to at least one acceptance criterion.

Both `spec.md` and `plan.md` must contain `Status: Approved`. Only mark them approved when the user/reviewer explicitly approves them, or when the initiating request explicitly asks the agent to carry the work from specification through implementation without a separate review stop.

Documentation-only edits, typo fixes, comment-only edits, and changes to this workflow are exempt when they do not alter runtime behavior, interfaces, dependencies, security, data, deployment, or user-visible outcomes.

## Required workflow

1. Read this file and any nearer `AGENTS.md` before editing.
2. Search `specs/` for an existing package that covers the request.
3. If none exists, copy `.spec-driven/templates/` to a new `specs/<id>-<slug>/` package. Use the next four-digit ID.
4. Resolve material ambiguity in `spec.md`. Give requirements IDs (`REQ-001`) and acceptance criteria IDs (`AC-001`).
5. Write `plan.md` only after the requirements are coherent. Link decisions and verification steps back to acceptance criteria.
6. Obtain the approval described above. Never silently treat a draft as approved.
7. Write `tasks.md`, keeping tasks small, ordered, and traceable to acceptance criteria.
8. Implement only what the approved spec covers. If scope changes, update the spec and plan before continuing.
9. Check off a task only after its implementation and relevant verification pass.
10. Run `python scripts/spec_guard.py check --all` plus project tests before handoff. Set all three documents to `Status: Complete` only when every acceptance criterion is satisfied and every task is checked.

## While working

- Treat the spec as the source of truth; chat history and implementation guesses do not override it.
- Record consequential choices in `plan.md` under Decision Log.
- Add newly discovered work to `tasks.md`; do not hide it in prose or silently expand scope.
- Preserve unrelated user changes.
- Report deviations, failed checks, and unchecked tasks at handoff.
- Never weaken or bypass `spec_guard.py` merely to make a check pass.

## Commands

```text
python scripts/spec_guard.py new <slug> --title "Short title"
python scripts/spec_guard.py install
python scripts/spec_guard.py check --all
python scripts/spec_guard.py check --staged
python scripts/spec_guard.py check --base <git-ref>
```

The detailed format and lifecycle are documented in [specs/README.md](specs/README.md).
