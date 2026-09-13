import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { baseUrl, login } from './lib/auth.js';

const failures = new Rate('read_message_failures');
const latency = new Trend('read_message_duration', true);
let session;

export const options = {
  noCookiesReset: true,
  scenarios: {
    read_messages: {
      executor: 'constant-vus',
      vus: 50,
      duration: '30s',
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
    read_message_failures: ['rate<0.01'],
    read_message_duration: ['p(99)<200'],
  },
};

export default function () {
  session ||= login();
  const response = http.get(`${baseUrl}/api/v1/messages?page=1&per_page=50`, {
    headers: { Accept: 'application/json' },
    tags: { name: 'GET /api/v1/messages' },
  });
  const succeeded = check(response, {
    'message read succeeds': (result) => result.status === 200 && Array.isArray(result.json('messages')),
  });

  failures.add(!succeeded);
  latency.add(response.timings.duration);
  sleep(0.5);
}
