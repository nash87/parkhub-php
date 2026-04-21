# parkhub-php — Local CI/CD mirror
#
# These targets mirror the reproducible local subset of .github/workflows/*.yml.
# NEVER let them drift from the workflow jobs they claim to mirror: if a
# workflow job changes, update the matching make target in the same commit.
# The GitHub workflows remain the source of truth; use `make ci` for the fast
# local core gate and `make act` when you need to execute the actual YAML.
#
# Usage:
#   make ci         # core local gate: lint + static-analysis + tests + frontend + drift
#   make lint       # pint --test + phpstan (backend-quality + static-analysis jobs)
#   make test       # full backend PHPUnit suite — mirrors backend-tests
#   make drift      # bootstrap sqlite + regenerate openapi + fail on diff — mirrors openapi-drift.yml
#   make act        # run the actual .github/workflows locally via nektos/act
#   make pre-push   # alias for ci; run before `git push origin/github`
#
# Requires: php 8.4, composer v2, node 22, npm. `act` is optional.

SHELL := bash
.SHELLFLAGS := -euo pipefail -c
MAKEFLAGS += --no-print-directory

.PHONY: help ci lint test static-analysis drift frontend act pre-push clean

help:
	@echo "parkhub-php local CI mirror (see .github/workflows/*.yml)"
	@echo ""
	@echo "  make ci         — lint + static-analysis + test + frontend + drift"
	@echo "  make lint       — pint --test (backend-quality)"
	@echo "  make static-analysis — phpstan (static-analysis job)"
	@echo "  make test       — full backend PHPUnit suite (backend-tests)"
	@echo "  make drift      — sqlite-backed openapi snapshot drift check"
	@echo "  make frontend   — npm ci + build (frontend job)"
	@echo "  make act        — run workflows via nektos/act (if installed)"
	@echo "  make pre-push   — alias for ci; run before git push"

## Mirrors: backend-quality job (.github/workflows/ci.yml)
lint:
	composer validate --strict
	find app bootstrap config database routes tests -name '*.php' -print0 \
		| xargs -0 -n 50 php -l > /dev/null
	./vendor/bin/pint --test

## Mirrors: static-analysis job
static-analysis:
	./vendor/bin/phpstan analyse --memory-limit=512M

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

## Core local CI — fast, reproducible subset of the blocking GitHub checks
ci: lint static-analysis test frontend drift
	@echo ""
	@echo "Core local gate passed. Run 'make act' for the full workflow YAML."

pre-push: ci

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
