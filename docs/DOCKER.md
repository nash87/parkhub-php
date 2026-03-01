# Docker Deployment — ParkHub PHP

Deploy ParkHub PHP using Docker or Docker Compose.
The Docker image contains PHP 8.3 + Apache and the pre-built React 19 frontend — no Node.js needed at runtime.

---

## Quick Start (3 commands)

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
docker compose up -d
```

Open **http://localhost:8080**. The setup wizard guides you through the first-run configuration.

> The first `docker compose up -d` builds the image from source. This takes **2–5 minutes**.
> Watch progress: `docker compose logs -f app`

Default credentials: `admin@parkhub.local` / `admin` — **change immediately after login**.

---

## Docker Compose (Recommended — with MySQL)

`docker compose up -d` starts four containers:

| Container | Purpose |
|-----------|---------|
| `app` | PHP 8.3 + Apache on port 8080 |
| `db` | MySQL 8.0 with a named volume for persistence |
| `worker` | Queue worker for email and webhook jobs |
| `scheduler` | Laravel task scheduler (every 60 s) |

Data persists in named Docker volumes:
- `app-storage` — vehicle photos, branding logo
- `db-data` — MySQL database files

### Updating

```bash
git pull
docker compose build --no-cache
docker compose up -d
```

Migrations run automatically on container startup.

---

## Single Container with SQLite (Simplest)

For evaluation, demos, or low-traffic single-node deployments:

```bash
docker build -t parkhub-php .
docker run -d \
  -p 8080:80 \
  -e APP_URL=http://localhost:8080 \
  -e DB_CONNECTION=sqlite \
  -v parkhub-storage:/var/www/html/storage \
  -v parkhub-db:/var/www/html/database \
  --name parkhub \
  parkhub-php
```

The named volumes ensure data persists across restarts and upgrades.

> SQLite is not recommended for production deployments with concurrent writes. Use MySQL 8 via Docker Compose for production.

---

## Pre-Built Image from GHCR

Pull the latest image from GitHub Container Registry instead of building locally:

```bash
docker pull ghcr.io/nash87/parkhub-php:latest

docker run -d \
  -p 8080:80 \
  -e APP_URL=http://localhost:8080 \
  -e DB_CONNECTION=sqlite \
  -v parkhub-storage:/var/www/html/storage \
  -v parkhub-db:/var/www/html/database \
  --name parkhub \
  ghcr.io/nash87/parkhub-php:latest
```

Images are tagged by branch (`main`), semver (`v1.2.0`), and short SHA (`sha-abc1234`).

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_URL` | `http://localhost:8080` | Public URL — required for QR codes and emails |
| `APP_DEBUG` | `false` | Never set `true` in production |
| `DB_CONNECTION` | `mysql` | `sqlite` or `mysql` |
| `DB_HOST` | `db` | MySQL hostname (use `db` in Docker Compose) |
| `DB_DATABASE` | `parkhub` | Database name |
| `DB_USERNAME` | `parkhub` | Database user |
| `DB_PASSWORD` | `secret` | Database password — **change for production** |
| `PARKHUB_ADMIN_EMAIL` | `admin@parkhub.local` | Initial admin email (first-run only) |
| `PARKHUB_ADMIN_PASSWORD` | `admin` | Initial admin password — **change before exposing** |
| `DEMO_MODE` | _(unset)_ | Set `true` to seed German demo data on startup |
| `QUEUE_CONNECTION` | `database` | `database` for async email/webhooks, `sync` for simplicity |
| `MAIL_MAILER` | `log` | `smtp` for production email, `log` writes to container logs |

Full reference: [docs/CONFIGURATION.md](CONFIGURATION.md)

---

## Production Configuration

Before going live, edit `docker-compose.yml` or pass env vars:

```yaml
environment:
  - APP_URL=https://parking.yourdomain.com
  - APP_DEBUG=false
  - APP_ENV=production
  - DB_PASSWORD=your-very-secure-database-password
  - PARKHUB_ADMIN_EMAIL=admin@yourdomain.com
  - PARKHUB_ADMIN_PASSWORD=change-me-now
  - MAIL_MAILER=smtp
  - MAIL_HOST=smtp.yourprovider.com
  - MAIL_PORT=587
  - MAIL_USERNAME=parking@yourdomain.com
  - MAIL_PASSWORD=your-smtp-password
```

Add a reverse proxy (Nginx, Caddy, or Traefik) in front of the `app` container for HTTPS termination.

---

## Health Checks

The container exposes two health endpoints (no authentication required):

```bash
# Liveness — is the PHP process running?
curl http://localhost:8080/api/v1/health/live
# {"status":"ok"}

# Readiness — is the database reachable?
curl http://localhost:8080/api/v1/health/ready
# {"status":"ok","database":"ok"}
```

Docker Compose uses `/api/v1/health/live` for the built-in healthcheck.

---

## Logs

```bash
# Application logs
docker compose logs -f app

# Queue worker logs
docker compose logs -f worker

# Laravel application log
docker exec parkhub-php-app-1 cat storage/logs/laravel.log
```
