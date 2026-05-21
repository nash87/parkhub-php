# Release Checklist

Use this before tagging a ParkHub release from this repo.

## Product truth

- README, `docs/API.md`, and `docs/FEATURES.md` agree on the shipped contract.
- `docs/parity-governance.md` still matches how the release is being cut.
- `docs/openapi-parity.md` reflects the current PHP vs Rust state.
- Release tag, `VERSION`, `parkhub-web/package.json`, and any release-facing
  version endpoints still agree.

## Contract and parity

- Regenerate and commit the local OpenAPI snapshot when the contract changed.
- Run `scripts/tests/test-legal-openapi-contract.sh` after changes to legal,
  compliance, module, plugin, export, erasure, or privacy surfaces.
- Review any remaining runtime-sensitive gaps and make sure they are documented.
- Do not silently introduce new shared-frontend branching requirements.

## Legal readiness

- Start from `docs/legal-readiness.md`; it is the operator-facing hub for
  legal-readiness evidence, review boundaries, and release checks.
- Complete or update `docs/deployment-readiness-record.md` for the target
  deployment before production use, business use, or customer-facing evaluation.
- Review `docs/legal-readiness-parity.md` for Rust/PHP legal-readiness parity
  when a change affects shared legal, privacy, module, plugin, or release policy.
- Run `scripts/tests/test-legal-readiness-wording.sh`; public docs must describe
  deployment-dependent readiness and obligations, not absolute legal compliance.
- Confirm the operator checklist in `docs/GDPR.md` and `docs/COMPLIANCE.md`
  reflects the enabled modules, integrations, processors, retention settings,
  and jurisdictions.
- Confirm the evidence map in `docs/legal-readiness.md` still points to the
  current templates, API surfaces, and release checks.
- Confirm privacy notice, Impressum, AVV/DPA, VVT, cookie/TTDSG, BFSG/EAA, and
  AI Act transparency templates are still starting points, not legal advice.
- Confirm any security-sensitive or legally sensitive module/plugin change is
  audit-logged and documented with a rollback path before release.
- Treat the Nido/fop legal catalog service (current CLI entrypoint:
  `fop legal catalog --json`; `nido legal` is not exposed by the installed Nido
  CLI yet) as reference-only, not legal advice: attorney review, citation
  verification, human signoff, deployment-specific configuration review, and
  final legal judgment remain required.
- Capture the current legal catalog `source_revision`, `generated_at`,
  `requires_attorney_review`, `requires_human_signoff`, `execution_allowed`, and
  `safety_boundary` values in the deployment readiness record before release.

## Quality bar

- Required CI is green.
- `composer setup` still bootstraps both root and `parkhub-web` dependencies
  cleanly enough for the root `npm run build` path to work from a fresh clone.
- Release workflow uses the same core quality bar described in repo docs.
- Install/download instructions match the actual published artifacts.
- Package/deploy surfaces (`render.yaml`, `fly.toml`, `koyeb.yaml`, Helm
  `appVersion`) still point at the intended release channel.

## Cross-repo discipline

- If this release changes a shared customer-visible feature, verify whether
  `parkhub-rust` needs a matching change.
- If parity is not yet closed, record the gap explicitly in release notes.
- GitHub `nash87/parkhub-php` remains the CI/review source of truth. Do not
  base releases on a stale Gitea mirror.
