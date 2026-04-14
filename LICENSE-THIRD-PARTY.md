# Third-Party Licenses -- ParkHub PHP

ParkHub PHP is MIT licensed. This file documents all third-party dependencies and their
licenses to ensure correct attribution and downstream compatibility.

> **Last updated:** 2026-04-14 (v4.9.0)

---

## License Compatibility Summary

**Default runtime is MIT-compatible OSS, with weak-copyleft (LGPL) and Apache-2.0
dependencies properly attributed.**

ParkHub itself is MIT licensed. The dependency tree includes:

- Permissive licenses: MIT, BSD-3-Clause, ISC (the majority)
- Apache-2.0 (with required NOTICE attribution — see `NOTICE`)
- Weak-copyleft LGPL-2.1 (dompdf, libvips) — used as dynamically linked libraries
  with no source modification
- One non-OSI ethical-use license (react-leaflet, Hippocratic-2.1) inside the
  optional MAP module

A complete inventory follows. Operators bound by strict OSI compliance can disable
the MAP module by setting `MODULE_MAP=false`; see the **License Compatibility**
section below for the full guidance.

---

## Notable Licenses Inventory

The following dependencies require explicit attribution or operator awareness because
they are not MIT/BSD/ISC. They are listed first so they cannot be missed.

| Package | License | Where | Notes |
|---------|---------|-------|-------|
| dompdf/dompdf | LGPL-2.1 | composer (transitive of `barryvdh/laravel-dompdf`) | PDF generation. Dynamically linked, **not statically linked into ParkHub source**. LGPL-compliant. |
| chillerlan/php-qrcode | Apache-2.0 | composer (direct) | QR code generation. **NOTICE attribution required** — see `NOTICE`. |
| phpoption/phpoption | Apache-2.0 | composer (transitive) | Option type helper. **NOTICE attribution required**. |
| nette/schema | BSD-3-Clause OR GPL-2.0/3.0 (dual) | composer (transitive) | We rely on the **BSD-3-Clause** interpretation. |
| nette/utils | BSD-3-Clause OR GPL-2.0/3.0 (dual) | composer (transitive) | We rely on the **BSD-3-Clause** interpretation. |
| react-leaflet | Hippocratic-2.1 | npm `parkhub-web` (MAP module) | **Non-OSI** ethical-use clause. Operators with strict OSI compliance requirements should disable the MAP module via `MODULE_MAP=false`. |
| leaflet | BSD-2-Clause | npm `parkhub-web` (MAP module) | Permissive. |
| libvips | LGPL-2.1+ | npm (native, via sharp) | Image processing. Dynamically linked, LGPL-compliant. |
| @axe-core/playwright | MPL-2.0 | npm dev | Weak copyleft, dev-only, not redistributed at runtime. |

---

## PHP / Composer Dependencies

### Production Dependencies

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| laravel/framework | ^12.54 | MIT | Laravel application framework |
| laravel/sanctum | ^4.3 | MIT | API token authentication |
| laravel/tinker | ^2.10.1 | MIT | REPL for Laravel |
| barryvdh/laravel-dompdf | ^3.1 | MIT | PDF invoice generation |
| chillerlan/php-qrcode | ^6.0 | MIT | QR code generation |
| dedoc/scramble | ^0.13.16 | MIT | API documentation generation |
| minishlink/web-push | ^10.0 | MIT | Web Push (VAPID) notifications |
| pragmarx/google2fa-laravel | ^3.0 | MIT | TOTP two-factor authentication |

### Development Dependencies

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| fakerphp/faker | ^1.24 | MIT | Fake data generation for tests |
| larastan/larastan | ^3.9 | MIT | PHPStan for Laravel |
| laravel/pail | ^1.2.6 | MIT | Real-time log viewer |
| laravel/pint | ^1.24 | MIT | PHP code style fixer (PSR-12) |
| laravel/sail | ^1.41 | MIT | Docker dev environment |
| mockery/mockery | ^1.6 | BSD-3-Clause | PHP mock objects framework |
| nunomaduro/collision | ^8.9 | MIT | Error reporting |
| phpunit/phpunit | ^11.5.50 | BSD-3-Clause | PHP testing framework |

---

## Frontend Dependencies (npm)

