# Contributing to ParkHub PHP

Thank you for considering a contribution to ParkHub. This guide covers the development
setup, coding standards, and pull request process.

---

## Development Setup

### Prerequisites

- PHP 8.4+
- Composer 2.x
- Node.js 20+ and npm
- SQLite (default) or MySQL 8+ / PostgreSQL

### Quick Start

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
composer setup
```

This will:
1. Install PHP dependencies (`composer install`)
2. Copy `.env.example` to `.env`
3. Generate an application key (`artisan key:generate`)
4. Run database migrations (`artisan migrate`)
5. Install Node.js dependencies (`npm install`)
6. Build frontend assets (`npm run build`)

### Running Locally

```bash
composer dev
```

This starts the PHP dev server, queue worker, log viewer, and Vite dev server concurrently.
Open http://localhost:8000 in your browser.

### Laravel Sail (Docker-based Dev Environment)

[Laravel Sail](https://laravel.com/docs/sail) provides a Docker-based local environment.
Composer is required once to install Sail; after that, all PHP tooling runs inside containers.

```bash
# Start all services (app, database, worker, scheduler)
./vendor/bin/sail up -d

# Run Artisan commands inside the container
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan tinker

# Run tests inside the container
./vendor/bin/sail artisan test

# Run Composer inside the container
./vendor/bin/sail composer install

# Stop all services
./vendor/bin/sail down
```

Add `alias sail='./vendor/bin/sail'` to your shell profile to save typing.
Open http://localhost:8080 when Sail is running.

### Docker (production-like compose)

```bash
docker compose up -d
# Open http://localhost:8080
```

---

## Running Tests

### Backend (PHPUnit)

```bash
php artisan test                    # All tests (~1,500 tests)
php artisan test tests/Unit         # Unit tests only
php artisan test tests/Feature      # Feature/integration tests only
php artisan test --filter=BookingTest  # Specific test class
```

### Frontend (Vitest)

```bash
cd parkhub-web && npx vitest run    # All frontend tests (~508 tests)
```

### End-to-End (Playwright)

```bash
npx playwright test                 # All E2E tests
npx playwright test --project=chromium  # Chromium only
```

### Integration Tests

Integration tests exercise cross-module interactions and full API lifecycles:

```bash
# Run all integration tests
php artisan test --filter=Integration

# Run the 1-month booking simulation
php artisan test --filter=Simulation
```

### Running the Simulation Engine

The booking simulation creates realistic 30-day booking patterns with configurable profiles:

```bash
# Default profile (small office, 10 users)
php artisan test --filter=SimulationSmall

# Campus profile (50 users, multi-lot)
php artisan test --filter=SimulationCampus

# Enterprise profile (200 users, multi-tenant)
php artisan test --filter=SimulationEnterprise
```

### Running k6 Load Tests

Performance testing with [k6](https://grafana.com/docs/k6/):

```bash
# Install k6
brew install k6  # macOS
# or: https://grafana.com/docs/k6/latest/set-up/install-k6/

# Run smoke test (quick sanity check)
k6 run tests/load/smoke.js

# Run sustained load test (50 VUs, 5 minutes)
k6 run tests/load/load.js

# Run stress test (100 VUs, 10 minutes)
k6 run tests/load/stress.js

# Run spike test (1 → 200 → 1 VUs)
k6 run tests/load/spike.js

