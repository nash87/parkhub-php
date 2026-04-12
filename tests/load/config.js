// ParkHub PHP — k6 shared configuration
// Override via environment: K6_BASE_URL, K6_ADMIN_EMAIL, K6_ADMIN_PASSWORD, etc.

export const BASE_URL = __ENV.K6_BASE_URL || "http://localhost:8082";

export const CREDENTIALS = {
  admin: {
    email: __ENV.ADMIN_EMAIL || "admin@parkhub.test",
    password: __ENV.ADMIN_PASSWORD || "demo",
  },
  user: {
    email: __ENV.USER_EMAIL || "user@parkhub.test",
    password: __ENV.USER_PASSWORD || "demo",
  },
};

export const THRESHOLDS = {
  http_req_duration: ["p(95)<500", "p(99)<1500"],
  http_req_failed: ["rate<0.01"],
  http_reqs: ["rate>10"],
};

export const HEADERS = {
  "Content-Type": "application/json",
  Accept: "application/json",
};

/**
 * Login and return auth headers with Bearer token.
 */
export function login(http, credentials) {
  const res = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({
      email: credentials.email,
      username: credentials.email.split("@")[0],
      password: credentials.password,
    }),
    { headers: HEADERS }
  );

  if (res.status !== 200) {
    console.error(`Login failed: ${res.status} ${res.body}`);
    return HEADERS;
  }

  const body = res.json();
  const token =
    body.data?.tokens?.access_token ||
    body.data?.token ||
    body.token ||
    body.access_token ||
    "";

  return {
    ...HEADERS,
    Authorization: `Bearer ${token}`,
  };
}

/**
 * Get a random lot ID from the lots list response.
 */
export function getRandomLotId(http, headers) {
  const res = http.get(`${BASE_URL}/api/v1/lots`, { headers });
  if (res.status !== 200) return null;

  const body = res.json();
  const items = Array.isArray(body) ? body : body.data || body.lots || [];
  if (items.length === 0) return null;

  return items[Math.floor(Math.random() * items.length)].id;
}

/**
 * Create a booking and return the booking ID (or null on failure).
 */
export function createBooking(http, headers, lotId) {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1 + Math.floor(Math.random() * 14));
  const dateStr = tomorrow.toISOString().split("T")[0];

  const startHour = 7 + Math.floor(Math.random() * 10);
  const endHour = startHour + 2 + Math.floor(Math.random() * 6);

  const res = http.post(
    `${BASE_URL}/api/v1/bookings`,
    JSON.stringify({
      lot_id: lotId,
      date: dateStr,
      start_time: `${dateStr}T${String(startHour).padStart(2, "0")}:00:00`,
      end_time: `${dateStr}T${String(Math.min(endHour, 23)).padStart(2, "0")}:00:00`,
      booking_type: "single",
    }),
    { headers }
  );

  if (res.status === 200 || res.status === 201) {
    const body = res.json();
    return body.id || body.data?.id || body.booking_id || null;
  }
  return null;
}

/**
 * Cancel a booking by ID.
 */
export function cancelBooking(http, headers, bookingId) {
  return http.del(`${BASE_URL}/api/v1/bookings/${bookingId}`, null, {
    headers,
  });
}
