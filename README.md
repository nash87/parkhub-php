# ParkHub PHP Edition

Open-source parking management system built with Laravel 11 + React SPA.

## Quick Start

### Requirements
- PHP 8.2+ with extensions: pdo, pdo_sqlite, mbstring, openssl, tokenizer, json, curl, fileinfo
- Composer
- Node.js 18+

### Development Setup

```bash
# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations (SQLite by default)
touch database/database.sqlite
php artisan migrate

# Install & build frontend
npm install
npm run build

# Start development server
php artisan serve
```

Visit http://localhost:8000 — the setup wizard will guide you through initial configuration.

### Shared Hosting (Apache)

1. Upload all files to your hosting
2. Point your domain to the `public/` directory
3. Visit `https://yourdomain.com/install.php` for one-click setup
4. Delete `install.php` after setup

### NixOS Development

```bash
nix-shell  # Uses shell.nix for PHP 8.3 + Composer + Node
composer install
npm install && npm run build
php artisan migrate
php artisan serve
```

## Tech Stack
- **Backend:** PHP 8.3, Laravel 11, SQLite/MySQL
- **Frontend:** React 19, Vite, Tailwind CSS 4, Phosphor Icons
- **Auth:** Laravel Sanctum (token-based)
- **PWA:** Service worker, manifest

## Features
- 55+ API endpoints
- Setup wizard with 5 use cases (corporate, residential, family, rental, public)
- Booking management with slot availability
- Recurring bookings
- Guest bookings
- Absence tracking (homeoffice, vacation, sick, training)
- Team overview
- Admin dashboard with stats, audit log, announcements
- Vehicle management
- GDPR compliance (data export, account deletion)
- 10 language support
- Light/dark mode
- PWA support
- Shared hosting compatible

## License
MIT
