import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const routes = ['/', '/login', '/register', '/setup'];

for (const route of routes) {
  test(`a11y: ${route} has no critical violations`, async ({ page }) => {
    await page.goto(route);
    // Let the SPA settle: `networkidle` waits for lazy chunks + any session
    // probe fetches, and the URL-stability poll gives the React Router a
    // chance to commit its final route (some entry paths like / redirect
    // to /login client-side — running axe during that redirect throws
    // "Execution context was destroyed, most likely because of a navigation").
    await page
      .waitForLoadState('networkidle', { timeout: 10_000 })
      .catch(() => { /* best-effort */ });
    let lastUrl = page.url();
    for (let i = 0; i < 20; i++) {
      await page.waitForTimeout(100);
      const cur = page.url();
      if (cur === lastUrl) break;
      lastUrl = cur;
    }
    await page
      .waitForFunction(
        () => {
          const txt = document.body?.textContent ?? '';
          return txt.length > 50 && !/^\s*Loading ParkHub/i.test(txt);
        },
        null,
        { timeout: 5_000 },
      )
      .catch(() => {
        /* fall through — axe runs on whatever rendered */
      });

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .disableRules(['color-contrast']) // managed via CSS custom properties
      .analyze();

    const serious = results.violations.filter(
      (v) => v.impact === 'serious' || v.impact === 'critical'
    );
    expect(serious).toEqual([]);
  });
}
