import { test, expect } from '@playwright/test';
import { loginAsAdmin, openV5, V5_MODES, V5_SCREENS } from './v5-helpers';

/**
 * v5 visual regression suite — T-1948.
 *
 * Captures one full-page PNG per `screen × mode`. Only runs under the
 * `chromium` project — the `mobile-chrome` project is deliberately
 * skipped until v5 ships a responsive breakpoint (see
 * docs/v5-test-coverage-plan.md). That keeps baselines
 * single-variant / linux-x64-pinned and avoids squashed-layout false
 * positives on Pixel 5 viewport.
 *
 * Tolerance:
 *   - `maxDiffPixelRatio: 0.02` — absorbs anti-aliasing jitter.
 *   - `fullPage: true` — scroll-length bodies (Dashboard, Buchungen,
 *     Analytics) need their below-the-fold surface covered.
 *   - `animations: 'disabled'` — Playwright pins transitions at frame 0.
 *   - helper adds a stylesheet that nukes animations / hides toasts /
 *     live counters / canvases / leaflet tiles, same as the existing
 *     root `e2e/visual.spec.ts`.
 */

// Screens whose React tree throws during hydration on github/main.
// fixme'd here so snapshot capture stays meaningful — without this,
// Playwright records a blank PNG as the baseline and every future
// real render flips it to "diff detected".
const KNOWN_BROKEN = new Set<string>(['nutzer']);

test.describe('v5 visual regression', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(
      testInfo.project.name !== 'chromium',
      'v5 visual baselines pinned to chromium — mobile-chrome lands with the responsive refactor',
    );
    await loginAsAdmin(page);
  });

  for (const screen of V5_SCREENS) {
    for (const mode of V5_MODES) {
      test(`${screen} — ${mode}`, async ({ page }) => {
        test.fixme(
          KNOWN_BROKEN.has(screen),
          `${screen} is broken on github/main — baseline would be blank, skip capture`,
        );
        await openV5(page, screen, mode);

        await expect(page).toHaveScreenshot(
          `v5-${screen}-${mode}.png`,
          {
            fullPage: true,
            maxDiffPixelRatio: 0.02,
            animations: 'disabled',
          },
        );
      });
    }
  }
});
