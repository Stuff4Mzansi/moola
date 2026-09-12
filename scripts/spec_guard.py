#!/usr/bin/env python3
"""Create and validate repository specification packages using only the stdlib."""

from __future__ import annotations

import argparse
import datetime as dt
import re
import stat
import subprocess
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SPECS = ROOT / "specs"
TEMPLATES = ROOT / ".spec-driven" / "templates"
ACTIVE = ROOT / ".spec-driven" / "active-spec"
PACKAGE_RE = re.compile(r"^(\d{4})-([a-z0-9]+(?:-[a-z0-9]+)*)$")
STATUS_RE = re.compile(r"(?mi)^Status:\s*(Draft|Approved|Complete)\s*$")
AC_RE = re.compile(r"\bAC-\d{3}\b")
REQ_RE = re.compile(r"\bREQ-\d{3}\b")

IMPLEMENTATION_NAMES = {
    "package.json", "package-lock.json", "pnpm-lock.yaml", "yarn.lock",
    "pyproject.toml", "poetry.lock", "requirements.txt", "cargo.toml",
    "cargo.lock", "go.mod", "go.sum", "dockerfile", "compose.yaml",
    "docker-compose.yml", "terraform.lock.hcl",
}
IMPLEMENTATION_SUFFIXES = {
    ".c", ".cc", ".cpp", ".cs", ".css", ".dart", ".ex", ".exs",
    ".go", ".graphql", ".h", ".hpp", ".html", ".java", ".js",
    ".json", ".jsx", ".kt", ".kts", ".lua", ".php", ".prisma",
    ".proto", ".ps1", ".py", ".rb", ".rs", ".scss", ".sh", ".sql",
    ".svelte", ".swift", ".tf", ".toml", ".ts", ".tsx", ".vue",
    ".xml", ".yaml", ".yml",
}
WORKFLOW_PREFIXES = (
    ".spec-driven/", ".cursor/", ".clinerules/", ".roo/",
    ".githooks/", "specs/", "scripts/spec_guard.py",
    "scripts/install-spec-guard.ps1",
)
INSTRUCTION_FILES = {
    "AGENTS.md", "CLAUDE.md", "GEMINI.md", ".windsurfrules", "CONTRIBUTING.md",
    ".github/copilot-instructions.md", ".github/pull_request_template.md",
    ".github/workflows/spec-driven.yml",
}


class GuardError(Exception):
    pass


def run_git(*args: str) -> list[str]:
    result = subprocess.run(
        ["git", *args], cwd=ROOT, text=True, encoding="utf-8",
        errors="replace", capture_output=True,
    )
    if result.returncode:
        raise GuardError(result.stderr.strip() or "Git command failed")
    return [line.strip() for line in result.stdout.splitlines() if line.strip()]


def status_of(text: str, path: Path) -> str:
    match = STATUS_RE.search(text)
    if not match:
        raise GuardError(f"{path.relative_to(ROOT)}: missing valid Status")
    return match.group(1)


def require_headings(text: str, path: Path, headings: tuple[str, ...]) -> None:
    missing = [heading for heading in headings if f"## {heading}" not in text]
    if missing:
        raise GuardError(f"{path.relative_to(ROOT)}: missing sections: {', '.join(missing)}")


def validate_package(package: Path, implementation_gate: bool) -> list[str]:
    errors: list[str] = []
    if not PACKAGE_RE.fullmatch(package.name):
        return [f"{package.relative_to(ROOT)}: expected NNNN-short-slug directory name"]

    paths = {name: package / name for name in ("spec.md", "plan.md", "tasks.md")}
    for path in paths.values():
        if not path.is_file():
            errors.append(f"{path.relative_to(ROOT)}: required file is missing")
    if errors:
        return errors

    texts = {name: path.read_text(encoding="utf-8") for name, path in paths.items()}
    try:
        require_headings(texts["spec.md"], paths["spec.md"], ("Context", "Scope", "Requirements", "Acceptance Criteria", "Out of Scope", "Open Questions"))
        require_headings(texts["plan.md"], paths["plan.md"], ("Approach", "Affected Components", "Interfaces and Data", "Risks and Mitigations", "Verification", "Decision Log"))
        require_headings(texts["tasks.md"], paths["tasks.md"], ("Tasks", "Handoff Notes"))
        statuses = {name: status_of(text, paths[name]) for name, text in texts.items()}
    except GuardError as exc:
        errors.append(str(exc))
        return errors

    if not REQ_RE.search(texts["spec.md"]):
        errors.append(f"{paths['spec.md'].relative_to(ROOT)}: add at least one REQ-NNN")
    spec_acs = set(AC_RE.findall(texts["spec.md"]))
    if not spec_acs:
        errors.append(f"{paths['spec.md'].relative_to(ROOT)}: add at least one AC-NNN")
    task_lines = [line for line in texts["tasks.md"].splitlines() if re.match(r"\s*- \[[ xX]\] T-\d{3}:", line)]
    if not task_lines:
        errors.append(f"{paths['tasks.md'].relative_to(ROOT)}: add at least one T-NNN checklist item")
    for line in task_lines:
        refs = set(AC_RE.findall(line))
        if not refs:
            errors.append(f"{paths['tasks.md'].relative_to(ROOT)}: task lacks an AC-NNN reference: {line.strip()}")
        elif refs - spec_acs:
            errors.append(f"{paths['tasks.md'].relative_to(ROOT)}: task references unknown criteria: {', '.join(sorted(refs - spec_acs))}")
    plan_acs = set(AC_RE.findall(texts["plan.md"]))
    if spec_acs - plan_acs:
        errors.append(f"{paths['plan.md'].relative_to(ROOT)}: verification does not cover: {', '.join(sorted(spec_acs - plan_acs))}")

    if implementation_gate and (statuses["spec.md"] not in {"Approved", "Complete"} or statuses["plan.md"] not in {"Approved", "Complete"}):
        errors.append(f"{package.relative_to(ROOT)}: implementation requires Approved or Complete spec.md and plan.md")
    if "Complete" in statuses.values():
        if set(statuses.values()) != {"Complete"}:
            errors.append(f"{package.relative_to(ROOT)}: Complete status must be set in all three documents")
        unchecked = [line for line in task_lines if re.match(r"\s*- \[ \]", line)]
        if unchecked:
            errors.append(f"{paths['tasks.md'].relative_to(ROOT)}: Complete package has unchecked tasks")
    return errors