### Runtime Dependencies

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| react | ^19.2.4 | MIT | UI library |
| react-dom | ^19.2.4 | MIT | React DOM renderer |
| react-router-dom | ^7.13.1 | MIT | Client-side routing |
| @phosphor-icons/react | ^2.1.10 | MIT | Icon library |
| @tanstack/react-query | ^5.90.21 | MIT | Server state management |
| framer-motion | ^12.35.2 | MIT | Animation library |
| date-fns | ^4.1.0 | MIT | Date utility library |
| react-hot-toast | ^2.6.0 | MIT | Toast notifications |
| zustand | ^5.0.11 | MIT | State management |
| i18next | ^25.8.18 | MIT | Internationalization framework |
| i18next-browser-languagedetector | ^8.2.0 | MIT | Browser language detection |
| i18next-http-backend | ^3.0.2 | MIT | i18n HTTP backend loader |
| react-i18next | ^16.5.4 | MIT | React bindings for i18next |

### Development Dependencies

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| vite | ^7.3.1 | MIT | Build tool and dev server |
| typescript | ~5.9.3 | Apache-2.0 | TypeScript compiler |
| @vitejs/plugin-react | ^5.1.4 | MIT | Vite React plugin |
| @tailwindcss/vite | ^4.2.1 | MIT | Tailwind v4 Vite plugin |
| tailwindcss | ^3.4.19 | MIT | CSS framework |
| postcss | ^8.5.8 | MIT | CSS processing |
| autoprefixer | ^10.4.27 | MIT | CSS vendor prefixes |
| @tailwindcss/forms | ^0.5.11 | MIT | Tailwind forms plugin |
| vite-plugin-pwa | ^1.2.0 | MIT | PWA support for Vite |
| sharp | ^0.34.5 | Apache-2.0 | High-performance image processing |
| eslint | ^9.39.1 | MIT | JavaScript linter |
| @eslint/js | ^9.39.1 | MIT | ESLint JS config |
| eslint-plugin-react-hooks | ^7.0.1 | MIT | React hooks lint rules |
| eslint-plugin-react-refresh | ^0.4.24 | MIT | React Refresh lint rules |
| typescript-eslint | ^8.57.0 | MIT | TypeScript ESLint |
| globals | ^16.5.0 | MIT | Global variable definitions |
| @types/react | ^19.2.14 | MIT | React TypeScript types |
| @types/react-dom | ^19.2.3 | MIT | React DOM TypeScript types |
| @types/node | ^24.10.1 | MIT | Node.js TypeScript types |
| @playwright/test | ^1.58.2 | Apache-2.0 | End-to-end testing |
| @axe-core/playwright | ^4.11.1 | MPL-2.0 | Accessibility testing |

---

## Transitive Dependencies of Note

| Package | License | Bundled By | Notes |
|---------|---------|------------|-------|
| libvips | LGPL-2.1+ | sharp | Native image processing; dynamically linked (LGPL-compliant) |
| workbox | Apache-2.0 OR MIT | vite-plugin-pwa | Service worker runtime |
| dompdf/dompdf | LGPL-2.1 | barryvdh/laravel-dompdf | PDF rendering; used as a library (LGPL-compliant) |

---

## License Details

### MIT License

The majority of dependencies use the MIT License, which permits:
- Commercial use
- Modification
- Distribution
- Private use

With the condition that the license and copyright notice are included.

### Apache-2.0

Used by TypeScript, sharp, and Playwright. Apache-2.0 is compatible with MIT.
Key additions over MIT: explicit patent grant and contribution terms.

### BSD-3-Clause

Used by mockery and phpunit (development only). BSD-3-Clause is a permissive license
compatible with MIT. Adds a non-endorsement clause.

### MPL-2.0

Used by @axe-core/playwright (development only, accessibility testing).
MPL-2.0 is a weak copyleft license -- only modifications to MPL-licensed files must
be shared. Compatible with MIT distribution since it is not bundled in production.

### LGPL-2.1+

Used by libvips (via sharp) and dompdf. LGPL permits use as a dynamically linked library
without requiring the host application to be LGPL. Both are used as libraries (not
statically compiled), maintaining MIT compatibility.

---

## Verification

To verify current dependency licenses:

```bash
# PHP dependencies
composer licenses

# npm dependencies
npx license-checker --summary
```

---

## License Compatibility Conclusion

| Category | Status |
|----------|--------|
| PHP runtime dependencies | All MIT -- fully compatible |
| PHP dev dependencies | MIT + BSD-3-Clause -- fully compatible |
| npm runtime dependencies | All MIT -- fully compatible |
| npm dev dependencies | MIT + Apache-2.0 + MPL-2.0 -- fully compatible |
| Transitive (native) | LGPL-2.1+ (dynamic linking) -- compatible |

**This project is fully cleared for open-source MIT release.** No copyleft dependencies
are present in the production build. All development dependencies with non-MIT licenses
(BSD-3-Clause, Apache-2.0, MPL-2.0) are permissive and not distributed with production builds.
