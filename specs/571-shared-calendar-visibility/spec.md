# 571 — Shared calendar visibility

Issue: [#571](https://github.com/nash87/parkhub-php/issues/571)

## Problem

The calendar shows only the caller's own bookings, so a user has no way to
tell when a slot is actually free. The only way to discover availability is
to attempt a booking and be rejected. The reporter asked to "see ALL the
bookings so that they know when to book the time slot".

## Why this is not simply "show everything"

ParkHub is deployed in workplaces, ships a GDPR guide and a works council
fairness report. There are two different pieces of information here and they
carry very different weight:

| Information | Nature | Needed to solve #571? |
|---|---|---|
| *This slot is taken 09:00–17:00 on Tuesday* | scheduling data | **yes** |
| *Dana Meyer parked on Tuesday, and every Tuesday* | behavioural data about an employee | no |

The second is an attendance record by proxy. In a German workplace it is
works-council relevant, and it is not required to answer "when can I book?".
The feature therefore delivers **occupancy** and treats **identity** as a
separately governed disclosure.

## Design

### Scope is opt-in per request

`GET /api/v1/calendar/events?scope=all` widens the result to every user's
bookings. Omitted or any other value keeps the historical behaviour
(caller's own bookings only), so existing clients are unaffected.

### Identity is governed by an existing control

The `booking_visibility` setting already governed the team view. It is
reused rather than duplicated, and its masking logic now lives in
`App\Services\BookingVisibility` so the two surfaces cannot drift:

| Mode | Owner label |
|---|---|
| `full` (default) | `Dana Meyer` |
| `firstName` | `Dana` |
| `initials` | `D.M` |
| `occupied` | `Occupied` (calendar) / `User` (team view) |

That setting was readable in code but absent from the settings defaults,
the write allowlist and the request validation — no operator could see or
change it. It is now a first-class admin setting, which is a precondition
for this feature rather than a side quest.

### What another user's event never contains

`vehicle_plate` and `notes` are excluded for any booking the caller does not
own. Cancelled bookings are excluded from the shared view entirely.

### Rescheduling stays owner-only

Calendar drag-to-reschedule is disabled for entries the caller does not own,
both in the drag handler and via the `draggable` attribute. Visibility is
not authority.

## Response shape

Each event gains:

| Field | Meaning |
|---|---|
| `mine` | `true` when the caller owns the booking |
| `owner` | `null` for own bookings; masked label otherwise |
| `slot_number` | the slot, so occupancy is readable without the title |

## Defects fixed in the same endpoint

Both were found while implementing this and are covered by tests:

1. **The SPA's date range was ignored.** The client sends `?start=&end=`
   while the controller read only `from`/`to`, so navigating to any other
   month silently returned the current month. Both spellings are now
   accepted. Each side had tests; neither side tested the contract between
   them.
2. **Bookings straddling the window disappeared.** The filter required
   containment (`start >= from AND end <= to`), so a booking beginning
   before the window or ending after it was dropped even though it occupies
   days in view. It is now an overlap test.

## Out of scope

- Per-lot filtering of the shared view. Worth doing if a deployment has
  enough lots for the month view to get noisy; the reporter raised this
  ("if there are more, like 1000 slots I guess that could be chaotic").
- Surfacing `booking_visibility` in the admin settings UI. The API now
  accepts it; the settings screen still needs a control.
