import { check } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import exec from 'k6/execution';
import { WebSocket } from 'k6/websockets';
import { loginForUser, websocketUrl } from './lib/auth.js';

const deliveries = new Rate('websocket_delivery_success');
const latency = new Trend('websocket_delivery_duration', true);

export const options = {
  noCookiesReset: true,
  setupTimeout: '2m',
  scenarios: {
    websocket_fanout: {
      executor: 'per-vu-iterations',
      vus: 100,
      iterations: 1,
      maxDuration: '30s',
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
    websocket_delivery_success: ['rate>0.99'],
    websocket_delivery_duration: ['p(95)<500'],
  },
};

export function setup() {
  const sessions = [];

  for (let sequence = 1; sequence <= 100; sequence += 1) {
    sessions.push(loginForUser(sequence));
  }

  return sessions;
}

export default function (sessions) {
  const session = sessions[exec.vu.idInTest - 1];
  const marker = `k6 fanout ${session.username} ${Date.now()}`;
  const socket = new WebSocket(
    `${websocketUrl}?token=${encodeURIComponent(session.websocketToken)}&user_id=${session.userId}`,
  );
  let sentAt = 0;
  let delivered = false;
  let deliveryTimeout;

  socket.addEventListener('open', () => {
    setTimeout(() => {
      sentAt = Date.now();
      socket.send(JSON.stringify({
        action: 'sendMessage',
        username: session.username,
        message: marker,
      }));
    }, 2_000);
  });

  socket.addEventListener('message', (event) => {
    const payload = JSON.parse(event.data);

    if (payload.action === 'newMessage' && payload.data?.msg === marker) {
      delivered = true;
      deliveries.add(true);
      latency.add(Date.now() - sentAt);
      check(payload, { 'broadcast returns the sent message': () => true });
      clearTimeout(deliveryTimeout);
      socket.close();
    }
  });

  socket.addEventListener('error', () => {
    deliveries.add(false);
  });

  deliveryTimeout = setTimeout(() => {
    if (!delivered) {
      deliveries.add(false);
      check(delivered, { 'broadcast returns the sent message': (value) => value });
      socket.close();
    }
  }, 10_000);
}
