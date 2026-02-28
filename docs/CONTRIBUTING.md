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
- [Database Migrations](#database-migrations)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)

---

## Development Environment

### Prerequisites

| Tool | Version |
|------|---------|
| PHP | 8.3+ |
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
- `php artisan serve` — Laravel API on http://localhost:8000
- `npm run dev` — Vite frontend with hot reload
- `php artisan queue:listen` — Queue worker for email jobs
- `php artisan pail` — Log viewer

Or run each process individually:

```bash
# Terminal 1 — API backend
php artisan serve

# Terminal 2 — Frontend hot reload
npm run dev

# Terminal 3 — Queue worker
php artisan queue:listen --tries=1
```

Open **http://localhost:5173** (Vite proxy) or **http://localhost:8000** directly.

---

## Project Structure

```
parkhub-php/
├── app/
│   ├── Http/Controllers/Api/    # API controllers (one file per resource group)
│   │   ├── AuthController.php
│   │   ├── BookingController.php
│   │   ├── AdminController.php
│   │   └── ...
│   ├── Mail/                    # Mailables (WelcomeEmail, BookingConfirmation)
│   └── Models/                  # Eloquent models (User, ParkingLot, Booking, ...)
├── database/
│   ├── migrations/              # Database schema (numbered, ordered)
│   └── seeders/                 # Demo data seeders
├── docs/                        # Documentation (this directory)
├── legal/                       # German legal document templates
├── resources/js/                # React 19 frontend
│   └── src/
│       ├── api/                 # API client functions
│       ├── components/          # Shared UI components
│       ├── context/             # React context providers (AuthContext)
│       ├── hooks/               # Custom hooks
│       ├── i18n/                # Translation strings
│       ├── pages/               # Page components (one file per route)
│       └── stores/              # State stores
├── routes/
│   ├── api.php                  # Legacy /api/* routes
│   └── api_v1.php               # Primary /api/v1/* routes (Rust-compatible)
└── tests/
    ├── Feature/                 # Full HTTP request tests
    └── Unit/                    # Isolated class tests
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

---

## Code Style

### PHP — Laravel Pint (PSR-12)

```bash
# Fix all formatting issues
vendor/bin/pint

# Check without fixing (for CI)
vendor/bin/pint --test
```

### TypeScript / React

- TypeScript strict mode is enabled — do not relax it
- Functional components only (no class components)
- No inline styles — use Tailwind CSS utility classes
- ESLint runs via `npm run lint`

---

## Frontend Development

The frontend is a React 19 SPA built with Vite 6. Source is at `resources/js/src/`.

```bash
# Development with hot reload
npm run dev

# Production build (output to public/build/)
npm run build

# Type checking without building
npm run type-check

# ESLint
npm run lint
```

Rebuild the frontend inside the Docker image:

```bash
docker build -t parkhub-php .
```

---

## Adding a New API Endpoint

1. Create or update the controller in `app/Http/Controllers/Api/`
2. Add request validation with `$request->validate()`
3. Check authorization — either via `auth:sanctum` middleware (route level) or
   `$this->requireAdmin($request)` inside the method (controller level)
4. Add the route to `routes/api_v1.php` (and optionally `routes/api.php` for legacy)
5. Write a feature test in `tests/Feature/`
6. Document the endpoint in `docs/API.md`

---

## Database Migrations

```bash
# Create a new migration
php artisan make:migration add_notes_to_bookings_table

# Run migrations
php artisan migrate

# Rollback one migration
php artisan migrate:rollback

# Fresh install (destroys all data — development only)
php artisan migrate:fresh --seed
```

Always write both `up()` and `down()` methods. Test rollback before submitting a PR.

For schema changes in PRs:
- Check whether the migration is backwards-compatible
- Document any required data migration steps
- Test with both SQLite and MySQL if the migration uses DB-specific syntax

---

## Pull Request Process

1. Fork the repository and create a feature branch from `main`:

   ```bash
   git checkout -b feature/your-feature-name
   ```

   Branch naming:
   - `feature/` — new functionality
   - `fix/` — bug fixes
   - `docs/` — documentation only
   - `refactor/` — code without behavior change
   - `security/` — security fixes (coordinate via responsible disclosure first)

2. Write tests for any new functionality. Ensure all existing tests pass:

   ```bash
   composer test
   ```

3. Run the code formatter:

   ```bash
   vendor/bin/pint
   npm run lint
   ```

4. Update documentation:
   - `docs/API.md` for new or changed endpoints
   - `docs/CONFIGURATION.md` for new `.env` variables
   - `docs/CHANGELOG.md` under `[Unreleased]`

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
