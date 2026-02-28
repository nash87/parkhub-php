# GDPR / DSGVO Compliance Guide — ParkHub PHP

This guide is addressed to operators deploying ParkHub PHP within the European Union (EU)
or the European Economic Area (EEA), where the General Data Protection Regulation
(DSGVO — Datenschutz-Grundverordnung) applies.

**This document is informational and does not constitute legal advice. Consult a
qualified data protection attorney (Datenschutzbeauftragter) for binding guidance.**

---

## Why On-Premise Simplifies GDPR

ParkHub PHP is designed for on-premise, self-hosted deployment. All data remains on your server.

| Aspect | Benefit |
|--------|---------|
| No cloud upload | No Auftragsverarbeitungsvertrag (AVV) needed for the core system |
| No third-party SaaS | No dependency on external privacy policies |
| Full control | You control storage location, encryption, access, and retention |
| No analytics | No tracking pixels, no CDN, no external JavaScript |

> **Exception**: If you configure SMTP email notifications, your SMTP provider becomes
> a data processor and requires an AVV. A template is in `legal/avv-template.md`.

---

## Data Inventory

### User Accounts (Art. 6 Abs. 1 lit. b DSGVO — contract performance)

| Category | Fields | Retention |
|----------|--------|-----------|
| Identity | `name`, `username`, `email`, `phone`, `picture` | Until deletion / anonymization |
| Role / access | `role`, `is_active`, `department`, `last_login` | Until deletion |
| Preferences | `preferences` JSON (theme, language, timezone, notification settings) | Until deletion |

### Booking Records (Art. 6 Abs. 1 lit. b + lit. c DSGVO)

| Fields | Retention |
|--------|-----------|
| `lot_name`, `slot_number`, `vehicle_plate`, `start_time`, `end_time`, `status`, `booking_type`, `notes` | 10 years (§147 AO, anonymized on erasure) |

**German tax law**: §147 AO requires 10-year retention of accounting records for commercial
parking operations. Booking records with pricing data fall under this obligation.

### Vehicle Data (Art. 6 Abs. 1 lit. b DSGVO)

| Fields | Retention |
|--------|-----------|
| `plate`, `make`, `model`, `color`, `photo_url` | Until vehicle deleted / account anonymization |

### Absence Data (Art. 6 Abs. 1 lit. b DSGVO)

| Fields | Retention |
|--------|-----------|
| `absence_type`, `start_date`, `end_date`, `note` | Until deleted / account anonymization |

### Audit Log (Art. 6 Abs. 1 lit. c + lit. f DSGVO)

| Fields | Retention |
|--------|-----------|
| `user_id`, `username`, `action`, `details`, `ip_address`, `created_at` | Operator-configured (recommended: 90 days to 1 year) |

