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
    @echo "VERSION file: $(cat VERSION)"
    @echo "parkhub-web: $(jq -r .version parkhub-web/package.json)"
    @echo "Last tag: $(git describe --tags --abbrev=0)"
    @echo ""
    @echo "Commits since last tag:"
    @git log --oneline "$(git describe --tags --abbrev=0)..HEAD" | head -20
    @echo ""
    @echo "Generated CHANGELOG section (dry-run, what cliff would write):"
    @git cliff --unreleased --strip header 2>/dev/null || echo "(install git-cliff to preview)"

[doc("regenerate CHANGELOG.md from git history (cliff.toml + conventional commits)")]
changelog:
    git cliff --output CHANGELOG.md
    @echo "✓ CHANGELOG.md regenerated. Review the diff before committing."

[doc("cut a new tag — bumps VERSION + parkhub-web/package.json + tags + pushes")]
release-tag VERSION:
    #!/usr/bin/env bash
    set -euo pipefail
    if [[ ! "{{VERSION}}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      echo "VERSION must be semver, e.g. 5.0.4 (got: {{VERSION}})" >&2
      exit 1
    fi
    if [[ -n "$(git status --porcelain)" ]]; then
      echo "working tree not clean — commit or stash first" >&2
      exit 1
    fi
    echo "▶ bumping versions to {{VERSION}}"
    echo "{{VERSION}}" > VERSION
    jq '.version = "{{VERSION}}"' parkhub-web/package.json > parkhub-web/package.json.tmp && mv parkhub-web/package.json.tmp parkhub-web/package.json
    echo "▶ regenerating CHANGELOG"
    git cliff -t v{{VERSION}} --output CHANGELOG.md
    git add VERSION parkhub-web/package.json CHANGELOG.md
    git commit -m "chore(release): v{{VERSION}}"
    git tag -a v{{VERSION}} -m "Release v{{VERSION}}"
    @echo "✓ tagged v{{VERSION}}. Review: git show v{{VERSION}}"
    @echo "  Push when ready: git push github main && git push github v{{VERSION}}"

# ---------------------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------------------
[doc("remove vendor + node_modules + parkhub-web/dist + bootstrap artifacts")]
clean:
    rm -rf vendor parkhub-web/node_modules parkhub-web/dist node_modules
    rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/logs/*.log
