// ParkHub PHP -- Smoke Test
// Quick sanity check: 1 VU, 30s
// Run: k6 run tests/load/smoke.js

import http from "k6/http";
import { check, sleep } from "k6";
import {
  BASE_URL,
  CREDENTIALS,
  HEADERS,
  login,
  getRandomLotId,
  createBooking,
  cancelBooking,
} from "./config.js";

export const options = {
  vus: 1,
  duration: "30s",
  thresholds: {
    http_req_duration: ["p(95)<200", "p(99)<500"],
    http_req_failed: ["rate<0.01"],
    checks: ["rate>0.99"],
  },
};

export function setup() {
  const authHeaders = login(http, CREDENTIALS.user);
  return { authHeaders };
}

export default function (data) {
  // 1. Health check
  const health = http.get(`${BASE_URL}/api/v1/health`);
  check(health, {
    "health returns 200": (r) => r.status === 200,
  });

  // 2. Login flow
  const loginRes = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({
      email: CREDENTIALS.user.email,
      username: CREDENTIALS.user.email.split("@")[0],
      password: CREDENTIALS.user.password,
    }),
    { headers: HEADERS }
  );
  check(loginRes, {
    "login returns 200": (r) => r.status === 200,
    "login has token": (r) => {
      const body = r.json();
      return !!(
        body.data?.tokens?.access_token ||
        body.data?.token ||
        body.token ||
        body.access_token
      );
    },
  });

  // 3. List bookings
  const bookings = http.get(`${BASE_URL}/api/v1/bookings`, {
    headers: data.authHeaders,
  });
  check(bookings, {
    "bookings returns 200": (r) => r.status === 200,
  });

  // 4. List lots
  const lots = http.get(`${BASE_URL}/api/v1/lots`, {
    headers: data.authHeaders,
  });
  check(lots, {
    "lots returns 200": (r) => r.status === 200,
  });

  // 5. Check occupancy
  const occupancy = http.get(`${BASE_URL}/api/v1/public/occupancy`);
  check(occupancy, {
    "occupancy returns 200": (r) => r.status === 200,
  });

  // 6. Create and cancel a booking
  const lotId = getRandomLotId(http, data.authHeaders);
  if (lotId) {
    const bookingId = createBooking(http, data.authHeaders, lotId);
    if (bookingId) {
      const cancelRes = cancelBooking(http, data.authHeaders, bookingId);
      check(cancelRes, {
        "booking cancelled": (r) => r.status === 200 || r.status === 204,
      });
    }
  }

  sleep(1);
}
