# Security Model — ParkHub PHP

Security architecture, controls, and responsible disclosure for ParkHub PHP (Laravel 12).

---

## Authentication

ParkHub PHP uses **Laravel Sanctum** opaque Bearer tokens stored in the
`personal_access_tokens` table.

| Property | Value |
|----------|-------|
| Token type | Opaque Bearer token |
| Token expiry | 7 days from issuance |
| Storage | SHA-256 hash in the database — plaintext token shown only once on login |
| Rotation | `POST /api/v1/auth/refresh` revokes all existing tokens and issues a new one |
| Revocation on password change | Yes — `PATCH /api/v1/users/me/password` revokes all tokens |
| Token pruning | Run `php artisan sanctum:prune-expired --hours=168` weekly |

> Note: Token expiry (`expires_at`) is enforced client-side. The database does not
> automatically reject tokens after 7 days unless you run the prune command.
> Consider scheduling `sanctum:prune-expired` as a weekly cron job.

---

## Password Security

| Control | Value |
|---------|-------|
| Hashing algorithm | bcrypt |
| Cost factor | 12 rounds (configurable via `BCRYPT_ROUNDS` in `.env`) |
| Minimum length | 8 characters (enforced in registration and change endpoints) |
| Maximum length | 128 characters (prevents bcrypt DoS on very long inputs) |
| Password change | Requires current password |
| Account deletion | Requires current password |
| GDPR anonymization | Requires current password |

---

## Rate Limiting

| Endpoint | Limit | Window |
|----------|-------|--------|
| `POST /api/v1/auth/login` | 10 requests | 1 minute per IP |
| `POST /api/v1/auth/register` | 10 requests | 1 minute per IP |
| `POST /api/v1/auth/forgot-password` | 5 requests | 15 minutes per IP |

Failed login attempts are recorded in the audit log with action `login_failed`,
including the attempted username and the IP address.

---

## Authorization

### Role Checks

Three roles are defined: `user`, `admin`, and `superadmin` (treated as admin throughout).

Admin-only endpoints call `$this->requireAdmin($request)` inside each controller method.
This is an application-level check in addition to the Sanctum middleware — it prevents
privilege escalation if a route is accidentally added without the middleware group.

### Resource Ownership

All user resources (vehicles, bookings, absences, favourites, notifications) are scoped
to `WHERE user_id = $request->user()->id`. A user cannot access another user's data
even by guessing a UUID.

---

## SQL Injection Prevention

All database queries use Laravel's Eloquent ORM or the Query Builder with bound parameters.
Raw SQL is limited to the admin heatmap query (`selectRaw`) where column names and
SQL functions are hardcoded — no user-supplied data is interpolated into raw SQL.

---

## File Upload Security

Vehicle photos are processed through multiple security controls:

1. **MIME type validation**: Laravel's validator checks `mimes:jpeg,png,gif,webp`
2. **File size limit**: 5 MB for multipart uploads, 8 MB for base64
3. **GD content validation**: uploaded data is decoded through PHP's `imagecreatefromstring()`.
   Files that are not valid images are rejected with HTTP 422.
   This prevents polyglot file attacks (e.g. a file that is simultaneously a valid JPEG and PHP code).
4. **Out-of-web-root storage**: files are stored at `storage/app/vehicles/{uuid}.jpg` and
   served via a controller endpoint — never placed in a directly web-accessible directory
5. **Branding logo**: validated with `image|max:2048` and stored under `storage/app/public/branding/`

---

## Input Validation

Every API endpoint that accepts input calls `$request->validate()` with explicit rules.
No user-supplied data is passed directly to database queries — the ORM uses parameter
binding throughout.

---

## CSRF

ParkHub PHP is a pure SPA communicating via JSON API with Bearer token authentication.
CSRF protection via cookies is not applicable. All state-changing requests require a
valid Bearer token in the `Authorization` header.

---

## XSS Prevention

- All user-supplied content is rendered through React's JSX, which escapes values by default
- No user-supplied content is set via `dangerouslySetInnerHTML` or PHP's `echo` without escaping
- Blade templates (used only for the app shell) use `{{ }}` syntax which auto-escapes

---

## Security Headers

HTTPS and HTTP security headers are the responsibility of the reverse proxy (Nginx, Caddy, Traefik).
Recommended headers to add at the proxy level:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'" always;
```

Set `APP_URL=https://...` in `.env` to ensure all generated URLs use HTTPS.

---

## Audit Log

All write operations create an entry in the `audit_log` table.
The table has no delete endpoint — deletion requires direct database access.

| Action | Triggered by |
|--------|-------------|
| `login` | Successful login |
| `login_failed` | Failed login attempt |
| `register` | New user registration |
| `account_deleted` | User deletes own account |
| `gdpr_erasure` | GDPR Art. 17 anonymization |
| `forgot_password` | Password reset request |
| `password_changed` | Password change |
| `impressum_updated` | Admin edits Impressum |
| `database_reset` | Admin resets the database |

Each entry stores: `user_id` (nullable), `username`, `action`, `details` (JSON),
`ip_address`, `created_at`.

---

## Sanctum Token Expiry Enforcement

Tokens are checked client-side against `expires_at`. For strict server-side enforcement,
run `sanctum:prune-expired` on a schedule:

```bash
# Prune tokens older than 7 days (168 hours)
php artisan sanctum:prune-expired --hours=168
```

Or add to `app/Console/Kernel.php`:

```php
$schedule->command('sanctum:prune-expired --hours=168')->weekly();
```

---

## Known Limitations

| Limitation | Mitigation |
|-----------|-----------|
| Token expiry is not strictly server-side enforced | Run `sanctum:prune-expired` weekly |
| SQLite lacks row-level locking | Use MySQL 8 for production multi-process deployments |
| Queue job payloads in `jobs` table are not encrypted | Restrict database access; use database disk encryption |
| Audit log is never automatically pruned | Implement a scheduled cleanup command |

---

## Responsible Disclosure

If you discover a security vulnerability in ParkHub PHP:

1. **Do not** open a public GitHub issue for security vulnerabilities
2. For vulnerabilities in a deployed instance, contact the instance operator
3. For vulnerabilities in the open-source code, open a GitHub Security Advisory:
   `https://github.com/nash87/parkhub-php/security/advisories/new`

Include in your report:
- Description of the vulnerability
- Steps to reproduce
- Potential impact assessment
- Suggested fix (if available)

**Response times:**
- Acknowledgement: within 48 hours
- Fix timeline for critical issues: within 7 days
- Researchers credited in release notes (unless anonymity is requested)

**CVE history**: No CVEs at initial public release (v1.0.1).
