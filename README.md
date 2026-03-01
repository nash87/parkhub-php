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
  <a href="docker-compose.yml"><img src="https://img.shields.io/badge/Docker-ready-2496ED.svg?style=for-the-badge&logo=docker&logoColor=white" alt="Docker Ready"></a>
  <a href="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml"><img src="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
</p>

<p align="center">
  <strong>Ihre Daten. Ihr Server. Ihre Kontrolle.</strong><br>
  The on-premise parking management platform for enterprises, universities, and residential complexes.<br>
  Runs on shared hosting, VPS, Docker, or Kubernetes. Zero cloud. Zero tracking. 100% GDPR compliant by design.
</p>

<p align="center">
  <a href="docs/INSTALLATION.md">Installation</a> ·
  <a href="docs/API.md">API Reference</a> ·
  <a href="docs/GDPR.md">GDPR Guide</a> ·
  <a href="docs/CONFIGURATION.md">Configuration</a> ·
  <a href="CHANGELOG.md">Changelog</a>
</p>

---

## Why ParkHub?

> Self-hosted means YOUR data never leaves YOUR server. No US cloud providers. No CLOUD Act risks.
> No data processing agreements needed — because there is no data processor.

| | ParkHub (self-hosted) | SaaS Alternatives |
|---|---|---|
| Your data location | Your server | Provider's cloud |
| GDPR processor agreement | Not needed | Required |
| Monthly SaaS fee | €0 | €200–2,000/month |
| US CLOUD Act exposure | None | Possible |
| Source code auditable | MIT License | Proprietary |
| German legal templates | Included | Extra cost |
| Shared hosting compatible | Yes | N/A |
| Works offline / LAN-only | Yes | No |

---

## Features

### Booking Management

| Feature | Status |
|---|---|
| One-time bookings with auto-slot assignment | Done |
| Quick booking (one tap, system picks best available slot) | Done |
| Recurring bookings (weekly day-of-week patterns) | Done |
| Guest bookings (named guest, no account required) | Done |
| Booking swap requests between users | Done |
| Booking cancellation with automatic slot release | Done |
| Full booking history (active, past, cancelled) | Done |
| Booking invoice (HTML, browser Print-to-PDF) | Done |
| Check-in with QR code per booking and per slot | Done |
| Waitlist for fully booked lots | Done |
| Auto-release (release no-show bookings after N minutes) | Done |
| iCal export (bookings as .ics calendar feed) | Done |
| Email notifications — welcome email + booking confirmation | Done |
| Favorite slots per user | Done |
| In-app notification feed | Done |

### Parking Lot Management

| Feature | Status |
|---|---|
| Create and manage multiple lots with per-lot zones | Done |
| Slot layout with zone assignment and visual map | Done |
| Per-slot status management | Done |
| Real-time occupancy statistics | Done |
| Public occupancy display board | Done |

### User Management

| Feature | Status |
|---|---|
| User registration and login | Done |
| Role-based access control: user / admin | Done |
| Laravel Sanctum Bearer token authentication (7-day expiry) | Done |
| Password reset via email | Done |
| Forgot password flow | Done |
| Vehicle management with photo upload (GD resize) | Done |
| German licence plate city-code lookup (400+ codes) | Done |
| Bulk user import (up to 500 users via CSV) | Done |
| User preferences (theme, language, timezone, notifications) | Done |
| Absence tracking (homeoffice, vacation, sick, training) | Done |
| Absence patterns (recurring weekly homeoffice) | Done |
| iCal import (absences and vacation from any calendar) | Done |
| Team overview — who is in office / on vacation today | Done |

### GDPR & Legal

| Feature | Status |
|---|---|
| Art. 20 — full personal data export as JSON | Done |
| Art. 17 — account anonymization (PII erasure, bookings kept per §147 AO) | Done |
| Hard account deletion with CASCADE | Done |
| DDG §5 Impressum — admin-editable, always publicly accessible | Done |
| Privacy policy settings (GDPR retention days configurable) | Done |
| Legal templates: Impressum, Datenschutz, AGB, AVV | Done |

### Security

| Feature | Status |
|---|---|
| Passwords hashed with bcrypt (12 rounds, configurable) | Done |
| Rate limiting: login/register 10/min, password reset 5/15min | Done |
| Audit log for all write operations | Done |
| Input validation on every endpoint (Laravel validator) | Done |
| Vehicle photo content validated via PHP GD (prevents polyglot attacks) | Done |
| Admin-only routes enforce role check independent of middleware | Done |

### Administration

