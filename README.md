<p align="center">
  <img src="public/favicon.svg" alt="ParkHub PHP" width="96">
</p>

<h1 align="center">ParkHub PHP — Self-Hosted Parking Management</h1>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge" alt="MIT License"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-12-FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12"></a>
  <a href="https://react.dev/"><img src="https://img.shields.io/badge/React-19-61DAFB.svg?style=for-the-badge&logo=react&logoColor=black" alt="React 19"></a>
  <a href="https://www.mysql.com/"><img src="https://img.shields.io/badge/MySQL-8.0-4479A1.svg?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL 8"></a>
  <a href="docs/GDPR.md"><img src="https://img.shields.io/badge/DSGVO-konform-green.svg?style=for-the-badge" alt="GDPR Compliant"></a>
</p>

<p align="center">
  <a href="docs/INSTALLATION.md">Installation</a> ·
  <a href="docs/API.md">API Reference</a> ·
  <a href="docs/GDPR.md">GDPR Guide</a> ·
  <a href="docs/CONFIGURATION.md">Configuration</a> ·
  <a href="docs/CHANGELOG.md">Changelog</a>
</p>

---

> **ParkHub — Ihre Parkplatzverwaltung. Ihre Daten. Ihr Server.**
> Keine Cloud. Keine Drittanbieter. DSGVO-konform durch Design.
> Betreiben Sie ParkHub auf Ihrem eigenen Server — ob Shared Hosting, VPS oder Kubernetes.
> Alle Buchungs- und Nutzerdaten bleiben ausschließlich bei Ihnen.

ParkHub PHP is a full-stack, self-hosted parking management platform for companies, universities, hospitals, and residential complexes. A React 19 frontend communicates with a Laravel 12 JSON API backed by SQLite (development) or MySQL 8 (production). The entire stack runs on any PHP 8.3 host — no Docker, no compiled binaries, no external services required at runtime.

---

## Features

| Feature | Status |
|---------|--------|
| Parking lot management (create, edit, delete, per-lot zones) | Done |
| Slot layout with zone assignment and visual map | Done |
| One-time bookings with auto-slot assignment | Done |
| Quick booking (one tap, system picks best slot) | Done |
| Recurring bookings (weekly day-of-week patterns) | Done |
| Guest bookings (named guest, no account required) | Done |
| Booking swap requests between users | Done |
| Check-in with QR codes per booking and per slot | Done |
| Booking invoice (HTML, browser Print-to-PDF) | Done |
| Vehicle management with photo upload (GD resize) | Done |
| German licence plate city-code lookup (400+ codes) | Done |
| Absence tracking (homeoffice, vacation, sick, training) | Done |
| Absence patterns (recurring weekly homeoffice) | Done |
| iCal export (bookings as .ics calendar feed) | Done |
| iCal import (absences and vacation from any calendar) | Done |
| Team overview — who is in office / on vacation today | Done |
| Waitlist for fully booked lots | Done |
| Admin dashboard with live occupancy stats | Done |
| Booking heatmap by weekday and hour | Done |
| Admin reports (bookings by day, status, type) | Done |
| Audit log for all write operations | Done |
| Announcements system (info, warning, error, success) | Done |
| User management with bulk import (up to 500 users) | Done |
| Admin CSV export of all bookings | Done |
| Branding: custom logo, company name, primary color | Done |
| Impressum editor (DDG §5 fields, publicly served) | Done |
| Privacy policy settings (GDPR retention days) | Done |
| Webhooks (outbound event notifications) | Done |
| Web Push notification subscriptions | Done |
| Laravel Sanctum Bearer token authentication | Done |
| Rate limiting (login/register: 10/min, password reset: 5/15min) | Done |
| GDPR Art. 20 — full data export as JSON | Done |
| GDPR Art. 17 — account anonymization (PII erasure, bookings kept) | Done |
| German legal templates (Impressum, Datenschutz, AGB, AVV) | Done |
| Dark mode / light mode / system preference | Done |
| Mobile-responsive React 19 frontend | Done |
| Health endpoints (`/health/live`, `/health/ready`) | Done |
| Docker single-container deployment (PHP 8.3 + Apache) | Done |
| Docker Compose with MySQL 8 | Done |
| Shared hosting deployment (FTP + browser installer) | Done |
| Email notifications — welcome email + booking confirmation (queued) | Done |
| Forgot password flow | Done |
| Auto-release settings (release no-show bookings after N minutes) | Done |
| User preferences (theme, language, timezone, notifications) | Done |
| Favorite slots | Done |
| In-app notification feed | Done |
| Kubernetes deployment manifests | Planned |

---

## Quick Start (3 commands)

```bash
git clone https://github.com/your-repo/parkhub-php.git
cd parkhub-php
docker compose up -d
```

Open **http://localhost:8080**. The setup wizard runs on first boot.

Default credentials (Docker): `admin` / `admin` — change immediately after login.

Migrations and a default admin account are created automatically by the container entrypoint.

---

## Screenshots

See the `docs/screenshots/` directory or the live demo link in the repository description.

---

## Architecture

