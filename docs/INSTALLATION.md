# Installation Guide — ParkHub PHP

> **Supported platforms**: Docker, VPS/LAMP, shared hosting, Kubernetes, PaaS.
> Docker Compose with MySQL is the recommended path for new deployments.

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Docker Compose (Recommended)](#docker-compose-recommended)
- [Docker — Single Container (SQLite)](#docker--single-container-sqlite)
- [VPS / LAMP Stack (Ubuntu 24.04)](#vps--lamp-stack-ubuntu-2404)
- [Kubernetes](#kubernetes)
- [Shared Hosting (FTP / cPanel)](#shared-hosting-ftp--cpanel)
- [PaaS (Railway, Render, Fly.io)](#paas-railway-render-flyio)
- [Email / Queue Setup](#email--queue-setup)
- [Backup Strategy](#backup-strategy)
- [Upgrade Guide](#upgrade-guide)
- [Health Check Endpoints](#health-check-endpoints)
- [First-Run Checklist](#first-run-checklist)

---

## Prerequisites

| Requirement | Minimum | Notes |
|-------------|---------|-------|
| PHP | 8.3 | Required extensions: `pdo`, `pdo_mysql`, `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath` |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/) |
| MySQL | 8.0+ | Or use SQLite (no server required) |
| Node.js | 20 LTS | Only needed if rebuilding the frontend from source |
| Web server | Apache 2.4 or Nginx | Apache: `mod_rewrite` must be enabled |

SQLite requires no separate server — the database is a single file at `database/database.sqlite`.

---

## Docker Compose (Recommended)

The fastest path to a production-ready instance with MySQL persistence.

### Step 1 — Clone and start

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
docker compose up -d
```

This starts two containers:
- `app` — PHP 8.3 + Apache on port 8080
- `db` — MySQL 8.0 with a named volume for persistence

### Step 2 — Open the setup wizard

Visit **http://localhost:8080**. The container entrypoint automatically:

1. Runs all pending database migrations
2. Creates a default admin account: username `admin`, password `admin`
3. Caches the Laravel configuration and routes

The setup wizard guides you through:
1. Company name and use case selection
2. Changing the default admin password (required)
3. Creating your first parking lot

### Step 3 — Configure for production

Edit `docker-compose.yml` before going live:

```yaml
environment:
  - APP_URL=https://parking.yourdomain.com
  - APP_DEBUG=false
  - DB_PASSWORD=your-very-secure-database-password
  - MAIL_MAILER=smtp
  - MAIL_HOST=smtp.yourprovider.com
  - MAIL_PORT=587
  - MAIL_USERNAME=parking@yourdomain.com
  - MAIL_PASSWORD=your-smtp-password
```

Add a reverse proxy (Nginx, Caddy, or Traefik) for HTTPS termination.

### Updating

```bash
git pull
docker compose build --no-cache
docker compose up -d
```

Migrations run automatically on container startup. No manual `php artisan migrate` needed.

---

## Docker — Single Container (SQLite)

For evaluation or low-traffic deployments with no MySQL:

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

The named volumes ensure data persists across container restarts and upgrades.

> SQLite is not recommended for production deployments with concurrent writes. Use MySQL 8 for production.

---

## VPS / LAMP Stack (Ubuntu 24.04)

Tested on Ubuntu 24.04 LTS. Adapt package names for Debian or Rocky Linux.

### Step 1 — Install PHP, Apache, and dependencies

```bash
sudo apt update && sudo apt install -y \
  php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-sqlite3 \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath \
  libapache2-mod-php8.3 apache2 \
  composer \
  git unzip

sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Step 2 — Clone and install

```bash
cd /var/www
sudo git clone https://github.com/nash87/parkhub-php.git parkhub
cd parkhub

# Install PHP dependencies
sudo composer install --no-dev --optimize-autoloader
```

> Pre-built frontend assets are included in the repository. Only run `npm ci && npm run build`
> if you have modified the frontend source.

### Step 3 — Configure the environment

```bash
sudo cp .env.example .env
sudo php artisan key:generate
```

Edit `/var/www/parkhub/.env`:

```dotenv
APP_NAME="ParkHub"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://parking.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parkhub
DB_USERNAME=parkhub
DB_PASSWORD=your-secure-password

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=parking@yourdomain.com
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=parking@yourdomain.com
MAIL_FROM_NAME="ParkHub"

QUEUE_CONNECTION=database
LOG_CHANNEL=daily
LOG_LEVEL=warning
BCRYPT_ROUNDS=12
```

### Step 4 — Create the MySQL database

```bash
sudo mysql -e "
  CREATE DATABASE parkhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'parkhub'@'localhost' IDENTIFIED BY 'your-secure-password';
  GRANT ALL PRIVILEGES ON parkhub.* TO 'parkhub'@'localhost';
  FLUSH PRIVILEGES;
"
```

### Step 5 — Run migrations and set permissions

```bash
sudo php artisan migrate --force
sudo php artisan storage:link

sudo chown -R www-data:www-data /var/www/parkhub
sudo chmod -R 755 storage bootstrap/cache
```

### Step 6 — Cache configuration for production

```bash
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache
```

### Step 7 — Apache virtual host

```bash
sudo tee /etc/apache2/sites-available/parkhub.conf << 'EOF'
<VirtualHost *:80>
    ServerName parking.yourdomain.com
    DocumentRoot /var/www/parkhub/public

    <Directory /var/www/parkhub/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/parkhub-error.log
    CustomLog ${APACHE_LOG_DIR}/parkhub-access.log combined
</VirtualHost>
EOF

sudo a2ensite parkhub
sudo systemctl reload apache2
```

### Step 8 — HTTPS with Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d parking.yourdomain.com
```

### Step 9 — Create admin account (if not auto-created)

```bash
sudo php artisan tinker --execute="
\App\Models\User::create([
    'username'    => 'admin',
    'email'       => 'admin@yourdomain.com',
    'password'    => bcrypt('your-strong-password'),
    'name'        => 'Admin',
    'role'        => 'admin',
    'is_active'   => true,
    'preferences' => json_encode(['language' => 'de', 'theme' => 'system']),
]);
echo 'Admin created\n';
"
```

---

## Kubernetes

Kubernetes deployment manifests are planned as a separate chart. In the interim,
use the Docker image as a standard Deployment:

### Namespace

```yaml
apiVersion: v1
kind: Namespace
metadata:
  name: parkhub
```

### Secret

```bash
kubectl create secret generic parkhub-secrets \
  --namespace parkhub \
  --from-literal=db-password='your-secure-password' \
  --from-literal=app-key='base64:YOUR_GENERATED_KEY'
```

### Deployment

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: parkhub
  namespace: parkhub
spec:
  replicas: 1
  selector:
    matchLabels:
      app: parkhub
  template:
    metadata:
      labels:
        app: parkhub
    spec:
      securityContext:
        runAsNonRoot: true
        runAsUser: 33    # www-data
        fsGroup: 33
      containers:
        - name: parkhub
          image: your-registry/parkhub-php:latest
          ports:
            - containerPort: 80
          env:
            - name: APP_URL
              value: "https://parking.yourdomain.com"
            - name: APP_ENV
              value: "production"
            - name: APP_DEBUG
              value: "false"
            - name: APP_KEY
              valueFrom:
                secretKeyRef:
                  name: parkhub-secrets
                  key: app-key
            - name: DB_CONNECTION
              value: "mysql"
            - name: DB_HOST
              value: "mysql-service"
            - name: DB_DATABASE
              value: "parkhub"
            - name: DB_USERNAME
              valueFrom:
                secretKeyRef:
                  name: parkhub-secrets
                  key: db-username
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: parkhub-secrets
                  key: db-password
            - name: QUEUE_CONNECTION
              value: "sync"     # Use sync to avoid a separate queue worker pod
          volumeMounts:
            - name: storage
              mountPath: /var/www/html/storage
          livenessProbe:
            httpGet:
              path: /api/v1/health/live
              port: 80
            initialDelaySeconds: 15
            periodSeconds: 30
          readinessProbe:
            httpGet:
              path: /api/v1/health/ready
              port: 80
            initialDelaySeconds: 10
            periodSeconds: 10
          resources:
            requests:
              memory: "256Mi"
              cpu: "100m"
            limits:
              memory: "512Mi"
              cpu: "500m"
          securityContext:
            allowPrivilegeEscalation: false
            capabilities:
              drop: [ALL]
      volumes:
        - name: storage
          persistentVolumeClaim:
            claimName: parkhub-storage
```

### PVC for file storage

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: parkhub-storage
  namespace: parkhub
spec:
  accessModes: [ReadWriteOnce]
  resources:
    requests:
      storage: 5Gi
```

**Key notes for Kubernetes:**
- Mount a PVC at `/var/www/html/storage` for vehicle photos and branding logos
- Use `QUEUE_CONNECTION=sync` to avoid needing a second queue-worker pod, or run a second Deployment with `command: ["php", "artisan", "queue:work"]`
- If using SQLite, also mount a PVC at `/var/www/html/database`
- Health endpoints: `GET /api/v1/health/live` and `GET /api/v1/health/ready`

---

## Shared Hosting (FTP / cPanel)

For shared hosting environments where you can upload files via FTP and access a browser.

### Step 1 — Build a deployable package

On your local machine:

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
bash deploy-shared-hosting.sh
```

This creates `parkhub-shared-hosting.zip` containing a production-ready build.

### Step 2 — Upload

1. Upload and extract `parkhub-shared-hosting.zip` to your hosting directory (e.g. `public_html/parking/`)
2. Set the web root to the `public/` subdirectory (or copy `public/` contents to the root)
3. Create a MySQL database via cPanel and note the credentials

### Step 3 — Configure

1. Copy `.env.example` to `.env` on the server (via FTP or the file manager)
2. Edit `.env` with your database credentials and `APP_URL`
3. Generate `APP_KEY` — if you cannot run CLI commands, generate a 32-byte base64 key:
   ```bash
   php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
   ```

### Step 4 — Install wizard

Visit `https://yourdomain.com/install.php` in your browser. The wizard runs migrations
and creates the default admin account.

> Delete `install.php` after completing the wizard.

---

## PaaS (Railway, Render, Fly.io)

> Note: PaaS deployments are convenient for evaluation and prototyping. For production
> use in German/EU environments with GDPR requirements, prefer an on-premise or private
> cloud deployment for full data sovereignty.

### Railway

1. Fork the repository on GitHub
2. Go to [railway.app](https://railway.app) → New Project → Deploy from GitHub Repo
3. Select your fork
4. Add a MySQL database: New → Database → MySQL
5. Set environment variables in the Railway dashboard:
   - `APP_URL` — your Railway-assigned domain (or custom domain)
   - `APP_KEY` — run `php artisan key:generate --show` locally to get one
   - `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from Railway MySQL
6. Deploy — migrations run automatically on startup

### Render

1. Fork the repository
2. New Web Service → connect your fork
3. Build command: `composer install --no-dev && npm ci && npm run build && php artisan key:generate --force`
4. Start command: `apache2-foreground` (or use the Dockerfile)
5. Add a PostgreSQL or MySQL add-on
6. Set environment variables

### Fly.io

```bash
curl -L https://fly.io/install.sh | sh

fly launch   # uses fly.toml if present
fly secrets set APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fly secrets set DB_PASSWORD="your-secure-password"

# Persistent volume for SQLite
fly volumes create parkhub_data --size 1
```

Update `fly.toml`:

```toml
[mounts]
  source = "parkhub_data"
  destination = "/var/www/html/database"
```

```bash
fly deploy
```

---

## Email / Queue Setup

Email notifications (welcome email on registration, booking confirmation) are sent
via the Laravel queue.

### SMTP configuration

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=parking@yourdomain.com
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=parking@yourdomain.com
MAIL_FROM_NAME="ParkHub"
```

### Queue driver

| Driver | Best for | Worker required |
|--------|----------|----------------|
| `sync` | Simple installs, low traffic | No |
| `database` | Production (jobs in `jobs` table) | Yes — see below |
| `redis` | High throughput | Yes + Redis |

### Queue worker with Supervisor (recommended for production)

```bash
sudo apt install -y supervisor
```

```ini
# /etc/supervisor/conf.d/parkhub-queue.conf
[program:parkhub-queue]
command=/usr/bin/php /var/www/parkhub/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/parkhub
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/parkhub-queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start parkhub-queue
```

### Auto-prune expired Sanctum tokens

Add to your cron scheduler (`crontab -u www-data -e`):

```cron
* * * * * php /var/www/parkhub/artisan schedule:run >> /dev/null 2>&1
```

Or schedule via `app/Console/Kernel.php`:

```php
$schedule->command('sanctum:prune-expired --hours=168')->daily();
```

---

## Backup Strategy

### Database backup

**MySQL**:

```bash
# Create a timestamped dump
mysqldump -u parkhub -p parkhub > /backups/parkhub-$(date +%Y%m%d).sql

# Restore
mysql -u parkhub -p parkhub < /backups/parkhub-YYYYMMDD.sql
```

**SQLite**:

```bash
cp /var/www/parkhub/database/database.sqlite /backups/parkhub-$(date +%Y%m%d).sqlite
```

### File storage backup (vehicle photos, branding logo)

```bash
tar czf /backups/parkhub-storage-$(date +%Y%m%d).tar.gz /var/www/parkhub/storage/app/
```

### Automated daily backup (cron)

```bash
# /etc/cron.daily/parkhub-backup
#!/bin/bash
DATE=$(date +%Y%m%d)
BACKUP_DIR=/backups/parkhub

mkdir -p $BACKUP_DIR

# Database
mysqldump -u parkhub -pYOUR_PASSWORD parkhub > $BACKUP_DIR/db-$DATE.sql

# Storage files
tar czf $BACKUP_DIR/storage-$DATE.tar.gz /var/www/parkhub/storage/app/

# Retain last 14 backups
find $BACKUP_DIR -name "*.sql" -mtime +14 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +14 -delete
```

---

## Upgrade Guide

### Docker Compose

```bash
cd parkhub-php
git pull
docker compose build --no-cache
docker compose up -d
```

Migrations run automatically on container startup.

### VPS (bare metal)

```bash
cd /var/www/parkhub

sudo git pull
sudo composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache
sudo systemctl reload apache2
```

### Before upgrading

Always back up the database before upgrading a production instance.

---

## Health Check Endpoints

| Endpoint | Purpose | Auth |
|----------|---------|------|
| `GET /api/v1/health/live` | Liveness — HTTP 200 if PHP process is running | None |
| `GET /api/v1/health/ready` | Readiness — HTTP 200 if database is reachable | None |

```bash
curl https://parking.yourdomain.com/api/v1/health/ready
# {"status":"ok","database":"ok"}
```

---

## First-Run Checklist

After installation:

- [ ] Login at `APP_URL` succeeds with admin credentials
- [ ] Admin password changed from the default `admin` to a strong unique password
- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`
- [ ] HTTPS active (certificate installed via certbot or reverse proxy)
- [ ] `php artisan config:cache` run (speeds up startup, required for production)
- [ ] At least one parking lot and slot created
- [ ] Email test: register a user and verify the welcome email is received
- [ ] Queue worker running (`php artisan queue:monitor`)
- [ ] `GET /api/v1/health/ready` returns HTTP 200
- [ ] Impressum filled in: Admin → Impressum (verify `/impressum` is publicly reachable)
- [ ] Privacy policy text entered: Admin → Privacy
- [ ] Backup strategy configured and tested
