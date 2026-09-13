import { registerSW } from 'virtual:pwa-register';

const listeners = new Set();
let deferredInstallPrompt = null;
let initialized = false;
let currentUserId = 0;

const state = {
  online: navigator.onLine,
  installAvailable: false,
  serviceWorkerReady: false,
  queuedMessages: 0,
  failedMessages: 0,
};

function storageKey() {
  return `chat-pwa-outbox:${currentUserId}`;
}

function readQueuedMessages() {
  try {
    const value = Number.parseInt(localStorage.getItem(storageKey()) || '0', 10);
    return Number.isFinite(value) ? value : 0;
  } catch {
    return 0;
  }
}

function failedStorageKey() {
  return `chat-pwa-failed:${currentUserId}`;
}

function readFailedMessages() {
  try {
    const value = Number.parseInt(localStorage.getItem(failedStorageKey()) || '0', 10);
    return Number.isFinite(value) ? value : 0;
  } catch {
    return 0;
  }
}

function emit() {
  const snapshot = { ...state };
  for (const listener of listeners) listener(snapshot);
}

function setOnline(online) {
  state.online = online;
  emit();
}

export function setupPwa(userId) {
  currentUserId = Number(userId) || 0;
  state.queuedMessages = readQueuedMessages();
  state.failedMessages = readFailedMessages();

  if (initialized) return;
  initialized = true;

  registerSW({
    immediate: true,
    onRegisteredSW(_url, registration) {
      state.serviceWorkerReady = Boolean(registration);
      emit();
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
    if (event.data?.type !== 'CHAT_OUTBOX_RESULT') return;

    const replayed = Math.max(0, Number(event.data.replayed) || 0);
    const failed = Math.max(0, Number(event.data.failed) || 0);
    state.queuedMessages = Math.max(0, state.queuedMessages - replayed - failed);
    state.failedMessages += failed;
    persistDeliveryState();
    emit();
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

export function recordQueuedMessage() {
  state.queuedMessages += 1;
  try {
    localStorage.setItem(storageKey(), String(state.queuedMessages));
  } catch {
    // Storage can be unavailable in strict privacy modes; in-memory state remains useful.
  }
  emit();
}

function persistDeliveryState() {
  try {
    if (state.queuedMessages > 0) localStorage.setItem(storageKey(), String(state.queuedMessages));
    else localStorage.removeItem(storageKey());

    if (state.failedMessages > 0) localStorage.setItem(failedStorageKey(), String(state.failedMessages));
    else localStorage.removeItem(failedStorageKey());
  } catch {
    // In-memory status still works when persistent storage is unavailable.
  }
}

export function clearQueuedMessages() {
  state.queuedMessages = 0;
  state.failedMessages = 0;
  try {
    localStorage.removeItem(storageKey());
    localStorage.removeItem(failedStorageKey());
  } catch {
    // Nothing else is required when persistent storage is unavailable.
  }
  emit();
}

export async function clearPrivatePwaData() {
  clearQueuedMessages();

  if ('caches' in window) {
    const keys = await caches.keys();
    await Promise.all(keys.filter((key) => key.startsWith('chat-messages-')).map((key) => caches.delete(key)));
  }

  if ('indexedDB' in window) indexedDB.deleteDatabase('workbox-background-sync');
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
