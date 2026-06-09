---
spec_id: "<!-- must match spec.md spec_id -->"
title: "<!-- Tasks: Feature Title -->"
status: draft          # draft | in-progress | done
created: <!-- YYYY-MM-DD -->
plan: "specs/<!-- spec_id -->/plan.md"
---

# Tasks: <!-- Feature Title -->

<!-- Tasks are ordered by dependency. Each task has a single owner and a
     clear acceptance criterion. Use `/speckit.taskstoissues` to open GitHub
     issues from this file. -->

## Phase 1 — Database + model

- [ ] **T1** Write migration `add_X_to_Y_table`
  - AC: `php artisan migrate` runs clean; rollback leaves the schema unchanged
  - Depends on: none

- [ ] **T2** Update `X` model: fillable, casts, relations
  - AC: `X::factory()->create()` passes; `$x->relation` returns the expected type
  - Depends on: T1

## Phase 2 — Backend API

- [ ] **T3** Add `XPolicy` — auth boundaries for the new resource
  - AC: unauthorized roles receive 403; authorized roles pass through
  - Depends on: T2

- [ ] **T4** Implement `Api/XController` with service extraction
  - AC: each endpoint returns the documented status codes; no logic in the controller
  - Depends on: T3

- [ ] **T5** Register routes in `routes/api_v1.php` (+ module toggle if applicable)
  - AC: `php artisan route:list` shows the new endpoints; disabled module returns 404
  - Depends on: T4

- [ ] **T6** Regenerate OpenAPI snapshot (`composer openapi:dump`)
  - AC: `make drift` passes; `docs/openapi/php.json` committed
  - Depends on: T5

## Phase 3 — Frontend

- [ ] **T7** Add API client call in `parkhub-web/src/api/`
  - AC: TypeScript compiles; Vitest test passes for the happy path
  - Depends on: T6

- [ ] **T8** Implement UI component(s) in `parkhub-web/src/`
  - AC: Playwright spec covers the new surface; axe-core finds no violations
  - Depends on: T7

## Phase 4 — Tests and docs

- [ ] **T9** Write feature tests (PHPUnit): 401, 403, 422, happy path, edge cases
  - AC: `php artisan test --filter=X` exits 0; mutation survivors documented
  - Depends on: T5

- [ ] **T10** Update `docs/API.md`, CHANGELOG, and `docs/openapi-parity.md`
  - AC: no broken internal links; parity gap (if any) recorded with a tracking issue
  - Depends on: T6, T9

## Phase 5 — Review and ship

- [ ] **T11** Open PR, fill PR template, link spec in "Spec reference" field
  - AC: `make ci` passes; all required status checks green
  - Depends on: T1–T10

- [ ] **T12** Address review feedback and merge
  - AC: PR merged to `main`; CHANGELOG entry present
  - Depends on: T11
