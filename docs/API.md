# API Reference

Full REST API reference for ParkHub PHP.

All endpoints are available under two prefixes:
- `/api/*` — legacy routes (same behaviour, shorter paths)
- `/api/v1/*` — v1 routes, compatible with the ParkHub Docker (Rust) edition

This document uses the `/api/v1/` prefix throughout.

---

## Authentication

ParkHub uses **Laravel Sanctum** Bearer tokens. After login, include the token in every protected request:

```
Authorization: Bearer <access_token>
```

Tokens expire after 7 days. Refresh by calling `/api/v1/auth/refresh`.

---

## Auth

### POST /api/v1/auth/login

Authenticate with username or email and password. Rate limited: 10 requests/minute per IP.

**Request:**
```json
{
  "username": "john.doe",
  "password": "secret123"
}
```

`username` accepts either the username or the email address.

**Response 200:**
```json
{
  "user": {
    "id": "uuid",
    "username": "john.doe",
    "email": "john@example.com",
    "name": "John Doe",
    "role": "user",
    "is_active": true,
    "department": "IT",
    "preferences": {},
    "last_login": "2026-01-01T10:00:00.000000Z"
  },
  "tokens": {
    "access_token": "1|abc123...",
    "token_type": "Bearer",
    "expires_at": "2026-01-08T10:00:00.000000Z"
  }
}
```

**Response 401:** `{ "error": "INVALID_CREDENTIALS", "message": "..." }`

**curl example:**
```bash
curl -X POST https://parking.example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"secret"}'
```

---

### POST /api/v1/auth/register

Create a new user account. Rate limited: 10 requests/minute per IP. Sends a welcome email (queued).

**Request:**
```json
{
  "username": "jane.doe",
  "email": "jane@example.com",
  "password": "securepass123",
  "name": "Jane Doe"
}
```

**Response 201:** Same structure as `/auth/login`.

---

### POST /api/v1/auth/refresh

Rotate the current token. Revokes all existing tokens and issues a new one. Requires authentication.

**Response 200:**
```json
{
  "tokens": {
    "access_token": "2|xyz...",
    "token_type": "Bearer",
    "expires_at": "2026-01-15T10:00:00.000000Z"
  }
}
```

---

### POST /api/v1/auth/forgot-password

Request a password reset. Rate limited: 5 requests/15 minutes per IP. Returns a generic success message regardless of whether the email exists (prevents user enumeration).

**Request:** `{ "email": "user@example.com" }`

**Response 200:** `{ "message": "If an account with that email exists, a reset link has been sent." }`

---

### GET /api/v1/users/me

Return the authenticated user's profile.

**Response 200:** User object (same structure as login response).

---

### PUT /api/v1/users/me

Update the authenticated user's profile (name, email, phone, department).

**Request (all fields optional):**
```json
{
  "name": "Jane Smith",
  "email": "jane.smith@example.com",
  "phone": "+49 151 12345678",
  "department": "Engineering"
}
```

---

### PATCH /api/v1/users/me/password

Change password. Requires current password. Issues a new token and revokes all existing tokens.

**Request:**
```json
{
  "current_password": "old-secret",
  "new_password": "new-secret-min8"
}
```

---

### DELETE /api/v1/users/me/delete

Permanently delete the authenticated account and all associated data (CASCADE). Requires password confirmation.

**Request:** `{ "password": "current-password" }`

---

## Setup

### GET /api/v1/setup/status

Returns whether initial setup has been completed.

**Response:** `{ "setup_completed": true }` (no auth required)

---

### POST /api/v1/setup

Initialize the application (first-run only).

### POST /api/v1/setup/change-password

Change the default admin password during setup wizard flow.

### POST /api/v1/setup/complete

Mark setup as completed and store company name and use case.

---

## Parking Lots

All lot endpoints require authentication.

### GET /api/v1/lots

List all parking lots with real-time available slot counts.

**Response:**
```json
[
  {
    "id": "uuid",
    "name": "P+R Hauptbahnhof",
    "address": "Bahnhofplatz 1, 80335 München",
    "total_slots": 50,
    "available_slots": 23,
    "status": "open",
    "layout": null
  }
]
```

---

