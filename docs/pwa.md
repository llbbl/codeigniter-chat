# Progressive Web App support

The Vue (`/chat/vue`) and Svelte (`/chat/svelte`) clients share one installable PWA. Vite emits `/manifest.webmanifest` and `/sw.js`; Workbox precaches only the public application shell (versioned JavaScript/CSS, icons, and the offline page).

## Build

```sh
corepack pnpm install --frozen-lockfile
corepack pnpm pwa:assets # only needed after changing public/pwa-icon.svg
corepack pnpm build
```

The normal build generates the manifest and root-scoped service worker. Serve the `public/` directory over HTTPS (localhost is also accepted by browsers) and do not move `sw.js` under `/dist`, because that would narrow its scope.

## Offline behavior

- Navigation uses the cached public offline page when the network is unavailable. Authenticated PHP pages are deliberately not cached.
- `GET /api/v1/messages` is network-first with a three-second timeout and a one-day maximum cache age. Clients add `pwa_user=<id>` to the cache key so cached conversations are not mixed between users.
- `POST /api/v1/messages` uses Workbox Background Sync and remains queued for up to 24 hours after a network failure. Both clients retain queued state until the service worker confirms replay; permanent authentication or validation failures are shown as failed delivery.
- Logging out clears cached message responses and the local background-sync database.

Queued writes retain the authenticated session and CSRF token captured when they were created. A replay rejected because the session or token expired is not silently represented as delivered; reopening the app refreshes from the server.

## Web Push subscriptions

Set the public VAPID key in the environment:

```dotenv
push.vapidPublicKey=YOUR_URL_SAFE_BASE64_PUBLIC_KEY
```

When configured, supported browsers show an **Enable notifications** action. The client creates a browser `PushSubscription` and sends it to authenticated endpoint `POST /api/v1/push-subscriptions`. `DELETE /api/v1/push-subscriptions` removes the current user's endpoint. The migration stores endpoint hashes, encryption keys, and ownership; sending push payloads and managing the private VAPID key belong in the notification delivery service.

Run migrations after deployment:

```sh
php spark migrate
```

## Verification

1. Run `corepack pnpm build` and confirm `public/manifest.webmanifest` and `public/sw.js` exist.
2. Serve the app over HTTPS, authenticate, and visit both framework clients.
3. In browser application tools, confirm the manifest is installable and the service worker controls the page.
4. Load messages once, switch the browser network to Offline, and confirm the offline indicator and cached messages appear.
5. Post while offline, restore the network, and confirm the queued notice clears only after the service worker reports a successful replay.
6. Run Lighthouse against a production build. Address installability and best-practice findings; authenticated pages may require Lighthouse to run with a preserved session.

Mutation testing remains manual-only through the `Mutation Tests` workflow's `workflow_dispatch` trigger; PWA changes do not make it a pull-request check.
