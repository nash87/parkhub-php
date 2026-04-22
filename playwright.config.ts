import { defineConfig, devices } from '@playwright/test';

// Local E2E typically runs against a single-process app server (`php artisan serve`),
// so the default Playwright worker fan-out causes false-red loading-state flakes.
// Keep local runs serial by default and allow explicit override when a faster stack exists.
const localWorkers = process.env.PLAYWRIGHT_WORKERS
  ? Number(process.env.PLAYWRIGHT_WORKERS)
  : 1;
const includeWebkitProject = !!process.env.CI || process.env.PLAYWRIGHT_ENABLE_WEBKIT === '1';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : localWorkers,
  reporter: [
    ['html', { open: 'never' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:8082',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile-chrome', use: { ...devices['Pixel 5'] } },
    ...(includeWebkitProject
      ? [{ name: 'mobile-safari', use: { ...devices['iPhone 14'] } }]
      : []),
  ],
});