### POST /api/v1/lots

Create a new parking lot.

**Request:**
```json
{
  "name": "Tiefgarage Ost",
  "address": "Musterstraße 1, 10115 Berlin",
  "total_slots": 40,
  "status": "open"
}
```

---

### GET /api/v1/lots/{id}

Get a lot with its layout. If no layout has been saved, one is auto-generated from the slots (rows of 10).

---

### PUT /api/v1/lots/{id}

Update lot name, address, total_slots, layout, or status.

---

### DELETE /api/v1/lots/{id}

Delete a lot and all its slots (CASCADE).

---

### GET /api/v1/lots/{id}/slots

List all slots for a lot with current booking status.

---

### GET /api/v1/lots/{id}/occupancy

Real-time occupancy for a lot.

**Response:**
```json
{
  "lot_id": "uuid",
  "lot_name": "P+R Hauptbahnhof",
  "total": 50,
  "occupied": 27,
  "available": 23,
  "percentage": 54
}
```

---

### GET /api/v1/lots/{id}/qr

Generate a QR code URL for quick booking at this lot.

### GET /api/v1/lots/{lotId}/slots/{slotId}/qr

Generate a QR code URL for a specific slot.

---

## Zones

### GET /api/v1/lots/{lotId}/zones

List zones for a lot.

### POST /api/v1/lots/{lotId}/zones

Create a zone. Request: `{ "name": "UG1", "color": "#3b82f6", "description": "..." }`

### PUT /api/v1/lots/{lotId}/zones/{id}

Update a zone.

### DELETE /api/v1/lots/{lotId}/zones/{id}

Delete a zone (sets `zone_id` to null on slots in this zone).

---

## Slots

### POST /api/v1/lots/{lotId}/slots

Create a slot in a lot.

**Request:**
```json
{
  "slot_number": "A-01",
  "status": "available",
  "zone_id": "uuid-of-zone",
  "reserved_for_department": "Management"
}
```

---

### PUT /api/v1/lots/{lotId}/slots/{slotId}

Update a slot (number, status, zone, department reservation).

---

### DELETE /api/v1/lots/{lotId}/slots/{slotId}

Delete a slot.

---

## Bookings

### GET /api/v1/bookings

List the authenticated user's bookings. Supports filters:
- `?status=confirmed` — filter by status
- `?from_date=2026-01-01` — bookings starting on or after this date
- `?to_date=2026-12-31` — bookings ending on or before this date

---

### POST /api/v1/bookings

Create a booking. If `slot_id` is omitted, the system auto-assigns the first available slot.

**Request:**
```json
{
  "lot_id": "uuid",
  "slot_id": "uuid",
  "start_time": "2026-03-01T08:00:00",
  "end_time": "2026-03-01T18:00:00",
  "booking_type": "einmalig",
  "vehicle_plate": "M-AB 1234",
  "notes": "Near entrance please"
}
```

**Response 409** if slot is unavailable or no slots available.

Sends a booking confirmation email (queued).

---

### POST /api/v1/bookings/quick

One-tap booking. Auto-assigns a slot in the user's default lot.

**Request:** `{ "lot_id": "uuid", "start_time": "...", "end_time": "..." }`

---

### PATCH /api/v1/bookings/{id}

Update booking fields (e.g. vehicle_plate, notes).

---

### DELETE /api/v1/bookings/{id}

Cancel a booking.

---

### POST /api/v1/bookings/guest

Create a booking for a named guest (no user account required for the guest).

**Request:**
```json
{
  "lot_id": "uuid",
  "slot_id": "uuid",
  "guest_name": "Max Mustermann",
  "start_time": "2026-03-01T09:00:00",
  "end_time": "2026-03-01T12:00:00",
  "vehicle_plate": "B-CD 5678"
}
```

---

### POST /api/v1/bookings/swap

Swap two bookings between users.

---

### POST /api/v1/bookings/{id}/swap-request

Create a swap request for a booking.

### PUT /api/v1/swap-requests/{id}

Accept or reject a swap request.

---

### POST /api/v1/bookings/{id}/checkin

Mark a booking as checked in.

---

### PUT /api/v1/bookings/{id}/notes

Update notes on a booking.

---

