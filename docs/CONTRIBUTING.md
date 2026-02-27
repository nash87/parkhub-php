# Contributing to ParkHub PHP

Development setup, testing, and pull request process.

---

## Development Environment

### Prerequisites

- PHP 8.3+ with extensions: `pdo_sqlite`, `mbstring`, `xml`, `gd`, `bcmath`, `zip`
- Composer 2.x
- Node.js 20 LTS + npm 10+
- Git

A `shell.nix` is included for Nix / NixOS users.

### First-time setup

```bash
git clone https://github.com/your-repo/parkhub-php.git
cd parkhub-php

# Install PHP and JS dependencies
composer install
npm install

# Create .env and generate app key
cp .env.example .env
php artisan key:generate

# SQLite database (no server needed in development)
touch database/database.sqlite
php artisan migrate

# Optionally seed demo data
php artisan db:seed --class=ProductionSimulationSeeder
```

### Run the development server

All four processes in parallel (recommended):

```bash
composer dev
```

This runs concurrently via `npx concurrently`:
- `php artisan serve` — Laravel backend on http://localhost:8000
- `php artisan queue:listen` — Queue worker for email jobs
- `php artisan pail` — Log viewer
- `npm run dev` — Vite frontend dev server with hot reload

Or start each process individually:

```bash
# Terminal 1 — API backend
php artisan serve

# Terminal 2 — Frontend hot reload
npm run dev

# Terminal 3 — Queue worker (for email testing)
php artisan queue:listen --tries=1
```

### One-command setup (from `composer.json`)

```bash
composer setup
```

This runs: `composer install`, `.env` creation, `php artisan key:generate`, `php artisan migrate`, `npm install`, `npm run build`.

---

## Project Structure

```
parkhub-php/
├── app/
│   ├── Http/Controllers/Api/    # API controllers (one per resource group)
│   ├── Mail/                    # Mailables (WelcomeEmail, BookingConfirmation)
│   └── Models/                  # Eloquent models
├── database/
│   ├── migrations/              # Database schema
│   └── seeders/                 # Demo data seeders
├── docs/                        # Documentation
├── legal/                       # German legal document templates
├── resources/js/                # React 19 frontend
│   └── src/
│       ├── api/                 # API client functions
│       ├── components/          # Shared UI components
│       ├── context/             # React context providers
│       ├── hooks/               # Custom hooks
│       ├── i18n/                # Translations
│       ├── pages/               # Page components (one per route)
│       └── stores/              # State stores
├── routes/
│   ├── api.php                  # Legacy API routes
│   └── api_v1.php               # V1 API routes (Rust-compatible)
└── tests/                       # PHPUnit / Pest tests
```

---

## Testing

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

Tests use SQLite in-memory by default (configured in `phpunit.xml`). No separate test database is needed.

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

### Writing tests

PHPUnit is the default test runner. Feature tests in `tests/Feature/` test full HTTP requests through the application. Unit tests in `tests/Unit/` test isolated classes.

Example feature test:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_booking(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $lot = \App\Models\ParkingLot::factory()->create();
        $slot = \App\Models\ParkingSlot::factory()->create(['lot_id' => $lot->id]);

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

PHP code follows **PSR-12**. Enforce with Laravel Pint (included as a dev dependency):

```bash
vendor/bin/pint
```

Check without fixing:

```bash
vendor/bin/pint --test
```

TypeScript/React code follows the existing ESLint and Prettier configuration.

---

## Frontend Development

The frontend is a React 19 SPA built with Vite 6. The source is under `resources/js/src/`.

Build for production:

```bash
npm run build
```

The built assets are output to `public/build/` and served by Laravel's asset handling.

Rebuild the frontend inside the Docker image:

```bash
docker build -t parkhub-php .
```

---

## Pull Request Process

1. Fork the repository and create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Write tests for any new functionality. Ensure all existing tests pass:
   ```bash
   composer test
   ```

3. Run the code formatter:
   ```bash
   vendor/bin/pint
   ```

4. Update the relevant documentation:
   - `docs/API.md` for new or changed endpoints
   - `docs/CONFIGURATION.md` for new `.env` variables
   - `docs/CHANGELOG.md` under an `[Unreleased]` section

5. Commit with a clear, descriptive message. Reference any related issues.

6. Open a pull request against `main`. The PR description should:
   - Explain what the change does and why
   - Link to any related issues
   - Note any breaking changes
   - Include migration steps if the database schema changed

7. All CI checks must pass before merge.

---

## Adding a New API Endpoint

1. Create or update the controller in `app/Http/Controllers/Api/`
2. Add the route in `routes/api.php` (legacy) and `routes/api_v1.php` (v1 / primary)
3. Add request validation with `$request->validate()`
4. Check authorization (either via `auth:sanctum` middleware or `$this->requireAdmin($request)` inside the method)
5. Add a test in `tests/Feature/`
6. Document the endpoint in `docs/API.md`

---

## Database Migrations

```bash
# Create a migration
php artisan make:migration add_column_to_table

# Run migrations
php artisan migrate

# Rollback
php artisan migrate:rollback
```

Always write both `up()` and `down()` methods. Test rollback before submitting a PR.

---

## Reporting Bugs

Open a GitHub issue with:
- PHP version and OS
- Steps to reproduce
- Expected vs. actual behaviour
- Relevant log output from `storage/logs/laravel.log`

For security vulnerabilities, follow the process in [SECURITY.md](SECURITY.md) — do not create a public issue.
