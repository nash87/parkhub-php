# Contributing to ParkHub PHP

Development setup, testing, code style, and pull request process.

---

## Table of Contents

- [Development Environment](#development-environment)
- [Project Structure](#project-structure)
- [Running Tests](#running-tests)
- [Code Style](#code-style)
- [Frontend Development](#frontend-development)
- [Adding a New API Endpoint](#adding-a-new-api-endpoint)
- [Adding a New Module](#adding-a-new-module)
- [Database Migrations](#database-migrations)
- [Branch Naming Conventions](#branch-naming-conventions)
- [Pull Request Process](#pull-request-process)
- [Internationalization (i18n)](#internationalization-i18n)
- [Reporting Bugs](#reporting-bugs)

---

## Development Environment

### Prerequisites

| Tool | Version |
|------|---------|
| PHP | 8.4+ |
| PHP extensions | `pdo_sqlite`, `mbstring`, `xml`, `gd`, `bcmath`, `zip` |
| Composer | 2.x |
| Node.js | 20 LTS |
| npm | 10+ |
| Git | any recent |

A `shell.nix` is included for Nix / NixOS users.

### First-Time Setup

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php

# One-command setup
composer setup
```

This runs: `composer install`, `.env` creation from `.env.example`, `php artisan key:generate`,
`php artisan migrate`, `npm install`, `npm run build`.

Or step by step:

```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install

# Environment config
cp .env.example .env
php artisan key:generate

# SQLite database (no server needed in development)
touch database/database.sqlite
php artisan migrate

# Optionally seed German demo data (10 lots, 200 users, ~3500 bookings)
php artisan db:seed --class=ProductionSimulationSeeder
```

### Start the Development Server

All four processes in parallel (recommended):

```bash
composer dev
```

This runs concurrently via `npx concurrently`:
- `php artisan serve` -- Laravel API on http://localhost:8000
- `npm run dev` -- Vite frontend with hot reload
- `php artisan queue:listen` -- Queue worker for email jobs
- `php artisan pail` -- Log viewer

Or run each process individually:

```bash
# Terminal 1 -- API backend
php artisan serve

# Terminal 2 -- Frontend hot reload
npm run dev

# Terminal 3 -- Queue worker
php artisan queue:listen --tries=1
```

Open **http://localhost:5173** (Vite proxy) or **http://localhost:8000** directly.

---

## Project Structure

```
parkhub-php/
  app/
    Console/Commands/       # Artisan commands
    Http/
      Controllers/Api/      # 47 API controllers
      Middleware/            # Request middleware (auth, headers, admin)
      Resources/            # API resource transformers
    Jobs/                   # Queue jobs
    Mail/                   # Mailable classes
    Models/                 # 22+ Eloquent models
  config/
    modules.php             # Module toggle configuration (35 modules)
  database/
    factories/              # Model factories for testing
    migrations/             # Database migrations
    seeders/                # Database seeders
  docs/                     # Documentation (this directory)
  legal/                    # German legal document templates (7 templates)
  routes/
    api.php                 # Legacy /api/* routes
    api_v1.php              # Primary /api/v1/* routes (Rust-compatible)
    modules/                # Per-module route files (35 modules)
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

## Running Tests

### Run all tests

```bash
composer test
# equivalent to: php artisan config:clear && php artisan test
```

### Run specific tests

```bash
php artisan test --filter=BookingTest
php artisan test tests/Feature/AuthTest.php
```

### Test database

Tests use SQLite in-memory (configured in `phpunit.xml`). No separate test database needed:

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

### Writing feature tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_booking(): void
    {
        $user  = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create(['lot_id' => $lot->id]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/bookings', [
                'lot_id'     => $lot->id,
                'slot_id'    => $slot->id,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time'   => now()->addHours(9)->toDateTimeString(),
            ]);

        $response->assertStatus(201);
    }
}
```

### Test coverage expectations

Every PR should maintain or improve test coverage. As of v3.2.0:
- **998 PHPUnit tests** (backend)
- **508 Vitest tests** (frontend)
- **1506 total tests**

---

## Code Style

### PHP -- Laravel Pint (PSR-12)

```bash
# Fix all formatting issues
vendor/bin/pint

# Check without fixing (for CI)
vendor/bin/pint --test
```

**Always run Pint on changed PHP files before committing.** CI will reject PRs with
style violations.

### Static Analysis -- Larastan (PHPStan)

```bash
./vendor/bin/phpstan analyse
```

Fix all Larastan errors before submitting a PR. The baseline file (`phpstan-baseline.neon`)
contains known issues that are being addressed over time.

### TypeScript / React

- TypeScript strict mode is enabled -- do not relax it
- Functional components only (no class components)
- No inline styles -- use Tailwind CSS utility classes
- ESLint runs via `npx eslint src/`

---

## Frontend Development

The frontend is a React 19 SPA built with Vite 7. Source is at `parkhub-web/src/`.

```bash
# Development with hot reload
npm run dev

# Production build (output to public/build/)
npm run build

# Type checking without building
npx tsc --noEmit

# ESLint
npx eslint src/
```

---

## Adding a New API Endpoint

1. Create or update the controller in `app/Http/Controllers/Api/`
2. Add request validation with `$request->validate()`
3. Check authorization -- either via `auth:sanctum` middleware (route level) or
   `$this->requireAdmin($request)` inside the method (controller level)
4. Add the route to `routes/api_v1.php` or the relevant `routes/modules/*.php` file
5. Write feature tests in `tests/Feature/`
6. Document the endpoint in `docs/API.md`

---

## Adding a New Module

ParkHub uses a module toggle system with 35 modules in four categories:

| Category | Default | Examples |
|----------|---------|---------|
| Core (20) | Enabled (opt-out) | bookings, vehicles, zones, themes |
| Admin (6) | Enabled (opt-out) | admin_reports, analytics, metrics |
| Integration (7) | Disabled (opt-in) | stripe, oauth, webhooks |
| Enterprise (2) | Disabled (opt-in) | multi_tenant, dynamic_pricing |

When adding a module:

1. Create a route file in `routes/modules/my_module.php`
2. Add the module toggle to `config/modules.php` with the appropriate category
3. Add `MODULE_MY_MODULE=true|false` to `.env.example`
4. Wrap route loading in a module check:
   ```php
   // routes/modules/my_module.php
   if (! config('modules.my_module')) {
       return;
   }
   ```
5. Write tests that verify:
   - Module works when enabled
   - Routes return 404 when module is disabled
6. Update the module table in README.md

---

## Database Migrations

```bash
# Create a new migration
php artisan make:migration add_notes_to_bookings_table

# Run migrations
php artisan migrate

# Rollback one migration
php artisan migrate:rollback

# Fresh install (destroys all data -- development only)
php artisan migrate:fresh --seed
```

Always write both `up()` and `down()` methods. Test rollback before submitting a PR.

For schema changes in PRs:
- Check whether the migration is backwards-compatible
- Document any required data migration steps
- Test with both SQLite and MySQL if the migration uses DB-specific syntax

---

## Branch Naming Conventions

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

1. Fork the repository and create a feature branch from `main` (see naming conventions above)

2. Write tests for any new functionality. Ensure all existing tests pass:

   ```bash
   composer test
   ```

3. Run the full check suite before pushing:

   ```bash
   vendor/bin/pint --test          # Code style
   ./vendor/bin/phpstan analyse    # Static analysis
   php artisan test                # Backend tests
   ```

4. Update documentation:
   - `docs/API.md` for new or changed endpoints
   - `docs/CONFIGURATION.md` for new `.env` variables
   - `CHANGELOG.md` under `[Unreleased]`

5. Commit with a clear, descriptive message in the imperative mood:

   ```
   Add recurring booking support for EU holidays
   Fix GDPR export missing absences field
   Update admin stats to include waitlist count
   ```

6. Open a PR against `main`. The PR description should:
   - Explain what the change does and why
   - Link to any related issues
   - Note any breaking changes (API, .env, database schema)
   - Include migration steps if schema changed

7. All CI checks must pass before merge.

### PR Checklist

- [ ] All existing tests pass
- [ ] New tests cover the added/changed functionality
- [ ] `./vendor/bin/pint --test` passes (zero style violations)
- [ ] `./vendor/bin/phpstan analyse` passes (zero new errors)
- [ ] Commit messages are descriptive
- [ ] PR description includes a summary and test plan
- [ ] No unrelated changes bundled in the PR

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

## Reporting Bugs

Open a GitHub issue with:

- PHP version: `php --version`
- Operating system and deployment method (Docker, VPS, etc.)
- Steps to reproduce
- Expected vs actual behaviour
- Log output from `storage/logs/laravel.log`

For security vulnerabilities, follow the process in [SECURITY.md](SECURITY.md).
Do not open a public issue for security reports.

---

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

See [LICENSE](../LICENSE).
