# parkhub-php task runner — `just` (MIT, casey/just).
#
# `just` shows all recipes. Run any with `just <name>`.

set shell := ["bash", "-uc"]
set dotenv-load := false

# ---------------------------------------------------------------------------
# Default
# ---------------------------------------------------------------------------
default:
    @just --list --unsorted

# ---------------------------------------------------------------------------
# One-command bootstrap for fresh clones
# ---------------------------------------------------------------------------
[doc("install all dev tools (mise + composer + npm + lefthook)")]
bootstrap:
    @echo "▶ installing mise tools (php, node, composer, gates...)"
    mise install
    @echo "▶ wiring lefthook git hooks"
    lefthook install --force
    @echo "▶ installing composer deps"
    composer install --no-interaction --prefer-dist
    @echo "▶ installing parkhub-web dependencies"
    cd parkhub-web && npm ci
    @echo "✓ ready — try: just dev"

# ---------------------------------------------------------------------------
# Day-to-day
# ---------------------------------------------------------------------------
[doc("php artisan serve + parkhub-web vite dev")]
dev:
    #!/usr/bin/env bash
    set -euo pipefail
    php artisan serve --host=127.0.0.1 --port=8000 &
    cd parkhub-web && npm run dev

[doc("astro dev server only")]
web-dev:
    cd parkhub-web && npm run dev

[doc("Laravel pail — live log tail")]
pail:
    php artisan pail

# ---------------------------------------------------------------------------
# Local CI gates — mirror lefthook pre-push
# ---------------------------------------------------------------------------
[doc("run all pre-push gates locally (the same lefthook fires on `git push`)")]
ci:
    lefthook run pre-push

[doc("the canonical local CI script — pint + phpstan + phpunit + vitest + audits")]
local-ci:
    bash .github/scripts/fop-local-ci.sh --profile pr

[doc("just the fast subset: pint + phpstan + lib tests")]
check:
    ./vendor/bin/pint --test
    ./vendor/bin/phpstan analyse --memory-limit=2G
    php artisan test --testsuite=Unit

# ---------------------------------------------------------------------------
# Formatting + linting
# ---------------------------------------------------------------------------
[doc("apply Pint (PHP) + dprint (md/json/yml/toml) + biome (parkhub-web)")]
fmt:
    ./vendor/bin/pint
    dprint fmt
    cd parkhub-web && [ -d node_modules/@biomejs/biome ] && npx biome check --write src/ || true

[doc("typos repo-wide (in-place auto-fix)")]
typos-fix:
    typos --write-changes

# ---------------------------------------------------------------------------
# Tests
# ---------------------------------------------------------------------------
[doc("PHPUnit feature + unit tests")]
test:
    php artisan test

[doc("vitest (parkhub-web) — only changed files since main")]
test-web:
    cd parkhub-web && npx vitest --run --changed

[doc("PHPStan static analysis — Larastan level 8")]
phpstan:
    ./vendor/bin/phpstan analyse --memory-limit=2G

# ---------------------------------------------------------------------------
# Security
# ---------------------------------------------------------------------------
[doc("composer audit (PHP advisories) + npm audit (web)")]
audit:
    composer audit --no-interaction
    cd parkhub-web && npm audit --omit=dev

[doc("trivy filesystem scan, CRITICAL+HIGH only")]
trivy:
    trivy fs --quiet --exit-code 1 \
      --scanners=vuln,misconfig \
      --severity=CRITICAL,HIGH \
      --skip-dirs=node_modules,vendor,parkhub-web/node_modules,storage \
      .

[doc("gitleaks scan on git history")]
gitleaks:
    gitleaks git --redact -v --no-banner

# ---------------------------------------------------------------------------
# Release plumbing
# ---------------------------------------------------------------------------
[doc("preview the next release — version surfaces + changelog dry-run")]
release-preview:
    @echo "package.json: $(jq -r .version package.json)"
    @echo "parkhub-web: $(jq -r .version parkhub-web/package.json)"
    @echo "Last tag: $(git describe --tags --abbrev=0)"
    @echo "Commits since last tag:"
    @git log --oneline "$(git describe --tags --abbrev=0)..HEAD" | head -20

# ---------------------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------------------
[doc("remove vendor + node_modules + parkhub-web/dist + bootstrap artifacts")]
clean:
    rm -rf vendor parkhub-web/node_modules parkhub-web/dist node_modules
    rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/logs/*.log