# Custom base URL
K6_BASE_URL=http://localhost:8000 k6 run tests/load/load.js
```

### Running with Docker Compose Test Profile

```bash
docker compose -f docker-compose.yml -f test.yml up -d
php artisan test
docker compose -f docker-compose.yml -f test.yml down
```

### Full Test Suite

```bash
php artisan test && cd parkhub-web && npx vitest run && cd .. && npx playwright test
```

---

## Code Style

### PHP -- Laravel Pint (PSR-12)

ParkHub uses [Laravel Pint](https://laravel.com/docs/pint) for PHP code formatting.
**Always run Pint on changed files before committing.**

```bash
./vendor/bin/pint                   # Auto-fix all files
./vendor/bin/pint --test            # Check only (CI mode)
./vendor/bin/pint app/Http/Controllers/Api/MyController.php  # Single file
```

### Static Analysis -- Larastan (PHPStan)

```bash
./vendor/bin/phpstan analyse        # Run static analysis
```

Fix all Larastan errors before submitting a PR. The baseline file (`phpstan-baseline.neon`)
contains known issues that are being addressed over time.

### Frontend -- Biome + TypeScript

ParkHub uses [Biome](https://biomejs.dev) for frontend lint + format. Biome is a
single Rust-native binary that replaces ESLint and Prettier (~25x faster) and
ships under MIT OR Apache-2.0.

```bash
cd parkhub-web
npx biome check src/                # Lint + format check
npx biome check --write src/        # Auto-fix
npx tsc --noEmit                    # Type check
```

Configuration lives in `parkhub-web/biome.json`. The lefthook `lint-ts` hook
runs `biome check` against staged files and re-stages auto-fixed output.

---

## Local CI (Lefthook)

ParkHub runs the same gates locally that GitHub Actions runs remotely. The
goal is to catch 90%+ of CI failures before push so the 15-30 min round-trip
is reserved for genuinely cross-cutting checks.

### One-time setup

```bash
composer install                    # installs lefthook via composer post-install
cd parkhub-web && npm install && cd ..
lefthook install                    # registers .git/hooks/pre-commit + pre-push
```

If `lefthook` is not on `$PATH`, install it from
[https://lefthook.dev](https://lefthook.dev) (Linux/macOS: `brew install lefthook`
or `npm i -g lefthook`).

### What runs when

| Hook | Speed | Commands |
|------|-------|----------|
| `pre-commit` | <10s typical | Pint format check, Biome check (changed files, auto-fix re-staged), `tsc --noEmit` |
| `pre-push` | <60s typical | PHPStan, PHPUnit Unit suite, Vitest (changed), OpenAPI drift, types drift |

Pre-commit runs on staged files only. Pre-push runs the full gate set in
parallel and is the local mirror of the GitHub Actions pipeline.

### Replaying CI locally

```bash
lefthook run pre-commit             # Same as committing, without committing
lefthook run pre-push               # Full local CI replay
```

### Drift gates

* `scripts/check-openapi-drift.sh` regenerates `docs/openapi/php.json` via
  Scramble and fails if the committed snapshot is stale. Always run
  `composer dump-autoload` first — Scramble silently drops endpoints when
  the autoload map is out of date.
* `scripts/check-types-drift.sh` is a no-op in this repo. The shared TS API
  types live in `parkhub-web/src/api/types.gen.ts` and are owned by the
  parkhub-rust repo; that repo's pre-push gates the regen.

### Bypassing in emergencies

```bash
git push --no-verify                # Skip pre-push (logged + frowned-upon)
git commit --no-verify              # Skip pre-commit
```

Use only when you have a justified reason (broken hook, time-critical
hotfix). CI will still run the full gate set on the PR.

---

## Merge Queue

ParkHub uses GitHub's **native merge queue** (GA since 2023) -- no third-party
SaaS required. Activate it once via:

> Settings -> Branches -> Branch protection rules -> select the rule for `main` ->
> check **Require merge queue**.

Configure the required status checks (`CI`, `CodeQL`, etc.) in the same UI.
There is no config file in the repo for this; it is a native GitHub feature
managed entirely through repository settings.

## Auto-merge

To opt a PR into auto-merge, apply the **`auto-merge`** label. The
`.github/workflows/auto-merge.yml` workflow then enables GitHub's native
auto-merge, which squash-merges the PR once all required status checks pass
and any required review approvals land.

The workflow runs on `pull_request_target` so it works for Dependabot PRs and
forks. Permissions are scoped to `pull-requests: write` + `contents: write`
only -- no third-party app installation required.

---

## Branch Naming Conventions

Use the following prefixes for branch names:

| Prefix | Purpose | Example |
|--------|---------|---------|
| `feat/` | New feature or module | `feat/parking-reservations` |
| `fix/` | Bug fix | `fix/booking-overlap-check` |
| `refactor/` | Code restructuring (no behavior change) | `refactor/controller-cleanup` |
| `docs/` | Documentation only | `docs/api-examples` |
| `test/` | Adding or updating tests | `test/vehicle-upload-edge-cases` |
| `chore/` | Tooling, dependencies, CI | `chore/update-laravel-12` |

Always branch from `main`:

```bash
git checkout main
git pull
git checkout -b feat/my-feature
```

---

## Pull Request Process

1. **Create a feature branch** from `main` (see naming conventions above)
2. **Write tests** for new functionality -- both happy path and edge cases
3. **Run the full check suite** before pushing:
   ```bash
   ./vendor/bin/pint --test          # Code style
   ./vendor/bin/phpstan analyse      # Static analysis
   php artisan test                  # Backend tests
   ```
4. **Write a clear commit message** -- describe *what* and *why*, not *how*
5. **Push and open a PR** against `main`
6. **Fill in the PR template** with a summary and test plan
7. **Address review feedback** -- push additional commits (do not force-push)

### PR Requirements

- [ ] All existing tests pass
- [ ] New tests cover the added/changed functionality
- [ ] `./vendor/bin/pint --test` passes (zero style violations)
- [ ] `./vendor/bin/phpstan analyse` passes (zero new errors)
- [ ] Commit messages are descriptive
- [ ] PR description includes a summary and test plan
- [ ] No unrelated changes bundled in the PR

### What Makes a Good PR

- **Small and focused** -- one feature or fix per PR
- **Tests included** -- feature tests for API endpoints, unit tests for business logic
- **Documentation updated** -- if adding a module, update the module table in README.md
- **Migration included** -- if adding database columns/tables

---

## Testing Requirements

### New API Endpoints

Every new API endpoint should have feature tests covering:
- Authentication (401 without token)
- Authorization (403 for non-admin on admin routes)
- Validation (422 with invalid input)
- Success case (200/201 with valid input)
- Edge cases (duplicates, not found, etc.)

### New Modules

When adding a module, follow the steps in the [Module System](#module-system) section above.
Make sure tests cover the disabled state (routes return 404) and the enabled state (happy path + edge cases).

---

## Project Structure

```
parkhub-php/
  app/
    Console/Commands/       # Artisan commands
    Http/
      Controllers/Api/      # 74 API controllers
      Middleware/            # Request middleware (auth, headers, admin)
      Resources/            # API resource transformers
    Jobs/                   # Queue jobs
    Mail/                   # Mailable classes
    Models/                 # 31 Eloquent models
  config/
    modules.php             # Module toggle configuration
  database/
    factories/              # Model factories for testing
    migrations/             # Database migrations
    seeders/                # Database seeders
  docs/                     # Documentation
  legal/                    # Legal template files (German)
  routes/
    api.php                 # Main API routes
    api_v1.php              # V1 API routes
    modules/                # Module route fragments (62 files for 67 toggles)
  tests/
    Feature/                # Integration tests
    Unit/                   # Unit tests
  parkhub-web/              # React 19 SPA frontend
    src/
      components/           # React components
      hooks/                # Custom hooks
      pages/                # Page components
      locales/              # i18n translation files (10 languages)
