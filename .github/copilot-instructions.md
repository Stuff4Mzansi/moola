# Mandatory spec-driven workflow

Follow `/AGENTS.md` for every coding task. Before proposing or applying implementation changes, locate or create the matching package in `/specs`, ensure `spec.md` and `plan.md` are approved, and make every implementation task traceable to an acceptance criterion. Keep the package synchronized with scope changes and run `python scripts/spec_guard.py check --all` before declaring the work complete.
