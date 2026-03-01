# PaaS Deployment — ParkHub PHP

Deploy ParkHub PHP on popular Platform-as-a-Service providers.

> **Note**: PaaS deployments are convenient for evaluation and prototyping.
> For production use in German or EU environments with GDPR requirements,
> prefer an on-premise or private cloud deployment for full data sovereignty.
> See [docs/GDPR.md](GDPR.md) for the operator compliance guide.

---

## Table of Contents

- [Railway](#railway)
- [Render](#render)
- [Fly.io](#flyio)
- [Koyeb](#koyeb)
- [Common Environment Variables](#common-environment-variables)

---

## Railway

### Manual Setup

1. Fork the repository on GitHub
2. Go to [railway.app](https://railway.app) and create a new project
3. Select "Deploy from GitHub Repo" and pick your fork
4. Railway auto-detects the `Dockerfile` and builds the image

**Add MySQL (optional):**

1. In your Railway project, click "New" → "Database" → "MySQL"
2. Set the following in your service's Variables tab:

```
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

**Required variables** (set in the Railway dashboard):

```
APP_KEY=base64:...    # run: php artisan key:generate --show
APP_URL=https://your-app.railway.app
APP_ENV=production
APP_DEBUG=false
PARKHUB_ADMIN_EMAIL=admin@example.com
PARKHUB_ADMIN_PASSWORD=change-me-now
```

Migrations run automatically on startup via `docker-entrypoint.sh`.

---

## Render

`render.yaml` is included in the repository root. It configures a free-tier web service
that pulls the pre-built image from GitHub Container Registry (avoids re-running npm+composer
on every Render build).

### Steps

1. Fork the repository on GitHub
2. Go to [render.com](https://render.com) → "New Web Service"
3. Connect your GitHub fork — Render detects `render.yaml` automatically
4. Update the `APP_URL` value in `render.yaml` to your Render-assigned domain
   (e.g. `https://parkhub-php-demo.onrender.com`)
5. Deploy

> **Note**: The free tier on Render spins down after 15 minutes of inactivity.
> The first request after a cold start may take 30–60 seconds.

**Environment variables** (configured in `render.yaml` — edit before deploying):

```yaml
- key: APP_URL
  value: https://your-app.onrender.com   # ← update this
- key: PARKHUB_ADMIN_PASSWORD
  value: admin                           # ← change this
```

---

## Fly.io

```bash
# Install flyctl
curl -L https://fly.io/install.sh | sh

# Launch (uses fly.toml if present)
fly launch

# Set secrets
fly secrets set APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fly secrets set DB_PASSWORD="your-secure-password"
fly secrets set PARKHUB_ADMIN_PASSWORD="change-me-now"

# Persistent volume for SQLite
fly volumes create parkhub_data --size 1
```

Update `fly.toml` to mount the volume:

```toml
[mounts]
  source = "parkhub_data"
  destination = "/var/www/html/database"
```

```bash
fly deploy
```

---

## Koyeb

`koyeb.yaml` is included in the repository root for one-command Koyeb deployment.

### Prerequisites

```bash
# Install the Koyeb CLI
curl -fsSL https://raw.githubusercontent.com/koyeb/koyeb-cli/master/install.sh | sh

# Login
koyeb login
```

### Steps

1. In the Koyeb dashboard, set the following secrets before deploying:
   - `APP_KEY` — generate with: `php artisan key:generate --show`
   - `PARKHUB_ADMIN_PASSWORD` — your initial admin password

2. Update `APP_URL` in `koyeb.yaml` to your Koyeb app URL:
   ```yaml
   - key: APP_URL
     value: "https://parkhub-php-<your-org>.koyeb.app"
   ```

3. Deploy:
   ```bash
   koyeb app deploy --file koyeb.yaml
   ```

Koyeb uses the Frankfurt (`fra`) region by default — good for GDPR/EU data residency.

---

## Common Environment Variables

These variables apply on all PaaS platforms:

| Variable | Required | Notes |
|----------|----------|-------|
| `APP_KEY` | Yes | Generate with `php artisan key:generate --show` |
| `APP_URL` | Yes | Set to your platform-assigned domain or custom domain |
| `APP_ENV` | Yes | Always `production` |
| `APP_DEBUG` | Yes | Always `false` in production |
| `DB_CONNECTION` | No | `sqlite` (default, ephemeral) or `mysql` |
| `DB_HOST` | If MySQL | Database hostname |
| `DB_DATABASE` | If MySQL | Database name |
| `DB_USERNAME` | If MySQL | Database username |
| `DB_PASSWORD` | If MySQL | Database password |
| `PARKHUB_ADMIN_EMAIL` | No | Initial admin email (default: `admin@parkhub.local`) |
| `PARKHUB_ADMIN_PASSWORD` | No | Initial admin password (default: `admin`) — **change this** |
| `QUEUE_CONNECTION` | No | `sync` (default for PaaS — no worker needed) |
| `MAIL_MAILER` | No | `log` (default — no emails sent) or `smtp` |
| `DEMO_MODE` | No | `true` to seed German demo data on startup |

Full variable reference: [docs/CONFIGURATION.md](CONFIGURATION.md)
