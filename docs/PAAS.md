# PaaS Deployment (Railway, Render, Fly.io)

Deploy ParkHub PHP with one click on popular PaaS platforms.

## Railway

### One-Click Deploy

[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/parkhub-php)

### Manual Setup

1. Fork this repo on GitHub
2. Go to [railway.app](https://railway.app) and create new project
3. Select "Deploy from GitHub Repo"
4. Pick your fork
5. Railway auto-detects `railway.toml` and deploys

**Add MySQL (optional):**
1. In your Railway project, click "New" → "Database" → "MySQL"
2. Copy the connection variables
3. Set in your service's variables:
   ```
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   ```

## Render

1. Fork this repo
2. Go to [render.com](https://render.com) → New Web Service
3. Connect your GitHub repo
4. Render detects `render.yaml` automatically
5. Deploy

## Fly.io

```bash
# Install flyctl
curl -L https://fly.io/install.sh | sh

# Deploy
fly launch  # Uses fly.toml
fly deploy

# Add persistent storage for SQLite
fly volumes create parkhub_data --size 1
```

Update `fly.toml` to mount the volume:
```toml
[mounts]
  source = "parkhub_data"
  destination = "/var/www/html/database"
```

## Environment Variables (All Platforms)

| Variable | Required | Default |
|----------|----------|---------|
| `APP_KEY` | Auto-generated | — |
| `APP_URL` | Set to your domain | — |
| `DB_CONNECTION` | No | `sqlite` |
| `DB_HOST` | If MySQL | — |
| `DB_DATABASE` | If MySQL | — |
| `DB_USERNAME` | If MySQL | — |
| `DB_PASSWORD` | If MySQL | — |
