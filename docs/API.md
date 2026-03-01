# API Reference — ParkHub PHP

Full REST API reference for ParkHub PHP (Laravel 12 + Sanctum).

All endpoints are available under the `/api/v1/` prefix.
A legacy `/api/` prefix is also supported for backwards compatibility.

---

## Table of Contents

- [Authentication](#authentication)
- [Response Format](#response-format)
- [Error Codes](#error-codes)
- [Rate Limits](#rate-limits)
- [Auth Endpoints](#auth-endpoints)
- [Setup](#setup)
- [Users & Profile](#users--profile)
- [GDPR Endpoints](#gdpr-endpoints)
- [Parking Lots](#parking-lots)
- [Zones](#zones)
- [Slots](#slots)
- [Bookings](#bookings)
- [Recurring Bookings](#recurring-bookings)
- [Absences](#absences)
- [Vehicles](#vehicles)
- [Team](#team)
- [Waitlist](#waitlist)
- [User Preferences & Stats](#user-preferences--stats)
- [Notifications](#notifications)
- [Calendar / iCal](#calendar--ical)
- [Webhooks](#webhooks)
- [Push Notifications](#push-notifications)
- [Admin Endpoints](#admin-endpoints)
- [Public Endpoints](#public-endpoints)
- [System & Health](#system--health)

---

## Authentication

ParkHub uses **Laravel Sanctum** opaque Bearer tokens.

After login, include the token in every protected request:

```
Authorization: Bearer <access_token>
```

Tokens expire after **7 days**. Refresh with `POST /api/v1/auth/refresh`.

```bash
# Save token to a shell variable (username field also accepts an email address)
TOKEN=$(curl -s -X POST https://parking.example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"secret"}' \
  | jq -r '.tokens.access_token')
```

---

## Response Format

Successful responses return the data directly (no envelope wrapper):

```json
{
  "id": "uuid",
  "name": "Parkplatz A",
  ...
}
```

or an array:

```json
[ { ... }, { ... } ]
```

Error responses:

```json
{
  "error": "INVALID_CREDENTIALS",
  "message": "Invalid username or password"
}
```

Laravel validation errors (HTTP 422):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

---

## Error Codes

| Code | HTTP | Meaning |
|------|------|---------|
| `INVALID_CREDENTIALS` | 401 | Wrong username or password |
| `ACCOUNT_DISABLED` | 403 | Account deactivated by an admin |
| `INVALID_PASSWORD` | 400/403 | Password confirmation failed |
| `NO_SLOTS_AVAILABLE` | 409 | No free slots in the requested lot/time window |
| `SLOT_UNAVAILABLE` | 409 | Specific slot already booked for the requested time |
| `INVALID_IMAGE` | 422 | Uploaded file is not a valid image |

---

## Rate Limits

| Endpoint | Limit | Window |
|----------|-------|--------|
| `POST /api/v1/auth/login` | 10 requests | 1 minute per IP |
| `POST /api/v1/auth/register` | 10 requests | 1 minute per IP |
| `POST /api/v1/auth/forgot-password` | 5 requests | 15 minutes per IP |

---

## Auth Endpoints

### POST /api/v1/auth/login

Authenticate and receive a Bearer token.

```bash
curl -s -X POST https://parking.example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"secret"}'
```

Request:

```json
{
  "username": "john.doe",
  "password": "secret123"
}
```

The `username` field accepts either a username or an email address.

Response (HTTP 200):

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
    "last_login": "2026-02-27T10:00:00.000000Z"
  },
  "tokens": {
    "access_token": "1|abc123...",
    "token_type": "Bearer",
    "expires_at": "2026-03-06T10:00:00.000000Z"
  }
}
```

---

### POST /api/v1/auth/register

Register a new user account. Sends a welcome email (queued). Rate limited: 10/min per IP.

```bash
curl -s -X POST https://parking.example.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"jane.doe","email":"jane@example.com","password":"securepass123","name":"Jane Doe"}'
```

Request:

```json
{
  "username": "jane.doe",
  "email": "jane@example.com",
  "password": "securepass123",
  "name": "Jane Doe"
}
```

Response (HTTP 201): same structure as login.

---

### POST /api/v1/auth/refresh

Rotate the Bearer token. Revokes all existing tokens and issues a new one. Auth required.

```bash
curl -s -X POST https://parking.example.com/api/v1/auth/refresh \
  -H "Authorization: Bearer $TOKEN"
```

Response:

```json
{
  "tokens": {
    "access_token": "2|xyz...",
    "token_type": "Bearer",
    "expires_at": "2026-03-13T10:00:00.000000Z"
  }
}
```

---

### POST /api/v1/auth/forgot-password

Request a password reset link. Rate limited: 5/15min per IP.
Returns a generic success message regardless of whether the email exists (prevents user enumeration).

```bash
curl -s -X POST https://parking.example.com/api/v1/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'
```

Response: `{ "message": "If an account with that email exists, a reset link has been sent." }`

---

## Setup

### GET /api/v1/setup/status

Check whether initial setup has been completed. No auth required.

```bash
curl -s https://parking.example.com/api/v1/setup/status
# {"setup_completed":true}
```

### POST /api/v1/setup

Initialize the application on first run.

### POST /api/v1/setup/change-password

Change the default admin password during the setup wizard flow.

Request: `{ "current_password": "admin", "new_password": "new-strong-password" }`

### POST /api/v1/setup/complete

Mark setup as complete and save company name and use case.

Request: `{ "company_name": "Muster GmbH", "use_case": "company" }`

---

## Users & Profile

### GET /api/v1/users/me

Return the authenticated user's profile.

```bash
curl -s https://parking.example.com/api/v1/users/me \
  -H "Authorization: Bearer $TOKEN"
```

### PUT /api/v1/users/me

Update the authenticated user's profile. All fields optional.

```bash
curl -s -X PUT https://parking.example.com/api/v1/users/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Smith","email":"jane.smith@example.com","phone":"+49 151 12345678"}'
```

### PATCH /api/v1/users/me/password

Change password. Requires current password. Rotates all tokens.

```bash
curl -s -X PATCH https://parking.example.com/api/v1/users/me/password \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"current_password":"old","new_password":"new-min-8-chars"}'
```

### DELETE /api/v1/users/me/delete

Hard-delete the account and all associated data (CASCADE). Requires password confirmation.

```bash
curl -s -X DELETE https://parking.example.com/api/v1/users/me/delete \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password":"current-password"}'
```

---

## GDPR Endpoints

### GET /api/v1/users/me/export

Download all personal data as a JSON file. Implements GDPR Art. 20 (Data Portability).

The legacy path `/api/v1/user/export` is also supported for backwards compatibility.

```bash
curl -s https://parking.example.com/api/v1/users/me/export \
  -H "Authorization: Bearer $TOKEN" \
  -o my-parkhub-data.json
```

The export includes: profile, all bookings, all absences, all vehicles, preferences.

---

### POST /api/v1/users/me/anonymize

Anonymize the account per GDPR Art. 17 (Right to Erasure). Requires password confirmation.

```bash
curl -s -X POST https://parking.example.com/api/v1/users/me/anonymize \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password":"current-password","reason":"User request"}'
```

What this endpoint does:

1. Replaces `name`, `email`, `username`, `phone`, `picture`, `department` with placeholders
2. Replaces `vehicle_plate` on all bookings with `[GELÖSCHT]` (§147 AO retention compliance)
3. Deletes absences, vehicles, favourites, notifications, push subscriptions
4. Sets `is_active = false`
5. Sets password to an unguessable random string (account becomes permanently inaccessible)
6. Revokes all tokens
7. Writes a `gdpr_erasure` entry to the audit log

Response: `{ "message": "Account anonymized. Personal data erased per GDPR Art. 17." }`

---

## Parking Lots

All lot endpoints require authentication.

### GET /api/v1/lots

List all parking lots with real-time available slot counts.

```bash
curl -s https://parking.example.com/api/v1/lots \
  -H "Authorization: Bearer $TOKEN"
```

Response:

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

### POST /api/v1/lots

Create a parking lot. Admin required.

```bash
curl -s -X POST https://parking.example.com/api/v1/lots \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Tiefgarage Ost","address":"Musterstraße 1, 10115 Berlin","total_slots":40,"status":"open"}'
```

### GET /api/v1/lots/:id

Get a lot including layout. Auto-generates layout from slots if none saved.

### PUT /api/v1/lots/:id

Update lot name, address, total_slots, layout JSON, or status.

### DELETE /api/v1/lots/:id

Delete a lot and all its slots (CASCADE).

### GET /api/v1/lots/:id/slots

List all slots for a lot including current booking status.

### GET /api/v1/lots/:id/occupancy

Real-time occupancy figures.

```bash
curl -s "https://parking.example.com/api/v1/lots/LOT_UUID/occupancy" \
  -H "Authorization: Bearer $TOKEN"
```

Response:

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

### GET /api/v1/lots/:id/qr

Generate a QR code URL for quick booking at this lot.

### GET /api/v1/lots/:lotId/slots/:slotId/qr

Generate a QR code URL for a specific slot.

---

## Zones

### GET /api/v1/lots/:lotId/zones

List zones for a lot.

### POST /api/v1/lots/:lotId/zones

Create a zone.

```json
{ "name": "UG1", "color": "#3b82f6", "description": "Untergeschoss 1" }
```

### PUT /api/v1/lots/:lotId/zones/:id

Update a zone.

### DELETE /api/v1/lots/:lotId/zones/:id

Delete a zone (sets `zone_id` to null on slots in this zone).

---

## Slots

### POST /api/v1/lots/:lotId/slots

Create a slot in a lot.

```bash
curl -s -X POST "https://parking.example.com/api/v1/lots/LOT_UUID/slots" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"slot_number":"A-01","status":"available","zone_id":"ZONE_UUID","reserved_for_department":"Management"}'
```

### PUT /api/v1/lots/:lotId/slots/:slotId

Update slot number, status, zone, or department reservation.

### DELETE /api/v1/lots/:lotId/slots/:slotId

Delete a slot.

---

## Bookings

### GET /api/v1/bookings

List the authenticated user's bookings. Optional filters:
- `?status=confirmed` — filter by status
- `?from_date=2026-01-01` — bookings starting on or after this date
- `?to_date=2026-12-31` — bookings ending on or before this date

```bash
curl -s "https://parking.example.com/api/v1/bookings?status=confirmed" \
  -H "Authorization: Bearer $TOKEN"
```

### POST /api/v1/bookings

Create a booking. If `slot_id` is omitted, the system auto-assigns the first available slot.
Sends a booking confirmation email (queued).

```bash
curl -s -X POST https://parking.example.com/api/v1/bookings \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lot_id": "LOT_UUID",
    "slot_id": "SLOT_UUID",
    "start_time": "2026-03-01T08:00:00",
    "end_time": "2026-03-01T18:00:00",
    "booking_type": "einmalig",
    "vehicle_plate": "M-AB 1234",
    "notes": "Near entrance please"
  }'
```

Returns HTTP 409 if slot is unavailable or no slots available in the lot.

### POST /api/v1/bookings/quick

One-tap booking. Auto-assigns the best available slot in a lot.

Request: `{ "lot_id": "uuid", "start_time": "...", "end_time": "..." }`

### PATCH /api/v1/bookings/:id

Update booking fields (vehicle_plate, notes).

### DELETE /api/v1/bookings/:id

Cancel a booking.

### POST /api/v1/bookings/guest

Create a booking for a named guest (no user account needed for the guest).

```bash
curl -s -X POST https://parking.example.com/api/v1/bookings/guest \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lot_id": "LOT_UUID",
    "slot_id": "SLOT_UUID",
    "guest_name": "Max Mustermann",
    "start_time": "2026-03-01T09:00:00",
    "end_time": "2026-03-01T12:00:00",
    "vehicle_plate": "B-CD 5678"
  }'
```

### POST /api/v1/bookings/swap

Swap two bookings between users.

### POST /api/v1/bookings/:id/swap-request

Create a swap request for a booking.

### PUT /api/v1/swap-requests/:id

Accept or reject a swap request.

Request: `{ "action": "accept" }` or `{ "action": "reject" }`

### POST /api/v1/bookings/:id/checkin

Mark a booking as checked in (validates QR code scan).

### PUT /api/v1/bookings/:id/notes

Update notes on a booking.

### GET /api/v1/bookings/:id/invoice

Get an HTML invoice (printer-friendly, use browser Print → Save as PDF).

```bash
curl -s "https://parking.example.com/api/v1/bookings/BOOKING_UUID/invoice" \
  -H "Authorization: Bearer $TOKEN"
```

### GET /api/v1/calendar/events

Return bookings formatted as calendar events.

---

## Recurring Bookings

### GET /api/v1/recurring-bookings

List recurring booking patterns for the authenticated user.

### POST /api/v1/recurring-bookings

Create a recurring booking pattern.

```bash
curl -s -X POST https://parking.example.com/api/v1/recurring-bookings \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lot_id": "LOT_UUID",
    "slot_id": "SLOT_UUID",
    "days_of_week": [0,1,2,3,4],
    "start_date": "2026-03-01",
    "end_date": "2026-12-31",
    "start_time": "08:00",
    "end_time": "18:00",
    "vehicle_plate": "M-AB 1234"
  }'
```

`days_of_week`: 0 = Monday, 1 = Tuesday, ..., 6 = Sunday.

### PUT /api/v1/recurring-bookings/:id

Update a recurring pattern.

### DELETE /api/v1/recurring-bookings/:id

Deactivate or delete a recurring pattern.

---

## Absences

### GET /api/v1/absences

List absences for the authenticated user.

### POST /api/v1/absences

Create an absence.

```bash
curl -s -X POST https://parking.example.com/api/v1/absences \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"absence_type":"homeoffice","start_date":"2026-03-10","end_date":"2026-03-10","note":"Working from home"}'
```

`absence_type`: `homeoffice`, `vacation`, `sick`, `training`, `other`

### PUT /api/v1/absences/:id

Update an absence.

### DELETE /api/v1/absences/:id

Delete an absence.

### GET /api/v1/absences/pattern

Get the user's recurring absence pattern (e.g. every Monday homeoffice).

### POST /api/v1/absences/pattern

Set the recurring absence pattern.

Request: `{ "weekdays": [0] }` (array of day indices: 0=Monday)

### GET /api/v1/absences/team

View all users' absences for team planning.

### POST /api/v1/absences/import

Import absences from an iCal `.ics` file.

Request: `multipart/form-data` with `ical_file` field.

---

## Vehicles

### GET /api/v1/vehicles

List the authenticated user's vehicles.

### POST /api/v1/vehicles

Add a vehicle.

```bash
curl -s -X POST https://parking.example.com/api/v1/vehicles \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"plate":"M-AB 1234","make":"BMW","model":"3 Series","color":"Blau","is_default":true}'
```

### PUT /api/v1/vehicles/:id

Update a vehicle.

### DELETE /api/v1/vehicles/:id

Delete a vehicle and its stored photo.

### POST /api/v1/vehicles/:id/photo

Upload a vehicle photo.

```bash
curl -s -X POST "https://parking.example.com/api/v1/vehicles/VEHICLE_UUID/photo" \
  -H "Authorization: Bearer $TOKEN" \
  -F "photo=@/path/to/photo.jpg"
```

Accepts:
- `multipart/form-data` with `photo` field (JPEG/PNG/GIF/WebP, max 5 MB)
- JSON with `photo_base64` (base64-encoded image, max 8 MB)

Images are validated through PHP GD and resized to max 800px on the longer edge.

### GET /api/v1/vehicles/:id/photo

Serve the stored vehicle photo as `image/jpeg`.

### GET /api/v1/vehicles/city-codes

Return a list of 400+ German Kfz-Unterscheidungszeichen (licence plate city codes) with city names.

```bash
curl -s https://parking.example.com/api/v1/vehicles/city-codes \
  -H "Authorization: Bearer $TOKEN"
```

---

## Team

### GET /api/v1/team

List all active users (for team visibility features).

### GET /api/v1/team/today

Show who is in the office, on homeoffice, or on vacation today.

---

## Waitlist

### GET /api/v1/waitlist

List the user's waitlist entries.

### POST /api/v1/waitlist

Join the waitlist for a lot on a specific date.

Request: `{ "lot_id": "uuid", "requested_date": "2026-03-15" }`

### DELETE /api/v1/waitlist/:id

Leave the waitlist.

---

## User Preferences & Stats

### GET /api/v1/user/preferences

Return user preferences.

### PUT /api/v1/user/preferences

Update preferences.

```bash
curl -s -X PUT https://parking.example.com/api/v1/user/preferences \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "language": "de",
    "theme": "dark",
    "notifications_enabled": true,
    "email_notifications": true,
    "push_notifications": false,
    "show_plate_in_calendar": true,
    "default_lot_id": "LOT_UUID",
    "locale": "de-DE",
    "timezone": "Europe/Berlin"
  }'
```

### GET /api/v1/user/stats

Return statistics: total bookings, bookings this month, homeoffice days, favourite slot.

### GET /api/v1/user/favorites

List the user's favourite slots.

### POST /api/v1/user/favorites

Add a slot to favourites. Request: `{ "slot_id": "uuid" }`

### DELETE /api/v1/user/favorites/:slotId

Remove a slot from favourites.

---

## Notifications

### GET /api/v1/notifications

List the last 50 in-app notifications for the user.

### PUT /api/v1/notifications/:id/read

Mark a notification as read.

### POST /api/v1/notifications/read-all

Mark all notifications as read.

---

## Calendar / iCal

### GET /api/v1/user/calendar.ics

Download the user's bookings as an iCal `.ics` file.
Import into any calendar application (Google Calendar, Apple Calendar, Outlook).

```bash
curl -s "https://parking.example.com/api/v1/user/calendar.ics" \
  -H "Authorization: Bearer $TOKEN" \
  -o my-bookings.ics
```

---

## Webhooks

### GET /api/v1/webhooks

List the user's registered webhooks.

### POST /api/v1/webhooks

Create a webhook.

```bash
curl -s -X POST https://parking.example.com/api/v1/webhooks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://hooks.example.com/parkhub",
    "events": ["booking.created","booking.cancelled"],
    "secret": "optional-hmac-secret"
  }'
```

### PUT /api/v1/webhooks/:id

Update a webhook.

### DELETE /api/v1/webhooks/:id

Delete a webhook.

---

## Push Notifications

### POST /api/v1/push/subscribe

Register a Web Push subscription.

### DELETE /api/v1/push/unsubscribe

Remove all push subscriptions for the authenticated user.

---

## Admin Endpoints

All `/admin/*` endpoints require `role=admin` or `role=superadmin`.

### GET /api/v1/admin/stats

Dashboard statistics: total users, lots, slots, bookings, active bookings, occupancy %, homeoffice count today.

```bash
curl -s https://parking.example.com/api/v1/admin/stats \
  -H "Authorization: Bearer $TOKEN"
```

### GET /api/v1/admin/heatmap

Booking heatmap by day of week and hour. Query: `?days=30`

### GET /api/v1/admin/reports

Booking reports for a period. Query: `?days=30`

### GET /api/v1/admin/dashboard/charts

Chart data: booking trend + current occupancy. Query: `?days=7`

### GET /api/v1/admin/audit-log

Paginated audit log. Supports: `?action=login_failed`, `?search=username`, `?per_page=50`

### Announcements

```bash
# Create announcement
curl -s -X POST https://parking.example.com/api/v1/admin/announcements \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Maintenance","message":"Lot B closed Friday.","severity":"warning"}'
```

`severity`: `info`, `warning`, `error`, `success`

- `GET /api/v1/admin/announcements` — list all
- `POST /api/v1/admin/announcements` — create
- `PUT /api/v1/admin/announcements/:id` — update
- `DELETE /api/v1/admin/announcements/:id` — delete

### User Management

- `GET /api/v1/admin/users` — list all users
- `PUT /api/v1/admin/users/:id` — update (name, email, role, is_active, department, password)
- `DELETE /api/v1/admin/users/:id` — delete user (cannot delete your own account)

### Bulk User Import

```bash
curl -s -X POST https://parking.example.com/api/v1/admin/users/import \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "users": [
      {"username":"max.mueller","email":"max@example.com","name":"Max Müller","role":"user","department":"IT","password":"initial-pass"}
    ]
  }'
```

Up to 500 users per request. Skips existing usernames and emails.

### CSV Booking Export

```bash
curl -s "https://parking.example.com/api/v1/admin/bookings/export" \
  -H "Authorization: Bearer $TOKEN" \
  -o bookings.csv
```

### Settings

- `GET /api/v1/admin/settings` — return all settings
- `PUT /api/v1/admin/settings` — update: `company_name`, `use_case`, `self_registration`, `license_plate_mode`, `max_bookings_per_day`, `allow_guest_bookings`, `auto_release_minutes`, `require_vehicle`, `primary_color`, `secondary_color`

### Email Settings

- `GET /api/v1/admin/settings/email`
- `PUT /api/v1/admin/settings/email` — fields: `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `from_email`, `from_name`, `enabled`

### Auto-Release Settings

- `GET /api/v1/admin/settings/auto-release`
- `PUT /api/v1/admin/settings/auto-release` — body: `{ "enabled": true, "timeout_minutes": 30 }`

### Webhook Settings (Admin-wide)

- `GET /api/v1/admin/settings/webhooks`
- `PUT /api/v1/admin/settings/webhooks`

### Branding

- `GET /api/v1/admin/branding`
- `PUT /api/v1/admin/branding` — fields: `company_name`, `primary_color`, `logo_url`
- `POST /api/v1/admin/branding/logo` — upload logo (JPEG/PNG, max 2 MB, multipart field: `logo`)

### Privacy / GDPR Settings

- `GET /api/v1/admin/privacy`
- `PUT /api/v1/admin/privacy` — fields: `policy_text`, `data_retention_days`, `gdpr_enabled`

### Impressum (DDG §5)

- `GET /api/v1/admin/impressum` — return current Impressum for editing
- `PUT /api/v1/admin/impressum` — update: `provider_name`, `provider_legal_form`, `street`, `zip_city`, `country`, `email`, `phone`, `register_court`, `register_number`, `vat_id`, `responsible_person`, `custom_text`

### Slot and Lot Management (Admin Variants)

- `PATCH /api/v1/admin/slots/:id` — update slot number, status, zone, department
- `DELETE /api/v1/admin/lots/:id` — delete lot and all slots

### Database Reset

```bash
curl -s -X POST https://parking.example.com/api/v1/admin/reset \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"confirm":"RESET"}'
```

Deletes all user data (bookings, absences, vehicles) except the calling admin's account.
Requires `{ "confirm": "RESET" }` to prevent accidental invocation.

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

```bash
curl -s https://parking.example.com/api/v1/legal/impressum
```

---

## System & Health

### GET /api/v1/health/live

Liveness probe — returns HTTP 200 if the PHP process is running.

```bash
curl https://parking.example.com/api/v1/health/live
# {"status":"ok"}
```

### GET /api/v1/health/ready

Readiness probe — returns HTTP 200 if the database is reachable.

### GET /api/v1/system/version

Return the current application version.

### GET /api/v1/system/maintenance

Return maintenance mode status.
