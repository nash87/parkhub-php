// ParkHub PHP -- Campus Profile Load Test
// 100 VUs, p95<500ms -- multi-lot campus deployment
// Run: k6 run tests/load/profiles/campus.js

import http from "k6/http";
import { check, sleep } from "k6";
import { Counter } from "k6/metrics";
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

export const options = {
  stages: [
    { duration: "1m", target: 20 },
    { duration: "2m", target: 100 },
    { duration: "3m", target: 100 },
    { duration: "1m", target: 50 },
    { duration: "1m", target: 0 },
  ],
  thresholds: {
    http_req_duration: ["p(95)<500", "p(99)<1500"],
    http_req_failed: ["rate<0.02"],
    errors: ["count<50"],
    checks: ["rate>0.98"],
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
  if (!ok) errorCount.add(1);
  return res;
}

export default function (data) {
  const endpoints = [
    { url: `${BASE_URL}/api/v1/health`, headers: HEADERS, name: "health" },
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
      url: `${BASE_URL}/api/v1/admin/users`,
      headers: data.adminHeaders,
      name: "admin-users",
    },
    {
      url: `${BASE_URL}/api/v1/admin/bookings`,
      headers: data.adminHeaders,
      name: "admin-bookings",
    },
  ];

  // Hit 4-6 random endpoints per iteration
  const count = 4 + Math.floor(Math.random() * 3);
  for (let i = 0; i < count; i++) {
    const ep = endpoints[Math.floor(Math.random() * endpoints.length)];
    hitEndpoint(ep.url, ep.headers, ep.name);
    sleep(0.05);
  }

  // Login flow (50% of iterations)
  if (Math.random() < 0.5) {
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
  }

  // Booking lifecycle (20% of iterations)
  if (Math.random() < 0.2) {
    const lotId = getRandomLotId(http, data.userHeaders);
    if (lotId) {
      const bookingId = createBooking(http, data.userHeaders, lotId);
      if (bookingId) {
        cancelBooking(http, data.userHeaders, bookingId);
      }
    }
  }

  sleep(0.2);
}
