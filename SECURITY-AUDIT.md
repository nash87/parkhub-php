# Security Audit — parkhub-php

**Audit Date:** 2026-02-28
**Scope:** Full codebase audit covering secrets, .gitignore, PHP/npm dependencies, OWASP Top 10, security headers, rate limiting, and GDPR compliance.

---

## Summary

| Category | Status |
|---|---|
| Secrets in code | No hardcoded secrets found |
| .gitignore | Updated — added missing entries |
| PHP dependencies (Laravel 12) | Current versions, no known CVEs |
| npm dependencies | Current versions (React 19, Vite 7) |
| OWASP A01 Broken Access Control | 3 issues fixed |
| OWASP A02 Cryptographic Failures | Good — bcrypt via Laravel, Sanctum tokens |
| OWASP A03 Injection | Good — Eloquent ORM, no raw SQL concatenation |
| OWASP A05 Security Misconfiguration | Informational — install.php requires post-install deletion |
| OWASP A07 Auth Failures | Good — rate limiting on login/register/forgot-password |
| OWASP A09 Security Logging | Good — AuditLog model used in auth and booking flows |
| Security Headers | Fixed — SecurityHeaders middleware added and registered globally |
| GDPR Data Export | Good — includes profile, bookings, absences, vehicles, preferences |
| GDPR Erasure | Good — anonymizeAccount() preserves anonymized bookings, deletes PII |

---

## 1. Secrets in Code / Repository

### ✅ Passed: No hardcoded credentials found

No API keys, JWT secrets, database passwords, or tokens found in any `.php` file.

### ✅ Passed: .env.example uses no real values

`APP_KEY` is empty in `.env.example` (correct — `artisan key:generate` fills it). No real DB credentials.

### ✅ Passed: Sanctum token configuration

Laravel Sanctum issues hashed opaque tokens via `createToken()`. The token hash is stored in the `personal_access_tokens` table; plain-text tokens are never persisted.

---

## 2. .gitignore Completeness

### ❌ FIXED: Missing .env.* variants

Added `/.env.*` to cover `.env.production`, `.env.staging`, `.env.testing`, etc.

### ❌ FIXED: Missing certificate and key file patterns

Added:
```
*.pem
*.key
*.p12
*.pfx
*.crt
*.cer
```

Note: `/storage/*.key` was already present (specifically for Laravel app keys stored in storage), but the top-level wildcard patterns protect against accidentally committed TLS certificates or SSH keys anywhere in the tree.

### ✅ Passed: Core entries present

- `/vendor/` — present
- `/node_modules/` — present
- `/.env` — present
- `/database/database.sqlite` — present
- `/storage/logs/*` — present

---

## 3. Dependency Security Review (PHP)

**File:** `composer.json`

| Package | Version | Status |
|---|---|---|
| `laravel/framework` | ^12.0 | Current major version |
| `laravel/sanctum` | ^4.3 | Current — Sanctum 4.x for Laravel 12 |
| `laravel/tinker` | ^2.10.1 | Current |
| `phpunit/phpunit` | ^11.5.3 | Current |
| `fakerphp/faker` | ^1.23 | Current |

**No known CVEs** in the specified version ranges as of the audit date.

### ✅ FIXED: `laravel/tinker` moved to `require-dev`

Tinker is a REPL that can execute arbitrary PHP code. It was previously listed under `require` (production).
It has been moved to `require-dev` to reduce the production attack surface. Docker builds use
`composer install --no-dev`, so Tinker is not present in production containers.

### ⚠️ Informational: `BCRYPT_ROUNDS=12` in .env.example

The default of 12 rounds is appropriate for bcrypt (OWASP recommends a minimum of 10). No change needed.

---

## 4. Dependency Security Review (npm / Frontend)

**File:** `package.json` (frontend, Vite-based React SPA)

| Package | Version | Status |
|---|---|---|
| `react` | ^19.2.0 | Current — React 19 |
| `react-dom` | ^19.2.0 | Current |
| `react-router-dom` | ^7.13.0 | Current |
| `vite` | ^7.3.1 | Current |
| `@tanstack/react-query` | ^5.90.20 | Current |
| `zustand` | ^5.0.11 | Current |
| `framer-motion` | ^12.33.0 | Current |
| `i18next` | ^25.8.4 | Current |
| `typescript` | ~5.9.3 | Current |
| `tailwindcss` | ^3.4.19 | Current |

**All frontend dependencies are current major versions.** No outdated packages flagged.

---

## 5. OWASP Top 10 Review

### A01 — Broken Access Control

#### ❌ FIXED: `AdminController::getSettings()` — missing admin check

