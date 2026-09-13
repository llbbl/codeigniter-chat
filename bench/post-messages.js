import { check, sleep } from 'k6';
import http from 'k6/http';
import { Rate, Trend } from 'k6/metrics';
import { baseUrl, csrfToken, login } from './lib/auth.js';

const failures = new Rate('post_message_failures');
const latency = new Trend('post_message_duration', true);
let session;

export const options = {
  noCookiesReset: true,
  scenarios: {
    post_messages: {
      executor: 'constant-vus',
      vus: 10,
      duration: '30s',
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
    post_message_failures: ['rate<0.01'],
    post_message_duration: ['p(95)<200'],
  },
};

export default function () {
  session ||= login();
  const response = http.post(
    `${baseUrl}/api/v1/messages`,
    {
      message: `k6 post ${session.username} ${Date.now()}`,
      csrf_test_name: csrfToken(),
    },
    {
      headers: { Accept: 'application/json' },
      tags: { name: 'POST /api/v1/messages' },
    },
  );
  const succeeded = check(response, {
    'message post succeeds': (result) => result.status === 200 && result.json('success') === true,
  });

  failures.add(!succeeded);
  latency.add(response.timings.duration);
  sleep(0.5);
}