### GET /api/v1/bookings/{id}/invoice

Return an HTML invoice for a booking (printer-friendly, use browser Print → Save as PDF).

---

### GET /api/v1/calendar/events

Return bookings formatted as calendar events.

---

## Recurring Bookings

### GET /api/v1/recurring-bookings

List recurring booking patterns for the authenticated user.

---

### POST /api/v1/recurring-bookings

Create a recurring booking pattern.

**Request:**
```json
{
  "lot_id": "uuid",
  "slot_id": "uuid",
  "days_of_week": [0, 1, 2, 3, 4],
  "start_date": "2026-03-01",
  "end_date": "2026-12-31",
  "start_time": "08:00",
  "end_time": "18:00",
  "vehicle_plate": "M-AB 1234"
}
```

`days_of_week`: 0 = Monday … 6 = Sunday.

---

### PUT /api/v1/recurring-bookings/{id}

Update a recurring pattern.

### DELETE /api/v1/recurring-bookings/{id}

Deactivate or delete a recurring pattern.

---

## Absences

### GET /api/v1/absences

List absences for the authenticated user.

### POST /api/v1/absences

Create an absence.

**Request:**
```json
{
  "absence_type": "homeoffice",
  "start_date": "2026-03-10",
  "end_date": "2026-03-10",
  "note": "Working from home"
}
```

`absence_type`: `homeoffice`, `vacation`, `sick`, `training`, `other`

### PUT /api/v1/absences/{id}

Update an absence.

### DELETE /api/v1/absences/{id}

Delete an absence.

---

### GET /api/v1/absences/pattern

Get the user's recurring absence pattern (e.g. every Monday homeoffice).

### POST /api/v1/absences/pattern

Set the recurring absence pattern.

### GET /api/v1/absences/team