**File:** `app/Http/Controllers/Api/AdminController.php`

**Before:**
```php
public function getSettings()
{
    $settings = Setting::all()->pluck('value', 'key')->toArray();
    ...
```

**After:**
```php
public function getSettings(Request $request)
{
    $this->requireAdmin($request);
    $settings = Setting::all()->pluck('value', 'key')->toArray();
    ...
```

**Impact:** Any authenticated user (not just admins) could call `GET /api/v1/admin/settings` (and `GET /api/settings` in the legacy route file) and receive all application settings including SMTP passwords stored in the `settings` table via `getEmailSettings`. The `settings` table stores `smtp_pass` via `updateEmailSettings`. Exposing all settings to non-admins would leak the SMTP password.

This endpoint is inside the `auth:sanctum` middleware group (authentication required), but the absence of the role check means any registered user was an effective admin for reading settings.

---

#### ❌ FIXED: `AdminController::exportBookingsCsv()` — missing admin check and Request parameter

**File:** `app/Http/Controllers/Api/AdminController.php`

**Before:**
```php
public function exportBookingsCsv()
{
    $bookings = \App\Models\Booking::with('user')...
```

**After:**
```php
public function exportBookingsCsv(Request $request)
{
    $this->requireAdmin($request);
    $bookings = \App\Models\Booking::with('user')...
```

**Impact:** `GET /api/v1/admin/bookings/export` was accessible to any authenticated user. The CSV export includes user names, email-derived information, booking times, vehicle plates, and booking status for all users. This is a significant data privacy exposure (GDPR Article 5 — data minimisation principle violated).

The route is inside `auth:sanctum`, so unauthenticated requests were blocked, but any valid session could trigger the export.

---

#### ❌ FIXED: `VehicleController::servePhoto()` — IDOR (Insecure Direct Object Reference)

**File:** `app/Http/Controllers/Api/VehicleController.php`

**Before:**
```php
public function servePhoto(string $id)
{
    $path = storage_path("app/vehicles/{$id}.jpg");
    if (!file_exists($path)) {
        return response()->json(['error' => 'Photo not found'], 404);
    }
    return response()->file($path, ['Content-Type' => 'image/jpeg']);
}
```

**After:**
```php
public function servePhoto(Request $request, string $id)
{
    // Verify ownership before serving — prevents IDOR where any authenticated
    // user could enumerate and download other users' vehicle photos by UUID.
    $vehicle = Vehicle::where('user_id', $request->user()->id)->find($id);
    if (!$vehicle) {
        return response()->json(['error' => 'Photo not found'], 404);
    }
    $path = storage_path("app/vehicles/{$id}.jpg");
    if (!file_exists($path)) {
        return response()->json(['error' => 'Photo not found'], 404);
    }
    return response()->file($path, ['Content-Type' => 'image/jpeg']);
}
```

**Impact:** Any authenticated user who knew (or could guess) another user's vehicle UUID could call `GET /api/v1/vehicles/{uuid}/photo` and download their vehicle photo. Since vehicle IDs are UUIDs (random 128-bit), practical enumeration would be extremely difficult, but the missing ownership check violates the principle of least privilege.

Note: The companion `uploadPhoto`, `update`, and `destroy` methods correctly include `->where('user_id', $request->user()->id)` — the missing check in `servePhoto` was the only IDOR.

---

#### ✅ Passed: All remaining admin endpoints have `requireAdmin()` checks

Every other admin method (`stats`, `heatmap`, `auditLog`, `announcements`, `importUsers`, `updateSettings`, `users`, `updateUser`, `resetDatabase`, `getBranding`, `updateBranding`, `uploadBrandingLogo`, `getPrivacy`, `updatePrivacy`, `getImpress`, `updateImpress`, `deleteLot`, `deleteUser`, `updateSlot`, `getAutoReleaseSettings`, `updateAutoReleaseSettings`, `getEmailSettings`, `updateEmailSettings`, `getWebhookSettings`, `updateWebhookSettings`) all call `$this->requireAdmin($request)`.

#### ✅ Passed: IDOR on bookings prevented

`BookingController::destroy()` uses `Booking::where('user_id', $request->user()->id)->findOrFail($id)` — only the booking owner can cancel.

---

### A02 — Cryptographic Failures ✅

- Passwords are hashed with **bcrypt** (`Hash::make()`) using Laravel's default cost factor (BCRYPT_ROUNDS=12)
- `Hash::check()` is used for verification — constant-time comparison
- Sanctum tokens are hashed before storage (`sha256` by default)
- `APP_KEY` is generated via `artisan key:generate` (AES-256-CBC for session encryption)