| Feature | Status |
|---|---|
| Admin dashboard with live occupancy stats | Done |
| Booking heatmap by weekday and hour | Done |
| Admin reports (bookings by day, status, type) | Done |
| Admin CSV export of all bookings | Done |
| Announcements system (info, warning, error, success) | Done |
| Branding: custom logo, company name, primary color | Done |
| Webhooks (outbound event notifications) | Done |
| Web Push notification subscriptions | Done |
| Health endpoints (`/health/live`, `/health/ready`) | Done |

### Deployment

| Feature | Status |
|---|---|
| Docker single-container (PHP 8.3 + Apache, zero-config) | Done |
| Docker Compose with MySQL 8 | Done |
| Shared hosting (cPanel, FTP + browser installer wizard) | Done |
| VPS / LAMP / LEMP (Ubuntu 24.04) | Done |
| PaaS (Railway, Render, Fly.io) | Done |
| Kubernetes deployment manifests | Planned |
| Dark mode / light mode / system preference | Done |
| Mobile-responsive React 19 frontend | Done |

---

## Quick Start (3 commands)

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
docker compose up -d
```

> **Note:** The first `docker compose up -d` builds the PHP + Node.js image from source.
> This takes **2–5 minutes** on first run. Subsequent starts are instant (image is cached).
> Watch progress: `docker compose logs -f app`

Open **http://localhost:8080** in your browser.

Default credentials (Docker): `admin@parkhub.local` / `admin` — **change immediately after login**.

The container entrypoint automatically:
1. Generates the Laravel `APP_KEY` if not set
2. Runs database migrations
3. Creates a default admin account (`admin@parkhub.local` / `admin`)

To use custom credentials from the start:
```bash
PARKHUB_ADMIN_EMAIL=you@example.com PARKHUB_ADMIN_PASSWORD=mypassword docker compose up -d
```

To start with pre-seeded German demo data (10 lots, 200 users, ~3,500 bookings):
```bash
DEMO_MODE=true docker compose up -d
```

---

## Live Demo

Self-hosted — deploy your own instance with Docker Compose (see Quick Start below).

To start with pre-seeded German demo data (10 lots, 200 users, ~3,500 bookings):

```bash
DEMO_MODE=true docker compose up -d
```

Login: `admin@parkhub.local` / `admin`

Screenshots are available in the `docs/screenshots/` directory.

---

## Architecture

```
Browser (React 19 SPA — TypeScript + Tailwind CSS)
  |  Served from /public/build/
  |  Bearer token (Laravel Sanctum, 7-day expiry)
  |  JSON REST API
  v