def is_implementation(path: str) -> bool:
    normalized = path.replace("\\", "/")
    if normalized in INSTRUCTION_FILES or normalized.startswith(WORKFLOW_PREFIXES):
        return False
    item = Path(normalized)
    return item.name.lower() in IMPLEMENTATION_NAMES or item.suffix.lower() in IMPLEMENTATION_SUFFIXES


def changed_files(args: argparse.Namespace) -> list[str]:
    if args.staged:
        return run_git("diff", "--cached", "--name-only", "--diff-filter=ACMR")
    if args.base:
        merge_base = run_git("merge-base", args.base, "HEAD")
        if not merge_base:
            raise GuardError(f"No merge base found for {args.base}")
        return run_git("diff", "--name-only", "--diff-filter=ACMR", f"{merge_base[0]}...HEAD")
    return []


def active_package() -> Path | None:
    if not ACTIVE.is_file():
        return None
    value = ACTIVE.read_text(encoding="utf-8").strip().replace("\\", "/")
    if not value:
        return None
    candidate = (ROOT / value).resolve()
    try:
        candidate.relative_to(SPECS.resolve())
    except ValueError as exc:
        raise GuardError(".spec-driven/active-spec must point inside specs/") from exc
    return candidate


def check(args: argparse.Namespace) -> int:
    errors: list[str] = []
    packages = sorted(path for path in SPECS.iterdir() if path.is_dir()) if SPECS.exists() else []
    implementation_gate = False

    if args.all:
        selected = packages
    else:
        files = changed_files(args)
        implementation_gate = any(is_implementation(path) for path in files)
        changed_names = {
            parts[1] for path in files
            if len(parts := path.replace("\\", "/").split("/")) >= 3 and parts[0] == "specs"
        }
        selected = [SPECS / name for name in sorted(changed_names)]
        if implementation_gate and not selected:
            active = active_package()
            if active:
                selected = [active]
            else:
                errors.append("Implementation changed without a changed spec package or .spec-driven/active-spec")

    if args.all and not packages:
        print("Spec guard passed (no spec packages yet).")
        return 0
    for package in selected:
        if not package.is_dir():
            errors.append(f"{package.relative_to(ROOT)}: spec package does not exist")
        else:
            errors.extend(validate_package(package, implementation_gate))

    if errors:
        print("Spec guard failed:", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1
    print(f"Spec guard passed ({len(selected)} package(s) checked).")
    return 0


def create(args: argparse.Namespace) -> int:
    slug = args.slug.strip().lower()
    if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", slug):
        raise GuardError("Slug must contain lowercase letters, digits, and single hyphens")
    existing_ids = [int(match.group(1)) for path in SPECS.iterdir() if path.is_dir() and (match := PACKAGE_RE.fullmatch(path.name))]
    package = SPECS / f"{max(existing_ids, default=0) + 1:04d}-{slug}"
    if package.exists():
        raise GuardError(f"{package.relative_to(ROOT)} already exists")
    package.mkdir(parents=True)
    today = dt.date.today().isoformat()
    for template in TEMPLATES.glob("*.md"):
        content = template.read_text(encoding="utf-8").replace("{{TITLE}}", args.title).replace("{{DATE}}", today)
        (package / template.name).write_text(content, encoding="utf-8")
    ACTIVE.write_text(f"{package.relative_to(ROOT).as_posix()}\n", encoding="utf-8")
    print(f"Created {package.relative_to(ROOT)} and set it active.")
    return 0


def install(_args: argparse.Namespace) -> int:
    if not (ROOT / ".git").exists():
        raise GuardError("No .git directory found. Initialize or clone the repository first.")
    hook = ROOT / ".githooks" / "pre-commit"
    if not hook.is_file():
        raise GuardError(".githooks/pre-commit is missing")
    hook.chmod(hook.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)
    run_git("config", "core.hooksPath", ".githooks")
    print("Spec guard installed as the repository pre-commit hook.")
    return 0


def parser() -> argparse.ArgumentParser:
    root = argparse.ArgumentParser(description=__doc__)
    commands = root.add_subparsers(dest="command", required=True)
    new = commands.add_parser("new", help="create the next numbered spec package")
    new.add_argument("slug")
    new.add_argument("--title", required=True)
    new.set_defaults(func=create)
    installer = commands.add_parser("install", help="install the cross-platform Git pre-commit hook")
    installer.set_defaults(func=install)
    validate = commands.add_parser("check", help="validate specs and implementation linkage")
    mode = validate.add_mutually_exclusive_group(required=True)
    mode.add_argument("--all", action="store_true", help="validate every existing package")
    mode.add_argument("--staged", action="store_true", help="validate staged Git changes")
    mode.add_argument("--base", help="validate changes since the merge base with this Git ref")
    validate.set_defaults(func=check)
    return root


def main() -> int:
    args = parser().parse_args()
    try:
        return args.func(args)
    except GuardError as exc:
        print(f"Spec guard failed: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