View team absences (all users' absences for planning).

---

### POST /api/v1/absences/import

Import absences from an iCal (.ics) file upload.

---

## Vehicles

### GET /api/v1/vehicles

List the authenticated user's vehicles.

### POST /api/v1/vehicles

Add a vehicle.

**Request:**
```json
{
  "plate": "M-AB 1234",
  "make": "BMW",
  "model": "3 Series",
  "color": "Blau",
  "is_default": true
}
```

### PUT /api/v1/vehicles/{id}

Update a vehicle.

### DELETE /api/v1/vehicles/{id}

Delete a vehicle (also removes the stored photo if present).

---

### POST /api/v1/vehicles/{id}/photo

Upload a vehicle photo. Accepts `multipart/form-data` with a `photo` file (JPEG/PNG/GIF/WebP, max 5 MB) or a JSON body with `photo_base64` (base64-encoded image, max 8 MB).

Images are validated through GD and resized to max 800px on the longer edge before storage.

### GET /api/v1/vehicles/{id}/photo

Serve the stored vehicle photo as `image/jpeg`.

---

### GET /api/v1/vehicles/city-codes

Return a list of 400+ German Kfz-Unterscheidungszeichen (licence plate city codes) with city names.

---

## Team

### GET /api/v1/team

List all active users (for team visibility).

### GET /api/v1/team/today

Show who is in the office, on homeoffice, or on vacation today.

---

## Waitlist

### GET /api/v1/waitlist

List the user's waitlist entries.

### POST /api/v1/waitlist

Join the waitlist for a lot on a specific date.

### DELETE /api/v1/waitlist/{id}

Leave the waitlist.

---

## User Preferences and Stats

### GET /api/v1/user/preferences

Return user preferences (theme, language, timezone, notification settings, etc.).

### PUT /api/v1/user/preferences

Update preferences. Accepted fields:

```json
{
  "language": "de",
  "theme": "dark",
  "notifications_enabled": true,
  "email_notifications": true,
  "push_notifications": false,
  "show_plate_in_calendar": true,
  "default_lot_id": "uuid",
  "locale": "de-DE",
  "timezone": "Europe/Berlin"
}
```

### GET /api/v1/user/stats

Return statistics for the user (total bookings, this month, homeoffice days, favourite slot).

### GET /api/v1/user/favorites

List the user's favourite slots.

### POST /api/v1/user/favorites

Add a slot to favourites. Request: `{ "slot_id": "uuid" }`

### DELETE /api/v1/user/favorites/{slotId}

Remove a slot from favourites.

---

## Notifications

### GET /api/v1/notifications

List the last 50 in-app notifications for the user.

### PUT /api/v1/notifications/{id}/read

Mark a notification as read.

### POST /api/v1/notifications/read-all

Mark all notifications as read.

---

## Calendar / iCal

### GET /api/v1/user/calendar.ics

Download the user's bookings as an iCal (.ics) file. Import into any calendar application (Google Calendar, Apple Calendar, Outlook).

---

## GDPR

### GET /api/v1/user/export

Download a JSON archive of all personal data for the authenticated user. Covers: profile, bookings, absences, vehicles, preferences. Satisfies GDPR Art. 20 (data portability).

**Response:** JSON file download (`my-parkhub-data.json`)

```bash
curl -H "Authorization: Bearer TOKEN" \
  https://parking.example.com/api/v1/user/export \
  -o my-data.json
```

---

### POST /api/v1/users/me/anonymize

Anonymize the account per GDPR Art. 17 (right to erasure). Requires password confirmation.

- Strips all PII from the user record (name, email, phone, picture, department)
- Deletes absences, vehicles, favourites, notifications, push subscriptions
- Keeps booking records with vehicle plate replaced by `[GELÖSCHT]` (required for §147 AO retention)
- Invalidates all tokens
- Writes a `gdpr_erasure` entry to the audit log

**Request:** `{ "password": "current-password", "reason": "User request" }`

**Response 200:** `{ "message": "Account anonymized. Personal data erased per GDPR Art. 17." }`

---

## Admin

All `/admin/*` endpoints require `role=admin` or `role=superadmin`.

### GET /api/v1/admin/stats

Dashboard statistics: total users, lots, slots, active bookings, occupancy percentage, homeoffice count today.

### GET /api/v1/admin/heatmap

Booking heatmap grouped by day of week and hour. Query: `?days=30`

### GET /api/v1/admin/reports

Booking reports for a period. Query: `?days=30`

### GET /api/v1/admin/dashboard/charts

Chart data: booking trend over last N days, current occupancy. Query: `?days=7`

### GET /api/v1/admin/audit-log

Paginated audit log. Supports `?action=login_failed`, `?search=username`, `?per_page=50`.

---

### GET /api/v1/admin/announcements

List all announcements.

### POST /api/v1/admin/announcements

Create an announcement.

**Request:**
```json
{
  "title": "Maintenance",
  "message": "The lot will be closed on Friday.",
  "severity": "warning"
}
```

`severity`: `info`, `warning`, `error`, `success`

### PUT /api/v1/admin/announcements/{id}

Update an announcement (title, message, severity, active).

### DELETE /api/v1/admin/announcements/{id}

Delete an announcement.

---

### GET /api/v1/admin/users

List all users.

### PUT /api/v1/admin/users/{id}

Update a user (name, email, role, is_active, department, password).

### DELETE /api/v1/admin/users/{id}

Delete a user account. Cannot delete your own account.

### POST /api/v1/admin/users/import

Bulk import up to 500 users. Skips existing usernames/emails.

**Request:**
```json
{
  "users": [
    {
      "username": "max.mueller",
      "email": "max@example.com",
      "name": "Max Müller",
      "role": "user",
      "department": "IT",
      "password": "initial-pass"
    }
  ]
}
```

---

### GET /api/v1/admin/settings

Return all application settings.

### PUT /api/v1/admin/settings

Update settings. Accepted keys: `company_name`, `use_case`, `self_registration`, `license_plate_mode`, `display_name_format`, `max_bookings_per_day`, `allow_guest_bookings`, `auto_release_minutes`, `require_vehicle`, `primary_color`, `secondary_color`.

---

### GET /api/v1/admin/settings/email

Return email settings.

### PUT /api/v1/admin/settings/email

Update email settings: `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `from_email`, `from_name`, `enabled`.

---

### GET /api/v1/admin/settings/auto-release

Return auto-release settings.

### PUT /api/v1/admin/settings/auto-release

Update: `{ "enabled": true, "timeout_minutes": 30 }`

---

### GET /api/v1/admin/settings/webhooks

Return registered webhook URLs.

### PUT /api/v1/admin/settings/webhooks

Replace the full list of webhooks.

---

### GET /api/v1/admin/branding

Return branding settings.

### PUT /api/v1/admin/branding

Update: `company_name`, `primary_color`, `logo_url`, `use_case`.

### POST /api/v1/admin/branding/logo

Upload a logo image (JPEG/PNG, max 2 MB). Multipart form field: `logo`.

---

### GET /api/v1/admin/privacy

Return privacy / GDPR settings.

### PUT /api/v1/admin/privacy

Update: `policy_text`, `data_retention_days`, `gdpr_enabled`.

---

### GET /api/v1/admin/impressum

Return Impressum (DDG §5) fields for the admin editor.

### PUT /api/v1/admin/impressum

Update Impressum fields: `provider_name`, `provider_legal_form`, `street`, `zip_city`, `country`, `email`, `phone`, `register_court`, `register_number`, `vat_id`, `responsible_person`, `custom_text`.

---

### GET /api/v1/admin/bookings/export

Download all bookings as a CSV file.

```bash
curl -H "Authorization: Bearer TOKEN" \
  https://parking.example.com/api/v1/admin/bookings/export \
  -o bookings.csv
```

---

### DELETE /api/v1/admin/lots/{id}

Delete a lot and all its slots (admin variant).

### PATCH /api/v1/admin/slots/{id}

Update a slot's number, status, zone, or department reservation.

---

### POST /api/v1/admin/reset

Delete all user data (bookings, absences, vehicles) except the calling admin's account. Requires `{ "confirm": "RESET" }` in the request body. Use with caution.

---

## Public Endpoints (No Authentication)

### GET /api/v1/public/occupancy

Real-time occupancy for all lots. Suitable for embedding in lobby displays.

### GET /api/v1/public/display

Extended occupancy display data.

### GET /api/v1/announcements/active

Return currently active, non-expired announcements.

### GET /api/v1/branding

Return public branding settings (company name, primary color, logo URL).

### GET /api/v1/branding/logo

Serve the branding logo. Returns a default SVG "P" icon if no logo has been uploaded.

### GET /api/v1/legal/impressum

Return the Impressum (DDG §5) fields. Must be publicly accessible per German law.

---

## System / Health

### GET /api/v1/health/live

Liveness probe — returns 200 if the PHP process is running.

```bash
curl https://parking.example.com/api/v1/health/live
# {"status":"ok"}
```

### GET /api/v1/health/ready

Readiness probe — returns 200 if the database is reachable.

### GET /api/v1/system/version

Return the current application version.

### GET /api/v1/system/maintenance

Return maintenance mode status.

---

## Webhooks (User)

### GET /api/v1/webhooks

List the user's registered webhooks.

### POST /api/v1/webhooks

Create a webhook.

**Request:**
```json
{
  "url": "https://hooks.example.com/parkhub",
  "events": ["booking.created", "booking.cancelled"],
  "secret": "optional-hmac-secret"
}
```

### PUT /api/v1/webhooks/{id}

Update a webhook.

### DELETE /api/v1/webhooks/{id}

Delete a webhook.

---

## Push Notifications

### POST /api/v1/push/subscribe

Register a Web Push subscription.

### DELETE /api/v1/push/unsubscribe

Remove all push subscriptions for the authenticated user.

---

## Error Responses

All error responses follow this structure:

```json
{
  "error": "ERROR_CODE",
  "message": "Human-readable description"
}
```

Common error codes:

| Code | HTTP | Meaning |
|------|------|---------|
| `INVALID_CREDENTIALS` | 401 | Wrong username or password |
| `ACCOUNT_DISABLED` | 403 | Account has been deactivated by an admin |
| `INVALID_PASSWORD` | 400/403 | Password confirmation failed |
| `NO_SLOTS_AVAILABLE` | 409 | No free slots in the requested lot/time window |
| `SLOT_UNAVAILABLE` | 409 | Specific slot already booked for the requested time |
| `INVALID_IMAGE` | 422 | Uploaded file is not a valid image |

Laravel validation errors return HTTP 422 with a `errors` object:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```
