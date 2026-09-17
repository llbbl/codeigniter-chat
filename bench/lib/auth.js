import { check, fail } from 'k6';
import exec from 'k6/execution';
import http from 'k6/http';

export const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8080').replace(/\/$/, '');
export const websocketUrl = (__ENV.WS_URL || 'ws://127.0.0.1:8081').replace(/\/$/, '');

const password = __ENV.BENCH_PASSWORD || 'Benchmark123!';

export function login() {
  return loginForUser(((exec.vu.idInTest - 1) % 100) + 1);
}

export function loginForUser(sequence) {
  const username = `k6_user_${String(sequence).padStart(3, '0')}`;
  const loginPage = http.get(`${baseUrl}/auth/login`, { tags: { name: 'GET /auth/login' } });
  const loginCsrf = metaValue(loginPage.body, 'csrf-token');

  if (!loginCsrf) {
    fail(`Could not read the CSRF token from ${baseUrl}/auth/login`);
  }

  const response = http.post(
    `${baseUrl}/auth/processLogin`,
    {
      username,
      password,
      csrf_test_name: loginCsrf,
    },
    {
      redirects: 5,
      tags: { name: 'POST /auth/processLogin' },
    },
  );
  const authenticated = check(response, {
    'login succeeds': (result) => result.status === 200 && result.url.includes('/chat'),
  });

  if (!authenticated) {
    fail(`Login failed for ${username} with status ${response.status}`);
  }

  const chatPage = http.get(`${baseUrl}/chat/vue`, { tags: { name: 'GET /chat/vue' } });
  const userId = scriptValue(chatPage.body, 'CURRENT_USER_ID');
  const websocketToken = scriptValue(chatPage.body, 'WEBSOCKET_TOKEN');

  if (!userId || !websocketToken) {
    fail(`Could not read WebSocket credentials for ${username}`);
  }

  return {
    username,
    userId,
    websocketToken,
  };
}

export function csrfToken() {
  const cookies = http.cookieJar().cookiesForURL(baseUrl);
  const values = cookies.csrf_cookie_name || [];

  if (values.length === 0) {
    fail('The authenticated session does not contain a CSRF cookie');
  }

  return values[0];
}

function metaValue(html, name) {
  if (typeof html !== 'string') {
    return '';
  }

  const match = html.match(new RegExp(`<meta[^>]+name=["']${name}["'][^>]+content=["']([^"']+)["']`, 'i'));

  return match ? match[1] : '';
}

function scriptValue(html, name) {
  if (typeof html !== 'string') {
    return '';
  }

  const match = html.match(new RegExp(`${name}\\s*=\\s*["']?([^"';\\s]+)`));

  return match ? match[1] : '';
}
