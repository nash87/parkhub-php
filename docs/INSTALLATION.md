# Installation Guide

Detailed installation instructions for every supported platform.

---

## Prerequisites

| Requirement | Minimum | Notes |
|-------------|---------|-------|
| PHP | 8.3 | Extensions: `pdo`, `pdo_mysql`, `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath` |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/) |
| Node.js | 20 LTS | Required only if building the frontend yourself |
| npm | 10+ | Bundled with Node 20 |
| MySQL | 8.0+ | Or use SQLite (no server required) |
| Web server | Apache 2.4 or Nginx | Apache: `mod_rewrite` required |

SQLite requires no separate server installation — the database is a single file at `database/database.sqlite`.

---

## Docker Compose (Recommended)

The fastest path to a production-ready deployment with MySQL persistence.

### Step 1 — Clone and start

```bash
git clone https://github.com/your-repo/parkhub-php.git
cd parkhub-php
docker compose up -d
```

This starts two containers:
- `app` — PHP 8.3 + Apache on port 8080
- `db` — MySQL 8.0 with a named volume for persistence

### Step 2 — Open the setup wizard

Visit http://localhost:8080. The setup wizard will guide you through:
1. Company name and use case
2. First admin account creation (the container creates a default `admin`/`admin` account automatically — change it immediately)
3. Creating your first parking lot

### Step 3 — Configure production settings

Edit `docker-compose.yml` before going live:

```yaml
environment:
  - APP_URL=https://parking.yourdomain.com
  - DB_PASSWORD=your-secure-password-here
  - APP_DEBUG=false
```

Add a reverse proxy (Nginx, Caddy, or Traefik) for HTTPS termination.

### Updating

```bash
git pull
docker compose build --no-cache
docker compose up -d
```

Migrations run automatically on each container start via the entrypoint script.

---

## Docker (SQLite — Simplest Single Container)

No MySQL needed. All data is stored in a single file inside the container volume.

```bash
docker build -t parkhub-php .
docker run -d \
  -p 8080:80 \
  -e APP_URL=http://localhost:8080 \
  -v parkhub-storage:/var/www/html/storage \
  -v parkhub-db:/var/www/html/database \
  --name parkhub \
  parkhub-php
```

The named volumes ensure data survives container restarts and upgrades.

---

## VPS / LAMP Stack

Tested on Ubuntu 24.04 LTS. Adjust package names for Debian or Rocky Linux as needed.

### Step 1 — Install dependencies

```bash
sudo apt update && sudo apt install -y \
  php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-sqlite3 \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath \
  apache2 libapache2-mod-php8.3 \
  composer \
  nodejs npm \
  unzip git
```

Enable required Apache modules:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Step 2 — Clone and install

```bash
cd /var/www
sudo git clone https://github.com/your-repo/parkhub-php.git parkhub
cd parkhub

# PHP dependencies
sudo composer install --no-dev --optimize-autoloader

# Frontend (pre-built assets are in the repo; rebuild only if you modified the frontend)
# npm ci && npm run build
```

### Step 3 — Configure environment

```bash
sudo cp .env.example .env
sudo php artisan key:generate
```

Edit `/var/www/parkhub/.env`:

```dotenv
APP_NAME=ParkHub
APP_ENV=production
APP_DEBUG=false
APP_URL=https://parking.yourdomain.com

DB_CONNECTION=sqlite
# For MySQL, see the MySQL section below

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=parking@yourdomain.com
MAIL_FROM_NAME="ParkHub"

QUEUE_CONNECTION=database
```

### Step 4 — Database setup

For SQLite (simplest):

```bash
sudo touch database/database.sqlite
sudo php artisan migrate --force
```

For MySQL:

```bash
sudo mysql -e "
  CREATE DATABASE parkhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'parkhub'@'localhost' IDENTIFIED BY 'your-secure-password';
  GRANT ALL PRIVILEGES ON parkhub.* TO 'parkhub'@'localhost';
  FLUSH PRIVILEGES;
"
```

