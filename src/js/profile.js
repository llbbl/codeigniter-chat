export function applyTheme(theme) {
  const selectedTheme = ['light', 'dark', 'system'].includes(theme) ? theme : 'system';
  document.documentElement.dataset.theme = selectedTheme;
  document.documentElement.style.colorScheme = selectedTheme === 'system' ? 'light dark' : selectedTheme;
}

export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export async function readJson(response) {
  const payload = await response.json();
  if (payload?.csrf_token) {
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', payload.csrf_token);
  }
  if (!response.ok) {
    throw new Error(payload?.error?.message || 'The profile request failed.');
  }

  return payload;
}

export function editableProfile(profile) {
  const editablePresences = ['online', 'away', 'busy'];

  return {
    ...profile,
    presence: editablePresences.includes(profile?.presence) ? profile.presence : 'online',
    notification_prefs: profile?.notification_prefs || { desktop: true, sound: true },
  };
}
