# ParkHub PHP Edition

<p align="center">
  <img src="public/favicon.svg" alt="ParkHub" width="80">
</p>

<p align="center">
  <strong>Open-source parking management system</strong><br>
  Built with Laravel 12 + React 19 — runs on any PHP hosting
</p>

<p align="center">
  <a href="#quick-start">Quick Start</a> •
  <a href="#features">Features</a> •
  <a href="docs/SHARED-HOSTING.md">Shared Hosting</a> •
  <a href="docs/DOCKER.md">Docker</a> •
  <a href="docs/VPS.md">VPS</a> •
  <a href="docs/PAAS.md">PaaS</a>
</p>

---

## Features

- **Setup Wizard** — 5 use-case templates (corporate, residential, family, rental, public)
- **Booking Management** — Slot availability, quick booking, guest bookings
- **Recurring Bookings** — Daily, weekly, monthly schedules
- **Absence Tracking** — Home office, vacation, sick leave, training
- **Team Overview** — See who's in the office
- **Admin Dashboard** — Statistics, audit log, announcements, user management
- **Vehicle Management** — License plates, multiple vehicles per user
- **GDPR Compliant** — Data export, account deletion
- **10 Languages** — EN, DE, FR, ES, IT, PT, NL, PL, CS, JA
- **PWA** — Installable, works offline
- **Light/Dark Mode** — System-aware theming
- **55+ API Endpoints** — Full REST API with Sanctum auth

## Requirements

- **PHP** 8.2+ with extensions: pdo, mbstring, openssl, tokenizer, json, curl, fileinfo
- **Database:** SQLite (default, zero config) or MySQL 5.7+ / MariaDB 10.3+
- **Composer** (for dependency installation)
- **Node.js 18+** (only for building frontend from source)

## Quick Start

### Shared Hosting (No Terminal Required)

1. Download the [latest release](../../releases)
2. Upload to your hosting via FTP
3. Visit `https://yourdomain.com/install.php`
4. Follow the wizard — done!

→ [Detailed Shared Hosting Guide](docs/SHARED-HOSTING.md)

### Docker

```bash
git clone https://github.com/your-repo/parkhub-php.git
cd parkhub-php
docker compose up -d
# Visit http://localhost:8080
```

→ [Docker Guide](docs/DOCKER.md)

### VPS (Manual)

```bash
sudo apt install php8.3 php8.3-{cli,fpm,mysql,sqlite3,mbstring,xml,curl,zip} apache2 composer
cd /var/www && git clone https://github.com/your-repo/parkhub-php.git parkhub
cd parkhub && composer install --no-dev
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
# Configure Apache virtual host → docs/VPS.md
```

→ [VPS Guide](docs/VPS.md)

### Railway / Render / Fly.io

[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/parkhub-php)

→ [PaaS Guide](docs/PAAS.md)

### Development

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm install && npm run dev   # Frontend dev server
php artisan serve             # Backend on :8000
```

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3, Laravel 12 |
| Frontend | React 19, TypeScript, Vite, Tailwind CSS 4 |
| Icons | Phosphor Icons |
| Auth | Laravel Sanctum (token-based) |
| Database | SQLite / MySQL / MariaDB |
| PWA | Service Worker + Manifest |

## Project Structure

```
├── app/                  # Laravel application (Models, Controllers, etc.)
├── config/               # Laravel configuration
├── database/             # Migrations, seeders, SQLite database
├── docs/                 # Deployment guides
├── public/               # Web root (index.php, assets, install.php)
├── resources/js/         # React SPA source
│   ├── src/
│   │   ├── components/   # Reusable UI components
│   │   ├── pages/        # Route pages
│   │   ├── stores/       # Zustand state management
│   │   ├── i18n/         # Translations (10 languages)
│   │   └── api/          # API client
│   └── index.html        # SPA entry point
├── routes/               # API and web routes
├── storage/              # Logs, cache, sessions
├── Dockerfile            # Docker image
├── docker-compose.yml    # Docker Compose (PHP + MySQL)
├── railway.toml          # Railway PaaS config
├── render.yaml           # Render PaaS config
└── fly.toml              # Fly.io config
```

## Also Available: ParkHub Docker Edition

Looking for a high-performance Rust backend? Check out **[ParkHub Docker Edition](https://github.com/nash87/parkhub)** — built with Rust + Axum for maximum performance. Best for VPS, Kubernetes, and DevOps teams.

## License

MIT License — see [LICENSE](LICENSE) for details.
