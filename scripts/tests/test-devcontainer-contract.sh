#!/usr/bin/env bash
#
# Smoke contract for the PHP devcontainer lane.
#
# This intentionally avoids building the image. The publish workflow owns the
# real build, while this local test verifies that the repo contains the files,
# toolchain pins, and GHCR publication wiring contributors need before the
# expensive container build is allowed to run.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

require_file() {
    [[ -f "$1" ]] || fail "missing $1"
}

require_grep() {
    local pattern="$1"
    local file="$2"
    local note="$3"
    grep -Eq "$pattern" "$file" || fail "$note ($file)"
}

require_file .devcontainer/devcontainer.json
require_file .devcontainer/Containerfile
require_file .github/workflows/devcontainer-publish.yml

python3 -m json.tool .devcontainer/devcontainer.json >/dev/null

require_grep 'parkhub-php full local CI/CD' .devcontainer/devcontainer.json 'devcontainer must identify the PHP local CI/CD image'
require_grep 'ghcr\.io/nash87/parkhub-php-devcontainer:latest' .devcontainer/devcontainer.json 'devcontainer must default to the prebuilt GHCR image'
require_grep '/workspaces/parkhub-php' .devcontainer/devcontainer.json 'devcontainer must mount the expected PHP workspace'
require_grep 'mise install' .devcontainer/devcontainer.json 'devcontainer must bootstrap mise-managed local CI tools'
require_grep 'composer install' .devcontainer/devcontainer.json 'devcontainer must bootstrap Laravel dependencies'
require_grep 'npm ci --prefix parkhub-web' .devcontainer/devcontainer.json 'devcontainer must bootstrap the active React/Astro app'
require_grep 'fop-local-ci\.sh --profile pr --post-status' .devcontainer/devcontainer.json 'devcontainer must advertise the local attestation command'

require_grep '^FROM php:8\.4-cli-bookworm' .devcontainer/Containerfile 'Containerfile must pin PHP 8.4'
require_grep 'ARG NODE_MAJOR=22' .devcontainer/Containerfile 'Containerfile must pin Node 22'
require_grep 'docker-php-ext-install' .devcontainer/Containerfile 'Containerfile must install PHP extensions'
require_grep 'pdo_sqlite' .devcontainer/Containerfile 'Containerfile must support sqlite test databases'
require_grep 'pdo_mysql' .devcontainer/Containerfile 'Containerfile must support mysql-compatible local runs'
require_grep 'install\.mise\.jdx\.dev' .devcontainer/Containerfile 'Containerfile must install mise for repo-pinned tools'
require_grep 'ACTIONLINT_VERSION=1\.7\.12' .devcontainer/Containerfile 'Containerfile must pin actionlint'
require_grep 'HELM_VERSION=v3\.18\.4' .devcontainer/Containerfile 'Containerfile must pin Helm'
require_grep 'docker\.io' .devcontainer/Containerfile 'Containerfile must include docker CLI for compose validation'

require_grep 'name: Publish dev container image' .github/workflows/devcontainer-publish.yml 'workflow must publish a devcontainer image'
require_grep "\\.devcontainer/\\*\\*" .github/workflows/devcontainer-publish.yml 'workflow must run on devcontainer changes'
require_grep 'ghcr\.io' .github/workflows/devcontainer-publish.yml 'workflow must publish to GHCR'
require_grep 'docker/build-push-action@' .github/workflows/devcontainer-publish.yml 'workflow must build and push via build-push-action'
require_grep 'cosign sign' .github/workflows/devcontainer-publish.yml 'workflow must sign the image'
require_grep "cron: '30 4 \\* \\* 0'" .github/workflows/devcontainer-publish.yml 'workflow must refresh weekly'

printf 'ParkHub PHP devcontainer contract OK.\n'
