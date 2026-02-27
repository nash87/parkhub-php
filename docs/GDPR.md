# GDPR / DSGVO Compliance Guide

This guide is addressed to operators who deploy ParkHub PHP within the European Union (EU) or the European Economic Area (EEA), where the General Data Protection Regulation (DSGVO — Datenschutz-Grundverordnung) applies.

**This document is informational and does not constitute legal advice. Consult a qualified data protection attorney (Datenschutzbeauftragter) for binding guidance.**

---

## Data Inventory

### Data Stored per User

| Category | Fields | Legal Basis (Art. 6 DSGVO) |
|----------|--------|---------------------------|
| Identity | `name`, `username`, `email`, `phone`, `picture` | Art. 6(1)(b) — contract performance |
| Role / access | `role`, `is_active`, `department`, `last_login` | Art. 6(1)(b) — contract performance |
| Preferences | `preferences` JSON (theme, language, timezone, notification settings) | Art. 6(1)(b) — contract performance |
| Bookings | `lot_name`, `slot_number`, `vehicle_plate`, `start_time`, `end_time`, `status`, `booking_type`, `notes` | Art. 6(1)(b) — contract performance; Art. 6(1)(c) — legal obligation (§147 AO) |
| Vehicles | `plate`, `make`, `model`, `color`, `photo_url` | Art. 6(1)(b) — contract performance |
| Absences | `absence_type`, `start_date`, `end_date`, `note` | Art. 6(1)(b) — contract performance |
| Notifications | In-app notification content | Art. 6(1)(b) — contract performance |
| Audit log | `user_id`, `username`, `action`, `details`, `ip_address` | Art. 6(1)(c) — legal obligation; Art. 6(1)(f) — legitimate interest (security) |
| Push subscriptions | Browser push endpoint, encryption keys | Art. 6(1)(a) — consent |
| Webhooks | URL, secret (operator-configured, not per-user) | Art. 6(1)(b) — contract performance |

### IP Addresses

IP addresses are stored in the `audit_log` table for security events (login, registration, failed login, GDPR erasure). Depending on your jurisdiction, IP addresses may constitute personal data under DSGVO.

Consider implementing a log retention policy. There is no automatic pruning of audit log entries in ParkHub PHP. You can implement this via a scheduled artisan command.

---

## User Rights Implementation

### Art. 20 — Right to Data Portability

Users can export all their personal data as a machine-readable JSON file.

**User-facing:** Settings page → "Export My Data"

**API endpoint:** `GET /api/v1/user/export`

The export includes: profile, all bookings, all absences, all vehicles, and preferences.

**Operator action required:** None. This right is implemented out of the box.

---

### Art. 17 — Right to Erasure ("Right to be Forgotten")

ParkHub implements two distinct deletion modes:

#### 1. Full account deletion (`DELETE /api/v1/users/me/delete`)

Hard-deletes the user record. All related data (bookings, absences, vehicles, etc.) is CASCADE-deleted. Use this when there is no legal retention obligation.

**Risk:** If booking records are needed for accounting or legal compliance (§147 AO — 10-year retention for business records), use anonymization instead.

#### 2. Account anonymization (`POST /api/v1/users/me/anonymize`)

This is the DSGVO-compliant approach for operators who must retain booking records.

The anonymization process:
1. Replaces `name`, `email`, `username`, `phone`, `picture`, `department` with placeholder values (`[Gelöschter Nutzer]`, `deleted-XXXXXXXX@deleted.invalid`, etc.)
2. Replaces `vehicle_plate` on all bookings with `[GELÖSCHT]`
3. Deletes absences, vehicles, favourites, notifications, push subscriptions
4. Sets `is_active = false`
5. Sets `password` to a random 64-character string (account becomes inaccessible)
6. Revokes all tokens
7. Writes a `gdpr_erasure` event to the audit log (without PII — only the anonymized ID)

After anonymization, booking records remain (with anonymized user reference) for accounting purposes. The user cannot log in again.

**User-facing:** Settings page → "Delete My Account" → choose Anonymize

**Operator action required:** None. Implement this as the default for user deletion requests received by email/form, by calling the API endpoint on the user's behalf.

---

### Art. 15 — Right of Access

Users can view all their data through the application (bookings, absences, vehicles, preferences). The `/api/v1/user/export` endpoint provides a complete machine-readable copy.

---

### Art. 16 — Right to Rectification

