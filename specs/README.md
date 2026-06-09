---
title: "Spec directory"
type: reference
status: active
last_reviewed: 2026-06-09
---

# Specs

This directory contains spec-kit artifacts for non-trivial features in
ParkHub PHP. Each feature lives in its own subdirectory:

```
specs/
  <feature-id>/
    spec.md     — requirements and user stories (no implementation detail)
    plan.md     — technical design, schema, API contract, and implementation steps
    tasks.md    — dependency-ordered task list with acceptance criteria
```

## When to write a spec

Any PR labeled `feature` or `enhancement` that adds a new API endpoint, a new
module, a schema migration, or a non-trivial UI surface requires a spec before
the implementation PR is opened.

Small bug fixes, dependency bumps, documentation changes, and refactors that
do not change behavior do not require a spec.

## Workflow

1. Open a GitHub issue describing the problem.
2. Run `/speckit.specify` (Claude Code) or copy
   `.specify/templates/spec-template.md` to `specs/<feature-id>/spec.md`.
3. Fill in the spec and open a draft PR for review.
4. Once the spec is approved, copy `.specify/templates/plan-template.md` to
   `specs/<feature-id>/plan.md` and flesh out the technical design.
5. Copy `.specify/templates/tasks-template.md` to
   `specs/<feature-id>/tasks.md` and track implementation progress.
6. Link `specs/<feature-id>/spec.md` in the "Spec reference" field of your
   implementation PR and check the `spec_written` checkbox in the feature
   request issue template.

## Governing principles

The project constitution lives at `.specify/memory/constitution.md`. It
captures non-negotiable boundaries for every contribution: API parity,
security requirements, GDPR constraints, and code quality thresholds.

## Legacy planning documents

Older planning artifacts live in `docs/plans/`. Those files remain valid and
are not migrated — new features use the `specs/` hierarchy going forward.
