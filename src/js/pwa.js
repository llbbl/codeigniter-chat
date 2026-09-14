import { registerSW } from 'virtual:pwa-register';

const listeners = new Set();
let deferredInstallPrompt = null;
let initialized = false;

const state = {
  online: navigator.onLine,
  installAvailable: false,
  serviceWorkerReady: false,
  queuedMessages: 0,
  failedMessages: 0,
};

function emit() {
  const snapshot = { ...state };
  for (const listener of listeners) listener(snapshot);
}

function setOnline(online) {
  state.online = online;
  emit();
}

export function setupPwa() {
  if (initialized) return;
  initialized = true;

  registerSW({
    immediate: true,
    onRegisteredSW(_url, registration) {
      state.serviceWorkerReady = Boolean(registration);
      emit();
      registration?.active?.postMessage({ type: 'CHAT_OUTBOX_STATUS_REQUEST' });
    },
    onRegisterError(error) {
      console.error('Service worker registration failed:', error);
    },
  });

  window.addEventListener('online', () => setOnline(true));
  window.addEventListener('offline', () => setOnline(false));
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    state.installAvailable = true;
    emit();
  });
  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    state.installAvailable = false;
    emit();
  });
  navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data?.type !== 'CHAT_OUTBOX_STATUS') return;

    state.queuedMessages = Math.max(0, Number(event.data.queuedMessages) || 0);
    state.failedMessages = Math.max(0, Number(event.data.failedMessages) || 0);
    emit();
  });
  navigator.serviceWorker?.ready.then((registration) => {
    registration.active?.postMessage({ type: 'CHAT_OUTBOX_STATUS_REQUEST' });
  });

  document.addEventListener('click', async (event) => {
    const logout = event.target.closest?.('a[href="/auth/logout"]');
    if (!logout) return;

    event.preventDefault();
    try {
      await clearPrivatePwaData();
    } finally {
      window.location.assign(logout.href);
    }
  });
}

export function subscribePwa(listener) {
  listeners.add(listener);
  listener({ ...state });
  return () => listeners.delete(listener);
}

export async function promptInstall() {
  if (!deferredInstallPrompt) return false;

  await deferredInstallPrompt.prompt();
  const choice = await deferredInstallPrompt.userChoice;
  deferredInstallPrompt = null;
  state.installAvailable = false;
  emit();

  return choice.outcome === 'accepted';
}

export function userScopedMessagesUrl(url, userId) {
  const scoped = new URL(url, window.location.origin);
  scoped.searchParams.set('pwa_user', String(Number(userId) || 0));
  return scoped.toString();
}

export async function dismissFailedMessages() {
  state.failedMessages = 0;
  emit();

  if (!('serviceWorker' in navigator)) return;
  const registration = await serviceWorkerReadyWithin();
  registration.active?.postMessage({ type: 'CHAT_OUTBOX_DISMISS_FAILURES' });
}

function clearDeliveryState() {
  state.queuedMessages = 0;
  state.failedMessages = 0;
  emit();
}

function serviceWorkerReadyWithin(timeout = 1000) {
  return Promise.race([
    navigator.serviceWorker.ready,
    new Promise((resolve) => window.setTimeout(() => resolve(null), timeout)),
  ]);
}

async function clearServiceWorkerOutbox() {
  if (!('serviceWorker' in navigator)) return;

  const registration = await serviceWorkerReadyWithin();
  if (!registration?.active) return;

  await Promise.race([
    new Promise((resolve) => {
      const channel = new MessageChannel();
      channel.port1.onmessage = resolve;
      registration.active.postMessage({ type: 'CHAT_OUTBOX_CLEAR' }, [channel.port2]);
    }),
    new Promise((resolve) => window.setTimeout(resolve, 1000)),
  ]);
}

export async function clearPrivatePwaData() {
  await clearServiceWorkerOutbox();
  clearDeliveryState();

  if ('caches' in window) {
    const keys = await caches.keys();
    await Promise.all(
      keys
        .filter((key) => key.startsWith('chat-messages-') || key.startsWith('chat-outbox-status-'))
        .map((key) => caches.delete(key)),
    );
  }
}

function urlBase64ToUint8Array(value) {
  const padding = '='.repeat((4 - (value.length % 4)) % 4);
  const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
  return Uint8Array.from(atob(base64), (character) => character.codePointAt(0));
}

export async function enablePushNotifications({ endpoint, csrfTokenName, csrfToken, publicKey }) {
  if (!publicKey || !('serviceWorker' in navigator) || !('PushManager' in window)) return false;

  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(publicKey),
  });
  const response = await fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    body: JSON.stringify({ ...subscription.toJSON(), [csrfTokenName]: csrfToken }),
  });

  if (!response.ok) throw new Error('Could not save the push subscription.');
  return true;
}
