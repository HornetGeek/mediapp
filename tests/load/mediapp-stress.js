import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'https://mediapps.online').replace(/\/+$/, '');
const REP_TOKEN = __ENV.REP_TOKEN;
const DOCTOR_TOKEN = __ENV.DOCTOR_TOKEN;
const SLEEP_SECONDS = Number(__ENV.STRESS_SLEEP_SECONDS || __ENV.SLEEP_SECONDS || 5);

const unexpectedAuthFailures = new Rate('unexpected_auth_failures');
const rateLimitedResponses = new Rate('rate_limited_responses');
const serverErrorResponses = new Rate('server_error_responses');
const endpointFailures = new Counter('endpoint_failures');

export const options = {
  stages: [
    { duration: '1m', target: 10 },
    { duration: '2m', target: 10 },
    { duration: '1m', target: 20 },
    { duration: '2m', target: 20 },
    { duration: '1m', target: 35 },
    { duration: '2m', target: 35 },
    { duration: '1m', target: 50 },
    { duration: '2m', target: 50 },
    { duration: '1m', target: 0 },
  ],
  thresholds: {
    http_req_failed: [{ threshold: 'rate<0.05', abortOnFail: true, delayAbortEval: '1m' }],
    http_req_duration: [{ threshold: 'p(95)<1500', abortOnFail: true, delayAbortEval: '1m' }],
    rate_limited_responses: [{ threshold: 'rate<0.05', abortOnFail: true, delayAbortEval: '1m' }],
    server_error_responses: [{ threshold: 'rate==0', abortOnFail: true, delayAbortEval: '30s' }],
    unexpected_auth_failures: [{ threshold: 'rate==0', abortOnFail: true, delayAbortEval: '30s' }],
  },
};

const publicEndpoints = [
  { method: 'GET', path: '/api/banner-ads', name: 'public_banner_ads' },
  { method: 'GET', path: '/api/privacy-policy', name: 'public_privacy_policy' },
  {
    method: 'POST',
    path: '/api/check-version',
    name: 'public_check_version',
    body: JSON.stringify({
      app_type: __ENV.CHECK_VERSION_APP_TYPE || 'doctor',
      platform: __ENV.CHECK_VERSION_PLATFORM || 'android',
    }),
    headers: { 'Content-Type': 'application/json' },
  },
];

const repEndpoints = [
  { method: 'GET', path: '/api/reps/profile', name: 'rep_profile' },
  { method: 'GET', path: '/api/reps/visits/balance', name: 'rep_visits_balance' },
  { method: 'GET', path: '/api/reps/companies', name: 'rep_companies' },
  { method: 'GET', path: '/api/reps/specialities', name: 'rep_specialities' },
];

const doctorEndpoints = [
  { method: 'GET', path: '/api/doctor/profile', name: 'doctor_profile' },
  { method: 'GET', path: '/api/doctor/specialities', name: 'doctor_specialities' },
  { method: 'GET', path: '/api/doctor/doctor-available-times', name: 'doctor_available_times' },
];

const scenarioGroups = [
  { weight: 25, auth: null, endpoints: publicEndpoints },
  { weight: 45, auth: 'rep', endpoints: repEndpoints },
  { weight: 30, auth: 'doctor', endpoints: doctorEndpoints },
];

export function setup() {
  if (!REP_TOKEN) {
    fail('REP_TOKEN is required. Pass it with -e REP_TOKEN="$REP_TOKEN".');
  }

  if (!DOCTOR_TOKEN) {
    fail('DOCTOR_TOKEN is required. Pass it with -e DOCTOR_TOKEN="$DOCTOR_TOKEN".');
  }

  if (!Number.isFinite(SLEEP_SECONDS) || SLEEP_SECONDS < 1) {
    fail('STRESS_SLEEP_SECONDS must be a number greater than or equal to 1.');
  }
}

export default function () {
  const group = pickWeighted(scenarioGroups);
  const endpoint = pick(group.endpoints);
  const headers = {
    Accept: 'application/json',
    ...(endpoint.headers || {}),
    ...authHeaders(group.auth),
  };

  const response = request(endpoint, headers);
  recordSafetySignals(response);

  const ok = check(response, {
    [`${endpoint.name}: status is 2xx`]: (res) => res.status >= 200 && res.status < 300,
    [`${endpoint.name}: not unauthorized`]: (res) => res.status !== 401 && res.status !== 403,
    [`${endpoint.name}: not rate limited`]: (res) => res.status !== 429,
    [`${endpoint.name}: no server error`]: (res) => res.status < 500,
  });

  if (!ok) {
    endpointFailures.add(1, {
      endpoint: endpoint.name,
      status: String(response.status),
    });
  }

  sleep(SLEEP_SECONDS);
}

function request(endpoint, headers) {
  const params = {
    headers,
    tags: { endpoint: endpoint.name },
    timeout: __ENV.REQUEST_TIMEOUT || '10s',
  };

  const url = `${BASE_URL}${endpoint.path}`;

  if (endpoint.method === 'POST') {
    return http.post(url, endpoint.body || null, params);
  }

  return http.get(url, params);
}

function authHeaders(auth) {
  if (auth === 'rep') {
    return { Authorization: `Bearer ${REP_TOKEN}` };
  }

  if (auth === 'doctor') {
    return { Authorization: `Bearer ${DOCTOR_TOKEN}` };
  }

  return {};
}

function recordSafetySignals(response) {
  unexpectedAuthFailures.add(response.status === 401 || response.status === 403);
  rateLimitedResponses.add(response.status === 429);
  serverErrorResponses.add(response.status >= 500);
}

function pickWeighted(groups) {
  const totalWeight = groups.reduce((sum, group) => sum + group.weight, 0);
  let selectedWeight = Math.random() * totalWeight;

  for (const group of groups) {
    selectedWeight -= group.weight;
    if (selectedWeight <= 0) {
      return group;
    }
  }

  return groups[groups.length - 1];
}

function pick(items) {
  return items[Math.floor(Math.random() * items.length)];
}
