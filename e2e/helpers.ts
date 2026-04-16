import { expect, type Page, type APIRequestContext } from '@playwright/test';

/** Demo credentials used across all E2E tests.
 *
 * Kept in lockstep with parkhub-rust/e2e/helpers.ts — both fields
 * populated so `data: DEMO_ADMIN` works against both backends
 * (PHP requires `username`, Rust accepts either).
 */
export const DEMO_ADMIN = {
  username: 'admin@parkhub.test',
  email: 'admin@parkhub.test',
  password: 'demo',
};

/** Authenticate via API and return the JWT/Sanctum token. */
export async function loginViaApi(request: APIRequestContext): Promise<string> {
  const res = await request.post('/api/v1/auth/login', {
    data: { username: DEMO_ADMIN.email, password: DEMO_ADMIN.password },
  });
  const body = await res.json();
  return body.data?.token ?? body.data?.tokens?.access_token ?? body.token ?? '';
}

/** Log in through the UI login form. */
export async function loginViaUi(page: Page): Promise<void> {
  // Wait for network to idle so all lazy-loaded chunks + React hydration have
  // completed before we interact with the form. Without this, page.fill()
  // can run against a DOM input whose react-hook-form register() listener
  // hasn't attached yet — the DOM shows the typed value but the form state
  // stays empty, and submit surfaces a "Required" alert.
  await page.goto('/login', { waitUntil: 'domcontentloaded' });

  const submit = page.getByRole('button', { name: /sign in|log in|login/i });
  await submit.waitFor({ state: 'visible' });
  await expect(submit).toBeEnabled();

  const emailField = page.getByLabel(/email/i);
  const passwordField = page.locator('input[type="password"]').first();

  // Focus-then-fill forces React to respond to a real interaction before we
  // set the value, which flushes any remaining hydration for this subtree.
  await emailField.click();
  await emailField.fill(DEMO_ADMIN.email);
  await passwordField.click();
  await passwordField.fill(DEMO_ADMIN.password);

  // Verify form state was captured. react-hook-form updates its internal
  // map on the input event; if the DOM shows the value but the state is
  // empty, blur+refill recovers without a test-level retry.
  if ((await emailField.inputValue()) !== DEMO_ADMIN.email) {
    await emailField.fill(DEMO_ADMIN.email);
  }
  if ((await passwordField.inputValue()) !== DEMO_ADMIN.password) {
    await passwordField.fill(DEMO_ADMIN.password);
  }

  await submit.click();
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 });
}

/** All public frontend routes (no auth needed). */
export const PUBLIC_ROUTES = ['/login', '/register', '/forgot-password', '/welcome'];

/** All protected frontend routes (auth needed). */
export const PROTECTED_ROUTES = [
  '/',
  '/book',
  '/bookings',
  '/credits',
  '/vehicles',
  '/favorites',
  '/absences',
  '/profile',
  '/team',
  '/notifications',
  '/calendar',
  '/translations',
];

/** Admin-only frontend routes. */
export const ADMIN_ROUTES = [
  '/admin',
  '/admin/settings',
  '/admin/users',
  '/admin/lots',
  '/admin/announcements',
  '/admin/reports',
  '/admin/translations',
];

/** All public API endpoints that should return 200 without auth. */
export const PUBLIC_API_ENDPOINTS = [
  '/api/v1/health',
  '/api/v1/health/live',
  '/api/v1/health/ready',
  '/api/v1/modules',
  '/api/v1/system/version',
  '/api/v1/system/maintenance',
  '/api/v1/public/occupancy',
  '/api/v1/discover',
];

/** Protected API endpoints that require auth (GET). */
export const PROTECTED_API_ENDPOINTS = [
  '/api/v1/me',
  '/api/v1/users/me',
  '/api/v1/lots',
  '/api/v1/features',
  '/api/v1/user/stats',
  '/api/v1/user/preferences',
  '/api/v1/team',
  '/api/v1/team/today',
];

/** Admin API endpoints (GET). */
export const ADMIN_API_ENDPOINTS = [
  '/api/v1/admin/users',
  '/api/v1/admin/bookings',
  '/api/v1/admin/stats',
  '/api/v1/admin/settings',
  '/api/v1/admin/audit-log',
];

/** Mobile device viewports for responsive tests. */
export const MOBILE_DEVICES = [
  { name: 'iPhone 14 Pro', width: 393, height: 852 },
  { name: 'iPhone 15 Pro Max', width: 430, height: 932 },
  { name: 'Samsung Galaxy S24', width: 360, height: 780 },
  { name: 'iPad Pro', width: 1024, height: 1366 },
  { name: 'Pixel 8', width: 412, height: 915 },
];
