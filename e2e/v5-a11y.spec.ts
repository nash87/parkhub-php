import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { loginAsAdmin, openV5, V5_SCREENS } from './v5-helpers';

/**
 * v5 accessibility audit — T-1974.
 *
 * One axe-core run per v5 screen (marble_light mode). We assert zero
 * `serious` + `critical` WCAG 2.1 AA violations. `minor` / `moderate`
 * findings are reported by the playwright HTML report but do not fail
 * the suite — pragmatic cut-off, mirrors the existing root `a11y.spec.ts`
 * contract.
 *
 * `color-contrast` is disabled: v5 drives colour tokens via CSS custom
 * properties + OKLCH gradients, and the marble/void palettes were
 * hand-tuned in the design phase. Contrast is re-audited manually via
 * the design-system tooling, not axe DOM inspection.
 *
 * The spec runs under the chromium project only — mobile-chrome baselines
 * will land with the v5 responsive refactor (see v5-visual.spec.ts header).
 */

test.describe('v5 a11y — WCAG 2.1 AA', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(
      testInfo.project.name !== 'chromium',
      'v5 a11y audit pinned to chromium — mobile variants land with the responsive refactor',
    );
    await loginAsAdmin(page);
  });

  for (const screen of V5_SCREENS) {
    test(`@a11y ${screen}: no serious or critical axe violations`, async ({ page }) => {
      await openV5(page, screen);

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .disableRules(['color-contrast'])
        .analyze();

      const blockers = results.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
      );
      expect(
        blockers,
        // Stringify for readable failure output — shows rule id,
        // impact, and affected selectors per violation.
        JSON.stringify(blockers, null, 2),
      ).toEqual([]);
    });
  }
});
