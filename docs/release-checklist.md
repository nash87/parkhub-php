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
- Review any remaining runtime-sensitive gaps and make sure they are documented.
- Do not silently introduce new shared-frontend branching requirements.

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
- Push order remains `origin` first, then `github`.