Laravel 12  (PHP 8.3, Apache)
  |
  +-- /api/v1/*    REST API (auth required, role-enforced)
  +-- /api/*       Legacy routes (backwards-compatible)
  +-- /health/*    Kubernetes liveness + readiness probes
  +-- /api/v1/legal  Public legal pages (DDG §5 Impressum)
  |
  +-- SQLite   (DB_CONNECTION=sqlite — development, single-node)
  +-- MySQL 8  (DB_CONNECTION=mysql — production, Docker Compose)
  |
  +-- Queue worker  (email notifications, push notifications)
  +-- Storage disk  (vehicle photos, branding logo)
```

No external service is called at runtime. SMTP is optional — notifications fall back to the log driver if unconfigured. The entire stack runs on any PHP 8.3 host, including shared hosting.

---

## Demo Data

Seed a realistic German dataset (10 lots, 200 users, ~3,500 bookings over 30 days):

```bash
php artisan db:seed --class=ProductionSimulationSeeder
```

Lots include P+R Hauptbahnhof Munchen, Tiefgarage Marktplatz Stuttgart, Parkhaus Technologiepark Karlsruhe, and seven more German locations.

---

## Installation

| Platform | Guide | Difficulty |
|---|---|---|
| Docker Compose (recommended) | [docs/INSTALLATION.md](docs/INSTALLATION.md#docker-compose-recommended) | Easy |
| Shared hosting (cPanel, FTP) | [docs/SHARED-HOSTING.md](docs/SHARED-HOSTING.md) | Easy |
| VPS / LAMP / LEMP (Ubuntu 24.04) | [docs/INSTALLATION.md](docs/INSTALLATION.md#vps--lamp-stack) | Medium |
| PaaS (Railway, Render, Fly.io) | [docs/PAAS.md](docs/PAAS.md) | Easy |
| Kubernetes | [docs/INSTALLATION.md](docs/INSTALLATION.md#kubernetes) | Medium |

---

## GDPR & German Legal Compliance

ParkHub PHP is designed for on-premise deployment in German-regulated environments.
Because all data stays on your server, **no Auftragsverarbeitungsvertrag (AVV) with a cloud provider is needed**.

| GDPR Article | Endpoint | Notes |
|---|---|---|
| Art. 20 — Data portability | `GET /api/v1/users/me/export` | JSON archive: profile, bookings, absences, vehicles |
| Art. 17 — Right to erasure | `POST /api/v1/users/me/anonymize` | Anonymizes all PII fields |
| Art. 17 + §147 AO | Booking records | Retained 10 years (German tax law), PII anonymized |
| Hard delete | `DELETE /api/v1/users/me/delete` | Full CASCADE deletion including all records |
| DDG §5 — Impressum | `GET /api/v1/legal/impressum` | Public endpoint, admin-editable |

**Legal templates included** in `legal/`:
- `impressum-template.md` — DDG §5 Impressum
- `datenschutz-template.md` — Datenschutzerklarung
- `agb-template.md` — Allgemeine Geschaftsbedingungen
- `avv-template.md` — Auftragsverarbeitungsvertrag

See [docs/GDPR.md](docs/GDPR.md) for the full operator compliance checklist.

---

## Security

- **Passwords**: bcrypt with 12 rounds (configurable via `BCRYPT_ROUNDS`)
- **API tokens**: Laravel Sanctum with 7-day expiry
- **Rate limiting**: login/register 10/min, password reset 5/15 min
- **Audit log**: every mutation recorded (login, register, booking create/delete, GDPR erasure, admin actions)
- **Input validation**: Laravel validator on every endpoint — no raw user data reaches the database
- **File uploads**: vehicle photos validated through PHP GD before storage (prevents polyglot file attacks)
- **RBAC**: admin-only routes enforce role check in each controller method, independent of middleware

See [docs/SECURITY.md](docs/SECURITY.md) for the full security model.

---

## Configuration

Key environment variables (full list in [docs/CONFIGURATION.md](docs/CONFIGURATION.md)):

| Variable | Default | Purpose |
|---|---|---|
| `APP_URL` | `http://localhost` | Public URL — required for QR codes and emails |
| `APP_KEY` | (auto-generated) | 32-byte Laravel encryption key |
| `DB_CONNECTION` | `sqlite` | `sqlite` or `mysql` |
| `DB_HOST` | — | MySQL host (if `DB_CONNECTION=mysql`) |
| `DB_DATABASE` | — | MySQL database name |
| `DB_USERNAME` | — | MySQL username |
| `DB_PASSWORD` | — | MySQL password |
| `MAIL_MAILER` | `log` | `smtp`, `sendmail`, or `log` |
| `MAIL_HOST` | — | SMTP server hostname |
| `MAIL_PORT` | `587` | SMTP port |
| `QUEUE_CONNECTION` | `database` | `database`, `sync`, or `redis` |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost (higher = slower = safer) |
| `PARKHUB_ADMIN_EMAIL` | `admin@parkhub.local` | Initial admin email (Docker / `install.php`) |
| `PARKHUB_ADMIN_PASSWORD` | `admin` | Initial admin password — **change immediately** |
| `DEMO_MODE` | `false` | Set `true` to seed German demo data on container start |

---

## API Reference

Quick example — list parking lots:

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin"}' | jq -r '.tokens.access_token')

curl -H "Authorization: Bearer $TOKEN" http://localhost:8080/api/v1/lots
```

See [docs/API.md](docs/API.md) for the complete REST API reference with curl examples.
The API is compatible with the Rust backend — both use the same `/api/v1/*` route structure.

---

## Documentation Index

| Document | Purpose |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Full installation guide (Docker, VPS, K8s) |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Every `.env` variable documented |
| [docs/API.md](docs/API.md) | Full REST API reference with curl examples |
| [docs/GDPR.md](docs/GDPR.md) | Operator DSGVO compliance guide |
| [docs/SECURITY.md](docs/SECURITY.md) | Security model and responsible disclosure |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) | Development setup and PR process |
| [docs/DOCKER.md](docs/DOCKER.md) | Docker-specific deployment guide |
| [docs/VPS.md](docs/VPS.md) | VPS / LAMP manual deployment |
| [docs/SHARED-HOSTING.md](docs/SHARED-HOSTING.md) | Shared hosting (FTP, install.php wizard) |
| [docs/PAAS.md](docs/PAAS.md) | PaaS deployment (Railway, Render, Fly.io) |
| [legal/impressum-template.md](legal/impressum-template.md) | German Impressum template (DDG §5) |
| [legal/datenschutz-template.md](legal/datenschutz-template.md) | Datenschutzerklarung template |
| [legal/agb-template.md](legal/agb-template.md) | AGB template |
| [legal/avv-template.md](legal/avv-template.md) | Auftragsverarbeitungsvertrag template |

---

## License

MIT — see [LICENSE](LICENSE).

---

## Contributing

Contributions welcome. See [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for the development setup
(PHPUnit/Pest testing, code style), and the PR process.

Bug reports and feature requests: use the [GitHub issue tracker](https://github.com/nash87/parkhub-php/issues).
