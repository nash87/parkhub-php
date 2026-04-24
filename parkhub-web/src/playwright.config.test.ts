import { describe, expect, it } from 'vitest';
import playwrightConfig from '../playwright.config';

/**
 * We intentionally register both a desktop chromium project and a
 * Pixel-5 `mobile-chrome` project so v5 specs can opt into mobile
 * viewports on CI without spinning up a dedicated job.
 */
describe('playwright.config', () => {
  const projects = playwrightConfig.projects ?? [];

  it('registers the chromium project', () => {
    expect(projects.find((p) => p.name === 'chromium')).toBeDefined();
  });

  it('registers the mobile-chrome project', () => {
    expect(projects.find((p) => p.name === 'mobile-chrome')).toBeDefined();
  });
});