Users can update their name, email, phone, and department via Settings. Admins can update any field via the admin panel.

---

### Art. 21 — Right to Object

Users can disable email notifications, push notifications, and opt out of non-essential data processing via their preferences.

---

## Data Retention

### Booking Records

Under German tax law (§147 AO — Abgabenordnung), business records must be retained for **10 years**. If ParkHub is used for commercial parking operations (paid parking, corporate reporting), booking records fall under this obligation.

**Recommendation:** Use the anonymization endpoint (Art. 17) rather than full deletion for users who request erasure. This preserves the booking record while removing all PII.

### Audit Log

The audit log contains IP addresses and usernames. Define a retention period appropriate for your security policy (typically 90 days to 1 year).

There is no built-in automatic pruning. You can add a scheduled artisan command:

```php
// In a console command or scheduled task:
AuditLog::where('created_at', '<', now()->subDays(90))->delete();
```

### Session Data

Sessions expire after `SESSION_LIFETIME` minutes (default 120). The `sessions` table can be pruned with `php artisan session:gc` or via the standard Laravel session garbage collection.

---

## Legal Documents (German Operators)

### Impressum (DDG §5)

The Impressum is **legally required** in Germany for any commercial digital service. ParkHub provides:

1. **Admin editor:** Admin panel → Impressum. Stores provider name, address, email, phone, company register, VAT ID.
2. **Public endpoint:** `GET /api/v1/legal/impressum` — no authentication required (legally required to be freely accessible).
3. **Frontend page:** `/impressum` renders the stored Impressum data.
4. **Template:** `legal/impressum-template.md` — fill in your data and enter it in the admin panel.

### Datenschutzerklarung (Privacy Policy)

A DSGVO-compliant privacy policy is required. A template is provided in `legal/datenschutz-template.md`.

The operator must adapt the template to reflect:
- Their organization's name and contact details
- The specific categories of data processed
- Data processor agreements (AVV) with any third-party services (SMTP provider, etc.)
- The contact details of the data protection officer (if applicable)

Store the privacy policy text in Admin → Privacy. It is displayed at `/privacy`.

### AGB (Terms of Service)

A template is provided in `legal/agb-template.md`. Required for commercial parking services.

### AVV (Auftragsverarbeitungsvertrag — Data Processing Agreement)

If your ParkHub installation processes personal data of another organization's employees (e.g. a SaaS model, or a company deploying ParkHub for a customer), a DSGVO Art. 28 data processing agreement is required between you and the data controller.

A template is provided in `legal/avv-template.md`.

---

## Technical and Organizational Measures (TOMs)

ParkHub PHP implements these technical measures:

| Measure | Implementation |
|---------|---------------|
| Encryption at rest | Use database encryption, disk encryption, or MySQL encrypted tablespaces (operator responsibility) |
| Encryption in transit | HTTPS (operator responsibility — configure TLS at the reverse proxy) |
| Access control | Role-based (user / admin / superadmin), Sanctum token authentication |
| Pseudonymization | Anonymization endpoint replaces PII with pseudonymous identifiers |
| Audit logging | All write operations logged with user, action, IP, timestamp |
| Brute-force protection | Rate limiting on login (10/min) and password reset (5/15min) |
| Data minimization | Only required fields collected; preferences are user-controlled |
| Password security | bcrypt with 12 rounds |

Organizational measures (privacy by default, staff training, incident response procedures, DPA registration) are the responsibility of the operator.

---

## Checklist for Operators

Before going live, complete these DSGVO steps:

- [ ] Fill in Impressum data in Admin → Impressum (DDG §5)
- [ ] Adapt and publish a Datenschutzerklarung (Admin → Privacy → policy text)
- [ ] Adapt and publish AGB (if commercial service)
- [ ] Sign an AVV with your SMTP provider (e.g. SendGrid, Mailgun, Postmark)
- [ ] Sign an AVV with your hosting provider if they can access your data
- [ ] Enable HTTPS (TLS 1.2+ at reverse proxy)
- [ ] Enable disk encryption at the OS level for the storage volume
- [ ] Define a data retention policy for audit logs
- [ ] Register with your national data protection authority if required (varies by country/company size)
- [ ] Verify that `GDPR_ENABLED=true` is set in the admin settings
- [ ] Test the export endpoint (`GET /api/v1/user/export`) — download and verify completeness
- [ ] Test the anonymization endpoint — verify PII is removed and booking records are preserved