```
Browser
  React 19 + TypeScript + Tailwind CSS
  (SPA served from /public/build/)
        |
        |  Bearer token (Laravel Sanctum)
        |  JSON REST API
        v
Laravel 12  (PHP 8.3, Apache)
  /api/*       — legacy routes
  /api/v1/*    — v1 routes (Rust-frontend compatible)
        |
        +---> SQLite   (DB_CONNECTION=sqlite, development / single-node)
        +---> MySQL 8  (DB_CONNECTION=mysql, production / Docker Compose)
        |
        +---> Queue worker  (email, push notifications)
        +---> Storage disk  (vehicle photos, branding logo)
```

No external service is called at runtime. SMTP is optional (notifications fall back to the log driver if unconfigured).

---

## Installation

| Platform | Guide |
|----------|-------|
| Docker Compose (recommended) | [docs/INSTALLATION.md](docs/INSTALLATION.md#docker-compose-recommended) |
| VPS / LAMP / LEMP (Ubuntu 24.04) | [docs/INSTALLATION.md](docs/INSTALLATION.md#vps--lamp-stack) |
| Shared hosting (cPanel, FTP) | [docs/SHARED-HOSTING.md](docs/SHARED-HOSTING.md) |
| PaaS (Railway, Render, Fly.io) | [docs/PAAS.md](docs/PAAS.md) |
| Kubernetes | [docs/INSTALLATION.md](docs/INSTALLATION.md#kubernetes) |

---

## Configuration

See [docs/CONFIGURATION.md](docs/CONFIGURATION.md) for every `.env` variable.

Key variables:

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_URL` | `http://localhost` | Public URL — required for QR codes and emails |
| `APP_KEY` | (auto-generated) | 32-byte encryption key |
| `DB_CONNECTION` | `sqlite` | `sqlite` or `mysql` |
| `DB_HOST` | — | MySQL host (if `DB_CONNECTION=mysql`) |
| `MAIL_MAILER` | `log` | `smtp`, `sendmail`, or `log` |
| `QUEUE_CONNECTION` | `database` | `database`, `sync`, or `redis` |

---

## Demo Data

Seed a realistic German dataset (10 lots, 200 users, ~3500 bookings over 30 days):

```bash
php artisan db:seed --class=ProductionSimulationSeeder
```

Lots include P+R Hauptbahnhof München, Tiefgarage Marktplatz Stuttgart, Parkhaus Technologiepark Karlsruhe, and seven more German locations.

---

## Security

- Passwords hashed with bcrypt (12 rounds configurable via `BCRYPT_ROUNDS`)
- API tokens issued by Laravel Sanctum with 7-day expiry
- Rate limiting on login/register (10/min) and password reset (5/15 min)
- Audit log written for every mutation (login, register, booking create/delete, GDPR erasure, admin actions)
- Input validation on every endpoint using Laravel's validator
- Vehicle photo content validated through PHP GD before storage (prevents polyglot file attacks)
- Admin-only routes enforce role check in each controller method, independent of middleware

See [docs/SECURITY.md](docs/SECURITY.md) for the full security model.

---

## GDPR & German Legal Compliance

| Right | Endpoint | Notes |
|-------|----------|-------|
| Art. 20 — Data portability | `GET /api/v1/user/export` | JSON archive: profile, bookings, absences, vehicles |
| Art. 17 — Right to erasure | `POST /api/v1/users/me/anonymize` | Anonymizes PII, keeps booking records per §147 AO |
| Account deletion | `DELETE /api/v1/users/me/delete` | Hard delete with CASCADE |
| DDG §5 Impressum | `GET /api/v1/legal/impressum` | Public endpoint, admin-editable |

Legal document templates for German operators are in the `legal/` directory. See [docs/GDPR.md](docs/GDPR.md) for the full compliance guide.

---

## License

MIT — see [LICENSE](LICENSE).

---

## Contributing

See [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for development setup, testing with PHPUnit/Pest, and the PR process.

---

## Documentation Index

| Document | Purpose |
|----------|---------|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Full installation guide (Docker, VPS, K8s) |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Every `.env` variable documented |
| [docs/API.md](docs/API.md) | Full API reference with curl examples |
| [docs/GDPR.md](docs/GDPR.md) | Operator DSGVO compliance guide |
| [docs/SECURITY.md](docs/SECURITY.md) | Security model and responsible disclosure |
| [docs/CHANGELOG.md](docs/CHANGELOG.md) | Release history |
| [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) | Development setup and PR process |
| [docs/DOCKER.md](docs/DOCKER.md) | Docker-specific deployment guide |
| [docs/VPS.md](docs/VPS.md) | VPS / LAMP manual deployment |
| [docs/SHARED-HOSTING.md](docs/SHARED-HOSTING.md) | Shared hosting (FTP, install.php wizard) |
| [docs/PAAS.md](docs/PAAS.md) | PaaS deployment (Railway, Render, Fly.io) |
| [legal/impressum-template.md](legal/impressum-template.md) | German Impressum template (DDG §5) |
| [legal/datenschutz-template.md](legal/datenschutz-template.md) | Datenschutzerklarung template |
| [legal/agb-template.md](legal/agb-template.md) | AGB template |
| [legal/avv-template.md](legal/avv-template.md) | Auftragsverarbeitungsvertrag template |
