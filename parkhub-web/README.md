# parkhub-web (PHP/Laravel runtime)

Frontend workspace for the **PHP edition** of ParkHub. Astro 6 ships a static SPA shell;
React 19 + Tailwind CSS 4 render the application; the production build is published into
Laravel's `public/` directory and served by the Laravel app behind the Sanctum-authenticated
`/api/v1/*` surface.

> Top-level project docs (product overview, deployment, API, compliance) live in the
> repository root: [`../README.md`](../README.md), [`../DEVELOPMENT.md`](../DEVELOPMENT.md),
> [`../ARCHITECTURE.md`](../ARCHITECTURE.md), [`../CHANGELOG.md`](../CHANGELOG.md).

## Stack

| Layer            | Technology                                                  |
|------------------|-------------------------------------------------------------|
| Framework        | [Astro](https://astro.build/) 6.1 (`output: 'static'`)      |
| UI runtime       | [React](https://react.dev/) 19.2 + React Compiler (Babel)   |
| Styling          | [Tailwind CSS](https://tailwindcss.com/) 4.2 via Vite plugin |
| Routing          | `react-router-dom` 7                                        |
| Data             | TanStack Query 5 + TanStack Table 8                         |
| Forms            | `react-hook-form` 7 + `zod` 4 + `@hookform/resolvers` 5     |
| Charts           | uPlot 1.6 · Maps: Leaflet 1.9 + `react-leaflet` 5           |
| i18n             | `i18next` 26 + `react-i18next` 17, 10 locales, hot-loaded   |
| Command Palette  | `cmdk` 1.1 (mounted globally, `Cmd+K` / `Ctrl+K`)           |
| Component dev    | Storybook 10 (`@storybook/react-vite`, a11y addon)          |
| Lint / Format    | Biome 2                                                     |
| Node             | `>= 22.12`                                                  |

## Layout — how Laravel serves it

```
parkhub-web/                  # this workspace
├── src/                      # React + Astro source
├── dist/                     # `astro build` output (gitignored)
└── ...
public/                       # Laravel document root
└── (assets copied from parkhub-web/dist/ on `npm run build:php`)
resources/ · routes/web.php   # Laravel Blade entry that boots the React shell
```

`npm run build:php` runs `astro build` and copies `dist/*` into the Laravel `public/`
directory, where Laravel's web routes serve the SPA shell and the Vite manifest. The
React app then talks to `/api/v1/*` (Sanctum cookie auth + Bearer fallback). See
[`../ARCHITECTURE.md`](../ARCHITECTURE.md) for the request flow.

## Scripts

| Script                  | Action                                                          |
|-------------------------|-----------------------------------------------------------------|
| `npm run dev`           | Astro dev server on `http://localhost:4321`                     |
| `npm run build`         | Production build into `dist/`                                   |
| `npm run build:php`     | Build + publish `dist/*` into Laravel's `public/`               |
| `npm run preview`       | Preview the production build locally                            |
| `npm run test`          | Vitest unit + component tests (jsdom, single run)               |
| `npm run test:watch`    | Vitest in watch mode                                            |
| `npm run test:coverage` | Vitest with v8 coverage (40 % statements gate)                  |
| `npm run test:e2e`      | Playwright against `BASE_URL` (defaults to live demo)           |
| `npm run storybook`     | Storybook 10 dev server on `http://localhost:6006`              |
| `npm run test-storybook`| Storybook test-runner (interaction + a11y addon)                |
| `npm run i18n:coverage` | Locale-key coverage report                                      |

## Testing strategy

- **Vitest 4** (`vitest.config.ts`) — jsdom env, Testing Library (`@testing-library/react`,
  `@testing-library/user-event`), v8 coverage with hard thresholds (40 % statements,
  30 % branches, 35 % functions, 40 % lines).
- **Playwright 1.59** (`playwright.config.ts`) — runs against `BASE_URL` (defaults to
  the live demo), `chromium` + `mobile-chrome` (Pixel 5) projects, sequential workers.
- **Storybook 10** + `@storybook/addon-a11y` + `@storybook/test-runner` — component
  development and per-story accessibility checks.
- **Axe-core a11y** — runs in CI on the v5 surfaces; keyboard-only nav verified for the
  full shell + Assistent panel.
- **Lighthouse CI** — `lighthouserc.json` enforces a11y ≥ 95, performance ≥ 75, SEO ≥ 90
  on every PR (see top-level [`../README.md`](../README.md#testing)).

## Related docs

- [`../README.md`](../README.md) — product overview, deployment, screenshots
- [`../DEVELOPMENT.md`](../DEVELOPMENT.md) — local dev loop, `composer ci`, `make ci`
- [`../ARCHITECTURE.md`](../ARCHITECTURE.md) — Laravel + frontend embedding flow
- [`../CHANGELOG.md`](../CHANGELOG.md) — release notes
- [`../docs/openapi/php.json`](../docs/openapi/php.json) — REST contract consumed by this UI
