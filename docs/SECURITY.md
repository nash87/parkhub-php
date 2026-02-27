# Security

Security model, implemented controls, and responsible disclosure process for ParkHub PHP.

---

## Authentication

ParkHub uses **Laravel Sanctum** for API authentication. Sanctum issues opaque Bearer tokens stored in the `personal_access_tokens` table.

| Property | Value |
|----------|-------|
| Token type | Opaque Bearer token |
| Token expiry | 7 days from issuance |
| Storage | Hashed (SHA-256) in the database — the plain-text token is shown only once |
| Rotation | `POST /api/v1/auth/refresh` revokes all existing tokens and issues a new one |
| Revocation on password change | Yes — `PATCH /api/v1/users/me/password` revokes all tokens |

---

## Password Security

| Control | Value |
|---------|-------|
| Hashing algorithm | bcrypt |
| Cost factor | 12 rounds (configurable via `BCRYPT_ROUNDS` in `.env`) |
| Minimum password length | 8 characters (enforced in registration and change endpoints) |
| Maximum password length | 128 characters (prevents DoS via bcrypt on very long inputs) |
| Password change | Requires the current password |
| Account deletion | Requires the current password |
| GDPR anonymization | Requires the current password |

---

## Rate Limiting

| Endpoint | Limit | Window |
|----------|-------|--------|
| `POST /api/login` | 10 requests | 1 minute per IP |
| `POST /api/register` | 10 requests | 1 minute per IP |
| `POST /api/v1/auth/login` | 10 requests | 1 minute per IP |
| `POST /api/v1/auth/register` | 10 requests | 1 minute per IP |
| `POST /api/v1/auth/forgot-password` | 5 requests | 15 minutes per IP |

Failed login attempts are recorded in the audit log with action `login_failed` and include the attempted username and IP address.

---

## Audit Log

All write operations create an immutable entry in the `audit_log` table. Logged events include:

| Action | Triggered by |
|--------|-------------|
| `login` | Successful login |
| `login_failed` | Failed login attempt |
| `register` | New user registration |
| `account_deleted` | User deletes own account |
| `gdpr_erasure` | GDPR Art. 17 anonymization |
| `forgot_password` | Password reset request (stores hashed email, not plain) |
| `password_changed` | Password change |
| `impressum_updated` | Admin edits Impressum |
| `database_reset` | Admin resets the database |

Each entry stores: `user_id` (nullable), `username`, `action`, `details` (JSON), `ip_address`, `created_at`.

The audit log table has no delete endpoint. Deletion requires direct database access.

---

## Authorization

### Role Checks

Two roles are defined: `user` and `admin` (plus `superadmin` which is treated as admin throughout the code).

Admin-only endpoints call `$this->requireAdmin($request)` inside each controller method. This is an application-level check in addition to the Sanctum middleware — not solely enforced by route middleware — which prevents privilege escalation if a route is accidentally added without the middleware group.

### Resource Ownership

User resources (vehicles, bookings, absences, favourites, notifications) are always scoped to `WHERE user_id = $request->user()->id`. A user cannot access another user's data even by guessing a UUID.

---

## File Upload Security

Vehicle photos are processed with the following controls:

1. MIME type validation via Laravel's validator (`mimes:jpeg,png,gif,webp`)
2. File size limit: 5 MB for multipart uploads, 8 MB for base64
3. Content validation: the uploaded data is decoded through PHP's `imagecreatefromstring()` (GD). Files that are not valid images are rejected with a 422 error. This prevents polyglot file attacks (e.g. a file that is both a valid JPEG and executable PHP).
4. Files are stored outside the web root at `storage/app/vehicles/{uuid}.jpg` and served via a controller endpoint — they are never placed directly in a publicly accessible directory.
5. Branding logo uploads are validated with `image|max:2048` and stored under `storage/app/public/branding/`.

---

## Input Validation

Every API endpoint that accepts input calls `$request->validate()` with explicit rules. No user-supplied data is passed directly to database queries — the ORM uses parameter binding throughout.

---

## SQL Injection

All database queries use Laravel's Eloquent ORM or the Query Builder with bound parameters. Raw SQL expressions are limited to the heatmap query (`selectRaw`) where column names and functions are hardcoded, not user-supplied.

---

## CSRF

ParkHub is a pure SPA that communicates via JSON API. CSRF protection via cookies is not applicable to Sanctum token-based authentication. All state-changing requests require a valid Bearer token.

---

## Headers and HTTPS

HTTPS configuration is the responsibility of the reverse proxy (Nginx, Caddy, Traefik) or the hosting provider. The application does not force HTTPS internally. Set `APP_URL=https://...` in `.env` to ensure generated URLs use HTTPS.

Recommended HTTP security headers to add at the reverse proxy level:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; ...
```

---

## Known Limitations

- **No automatic token expiry enforcement:** Token expiry (7 days) is checked client-side based on the `expires_at` field returned at login. The database does not automatically invalidate tokens after 7 days unless you run `php artisan sanctum:prune-expired`. Consider adding this to a scheduled task.
- **SQLite in production:** SQLite lacks row-level locking and is not suitable for multi-process deployments. Use MySQL 8 for production.
- **Queue encryption:** Job payloads in the `jobs` table are not encrypted. Email addresses in queued mail jobs are visible to anyone with database access. Use database encryption or restrict database access accordingly.
- **Audit log retention:** The audit log is never automatically pruned. Implement a cleanup command for long-term deployments.

---

## Responsible Disclosure

If you discover a security vulnerability in ParkHub PHP, please report it responsibly:

1. **Do not** create a public GitHub issue for security vulnerabilities.
2. Send a description to the project maintainer's email address (see the repository contact).
3. Include: description of the vulnerability, steps to reproduce, potential impact, and (if available) a suggested fix.
4. We aim to acknowledge receipt within 48 hours and provide a fix timeline within 7 days for critical issues.

We appreciate responsible disclosure and will credit researchers in the release notes (unless anonymity is requested).
