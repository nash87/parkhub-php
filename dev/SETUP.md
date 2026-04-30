# SOTA-2026 local dev setup (parkhub-php)

Mirror of `parkhub-rust`'s dev kit, adapted for the Laravel 13 + Astro stack.

## Prerequisites (one-time, host-side)

```bash
flatpak-spawn --host /home/linuxbrew/.linuxbrew/bin/brew install mise direnv just lefthook php composer
flatpak-spawn --host bash -lc 'mise activate bash >> ~/.bashrc'
flatpak-spawn --host bash -lc 'direnv hook bash >> ~/.bashrc'
```

## Per-repo bootstrap

```bash
direnv allow .
just bootstrap
```

This runs:

1. `mise install` — pins PHP 8.4, Node 22.12, composer, lefthook, typos, gitleaks, zizmor, osv-scanner, trivy, dprint
2. `lefthook install --force`
3. `composer install`
4. `cd parkhub-web && npm ci`

## Day-to-day

```bash
just              # list all recipes
just dev          # php artisan serve + astro dev (concurrent)
just check        # pint + phpstan + unit tests
just local-ci     # full local CI (matches CI Security workflow)
just fmt          # pint + dprint + biome auto-fix
just security     # composer audit + npm audit + trivy + gitleaks
```

## What's wired

| Tool | Config | Purpose |
|---|---|---|
| `mise` | `.mise.toml` | toolchain pin (php, node, composer); one-command install |
| `direnv` | `.envrc` | auto-activate mise on `cd` |
| `just` | `Justfile` | task runner — single source of truth |
| `dprint` | `dprint.json` | unified formatter for json/md/toml/yaml |
| `typos` | `typos.toml` | spell-check with project allow-list |
| `lefthook` | `lefthook.yml` | git hooks (pre-commit + pre-push gates) |
| `Pint` | (Laravel default) | PHP style — applied via `just fmt` |
| `PHPStan / Larastan` | `phpstan.neon` | static analysis level 8 |

## Differences from parkhub-rust kit

- No `bacon` (Rust live-loop) — use `php artisan pail` for log tailing
- No `cargo-*` tools — composer/PHPStan/Pint cover advisories + style + analysis
- `.gitea/workflows/` are NOT used (parkhub-php is on GitHub like parkhub-rust)

## License

All upstream tools MIT or Apache-2.0. Per `composer.json` license: `MIT`.

## Adopt elsewhere

Same template applies to other Laravel/Astro/Vite repos. Copy these 5 files (`.mise.toml`, `.envrc`, `Justfile`, `dprint.json`, `typos.toml`) + `dev/SETUP.md`, adjust the toolchain pins, then `direnv allow . && just bootstrap`.