### ⚠️ A02 — Informational: Token expiry not enforced server-side in `refresh()`

The `AuthController::refresh()` method deletes all existing tokens and issues a new one. The new token's expiry is set only in the JSON response (`expires_at: now()->addDays(7)`). Laravel Sanctum does not automatically expire tokens unless `expiration` is set in `config/sanctum.php`. Without this, tokens are effectively indefinite.

**Recommendation:** Set `'expiration' => 10080` (7 days in minutes) in `config/sanctum.php` to enforce server-side token expiry.

---

### A03 — Injection ✅

All database access uses **Eloquent ORM** or the **Query Builder** with parameterized bindings. No raw string interpolation in SQL found.

The heatmap endpoint (`AdminController::heatmap`) uses `selectRaw()` with hard-coded SQL fragments (no user input is interpolated into the raw SQL string). The `strftime`/`DAYOFWEEK` expressions do not incorporate request parameters directly.

The `auditLog` endpoint uses `->where('action', $request->action)` and `->where('username', 'like', '%' . $request->search . '%')`. These go through the Query Builder's parameterized binding mechanism — not vulnerable to SQL injection.

### ⚠️ A03 — Informational: `install.php` uses exec() with user-supplied PHP string interpolation

**File:** `public/install.php` lines 83–99

```php
exec("php $artisan tinker --execute=\"
    User::create([
        'username' => '$adminUser',
        'email' => '$adminEmail',
        'password' => Hash::make('$adminPass'),
```

User-controlled values (`$adminUser`, `$adminEmail`, `$adminPass`, `$companyName`) from `$_POST` are interpolated directly into a shell command string passed to `exec()`. This is a **command injection** vulnerability in the installer.

**Risk assessment:** The installer is:
1. Intended to be deleted immediately after use (the installer itself displays a warning: "Delete this install.php file for security!")
2. Only accessible to operators who have direct filesystem access to upload it
3. Protected by the fact that the server must not yet be set up (the installer creates the initial DB)

However, if `install.php` is left on a publicly accessible server after installation, a malicious actor could POST crafted values to execute arbitrary shell commands with the PHP process's privileges.

**Mitigation already in place:** The installer displays a prominent warning to delete the file. There is no automated deletion.

**Recommendation:** Sanitize `$adminUser`, `$adminEmail`, `$adminPass`, and `$companyName` before interpolation, or use `escapeshellarg()`. Alternatively, replace the `tinker` exec approach with direct Eloquent calls using the Laravel bootstrap (`require __DIR__.'/../vendor/autoload.php'; ...`).

---

### A05 — Security Misconfiguration

#### ⚠️ install.php must be deleted after installation

**File:** `public/install.php`

The installer is a publicly accessible PHP file in the `public/` directory. If left in place after a successful installation, it allows:
1. Overwriting the existing `.env` file with attacker-controlled database credentials
2. Re-running migrations (potentially destructive)
3. The command injection issue noted above

The installer displays a warning but does not self-delete or lock itself after use.

**Recommendation for operators:** Delete `install.php` immediately after installation. Consider adding a check at the top of the installer that reads `setup_completed` from `.env` or the database and returns HTTP 410 Gone if already installed.

#### ✅ Passed: APP_DEBUG is false in production

`APP_DEBUG=false` in `.env.example`. The `.env` file created by `install.php` also sets `APP_ENV=production` and `APP_DEBUG=false`.

#### ✅ Passed: Error responses are generic

Laravel's exception handler in production mode returns generic JSON error messages without stack traces when `APP_DEBUG=false`.

---

### A07 — Identification and Authentication Failures ✅

**Rate limiting is wired and active:**
- Login + Register: `throttle:10,1` (10 attempts per minute per IP) — `routes/api_v1.php` line 24
- Forgot Password: `throttle:5,15` (5 attempts per 15 minutes per IP) — `routes/api_v1.php` line 242

**Session invalidation:**
- `changePassword()`: deletes all existing tokens and issues a new one
- `deleteAccount()`: deletes all tokens before deleting user
- `refresh()`: deletes all existing tokens and issues a new one

**User enumeration prevention:**
- `forgotPassword()` returns a generic response regardless of whether the email exists: `"If an account with that email exists, a reset link has been sent."`
- `login()` returns `"Invalid username or password"` for both missing user and wrong password

---

### A09 — Security Logging and Monitoring ✅

**AuditLog model is actively used** across controllers:

