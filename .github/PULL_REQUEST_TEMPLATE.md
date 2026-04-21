## Summary
<!-- Brief description of what this PR does and why -->

## Type of change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation
- [ ] Security fix
- [ ] Dependency update
- [ ] Refactor / code quality

## Testing
- [ ] I tested locally with Docker Compose (`docker compose up -d`)
- [ ] I ran the test suite (`php artisan test`)
- [ ] I ran frontend tests (`cd parkhub-web && npx vitest run`)
- [ ] I verified the change works on SQLite (dev) and MySQL (production)
- [ ] I checked GDPR compliance impact (does this change affect data handling, storage, or erasure?)

## Security checklist
- [ ] No secrets, credentials, or API keys committed
- [ ] New endpoints have appropriate auth middleware and rate limiting
- [ ] User input is validated via `$request->validate()`
- [ ] Database queries use Eloquent/Query Builder (no raw SQL with user input)
- [ ] File uploads validated via GD content check

## Checklist
- [ ] Code follows project style (Laravel conventions, PSR-12)
- [ ] No secrets or credentials committed
- [ ] Documentation updated if needed (docs/, README.md)
- [ ] CHANGELOG.md entry added
- [ ] Database migration added if schema changes are required
- [ ] New API endpoints documented in docs/API.md
- [ ] Shared feature/API changes reviewed against `docs/parity-governance.md`
- [ ] `docs/openapi-parity.md` updated or explicitly confirmed unchanged
