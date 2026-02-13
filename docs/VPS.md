# VPS Manual Deployment

Deploy ParkHub PHP on a fresh Ubuntu/Debian VPS.

## Requirements

- Ubuntu 22.04+ or Debian 12+
- 512 MB RAM minimum
- Root or sudo access

## Quick Setup

```bash
# Install dependencies
sudo apt update && sudo apt install -y php8.3 php8.3-{cli,fpm,mysql,sqlite3,mbstring,xml,curl,zip,gd,bcmath} \
  apache2 libapache2-mod-php8.3 mysql-server composer unzip git

# Clone and install
cd /var/www
sudo git clone https://github.com/your-repo/parkhub-php.git parkhub
cd parkhub

# PHP dependencies
sudo composer install --no-dev --optimize-autoloader

# Build frontend (if not pre-built)
# sudo apt install -y nodejs npm
# npm ci && npm run build

# Configure
sudo cp .env.example .env
sudo php artisan key:generate

# Database (SQLite)
sudo touch database/database.sqlite
sudo php artisan migrate

# Permissions
sudo chown -R www-data:www-data /var/www/parkhub
sudo chmod -R 755 storage bootstrap/cache
```

## Apache Virtual Host

```bash
sudo tee /etc/apache2/sites-available/parkhub.conf << 'EOF'
<VirtualHost *:80>
    ServerName parking.yourdomain.com
    DocumentRoot /var/www/parkhub/public

    <Directory /var/www/parkhub/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/parkhub-error.log
    CustomLog ${APACHE_LOG_DIR}/parkhub-access.log combined
</VirtualHost>
EOF

sudo a2ensite parkhub
sudo a2enmod rewrite
sudo systemctl reload apache2
```

## MySQL Setup (Optional)

```bash
sudo mysql -e "CREATE DATABASE parkhub; CREATE USER 'parkhub'@'localhost' IDENTIFIED BY 'your-password'; GRANT ALL ON parkhub.* TO 'parkhub'@'localhost'; FLUSH PRIVILEGES;"
```

Update `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=parkhub
DB_USERNAME=parkhub
DB_PASSWORD=your-password
```

Then: `sudo php artisan migrate`

## HTTPS with Let's Encrypt

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d parking.yourdomain.com
```

## Systemd (Optional Queue Worker)

```bash
sudo tee /etc/systemd/system/parkhub-queue.service << 'EOF'
[Unit]
Description=ParkHub Queue Worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/parkhub
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl enable --now parkhub-queue
```

## Updating

```bash
cd /var/www/parkhub
sudo git pull
sudo composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo php artisan config:cache
sudo php artisan route:cache
```
