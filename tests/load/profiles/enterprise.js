// ParkHub PHP -- Enterprise Profile Load Test
// 300 VUs, p95<1s -- large-scale multi-tenant deployment
// Run: k6 run tests/load/profiles/enterprise.js

import http from "k6/http";
import { check, sleep } from "k6";
import { Counter, Rate } from "k6/metrics";
import {
  BASE_URL,
  CREDENTIALS,
  HEADERS,
  login,
  getRandomLotId,
  createBooking,
  cancelBooking,
} from "../config.js";

const errorCount = new Counter("errors");
const failRate = new Rate("failed_requests");

export const options = {
  stages: [
    { duration: "2m", target: 50 },
    { duration: "2m", target: 150 },
    { duration: "3m", target: 300 },
    { duration: "2m", target: 300 },
    { duration: "1m", target: 100 },
    { duration: "1m", target: 0 },
  ],
  thresholds: {
    http_req_duration: ["p(95)<1000", "p(99)<3000"],
    http_req_failed: ["rate<0.05"],
    errors: ["count<200"],
    failed_requests: ["rate<0.10"],
    checks: ["rate>0.90"],
  },
};

export function setup() {
  const userHeaders = login(http, CREDENTIALS.user);
  const adminHeaders = login(http, CREDENTIALS.admin);
  return { userHeaders, adminHeaders };
}

function hitEndpoint(url, headers, name) {
  const res = http.get(url, { headers, tags: { name } });
  const ok = check(res, { [`${name} ok`]: (r) => r.status === 200 });
  if (!ok) {
    errorCount.add(1);
    failRate.add(true);
  } else {
    failRate.add(false);
  }
  return res;
}

export default function (data) {
  // Public endpoints (always included)
  hitEndpoint(`${BASE_URL}/api/v1/health`, HEADERS, "health");

  const endpoints = [
    {
      url: `${BASE_URL}/api/v1/public/occupancy`,
      headers: HEADERS,
      name: "occupancy",
    },
    {
      url: `${BASE_URL}/api/v1/lots`,
      headers: data.userHeaders,
      name: "lots",
    },
    {
      url: `${BASE_URL}/api/v1/bookings`,
      headers: data.userHeaders,
      name: "bookings",
    },
    {
      url: `${BASE_URL}/api/v1/me`,
      headers: data.userHeaders,
      name: "profile",
    },
    {
      url: `${BASE_URL}/api/v1/vehicles`,
      headers: data.userHeaders,
      name: "vehicles",
    },
    {
      url: `${BASE_URL}/api/v1/team`,
      headers: data.userHeaders,
      name: "team",
    },
    {
      url: `${BASE_URL}/api/v1/absences`,
      headers: data.userHeaders,
      name: "absences",
    },
    {
      url: `${BASE_URL}/api/v1/waitlist`,
      headers: data.userHeaders,
      name: "waitlist",
    },
    {
      url: `${BASE_URL}/api/v1/recurring-bookings`,
      headers: data.userHeaders,
      name: "recurring",
    },

    // Admin (simulating admin dashboard)
    {
      url: `${BASE_URL}/api/v1/admin/users`,
      headers: data.adminHeaders,
      name: "admin-users",
    },
    {
      url: `${BASE_URL}/api/v1/admin/bookings`,
      headers: data.adminHeaders,
      name: "admin-bookings",
    },
    {
      url: `${BASE_URL}/api/v1/admin/audit-log`,
      headers: data.adminHeaders,
      name: "admin-audit",
    },
    {
      url: `${BASE_URL}/api/v1/admin/settings`,
      headers: data.adminHeaders,
      name: "admin-settings",
    },
  ];

  // Hit 5-8 random endpoints per iteration
  const count = 5 + Math.floor(Math.random() * 4);
  for (let i = 0; i < count; i++) {
    const ep = endpoints[Math.floor(Math.random() * endpoints.length)];
    hitEndpoint(ep.url, ep.headers, ep.name);
    sleep(0.05);
  }

  // Login flow (30% of iterations)
  if (Math.random() < 0.3) {
    const loginRes = http.post(
      `${BASE_URL}/api/v1/auth/login`,
      JSON.stringify({
        email: CREDENTIALS.user.email,
        username: CREDENTIALS.user.email.split("@")[0],
        password: CREDENTIALS.user.password,
      }),
      { headers: HEADERS }
    );
    const loginOk = check(loginRes, {
      "login ok": (r) => r.status === 200,
    });
    if (!loginOk) failRate.add(true);
    else failRate.add(false);
  }

  // Booking lifecycle (15% of iterations)
  if (Math.random() < 0.15) {
    const lotId = getRandomLotId(http, data.userHeaders);
    if (lotId) {
      const bookingId = createBooking(http, data.userHeaders, lotId);
      if (bookingId) {
        // Read the booking
        const showRes = http.get(
          `${BASE_URL}/api/v1/bookings/${bookingId}`,
          { headers: data.userHeaders }
        );
        check(showRes, {
          "booking detail ok": (r) => r.status === 200,
        });

        // Cancel
        cancelBooking(http, data.userHeaders, bookingId);
      }
    }
  }

  sleep(0.1);
}
