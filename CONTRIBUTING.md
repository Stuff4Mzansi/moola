# Contributing

This repository uses spec-driven development for human and AI contributions. Read [AGENTS.md](AGENTS.md) before making changes.

For any behavioral or implementation change, create or select a package in `specs/`, approve its requirements and technical plan, implement its traceable tasks, and run:

```sh
python scripts/spec_guard.py check --all
```

To enable the local pre-commit gate after cloning or initializing Git, run this command on Windows, macOS, or Linux:

```sh
python scripts/spec_guard.py install
```

Pull requests are checked by the same guard in CI.