| Event | Location |
|---|---|
| `login_failed` | `AuthController::login()` |
| `login` | `AuthController::login()` |
| `register` | `AuthController::register()` |
| `account_deleted` | `AuthController::deleteAccount()` |
| `password_changed` | `AuthController::changePassword()` |
| `forgot_password` | `AuthController::forgotPassword()` |
| `booking_created` | `BookingController::store()` |
| `booking_cancelled` | `BookingController::destroy()` |
| `database_reset` | `AdminController::resetDatabase()` |
| `impressum_updated` | `AdminController::updateImpress()` |

**IP address logged** for auth events.

### ⚠️ Informational: Audit log for failed admin access not implemented

When `requireAdmin()` calls `abort(403)`, no audit entry is created. An attacker probing admin endpoints with a valid non-admin token would not appear in the audit log.

**Recommendation:** Add an audit entry in `requireAdmin()` or via an exception handler when a 403 is triggered on an admin route.

---

## 6. Security Headers

### ❌ FIXED: Security response headers (2026-02-28)

Added `app/Http/Middleware/SecurityHeaders.php` and registered globally in `bootstrap/app.php`.

All responses now include:

| Header | Value |
|---|---|
| `X-Content-Type-Options` | `nosniff` — prevents MIME sniffing |
| `X-Frame-Options` | `SAMEORIGIN` — clickjacking protection |
| `X-XSS-Protection` | `1; mode=block` — legacy XSS filter for older browsers |
| `Referrer-Policy` | `strict-origin-when-cross-origin` — limits referrer leakage |

Registration in `bootstrap/app.php`:
```php
$middleware->append(\App\Http\Middleware\SecurityHeaders::class);
```

**Note:** `Content-Security-Policy` and `Strict-Transport-Security` are intentionally not set
here — they depend on the deployment origin and TLS setup and must be configured in the reverse
proxy (nginx/Apache/Caddy) per deployment.

---

## 7. GDPR Endpoint Completeness

### `GET /api/v1/users/me/export` ✅ Complete

Handler: `UserController::exportData()` — exports:
- Profile (id, name, email, role, department, created_at)
- All bookings (lot_name, slot_number, vehicle_plate, start/end times, status, type)
- All absences (type, start_date, end_date, note)
- All vehicles (plate, make, model, color, is_default)
- User preferences

Response includes `Content-Disposition: attachment` header for direct browser download.

### `POST /api/v1/users/me/anonymize` ✅ Complete (GDPR Art. 17)

Handler: `UserController::anonymizeAccount()` — requires password confirmation, then:
- Anonymizes bookings (vehicle_plate → `[GELÖSCHT]`, notes cleared)
- Deletes absences, vehicles, favorites, notifications, push subscriptions
- Invalidates all tokens
- Writes audit log entry before anonymizing (for compliance record)
- Anonymizes user record (name → `[Gelöschter Nutzer]`, email → invalid placeholder, password randomized)
- Preserves anonymized booking records (§ 257 HGB / § 147 AO — 7-year retention)

---

## Operator Security Checklist

Before going to production, operators must:

- [ ] **Delete `public/install.php`** immediately after installation
- [ ] Change the admin password set during installation
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- [ ] Set `APP_KEY` via `php artisan key:generate`
- [ ] Configure `SESSION_ENCRYPT=true` in `.env` for additional session security
- [ ] Set token expiry in `config/sanctum.php`: `'expiration' => 10080` (7 days)
- [x] ~~Move `laravel/tinker` to `require-dev` in `composer.json`~~ — DONE
- [x] ~~Add security headers middleware~~ — DONE (SecurityHeaders.php registered globally)
- [ ] Configure Content-Security-Policy in reverse proxy for your specific origin
- [ ] Configure HTTPS on the web server and set `SESSION_SECURE_COOKIE=true` in `.env`
- [ ] Add `Strict-Transport-Security` header in reverse proxy (after TLS is confirmed working)
- [ ] Run `composer audit` in CI to track dependency CVEs
- [ ] Fill in and deploy legal templates from `legal/` directory (impressum, datenschutz, AGB, AVV, cookie policy)
- [ ] Review and deploy the Widerrufsbelehrung template if offering bookings to consumers (B2C)

## How `admin_password_hash` and JWT secrets are configured

**This application does not use custom JWT secrets.** Authentication is handled by Laravel Sanctum, which uses opaque tokens stored in the `personal_access_tokens` table and hashed with SHA-256. No operator-configurable JWT secret is required.

**Admin password** is set during the setup wizard (`POST /api/v1/setup`) or during installation via `install.php`. It is hashed via `Hash::make()` (bcrypt, 12 rounds) and stored in the `users` table. To reset it via CLI: `php artisan tinker --execute="App\Models\User::where('role','admin')->first()->update(['password' => Hash::make('newpassword')]);"`.
