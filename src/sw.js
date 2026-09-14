import { Queue } from 'workbox-background-sync';
import { CacheableResponsePlugin } from 'workbox-cacheable-response';
import { clientsClaim } from 'workbox-core';
import { ExpirationPlugin } from 'workbox-expiration';
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { NetworkFirst, NetworkOnly } from 'workbox-strategies';

self.skipWaiting();
clientsClaim();
cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

async function notifyClients(message) {
  const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  for (const client of windows) client.postMessage(message);
}

const outboxStatusCache = 'chat-outbox-status-v1';
const outboxStatusUrl = '/__pwa/outbox-status';

async function publishOutboxStatus(queue, failedDelta = 0, resetFailures = false) {
  const cache = await caches.open(outboxStatusCache);
  const existingResponse = await cache.match(outboxStatusUrl);
  const existing = existingResponse ? await existingResponse.json() : { failedMessages: 0 };
  const status = {
    type: 'CHAT_OUTBOX_STATUS',
    queuedMessages: await queue.size(),
    failedMessages: resetFailures ? 0 : Math.max(0, Number(existing.failedMessages) || 0) + failedDelta,
  };
  await cache.put(
    outboxStatusUrl,
    new Response(JSON.stringify(status), { headers: { 'Content-Type': 'application/json' } }),
  );
  await notifyClients(status);
}

async function replayOutbox({ queue }) {
  let entry;
  let replayed = 0;
  let failed = 0;

  entry = await queue.shiftRequest();
  while (entry) {
    try {
      const response = await fetch(entry.request.clone());
      if (response.ok) {
        replayed += 1;
      } else if (response.status >= 400 && response.status < 500) {
        // Authentication and validation failures will not improve with another
        // retry. Drop them and tell the UI that delivery failed.
        failed += 1;
      } else {
        throw new Error(`Message replay returned HTTP ${response.status}`);
      }
    } catch (error) {
      await queue.unshiftRequest(entry);
      await publishOutboxStatus(queue, failed);
      throw error;
    }

    entry = await queue.shiftRequest();
  }

  if (replayed > 0 || failed > 0) await publishOutboxStatus(queue, failed);
}

const outbox = new Queue('chat-message-outbox', {
  maxRetentionTime: 24 * 60,
  onSync: replayOutbox,
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'CHAT_OUTBOX_STATUS_REQUEST') {
    event.waitUntil(publishOutboxStatus(outbox));
  }
  if (event.data?.type === 'CHAT_OUTBOX_CLEAR') {
    event.waitUntil(
      (async () => {
        while (await outbox.shiftRequest()) {
          // Drain queued requests before ending the authenticated session.
        }
        await caches.delete(outboxStatusCache);
        await notifyClients({ type: 'CHAT_OUTBOX_STATUS', queuedMessages: 0, failedMessages: 0 });
        event.ports[0]?.postMessage({ cleared: true });
      })(),
    );
  }
  if (event.data?.type === 'CHAT_OUTBOX_DISMISS_FAILURES') {
    event.waitUntil(publishOutboxStatus(outbox, 0, true));
  }
});

registerRoute(
  ({ url, request }) =>
    request.method === 'GET' && url.origin === self.location.origin && url.pathname === '/api/v1/messages',
  new NetworkFirst({
    cacheName: 'chat-messages-v1',
    networkTimeoutSeconds: 3,
    plugins: [
      new CacheableResponsePlugin({ statuses: [200] }),
      new ExpirationPlugin({ maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 }),
    ],
  }),
  'GET',
);

registerRoute(
  ({ url, request }) =>
    request.method === 'POST' && url.origin === self.location.origin && url.pathname === '/api/v1/messages',
  new NetworkOnly({
    plugins: [
      {
        async fetchDidFail({ request }) {
          await outbox.pushRequest({ request });
          await publishOutboxStatus(outbox);
        },
      },
    ],
  }),
  'POST',
);

const navigationRoute = new NavigationRoute(new NetworkOnly(), {
  denylist: [/^\/api\//, /^\/auth\//],
});
navigationRoute.setCatchHandler(createHandlerBoundToURL('/offline.html'));
registerRoute(navigationRoute);
