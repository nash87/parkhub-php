<p align="center">
  <img src="public/favicon.svg" alt="ParkHub PHP Edition" width="96">
</p>

<h1 align="center">ParkHub — PHP Edition</h1>

<p align="center">
  <strong>Open-source parking management for shared hosting and VPS.<br>
  Runs on any server with PHP 8.2 + MySQL/SQLite. No Docker required.</strong>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge" alt="MIT License"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-12-FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.2+-777BB4.svg?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="https://react.dev/"><img src="https://img.shields.io/badge/React-19-61DAFB.svg?style=for-the-badge&logo=react&logoColor=black" alt="React 19"></a>
</p>

<p align="center">
  <a href="docs/SHARED-HOSTING.md">Shared Hosting</a> ·
  <a href="docs/DOCKER.md">Docker</a> ·
  <a href="docs/VPS.md">VPS</a> ·
  <a href="docs/PAAS.md">PaaS (Render/Railway)</a> ·
  <a href="docs/API.md">API Reference</a> ·
  <a href="CHANGELOG.md">Changelog</a>
</p>

---

## What is this?

ParkHub PHP Edition is a full-stack parking management system — the same app as [ParkHub Docker](https://github.com/nash87/parkhub-docker), but built for environments where you **cannot run Docker or compiled binaries**. It uses Laravel as the API backend and the same React frontend.

**Choose this version if you:**
- Have shared hosting (cPanel, Plesk, IONOS, Netcup, All-Inkl, etc.)
- Already run PHP + MySQL on your server
- Want to avoid Docker entirely
- Need a budget-friendly deployment (free tiers available)

**Choose [ParkHub Docker](https://github.com/nash87/parkhub-docker) if you:**
- Have a VPS or container environment
- Want a single binary with zero dependencies
- Need embedded database (no MySQL/PostgreSQL required)
- Prefer Rust performance for high-traffic deployments

---

## Which version is right for me?

| | ParkHub PHP | ParkHub Docker |
|---|---|---|
| **Tech stack** | Laravel 12 + PHP 8.2 | Rust (Axum) + Embedded DB |
| **Database** | SQLite or MySQL/PostgreSQL | Embedded redb (SQLite-like) |
| **Deployment** | Shared hosting, cPanel, VPS | Docker, Kubernetes, single binary |
| **Dependencies** | PHP 8.2, Composer | None — single ~30 MB binary |
| **Hosting cost** | Free tier available (shared) | Needs VPS / container runtime |
| **Setup time** | ~5 minutes (install.php wizard) | ~2 minutes (docker compose up) |
| **Performance** | Good (PHP-FPM) | Excellent (Rust async) |
| **Features** | ✅ Full parity | ✅ Full parity |
| **API compatibility** | ✅ Identical endpoints | ✅ Identical endpoints |
| **Mobile PWA** | ✅ | ✅ |
| **10 color themes** | ✅ | ✅ |
| **10 languages** | ✅ | ✅ |
| **Admin dashboard** | ✅ | ✅ |
| **Auto-updates** | Manual via Git/Composer | Built-in update checker |
| **Source code** | [nash87/parkhub-php](https://github.com/nash87/parkhub-php) | [nash87/parkhub-docker](https://github.com/nash87/parkhub-docker) |

---

## Features

Both editions share the same feature set:

| Feature | Description |
|---|---|
| **Slot Management** | Visual parking map with real-time availability |
| **Smart Booking** | One-time, multi-day, recurring, guest bookings |
| **Quick Book** | One-tap booking — system picks the best slot |
| **Booking Check-in** | QR-code check-in, manual check-in |
| **Absence Tracking** | Homeoffice, vacation, sick leave, training — all in one place |
| **iCal Import/Export** | Import absence/vacation from any calendar app, export bookings as .ics |
| **Team Overview** | See who's in office, on vacation, or working from home |
| **Waitlist** | Automatic notification when a spot opens up |
| **Swap Requests** | Request to swap a booking with a colleague |
| **Vehicle Management** | Multiple vehicles, photo upload, German license plate format |
| **Notifications** | In-app notifications, push notifications (Web Push) |
| **Admin Dashboard** | Stats, heatmap, audit log, CSV export, reports |
| **User Management** | Create, deactivate, import via CSV |
| **Branding** | Custom logo, company name, color scheme |
| **Privacy / GDPR** | Data export, account deletion, configurable retention policy |
| **10 Themes** | Solarized, Dracula, Nord, Gruvbox, Catppuccin, Tokyo Night, One Dark, Rose Pine, Everforest, Default Blue |
| **10 Languages** | English, German, Spanish, French, Portuguese, Turkish, Arabic, Hindi, Japanese, Chinese |
| **Accessibility** | Colorblind modes, font scaling, reduced motion, high contrast |
| **PWA** | Install as native app on any device |
| **Setup Wizard** | First-run guided setup: use case, company name, demo data, registration mode |
| **Onboarding** | Step-by-step user onboarding on first login |
| **Auto-Release** | Automatically release bookings after configurable timeout (no no-shows) |
| **Webhooks** | Send events to external services on booking changes |
| **REST API** | Full API with 100+ endpoints — see [API Docs](docs/API.md) |
| **Health Endpoints** | `/health/live` + `/health/ready` for monitoring |

---

## Quick Start

### Option A: Shared Hosting (cPanel / Plesk / FTP)

The fastest way to get started on shared hosting:

1. Download `parkhub-php-shared-hosting.zip` from [Releases](https://github.com/nash87/parkhub-php/releases)
2. Upload and unzip to your web root (e.g. `public_html/`)
3. Create a MySQL database in your hosting panel
4. Open `https://yourdomain.com/install.php` in your browser
5. Follow the setup wizard — takes about 2 minutes

Full guide: [docs/SHARED-HOSTING.md](docs/SHARED-HOSTING.md)

### Option B: Docker

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
docker compose up -d
```

Open `http://localhost:8080` and run through the setup wizard.

### Option C: Manual (VPS / NixOS / Any Linux)

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
composer install --no-dev
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
# Serve with nginx + PHP-FPM, Apache, or:
php artisan serve --host=0.0.0.0 --port=8080
```

Full guide: [docs/VPS.md](docs/VPS.md)

### Option D: PaaS (Render / Railway — Free Tier)

One-click deploy to Render or Railway — no server needed:

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy)
[![Deploy to Railway](https://railway.app/button.svg)](https://railway.app)

Full guide: [docs/PAAS.md](docs/PAAS.md)

---

## Shared Hosting Notes

PHP Edition is designed to run on the cheapest possible hosting:

- **Minimum requirements:** PHP 8.2, MySQL 5.7+ or SQLite, 50 MB disk
- **Tested on:** IONOS, Netcup, All-Inkl, Strato, Hostinger, cPanel hosts
- **Free hosting:** Works on free tiers at Render, Railway, Fly.io
- **No root required:** Everything runs in the web root
- **No exec() or shell_exec():** Safe for restricted shared hosting

---

## Comparison with ParkHub Docker

ParkHub comes in two editions with identical features and API endpoints. The only difference is the deployment model:

```
┌─────────────────────────────────────────────────────┐
│                    ParkHub                          │
│                 (same frontend)                     │
├────────────────────┬────────────────────────────────┤
│   PHP Edition      │       Docker Edition           │
│                    │                                │
│  Laravel 12 API    │    Rust (Axum) API             │
│  MySQL / SQLite    │    Embedded redb database      │
│  Any PHP hosting   │    Single binary / Docker      │
│  Shared hosting ✓  │    VPS / Kubernetes ✓          │
│  Free tier ✓       │    Self-contained ✓            │
└────────────────────┴────────────────────────────────┘
```

Both editions expose the same REST API, so you can switch between them at any time by exporting and importing your data.

---

## Screenshots

### Mobile (PWA)

<div align="center">
  <img src="docs/screenshots/mobile-light.png" width="280" alt="Dashboard Light">
  <img src="docs/screenshots/mobile-dark.png" width="280" alt="Dashboard Dark">
</div>

### Desktop

<div align="center">
  <img src="docs/screenshots/desktop-light.png" width="800" alt="Desktop Dashboard">
</div>

---

## API

The PHP Edition implements the same REST API as ParkHub Docker. All endpoints are prefixed with `/api/v1`.

Key endpoint groups:
- `POST /api/v1/auth/login` — authentication
- `GET /api/v1/lots` — parking lots
- `POST /api/v1/bookings` — create booking
- `GET /api/v1/absences` — absence tracking
- `GET /api/v1/admin/stats` — admin dashboard
- `GET /api/v1/health/live` — health check

Full reference: [docs/API.md](docs/API.md)

---

## Development

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve &
npm run dev
```

Tests:

```bash
php artisan test
```

---

## License

MIT — see [LICENSE](LICENSE)

---

<p align="center">
  Also available as a single-binary Docker edition: <a href="https://github.com/nash87/parkhub-docker">nash87/parkhub-docker</a>
</p>
