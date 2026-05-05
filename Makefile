# parkhub-php — Local-first CI/CD entrypoints
#
# `make ci` is the canonical PR gate. It runs through fop so local work gets
# the same serialization, memory limits, and GitHub commit-status attestation
# path as the protected PR check. GitHub same-repo PRs should mostly verify
# the posted `fop/local-ci/pr` status; Gitea can run the fuller internal
# workflow mirror.
#
# Usage:
#   make ci         # fop local PR gate (no GitHub status post)
#   make ci-post    # fop local PR gate + post fop/local-ci/pr for GitHub
#   make full       # fop full profile: PR gate + mutation/e2e extras
#   make cd         # fop CD profile: full + release-oriented scans/smoke
#   make release-preflight # required gate before cutting/publishing a release
#   make ci-security # strict local OSS mirror of GitHub/Gitea security gates
#   make lint       # pint --test + phpstan (backend-quality + static-analysis jobs)
#   make test       # full backend PHPUnit suite — mirrors backend-tests
#   make drift      # bootstrap sqlite + regenerate openapi + fail on diff — mirrors openapi-drift.yml
#   make act        # run the actual .github/workflows locally via nektos/act
#   make pre-push   # alias for ci; run before `git push origin/github`
#
# Requires: php 8.4, composer v2, node 22, npm. CD/release-preflight also
# requires Trivy and Playwright Chromium. `act` is optional.

SHELL := bash
.SHELLFLAGS := -euo pipefail -c
MAKEFLAGS += --no-print-directory

.PHONY: help ci ci-post full cd release-preflight ci-security script-tests lint test static-analysis drift frontend act pre-push pre-push-report clean

help:
	@echo "parkhub-php local-first CI/CD"
	@echo ""
	@echo "  make ci         — fop local PR gate"
	@echo "  make ci-post    — fop local PR gate + post fop/local-ci/pr"
	@echo "  make full       — fop full profile"
	@echo "  make cd         — fop CD profile"
	@echo "  make release-preflight — required release gate (CD profile)"
	@echo "  make ci-security — strict local OSS security/workflow mirror"
	@echo "  make script-tests — shell script contract tests"
	@echo "  make lint       — pint --test (backend-quality)"
	@echo "  make static-analysis — phpstan (static-analysis job)"
	@echo "  make test       — full backend PHPUnit suite (backend-tests)"
	@echo "  make drift      — sqlite-backed openapi snapshot drift check"
	@echo "  make frontend   — npm ci + build (frontend job)"
	@echo "  make act        — run workflows via nektos/act (if installed)"
	@echo "  make pre-push   — alias for ci; run before git push"
	@echo "  make pre-push-report — verify current HEAD has a local-ci success report"

## Mirrors: backend-quality job (.github/workflows/ci.yml)
lint:
	composer validate --strict
	find app bootstrap config database routes tests -name '*.php' -print0 \
		| xargs -0 -n 50 php -l > /dev/null
	./vendor/bin/pint --test

## Mirrors: static-analysis job
static-analysis:
	scripts/ci/phpstan-analyse.sh --memory-limit=512M

## Mirrors: backend-tests job
test:
	DB_CONNECTION=sqlite DB_DATABASE=':memory:' ./scripts/ci/bootstrap-laravel.sh
	DB_CONNECTION=sqlite DB_DATABASE=':memory:' composer test

## Mirrors: frontend job
frontend:
	npm ci
	npm ci --prefix parkhub-web
	npm test --prefix parkhub-web
	npm run build

## Mirrors: openapi-drift.yml
drift:
	cp .env.example .env
	php artisan key:generate --force
	mkdir -p database
	touch database/database.sqlite
	php artisan migrate --graceful --force
	composer openapi:dump
	@if ! git diff --exit-code docs/openapi/php.json; then \
		echo "ERROR: docs/openapi/php.json drifted — run 'composer openapi:dump' and commit."; \
		exit 1; \
	fi
	@echo "OpenAPI snapshot in sync."

## Canonical local PR gate. GitHub branch protection expects ci-post on PR heads.
ci:
	.github/scripts/fop-local-ci.sh --profile pr

ci-post:
	.github/scripts/fop-local-ci.sh --profile pr --post-status

full:
	.github/scripts/fop-local-ci.sh --profile full

cd:
	.github/scripts/fop-local-ci.sh --profile cd

release-preflight: cd

## Lighthouse perf gate (CI/CD audit gap #2). GHA-only previously, missing
## locally — added so `make lighthouse` matches what GHA enforces. Skipped
## cleanly when @lhci/cli isn't installed (advisory like grype/zizmor).
lighthouse:
	@if ! command -v npx >/dev/null 2>&1; then \
		echo "npx not on PATH; skipping Lighthouse (advisory)."; \
		exit 0; \
	fi
	npm run build --prefix parkhub-web
	npx --no-install @lhci/cli@latest autorun --config=lighthouserc.json || \
		echo "@lhci/cli not installed; install with 'npm install -g @lhci/cli@latest' (advisory)."

## Mutation testing gate (CI/CD audit gap #3). Infection runs nightly on GHA
## but never locally — added so `make mutants` is callable from `make full`/`cd`
## or directly. Soft-gate (advisory) like infection.yml's continue-on-error.
mutants:
	@if [[ ! -x ./vendor/bin/infection ]]; then \
		echo "./vendor/bin/infection missing; run 'composer install' (advisory)."; \
		exit 0; \
	fi
	./vendor/bin/infection --threads=4 --no-progress || \
		echo "infection returned non-zero (advisory)."

ci-security:
	$(MAKE) script-tests
	.github/scripts/fop-local-ci.sh --profile pr --dry-run >/dev/null
	scripts/ci/local-security-audit.sh --profile cd --strict-tools --fail-advisory

script-tests:
	bash scripts/tests/test-drift-scripts.sh
	bash scripts/tests/test-fop-local-ci-failure-trap.sh
	bash scripts/tests/test-local-ci-report-check.sh

pre-push: ci

pre-push-report:
	bash scripts/check-local-ci-report.sh pr

## Run the real workflows locally with nektos/act
act:
	@if ! command -v act >/dev/null 2>&1; then \
		echo "act not installed. Install:"; \
		echo "  brew install act                                 # macOS/Linuxbrew"; \
		echo "  curl -fsSL https://raw.githubusercontent.com/nektos/act/master/install.sh | sudo bash"; \
		echo "See DEVELOPMENT.md for .actrc conventions."; \
		exit 1; \
	fi
	act -W .github/workflows/ci.yml

clean:
	rm -rf vendor node_modules parkhub-web/node_modules .phpunit.result.cache
