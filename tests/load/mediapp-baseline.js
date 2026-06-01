import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'https://mediapps.online').replace(/\/+$/, '');
const REP_TOKEN = __ENV.REP_TOKEN;
const DOCTOR_TOKEN = __ENV.DOCTOR_TOKEN;

const unauthorizedRate = new Rate('unexpected_auth_failures');
const rateLimitedRate = new Rate('rate_limited_responses');
const serverErrorRate = new Rate('server_error_responses');
const endpointFailures = new Counter('endpoint_failures');

export const options = {
  stages: [
    { duration: '1m', target: 5 },
    { duration: '3m', target: 5 },
    { duration: '3m', target: 15 },
    { duration: '7m', target: 15 },
    { duration: '1m', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    unexpected_auth_failures: ['rate==0'],
    rate_limited_responses: ['rate<0.02'],
    server_error_responses: ['rate==0'],
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
  { weight: 30, auth: null, endpoints: publicEndpoints },
  { weight: 40, auth: 'rep', endpoints: repEndpoints },
  { weight: 30, auth: 'doctor', endpoints: doctorEndpoints },
];

export function setup() {
  if (!REP_TOKEN) {
    fail('REP_TOKEN is required. Pass it with -e REP_TOKEN="$REP_TOKEN".');
  }

  if (!DOCTOR_TOKEN) {
    fail('DOCTOR_TOKEN is required. Pass it with -e DOCTOR_TOKEN="$DOCTOR_TOKEN".');
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

  sleep(Number(__ENV.SLEEP_SECONDS || 1));
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
  unauthorizedRate.add(response.status === 401 || response.status === 403);
  rateLimitedRate.add(response.status === 429);
  serverErrorRate.add(response.status >= 500);
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
