<template>
  <div class="profile-menu">
    <button
      type="button"
      class="pwa-action"
      :aria-expanded="panelOpen"
      aria-controls="vue-profile-panel"
      @click="panelOpen = !panelOpen"
    >
      Profile
    </button>
    <section v-if="panelOpen" id="vue-profile-panel" class="profile-panel" aria-labelledby="vue-profile-title">
      <h2 id="vue-profile-title">Profile settings</h2>
      <form @submit.prevent="saveProfile">
        <label for="vue-display-name">Display name</label>
        <input id="vue-display-name" v-model="profile.display_name" maxlength="100" autocomplete="name">

        <label for="vue-theme">Theme</label>
        <select id="vue-theme" v-model="profile.theme" @change="previewTheme">
          <option value="system">System</option>
          <option value="light">Light</option>
          <option value="dark">Dark</option>
        </select>

        <label for="vue-presence">Presence</label>
        <select id="vue-presence" v-model="profile.presence">
          <option value="online">Online</option>
          <option value="away">Away</option>
          <option value="busy">Busy</option>
        </select>

        <label><input v-model="profile.notification_prefs.desktop" type="checkbox"> Desktop notifications</label>
        <label><input v-model="profile.notification_prefs.sound" type="checkbox"> Sounds</label>
        <button type="submit" class="send-btn" :disabled="saving">{{ saving ? 'Saving…' : 'Save profile' }}</button>
      </form>

      <form @submit.prevent="uploadAvatar">
        <label for="vue-avatar">Avatar (JPEG, PNG, or WebP; max 2 MB and 1024×1024)</label>
        <input id="vue-avatar" ref="avatar" type="file" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit" class="clear-btn" :disabled="uploading">
          {{ uploading ? 'Uploading…' : 'Upload avatar' }}
        </button>
      </form>
      <p v-if="status" class="profile-status" role="status">{{ status }}</p>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
    </section>
  </div>
</template>

<script>
  import { applyTheme, csrfToken, editableProfile, readJson } from '../js/profile.js';

  export default {
    emits: ['updated'],
    data() {
      return {
        panelOpen: false,
        saving: false,
        uploading: false,
        status: '',
        error: '',
        profile: {
          display_name: '',
          theme: 'system',
          presence: 'online',
          notification_prefs: { desktop: true, sound: true },
        },
      };
    },
    mounted() {
      void this.loadProfile();
    },
    methods: {
      async loadProfile() {
        try {
          const payload = await readJson(await fetch(this.$chatRoutes.profile));
          this.profile = editableProfile(payload.profile);
          applyTheme(this.profile.theme);
          this.$emit('updated', this.profile);
        } catch (error) {
          this.error = error.message;
        }
      },
      previewTheme() {
        applyTheme(this.profile.theme);
      },
      async saveProfile() {
        this.saving = true;
        this.error = '';
        this.status = '';
        try {
          const response = await fetch(this.$chatRoutes.profile, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(this.profile),
          });
          const payload = await readJson(response);
          this.profile = editableProfile(payload.profile);
          applyTheme(this.profile.theme);
          this.status = 'Profile saved.';
          this.$emit('updated', this.profile);
        } catch (error) {
          this.error = error.message;
        } finally {
          this.saving = false;
        }
      },
      async uploadAvatar() {
        const file = this.$refs.avatar?.files?.[0];
        if (!file) return;
        this.uploading = true;
        this.error = '';
        try {
          const body = new FormData();
          body.append('avatar', file);
          const payload = await readJson(
            await fetch(this.$chatRoutes.profileAvatar, {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': csrfToken() },
              body,
            }),
          );
          this.profile.avatar_url = payload.avatar_url;
          this.status = 'Avatar uploaded.';
          this.$emit('updated', this.profile);
        } catch (error) {
          this.error = error.message;
        } finally {
          this.uploading = false;
        }
      },
    },
  };
</script>
