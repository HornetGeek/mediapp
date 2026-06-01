# Mediapp Production Load Test

This folder contains a conservative k6 baseline test for `https://mediapps.online`.
It only calls public endpoints and authenticated read endpoints for representative
and doctor users.

## Safety Rules

- Do not commit API tokens.
- Use dedicated test tokens and rotate them after the run.
- Do not run this during peak production traffic.
- Stop the test if production users are affected.
- This script intentionally avoids booking, status changes, deletes, and notification clearing.

## Token Verification

Set the tokens in your shell:

```bash
export REP_TOKEN="..."
export DOCTOR_TOKEN="..."
```

Verify the tokens before starting the load test:

```bash
curl -i "https://mediapps.online/api/reps/visits/balance" \
  -H "Authorization: Bearer $REP_TOKEN"

curl -i "https://mediapps.online/api/doctor/profile" \
  -H "Authorization: Bearer $DOCTOR_TOKEN"
```

Both requests should return a 2xx response. Do not continue if either returns
`401` or `403`.

## Run Baseline Test

Run k6 through Docker:

```bash
docker run --rm -i grafana/k6 run \
  -e BASE_URL="https://mediapps.online" \
  -e REP_TOKEN="$REP_TOKEN" \
  -e DOCTOR_TOKEN="$DOCTOR_TOKEN" \
  - < tests/load/mediapp-baseline.js
```

## Run Controlled Stress Test

This test ramps traffic above the baseline and aborts if rate limiting, server
errors, authentication failures, or slow responses cross safety thresholds.

```bash
docker run --rm -i grafana/k6 run \
  -e BASE_URL="https://mediapps.online" \
  -e REP_TOKEN="$REP_TOKEN" \
  -e DOCTOR_TOKEN="$DOCTOR_TOKEN" \
  -e STRESS_SLEEP_SECONDS="5" \
  - < tests/load/mediapp-stress.js
```

The stress profile is:

- 10 virtual users for 3 minutes.
- 20 virtual users for 3 minutes.
- 35 virtual users for 3 minutes.
- 50 virtual users for 2 minutes.
- Ramp down over 1 minute.

Use the highest step with acceptable latency and low error/rate-limit responses
as the confirmed capacity from this single test source.

The baseline profile is:

- Ramp to 5 virtual users over 1 minute.
- Hold 5 virtual users for 3 minutes.
- Ramp to 15 virtual users over 3 minutes.
- Hold 15 virtual users for 7 minutes.
- Ramp down over 1 minute.

## Acceptance Criteria

- `http_req_failed` below 1%.
- `p95` latency below 1000 ms.
- `p99` latency below 2000 ms.
- No repeated `5xx` responses.
- No unexpected `401` or `403` responses.

## Stop Conditions

Stop immediately if:

- `5xx` errors repeat.
- Error rate exceeds 2%.
- `p95` latency stays above 2 seconds.
- Production CPU, memory, or database load becomes unsafe.
- Real users report issues.

## Optional Tuning

```bash
export CHECK_VERSION_APP_TYPE="doctor"
export CHECK_VERSION_PLATFORM="android"
export SLEEP_SECONDS="1"
export REQUEST_TIMEOUT="10s"
```
