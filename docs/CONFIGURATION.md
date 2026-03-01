# Configuration Reference — ParkHub PHP

All `.env` variables for ParkHub PHP, grouped by category.

Copy `.env.example` to `.env` and edit before first run.
In Docker, pass variables as container environment variables instead of using a file.

---

## Table of Contents

- [Application](#application)
- [Database](#database)
- [Session](#session)
- [Cache](#cache)
- [Queue](#queue)
- [Mail](#mail)
- [Logging](#logging)
- [Filesystem](#filesystem)
- [Frontend Build](#frontend-build)
- [SQLite vs MySQL](#sqlite-vs-mysql)
- [Minimal Production `.env`](#minimal-production-env)

---

## Application

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `APP_NAME` | `ParkHub` | No | Application display name. Shown in emails and the browser tab title. |
| `APP_ENV` | `local` | Yes | `local`, `production`, or `testing`. Always set `production` in live deployments. |
| `APP_KEY` | _(empty)_ | Yes | 32-byte base64-encoded encryption key. Generate with `php artisan key:generate`. Auto-generated in the Docker entrypoint. |
| `APP_DEBUG` | `true` | Yes | `true` in development only. **Set `false` in production** — exposing stack traces to users is a security risk. |
| `APP_URL` | `http://localhost` | Yes | Public base URL including scheme and port if non-standard. Used for QR code links, email links, and API responses. Example: `https://parking.company.com` |
| `APP_LOCALE` | `en` | No | Default locale for date formatting and translations. |
| `APP_FALLBACK_LOCALE` | `en` | No | Fallback locale when a translation is missing. |
| `APP_MAINTENANCE_DRIVER` | `file` | No | `file` or `cache`. Driver for maintenance mode state storage. |
| `BCRYPT_ROUNDS` | `12` | No | bcrypt hashing cost factor. Range 10–14. Higher = slower but more brute-force-resistant. 12 is the recommended production value. |

---

## Database

### SQLite (development / single-node)

```dotenv
DB_CONNECTION=sqlite
# All other DB_* variables are unused
```

The SQLite file is created at `database/database.sqlite`. No server process required.

> SQLite uses WAL mode and tolerates moderate read concurrency, but is **not suitable for
> multi-process deployments** (multiple PHP-FPM workers writing simultaneously).
> Use MySQL 8 for production.

### MySQL 8 (production)

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parkhub
DB_USERNAME=parkhub
DB_PASSWORD=your-secure-password
```

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION` | `sqlite` | `sqlite` or `mysql` |
| `DB_HOST` | `127.0.0.1` | MySQL hostname. Use `db` when running via Docker Compose. |
| `DB_PORT` | `3306` | MySQL port |
| `DB_DATABASE` | `laravel` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | _(empty)_ | Database password. Always set in production. |

---

## Session

| Variable | Default | Description |
|----------|---------|-------------|
| `SESSION_DRIVER` | `database` | `file`, `database`, `redis`, or `cookie`. `database` stores sessions in the `sessions` table — recommended. |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes. Inactive sessions expire after this period. |
| `SESSION_ENCRYPT` | `false` | Encrypt session data at rest in the sessions table. |
| `SESSION_PATH` | `/` | Session cookie path. |
| `SESSION_DOMAIN` | `null` | Session cookie domain. Set to `.yourdomain.com` in production to allow subdomains. |

---

## Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `CACHE_STORE` | `database` | `file`, `database`, `redis`, or `array`. `database` uses the `cache` table. |
| `REDIS_HOST` | `127.0.0.1` | Redis host (only needed if `CACHE_STORE=redis` or `QUEUE_CONNECTION=redis`) |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | `null` | Redis authentication password |
| `REDIS_CLIENT` | `phpredis` | `phpredis` (requires PHP `ext-redis`) or `predis` (pure PHP, no extension needed) |

---

## Queue

Email notifications (welcome email on registration, booking confirmation) are processed
asynchronously via the queue. Choose a driver based on your needs:

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `database` | `sync`, `database`, or `redis` |

**`sync`** — Jobs execute inline in the HTTP request. No worker needed. Blocks the response
for the duration of the SMTP call. Good for low-traffic installs or Kubernetes single-pod.

**`database`** — Jobs are stored in the `jobs` table and processed by a `php artisan queue:work`
worker process. Recommended for production.

**`redis`** — Like `database` but uses Redis. Higher throughput, requires Redis.

---

## Mail

| Variable | Default | Description |
|----------|---------|-------------|
| `MAIL_MAILER` | `log` | Transport: `log` (writes to log file, no emails sent), `smtp`, `sendmail`, `mailgun`, `ses` |
| `MAIL_SCHEME` | `null` | TLS scheme: `null` (no encryption), `tls` (STARTTLS on port 587), `ssl` (implicit TLS on port 465) |
| `MAIL_HOST` | `127.0.0.1` | SMTP server hostname |
| `MAIL_PORT` | `587` | SMTP port. Common: 587 (STARTTLS), 465 (SSL), 25 (plain) |
| `MAIL_USERNAME` | `null` | SMTP authentication username |
| `MAIL_PASSWORD` | `null` | SMTP authentication password |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Sender address shown in outgoing emails |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Sender name shown in outgoing emails |

### Example — Gmail SMTP

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_FROM_ADDRESS=parking@yourdomain.com
MAIL_FROM_NAME="ParkHub"
```

Use a Gmail App Password (16 characters, created in Google Account → Security → App Passwords).
Not your regular Gmail password. Requires 2FA to be enabled.

### Example — Mailgun

```dotenv
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Example — Amazon SES

```dotenv
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-key-id
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=eu-central-1
```

Note: If using SES or Mailgun as SMTP processors, they become data processors under DSGVO.
An Auftragsverarbeitungsvertrag (AVV) is required. A template is in `legal/avv-template.md`.

---

## Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_CHANNEL` | `stack` | Log channel: `stack`, `single`, `daily`, `syslog`, `errorlog`, `stderr` |
| `LOG_STACK` | `single` | Channels included when `LOG_CHANNEL=stack` |
| `LOG_LEVEL` | `warning` | Minimum log level: `debug`, `info`, `notice`, `warning`, `error`, `critical` |

In production, set `LOG_CHANNEL=daily` and `LOG_LEVEL=warning` to reduce log volume and
enable automatic log rotation by day.

---

## Filesystem

| Variable | Default | Description |
|----------|---------|-------------|
| `FILESYSTEM_DISK` | `local` | Default storage disk. `local` stores files under `storage/app/`. |

Vehicle photos are stored at `storage/app/vehicles/{uuid}.jpg`.
Branding logos are stored at `storage/app/public/branding/logo.{ext}`.

Run `php artisan storage:link` once after installation to create the `public/storage`
symlink so files in `storage/app/public/` are web-accessible.

---

## Frontend Build

| Variable | Default | Description |
|----------|---------|-------------|
| `VITE_APP_NAME` | `${APP_NAME}` | Exposed to React frontend as `import.meta.env.VITE_APP_NAME` |

---

## ParkHub-Specific Variables

These variables are read by `docker-entrypoint.sh` and `public/install.php` during first-run initialization.

| Variable | Default | Description |
|----------|---------|-------------|
| `PARKHUB_ADMIN_EMAIL` | `admin@parkhub.local` | Email address for the initial admin account created on first startup. Change before first run. |
| `PARKHUB_ADMIN_PASSWORD` | `admin` | Password for the initial admin account. **Always change this before exposing the instance to a network.** |
| `DEMO_MODE` | `false` | Set `true` to seed realistic German demo data (10 lots, 200 users, ~3,500 bookings) on container start. Intended for demo instances only — not for production. |

---

## SQLite vs MySQL

| Aspect | SQLite | MySQL 8 |
|--------|--------|---------|
| Server required | No | Yes |
| Multi-process safe | No (WAL mode for limited concurrency) | Yes |
| Max users (practical) | ~100 | Thousands |
| Admin heatmap query | Uses `strftime()` | Uses `DAYOFWEEK()` / `HOUR()` |
| Recommended for | Development, demos, small offices | Production |

The booking heatmap query in `AdminController` auto-detects the database driver and
uses the correct date functions for each.

---

## Minimal Production `.env`

```dotenv
APP_NAME="ParkHub"
APP_ENV=production
APP_KEY=base64:GENERATED_BY_PHP_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://parking.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parkhub
DB_USERNAME=parkhub
DB_PASSWORD=your-very-secure-database-password

SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=parking@yourdomain.com
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=parking@yourdomain.com
MAIL_FROM_NAME="ParkHub"

LOG_CHANNEL=daily
LOG_LEVEL=warning

BCRYPT_ROUNDS=12
```