Update `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parkhub
DB_USERNAME=parkhub
DB_PASSWORD=your-secure-password
```

Then: `sudo php artisan migrate --force`

### Step 5 — Set permissions

```bash
sudo chown -R www-data:www-data /var/www/parkhub
sudo chmod -R 755 storage bootstrap/cache
sudo php artisan storage:link
```

### Step 6 — Apache virtual host

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

### Step 7 — HTTPS with Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d parking.yourdomain.com
```

### Step 8 — Create admin account

If no admin exists after migration:

```bash
sudo php artisan tinker --execute="
\App\Models\User::create([
    'username' => 'admin',
    'email'    => 'admin@yourdomain.com',
    'password' => bcrypt('your-secure-password'),
    'name'     => 'Admin',
    'role'     => 'admin',
    'is_active'=> true,
    'preferences' => json_encode(['language' => 'en', 'theme' => 'system']),
]);
echo 'Admin created';
"
```

---

## Email Setup

Email notifications (welcome email on registration, booking confirmation) are sent via the Laravel queue. Configure SMTP in `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=parking@yourdomain.com
MAIL_FROM_NAME="ParkHub"
```

Set `QUEUE_CONNECTION=database` (default) to process emails asynchronously. If you set `QUEUE_CONNECTION=sync`, emails are sent inline (blocking, simpler, no worker needed).

---

## Queue Worker Setup

The database queue driver stores jobs in the `jobs` table. Run a persistent worker:

### Supervisor (recommended for production)

Install Supervisor:

```bash
sudo apt install -y supervisor
```

Create `/etc/supervisor/conf.d/parkhub-queue.conf`:

```ini
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

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start parkhub-queue
```

### Systemd alternative

See `docs/VPS.md` for a ready-made systemd unit file.

---

## Kubernetes

Kubernetes deployment manifests are planned. In the interim, deploy the Docker image as a standard Deployment + Service:

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
      containers:
        - name: parkhub
          image: your-registry/parkhub-php:latest
          ports:
            - containerPort: 80
          env:
            - name: APP_URL
              value: "https://parking.yourdomain.com"
            - name: DB_CONNECTION
              value: "mysql"
            - name: DB_HOST
              value: "mysql-service"
            - name: DB_DATABASE
              value: "parkhub"
            - name: DB_USERNAME
              valueFrom:
                secretKeyRef:
                  name: parkhub-db-secret
                  key: username
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: parkhub-db-secret
                  key: password
          volumeMounts:
            - name: storage
              mountPath: /var/www/html/storage
      volumes:
        - name: storage
          persistentVolumeClaim:
            claimName: parkhub-storage
```

Key considerations for Kubernetes:
- Mount a PVC at `/var/www/html/storage` for uploaded files (vehicle photos, branding logo).
- If using `DB_CONNECTION=sqlite`, mount a PVC at `/var/www/html/database` for the SQLite file.
- Use `QUEUE_CONNECTION=sync` to avoid needing a separate queue worker pod, or run a second pod with `CMD ["php", "artisan", "queue:work"]`.
- The health endpoints `GET /api/v1/health/live` and `GET /api/v1/health/ready` are available for readiness and liveness probes.

---

## First Run Checklist

After installation, verify:

- [ ] Visit `APP_URL` in a browser — the login page renders
- [ ] Log in with admin credentials
- [ ] Change the default admin password (Admin panel or `PATCH /api/v1/users/me/password`)
- [ ] Create at least one parking lot (Admin → Lots → New)
- [ ] Add slots to the lot
- [ ] Test email by registering a new user (if `MAIL_MAILER` is configured)
- [ ] Confirm the queue worker is running (`php artisan queue:monitor`)
- [ ] Set `APP_DEBUG=false` and `APP_ENV=production` in `.env`
- [ ] Run `php artisan config:cache && php artisan route:cache` to cache config

---

## Updating

```bash
cd /var/www/parkhub
sudo git pull
sudo composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo php artisan config:cache
sudo php artisan route:cache
sudo systemctl reload apache2
```
