<script>
  import { onMount } from 'svelte';
  import { applyTheme, csrfToken, editableProfile, readJson } from '../js/profile.js';

  let { routes, onupdated } = $props();
  let open = $state(false);
  let saving = $state(false);
  let uploading = $state(false);
  let status = $state('');
  let error = $state('');
  let avatarInput = $state();
  let profile = $state({
    display_name: '',
    theme: 'system',
    presence: 'online',
    notification_prefs: { desktop: true, sound: true },
  });

  async function loadProfile() {
    try {
      const payload = await readJson(await fetch(routes.profile));
      profile = editableProfile(payload.profile);
      applyTheme(profile.theme);
      onupdated(profile);
    } catch (caught) {
      error = caught.message;
    }
  }

  async function saveProfile(event) {
    event.preventDefault();
    saving = true;
    error = '';
    status = '';
    try {
      const payload = await readJson(
        await fetch(routes.profile, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
          body: JSON.stringify(profile),
        }),
      );
      profile = editableProfile(payload.profile);
      applyTheme(profile.theme);
      status = 'Profile saved.';
      onupdated(profile);
    } catch (caught) {
      error = caught.message;
    } finally {
      saving = false;
    }
  }

  async function uploadAvatar(event) {
    event.preventDefault();
    const file = avatarInput?.files?.[0];
    if (!file) return;
    uploading = true;
    error = '';
    try {
      const body = new FormData();
      body.append('avatar', file);
      const payload = await readJson(
        await fetch(routes.profileAvatar, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken() },
          body,
        }),
      );
      profile.avatar_url = payload.avatar_url;
      status = 'Avatar uploaded.';
      onupdated(profile);
    } catch (caught) {
      error = caught.message;
    } finally {
      uploading = false;
    }
  }

  onMount(() => {
    void loadProfile();
  });
</script>

<div class="profile-menu">
  <button
    type="button"
    class="pwa-action"
    aria-expanded={open}
    aria-controls="svelte-profile-panel"
    onclick={() => open = !open}
  >
    Profile
  </button>
  {#if open}
    <section id="svelte-profile-panel" class="profile-panel" aria-labelledby="svelte-profile-title">
      <h2 id="svelte-profile-title">Profile settings</h2>
      <form onsubmit={saveProfile}>
        <label for="svelte-display-name">Display name</label>
        <input id="svelte-display-name" bind:value={profile.display_name} maxlength="100" autocomplete="name">
        <label for="svelte-theme">Theme</label>
        <select id="svelte-theme" bind:value={profile.theme} onchange={() => applyTheme(profile.theme)}>
          <option value="system">System</option>
          <option value="light">Light</option>
          <option value="dark">Dark</option>
        </select>
        <label for="svelte-presence">Presence</label>
        <select id="svelte-presence" bind:value={profile.presence}>
          <option value="online">Online</option>
          <option value="away">Away</option>
          <option value="busy">Busy</option>
        </select>
        <label><input type="checkbox" bind:checked={profile.notification_prefs.desktop}> Desktop notifications</label>
        <label><input type="checkbox" bind:checked={profile.notification_prefs.sound}> Sounds</label>
        <button type="submit" class="send-btn" disabled={saving}>{saving ? 'Saving…' : 'Save profile'}</button>
      </form>
      <form onsubmit={uploadAvatar}>
        <label for="svelte-avatar">Avatar (JPEG, PNG, or WebP; max 2 MB and 1024×1024)</label>
        <input id="svelte-avatar" bind:this={avatarInput} type="file" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit" class="clear-btn" disabled={uploading}>
          {uploading ? 'Uploading…' : 'Upload avatar'}
        </button>
      </form>
      {#if status}
        <p class="profile-status" role="status">{status}</p>
      {/if}
      {#if error}
        <p class="error" role="alert">{error}</p>
      {/if}
    </section>
  {/if}
</div>
