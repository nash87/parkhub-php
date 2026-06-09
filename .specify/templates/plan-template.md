---
spec_id: "<!-- must match spec-template.md spec_id -->"
title: "<!-- Technical plan: Feature Title -->"
status: draft          # draft | review | approved
created: <!-- YYYY-MM-DD -->
authors: ["@nash87"]
spec: "specs/<!-- spec_id -->/spec.md"
---

# Plan: <!-- Feature Title -->

## Architecture overview

<!-- How does this fit into the existing Laravel + React architecture?
     Describe the data flow end-to-end. A simple ASCII diagram is fine. -->

## Database changes

<!-- List new migrations. For each: table, column(s), type, nullable/default,
     index, and any data migration needed for existing rows. -->

### Migrations

- `YYYY_MM_DD_000000_add_X_to_Y_table.php`
  - `Y.column` — `type` nullable/not-null default `value`

## API changes

### New endpoints

```
METHOD /api/v1/<resource>
  Auth: bearer | api-key | public
  Body: { ... }
  Response: { ... }
  Errors: 401, 403, 422, 404
```

### Modified endpoints

<!-- List endpoints whose request/response shape changes. Note parity
     impact: does parkhub-rust need a matching change? -->

### OpenAPI snapshot

Run `composer openapi:dump` after implementing. Commit the updated
`docs/openapi/php.json`. The `make drift` gate will fail without it.

## Implementation steps

<!-- Ordered, dependency-aware. Each step should be achievable in one
     focused commit or PR. -->

1. Migration: `php artisan make:migration add_X_to_Y_table`
2. Model: add fillable, casts, relations
3. Policy: `php artisan make:policy XPolicy`
4. Controller(s): `php artisan make:controller Api/XController`
5. Route: add to `routes/api_v1.php` with middleware
6. Module toggle: if applicable, wrap in `MODULE_X` check
7. Service: extract business logic from controller
8. Frontend: `parkhub-web/src/` changes
9. Tests: feature + unit (see test plan below)
10. Docs: update `docs/API.md`, OpenAPI snapshot, CHANGELOG

## Test plan

<!-- For each story in the spec, map to a test class and the scenarios covered. -->

| Story | Test class | Scenarios |
|-------|-----------|-----------|
| S1 | `tests/Feature/XTest.php` | happy path, 401, 403, 422, edge case |

## Parity checklist

- [ ] `docs/openapi-parity.md` reviewed — no parity gap introduced
- [ ] If parity gap: filed issue in `nash87/parkhub-rust` (link: )
- [ ] `docs/parity-governance.md` updated if ownership changes

## Rollback plan

<!-- How is this change reverted if it ships a bug to production?
     Consider: migration rollback, feature flag, config toggle. -->

## Open questions

<!-- Technical unknowns that must resolve before implementation. -->
