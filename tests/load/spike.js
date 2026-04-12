// ParkHub PHP -- Spike Test
// Sudden load surge: 1 -> 200 -> 1 VUs
// Run: k6 run tests/load/spike.js

import http from "k6/http";
import { check, sleep } from "k6";
import { Rate } from "k6/metrics";
import {
  BASE_URL,
  CREDENTIALS,
  HEADERS,
  login,
  getRandomLotId,
  createBooking,
  cancelBooking,
} from "./config.js";

const failRate = new Rate("failed_requests");

export const options = {
  stages: [
    { duration: "30s", target: 1 }, // warm up
    { duration: "30s", target: 200 }, // spike
    { duration: "1m", target: 200 }, // hold spike
    { duration: "30s", target: 1 }, // recover
    { duration: "1m", target: 1 }, // cool down
  ],
  thresholds: {
    http_req_duration: ["p(95)<2000"],
    failed_requests: ["rate<0.10"],
    checks: ["rate>0.90"],
  },
};

export function setup() {
  const authHeaders = login(http, CREDENTIALS.user);
  return { authHeaders };
}

export default function (data) {
  // 1. Health (always fast, tests infrastructure)
  const health = http.get(`${BASE_URL}/api/v1/health`);
  failRate.add(health.status !== 200);
  check(health, { "health ok": (r) => r.status === 200 });

  // 2. Login (auth under pressure)
  const loginRes = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({
      email: CREDENTIALS.user.email,
      username: CREDENTIALS.user.email.split("@")[0],
      password: CREDENTIALS.user.password,
    }),
    { headers: HEADERS }
  );
  failRate.add(loginRes.status !== 200);
  check(loginRes, { "login ok": (r) => r.status === 200 });

  // 3. List bookings (read under pressure)
  const bookings = http.get(`${BASE_URL}/api/v1/bookings`, {
    headers: data.authHeaders,
  });
  failRate.add(bookings.status !== 200);
  check(bookings, { "bookings ok": (r) => r.status === 200 });

  // 4. List lots
  const lots = http.get(`${BASE_URL}/api/v1/lots`, {
    headers: data.authHeaders,
  });
  failRate.add(lots.status !== 200);
  check(lots, { "lots ok": (r) => r.status === 200 });

  // 5. Occupancy check
  const occupancy = http.get(`${BASE_URL}/api/v1/public/occupancy`);
  failRate.add(occupancy.status !== 200);
  check(occupancy, { "occupancy ok": (r) => r.status === 200 });

  // 6. Booking lifecycle (10% of iterations)
  if (Math.random() < 0.1) {
    const lotId = getRandomLotId(http, data.authHeaders);
    if (lotId) {
      const bookingId = createBooking(http, data.authHeaders, lotId);
      if (bookingId) {
        cancelBooking(http, data.authHeaders, bookingId);
      }
    }
  }

  sleep(0.2);
}
