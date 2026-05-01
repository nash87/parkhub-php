import { test, expect } from '@playwright/test';
import { loginViaUi, PUBLIC_ROUTES, PROTECTED_ROUTES, ADMIN_ROUTES } from './helpers';

test.describe('Pages — Public Routes', () => {
  for (const route of PUBLIC_ROUTES) {
    test(`${route} loads without errors`, async ({ page }) => {
      const res = await page.goto(route);
      expect(res?.status()).toBeLessThan(400);
      // Page should render some content
      await expect(page.locator('body')).not.toBeEmpty();
    });
  }

  test('/login shows a login form', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('button', { name: /sign in|log in|login/i })).toBeVisible();
  });

  test('/register shows a registration form', async ({ page }) => {
    await page.goto('/register');
    await expect(page.getByRole('button', { name: /sign up|register|create/i })).toBeVisible();
  });

  test('/forgot-password shows password reset form', async ({ page }) => {
    await page.goto('/forgot-password');
    await expect(page.getByLabel(/email/i)).toBeVisible();
  });
});

test.describe('Pages — Protected Routes (after login)', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUi(page);
  });

  for (const route of PROTECTED_ROUTES) {
    test(`${route} loads after auth`, async ({ page }) => {
      const res = await page.goto(route);
      expect(res?.status()).toBeLessThan(400);
      await expect(page.locator('body')).not.toBeEmpty();
    });
  }

  test('dashboard shows stats or content', async ({ page }) => {
    await page.goto('/');
    // Dashboard should have some visible element
    await expect(page.locator('main, [data-testid], h1, h2')).not.toHaveCount(0);
  });

  test('/profile page has settings section', async ({ page }) => {
    await page.goto('/profile');
    await expect(page.locator('body')).toContainText(/profile|settings|theme|account/i);
  });
});

test.describe('Pages — Admin Routes (after admin login)', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaUi(page);
  });

  for (const route of ADMIN_ROUTES) {
    test(`${route} loads for admin`, async ({ page }) => {
      const res = await page.goto(route);
      expect(res?.status()).toBeLessThan(400);
      await expect(page.locator('body')).not.toBeEmpty();
      await expect(page.locator('body')).not.toContainText(/returned an object instead of string/i);
    });
  }

  test('admin modules, chargers, and updates settle without broken UI chrome', async ({ page }) => {
    for (const route of ['/admin/modules', '/admin/chargers', '/admin/updates']) {
      await page.goto(route);
      await expect(page.locator('body')).not.toContainText(/returned an object instead of string/i);
      await expect(page.locator('body')).not.toContainText(/key '[^']+' returned/i);
      await expect(page.locator('h1, h2').first()).toBeVisible();
    }

    await page.goto('/admin/chargers');
    await expect(page.getByText(/Total Chargers/i).first()).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/Could not load charger statistics/i);

    await page.goto('/admin/updates');
    await expect(page.getByTestId('current-version')).not.toHaveText('—');
  });
});

test.describe('Pages — Redirects', () => {
  test('/ without auth redirects to /login or /welcome', async ({ page }) => {
    await page.goto('/');
    // AuthProvider shows LoadingSplash on mount until /api/v1/users/me
    // resolves, so URL rewrites to /login or /welcome only AFTER the
    // unauth check returns. Wait for the redirect before reading URL.
    await page.waitForURL(/\/(login|welcome)/, { timeout: 10_000 });
  });

  test('unknown route shows 404 page', async ({ page }) => {
    await page.goto('/this-route-does-not-exist');
    await expect(page.locator('body')).toContainText(/not found|404/i);
  });
});
