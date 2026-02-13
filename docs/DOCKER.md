# Docker Deployment

## Quick Start

```bash
git clone https://github.com/your-repo/parkhub-php.git
cd parkhub-php
docker compose up -d
```

Visit http://localhost:8080 — the setup wizard will guide you.

## With SQLite (Simplest)

```bash
docker build -t parkhub-php .
docker run -d -p 8080:80 --name parkhub parkhub-php
```

## With MySQL (docker-compose)

```bash
docker compose up -d
```

This starts:
- **ParkHub** on port 8080
- **MySQL 8.0** with persistent storage

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_URL` | `http://localhost:8080` | Public URL |
| `APP_DEBUG` | `false` | Enable debug mode |
| `DB_CONNECTION` | `mysql` | `sqlite` or `mysql` |
| `DB_HOST` | `db` | MySQL hostname |
| `DB_DATABASE` | `parkhub` | Database name |
| `DB_USERNAME` | `parkhub` | Database user |
| `DB_PASSWORD` | `secret` | Database password |

## Production

For production, update `docker-compose.yml`:

```yaml
environment:
  - APP_URL=https://parking.yourdomain.com
  - DB_PASSWORD=your-secure-password
```

Add a reverse proxy (nginx/Caddy/Traefik) for HTTPS.

## Updating

```bash
git pull
docker compose build
docker compose up -d
```

Migrations run automatically on startup.
