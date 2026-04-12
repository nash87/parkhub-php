// ParkHub PHP -- Small Profile Load Test
// 20 VUs, p95<200ms -- single-lot deployment
// Run: k6 run tests/load/profiles/small.js

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
} from "../config.js";

export const options = {
  stages: [
    { duration: "30s", target: 5 },
    { duration: "2m", target: 20 },
    { duration: "2m", target: 20 },
    { duration: "30s", target: 0 },
  ],
  thresholds: {
    http_req_duration: ["p(95)<200", "p(99)<500"],
    http_req_failed: ["rate<0.01"],
    checks: ["rate>0.99"],
  },
};

export function setup() {
  const userHeaders = login(http, CREDENTIALS.user);
  return { userHeaders };
}

export default function (data) {
  // Health check
  const health = http.get(`${BASE_URL}/api/v1/health`);
  check(health, { "health ok": (r) => r.status === 200 });

  // Login
  const loginRes = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({
      email: CREDENTIALS.user.email,
      username: CREDENTIALS.user.email.split("@")[0],
      password: CREDENTIALS.user.password,
    }),
    { headers: HEADERS }
  );
  check(loginRes, { "login ok": (r) => r.status === 200 });

  // List lots
  const lots = http.get(`${BASE_URL}/api/v1/lots`, {
    headers: data.userHeaders,
  });
  check(lots, { "lots ok": (r) => r.status === 200 });

  // List bookings
  const bookings = http.get(`${BASE_URL}/api/v1/bookings`, {
    headers: data.userHeaders,
  });
  check(bookings, { "bookings ok": (r) => r.status === 200 });

  // Occupancy
  const occupancy = http.get(`${BASE_URL}/api/v1/public/occupancy`);
  check(occupancy, { "occupancy ok": (r) => r.status === 200 });

  // Booking lifecycle (30% of iterations)
  if (Math.random() < 0.3) {
    const lotId = getRandomLotId(http, data.userHeaders);
    if (lotId) {
      const bookingId = createBooking(http, data.userHeaders, lotId);
      if (bookingId) {
        cancelBooking(http, data.userHeaders, bookingId);
      }
    }
  }

  sleep(0.5);
}
