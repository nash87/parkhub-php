/**
 * Rate-limit boundary E2E tests (W2 audit gap)
 *
 * Coverage:
 *   - POST /api/v1/auth/login   → throttle:auth  → 5 req/min/IP (AppServiceProvider)
 *   - POST /api/v1/auth/forgot-password → throttle:password-reset → 3 req/15min/IP
 *
 * Uses `request` context (direct HTTP, no browser) for tight loops — faster
 * and far less RAM than page.goto.  The baseURL from playwright.config.ts is
 * inherited automatically by `request` fixtures.
 *
 * These tests are intentionally slow-safe: they skip rather than fail when
 * Retry-After would exceed 90 s (keeps CI bounded).  The happy-path decay test
 * is annotated @slow so it can be excluded with --grep-invert in fast modes.
 */

import { test, expect, APIRequestContext } from '@playwright/test';

// Auth-limiter: 5 req/min/IP.  We send LIMIT+1 to hit the wall.
const AUTH_LIMIT = 5;
// Password-reset limiter: 3 req/15min/IP.
const RESET_LIMIT = 3;

// Max Retry-After we will honour in the decay test before skipping.
const MAX_WAIT_SECS = 90;

// Credentials that will always produce 401/422 without consuming a real session.
const BAD_CREDS = { email: 'nobody@parkhub.test', password: 'wrong-password-xyzzy' };

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Extract a numeric Retry-After value (seconds) from a response. */
function retryAfterSecs(headers: Record<string, string>): number | null {
  const raw = headers['retry-after'];
  if (!raw) return null;
  const parsed = parseInt(raw, 10);
  if (!isNaN(parsed)) return parsed;
  // RFC 7231 HTTP-date form — compute delta from now
  const date = new Date(raw);
  if (!isNaN(date.getTime())) {
    return Math.max(1, Math.ceil((date.getTime() - Date.now()) / 1000));
  }
  return null;
}

/** POST /api/v1/auth/login with bad credentials, return the raw response. */
async function postLogin(request: APIRequestContext) {
  return request.post('/api/v1/auth/login', {
    data: BAD_CREDS,
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    // Don't follow redirects — we need the exact status code.
    failOnStatusCode: false,
  });
}

/** POST /api/v1/auth/forgot-password with a dummy email. */
async function postForgotPassword(request: APIRequestContext) {
  return request.post('/api/v1/auth/forgot-password', {
    data: { email: BAD_CREDS.email },
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    failOnStatusCode: false,
  });
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test.describe('Rate-limit boundaries', () => {

  test('login endpoint enforces rate limit after 5 attempts', async ({ request }) => {
    const statuses: number[] = [];

    // Fire AUTH_LIMIT requests — each must fail auth (401 or 422), not be rate-limited.
    for (let i = 0; i < AUTH_LIMIT; i++) {
      const resp = await postLogin(request);
      statuses.push(resp.status());
      expect(
        resp.status(),
        `Request ${i + 1} should be an auth failure (401/422), not rate-limited yet`,
      ).toBeOneOf([401, 422]);
    }

    // The (LIMIT+1)th request must be rejected by the rate limiter.
    const overLimit = await postLogin(request);
    expect(
      overLimit.status(),
      'Request over the limit must return 429 Too Many Requests',
    ).toBe(429);

    // Laravel MUST include Retry-After on 429 responses.
    const headers = overLimit.headers();
    expect(headers['retry-after'], 'Retry-After header must be present on 429').toBeTruthy();

    const retrySecs = retryAfterSecs(headers);
    expect(retrySecs, 'Retry-After must parse to a positive integer').not.toBeNull();
    expect(retrySecs!).toBeGreaterThan(0);
  });

  test('password-reset endpoint enforces rate limit after 3 attempts', async ({ request }) => {
    // Fire RESET_LIMIT requests — each should succeed or fail gracefully (not 429).
    for (let i = 0; i < RESET_LIMIT; i++) {
      const resp = await postForgotPassword(request);
      expect(
        resp.status(),
        `Request ${i + 1} should not be rate-limited yet`,
      ).not.toBe(429);
    }

    // The (LIMIT+1)th must be rate-limited.
    const overLimit = await postForgotPassword(request);
    expect(overLimit.status()).toBe(429);

    const headers = overLimit.headers();
    expect(headers['retry-after'], 'Retry-After header must be present on 429').toBeTruthy();

    const retrySecs = retryAfterSecs(headers);
    expect(retrySecs!).toBeGreaterThan(0);
  });

  test('rate-limit window decays — request succeeds after Retry-After @slow', async ({ request }) => {
    // Exhaust the auth limiter first.
    for (let i = 0; i <= AUTH_LIMIT; i++) {
      await postLogin(request);
    }

    // Read Retry-After from the last exhausted response.
    const limited = await postLogin(request);
    expect(limited.status()).toBe(429);

    const retrySecs = retryAfterSecs(limited.headers());
    if (retrySecs === null || retrySecs > MAX_WAIT_SECS) {
      test.skip();
      return;
    }

    // Wait for the window to expire (add 2 s buffer for clock skew).
    await new Promise(resolve => setTimeout(resolve, (retrySecs + 2) * 1000));

    // After the window, the limiter bucket should be reset — a fresh attempt
    // must NOT return 429 (it will return 401/422 since creds are wrong).
    const afterDecay = await postLogin(request);
    expect(
      afterDecay.status(),
      'After Retry-After window, request should be accepted by the limiter (auth fail, not rate-limit)',
    ).not.toBe(429);
  });

});