IP addresses stored in the audit log may constitute personal data under DSGVO. Implement
a retention policy — see the [Audit Log Pruning](#audit-log-pruning) section below.

### Push Subscriptions (Art. 6 Abs. 1 lit. a DSGVO — consent)

| Fields | Retention |
|--------|-----------|
| Browser push endpoint, encryption keys | Until unsubscription |

---

## What ParkHub Does NOT Collect

| Item | Status |
|------|--------|
| Cookies | None — Bearer token stored in `localStorage` (technically necessary, no consent needed) |
| Analytics | None |
| External CDN resources | None — all assets served locally |
| Third-party tracking | None |
| Advertising data | None |

No cookie consent banner is required for core functionality (TTDSG §25).

---

## User Rights Implementation

### Art. 15 — Right of Access (Auskunftsrecht)

Users can view all their data through the application and download a complete JSON export.

**User-facing**: Settings → Export My Data

**API endpoint**: `GET /api/v1/user/export`

The export includes: profile, all bookings, all absences, all vehicles, preferences.

**Operator action required**: None.

---

### Art. 16 — Right to Rectification (Berichtigungsrecht)

Users can update name, email, phone, and department via the Settings page.
Administrators can update any user field via `PUT /api/v1/admin/users/:id`.

**Operator action required**: None.

---

### Art. 17 — Right to Erasure (Recht auf Vergessenwerden)

ParkHub implements two distinct deletion modes:

#### 1. Account anonymization — DSGVO-compliant approach (recommended)

**API endpoint**: `POST /api/v1/users/me/anonymize`

What this endpoint does:

1. Replaces `name`, `email`, `username`, `phone`, `picture`, `department` with anonymized placeholders
2. Replaces `vehicle_plate` on all bookings with `[GELÖSCHT]`
3. Deletes absences, vehicles, favourites, notifications, push subscriptions
4. Sets `is_active = false` (account permanently locked)
5. Sets password to a random 64-character string (account becomes inaccessible)
6. Revokes all tokens
7. Writes a `gdpr_erasure` event to the audit log (stores only the anonymized user ID, not PII)

After anonymization, booking records remain (with anonymized user reference) for accounting
purposes. The user cannot log in again. This is the recommended approach for operators
who must retain booking records for tax purposes.

#### 2. Hard account deletion

**API endpoint**: `DELETE /api/v1/users/me/delete`

Hard-deletes the user record. All related data (bookings, absences, vehicles) is CASCADE-deleted.

> **Warning**: If booking records are needed for accounting (§147 AO), use anonymization
> instead of hard deletion. Consult your tax advisor.

---

### Art. 18 — Right to Restriction of Processing

Not automatically implemented. Handle restriction requests manually by deactivating
the user's account (`PUT /api/v1/admin/users/:id` with `is_active: false`) and
documenting the restriction in your internal process log.

---

### Art. 20 — Right to Data Portability (Datenübertragbarkeit)

The export endpoint (`GET /api/v1/user/export`) delivers all personal data in
machine-readable JSON format. This satisfies Art. 20.

**Operator action required**: None.

---

### Art. 21 — Right to Object (Widerspruchsrecht)

Users can disable email notifications and push notifications via their preferences.
For processing on the basis of legitimate interest (Art. 6 lit. f — audit log data):
establish an email-based process for objections. Add the contact address to your Impressum.

---

## Data Retention Configuration

### GDPR Retention Days

Set a default data retention period in the admin panel:

Admin → Privacy → Data Retention Days

Or via the API:

```bash
curl -s -X PUT https://parking.example.com/api/v1/admin/privacy \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"data_retention_days": 730, "gdpr_enabled": true}'
```

This setting is informational — it does not automatically delete data.
Implement automated cleanup via a scheduled artisan command.

### Audit Log Pruning

There is no automatic audit log pruning. Add a scheduled command:

```php
// In App\Console\Kernel or a dedicated console command:
\App\Models\AuditLog::where('created_at', '<', now()->subDays(90))->delete();
```

Schedule via cron:

```cron
0 2 * * 0 php /var/www/parkhub/artisan app:prune-audit-log
```

### Session Token Expiry

Sanctum tokens expire after `SESSION_LIFETIME` minutes (default 120) of inactivity.
The token's `expires_at` field is checked client-side. Server-side pruning:

```bash
php artisan sanctum:prune-expired --hours=168
```

Schedule weekly:

```cron
0 3 * * 0 php /var/www/parkhub/artisan sanctum:prune-expired --hours=168
```

---

## Legal Documents

### Impressum (DDG §5)

The Impressum is legally required for all commercial digital services in Germany.

1. Admin panel → Impressum (or `PUT /api/v1/admin/impressum`)
2. Fill in all required fields (provider name, address, email, phone, company register, VAT ID)
3. The Impressum is publicly accessible at `/impressum` and via `GET /api/v1/legal/impressum`

**Required fields**: provider name, legal form, street, postal code, city, country, email,
phone. For GmbH/AG: register court, register number, VAT ID, managing directors.

**Template**: `legal/impressum-template.md`

### Datenschutzerklarung (Privacy Policy)

A DSGVO-compliant privacy policy is required.

**Template**: `legal/datenschutz-template.md`

Adapt the template to reflect your organization's:
- Name and contact details
- Specific data categories processed
- Any data processor agreements (AVV)
- Data Protection Officer contact (if applicable)

Store the policy text via Admin → Privacy. It is displayed at `/privacy`.

### AGB (Terms of Service)

Required for commercial parking services.

**Template**: `legal/agb-template.md`

### AVV (Auftragsverarbeitungsvertrag — Data Processing Agreement)

Required if you use SMTP providers (SendGrid, Mailgun, Postmark) or any service
that processes personal data on your behalf.

**Template**: `legal/avv-template.md`

---

## Technical and Organizational Measures (TOMs, Art. 32 DSGVO)

| Measure | Implementation in ParkHub PHP |
|---------|------------------------------|
| Encryption in transit | HTTPS (operator responsibility — configure TLS at reverse proxy) |
| Encryption at rest | Database encryption or OS disk encryption (operator responsibility) |
| Access control | Role-based (user/admin/superadmin), Sanctum token auth |
| Pseudonymization | Anonymization endpoint replaces PII with pseudonymous identifiers |
| Audit logging | All write operations logged with user, action, IP, timestamp |
| Brute-force protection | Rate limiting on login (10/min) and password reset (5/15min) |
| Data minimization | Only required fields collected; preferences user-controlled |
| Password security | bcrypt with 12 rounds (configurable) |
| File upload validation | GD content validation prevents polyglot file attacks |
| SQL injection prevention | Eloquent ORM with parameterized queries throughout |

Organizational measures (privacy by default, staff training, incident response,
DPA registration) are the operator's responsibility.

---

## DSGVO Compliance Checklist

Before going live:

**Legal setup**
- [ ] Impressum fully filled in (Admin → Impressum). Verify `/impressum` is publicly accessible
- [ ] Datenschutzerklärung written and published (Admin → Privacy → Policy Text)
- [ ] AGB created and published (if commercial service)
- [ ] AVV signed with SMTP provider (e.g. Mailgun, SendGrid, Postmark) — `legal/avv-template.md`
- [ ] AVV signed with hosting provider if they can physically access your server
- [ ] Verzeichnis der Verarbeitungstätigkeiten (VVT) updated (Art. 30 DSGVO)
- [ ] DPA/DSB appointment evaluated (Art. 37 DSGVO)

**Technical controls**
- [ ] HTTPS enabled (TLS 1.2+ at reverse proxy)
- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`
- [ ] Disk encryption at the OS level for the data volume
- [ ] GDPR enabled: `gdpr_enabled=true` in admin settings
- [ ] Data retention policy for audit logs implemented

**Testing**
- [ ] Export endpoint tested: `GET /api/v1/user/export` → verify JSON completeness
- [ ] Anonymization endpoint tested: verify PII removed, booking records retained
- [ ] Hard deletion tested: verify CASCADE removes all user data
- [ ] Password reset flow tested end-to-end

---

## Responding to Data Subject Access Requests (DSAR)

When a user submits a DSAR:

**For Art. 15/20 (access / portability) requests:**
1. Verify the requester's identity
2. Call `GET /api/v1/user/export` on their behalf (as admin)
3. Deliver within 30 calendar days

**For Art. 17 (erasure) requests:**
1. Verify identity
2. Call `POST /api/v1/users/me/anonymize` on their behalf
3. Inform the user of the §147 AO retention limitation and its legal basis
4. Document in your internal DSAR log

---

## Cookie Policy (TTDSG §25)

ParkHub PHP does not set any cookies. The Bearer token is stored in `localStorage` or
`sessionStorage` — this is technically necessary for session management and does not
require consent under TTDSG §25.

No analytics, advertising, or tracking technologies are used.

---

## Data Protection Officer (DSB)

Under Art. 37 DSGVO, appointing a Data Protection Officer is mandatory for:
- Public bodies and authorities
- Organizations processing special categories of data (Art. 9) at scale
- Organizations systematically monitoring individuals at large scale

For most organizations using ParkHub for internal parking management, appointment is
not mandatory. Consult your legal advisor to determine your obligation.
