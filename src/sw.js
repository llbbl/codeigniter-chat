import { BackgroundSyncPlugin } from 'workbox-background-sync';
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
      if (replayed > 0 || failed > 0) {
        await notifyClients({ type: 'CHAT_OUTBOX_RESULT', replayed, failed });
      }
      throw error;
    }

    entry = await queue.shiftRequest();
  }

  if (replayed > 0 || failed > 0) {
    await notifyClients({ type: 'CHAT_OUTBOX_RESULT', replayed, failed });
  }
}

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
      new BackgroundSyncPlugin('chat-message-outbox', {
        maxRetentionTime: 24 * 60,
        onSync: replayOutbox,
      }),
    ],
  }),
  'POST',
);

registerRoute(
  new NavigationRoute(createHandlerBoundToURL('/offline.html'), {
    denylist: [/^\/api\//, /^\/auth\//],
  }),
);
