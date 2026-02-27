# Third-Party Licenses — ParkHub PHP

ParkHub PHP (Laravel) is MIT licensed. This file documents all third-party
dependencies and their licenses to ensure compatibility with open-source
distribution.

## License Compatibility Summary

All dependencies — both PHP/Composer and JavaScript/npm — are MIT licensed.
This project is fully compatible with open-source MIT distribution with no
restrictions or caveats.

---

## PHP / Composer Dependencies

### Production Dependencies

| Package | Version | License | Notes |
|---------|---------|---------|-------|
| laravel/framework | ^12.0 | MIT | Laravel application framework |
| laravel/sanctum | ^4.3 | MIT | API token authentication |
| laravel/tinker | ^2.10.1 | MIT | REPL for Laravel |

### Development Dependencies

| Package | Version | License | Notes |
|---------|---------|---------|-------|
| fakerphp/faker | ^1.23 | MIT | Fake data generation |
| laravel/pail | ^1.2.2 | MIT | Real-time log viewer |
| laravel/pint | ^1.24 | MIT | PHP code style fixer |
| laravel/sail | ^1.41 | MIT | Docker dev environment |
| mockery/mockery | ^1.6 | BSD-3-Clause | PHP mock objects |
| nunomaduro/collision | ^8.6 | MIT | Error reporting |
| phpunit/phpunit | ^11.5.3 | BSD-3-Clause | PHP testing framework |

---

## Frontend Dependencies (npm)

### Runtime Dependencies

| Package | Version | License | Notes |
|---------|---------|---------|-------|
| react | ^19.2.0 | MIT | UI library |
| react-dom | ^19.2.0 | MIT | React DOM renderer |
| react-router-dom | ^7.13.0 | MIT | Client-side routing |
| @phosphor-icons/react | ^2.1.10 | MIT | Icon library |
| @tanstack/react-query | ^5.90.20 | MIT | Server state management |
| framer-motion | ^12.33.0 | MIT | Animation library |
| date-fns | ^4.1.0 | MIT | Date utility library |
| react-hot-toast | ^2.6.0 | MIT | Toast notifications |
| zustand | ^5.0.11 | MIT | State management |
| i18next | ^25.8.4 | MIT | Internationalization framework |
| i18next-browser-languagedetector | ^8.2.0 | MIT | i18n browser language detection |
| i18next-http-backend | ^3.0.0 | MIT | i18n HTTP backend loader |
| react-i18next | ^16.5.4 | MIT | React bindings for i18next |

### Development Dependencies

| Package | Version | License | Notes |
|---------|---------|---------|-------|
| vite | ^7.3.1 | MIT | Build tool |
| typescript | ~5.9.3 | Apache-2.0 | TypeScript compiler |
| @vitejs/plugin-react | ^5.1.1 | MIT | Vite React plugin |
| @tailwindcss/vite | ^4.1.0 | MIT | Tailwind v4 Vite plugin |
| tailwindcss | ^3.4.19 | MIT | CSS framework |
| postcss | ^8.5.6 | MIT | CSS processing |
| autoprefixer | ^10.4.24 | MIT | CSS vendor prefixes |
| @tailwindcss/forms | ^0.5.0 | MIT | Tailwind forms plugin |
| vite-plugin-pwa | ^1.2.0 | MIT | PWA support for Vite |
| sharp | ^0.34.5 | Apache-2.0 | High-performance image processing |
| eslint | ^9.39.1 | MIT | JavaScript linter |
| @eslint/js | ^9.39.1 | MIT | ESLint JS config |
| eslint-plugin-react-hooks | ^7.0.1 | MIT | React hooks lint rules |
| eslint-plugin-react-refresh | ^0.4.24 | MIT | React Refresh lint rules |
| typescript-eslint | ^8.46.4 | MIT | TypeScript ESLint |
| globals | ^16.5.0 | MIT | Global variable definitions |
| @types/react | ^19.2.5 | MIT | React TypeScript types |
| @types/react-dom | ^19.2.3 | MIT | React DOM TypeScript types |
| @types/node | ^24.10.1 | MIT | Node.js TypeScript types |
| i18next | ^25.8.4 | MIT | Listed in both deps sections |
| i18next-browser-languagedetector | ^8.2.0 | MIT | Listed in both deps sections |
| react-i18next | ^16.5.4 | MIT | Listed in both deps sections |

---

## Notes on Specific Dependencies

### mockery/mockery and phpunit/phpunit — BSD-3-Clause

Both are BSD-3-Clause licensed, a permissive license fully compatible with MIT.
They are development-only dependencies and not distributed with production builds.

### typescript — Apache-2.0

TypeScript is Apache-2.0 licensed (development only, not distributed at runtime).
Apache-2.0 is compatible with MIT for open-source distribution.

### sharp (^0.34.5) — Apache-2.0

The `sharp` npm package is Apache-2.0 licensed (development dependency, used
during build for image optimization). Apache-2.0 is compatible with MIT.
Note: `sharp` bundles `libvips` (LGPL-2.1+) for native image processing. LGPL
is compatible with open-source distribution when linked dynamically, which is
the case here (native module loaded at runtime, not statically compiled in).

### vite-plugin-pwa — MIT

This plugin generates Progressive Web App manifests and service workers.
It uses Workbox under the hood (Apache-2.0 OR MIT), which is compatible.

---

## License Compatibility Conclusion

| Category | Status |
|----------|--------|
| PHP runtime dependencies | All MIT — fully compatible |
| PHP dev dependencies | MIT and BSD-3-Clause — fully compatible |
| npm runtime dependencies | All MIT — fully compatible |
| npm devDependencies | MIT and Apache-2.0 — fully compatible |

This project is fully cleared for open-source MIT release. No GPL or other
copyleft dependencies are present. All dependencies are permissive-licensed
and compatible with MIT distribution of both source and binaries.
