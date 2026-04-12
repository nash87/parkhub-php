// ParkHub PHP -- Load Test
// Sustained load: ramp to 50 VUs over 5 minutes
// Run: k6 run tests/load/load.js

import http from "k6/http";
import { check, sleep } from "k6";
import {
  BASE_URL,
  CREDENTIALS,
  HEADERS,
  THRESHOLDS,
  login,
  getRandomLotId,
  createBooking,
  cancelBooking,
} from "./config.js";

export const options = {
  stages: [
    { duration: "1m", target: 10 },
    { duration: "2m", target: 50 },
    { duration: "1m", target: 50 },
    { duration: "1m", target: 0 },
  ],
  thresholds: {
    ...THRESHOLDS,
    checks: ["rate>0.99"],
  },
};

export function setup() {
  const userHeaders = login(http, CREDENTIALS.user);
  const adminHeaders = login(http, CREDENTIALS.admin);
  return { userHeaders, adminHeaders };
}

export default function (data) {
  // 1. Login
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

  // 2. List lots
  const lots = http.get(`${BASE_URL}/api/v1/lots`, {
    headers: data.userHeaders,
  });
  check(lots, { "lots ok": (r) => r.status === 200 });

  // 3. Check occupancy
  const occupancy = http.get(`${BASE_URL}/api/v1/public/occupancy`);
  check(occupancy, { "occupancy ok": (r) => r.status === 200 });

  // 4. List bookings
  const bookings = http.get(`${BASE_URL}/api/v1/bookings`, {
    headers: data.userHeaders,
  });
  check(bookings, { "bookings ok": (r) => r.status === 200 });

  // 5. Create booking
  const lotId = getRandomLotId(http, data.userHeaders);
  if (lotId) {
    const bookingId = createBooking(http, data.userHeaders, lotId);

    // 6. Cancel booking
    if (bookingId) {
      const cancelRes = cancelBooking(http, data.userHeaders, bookingId);
      check(cancelRes, {
        "cancel ok": (r) => r.status === 200 || r.status === 204,
      });
    }
  }

  // 7. Health endpoint
  const health = http.get(`${BASE_URL}/api/v1/health`);
  check(health, { "health ok": (r) => r.status === 200 });

  sleep(0.5);
}
