#!/usr/bin/env bash
set -euo pipefail

python3 - <<'PY'
from pathlib import Path
import os
import sys

try:
    import yaml
except ModuleNotFoundError:
    yaml = None
    _msg = (
        "PyYAML is not installed; workflow YAML parse validation cannot run. "
        "Install with: python3 -m pip install --user PyYAML"
    )
    if os.environ.get("CI") or os.environ.get("GITHUB_ACTIONS"):
        # In CI the YAML half of this gate is mandatory — fail cleanly
        # (no traceback) instead of silently weakening the policy.
        print(f"FAIL: {_msg}", file=sys.stderr)
        raise SystemExit(1)
    print(f"WARN: {_msg} — continuing with the text-pattern half only.", file=sys.stderr)

repo = Path(".")
errors = []

forbidden = {
    "wolfi-base" + ":latest": "mutable Wolfi base image tag",
    "advisory until " + "first signed": "advisory attestation verification",
}

# Scoped to workflow manifests only: prose (.md docs) legitimately discusses
# the marker, and only workflow files can actually soften a gate with it.
forbidden_workflow_only = {
    "continue-on-error" + ": true": "non-blocking release or security gate",
}


def is_workflow_file(path: Path) -> bool:
    parts = path.parts
    return (
        len(parts) >= 2
        and parts[0] in {".github", ".gitea"}
        and "workflows" in parts
        and path.suffix in {".yml", ".yaml"}
    )

skip_dirs = {
    ".git",
    ".fop",
    ".claude",
    "node_modules",
    "vendor",
    "storage",
    "bootstrap/cache",
    "parkhub-web/node_modules",
}
text_exts = {
    ".dockerfile",
    ".env",
    ".json",
    ".md",
    ".sh",
    ".toml",
    ".yaml",
    ".yml",
}
top_level_files = {
    "Containerfile",
    "Dockerfile",
    "Dockerfile.debian",
    "docker-compose.yml",
    "docker-compose.yaml",
    "docker-compose.test.yml",
    "fly.toml",
    "koyeb.yaml",
    "render.yaml",
}


def skipped(path: Path) -> bool:
    parts = path.parts
    for item in skip_dirs:
        item_parts = tuple(item.split("/"))
        if any(tuple(parts[index : index + len(item_parts)]) == item_parts for index in range(len(parts))):
            return True
    return False


def is_policy_surface(path: Path) -> bool:
    if skipped(path):
        return False
    if str(path) == "scripts/check-release-supply-chain-policy.sh":
        return False
    if path.name in top_level_files:
        return True
    if path.name.lower().startswith(("dockerfile", "containerfile")):
        return True
    if path.suffix.lower() in text_exts:
        return True
    return False


def read_text(path: Path) -> str:
    try:
        return path.read_text(errors="replace")
    except OSError as exc:
        errors.append(f"{path}: cannot read policy surface: {exc}")
        return ""


def iter_policy_files(root: Path):
    for dirpath, dirnames, filenames in os.walk(root):
        current = Path(dirpath)
        dirnames[:] = [name for name in dirnames if not skipped(current / name)]
        for filename in filenames:
            path = current / filename
            if path.is_file() and is_policy_surface(path):
                yield path


# Per-line scan with a documented exemption escape hatch: a line carrying
# `policy-exempt:` (e.g. `continue-on-error: true # policy-exempt:
# reporting-only ...`) is deliberately excluded. Use only for reporting /
# publication steps that must never gate; actual security/release gates
# stay forbidden from soft-failing.
for path in sorted(iter_policy_files(repo)):
    for lineno, line in enumerate(read_text(path).splitlines(), start=1):
        if "policy-exempt:" in line:
            continue
        for pattern, description in forbidden.items():
            if pattern in line:
                errors.append(f"{path}:{lineno}: contains {description}: {pattern}")
        if is_workflow_file(path):
            for pattern, description in forbidden_workflow_only.items():
                if pattern in line:
                    errors.append(f"{path}:{lineno}: contains {description}: {pattern}")

workflow = Path(".github/workflows/docker-publish.yml")
if not workflow.is_file():
    errors.append(".github/workflows/docker-publish.yml is required")
else:
    text = read_text(workflow)
    required_snippets = {
        "id-token: write": "docker publish must grant OIDC id-token for keyless signing",
        "attestations: write": "docker publish must grant attestations write permission",
        "provenance: mode=max": "docker publish must request max provenance",
        "sbom: true": "docker publish must request SBOM generation",
        "attest-build-provenance@": "docker publish must attest build provenance",
        "cosign sign --yes": "docker publish must cosign the immutable image digest",
    }
    for snippet, description in required_snippets.items():
        if snippet not in text:
            errors.append(f"{workflow}: {description}")

verify = Path(".github/workflows/cosign-verify.yml")
if not verify.is_file():
    errors.append(".github/workflows/cosign-verify.yml is required")
else:
    text = read_text(verify)
    for snippet in ("cosign verify", "verify-attestation", "--type spdxjson"):
        if snippet not in text:
            errors.append(f"{verify}: missing {snippet} verification")

if yaml is not None:
    try:
        for workflow_path in sorted(Path(".github/workflows").glob("*.yml")) + sorted(Path(".github/workflows").glob("*.yaml")):
            yaml.safe_load(workflow_path.read_text())
        for workflow_path in sorted(Path(".gitea/workflows").glob("*.yml")) + sorted(Path(".gitea/workflows").glob("*.yaml")):
            yaml.safe_load(workflow_path.read_text())
    except yaml.YAMLError as exc:
        errors.append(f"workflow YAML parse failed: {exc}")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

print("Release supply-chain policy OK")
PY
