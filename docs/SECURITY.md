# Security Model — ParkHub PHP

> **Version:** 3.2.0 | **Last updated:** 2026-03-22

Security architecture, controls, OWASP compliance, and responsible disclosure for
ParkHub PHP (Laravel 12 + React 19).

---

## Table of Contents

1. [Security Architecture Overview](#security-architecture-overview)
2. [Authentication](#authentication)
3. [Password Security](#password-security)
4. [Two-Factor Authentication (2FA/TOTP)](#two-factor-authentication-2fatotp)
5. [API Key Authentication](#api-key-authentication)
6. [Authorization](#authorization)
7. [Encryption](#encryption)
8. [Rate Limiting](#rate-limiting)
9. [Input Validation](#input-validation)
10. [OWASP Top 10 Compliance](#owasp-top-10-compliance-matrix)
11. [Security Headers](#security-headers)
12. [File Upload Security](#file-upload-security)
13. [CSRF / XSS Prevention](#csrf--xss-prevention)
14. [Audit Log](#audit-log)
15. [Known Limitations](#known-limitations)
16. [Vulnerability Disclosure Process](#vulnerability-disclosure-process)
17. [Security Contact](#security-contact)

---

## Security Architecture Overview

```
                    Reverse Proxy (Nginx/Caddy)
                  TLS 1.2+ / HSTS / Security Headers
                              |
              Laravel 12 Application Layer
  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐
  │ Rate Limiter  │  │ SecurityHdrs │  │ RequireAdmin MW  │
  │ (per-route)   │  │ (CSP, HSTS)  │  │ (role gate)      │
  └──────┬───────┘  └──────┬───────┘  └──────┬───────────┘
         │                 │                  │
  ┌──────▼─────────────────▼──────────────────▼───────────┐
  │                  Sanctum Auth Layer                    │
  │   httpOnly Cookie (SPA) + Bearer Token (API)          │
  │   2FA/TOTP · API Keys · Token Expiry                  │
  └──────┬────────────────────────────────────────────────┘
         │
  ┌──────▼────────────────────────────────────────────────┐
  │              Input Validation Layer                    │
  │   $request->validate() · Eloquent ORM (parameterized) │
  │   GD image validation · File size limits              │
  └──────┬────────────────────────────────────────────────┘
         │
  ┌──────▼────────────────────────────────────────────────┐
  │              Audit Log (all write operations)         │
  │   User · Action · Details · IP · Timestamp            │
  └───────────────────────────────────────────────────────┘
                              |
    Database: MySQL 8 / PostgreSQL / SQLite
    bcrypt passwords · parameterized queries · no raw SQL
```

**Key principles:**
- Defense in depth — multiple layers of security controls
- Principle of least privilege — users access only their own data
- Secure by default — security features enabled without configuration
- Self-hosted — no third-party data exposure by default

---

## Authentication

ParkHub PHP supports two authentication modes:

### 1. httpOnly Cookie Authentication (Primary — SPA)

Used by the React frontend for XSS-proof session management.

| Property | Value |
|----------|-------|
| Cookie name | `parkhub_session` |
| HttpOnly | Yes — not accessible via JavaScript |
| SameSite | `Lax` — CSRF protection for cross-origin requests |
| Secure | Yes (when `APP_URL` uses HTTPS) |
| Expiry | Session-based (cleared on browser close) or configurable |

### 2. Bearer Token Authentication (API)

Used by external API consumers, mobile apps, and integrations.

| Property | Value |
|----------|-------|
| Token type | Opaque Bearer token (Laravel Sanctum) |
| Token expiry | 7 days from issuance |
| Storage | SHA-256 hash in the database — plaintext token shown only once on login |
| Rotation | `POST /api/v1/auth/refresh` revokes all existing tokens and issues a new one |
| Revocation on password change | Yes — `PATCH /api/v1/users/me/password` revokes all tokens |
| Token pruning | Run `php artisan sanctum:prune-expired --hours=168` weekly |

### 3. API Key Authentication

For machine-to-machine integrations. API keys are scoped per user and can be revoked
individually.

| Property | Value |
|----------|-------|
| Endpoint | `POST /api/v1/api-keys` (create), `DELETE /api/v1/api-keys/:id` (revoke) |
| Format | Prefixed opaque token (`pk_...`) |
| Scope | Inherits user's role permissions |
| Rotation | Manual — create new key, delete old one |

---

## Password Security

| Control | Value |
|---------|-------|
| Hashing algorithm | bcrypt |
| Cost factor | 12 rounds (configurable via `BCRYPT_ROUNDS` in `.env`) |
| Minimum length | 8 characters (enforced in registration and change endpoints) |
| Maximum length | 128 characters (prevents bcrypt DoS on very long inputs) |
| Configurable policies | Minimum length, require uppercase, require numbers, require special characters |
| Password change | Requires current password |
| Account deletion | Requires current password |
| GDPR anonymization | Requires current password |

---

## Two-Factor Authentication (2FA/TOTP)

| Feature | Details |
|---------|---------|
| Standard | TOTP (RFC 6238) — compatible with Google Authenticator, Authy, 1Password, etc. |
| Enrollment | `POST /api/v1/2fa/enable` — returns QR code and secret |
| Verification | `POST /api/v1/2fa/verify` — validates 6-digit TOTP code |
| Backup codes | 8 single-use recovery codes generated on enrollment |
| Disable | `POST /api/v1/2fa/disable` — requires current password |
| Login flow | After password verification, 2FA code is required as a second step |
| Per-account | Each user independently enables/disables 2FA |

---

## API Key Authentication

See [Authentication](#3-api-key-authentication) above.

---

## Authorization

### Role Hierarchy

Four roles with ascending privilege levels:

| Role | Level | Capabilities |
|------|-------|-------------|
| `user` | 1 | Own bookings, vehicles, absences, preferences |
| `premium` | 2 | User capabilities + priority booking features |
| `admin` | 3 | All user data, reports, settings, user management |
| `superadmin` | 4 | Admin + system configuration, database operations |

### Role Checks

Admin-only endpoints call `$this->requireAdmin($request)` inside each controller method.
This is an application-level check in addition to the Sanctum middleware — it prevents
privilege escalation if a route is accidentally added without the middleware group.

### Resource Ownership

All user resources (vehicles, bookings, absences, favourites, notifications) are scoped
to `WHERE user_id = $request->user()->id`. A user cannot access another user's data
even by guessing a UUID.

---

## Encryption

| Layer | Method | Details |
|-------|--------|---------|
| **Passwords** | bcrypt (12 rounds) | Industry standard, configurable cost factor |
| **In transit** | TLS 1.2+ (HTTPS) | Operator configures at reverse proxy level |
| **At rest** (optional) | AES-256-GCM | Database-level or OS disk encryption (operator responsibility) |
| **SMTP passwords** | Laravel encryption | Admin settings store SMTP passwords encrypted |
| **API tokens** | SHA-256 hash | Plaintext never stored; shown only once on creation |
| **2FA secrets** | Database encrypted | Stored via Laravel's encrypted cast |
| **Backup codes** | Hashed | Bcrypt-hashed, single-use |

---

## Rate Limiting

| Endpoint | Limit | Window | Key |
|----------|-------|--------|-----|
| `POST /api/v1/auth/login` | 10 requests | 1 minute | Per IP |
| `POST /api/v1/auth/register` | 10 requests | 1 minute | Per IP |
| `POST /api/v1/auth/forgot-password` | 5 requests | 15 minutes | Per IP |
| `POST /api/v1/payments/*` | 10 requests | 1 minute | Per user |
| `POST /api/v1/webhooks/*` | 60 requests | 1 minute | Per IP |
| General API | 60 requests | 1 minute | Per user |

Failed login attempts are recorded in the audit log with action `login_failed`,
including the attempted username and the IP address.

The Rate Limit Dashboard (`GET /api/v1/admin/rate-limits`) provides real-time monitoring
of rate limit groups and a 24-hour history of blocked requests.

---

## Input Validation

Every API endpoint that accepts input calls `$request->validate()` with explicit rules.
No user-supplied data is passed directly to database queries — the ORM uses parameter
binding throughout.

Key validation patterns:
- **Email**: `required|email|max:255`
- **Password**: `required|string|min:8|max:128`
- **Plate numbers**: `required|string|max:20` with format validation
- **File uploads**: MIME type + size + GD content validation
- **Dates**: `date|after_or_equal:today` for bookings
- **IDs**: UUID or integer validation on path parameters
- **JSON payloads**: Explicit field whitelisting — no mass assignment

---

## OWASP Top 10 Compliance Matrix

| OWASP Category | Status | ParkHub Implementation |
|----------------|--------|----------------------|
| **A01: Broken Access Control** | Mitigated | 4-tier RBAC, resource ownership scoping, admin middleware, Sanctum auth |
| **A02: Cryptographic Failures** | Mitigated | bcrypt passwords, SHA-256 token hashing, TLS in transit, encrypted SMTP secrets |
| **A03: Injection** | Mitigated | Eloquent ORM with parameterized queries throughout; no raw SQL concatenation |
| **A04: Insecure Design** | Mitigated | Privacy by design (self-hosted), defense in depth, principle of least privilege |
| **A05: Security Misconfiguration** | Partially mitigated | `APP_DEBUG=false` required in production; `install.php` must be deleted post-setup |
| **A06: Vulnerable Components** | Monitored | All dependencies MIT/Apache-2.0/BSD; Dependabot/Renovate recommended |
| **A07: Authentication Failures** | Mitigated | Rate limiting, 2FA/TOTP, token expiry, bcrypt, configurable password policies |
| **A08: Software and Data Integrity** | Mitigated | Composer + npm lock files, Webpack integrity, signed container images recommended |
| **A09: Security Logging** | Implemented | Full audit log — login, registration, deletion, password changes, admin actions |
| **A10: Server-Side Request Forgery** | Not applicable | No server-side URL fetching from user input |

---

## Security Headers

Security headers are applied via the `SecurityHeaders` middleware (registered globally):

| Header | Value | Purpose |
|--------|-------|---------|
| `X-Content-Type-Options` | `nosniff` | Prevent MIME type sniffing |
| `X-Frame-Options` | `SAMEORIGIN` | Prevent clickjacking |
| `X-XSS-Protection` | `0` | Disabled (CSP is preferred) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limit referrer information |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Restrict browser APIs |

Additional headers recommended at the reverse proxy level:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'" always;
```

Set `APP_URL=https://...` in `.env` to ensure all generated URLs use HTTPS.

---

## File Upload Security

Vehicle photos are processed through multiple security controls:

1. **MIME type validation**: Laravel's validator checks `mimes:jpeg,png,gif,webp`
2. **File size limit**: 5 MB for multipart uploads, 8 MB for base64
3. **GD content validation**: uploaded data is decoded through PHP's `imagecreatefromstring()`.
   Files that are not valid images are rejected with HTTP 422.
   This prevents polyglot file attacks (e.g., a file that is simultaneously a valid JPEG and PHP code).
4. **Out-of-web-root storage**: files are stored at `storage/app/vehicles/{uuid}.jpg` and
   served via a controller endpoint — never placed in a directly web-accessible directory
5. **Branding logo**: validated with `image|max:2048` and stored under `storage/app/public/branding/`

---

## CSRF / XSS Prevention

### CSRF

ParkHub PHP is a pure SPA communicating via JSON API with Bearer token authentication.
CSRF protection via cookies is not applicable. All state-changing requests require a
valid Bearer token in the `Authorization` header or a valid httpOnly session cookie.

### XSS

- All user-supplied content is rendered through React's JSX, which escapes values by default
- No user-supplied content is rendered as raw HTML without sanitization
- Blade templates (used only for the app shell) use `{{ }}` syntax which auto-escapes
- Nonce-based Content Security Policy prevents inline script injection

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
| `2fa_enabled` | User enables two-factor authentication |
| `2fa_disabled` | User disables two-factor authentication |
| `impressum_updated` | Admin edits Impressum |
| `settings_updated` | Admin changes system settings |
| `database_reset` | Admin resets the database |
| `user_role_changed` | Admin changes a user's role |

Each entry stores: `user_id` (nullable), `username`, `action`, `details` (JSON),
`ip_address`, `created_at`.

---

## Known Limitations

| Limitation | Mitigation |
|-----------|-----------|
| Token expiry is not strictly server-side enforced | Run `sanctum:prune-expired` weekly |
| SQLite lacks row-level locking | Use MySQL 8 or PostgreSQL for production multi-process deployments |
| Queue job payloads in `jobs` table are not encrypted | Restrict database access; use database disk encryption |
| Audit log is never automatically pruned | Implement a scheduled cleanup command |
| No built-in WAF | Deploy behind Cloudflare, AWS WAF, or ModSecurity |
| No automatic dependency vulnerability scanning | Configure Dependabot or `composer audit` in CI |

---

## Vulnerability Disclosure Process

ParkHub follows a responsible disclosure process:

### Reporting

1. **Do NOT** open a public GitHub issue for security vulnerabilities
2. **Preferred**: Create a [GitHub Security Advisory](https://github.com/nash87/parkhub-php/security/advisories/new) (private)
3. **Alternative**: Email the security contact below

### What to Include

- Description of the vulnerability
- Steps to reproduce (proof of concept if possible)
- Potential impact assessment
- Affected versions
- Suggested fix (if available)

### Response Timeline

| Severity | Acknowledgement | Fix Timeline |
|----------|----------------|--------------|
| Critical (RCE, auth bypass, data leak) | Within 24 hours | Within 7 days |
| High (privilege escalation, XSS) | Within 48 hours | Within 14 days |
| Medium (information disclosure) | Within 72 hours | Within 30 days |
| Low (best practice) | Within 1 week | Next release |

### Recognition

- Security researchers are credited in release notes (unless anonymity is requested)
- Significant findings may be assigned a CVE

### CVE History

No CVEs have been reported against ParkHub PHP.

---

## Security Contact

- **GitHub Security Advisory**: [Create advisory](https://github.com/nash87/parkhub-php/security/advisories/new)
- **Repository**: [github.com/nash87/parkhub-php](https://github.com/nash87/parkhub-php)
- **Supported versions**: See [SECURITY.md](/SECURITY.md) (root) for version support matrix

---

*This security documentation covers ParkHub PHP v3.2.0. For GDPR compliance, see
[GDPR.md](GDPR.md). For the full compliance matrix, see [COMPLIANCE.md](COMPLIANCE.md).*
