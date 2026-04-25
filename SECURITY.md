# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 3.2.x   | Yes                |
| 3.1.x   | Yes                |
| 3.0.x   | Security fixes only |
| 2.x     | No                 |
| < 2.0   | No                 |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Instead, use one of these channels:

1. **GitHub Security Advisory** (preferred):
   [Create a private advisory](https://github.com/nash87/parkhub-php/security/advisories/new)

2. **Email**: Open a private security advisory on GitHub (see above)

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact assessment
- Suggested fix (if available)

### Response Times

| Severity | Acknowledgement | Fix Timeline |
|----------|----------------|--------------|
| Critical | Within 24 hours | Within 7 days |
| High     | Within 48 hours | Within 14 days |
| Medium   | Within 72 hours | Within 30 days |
| Low      | Within 1 week   | Next release |

Researchers are credited in release notes unless anonymity is requested.

## Security Features

### Authentication
- **httpOnly cookie auth** -- XSS-proof session management with SameSite=Lax
- **2FA/TOTP** -- QR code enrollment, backup codes, per-account toggle
- **bcrypt** password hashing (12 rounds, configurable via `BCRYPT_ROUNDS`)
- **Laravel Sanctum** opaque Bearer tokens with 7-day expiry
- **API key authentication** -- machine-to-machine integrations
- **Token rotation** on password change (all existing tokens revoked)
- **4-tier RBAC** (user, premium, admin, superadmin) enforced at controller level
- **Configurable password policies** -- length, uppercase, numbers, special characters

### Transport Security
- Security headers via `SecurityHeaders` middleware (X-Content-Type-Options, X-Frame-Options, CSP, Referrer-Policy)
- Server and framework version headers suppressed
- CORS restricted to specific allowed origins (no wildcard)
- HSTS recommended at reverse proxy level

### Rate Limiting
- Login: 10 requests/minute per IP
- Registration: 10 requests/minute per IP
- Forgot password: 5 requests/15 minutes per IP
- Failed login attempts logged in audit log
- Rate Limit Dashboard for admin monitoring

### Input Validation
- All endpoints use `$request->validate()` with explicit rules
- Eloquent ORM and Query Builder with bound parameters (no raw SQL interpolation)
- Vehicle photo content validation via GD (`imagecreatefromstring()`) prevents polyglot attacks
- File uploads stored out of web root, served via controller endpoint

### Audit Log
- All write operations logged to `audit_log` table
- Login success/failure, registration, account deletion, GDPR erasure, password change, admin actions
- Stores user ID, username, action, details (JSON), IP address, timestamp
- No delete endpoint -- deletion requires direct database access

### CSRF
- Pure SPA with Bearer token / httpOnly cookie authentication
- All state-changing requests require valid authentication
- No cookie-based CSRF vulnerability surface

## Kubernetes Hardening

The bundled Helm chart (`helm/parkhub/`) ships with the full [Pod Security
Standards **restricted**](https://kubernetes.io/docs/concepts/security/pod-security-standards/#restricted)
profile, now **default via `security.readOnlyRootFilesystem.enabled: true`**:

- `runAsNonRoot: true`, `runAsUser: 33` (www-data)
- `allowPrivilegeEscalation: false`
- `capabilities: drop: [ALL]`
- `readOnlyRootFilesystem: true`
- `seccompProfile: { type: RuntimeDefault }`

The paths Laravel + Apache must write to are backed by `emptyDir` volumes
with a `128Mi` size cap each: `/var/www/html/bootstrap/cache`,
`/var/www/html/storage` (skipped when a PVC is enabled), `/var/run/apache2`,
`/var/lock/apache2`, and `/tmp`.

Operators who need a writable root (dev images running `composer install`
or `php artisan tinker` at runtime) can flip the flag:

```yaml
security:
  readOnlyRootFilesystem:
    enabled: false
```

See [helm/parkhub/HARDENING.md](helm/parkhub/HARDENING.md) for the full
matrix + verification commands.

## Supply Chain

- **`composer audit`** runs on every push and nightly in `.github/workflows/security.yml`.
- **`npm audit --omit=dev`** runs nightly; production JS dependencies are kept on known-clean versions.
- **`roave/security-advisories`** is a `require-dev` dependency, which blocks `composer install` from bringing in a Composer package that has a published CVE.
- **Trivy** filesystem scan (`vuln,misconfig`) runs on every push and nightly, uploading SARIF to the GitHub Security tab.
- **`gitleaks`** (MIT) secret scan runs on every push over the full git history. Replaced trufflehog (AGPL) on 2026-04-25 (#365) — invoked as the upstream binary directly (gitleaks-action wrapper switched to a proprietary EULA in v2).
- **Docker images** are built with `provenance: mode=max, sbom: true` and signed via `actions/attest-build-provenance`, giving SLSA L3 attestations downloadable from the release assets.

## Full Security Documentation

See [docs/SECURITY.md](docs/SECURITY.md) for the complete security architecture, OWASP Top 10
compliance matrix, encryption details, and vulnerability disclosure process.

## GDPR / Legal Compliance

See [docs/GDPR.md](docs/GDPR.md) for DSGVO compliance and [docs/COMPLIANCE.md](docs/COMPLIANCE.md)
for the full international compliance matrix.

## CVE History

No CVEs have been reported against ParkHub PHP.
