# Shared Hosting Deployment Guide

Deploy ParkHub PHP on any shared hosting provider — no terminal required.

## Supported Providers

ParkHub PHP requires **PHP 8.3**. Verify that your host supports PHP 8.3 before deploying.

| Provider | Free Tier | PHP 8.3 | MySQL | Notes |
|----------|-----------|---------|-------|-------|
| InfinityFree | Yes | Check panel | Yes | Recommended free option — verify PHP version in control panel |
| Byet / iFastNet | Yes | Check panel | Yes | Same backend as InfinityFree |
| AeonFree | Yes | Check panel | Yes | Smaller community |
| Strato | Paid | Yes (8.3+) | Yes | German provider, reliable, GDPR-friendly |
| IONOS | Paid | Yes (8.3+) | Yes | Good EU hosting, supports PHP 8.3 |
| All-Inkl | Paid | Yes (8.3+) | Yes | Popular in DACH region |
| Hostinger | Paid | Yes (8.3+) | Yes | Budget-friendly |

## Quick Start

### Option A: One-Click Installer (Recommended)

1. Download the latest release ZIP or run `./deploy-shared-hosting.sh` to build
2. Upload all files to your hosting via FTP
3. Visit `https://yourdomain.com/install.php`
4. Follow the setup wizard
5. Delete `install.php` after setup

### Option B: Manual Setup

Follow the detailed steps below.

---

## Step-by-Step Guide

### 1. Build the Package

On your local machine (requires PHP 8.3, Composer, Node.js):

```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
./deploy-shared-hosting.sh
```

This creates `parkhub-php-shared-hosting.zip`.

### 2. Upload via FTP

1. **Download FileZilla** from [filezilla-project.org](https://filezilla-project.org/)
2. Connect to your hosting:
   - **Host:** Your FTP hostname (e.g., `ftpupload.net` for InfinityFree)
   - **Username/Password:** From your hosting control panel
   - **Port:** 21
3. Navigate to your web root:
   - InfinityFree: `/htdocs/`
   - Byet: `/htdocs/`
   - Most hosts: `/public_html/`
4. Upload the entire ZIP contents

**Important:** The `public/` folder contents should be accessible from your domain. If your host doesn't support subdirectory routing, upload the contents of `public/` to the web root, and everything else one level above.

### 3. Directory Structure on Hosting

```
/your-hosting-root/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          ← Domain points here
│   ├── index.php
│   ├── install.php
│   ├── .htaccess
│   └── assets/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env.example
└── .htaccess        ← Redirects to public/
```

### 4. Set File Permissions

Via your hosting's File Manager or FTP:

```
storage/          → 755 (or 775)
bootstrap/cache/  → 755 (or 775)
database/         → 755 (or 775)
```

### 5. Run the Installer

Visit `https://yourdomain.com/install.php` in your browser.

The installer will:
- Check PHP version and extensions
- Let you configure database (SQLite or MySQL)
- Create your admin account
- Run database migrations
- Generate app key

### 6. Database Setup (MySQL option)

If you choose MySQL instead of SQLite:

1. Go to your hosting's **phpMyAdmin** or database panel
2. Create a new database (e.g., `parkhub`)
3. Note the credentials:
   - Database name
   - Username
   - Password
   - Host (usually `localhost` or `sql.yourhost.com`)
4. Enter these in the installer

### 7. Post-Installation

- **Delete `install.php`** for security
- Bookmark your ParkHub URL
- Log in with your admin credentials
- Run the setup wizard to configure your parking lots

---

## Provider-Specific Instructions

### InfinityFree

1. Create account at [infinityfree.com](https://www.infinityfree.com/)
2. Create a new hosting account
3. Note your FTP credentials from the control panel
4. Upload files to `/htdocs/`
5. Your site will be at `http://yourname.epizy.com` (or custom domain)
6. **MySQL:** Create database via control panel → MySQL Databases
7. phpMyAdmin is available in the control panel

**Limitations:**
- No SSH access
- 10 MB max upload per file (upload ZIP in parts if needed)
- Daily hit limit (50,000)

### Byet / iFastNet

1. Register at [byet.host](https://byet.host/)
2. Similar to InfinityFree (same backend)
3. Upload to `/htdocs/`
4. MySQL available via VistaPanel

### AeonFree

1. Register at [aeonfree.com](https://aeonfree.com/)
2. Use cPanel for file management
3. Upload to `/public_html/`
4. MySQL via cPanel → MySQL Databases

### Strato / IONOS / All-Inkl (Paid German Hosts)

These providers offer SSH access and better PHP support:

1. Upload via FTP or SSH
2. Point domain to `public/` directory via hosting panel
3. Create MySQL database via admin panel
4. Use the one-click installer or configure manually

---

## Troubleshooting

### "500 Internal Server Error"
- Check if `mod_rewrite` is enabled (most hosts have it)
- Verify `.htaccess` files are uploaded
- Check `storage/logs/laravel.log` for details
- Ensure `storage/` and `bootstrap/cache/` are writable

### "Page Not Found" for all routes
- `.htaccess` file might be missing
- `mod_rewrite` might be disabled
- Try adding `RewriteBase /` to `public/.htaccess`

### "Class not found" errors
- `vendor/` directory might be incomplete
- Re-upload or rebuild with `composer install --no-dev`

### Database connection errors
- Verify credentials in `.env`
- For shared hosting MySQL, the host is often NOT `localhost`
- Check your hosting panel for the correct MySQL hostname

### Blank white page
- Enable error display temporarily: add `APP_DEBUG=true` to `.env`
- Check PHP error logs in your hosting panel
- Ensure PHP 8.2+ is selected in your hosting PHP version settings

### File upload size issues
- Create/edit `public/.user.ini`:
  ```ini
  upload_max_filesize = 64M
  post_max_size = 64M
  memory_limit = 256M
  max_execution_time = 300
  ```

### SQLite "unable to open database file"
- Ensure `database/` directory is writable
- Ensure `database/database.sqlite` exists and is writable
- Some shared hosts don't support SQLite — use MySQL instead

### Mixed content / HTTPS issues
- Set `APP_URL=https://yourdomain.com` in `.env`
- Add `FORCE_HTTPS=true` if available
- Check if your host provides free SSL (most do)