```

---

## Module System

ParkHub uses a module toggle system with **67 modules** spread across five categories:

| Category | Count | Default | Description |
|----------|-------|---------|-------------|
| Core | 20 | Enabled (opt-out) | Essential parking management (bookings, vehicles, zones, QR, GDPR, map, …) |
| Admin & Management | 24 | Enabled (opt-out) | Reporting, analytics, fleet, EV charging, geofence, audit log, … |
| Platform & v4.x | 14 | Mostly enabled | Plugins, GraphQL, compliance, RBAC, PWA, sharing, scheduled reports, … |
| Integration | 7 | Disabled (opt-in) | External services requiring credentials (Stripe, OAuth, webhooks, realtime, …) |
| Enterprise | 2 | Disabled (opt-in) | Advanced features for large deployments (multi-tenant, dynamic pricing) |

Each module can be toggled via `MODULE_*` environment variables. Module routes are loaded
conditionally from `routes/modules/`. When a module is disabled, its routes return 404.

The full list of flags lives in `config/modules.php`. The current state of all modules is
exposed at `GET /api/v1/modules`.

### Adding a New Module

1. Create a route file in `routes/modules/`
2. Register the toggle in `config/modules.php`
3. Add the `MODULE_*` env var to `.env.example`
4. Write tests that verify the module can be toggled off (routes return 404)
5. Update the module table in `README.md`

---

## Internationalization (i18n)

ParkHub supports 10 languages: EN, DE, FR, ES, IT, PT, TR, PL, JA, ZH.

### Adding a New Language

1. Copy `parkhub-web/src/locales/en.json` to `parkhub-web/src/locales/{code}.json`
2. Translate all keys (do not remove any keys)
3. Add the language to the language selector in `parkhub-web/src/components/Layout.tsx`
4. Add i18n tests that validate the new locale for missing keys

### Updating Translations

1. Edit the relevant locale file in `parkhub-web/src/locales/`
2. Run `cd parkhub-web && npx vitest run` to verify no keys are missing
3. Submit a PR with the translation changes

### Translation Guidelines

- Use formal address ("Sie" in German, "vous" in French) for UI text
- Keep translations concise -- UI labels should be short
- Preserve placeholder variables like `{{count}}`, `{{name}}`
- Test the UI with your translations to verify layout (some languages are longer)

---

## Good First Issues

Looking for a place to start? Here are well-scoped tasks that don't require deep system knowledge:

### Backend
- **Add a missing test** — pick any controller in `app/Http/Controllers/Api/` that has no corresponding test
  in `tests/Feature/` and write one. Cover authentication (401), authorization (403), validation (422), and
  the happy-path success case.
- **Fix a PHPStan baseline entry** — open `phpstan-baseline.neon` and resolve one ignored error in a file
  you feel comfortable with.
- **Improve a validation rule** — look for `required` rules that are missing `max:` or `min:` constraints
  and tighten them.
- **Add a factory** — if a model in `app/Models/` is missing a factory in `database/factories/`, add one.

### Frontend
- **Fix a missing i18n key** — run `cd parkhub-web && npx vitest run` and look for failing locale tests,
  then add the missing translation in the relevant `src/locales/*.json` file.
- **Improve an error state** — find a component in `parkhub-web/src/components/` that renders a network
  request but has no error boundary or empty-state message, and add one.
- **Add a missing loading state** — find a data-fetching hook in `parkhub-web/src/hooks/` where the
  loading state is not surfaced in the UI and wire it up.

### DevOps / Documentation
- **Document a new env variable** — if a `MODULE_*` env var exists in `config/modules.php` but is missing
  from `.env.example`, add it with a comment.
- **Add a helpful Artisan command description** — find a command class in `app/Console/Commands/` that has
  no `$description` property set and add one.

### How to Claim an Issue

1. Check [open issues](https://github.com/nash87/parkhub-php/issues) labelled
   `good first issue` or `help wanted`.
2. Leave a comment saying you'd like to work on it so it can be assigned to you.
3. Fork, branch (`feat/`, `fix/`, `docs/`, etc.), and open a PR against `main` when ready.

---

## License

MIT -- see [LICENSE](LICENSE).

By contributing to ParkHub PHP, you agree that your contributions will be licensed under
the MIT License.
