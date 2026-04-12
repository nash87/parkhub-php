# ParkHub Load Tests (k6)

Performance and load testing scripts using [k6](https://grafana.com/docs/k6/).

## Prerequisites

Install k6:

```bash
# macOS
brew install k6

# Linux (Debian/Ubuntu)
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6

# Docker
docker run --rm -i grafana/k6 run - <tests/load/smoke.js
```

## Test Scenarios

| Script | VUs | Duration | Purpose |
|--------|-----|----------|---------|
| `smoke.js` | 1 | 30s | Sanity check -- health, login, bookings, cancel |
| `load.js` | 50 | 5min | Sustained load -- full booking lifecycle |
| `stress.js` | 100 | 10min | All endpoints under heavy load |
| `spike.js` | 200 | ~4min | Sudden traffic surge (1 to 200 VUs) |

## Profiles

Realistic load patterns matching deployment sizes:

| Profile | VUs | p95 Target | Deployment |
|---------|-----|------------|------------|
| `profiles/small.js` | 20 | <200ms | Single lot, ~200 slots |
| `profiles/campus.js` | 100 | <500ms | Multi-lot campus, ~800 slots |
| `profiles/enterprise.js` | 300 | <1s | Large-scale multi-tenant |

## Running

```bash
# Start ParkHub PHP first (default port 8082)
docker compose up -d

# --- Standard scenarios ---

# Smoke test (quick sanity)
k6 run tests/load/smoke.js

# Load test
k6 run tests/load/load.js

# Stress test
k6 run tests/load/stress.js

# Spike test
k6 run tests/load/spike.js

# --- Profile tests ---

# Small deployment
k6 run tests/load/profiles/small.js

# Campus deployment
k6 run tests/load/profiles/campus.js

# Enterprise deployment
k6 run tests/load/profiles/enterprise.js
```

## Configuration

Override defaults via environment variables:

```bash
K6_BASE_URL=https://parking.example.com \
ADMIN_EMAIL=admin@corp.com \
ADMIN_PASSWORD=secret \
USER_EMAIL=user@corp.com \
USER_PASSWORD=secret \
  k6 run tests/load/load.js
```

The default base URL is `http://localhost:8082` (PHP edition).
For Kubernetes deployments, use port-forward or your ingress URL.

## What Each Test Does

Every test script follows this pattern:

1. **Login** -- authenticate to get a Bearer token
2. **Create booking** -- POST a new booking to a random lot
3. **List bookings** -- GET the user's booking list
4. **Check occupancy** -- GET the public occupancy endpoint
5. **Cancel booking** -- DELETE the booking created in step 2
6. **Health check** -- GET the health endpoint

Stress and enterprise profiles additionally hit admin endpoints (users, audit log, settings).

## Thresholds

| Metric | Small | Campus | Enterprise |
|--------|-------|--------|------------|
| `http_req_duration (p95)` | <200ms | <500ms | <1000ms |
| `http_req_duration (p99)` | <500ms | <1500ms | <3000ms |
| `http_req_failed` | <1% | <2% | <5% |

## Output

Export results to JSON or InfluxDB for dashboarding:

```bash
# JSON output
k6 run --out json=results.json tests/load/load.js

# InfluxDB (for Grafana dashboards)
k6 run --out influxdb=http://localhost:8086/k6 tests/load/load.js

# Cloud (Grafana Cloud k6)
K6_CLOUD_TOKEN=your-token k6 cloud tests/load/load.js
```
