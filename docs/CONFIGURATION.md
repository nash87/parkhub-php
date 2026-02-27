# Configuration Reference

All `.env` variables for ParkHub PHP, grouped by category.

Copy `.env.example` to `.env` and edit before first run. The Docker container reads these values from environment variables passed at runtime.

---

## Application

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `APP_NAME` | `Laravel` | No | Display name (shown in emails and browser tab title) |
| `APP_ENV` | `local` | Yes | `local`, `production`, or `testing`. Set `production` in live deployments. |
| `APP_KEY` | _(empty)_ | Yes | 32-byte base64-encoded encryption key. Generate with `php artisan key:generate`. Auto-generated in Docker. |
| `APP_DEBUG` | `true` | Yes | `true` in development, **`false` in production**. Exposing debug output to users is a security risk. |
| `APP_URL` | `http://localhost` | Yes | Public base URL including scheme and port (if non-standard). Used to generate QR code links and email links. Example: `https://parking.company.com` |
| `APP_LOCALE` | `en` | No | Default locale for translations and date formatting. |
| `APP_FALLBACK_LOCALE` | `en` | No | Fallback locale when a translation is missing. |
| `APP_MAINTENANCE_DRIVER` | `file` | No | `file` or `cache`. Driver for maintenance mode state. |
| `BCRYPT_ROUNDS` | `12` | No | Number of bcrypt hashing rounds. 10–14 is the practical range. Higher is slower but more secure. |

---

## Database

### SQLite (development / single-node)

```dotenv
DB_CONNECTION=sqlite
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD are unused
```

The SQLite file is created at `database/database.sqlite`. No server process is needed.

**Note:** SQLite is unsuitable for multi-process deployments (multiple web workers writing simultaneously). Use MySQL for production.

### MySQL 8 (production / Docker Compose)

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
| `DB_HOST` | `127.0.0.1` | MySQL host. Use `db` when running via Docker Compose. |
| `DB_PORT` | `3306` | MySQL port |
| `DB_DATABASE` | `laravel` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | _(empty)_ | Database password |

---

## Session

| Variable | Default | Description |
|----------|---------|-------------|
| `SESSION_DRIVER` | `database` | `file`, `database`, `redis`, or `cookie`. `database` stores sessions in the `sessions` table. |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes. Inactive sessions expire after this. |
| `SESSION_ENCRYPT` | `false` | Encrypt session data at rest. |
| `SESSION_PATH` | `/` | Cookie path. |
| `SESSION_DOMAIN` | `null` | Cookie domain. Set to your domain in production. |

---

## Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `CACHE_STORE` | `database` | `file`, `database`, `redis`, or `array`. `database` uses the `cache` table. |
| `REDIS_HOST` | `127.0.0.1` | Redis host (if `CACHE_STORE=redis` or `QUEUE_CONNECTION=redis`) |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | `null` | Redis password |
| `REDIS_CLIENT` | `phpredis` | `phpredis` (requires ext-redis) or `predis` (pure PHP, no extension needed) |

---

## Queue

Email notifications (welcome email, booking confirmation) are processed by the queue. Choose a driver:

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `database` | `sync` (inline, no worker), `database` (async, requires worker), `redis` (async, requires Redis + worker) |

**`sync`**: Emails are sent inline during the HTTP request. Simple but blocks the response for the duration of the SMTP call. Good for low-traffic installs.

**`database`**: Jobs are stored in the `jobs` table and processed by a `php artisan queue:work` worker. Recommended for production.

**`redis`**: Like `database` but uses Redis for the job queue. Higher throughput.

---

## Mail

| Variable | Default | Description |
|----------|---------|-------------|
| `MAIL_MAILER` | `log` | `log` (dev: writes to log file), `smtp`, `sendmail`, `mailgun`, `ses` |
| `MAIL_SCHEME` | `null` | `null`, `tls`, or `ssl`. Use `tls` for port 587 (STARTTLS), `ssl` for port 465. |
| `MAIL_HOST` | `127.0.0.1` | SMTP server hostname |
| `MAIL_PORT` | `2525` | SMTP port. Common values: 587 (STARTTLS), 465 (SSL), 25 (plain) |
| `MAIL_USERNAME` | `null` | SMTP authentication username |
| `MAIL_PASSWORD` | `null` | SMTP authentication password |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | From address shown in outgoing emails |
| `MAIL_FROM_NAME` | `${APP_NAME}` | From name shown in outgoing emails |

### Example — Gmail SMTP

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=parking@yourdomain.com
MAIL_FROM_NAME="ParkHub"
```

Use a Gmail App Password, not your account password. Enable 2FA first.

### Example — Mailgun

```dotenv
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxx
```

---

## Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_CHANNEL` | `stack` | Log channel: `stack`, `single`, `daily`, `syslog`, `errorlog`, `stderr` |
| `LOG_STACK` | `single` | Channels to include when `LOG_CHANNEL=stack` |
| `LOG_LEVEL` | `debug` | Minimum log level: `debug`, `info`, `notice`, `warning`, `error`, `critical` |

In production, set `LOG_LEVEL=warning` to reduce log volume.

---

## Filesystem

| Variable | Default | Description |
|----------|---------|-------------|
| `FILESYSTEM_DISK` | `local` | Default filesystem disk for file storage. `local` stores under `storage/app/`. |

Vehicle photos and branding logos are stored under `storage/app/vehicles/` and `storage/app/public/branding/` respectively.

Run `php artisan storage:link` once after installation to create the `public/storage` symlink for serving public files.

---

## Frontend Build

| Variable | Default | Description |
|----------|---------|-------------|
| `VITE_APP_NAME` | `${APP_NAME}` | Exposed to the React frontend as `import.meta.env.VITE_APP_NAME` |

---

## SQLite vs. MySQL Differences

| Aspect | SQLite | MySQL 8 |
|--------|--------|---------|
| Server required | No | Yes |
| Multi-process safe | No (WAL mode OK for low concurrency) | Yes |
| Performance | Good for < 100 users | Excellent |
| Admin heatmap query | Uses `strftime()` | Uses `DAYOFWEEK()` / `HOUR()` |
| Migration notes | None | Ensure `utf8mb4` charset |
| Production suitability | Small / single-node only | Recommended |

The heatmap query in `AdminController` automatically detects the database driver and uses the correct date functions for each.

---

## Minimal Production `.env`

```dotenv
APP_NAME="ParkHub"
APP_ENV=production
APP_KEY=base64:GENERATED_BY_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://parking.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parkhub
DB_USERNAME=parkhub
DB_PASSWORD=your-very-secure-password

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
